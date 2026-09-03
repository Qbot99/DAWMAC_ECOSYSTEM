<?php
require "db.php";

$conn->query("SET SESSION group_concat_max_len = 1000000;");

$sql = "
SELECT wheel.id, wheel.name, wheel.description, wheel.min_weight, series.name AS series_name, wheel.series_id, JSON_ARRAYAGG(image.url) AS images, video.youtube_url FROM wheel JOIN series ON wheel.series_id = series.id LEFT JOIN image ON wheel.id = image.wheel_id LEFT JOIN video ON wheel.id = video.wheel_id GROUP BY wheel.id, wheel.name, wheel.description, wheel.min_weight, series.name, wheel.series_id, video.youtube_url ORDER BY wheel.id DESC;
";

$result = $conn->query($sql);

if (!$result) {
    http_response_code(500);
    echo json_encode([
        "error" => "Błąd zapytania: " . $conn->error
    ]);
    exit();
}

$wheels = [];

while ($row = $result->fetch_assoc()) {
    // images jest stringiem JSON, zdekoduj go do tablicy PHP
    $row['images'] = json_decode($row['images'], true);
    $wheels[] = $row;
}

echo json_encode($wheels);
