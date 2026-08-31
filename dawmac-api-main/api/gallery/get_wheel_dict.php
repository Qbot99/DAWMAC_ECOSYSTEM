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
    while ($r = $res->fetch_assoc()) {
        $r['id']       = (int) $r['id'];
        $r['products'] = (int) $r['products'];
        $r['projects'] = (int) $r['projects'];
        $r['active']   = (bool) $r['active'];
        $r['label']    = $r['brand'] . ' ' . $r['model'];
        $pozycje[]     = $r;
    }
    $stmt->close();

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
