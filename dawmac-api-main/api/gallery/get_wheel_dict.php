<?php
/**
 * Słownik felg dla panelu — podpowiedzi zamiast wpisywania z palca.
 *
 *   get_wheel_dict.php                    → lista producentów (do rozwijanej)
 *   get_wheel_dict.php?brand=JAPANRACING  → modele tego producenta
 *   get_wheel_dict.php?q=jr21             → szukanie po wszystkim naraz
 *
 * Zapytanie normalizujemy tą samą funkcją co bazę, więc "jr 21", "JR-21"
 * i "jr21" trafiają w to samo. Dodatkowo szukamy po surowym zapisie, żeby
 * "japan racing" znalazło się po fragmencie nazwy producenta.
 *
 * Przy każdej pozycji oddajemy `products` (ile produktów w sklepie) i
 * `projects` (ile aut już mamy) — panel pokazuje to przy podpowiedzi, więc
 * od razu widać, na ile kart produktów trafi nowy wpis.
 */

require 'db.php';
require_once __DIR__ . '/lib/wheel_norm.php';
require_once __DIR__ . '/lib/wheel_alias.php';

$q     = trim((string) ($_GET['q'] ?? ''));
$brand = trim((string) ($_GET['brand'] ?? ''));
$limit = isset($_GET['limit']) ? max(1, min(100, (int) $_GET['limit'])) : 30;

// Domyślnie tylko to, co sklep ma w sprzedaży. `all=1` pokazuje też pozycje
// wycofane — przydatne przy poprawianiu starych wpisów galerii.
$tylkoAktywne = empty($_GET['all']);

/* ---------------------------------------------------------------- */
/* Tryb 1: szukanie                                                  */
/* ---------------------------------------------------------------- */

if ($q !== '') {
    $qn   = dawmac_wheel_norm($q);
    $like = '%' . $conn->real_escape_string($q) . '%';

    $sql = "SELECT id, brand, model, brand_norm, model_norm, products, projects, active
            FROM wheel_dict
            WHERE (
                    CONCAT(brand_norm, model_norm) LIKE ?
                 OR model_norm LIKE ?
                 OR brand LIKE ?
                 OR model LIKE ?
            )";

    if ($tylkoAktywne) {
        $sql .= " AND active = 1";
    }

    // Najpierw dokładne trafienie w model, potem po liczbie produktów.
    $sql .= " ORDER BY (model_norm = ?) DESC, products DESC, brand ASC LIMIT ?";

    $wzorNorm = '%' . $qn . '%';

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('sssssi', $wzorNorm, $wzorNorm, $like, $like, $qn, $limit);
    $stmt->execute();
    $res = $stmt->get_result();

    $pozycje = [];
    $juzMam  = [];
    while ($r = $res->fetch_assoc()) {
        $r['id']       = (int) $r['id'];
        $r['products'] = (int) $r['products'];
        $r['projects'] = (int) $r['projects'];
        $r['active']   = (bool) $r['active'];
        $r['label']    = $r['brand'] . ' ' . $r['model'];
        $r['fuzzy']    = false;
        $juzMam[$r['id']] = true;
        $pozycje[]     = $r;
    }
    $stmt->close();

    /*
     * Podpowiedzi przy literówce — dopiero gdy zwykłe szukanie nic sensownego
     * nie znalazło. Wpisanie "forza" ma pokazać "Forzza", bo w katalogu nie ma
     * żadnej "Forzy".
     *
     * To jest WYŁĄCZNIE podpowiedź do kliknięcia. Nic się tu nie scala samo:
     * w danych są pary różniące się jednym znakiem, które są innymi felgami
     * (Stuttgart ST4 vs ST3, Platin P115 vs P113), więc ostatnie słowo
     * zawsze należy do człowieka.
     */
    if (count($pozycje) < 5 && strlen($qn) >= 3) {
        $wszystko = $conn->query(
            "SELECT id, brand, model, brand_norm, model_norm, products, projects, active
             FROM wheel_dict" . ($tylkoAktywne ? " WHERE active = 1" : "")
        );

        $kandydaci = [];
        // Im dłuższe zapytanie, tym więcej wybaczamy — ale nigdy więcej niż 3.
        $prog = strlen($qn) >= 8 ? 3 : 2;

        while ($w = $wszystko->fetch_assoc()) {
            if (isset($juzMam[(int) $w['id']])) {
                continue;
            }

            $pelny = $w['brand_norm'] . $w['model_norm'];

            // Liczymy odległość i do całości, i do samego modelu — wpisujesz
            // raz "forza titan", a raz samo "ttan".
            $d = min(
                levenshtein($qn, $pelny),
                levenshtein($qn, $w['model_norm']),
                levenshtein($qn, $w['brand_norm'])
            );

            if ($d <= $prog) {
                $w['id']       = (int) $w['id'];
                $w['products'] = (int) $w['products'];
                $w['projects'] = (int) $w['projects'];
                $w['active']   = (bool) $w['active'];
                $w['label']    = $w['brand'] . ' ' . $w['model'];
                $w['fuzzy']    = true;
                $w['distance'] = $d;
                $kandydaci[]   = $w;
            }
        }

        usort($kandydaci, static function (array $a, array $b): int {
            return [$a['distance'], -$a['products']] <=> [$b['distance'], -$b['products']];
        });

        foreach (array_slice($kandydaci, 0, 6) as $k) {
            $pozycje[] = $k;
        }
    }

    echo json_encode(['query' => $q, 'normalized' => $qn, 'count' => count($pozycje), 'items' => $pozycje],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit();
}

/* ---------------------------------------------------------------- */
/* Tryb 2: modele wybranego producenta                               */
/* ---------------------------------------------------------------- */

if ($brand !== '') {
    $bn = dawmac_wheel_norm($brand);

    $sql = "SELECT id, brand, model, brand_norm, model_norm, products, projects, active
            FROM wheel_dict WHERE brand_norm = ?";
    if ($tylkoAktywne) {
        $sql .= " AND active = 1";
    }
    $sql .= " ORDER BY products DESC, model ASC";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('s', $bn);
    $stmt->execute();
    $res = $stmt->get_result();

    $pozycje = [];
    while ($r = $res->fetch_assoc()) {
        $r['id']       = (int) $r['id'];
        $r['products'] = (int) $r['products'];
        $r['projects'] = (int) $r['projects'];
        $r['active']   = (bool) $r['active'];
        $r['label']    = $r['brand'] . ' ' . $r['model'];
        $pozycje[]     = $r;
    }
    $stmt->close();

    echo json_encode(['brand' => $brand, 'normalized' => $bn, 'count' => count($pozycje), 'items' => $pozycje],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit();
}

/* ---------------------------------------------------------------- */
/* Tryb 3: sami producenci                                           */
/* ---------------------------------------------------------------- */

$sql = "SELECT brand_norm, MIN(brand) AS brand, COUNT(*) AS models,
               SUM(products) AS products, SUM(projects) AS projects
        FROM wheel_dict";
if ($tylkoAktywne) {
    $sql .= " WHERE active = 1";
}
$sql .= " GROUP BY brand_norm ORDER BY brand ASC";

$res = $conn->query($sql);

$marki = [];
while ($r = $res->fetch_assoc()) {
    $marki[] = [
        'brand'      => $r['brand'],
        'brand_norm' => $r['brand_norm'],
        'models'     => (int) $r['models'],
        'products'   => (int) $r['products'],
        'projects'   => (int) $r['projects'],
    ];
}

echo json_encode(['count' => count($marki), 'brands' => $marki],
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

$conn->close();
