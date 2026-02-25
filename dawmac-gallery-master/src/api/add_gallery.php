<?php
require 'db.php';

$conn->query("SET NAMES 'utf8mb4'");
$conn->query("SET CHARACTER SET 'utf8mb4'");


function convertToWebP($source, $destination, $quality = 80) {
    $info = getimagesize($source);
    if ($info === false) {
        return false;
    }

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
    // Uzyskaj oryginalne wymiary obrazu
    list($original_width, $original_height) = getimagesize($source);
    $aspect_ratio = $original_width / $original_height;
    
    // Oblicz nową wysokość zachowując proporcje
    $height = $width / $aspect_ratio;

    // Jeśli $width i $height są liczbami zmiennoprzecinkowymi, użyj round() lub floor() przed przypisaniem do zmiennej typu int:
    $width = round($width); // zaokrągla do najbliższej liczby całkowitej
    $height = round($height); // zaokrągla do najbliższej liczby całkowitej

    // Utwórz obrazek
    $image = imagecreatefromwebp($source);
    $thumbnail = imagecreatetruecolor($width, $height);

    // Zmień rozmiar obrazu
    imagecopyresampled($thumbnail, $image, 0, 0, 0, 0, $width, $height, $original_width, $original_height);

    // Zapisz miniaturkę jako plik WebP
    $result = imagewebp($thumbnail, $destination);
    imagedestroy($image);
    imagedestroy($thumbnail);
    
    return $result;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    header('Content-Type: application/json');

    $response = [
        "car" => [
            "brand" => $_POST['car_brand'],
            "model" => $_POST['car_model'] ?? null,
        ],
        "wheel" => [
            "brand" => $_POST['wheel_brand'] ?? 'Brak danych',
            "model" => $_POST['wheel_model'] ?? 'Brak danych',
            "params" => $_POST['wheel_params'] ?? 'Brak danych',
        ],
        "images" => [],
        "errors" => []
    ];
    $show_in_store = isset($_POST['dont_show_in_store']) ? 0 : 1;

    // Przesyłanie plików
    if (!empty($_FILES['images']['name'][0])) {
        foreach ($_FILES['images']['tmp_name'] as $key => $tmp_name) {
            $uploadDir = "images/";
            $fileName = pathinfo($_FILES['images']['name'][$key], PATHINFO_FILENAME) . ".webp";
            $destination = $uploadDir . $fileName;

            // Konwertowanie na WebP
            if ($webpPath = convertToWebP($tmp_name, "../" . $destination)) {
                $response['images'][] = [
                    "name" => $fileName,
                    "path" => $destination,
                ];

                // Tworzenie miniaturki o szerokości 700px
                $thumbnailPath = $uploadDir . "thumb700_" . $fileName;
                if (!createThumbnail("../" . $destination, "../" . $thumbnailPath)) {
                    $response["errors"][] = "Błąd tworzenia miniaturki dla: " . $_FILES['images']['name'][$key];
                }
            } else {
                $response["errors"][] = "Błąd konwersji pliku: " . $_FILES['images']['name'][$key];
            }
        }
    }

    // Wstawianie danych do `wheel`
    $stmt = $conn->prepare("INSERT INTO wheel (brand, model, params) VALUES (?, ?, ?)");
    if ($stmt) {
        $stmt->bind_param("sss", $response['wheel']['brand'], $response['wheel']['model'], $response['wheel']['params']);
        $stmt->execute();
        $wheel_id = $stmt->insert_id;
        $stmt->close();
    } else {
        $response["errors"][] = "Błąd zapytania SQL (wheel): " . $conn->error;
    }
    
    // Wstawianie danych do `project`
    if ($response['car']['model']) {
        $stmt = $conn->prepare("INSERT INTO project (car_brand_id, car_model_id, wheel_id, show_in_store) VALUES (?, ?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param("ssii", $response['car']['brand'], $response['car']['model'], $wheel_id, $show_in_store);
            $stmt->execute();
            $project_id = $stmt->insert_id;
            $stmt->close();
        } else {
            $response["errors"][] = "Błąd zapytania SQL (project): " . $conn->error;
        }
    } else {
        $stmt = $conn->prepare("INSERT INTO project (car_brand_id, wheel_id, show_in_store) VALUES (?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param("sii", $response['car']['brand'], $wheel_id, $show_in_store);
            $stmt->execute();
            $project_id = $stmt->insert_id;
            $stmt->close();
        } else {
            $response["errors"][] = "Błąd zapytania SQL (project bez modelu): " . $conn->error;
        }
    }

    // Wstawianie zdjęć do `project_images`
    if (!empty($response['images']) && isset($project_id)) {
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

    // Zamknięcie połączenia
    $conn->close();
    
    // Przekierowanie lub odpowiedź JSON
    if (empty($response["errors"])) {
        header('Location: /adminPanel');
    } else {
        echo json_encode($response);
    }
}
?>
