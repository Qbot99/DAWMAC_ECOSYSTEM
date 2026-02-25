<?php
require 'db.php';


$result = $conn->query("SELECT * FROM car_brand ORDER BY car_brand.name;");
$data = $result->fetch_all(MYSQLI_ASSOC);
echo json_encode($data);

$conn->close();

?>
