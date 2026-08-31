<?php
/**
 * Projekty (auta klientów) dla konkretnej felgi — zasila karty produktów w sklepie.
 *
 * GET /api/gallery/get_projects_by_wheel.php?brand=Japan%20Racing&model=JR21
 *
 * Marka i model mogą przyjść w dowolnym zapisie — normalizujemy je tu, po
 * stronie serwera, dokładnie tą samą funkcją, którą wypełnione są kolumny
 * brand_norm/model_norm. Dzięki temu sklep nie musi znać reguł normalizacji
 * i nie da się rozjechać obu stron przez literówkę w zapytaniu.
 *
 * Zwracamy wyłącznie projekty z show_in_store = 1 — flaga istniała w bazie
 * od początku i wreszcie zaczyna coś znaczyć.
 */

require 'db.php';
require_once __DIR__ . '/lib/wheel_norm.php';

$brand = dawmac_wheel_norm($_GET['brand'] ?? '');
$model = dawmac_wheel_norm($_GET['model'] ?? '');

// Ile aut maksymalnie oddać. Karta produktu i tak pokazuje kilka,
// ale zostawiamy zapas na "zobacz wszystkie".
$limit = isset($_GET['limit']) ? max(1, min(60, (int) $_GET['limit'])) : 24;

// Ile zdjęć na auto. Domyślnie 1 (kafelek); szczegóły dociąga lightbox
// przez istniejące get_project_details.php.
$perProject = isset($_GET['images']) ? max(1, min(12, (int) $_GET['images'])) : 1;

if ($brand === '' || $model === '') {
    echo json_encode([
        'brand'    => $brand,
        'model'    => $model,
        'count'    => 0,
        'projects' => [],
        'note'     => 'Marka i model są wymagane.',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit();
}

$sql = "SELECT
            p.id            AS project_id,
            w.brand         AS wheel_brand,
            w.model         AS wheel_model,
            w.params        AS params,
            cb.name         AS car_brand,
            cm.name         AS car_model
        FROM project p
        JOIN wheel w        ON p.wheel_id = w.id
        LEFT JOIN car_brand cb ON p.car_brand_id = cb.id
        LEFT JOIN car_model cm ON p.car_model_id = cm.id
        WHERE w.brand_norm = ?
          AND w.model_norm = ?
          AND p.show_in_store = 1
        ORDER BY p.id DESC
        LIMIT ?";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    http_response_code(500);
    echo json_encode(['error' => 'Błąd zapytania: ' . $conn->error]);
    exit();
}

$stmt->bind_param('ssi', $brand, $model, $limit);
$stmt->execute();
$wynik = $stmt->get_result();

$projekty = [];
$idki     = [];

while ($row = $wynik->fetch_assoc()) {
    $id = (int) $row['project_id'];
    $idki[] = $id;

    $auto = trim(($row['car_brand'] ?? '') . ' ' . ($row['car_model'] ?? ''));

    $projekty[$id] = [
        'project_id' => $id,
        'car'        => $auto !== '' ? $auto : null,
        'wheel'      => trim(($row['wheel_brand'] ?? '') . ' ' . ($row['wheel_model'] ?? '')),
        'params'     => $row['params'] ?: null,
        'images'     => [],
    ];
}
$stmt->close();

/*
 * Zdjęcia dobieramy jednym zapytaniem dla wszystkich projektów naraz —
 * inaczej karta produktu z 24 autami wywołałaby 24 osobne zapytania.
 */
if ($idki) {
    $miejsca = implode(',', array_fill(0, count($idki), '?'));
    $typy    = str_repeat('i', count($idki));

    $stmtImg = $conn->prepare(
        "SELECT project_id, image_url, is_primary
         FROM project_images
         WHERE project_id IN ($miejsca)
         ORDER BY is_primary DESC, id ASC"
    );

    if ($stmtImg) {
        $stmtImg->bind_param($typy, ...$idki);
        $stmtImg->execute();
        $img = $stmtImg->get_result();

        while ($row = $img->fetch_assoc()) {
            $pid = (int) $row['project_id'];
            if (isset($projekty[$pid]) && count($projekty[$pid]['images']) < $perProject) {
                $projekty[$pid]['images'][] = $row['image_url'];
            }
        }
        $stmtImg->close();
    }
}

// Auta bez ani jednego zdjęcia nie mają czego pokazać na karcie produktu.
$projekty = array_values(array_filter(
    $projekty,
    static fn(array $p): bool => !empty($p['images'])
));

echo json_encode([
    'brand'    => $brand,
    'model'    => $model,
    'count'    => count($projekty),
    'projects' => $projekty,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

$conn->close();
