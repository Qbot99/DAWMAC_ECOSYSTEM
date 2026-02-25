<?php

require "db.php";

$project_id = trim($_POST["id"] ?? '');
if ($project_id === '' || !is_numeric($project_id)) {
    http_response_code(400);
    echo json_encode(["error" => "ID projektu jest wymagane i musi być liczbą."]);
    exit();
}

$img_url = trim($_POST["img"] ?? '');
if ($img_url === '' || !is_string($img_url)) {
    http_response_code(400);
    echo json_encode(["error" => "URL zdjęcia jest wymagane i musi to być text."]);
    exit();
}

// Przygotowanie zapytania DELETE
$stmt = $conn->prepare("DELETE FROM `project_images` WHERE `project_id` = ? AND `image_url` = ?");
if (!$stmt) {
    http_response_code(500);
    echo json_encode(["error" => "Błąd przygotowania zapytania DELETE: " . $conn->error]);
    exit();
}

// Przypisanie parametru (i = integer, s = string)
$stmt->bind_param("is", $project_id, $img_url);




$image_path = "../../gallery/" . $img_url;

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
