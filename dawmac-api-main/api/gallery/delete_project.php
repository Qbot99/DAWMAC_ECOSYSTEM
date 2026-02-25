<?php
require 'db.php';

header('Content-Type: application/json');

$response = [];

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405); // Method Not Allowed
    echo json_encode(["error" => "Nieprawidłowa metoda żądania. Oczekiwano POST."]);
    exit;
}

if (!isset($_POST['gallery_id'])) {
    http_response_code(400); // Bad Request
    echo json_encode(["error" => "Brak gallery_id w przesłanych danych."]);
    exit;
}

$gallery_id = $_POST['gallery_id'];
$response["debug"] = "Odebrane gallery_id: " . htmlspecialchars($gallery_id);

$conn->begin_transaction();

try {
    // Usuń zdjęcia powiązane z projektem
    $stmt = $conn->prepare("DELETE FROM project_images WHERE project_id = ?");
    if (!$stmt) throw new Exception("Błąd przygotowania zapytania project_images: " . $conn->error);
    $stmt->bind_param("i", $gallery_id);
    if (!$stmt->execute()) throw new Exception("Błąd wykonania project_images: " . $stmt->error);
    $stmt->close();

    // Pobierz powiązane wheel_id
    $stmt = $conn->prepare("SELECT wheel_id FROM project WHERE id = ?");
    if (!$stmt) throw new Exception("Błąd przygotowania zapytania SELECT wheel_id: " . $conn->error);
    $stmt->bind_param("i", $gallery_id);
    if (!$stmt->execute()) throw new Exception("Błąd wykonania SELECT wheel_id: " . $stmt->error);
    $stmt->bind_result($wheel_id);
    $stmt->fetch();
    $stmt->close();

    // Usuń projekt
    $stmt = $conn->prepare("DELETE FROM project WHERE id = ?");
    if (!$stmt) throw new Exception("Błąd przygotowania zapytania project: " . $conn->error);
    $stmt->bind_param("i", $gallery_id);
    if (!$stmt->execute()) throw new Exception("Błąd wykonania DELETE project: " . $stmt->error);
    $stmt->close();

    // Usuń wheel jeśli istnieje
    if (!empty($wheel_id)) {
        $stmt = $conn->prepare("DELETE FROM wheel WHERE id = ?");
        if (!$stmt) throw new Exception("Błąd przygotowania zapytania wheel: " . $conn->error);
        $stmt->bind_param("i", $wheel_id);
        if (!$stmt->execute()) throw new Exception("Błąd wykonania DELETE wheel: " . $stmt->error);
        $stmt->close();
    }

    $conn->commit();
    $response["success"] = true;
} catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    $response["errors"][] = $e->getMessage();
}

$conn->close();

echo json_encode($response);
