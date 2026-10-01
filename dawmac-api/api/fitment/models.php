<?php
/**
 * Modele jednej marki.
 *
 * GET /api/fitment/models.php?make=volkswagen
 *
 * Marka po slugu (jak w adresach sklepu), nie po id — id zmienia się
 * między bazą testową a produkcyjną, slug nie.
 */

require __DIR__ . '/db.php';

$makeSlug = dawmac_fit_slug($_GET['make'] ?? '');

if ($makeSlug === '') {
    dawmac_fit_respond(['error' => 'Parametr make jest wymagany.'], 400);
}

$stmt = $conn->prepare('SELECT id, name, slug, country FROM fit_make WHERE slug = ?');
$stmt->bind_param('s', $makeSlug);
$stmt->execute();
$make = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$make) {
    dawmac_fit_respond(['error' => 'Nie ma takiej marki.'], 404);
}

$make['id'] = (int) $make['id'];

$stmt = $conn->prepare(
    "SELECT md.id, md.name, md.slug, COUNT(g.id) AS generations
     FROM fit_model md
     LEFT JOIN fit_generation g ON g.model_id = md.id
     WHERE md.make_id = ?
     GROUP BY md.id, md.name, md.slug
     ORDER BY md.name"
);
$stmt->bind_param('i', $make['id']);
$stmt->execute();
$res = $stmt->get_result();

$models = [];
while ($r = $res->fetch_assoc()) {
    $r['id']          = (int) $r['id'];
    $r['generations'] = (int) $r['generations'];
    $models[]         = $r;
}
$stmt->close();

dawmac_fit_respond(['make' => $make, 'count' => count($models), 'models' => $models]);
