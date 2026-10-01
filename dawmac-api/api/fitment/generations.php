<?php
/**
 * Generacje modelu z parametrami piasty.
 *
 * GET /api/fitment/generations.php?make=volkswagen&model=golf
 */

require __DIR__ . '/db.php';

$makeSlug  = dawmac_fit_slug($_GET['make'] ?? '');
$modelSlug = dawmac_fit_slug($_GET['model'] ?? '');

if ($makeSlug === '' || $modelSlug === '') {
    dawmac_fit_respond(['error' => 'Parametry make i model są wymagane.'], 400);
}

$stmt = $conn->prepare(
    "SELECT md.id, md.name, md.slug, mk.name AS make_name, mk.slug AS make_slug
     FROM fit_model md
     JOIN fit_make mk ON mk.id = md.make_id
     WHERE mk.slug = ? AND md.slug = ?"
);
$stmt->bind_param('ss', $makeSlug, $modelSlug);
$stmt->execute();
$model = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$model) {
    dawmac_fit_respond(['error' => 'Nie ma takiego modelu.'], 404);
}

$modelId = (int) $model['id'];

$stmt = $conn->prepare('SELECT * FROM fit_generation WHERE model_id = ? ORDER BY year_from, name');
$stmt->bind_param('i', $modelId);
$stmt->execute();
$res = $stmt->get_result();

$generations = [];
while ($r = $res->fetch_assoc()) {
    $generations[] = dawmac_fit_generation_out($r);
}
$stmt->close();

dawmac_fit_respond([
    'make'        => ['name' => $model['make_name'], 'slug' => $model['make_slug']],
    'model'       => ['id' => $modelId, 'name' => $model['name'], 'slug' => $model['slug']],
    'count'       => count($generations),
    'generations' => $generations,
]);
