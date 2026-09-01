<?php
/**
 * Automatyczne dopasowanie galerii do katalogu sklepu — hurtem, nie po jednym.
 *
 * ZASADA: jednoznaczność, nie podobieństwo.
 *
 * Sklep i galeria używają tej samej felgi pod różnymi konwencjami nazw:
 *   galeria "FS25"     — sklep "V-FS25"        (sklep dopisuje przedrostek marki)
 *   galeria "B12"      — sklep "R8-B12"
 *   galeria "MUNICH"   — sklep "Eurosport Munich"
 *   galeria "Evoke"    — sklep "Evoke X"       (galeria ma nazwę skróconą)
 *
 * Dopasowujemy tylko wtedy, gdy DOKŁADNIE JEDEN model danej marki zawiera
 * nazwę z galerii jako początek albo koniec. Gdy pasuje kilka — zostawiamy
 * człowiekowi. To dlatego "Dawmac Forged / F2P" nie zostanie ruszony:
 * pasuje do niego F2P1, F2P10, F2P100 i kilkadziesiąt innych, a zgadywanie
 * wybrałoby jeden z nich na chybił trafił.
 *
 * Odległość edycyjna jest tu świadomie NIEUŻYWANA — Stuttgart ST4 i ST3
 * różnią się o jeden znak, a to inne felgi.
 *
 *   php auto_match.php                 — podgląd
 *   php auto_match.php --apply         — zapisz aliasy i przelicz wpisy
 *   php auto_match.php --only=suffix   — tylko te, gdzie sklep dopisuje przedrostek
 */

$root = getenv('DAWMAC_DOCROOT')
    ?: '/home/klient.dhosting.pl/dawmac/api.dawmacpolska.pl/public_html';

$_SERVER['DOCUMENT_ROOT'] = $root;
$_SERVER['REQUEST_METHOD'] = 'CLI';
ini_set('display_errors', 'stderr');

require $root . '/api/gallery/lib/wheel_norm.php';
require $root . '/api/gallery/db.php';

$apply = in_array('--apply', $argv, true);
$only  = '';
foreach ($argv as $a) {
    if (str_starts_with($a, '--only=')) {
        $only = substr($a, 7);
    }
}

echo $apply ? "TRYB: zapis\n\n" : "TRYB: podgląd. Dodaj --apply.\n\n";

/* Katalog sklepu w pamięci, pogrupowany po marce. */
$sklep = [];
$res = $conn->query("SELECT brand, model, brand_norm, model_norm, products FROM wheel_dict WHERE active = 1");
while ($d = $res->fetch_assoc()) {
    $sklep[$d['brand_norm']][$d['model_norm']] = $d;
}

/* Wykluczenia; model_norm = '*' wyłącza całą markę (tak trzymamy felgi kute). */
$pominiete = [];
$pominieteMarki = [];
$res = @$conn->query("SELECT brand_norm, model_norm FROM wheel_ignored");
if ($res) {
    while ($i = $res->fetch_assoc()) {
        if ($i['model_norm'] === '*') {
            $pominieteMarki[$i['brand_norm']] = true;
        } else {
            $pominiete[$i['brand_norm'] . "\x1f" . $i['model_norm']] = true;
        }
    }
}

/* Pary z galerii, które nie trafiają w katalog. */
$res = $conn->query(
    "SELECT w.brand_norm bn, w.model_norm mn, MIN(w.brand) b, MIN(w.model) m,
            COUNT(DISTINCT p.id) cars
     FROM wheel w JOIN project p ON p.wheel_id = w.id
     WHERE w.brand_norm <> '' AND w.model_norm <> ''
     GROUP BY w.brand_norm, w.model_norm"
);

$pewne = [];
$niejednoznaczne = [];
$bezOdpowiednika = 0;

