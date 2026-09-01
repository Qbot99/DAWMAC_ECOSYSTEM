<?php
// resize.php
$path = $_GET['path'] ?? '';

if (!$path) {
    http_response_code(400);
    exit("Missing path");
}

// Build the API URL securely
$url = "https://api.dawmacpolska.pl/forged/" . ltrim($path, '/');

// Fetch the image
$arrContextOptions = [
    "ssl" => [
        "verify_peer" => false,
        "verify_peer_name" => false,
    ],
];

$imageData = @file_get_contents($url, false, stream_context_create($arrContextOptions));

if (!$imageData) {
    http_response_code(404);
    exit("Not found");
}

// Create image from string
$img = @imagecreatefromstring($imageData);
if (!$img) {
    // If it fails (maybe too large or unsupported format), just output original data as fallback
    header('Content-Type: image/jpeg');
    header('Content-Length: ' . strlen($imageData));
    header("Cache-Control: public, max-age=2592000");
    echo $imageData;
    exit();
}

// Resize image to max 800px width
$width = imagesx($img);
$height = imagesy($img);
$targetWidth = 800;

if ($width > $targetWidth) {
    $targetHeight = floor($height * ($targetWidth / $width));
    $newImg = imagecreatetruecolor($targetWidth, $targetHeight);

    // Handle transparency for PNGs
    imagealphablending($newImg, false);
    imagesavealpha($newImg, true);
    $transparent = imagecolorallocatealpha($newImg, 255, 255, 255, 127);
    imagefilledrectangle($newImg, 0, 0, $targetWidth, $targetHeight, $transparent);

    imagecopyresampled($newImg, $img, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);
    imagedestroy($img);
    $img = $newImg;
}

// Buffer the output so we can calculate Content-Length
ob_start();
imagejpeg($img, null, 75);
$final_image_data = ob_get_clean();
imagedestroy($img);

// Output as JPEG with 75% quality (WhatsApp friendly, typically < 100kb), with Content-Length header!
header('Content-Type: image/jpeg');
header('Content-Length: ' . strlen($final_image_data));
header('Content-Disposition: inline; filename="preview.jpg"');
header("Cache-Control: public, max-age=2592000");
echo $final_image_data;
