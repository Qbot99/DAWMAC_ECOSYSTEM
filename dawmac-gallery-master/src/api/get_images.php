<?php
require 'db.php';

if (!isset($_GET['project_id'])) {
    echo json_encode(["error" => "Brak wymaganego parametru project_id"]);
    exit;
}

$project_id = (int) $_GET['project_id'];

$query = "SELECT id, image_url, is_primary FROM project_images WHERE project_id = ? ORDER BY is_primary DESC, id ASC";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $project_id);
$stmt->execute();
$result = $stmt->get_result();

$images = $result->fetch_all(MYSQLI_ASSOC);
echo json_encode($images);

$stmt->close();
$conn->close();
?>
