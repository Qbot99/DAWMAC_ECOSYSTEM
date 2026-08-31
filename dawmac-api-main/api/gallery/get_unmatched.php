<?php
/**
 * Lista robocza — wpisy galerii, które nie trafiają w żaden produkt.
 *
 * Grupujemy po PARZE producent+model, a nie po autach. Jedna decyzja
 * ("JAPANRACNIG to Japan Racing") naprawia od razu wszystkie auta wpisane
 * tak samo, zamiast klikania po jednym. Lista jest posortowana po zysku,
 * czyli po liczbie aut, które odblokuje pojedyncza poprawka.
 *
 * Do każdej pozycji dokładamy podpowiedzi ze słownika (odległość edycyjna),
 * ale to SĄ tylko podpowiedzi — w danych są pary różniące się jedną cyfrą,
 * które są innymi felgami, więc wybiera człowiek.
 *
 *   get_unmatched.php              → wszystkie grupy
 *   get_unmatched.php?limit=50     → tylko najbardziej opłacalne
 *   get_unmatched.php?kind=brand   → tylko te, gdzie nie ma producenta
 */

require 'db.php';
require_once __DIR__ . '/lib/wheel_norm.php';

$limit = isset($_GET['limit']) ? max(1, min(500, (int) $_GET['limit'])) : 200;
$kind  = $_GET['kind'] ?? '';

/* Słownik do pamięci — 2614 pozycji, tanio. */
$slownik = [];
$marki   = [];
$sklejone = [];
$res = $conn->query("SELECT brand, model, brand_norm, model_norm, products FROM wheel_dict WHERE active = 1");
while ($d = $res->fetch_assoc()) {
    $d['products'] = (int) $d['products'];
    $slownik[] = $d;
    $marki[$d['brand_norm']][] = $d;
    // Klucz na wypadek innego miejsca podziału nazwy (OE|MS IFG10 vs OEMS|IFG10).
    $sklejone[$d['brand_norm'] . $d['model_norm']] = $d;
}

/* Pary z galerii + ile aut na każdą. */
$res = $conn->query(
    "SELECT w.brand_norm, w.model_norm,
            MIN(w.brand) AS brand, MIN(w.model) AS model,
            COUNT(DISTINCT p.id) AS cars
     FROM wheel w
     JOIN project p ON p.wheel_id = w.id
     GROUP BY w.brand_norm, w.model_norm"
);

$grupy = [];

while ($g = $res->fetch_assoc()) {
    $bn = $g['brand_norm'];
    $mn = $g['model_norm'];

    /* Puste pole = nie ma czego dopasowywać, ale trzeba to zgłosić. */
    if ($bn === '' || $mn === '') {
        $rodzaj = 'empty';
    } else {
        /* Czy para już pasuje? */
        $pasuje = false;
        foreach ($marki[$bn] ?? [] as $d) {
            if ($d['model_norm'] === $mn) {
                $pasuje = true;
                break;
            }
        }
        // Dopasowanie po sklejeniu jest pewne — to ten sam ciąg znaków,
        // więc taka para nie należy na listę roboczą.
        if (!$pasuje && isset($sklejone[$bn . $mn])) {
            $pasuje = true;
        }

        if ($pasuje) {
            continue;
        }
        $rodzaj = isset($marki[$bn]) ? 'model' : 'brand';
    }

    if ($kind !== '' && $kind !== $rodzaj) {
        continue;
    }

    /* Podpowiedzi. Gdy producent jest znany, szukamy tylko w jego modelach —
       to znacząco zmniejsza szansę na bzdurną sugestię z innej marki. */
    $pula = ($rodzaj === 'model') ? ($marki[$bn] ?? []) : $slownik;
    $podpowiedzi = [];

    if ($rodzaj !== 'empty') {
        foreach ($pula as $d) {
            $dystans = ($rodzaj === 'model')
                ? levenshtein($mn, $d['model_norm'])
                : min(
                    levenshtein($bn, $d['brand_norm']),
                    levenshtein($bn . $mn, $d['brand_norm'] . $d['model_norm'])
                );

            if ($dystans <= 2) {
                $podpowiedzi[] = [
                    'brand'      => $d['brand'],
                    'model'      => $d['model'],
                    'brand_norm' => $d['brand_norm'],
                    'model_norm' => $d['model_norm'],
                    'products'   => $d['products'],
                    'distance'   => $dystans,
                    /* Różnica w cyfrach modelu prawie zawsze znaczy INNĄ felgę
                       (Stuttgart ST4 vs ST3), więc oznaczamy to wprost. */
                    'digit_diff' => preg_replace('~\D~', '', $mn) !== preg_replace('~\D~', '', $d['model_norm']),
                ];
            }
        }

        usort($podpowiedzi, static fn(array $a, array $b): int
            => [$a['digit_diff'], $a['distance'], -$a['products']]
            <=> [$b['digit_diff'], $b['distance'], -$b['products']]);

        $podpowiedzi = array_slice($podpowiedzi, 0, 5);
    }

    $grupy[] = [
        'brand'       => $g['brand'],
        'model'       => $g['model'],
        'brand_norm'  => $bn,
        'model_norm'  => $mn,
        'cars'        => (int) $g['cars'],
        'kind'        => $rodzaj,
        'suggestions' => $podpowiedzi,
    ];
}

/* Najpierw to, co odblokuje najwięcej aut. */
usort($grupy, static fn(array $a, array $b): int => $b['cars'] <=> $a['cars']);

$razemAut = array_sum(array_column($grupy, 'cars'));

echo json_encode([
    'groups_total' => count($grupy),
    'cars_total'   => $razemAut,
    'groups'       => array_slice($grupy, 0, $limit),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

$conn->close();
