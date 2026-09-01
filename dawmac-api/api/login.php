<?php
session_start();

$allowedOrigins = ['http://localhost:5173', 'http://localhost'];
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

if (in_array($origin, $allowedOrigins)) {
    header("Access-Control-Allow-Origin: $origin");
    header('Access-Control-Allow-Credentials: true');
}

header('Content-Type: application/json');

// Obsłuż preflight request (OPTIONS)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    exit;
}

$users = include 'users.php';

$data = json_decode(file_get_contents('php://input'), true);
$username = $data['username'] ?? '';
$password = $data['password'] ?? '';

foreach ($users as $user) {
  if ($user['username'] === $username && password_verify($password, $user['password'])) {
    session_regenerate_id(true);
    $_SESSION['user'] = $username;
    echo json_encode(['success' => true]);
    exit;
  }
}

http_response_code(401);
echo json_encode(['success' => false, 'message' => 'Invalid credentials']);
