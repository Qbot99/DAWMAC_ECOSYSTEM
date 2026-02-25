<?php
require 'db.php';

$car_brand_id = $_GET['car_brand_id'];

$result = $conn->query("SELECT id,name FROM car_model WHERE car_model.car_brand_id = ".$car_brand_id." ORDER BY car_model.name;");
$data = $result->fetch_all(MYSQLI_ASSOC);
echo json_encode($data);

$conn->close();

?>
