<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require 'db.php'; // Upewnij się, że ścieżka jest poprawna

// Odczytaj dane z żądania
$data = json_decode(file_get_contents("php://input"), true);
$username = $data['username'] ?? '';
$password = $data['password'] ?? '';

// Sprawdź, czy użytkownik istnieje
$stmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

// Sprawdź, czy użytkownik został znaleziony
if ($user) {
    // Sprawdź, czy hasło jest poprawne
    if (password_verify($password, $user['password'])) {
        $_SESSION['user'] = $user['username'];
        echo json_encode(["success" => true]);
    } else {
        echo json_encode(["success" => false, "error" => $user['password']]);
    }
} else {
    echo json_encode(["success" => false, "error" => "Nie znaleziono użytkownika"]);
}

$stmt->close();
$conn->close();
?>
