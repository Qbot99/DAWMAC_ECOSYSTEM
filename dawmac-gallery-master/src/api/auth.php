<?php

header("Access-Control-Allow-Origin: *"); // Pozwala na dostęp ze wszystkich domen (dla testów)
header("Access-Control-Allow-Methods: GET, POST, OPTIONS"); // Dozwolone metody
header("Access-Control-Allow-Headers: Content-Type, Authorization"); // Dozwolone nagłówki

// Obsługa preflight request dla CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}


session_start();



echo json_encode(["authenticated" => isset($_SESSION['user'])]);
?>
