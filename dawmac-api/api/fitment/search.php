<?php
/**
 * Do jakich aut pasuje felga — po rozstawie i otworze centralnym.
 *
 * GET /api/fitment/search.php?pcd=5x112&cb=66.6
 * GET /api/fitment/search.php?pcd=5x112&cb=66.6&verified=1   (tylko potwierdzone dane)
 *
 * Zwraca auta, na których piastę felga wejdzie: ten sam rozstaw i piasta
 * nie większa niż otwór felgi. Przy każdym aucie needs_ring mówi, czy
 * potrzebny jest pierścień centrujący. Regułę trzyma dawmac_fit_check() —
 * SQL tylko zawęża listę, decyzję podejmuje ta sama funkcja co wszędzie.
 */

require __DIR__ . '/db.php';

$pcd = dawmac_fit_parse_pcd($_GET['pcd'] ?? '');
$cb  = (float) str_replace(',', '.', (string) ($_GET['cb'] ?? ''));

if ($pcd === null) {
    dawmac_fit_respond(['error' => 'Parametr pcd jest wymagany, np. 5x112.'], 400);
}
if ($cb < 40 || $cb > 180) {
    dawmac_fit_respond(['error' => 'Parametr cb (otwór centralny felgi w mm) jest wymagany, np. 66.6.'], 400);
}

$onlyVerified = !empty($_GET['verified']);

$pcdMm = number_format($pcd['mm'], 2, '.', '');
// Liczone w setnych, żeby granica była dokładnie ta sama co w dawmac_fit_check().
$maxCb = number_format(
    (dawmac_fit_hundredths($cb) + dawmac_fit_hundredths(DAWMAC_FIT_CB_TOLERANCE)) / 100, 2, '.', ''
);

$sql = "SELECT g.*, md.name AS model_name, md.slug AS model_slug, mk.name AS make_name, mk.slug AS make_slug
        FROM fit_generation g
        JOIN fit_model md ON md.id = g.model_id
        JOIN fit_make mk ON mk.id = md.make_id
        WHERE g.pcd_holes = ?
          AND g.pcd_mm = CAST(? AS DECIMAL(6,2))
          AND g.center_bore <= CAST(? AS DECIMAL(6,2))"
     . ($onlyVerified ? ' AND g.verified = 1' : '')
     . " ORDER BY mk.name, md.name, g.year_from";

$stmt = $conn->prepare($sql);
$stmt->bind_param('iss', $pcd['holes'], $pcdMm, $maxCb);
$stmt->execute();
$res = $stmt->get_result();

$felga    = ['pcd_holes' => $pcd['holes'], 'pcd_mm' => $pcd['mm'], 'center_bore' => $cb];
$vehicles = [];

while ($r = $res->fetch_assoc()) {
    $check = dawmac_fit_check($felga, $r);
    if (!$check['fits']) {
        continue;
    }

    $vehicles[] = [
        'make'       => ['name' => $r['make_name'], 'slug' => $r['make_slug']],
        'model'      => ['name' => $r['model_name'], 'slug' => $r['model_slug']],
        'generation' => dawmac_fit_generation_out($r),
        'needs_ring' => $check['needs_ring'],
        'note'       => $check['reason'],
    ];
}
$stmt->close();

dawmac_fit_respond([
    'pcd'         => dawmac_fit_pcd_label($pcd['holes'], $pcd['mm']),
    'center_bore' => $cb,
    'count'       => count($vehicles),
    'vehicles'    => $vehicles,
]);
