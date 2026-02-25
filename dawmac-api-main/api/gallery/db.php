<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *"); // Allow all origins, or change to a specific one (e.g., http://127.0.0.1:4321)
header("Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
// header("Cache-Control: public, max-age=31536000, immutable");


// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(204); // No Content
    exit();
}
require $_SERVER['DOCUMENT_ROOT'] . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable($_SERVER['DOCUMENT_ROOT']."/..");
$dotenv->load();

$servername = $_ENV["DB_SERVER"] ?? '';
$username = $_ENV["DB_USER"] ?? '';
$password = $_ENV['DB_PASSWORD'] ?? '';
$dbname = $_ENV['GALLERY_DB_NAME'] ?? '';

// Połączenie z bazą danych z wyciszonymi warningami
$conn = @new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode([
        "error" => "Connection failed: " . $conn->connect_error
    ]);
    exit();
}

mysqli_set_charset($conn, "utf8mb4");

// Tutaj możesz dorzucić kolejne zapytania, np. SELECT itd.

// echo json_encode([
//     "success" => true,
//     "message" => "Connection successful"
// ]);
