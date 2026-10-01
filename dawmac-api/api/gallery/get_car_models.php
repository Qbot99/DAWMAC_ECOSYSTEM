<?php
require 'db.php';

// Id marki tylko jako liczba — wcześniej trafiało do SQL wprost z adresu (SQL injection).
$car_brand_id = filter_var($_GET['car_brand_id'] ?? '', FILTER_VALIDATE_INT);
if ($car_brand_id === false) {
    http_response_code(400);
    echo json_encode([]);
    exit();
}

$stmt = $conn->prepare("SELECT id,name FROM car_model WHERE car_model.car_brand_id = ? ORDER BY car_model.name;");
$stmt->bind_param("i", $car_brand_id);
$stmt->execute();
$data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
echo json_encode($data);

$conn->close();

?>
