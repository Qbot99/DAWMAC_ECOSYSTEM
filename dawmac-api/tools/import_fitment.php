<?php
/**
 * Import danych dopasowań (marki, modele, generacje, rozmiary) z plików JSON.
 *
 * Najpierw sprawdzamy CAŁY plik. Jeden błędny wpis = nic nie zostaje
 * zapisane, a lista błędów mówi dokładnie, gdzie poprawić. Te dane trafią
 * do klientów, więc połowiczny import jest gorszy niż żaden.
 *
 * Import tylko dopisuje i aktualizuje — niczego nie usuwa. Generacji
 * oznaczonej w bazie jako zweryfikowana (verified = 1) nie nadpisuje
 * danymi niezweryfikowanymi; taki wpis jest pomijany z komunikatem.
 *
 *   php import_fitment.php                         — sprawdza data/fitment/*.json, bez bazy
 *   php import_fitment.php plik.json               — sprawdza wskazany plik
 *   php import_fitment.php --apply [plik.json ...] — sprawdza i zapisuje
 *
 * Format pliku: patrz data/fitment/README.md.
 */

$root = getenv('DAWMAC_DOCROOT')
    ?: '/home/klient.dhosting.pl/dawmac/api.dawmacpolska.pl/public_html';

$_SERVER['DOCUMENT_ROOT'] = $root;
$_SERVER['REQUEST_METHOD'] = 'CLI';
ini_set('display_errors', 'stderr');

require_once is_file(__DIR__ . '/../api/fitment/lib/fitment.php')
    ? __DIR__ . '/../api/fitment/lib/fitment.php'
    : $root . '/api/fitment/lib/fitment.php';

$apply = in_array('--apply', $argv, true);
$pliki = array_values(array_filter(array_slice($argv, 1), fn($a) => strpos($a, '--') !== 0));

if (!$pliki) {
    $pliki = glob(__DIR__ . '/../data/fitment/*.json') ?: [];
}
if (!$pliki) {
    exit("Brak plików do importu.\n");
}

// Połączenie przed pierwszym echo: db.php wysyła nagłówki HTTP,
// a po wypisaniu czegokolwiek PHP sypie ostrzeżeniami.
if ($apply) {
    require $root . '/api/gallery/db.php';
}

echo $apply ? "TRYB: zapis\n\n" : "TRYB: podgląd (bez bazy). Dodaj --apply.\n\n";

/* ------------------------------------------------------------------ */
/* Walidacja — wszystko, zanim cokolwiek dotknie bazy                  */
/* ------------------------------------------------------------------ */

$bledy   = [];
$wpisy   = []; // spłaszczone generacje gotowe do zapisu
$liczniki = ['marek' => 0, 'modeli' => 0, 'generacji' => 0, 'rozmiarów' => 0];

foreach ($pliki as $plik) {
    $nazwaPliku = basename($plik);
    $dane = json_decode((string) @file_get_contents($plik), true);

    if (!is_array($dane) || !isset($dane['makes']) || !is_array($dane['makes'])) {
        $bledy[] = "$nazwaPliku: to nie jest poprawny JSON z listą \"makes\"";
        continue;
    }

    $zrodloPliku = isset($dane['source']) ? (string) $dane['source'] : null;

    foreach ($dane['makes'] as $make) {
        $makeName = trim((string) ($make['name'] ?? ''));
        $makeSlug = dawmac_fit_slug($makeName);
        $sciezkaMarki = "$nazwaPliku › " . ($makeName !== '' ? $makeName : '?');

        if ($makeSlug === '') {
            $bledy[] = "$sciezkaMarki: brak nazwy marki";
            continue;
        }

        $country = $make['country'] ?? null;
        if ($country !== null && !preg_match('~^[A-Z]{2}$~', (string) $country)) {
            $bledy[] = "$sciezkaMarki: country musi być kodem kraju z 2 wielkich liter (np. DE, CN) albo null";
        }

        if (!isset($make['models']) || !is_array($make['models'])) {
            $bledy[] = "$sciezkaMarki: brak listy \"models\"";
            continue;
        }

        $liczniki['marek']++;

        foreach ($make['models'] as $model) {
            $modelName = trim((string) ($model['name'] ?? ''));
            $modelSlug = dawmac_fit_slug($modelName);
            $sciezkaModelu = "$sciezkaMarki › " . ($modelName !== '' ? $modelName : '?');

            if ($modelSlug === '') {
                $bledy[] = "$sciezkaModelu: brak nazwy modelu";
                continue;
            }
            if (!isset($model['generations']) || !is_array($model['generations'])) {
                $bledy[] = "$sciezkaModelu: brak listy \"generations\"";
                continue;
            }

            $liczniki['modeli']++;

            foreach ($model['generations'] as $g) {
                $genName = trim((string) ($g['name'] ?? ''));
                $sciezka = "$sciezkaModelu › " . ($genName !== '' ? $genName : '?');
                $klucz   = $makeSlug . '/' . $modelSlug . '/' . dawmac_fit_slug($genName);

                foreach (dawmac_fit_validate_generation(is_array($g) ? $g : []) as $blad) {
                    $bledy[] = "$sciezka: $blad";
                }

                if (isset($wpisy[$klucz])) {
                    $bledy[] = "$sciezka: ta generacja występuje w danych dwa razy";
                }

                $wpisy[$klucz] = [
                    'make'    => ['name' => $makeName, 'slug' => $makeSlug, 'country' => $country],
                    'model'   => ['name' => $modelName, 'slug' => $modelSlug],
                    'gen'     => is_array($g) ? $g : [],
                    'source'  => isset($g['source']) ? (string) $g['source'] : $zrodloPliku,
                    'sciezka' => $sciezka,
                ];

                $liczniki['generacji']++;
                $liczniki['rozmiarów'] += is_array($g['wheels'] ?? null) ? count($g['wheels']) : 0;
            }
        }
    }
}

