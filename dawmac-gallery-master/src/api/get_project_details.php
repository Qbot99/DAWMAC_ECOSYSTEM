<?php
require 'db.php';

$project_id = $_GET['id'];
$result = $conn->query("SELECT 
    p.id AS project_id, 
    w.brand, 
    w.model, 
    w.params, 
    p.show_in_store,
    GROUP_CONCAT(pi.image_url SEPARATOR ', ') AS images 
FROM project p 
JOIN wheel w ON p.wheel_id = w.id 
LEFT JOIN project_images pi ON p.id = pi.project_id 
WHERE p.id = ".$project_id." 
GROUP BY p.id, w.brand, w.model, w.params
ORDER BY p.id 
LIMIT 0, 25;
");

$data = $result->fetch_all(MYSQLI_ASSOC);
echo json_encode($data);

$conn->close();
?>
