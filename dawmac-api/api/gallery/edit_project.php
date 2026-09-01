<?php

require "db.php";
require_once __DIR__ . '/lib/wheel_norm.php';

if (!$conn) {
    http_response_code(500);
    echo json_encode(["error" => "Brak połączenia z bazą danych."]);
    exit();
}

$response = ['errors' => [] ];

$wheel_brand = trim($_POST["brand"] ?? '');
$wheel_model = trim($_POST["model"] ?? '');
$wheel_params = trim($_POST["params"] ?? '');
$project_id = trim($_POST["project_id"] ?? ''); 


// Poprawka nazwy felgi musi od razu przeliczyć klucz dopasowania —
// inaczej auto zostałoby na kartach produktów pod starą, błędną nazwą.
$brand_norm = dawmac_wheel_norm($wheel_brand);
$model_norm = dawmac_wheel_norm($wheel_model);

$stmt = $conn->prepare("UPDATE wheel
SET brand = ?, model = ?, params = ?, brand_norm = ?, model_norm = ?
WHERE id = (
    SELECT wheel_id FROM project WHERE id = ?
);");

if ($stmt) {
    $stmt->bind_param("sssssi", $wheel_brand, $wheel_model, $wheel_params, $brand_norm, $model_norm, $project_id);
    $stmt->execute();
    $stmt->close();
} else {
    $response["errors"][] = "Błąd zapytania SQL (wheel): " . $conn->error;
    echo json_encode($response);
    exit();
}

if (empty($response["errors"])) {
    header('Location: /');
} else {
    header('Content-Type: application/json');
    echo json_encode($response);
}
?>