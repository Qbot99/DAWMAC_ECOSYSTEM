<?php
require 'db.php'; // Połączenie z bazą danych

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $project_id = $_POST['gallery_id'] ?? null;
    $primary_image = $_POST['primary_image'] ?? null;
    
    if ($project_id && $primary_image) {
        // Resetowanie wszystkich obrazów do is_primary = 0
        $resetQuery = "UPDATE project_images SET is_primary = 0 WHERE project_id = ?";
        $stmt = $conn->prepare($resetQuery);
        $stmt->bind_param("i", $project_id);
        $stmt->execute();
        
        // Ustawienie nowego obrazu jako głównego
        $updateQuery = "UPDATE project_images SET is_primary = 1 WHERE project_id = ? AND image_url = ?";
        $stmt = $conn->prepare($updateQuery);
        $stmt->bind_param("is", $project_id, $primary_image);
        $stmt->execute();
        
        echo json_encode(["success" => true, "message" => "Główne zdjęcie zostało ustawione."]);
    } else {
        echo json_encode(["success" => false, "message" => "Błąd: Nie wybrano galerii lub zdjęcia."]);
    }
} else {
    echo json_encode(["success" => false, "message" => "Błędne żądanie."]);
}

$conn->close();
?>
