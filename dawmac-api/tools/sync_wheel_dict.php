<?php
/**
 * SŁOWNIK FELG — synchronizacja katalogu sklepu do bazy galerii.
 *
 * Sklep prowadzi jedyną utrzymywaną listę felg, jaka w ogóle istnieje:
 * atrybuty pa_producent (157 pozycji) i pa_model (3394). Ten skrypt przenosi
 * z niej realnie występujące pary producent+model do tabeli `wheel_dict`
 * w bazie galerii, żeby panel mógł podpowiadać zamiast pozwalać wpisywać
 * z palca. Dzięki temu każdy nowy wpis od razu pasuje do produktu.
 *
 * Bierzemy PARY, nie iloczyn kartezjański — 2616 kombinacji, które faktycznie
 * istnieją na opublikowanych produktach, a nie 157 × 3394 bzdur.
 *
 * Uruchamianie (z ~/api.dawmacpolska.pl/tools):
 *
 *   php sync_wheel_dict.php            — podgląd, nic nie zapisuje
 *   php sync_wheel_dict.php --apply    — tworzy tabelę i synchronizuje
 *
 * Do crona raz na dobę. Nowy model felgi w sklepie = nowa podpowiedź w panelu
 * następnego dnia, bez żadnej ręcznej roboty.
 */

$root = getenv('DAWMAC_DOCROOT')
    ?: '/home/klient.dhosting.pl/dawmac/api.dawmacpolska.pl/public_html';

$WP_CONFIG = getenv('DAWMAC_WPCONFIG')
    ?: '/home/klient.dhosting.pl/dawmac/dawmac.pl-aid9/public_html/wp-config.php';

$_SERVER['DOCUMENT_ROOT'] = $root;
$_SERVER['REQUEST_METHOD'] = 'CLI';

require $root . '/api/gallery/lib/wheel_norm.php';
require $root . '/api/gallery/db.php';   // $conn = baza galerii

$apply = in_array('--apply', $argv, true);

echo $apply ? "TRYB: zapis\n\n" : "TRYB: podgląd — nic nie zostanie zapisane. Dodaj --apply.\n\n";

/* ------------------------------------------------------------------ */
/* 1. Połączenie ze sklepem                                            */
/* ------------------------------------------------------------------ */

/**
 * Czytamy dane dostępowe wprost z wp-config.php zamiast ładować cały
 * WordPress — skrypt ma być szybki i nie ruszać sklepu.
 */
function wp_stala(string $plik, string $nazwa): string
{
    static $tresc = null;
    if ($tresc === null) {
        $tresc = (string) file_get_contents($plik);
    }

    $wzor = '~define\(\s*[\'"]' . preg_quote($nazwa, '~') . '[\'"]\s*,\s*[\'"](.*?)[\'"]\s*\)~s';

    return preg_match($wzor, $tresc, $m) ? $m[1] : '';
}

$wp = new mysqli(
    wp_stala($WP_CONFIG, 'DB_HOST'),
    wp_stala($WP_CONFIG, 'DB_USER'),
    wp_stala($WP_CONFIG, 'DB_PASSWORD'),
    wp_stala($WP_CONFIG, 'DB_NAME')
);

if ($wp->connect_error) {
    exit("Nie udało się połączyć ze sklepem: " . $wp->connect_error . "\n");
}
$wp->set_charset('utf8mb4');

/* ------------------------------------------------------------------ */
/* 2. Pary producent + model z opublikowanych produktów                */
/* ------------------------------------------------------------------ */

$sql = "SELECT tp.name AS producent, tm.name AS model, COUNT(DISTINCT p.ID) AS produktow
        FROM wp_posts p
        JOIN wp_term_relationships trp  ON trp.object_id = p.ID
        JOIN wp_term_taxonomy   ttp ON ttp.term_taxonomy_id = trp.term_taxonomy_id AND ttp.taxonomy = 'pa_producent'
        JOIN wp_terms            tp  ON tp.term_id = ttp.term_id
        JOIN wp_term_relationships trm  ON trm.object_id = p.ID
        JOIN wp_term_taxonomy   ttm ON ttm.term_taxonomy_id = trm.term_taxonomy_id AND ttm.taxonomy = 'pa_model'
        JOIN wp_terms            tm  ON tm.term_id = ttm.term_id
        WHERE p.post_type = 'product' AND p.post_status = 'publish'
        GROUP BY tp.name, tm.name";

$wynik = $wp->query($sql);
if (!$wynik) {
    exit("Błąd zapytania do sklepu: " . $wp->error . "\n");
}

