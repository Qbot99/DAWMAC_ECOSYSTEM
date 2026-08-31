<?php
header('Content-Type: application/json');
require_once("db.php");

$project_id = isset($_POST['id']) ? intval($_POST['id']) : 0;

if (!$project_id) {
    echo json_encode(['error' => 'Brak ID projektu']);
    exit;
}

$sql = "
SELECT 
    p.id AS project_id,
    GROUP_CONCAT(DISTINCT i.image_url) AS images,
    p.car_brand_id,
    p.car_model_id,
    w.brand,
    w.model,
    w.params,
    p.youtube_url,
    p.auction_url
FROM project p
LEFT JOIN project_images i ON p.id = i.project_id
LEFT JOIN wheel w ON p.wheel_id = w.id
WHERE p.id = $project_id
GROUP BY p.id, p.car_brand_id, p.car_model_id, w.brand, w.model, w.params, p.youtube_url, p.auction_url
LIMIT 1

";

try {
    $result = $conn->query($sql);
    $data = [];

    while ($row = $result->fetch_assoc()) {
        $row['images'] = explode(",", $row['images']);
        $data[] = $row;
    }

    echo json_encode($data);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
