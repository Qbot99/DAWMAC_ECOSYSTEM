<?php
/**
 * Pełna karta auta: piasta i rozmiary felg/opon.
 *
 * GET /api/fitment/vehicle.php?id=12      (id generacji)
 */

require __DIR__ . '/db.php';

$id = (int) ($_GET['id'] ?? 0);

if ($id <= 0) {
    dawmac_fit_respond(['error' => 'Parametr id jest wymagany.'], 400);
}

$stmt = $conn->prepare(
    "SELECT g.*, md.name AS model_name, md.slug AS model_slug, mk.name AS make_name, mk.slug AS make_slug
     FROM fit_generation g
     JOIN fit_model md ON md.id = g.model_id
     JOIN fit_make mk ON mk.id = md.make_id
     WHERE g.id = ?"
);
$stmt->bind_param('i', $id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row) {
    dawmac_fit_respond(['error' => 'Nie ma takiego auta.'], 404);
}

// Fabryczne rozmiary najpierw, potem od najmniejszej felgi.
$stmt = $conn->prepare(
    "SELECT axle, diameter, width, et, tire, is_oem
     FROM fit_wheel
     WHERE generation_id = ?
     ORDER BY is_oem DESC, diameter, width, FIELD(axle, 'both', 'front', 'rear'), et"
);
$stmt->bind_param('i', $id);
$stmt->execute();
$res = $stmt->get_result();

$wheels = [];
while ($r = $res->fetch_assoc()) {
    $wheels[] = dawmac_fit_wheel_out($r);
}
$stmt->close();

dawmac_fit_respond([
    'vehicle' => [
        'make'       => ['name' => $row['make_name'], 'slug' => $row['make_slug']],
        'model'      => ['name' => $row['model_name'], 'slug' => $row['model_slug']],
        'generation' => dawmac_fit_generation_out($row),
        'wheels'     => $wheels,
    ],
]);
