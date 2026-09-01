<?php
require 'db.php';

if (!$conn) {
    http_response_code(500);
    echo json_encode(["error" => "Brak połączenia z bazą danych."]);
    exit();
}

$wheel_name = trim($_POST["wheel_name"] ?? '');
if ($wheel_name === '') {
    http_response_code(400);
    echo json_encode(["error" => "Nazwa felgi jest wymagana."]);
    exit();
}

$series_id = trim($_POST["series"] ?? '');
if ($series_id === '' || !is_numeric($series_id)) {
    http_response_code(400);
    echo json_encode(["error" => "ID serii jest wymagane i musi być liczbą."]);
    exit();
}

if (!isset($_FILES["wheel_image"])) {
    http_response_code(400);
    echo json_encode(["error" => "Pliki obrazków felg są wymagane."]);
    exit();
}

// Zapis felgi do bazy z użyciem prepared statement
$stmt = $conn->prepare("INSERT INTO `wheel` (`name`, `series_id`) VALUES (?, ?)");
if (!$stmt) {
    http_response_code(500);
    echo json_encode(["error" => "Błąd przygotowania zapytania do bazy: " . $conn->error]);
    exit();
}
$stmt->bind_param("si", $wheel_name, $series_id);

if (!$stmt->execute()) {
    http_response_code(500);
    echo json_encode(["error" => "Błąd zapisu felgi: " . $stmt->error]);
    exit();
}

$wheel_id = $stmt->insert_id;
$stmt->close();

// Tworzenie katalogu dopiero teraz, bo dopiero teraz mamy $wheel_id
$wheels_images_dir = "../../forged/wheels_images";
$target_dir = $wheels_images_dir . "/" . $wheel_id;

if (!is_dir($target_dir)) {
    if (!mkdir($target_dir, 0777, true)) {
        http_response_code(500);
        echo json_encode(["error" => "Nie można utworzyć katalogu docelowego."]);
        exit();
    }
}

$uploaded_files = $_FILES["wheel_image"];
$success_files = [];
$error_files = [];

// Prepared statement do wstawiania obrazków
$stmt_img = $conn->prepare("INSERT INTO `image` (`url`, `wheel_id`) VALUES (?, ?)");
if (!$stmt_img) {
    http_response_code(500);
    echo json_encode(["error" => "Błąd przygotowania zapytania do bazy (image): " . $conn->error]);
    exit();
}

for ($i = 0; $i < count($uploaded_files["name"]); $i++) {
    if ($uploaded_files["error"][$i] !== UPLOAD_ERR_OK) {
        $error_files[] = [
            "file" => $uploaded_files["name"][$i],
            "error" => "Błąd przesyłania pliku: kod " . $uploaded_files["error"][$i]
        ];
        continue;
    }

    $filename = basename($uploaded_files["name"][$i]);
    $filename = preg_replace('/[^a-zA-Z0-9_\.\-]/', '_', $filename);
    $target_file = $target_dir . "/" . $filename;

    if (!is_uploaded_file($uploaded_files["tmp_name"][$i])) {
        $error_files[] = [
            "file" => $filename,
            "error" => "Plik tymczasowy nie istnieje."
        ];
        continue;
    }

    if (move_uploaded_file($uploaded_files["tmp_name"][$i], $target_file)) {
        $success_files[] = $filename;

        $file_location = "wheels_images/" . $wheel_id . "/" . $filename;
        $stmt_img->bind_param("si", $file_location, $wheel_id);

        if (!$stmt_img->execute()) {
            $error_files[] = [
                "file" => $filename,
                "error" => "Błąd zapisu ścieżki pliku w bazie: " . $stmt_img->error
            ];
        }
    } else {
        $error_files[] = [
            "file" => $filename,
            "error" => "Błąd podczas przesyłania pliku."
        ];
    }
}

$stmt_img->close();
$conn->close();

if (count($error_files) > 0) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "uploaded" => $success_files,
        "errors" => $error_files
    ]);
} else {
    echo json_encode([
        "success" => true,
        "uploaded" => $success_files,
        "message" => "Wszystkie pliki zostały przesłane pomyślnie."
    ]);
}