if ($bledy) {
    echo "BŁĘDY (" . count($bledy) . ") — nic nie zapisano:\n";
    foreach ($bledy as $b) {
        echo "  - $b\n";
    }
    exit(1);
}

printf(
    "Dane poprawne: %d marek, %d modeli, %d generacji, %d rozmiarów felg.\n",
    $liczniki['marek'], $liczniki['modeli'], $liczniki['generacji'], $liczniki['rozmiarów']
);

if (!$apply) {
    echo "\nNic nie zapisano.\n";
    exit(0);
}

/* ------------------------------------------------------------------ */
/* Zapis — jedna transakcja                                            */
/* ------------------------------------------------------------------ */

$wynik = ['nowe' => 0, 'zmienione' => 0, 'bez zmian' => 0, 'pominięte' => 0, 'rozmiary nowe' => 0];

$idMarki  = [];
$idModelu = [];

$conn->begin_transaction();

try {
    foreach ($wpisy as $w) {
        $m = $w['make'];

        if (!isset($idMarki[$m['slug']])) {
            $stmt = $conn->prepare(
                "INSERT INTO fit_make (name, slug, country, created_at, updated_at)
                 VALUES (?, ?, ?, NOW(), NOW())
                 ON DUPLICATE KEY UPDATE name = VALUES(name), country = VALUES(country), updated_at = NOW()"
            );
            $stmt->bind_param('sss', $m['name'], $m['slug'], $m['country']);
            $stmt->execute();
            $stmt->close();

            $idMarki[$m['slug']] = dawmac_fit_import_id($conn, 'SELECT id FROM fit_make WHERE slug = ?', 's', [$m['slug']]);
        }
        $makeId = $idMarki[$m['slug']];

        $kluczModelu = $makeId . '/' . $w['model']['slug'];
        if (!isset($idModelu[$kluczModelu])) {
            $stmt = $conn->prepare(
                "INSERT INTO fit_model (make_id, name, slug, created_at, updated_at)
                 VALUES (?, ?, ?, NOW(), NOW())
                 ON DUPLICATE KEY UPDATE name = VALUES(name), updated_at = NOW()"
            );
            $stmt->bind_param('iss', $makeId, $w['model']['name'], $w['model']['slug']);
            $stmt->execute();
            $stmt->close();

            $idModelu[$kluczModelu] = dawmac_fit_import_id(
                $conn, 'SELECT id FROM fit_model WHERE make_id = ? AND slug = ?', 'is', [$makeId, $w['model']['slug']]
            );
        }
        $modelId = $idModelu[$kluczModelu];

        $g        = $w['gen'];
        $name     = trim((string) $g['name']);
        $slug     = dawmac_fit_slug($name);
        $pcd      = dawmac_fit_parse_pcd($g['pcd']);
        $pcdMm    = number_format($pcd['mm'], 2, '.', '');
        $cb       = number_format((float) $g['center_bore'], 2, '.', '');
        $thread   = dawmac_fit_parse_thread($g['thread']);
        $yearFrom = $g['years'][0];
        $yearTo   = $g['years'][1] ?? null;
        $torque   = $g['torque_nm'] ?? null;
        $source   = $w['source'] !== null ? mb_substr($w['source'], 0, 255, 'UTF-8') : null;
        $verified = !empty($g['verified']) ? 1 : 0;

        $stmt = $conn->prepare(
            'SELECT id, verified, name, year_from, year_to, pcd_holes, pcd_mm, center_bore,
                    thread, fastener, torque_nm, source
             FROM fit_generation WHERE model_id = ? AND slug = ?'
        );
        $stmt->bind_param('is', $modelId, $slug);
        $stmt->execute();
        $istniejaca = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($istniejaca && (int) $istniejaca['verified'] === 1 && $verified === 0) {
            echo "  POMIJAM {$w['sciezka']}: w bazie jest zweryfikowana, a import nie\n";
            $wynik['pominięte']++;
            continue;
        }

        $nowe = [
            'name' => $name, 'year_from' => $yearFrom, 'year_to' => $yearTo,
            'pcd_holes' => $pcd['holes'], 'pcd_mm' => $pcdMm, 'center_bore' => $cb,
            'thread' => $thread, 'fastener' => $g['fastener'], 'torque_nm' => $torque,
            'source' => $source, 'verified' => $verified,
        ];

        if ($istniejaca) {
            $genId = (int) $istniejaca['id'];

            // mysqli oddaje liczby jako tekst — porównujemy tekstowo, NULL osobno.
            $zmiana = false;
            foreach ($nowe as $kolumna => $wartosc) {
                $stara = $istniejaca[$kolumna];
                if (($stara === null) !== ($wartosc === null) || (string) $stara !== (string) $wartosc) {
                    $zmiana = true;
                    break;
                }
            }

            if ($zmiana) {
                $stmt = $conn->prepare(
                    "UPDATE fit_generation SET
                        name = ?, year_from = ?, year_to = ?, pcd_holes = ?, pcd_mm = ?, center_bore = ?,
                        thread = ?, fastener = ?, torque_nm = ?, source = ?, verified = ?, updated_at = NOW()
                     WHERE id = ?"
                );
                $stmt->bind_param(
                    'siiissssisii',
                    $name, $yearFrom, $yearTo, $pcd['holes'], $pcdMm, $cb,
                    $thread, $g['fastener'], $torque, $source, $verified, $genId
                );
                $stmt->execute();
                $stmt->close();
                $wynik['zmienione']++;
            } else {
                $wynik['bez zmian']++;
            }
        } else {
            $stmt = $conn->prepare(
                "INSERT INTO fit_generation
                    (model_id, name, slug, year_from, year_to, pcd_holes, pcd_mm, center_bore,
                     thread, fastener, torque_nm, source, verified, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())"
            );
            $stmt->bind_param(
                'issiiissssisi',
                $modelId, $name, $slug, $yearFrom, $yearTo, $pcd['holes'], $pcdMm, $cb,
                $thread, $g['fastener'], $torque, $source, $verified
            );
            $stmt->execute();
            $genId = (int) $stmt->insert_id;
            $stmt->close();
            $wynik['nowe']++;
        }

        foreach ($g['wheels'] ?? [] as $rozmiar) {
            $rim   = dawmac_fit_parse_rim($rozmiar['size']);
            $tire  = isset($rozmiar['tire']) ? dawmac_fit_parse_tire($rozmiar['tire'])['label'] : '';
            $axle  = $rozmiar['axle'] ?? 'both';
            $oem   = !empty($rozmiar['oem']) ? 1 : 0;
            $width = number_format($rim['width'], 1, '.', '');

            $stmt = $conn->prepare(
                "INSERT INTO fit_wheel (generation_id, axle, diameter, width, et, tire, is_oem)
                 VALUES (?, ?, ?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE is_oem = VALUES(is_oem)"
            );
            $stmt->bind_param('isisisi', $genId, $axle, $rim['diameter'], $width, $rim['et'], $tire, $oem);
            $stmt->execute();
            if ($stmt->affected_rows === 1) {
                $wynik['rozmiary nowe']++;
            }
            $stmt->close();
        }
    }

    $conn->commit();
} catch (Throwable $e) {
    $conn->rollback();
    exit("Błąd zapisu, wycofano całość: " . $e->getMessage() . "\n");
}

echo "\nGeneracje: {$wynik['nowe']} nowe, {$wynik['zmienione']} zmienione, {$wynik['bez zmian']} bez zmian, {$wynik['pominięte']} pominięte.\n";
echo "Rozmiary felg: {$wynik['rozmiary nowe']} nowe.\n";

/* ------------------------------------------------------------------ */

function dawmac_fit_import_id(mysqli $conn, string $sql, string $types, array $params): int
{
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $id = (int) ($stmt->get_result()->fetch_row()[0] ?? 0);
    $stmt->close();

    return $id;
}
