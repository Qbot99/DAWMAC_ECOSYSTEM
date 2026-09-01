<?php

require "db.php";

$wheel_id = trim($_POST["wheel_id"] ?? '');
if ($wheel_id === '' || !is_numeric($wheel_id)) {
    http_response_code(400);
    echo json_encode(["error" => "ID felgi jest wymagane i musi być liczbą."]);
    exit();
}

$img_url = trim($_POST["img_url"] ?? '');
if ($img_url === '' || !is_string($img_url)) {
    http_response_code(400);
    echo json_encode(["error" => "URL zdjęcia jest wymagane i musi to być text.."]);
    exit();
}

// Przygotowanie zapytania DELETE
$stmt = $conn->prepare("DELETE FROM `image` WHERE `wheel_id` = ? AND `url` = ?");
if (!$stmt) {
    http_response_code(500);
    echo json_encode(["error" => "Błąd przygotowania zapytania DELETE: " . $conn->error]);
    exit();
}

// Przypisanie parametru (i = integer, s = string)
$stmt->bind_param("is", $wheel_id, $img_url);




$image_path = "../../forged/" . $img_url;

if(str_contains("../", $img_url))
{
    http_response_code(500);

     echo json_encode(["error" => "Nie wolno używać '../'!"]);
}

// Wykonanie zapytania
if ($stmt->execute() && deleteImage($image_path)) {
    echo json_encode(["success" => true, "message" => "Zdjęcie zostało usunięte."]);
} else {
    http_response_code(500);
    if ($stmt->error) {
        echo json_encode(["error" => "Błąd podczas usuwania zdjęcia: " . $stmt->error]);
    } else {
        echo json_encode(["error" => "Nie udało się usunąć zdjęcia."]);
    }
}

$stmt->close();
$conn->close();

// Funkcja usuwająca jedno zdjęcie
function deleteImage($filePath) {
    if (!file_exists($filePath)) {
        return false;
    }
    return unlink($filePath);
}
