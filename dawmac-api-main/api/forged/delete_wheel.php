<?php

require "db.php";

$wheel_id = trim($_POST["wheel_id"] ?? '');
if ($wheel_id === '' || !is_numeric($wheel_id)) {
    http_response_code(400);
    echo json_encode(["error" => "ID felgi jest wymagane i musi być liczbą."]);
    exit();
}

// Przygotowanie zapytania DELETE
$stmt = $conn->prepare("DELETE FROM `wheel` WHERE `id` = ?");
if (!$stmt) {
    http_response_code(500);
    echo json_encode(["error" => "Błąd przygotowania zapytania DELETE: " . $conn->error]);
    exit();
}

// Przypisanie parametru (i = integer)
$stmt->bind_param("i", $wheel_id);



// Delete folder
$folder_dir = "../../forged/wheels_images/".$wheel_id;



// Wykonanie zapytania
if ($stmt->execute() && deleteFolder($folder_dir)) {
    echo json_encode(["success" => true, "message" => "Felga została usunięta."]);
} else {
    http_response_code(500);
    if ($stmt->error){
    echo json_encode(["error" => "Błąd podczas usuwania felgi: " . $stmt->error]);
}
else{
    echo json_encode(["error" => "Nie udało się usunąć folderu."]);
}
}

$stmt->close();
$conn->close();



function deleteFolder($folderPath) {
    if (!is_dir($folderPath)) {
        return false;
    }

    $items = scandir($folderPath);
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }

        $itemPath = $folderPath . DIRECTORY_SEPARATOR . $item;
        if (is_dir($itemPath)) {
            deleteFolder($itemPath); // rekurencja
        } else {
            unlink($itemPath);
        }
    }

    return rmdir($folderPath);
}