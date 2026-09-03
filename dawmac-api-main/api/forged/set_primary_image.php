<?php

require "db.php";

$wheel_id = trim($_POST["wheel_id"] ?? '');
if ($wheel_id === '' || !is_numeric($wheel_id)) {
    http_response_code(400);
    echo json_encode(["error" => "ID felgi jest wymagane i musi być liczbą."]);
    exit();
}

$img_url = trim($_POST["img_url"] ?? '');
if ($img_url === '') {
    http_response_code(400);
    echo json_encode(["error" => "URL zdjęcia jest wymagany."]);
    exit();
}

// Jedno zdjęcie główne na felgę: najpierw zerujemy flagę, potem ustawiamy
// wskazane. Ta sama logika co w ios/api_forged.php (action=update_order).
$conn->begin_transaction();

$reset = $conn->prepare("UPDATE `image` SET `is_primary` = 0 WHERE `wheel_id` = ?");
$set = $conn->prepare("UPDATE `image` SET `is_primary` = 1 WHERE `wheel_id` = ? AND `url` = ?");
if (!$reset || !$set) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(["error" => "Błąd przygotowania zapytania: " . $conn->error]);
    exit();
}

$reset->bind_param("i", $wheel_id);
$set->bind_param("is", $wheel_id, $img_url);

if (!$reset->execute() || !$set->execute()) {
    $err = $set->error ?: $reset->error;
    $conn->rollback();
    http_response_code(500);
    echo json_encode(["error" => "Błąd zapisu zdjęcia głównego: " . $err]);
    exit();
}

if ($set->affected_rows < 1) {
    $conn->rollback();
    http_response_code(404);
    echo json_encode(["error" => "To zdjęcie nie należy do tej felgi."]);
    exit();
}

$conn->commit();
echo json_encode(["success" => true, "message" => "Ustawiono zdjęcie główne."]);

$reset->close();
$set->close();
$conn->close();
