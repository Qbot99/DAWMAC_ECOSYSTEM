<?php

require "db.php";

$wheel_id = trim($_POST["wheel_id"] ?? '');
if ($wheel_id === '' || !is_numeric($wheel_id)) {
    http_response_code(400);
    echo json_encode(["error" => "ID felgi jest wymagane i musi być liczbą."]);
    exit();
}

$wheel_name = trim($_POST["wheel_name"] ?? '');
if ($wheel_name === '' || !is_string($wheel_name)) {
    http_response_code(400);
    echo json_encode(["error" => "ID felgi to musi być text."]);
    exit();
}


// Przygotowanie zapytania UPDATE
$stmt = $conn->prepare("UPDATE `wheel` SET name = ? WHERE `id` = ?");
if (!$stmt) {
    http_response_code(500);
    echo json_encode(["error" => "Błąd przygotowania zapytania UPDATE: " . $conn->error]);
    exit();
}

// Przypisanie parametrów (s - string, i - integer)
$stmt->bind_param("si", $wheel_name, $wheel_id);



// Wykonanie zapytania
if ($stmt->execute() ) {
    echo json_encode(["success" => true, "message" => "Nazwa felgi została zmieniona.".$wheel_name]);
} else {

    echo json_encode(["error" => "Błąd podczas zmiany nazwy felgi: " . $stmt->error]);

}

$stmt->close();
$conn->close();