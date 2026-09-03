<?php
require "db.php";

$conn->query("SET SESSION group_concat_max_len = 1000000;");

$sql = "
SELECT wheel.id, wheel.name, wheel.description, wheel.min_weight, series.name AS series_name, wheel.series_id,
       GROUP_CONCAT(image.url ORDER BY image.is_primary DESC, image.id ASC SEPARATOR '\n') AS images,
       video.youtube_url
FROM wheel
JOIN series ON wheel.series_id = series.id
LEFT JOIN image ON wheel.id = image.wheel_id
LEFT JOIN video ON wheel.id = video.wheel_id
GROUP BY wheel.id, wheel.name, wheel.description, wheel.min_weight, series.name, wheel.series_id, video.youtube_url
ORDER BY wheel.id DESC;
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
    // Zdjęcia przychodzą jako lista rozdzielona nową linią, główne (is_primary)
    // jest pierwsze. Strona forged i panel traktują images[0] jako główne.
    $row['images'] = ($row['images'] === null || $row['images'] === '')
        ? []
        : explode("\n", $row['images']);
    $wheels[] = $row;
}

echo json_encode($wheels);