while ($g = $res->fetch_assoc()) {
    $bn = $g['bn'];
    $mn = $g['mn'];

    if (isset($pominieteMarki[$bn]) || isset($pominiete[$bn . "\x1f" . $mn]) || isset($sklep[$bn][$mn])) {
        continue;
    }

    if (!isset($sklep[$bn]) || strlen($mn) < 2) {
        $bezOdpowiednika++;
        continue;
    }

    $konce = [];
    $poczatki = [];

    foreach ($sklep[$bn] as $km => $d) {
        if ($km === $mn) {
            continue;
        }
        if (str_ends_with($km, $mn)) {
            $konce[] = $d;
        } elseif (str_starts_with($km, $mn)) {
            $poczatki[] = $d;
        }
    }

    /*
     * Koniec traktujemy jako pewniejszy: to sklep dopisuje przedrostek marki
     * (V-FS25, R8-B12), a więc nazwa z galerii jest w całości zachowana.
     * Początek oznacza, że to galeria ma nazwę skróconą — też sensowne,
     * ale bardziej zależne od tego, ile modeli tworzy rodzinę.
     */
    $kandydaci = $konce ?: $poczatki;
    $rodzaj    = $konce ? 'suffix' : 'prefix';

    if (count($kandydaci) === 1) {
        if ($only === '' || $only === $rodzaj) {
            $pewne[] = [$g, $kandydaci[0], $rodzaj];
        }
    } elseif (count($kandydaci) > 1) {
        $niejednoznaczne[] = [$g, array_map(static fn($d) => $d['model'], array_slice($kandydaci, 0, 6))];
    } else {
        $bezOdpowiednika++;
    }
}

usort($pewne, static fn($a, $b) => $b[0]['cars'] <=> $a[0]['cars']);

$autaPewne = array_sum(array_map(static fn($p) => (int) $p[0]['cars'], $pewne));

printf("PEWNE — dokładnie jedno dopasowanie: %d grup / %d aut\n\n", count($pewne), $autaPewne);

foreach ($pewne as [$g, $d, $rodzaj]) {
    printf(
        "  %3d aut  %s / %-14s -> %s %-16s [%s] %s prod.\n",
        $g['cars'], $g['b'], $g['m'], $d['brand'], $d['model'],
        $rodzaj === 'suffix' ? 'sklep dopisuje przedrostek' : 'galeria skraca nazwę',
        $d['products']
    );
}

printf(
    "\nNIEJEDNOZNACZNE — pasuje kilka, zostawiamy człowiekowi: %d grup / %d aut\n",
    count($niejednoznaczne),
    array_sum(array_map(static fn($n) => (int) $n[0]['cars'], $niejednoznaczne))
);

foreach (array_slice($niejednoznaczne, 0, 8) as [$g, $kand]) {
    printf("  %3d aut  %s / %-14s -> %s …\n", $g['cars'], $g['b'], $g['m'], implode(', ', $kand));
}

printf("\nBEZ ODPOWIEDNIKA W KATALOGU: %d grup\n", $bezOdpowiednika);

if (!$apply) {
    echo "\nNic nie zapisano.\n";
    exit(0);
}

/* Zapis: alias pary + przeliczenie klucza w istniejących wpisach. */
$stmtAlias = $conn->prepare(
    "INSERT INTO wheel_alias (from_brand, from_model, to_brand, to_model, note, created_at)
     VALUES (?, ?, ?, ?, 'auto_match: jednoznaczne dopasowanie nazw', NOW())
     ON DUPLICATE KEY UPDATE to_brand = VALUES(to_brand), to_model = VALUES(to_model), note = VALUES(note)"
);
$stmtWheel = $conn->prepare(
    "UPDATE wheel SET brand_norm = ?, model_norm = ? WHERE brand_norm = ? AND model_norm = ?"
);

$zapisane = 0;
$felgi    = 0;

$conn->begin_transaction();

foreach ($pewne as [$g, $d, $rodzaj]) {
    $stmtAlias->bind_param('ssss', $g['bn'], $g['mn'], $d['brand_norm'], $d['model_norm']);
    $stmtAlias->execute();

    $stmtWheel->bind_param('ssss', $d['brand_norm'], $d['model_norm'], $g['bn'], $g['mn']);
    $stmtWheel->execute();

    $felgi += $stmtWheel->affected_rows;
    $zapisane++;
}

$conn->commit();
$stmtAlias->close();
$stmtWheel->close();

printf("\nZapisane aliasy: %d, przeliczone wiersze wheel: %d.\n", $zapisane, $felgi);
echo "Oryginalne brand/model zostały nietknięte — zmienił się tylko klucz dopasowania.\n";