$pary = [];
while ($r = $wynik->fetch_assoc()) {
    $bn = dawmac_wheel_norm($r['producent']);
    $mn = dawmac_wheel_norm($r['model']);

    if ($bn === '' || $mn === '') {
        continue;
    }

    $klucz = $bn . "\x1f" . $mn;

    // Ta sama para po normalizacji może przyjść z kilku wariantów zapisu
    // także po stronie sklepu — sumujemy produkty, zapis bierzemy pierwszy.
    if (isset($pary[$klucz])) {
        $pary[$klucz]['produktow'] += (int) $r['produktow'];
    } else {
        $pary[$klucz] = [
            'brand'      => trim($r['producent']),
            'model'      => trim($r['model']),
            'brand_norm' => $bn,
            'model_norm' => $mn,
            'produktow'  => (int) $r['produktow'],
        ];
    }
}
$wp->close();

printf("Par producent+model w sklepie: %d\n", count($pary));

/* ------------------------------------------------------------------ */
/* 3. Ile aut w galerii przypada na każdą parę                         */
/* ------------------------------------------------------------------ */

$wGalerii = [];
$r = $conn->query(
    "SELECT w.brand_norm, w.model_norm, COUNT(DISTINCT p.id) n
     FROM wheel w JOIN project p ON p.wheel_id = w.id
     WHERE w.brand_norm <> '' AND w.model_norm <> ''
     GROUP BY w.brand_norm, w.model_norm"
);
while ($x = $r->fetch_assoc()) {
    $wGalerii[$x['brand_norm'] . "\x1f" . $x['model_norm']] = (int) $x['n'];
}

$zeZdjeciami = count(array_intersect_key($pary, $wGalerii));
printf("Z tego ma już zdjęcia w galerii:  %d\n", $zeZdjeciami);
printf("Par w galerii bez odpowiednika w sklepie: %d\n\n", count(array_diff_key($wGalerii, $pary)));

if (!$apply) {
    echo "Przykłady (5 najliczniejszych):\n";
    $probka = $pary;
    uasort($probka, static fn($a, $b) => $b['produktow'] <=> $a['produktow']);
    foreach (array_slice($probka, 0, 5) as $klucz => $p) {
        printf("  %-22s %-14s %4d produktów, %3d aut w galerii\n",
            $p['brand'], $p['model'], $p['produktow'], $wGalerii[$klucz] ?? 0);
    }
    echo "\nNic nie zapisano. Uruchom z --apply.\n";
    exit(0);
}

/* ------------------------------------------------------------------ */
/* 4. Tabela słownika                                                  */
/* ------------------------------------------------------------------ */

$conn->query("
    CREATE TABLE IF NOT EXISTS `wheel_dict` (
        `id`          INT AUTO_INCREMENT PRIMARY KEY,
        `brand`       VARCHAR(120) NOT NULL,
        `model`       VARCHAR(120) NOT NULL,
        `brand_norm`  VARCHAR(64)  NOT NULL,
        `model_norm`  VARCHAR(64)  NOT NULL,
        `products`    INT NOT NULL DEFAULT 0,
        `projects`    INT NOT NULL DEFAULT 0,
        `active`      TINYINT(1) NOT NULL DEFAULT 1,
        `updated_at`  DATETIME NOT NULL,
        UNIQUE KEY `uq_wheel_dict` (`brand_norm`, `model_norm`),
        KEY `idx_wheel_dict_brand` (`brand_norm`),
        KEY `idx_wheel_dict_active` (`active`, `products`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
") or exit("Błąd CREATE TABLE: " . $conn->error . "\n");

/*
 * active = 0 dla wszystkiego, potem podnosimy przy wpisach ze sklepu.
 * Nie kasujemy nieaktywnych: stare wpisy galerii wciąż się do nich odwołują,
 * a wycofany z oferty model felgi nadal jeździ na czyimś aucie.
 */
$conn->query("UPDATE `wheel_dict` SET `active` = 0");

$stmt = $conn->prepare("
    INSERT INTO `wheel_dict` (brand, model, brand_norm, model_norm, products, projects, active, updated_at)
    VALUES (?, ?, ?, ?, ?, ?, 1, NOW())
    ON DUPLICATE KEY UPDATE
        brand = VALUES(brand), model = VALUES(model),
        products = VALUES(products), projects = VALUES(projects),
        active = 1, updated_at = NOW()
");

$conn->begin_transaction();
foreach ($pary as $klucz => $p) {
    $projekty = $wGalerii[$klucz] ?? 0;
    $stmt->bind_param(
        'ssssii',
        $p['brand'], $p['model'], $p['brand_norm'], $p['model_norm'],
        $p['produktow'], $projekty
    );
    if (!$stmt->execute()) {
        $conn->rollback();
        exit("Błąd zapisu przy {$p['brand']} {$p['model']}: " . $stmt->error . "\n");
    }
}
$conn->commit();
$stmt->close();

$aktywne  = $conn->query("SELECT COUNT(*) n FROM wheel_dict WHERE active = 1")->fetch_assoc()['n'];
$wycofane = $conn->query("SELECT COUNT(*) n FROM wheel_dict WHERE active = 0")->fetch_assoc()['n'];

printf("Słownik: %s pozycji aktywnych, %s wycofanych ze sprzedaży.\n", $aktywne, $wycofane);
echo "Gotowe.\n";
