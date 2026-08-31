<?php
require 'db.php';
require_once __DIR__ . '/lib/wheel_norm.php';
require_once __DIR__ . '/lib/media_url.php';

function convertToWebP($source, $destination, $quality = 80) {
    $info = getimagesize($source);
    if ($info === false) return false;

    switch ($info['mime']) {
        case 'image/jpeg':
            $image = imagecreatefromjpeg($source);
            break;
        case 'image/png':
            $image = imagecreatefrompng($source);
            imagepalettetotruecolor($image);
            imagealphablending($image, true);
            imagesavealpha($image, true);
            break;
        case 'image/gif':
            $image = imagecreatefromgif($source);
            break;
        default:
            return false;
    }

    $result = imagewebp($image, $destination, $quality);
    imagedestroy($image);
    return $result ? $destination : false;
}

function createThumbnail($source, $destination, $width = 700) {
    list($original_width, $original_height) = getimagesize($source);
    $aspect_ratio = $original_width / $original_height;
    $height = round($width / $aspect_ratio);
    $width = round($width);

    $image = imagecreatefromwebp($source);
    $thumbnail = imagecreatetruecolor($width, $height);
    imagecopyresampled($thumbnail, $image, 0, 0, 0, 0, $width, $height, $original_width, $original_height);

    $result = imagewebp($thumbnail, $destination);
    imagedestroy($image);
    imagedestroy($thumbnail);

    return $result;
}

// -------------------- WALIDACJE --------------------
if (!$conn) {
    http_response_code(500);
    echo json_encode(["error" => "Brak połączenia z bazą danych."]);
    exit();
}

$response = ['errors' => [], 'images' => []];

$wheel_brand = trim($_POST["wheel_brand"] ?? '');
$wheel_model = trim($_POST["wheel_model"] ?? '');
$wheel_params = trim($_POST["wheel_params"] ?? '');
$car_brand_id = trim($_POST["car_brand_id"] ?? '');
$car_model_id = trim($_POST["car_model_id"] ?? '');
$show_in_store = 1; // Możesz zmienić na $_POST["show_in_store"] ?? 1

// Linki opcjonalne. Niepoprawny adres YouTube zapisujemy jako pusty zamiast
// wstawiać na stronę odtwarzacz, który się nie uruchomi.
$youtube_url = dawmac_youtube_url($_POST["youtube_url"] ?? '');
$auction_url = dawmac_auction_url($_POST["auction_url"] ?? '');

if ($car_brand_id === '' || !is_numeric($car_brand_id)) $response['errors'][] = "ID marki auta jest wymagane i musi być liczbą.";
if (!isset($_FILES["images"]) || empty($_FILES["images"]["tmp_name"][0])) $response['errors'][] = "Pliki obrazków felg są wymagane.";

if (!empty($response['errors'])) {
    http_response_code(400);
    echo json_encode($response);
    exit();
}

// -------------------- DODAJ FELGĘ --------------------
// Kolumny *_norm to klucz dopasowania do produktów w sklepie. Liczymy je
// przy zapisie, żeby nowe auto trafiało na karty produktów od razu i żeby
// różnice w zapisie ("Japan Racing " vs "JAPAN RACING") przestały mieć znaczenie.
$brand_norm = dawmac_wheel_norm($wheel_brand);
$model_norm = dawmac_wheel_norm($wheel_model);

$stmt = $conn->prepare("INSERT INTO wheel (brand, model, params, brand_norm, model_norm) VALUES (?, ?, ?, ?, ?)");
if ($stmt) {
    $stmt->bind_param("sssss", $wheel_brand, $wheel_model, $wheel_params, $brand_norm, $model_norm);
    $stmt->execute();
    $wheel_id = $stmt->insert_id;
    $stmt->close();
} else {
    $response["errors"][] = "Błąd zapytania SQL (wheel): " . $conn->error;
    echo json_encode($response);
    exit();
}

// -------------------- DODAJ PROJEKT --------------------
$project_id = null;
if ($car_model_id !== '' && is_numeric($car_model_id)) {
    $stmt = $conn->prepare("INSERT INTO project (car_brand_id, car_model_id, wheel_id, show_in_store, youtube_url, auction_url) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("iiiiss", $car_brand_id, $car_model_id, $wheel_id, $show_in_store, $youtube_url, $auction_url);
} else {
    $stmt = $conn->prepare("INSERT INTO project (car_brand_id, wheel_id, show_in_store, youtube_url, auction_url) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("iiiss", $car_brand_id, $wheel_id, $show_in_store, $youtube_url, $auction_url);
}

if ($stmt) {
    $stmt->execute();
    $project_id = $stmt->insert_id;
    $stmt->close();
} else {
    $response["errors"][] = "Błąd zapytania SQL (project): " . $conn->error;
    echo json_encode($response);
    exit();
}

// -------------------- PRZETWARZANIE PLIKÓW --------------------
$uploadDir = __DIR__ . "/../../gallery/images/$project_id/";
$publicPath = "/images/$project_id/";

if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

foreach ($_FILES['images']['tmp_name'] as $key => $tmp_name) {
    $originalName = pathinfo($_FILES['images']['name'][$key], PATHINFO_FILENAME);
    $safeName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $originalName) . ".webp";

    $destination = $uploadDir . $safeName;
    $publicImagePath = $publicPath . $safeName;

    if (convertToWebP($tmp_name, $destination)) {
        $response['images'][] = [
            "name" => $safeName,
            "path" => $publicImagePath,
        ];

        $thumbPath = $uploadDir . "thumb700_" . $safeName;
        createThumbnail($destination, $thumbPath);
    } else {
        $response["errors"][] = "Błąd konwersji pliku: " . $_FILES['images']['name'][$key];
    }
}

// -------------------- ZAPIS DO BAZY ZDJĘĆ --------------------
if (!empty($response['images'])) {
    $stmt = $conn->prepare("INSERT INTO project_images (image_url, project_id) VALUES (?, ?)");
    if ($stmt) {
        foreach ($response['images'] as $image) {
            $stmt->bind_param("si", $image['path'], $project_id);
            $stmt->execute();
        }
        $stmt->close();
    } else {
        $response["errors"][] = "Błąd zapytania SQL (project_images): " . $conn->error;
    }
}

$conn->close();

// -------------------- KOŃCOWA ODPOWIEDŹ --------------------
if (empty($response["errors"])) {
    header('Location: /');
} else {
    header('Content-Type: application/json');
    echo json_encode($response);
}
?>
