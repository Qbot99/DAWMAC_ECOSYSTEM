<?php
session_start();

header('Access-Control-Allow-Origin: http://localhost');
header('Access-Control-Allow-Origin: http://localhost:5173');
header('Access-Control-Allow-Credentials: true');
header('Content-Type: application/json');

if (isset($_SESSION['user'])) {
    echo json_encode(['loggedIn' => true, 'username' => $_SESSION['user']]);
} else {
    echo json_encode(['loggedIn' => false]);
}
