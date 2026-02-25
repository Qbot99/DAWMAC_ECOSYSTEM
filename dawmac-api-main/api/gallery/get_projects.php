<?php
require 'db.php';

$carBrandId = $_GET['car_brand_id'] ?? null;
$carModelId = $_GET['car_model_id'] ?? null;
$searchKeyword = $_GET['search_keyword']?? null;

$sql = "SELECT 
    p.id AS project_id,
    w.brand, 
    w.model, 
    (
        SELECT pi.image_url 
        FROM project_images pi 
        WHERE pi.project_id = p.id 
        ORDER BY pi.is_primary DESC, pi.id DESC 
        LIMIT 1
    ) AS image
FROM project p
JOIN wheel w ON p.wheel_id = w.id
LEFT JOIN car_brand cb ON p.car_brand_id = cb.id
LEFT JOIN car_model cm ON p.car_model_id = cm.id
WHERE 1=1";

$params = [];
$types = '';

if ($carBrandId) {
    $sql .= " AND cb.id = ?";
    $params[] = $carBrandId;
    $types .= 'i';
}

if ($carModelId) {
    $sql .= " AND cm.id = ?";
    $params[] = $carModelId;
    $types .= 'i';
}

if ($searchKeyword) {
    $sql .= " AND CONCAT(w.brand, ' ', w.model, ' ', w.params) LIKE ?";
    $params[] = '%' . $searchKeyword . '%';
    $types .= 's';
}


$sql .= " GROUP BY p.id, w.brand, w.model ORDER BY p.id DESC ";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();

$result = $stmt->get_result();
$data = $result->fetch_all(MYSQLI_ASSOC);

echo json_encode($data);

$stmt->close();
$conn->close();
