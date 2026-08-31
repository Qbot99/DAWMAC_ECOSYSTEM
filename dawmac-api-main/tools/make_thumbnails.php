<?php
/**
 * Dorabia brakujące miniatury thumb700_ dla zdjęć galerii.
 *
 * Dlaczego to w ogóle jest potrzebne: panel webowy (add_wheel.php) tworzy
 * miniaturę przy wgrywaniu, ale endpoint aplikacji iOS robił samo
 * move_uploaded_file(). Ponieważ zdjęcia idą głównie z telefonu, większość
 * galerii nie ma miniatur — a front ma fallback `onerror` na pełny plik,
 * więc od miesięcy serwuje odwiedzającym pliki po ~400 KB zamiast po ~30 KB.
 * Nikt tego nie zauważył, bo obrazki się wyświetlają.
 *
 * Uruchamianie (z ~/api.dawmacpolska.pl/tools):
 *
 *   php make_thumbnails.php                 — tylko policz, nic nie twórz
 *   php make_thumbnails.php --apply         — twórz (partiami po 300)
 *   php make_thumbnails.php --apply --limit=1000
 *
 * Idempotentny i wznawialny: pomija to, co już ma miniaturę, więc można
 * odpalać w kółko aż zejdzie do zera.
 */

$root = getenv('DAWMAC_DOCROOT')
    ?: '/home/klient.dhosting.pl/dawmac/api.dawmacpolska.pl/public_html';

$_SERVER['DOCUMENT_ROOT'] = $root;
$_SERVER['REQUEST_METHOD'] = 'CLI';

require $root . '/api/gallery/db.php';

$apply = in_array('--apply', $argv, true);
$limit = 300;
foreach ($argv as $a) {
    if (str_starts_with($a, '--limit=')) {
        $limit = max(1, (int) substr($a, 8));
    }
}

$BASE  = $root . '/gallery/';
$WIDTH = 700;

ini_set('memory_limit', '512M');

echo $apply ? "TRYB: zapis\n\n" : "TRYB: podgląd — nic nie powstanie. Dodaj --apply.\n\n";

/**
 * Ścieżka miniatury dla ścieżki z bazy.
 * W bazie są dwa formaty: 'images/plik.jpg' (nowe, z apki) oraz
 * '/images/2869/plik.webp' (stare, z podkatalogiem na projekt).
 */
function sciezka_miniatury(string $urlZBazy): array
{
    $wzgledna = ltrim($urlZBazy, '/');
    $katalog  = dirname($wzgledna);
    $plik     = basename($wzgledna);

    $katalog = ($katalog === '.' ) ? '' : $katalog . '/';

    return [$wzgledna, $katalog . 'thumb700_' . $plik];
}

function zmniejsz(string $zrodlo, string $cel, int $szerokosc): bool
{
    $info = @getimagesize($zrodlo);
    if ($info === false) {
        return false;
    }

    [$w, $h] = $info;
    if ($w <= 0 || $h <= 0) {
        return false;
    }

    switch ($info['mime']) {
        case 'image/jpeg': $obraz = @imagecreatefromjpeg($zrodlo); break;
        case 'image/png':  $obraz = @imagecreatefrompng($zrodlo);  break;
        case 'image/webp': $obraz = @imagecreatefromwebp($zrodlo); break;
        case 'image/gif':  $obraz = @imagecreatefromgif($zrodlo);  break;
        default: return false;
    }

    if (!$obraz) {
        return false;
    }

    // Zdjęcie węższe niż miniatura: kopiujemy, zamiast powiększać.
    if ($w <= $szerokosc) {
        imagedestroy($obraz);
        return copy($zrodlo, $cel);
    }

    $noweH = (int) round($szerokosc * $h / $w);
    $mini  = imagecreatetruecolor($szerokosc, $noweH);

    if ($info['mime'] === 'image/png' || $info['mime'] === 'image/webp') {
        imagealphablending($mini, false);
        imagesavealpha($mini, true);
    }

    imagecopyresampled($mini, $obraz, 0, 0, 0, 0, $szerokosc, $noweH, $w, $h);

    $ok = match ($info['mime']) {
        'image/jpeg' => imagejpeg($mini, $cel, 82),
        'image/png'  => imagepng($mini, $cel, 6),
        'image/webp' => imagewebp($mini, $cel, 82),
        'image/gif'  => imagegif($mini, $cel),
        default      => false,
    };

    imagedestroy($obraz);
    imagedestroy($mini);

    return (bool) $ok;
}

$wynik = $conn->query("SELECT id, image_url FROM project_images ORDER BY id DESC");
if (!$wynik) {
    exit("BŁĄD SELECT: " . $conn->error . "\n");
}

$brakuje = [];
$maja = 0;
$bezPliku = 0;

while ($row = $wynik->fetch_assoc()) {
    [$org, $mini] = sciezka_miniatury((string) $row['image_url']);

    if (!is_file($BASE . $org)) {
        $bezPliku++;
        continue;
    }
    if (is_file($BASE . $mini)) {
        $maja++;
        continue;
    }
    $brakuje[] = [$org, $mini];
}

printf("Zdjęć w bazie z plikiem na dysku: %d\n", $maja + count($brakuje));
printf("  ma już miniaturę:              %d\n", $maja);
printf("  BRAK miniatury:                %d\n", count($brakuje));
printf("Wpisów bez pliku na dysku:       %d\n\n", $bezPliku);

if (!$apply) {
    $przyklad = array_slice($brakuje, 0, 5);
    foreach ($przyklad as [$org, $mini]) {
        printf("  utworzy: %s\n", $mini);
    }
    echo "\nNic nie utworzono. Uruchom z --apply.\n";
    exit(0);
}

$zrobione = 0;
$bledy    = 0;
$zysk     = 0;

foreach (array_slice($brakuje, 0, $limit) as [$org, $mini]) {
    $przed = (int) @filesize($BASE . $org);

    if (zmniejsz($BASE . $org, $BASE . $mini, $WIDTH)) {
        $zrobione++;
        $zysk += max(0, $przed - (int) @filesize($BASE . $mini));
    } else {
        $bledy++;
        fwrite(STDERR, "  nie udało się: $org\n");
    }
}

printf("Utworzone miniatury: %d\n", $zrobione);
printf("Nieudane:            %d\n", $bledy);
printf("Zaoszczędzone przy wyświetlaniu: %.1f MB\n", $zysk / 1048576);
printf("Zostało do zrobienia: %d\n", max(0, count($brakuje) - $limit));
