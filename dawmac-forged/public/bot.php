<?php
$uri = $_SERVER['REQUEST_URI'];

// default values
$title = "Dawmac Forged | Felgi Kute";
$description = "Ekskluzywne felgi kute na zamówienie.";
$image = "https://forged.dawmacpolska.pl/monoblock.jpeg";
$url = "https://forged.dawmacpolska.pl" . $uri;

if (preg_match('/^\/wheel\/([a-zA-Z0-9_\-%]+)/', $uri, $matches)) {
  // urldecode in case name has spaces or %20
  $rimName = urldecode($matches[1]);
  $title = "Dawmac Forged | Felga " . strtoupper($rimName);

  // Check if image exists
  $found = false;
  $extensions = ['jpg', 'jpeg', 'png', 'webp'];
  foreach ($extensions as $ext) {
    $testImg = "hero-baner-img/" . $rimName . "." . $ext;
    if (file_exists(__DIR__ . "/" . $testImg)) {
      $image = "https://forged.dawmacpolska.pl/" . $testImg;
      $found = true;
      break;
    }
  }

  // Fallback if image not found locally, check the API database
  if (!$found) {
    $apiUrl = "https://api.dawmacpolska.pl/api/forged/list_wheels.php";
    $arrContextOptions = [
      "ssl" => [
        "verify_peer" => false,
        "verify_peer_name" => false,
      ],
    ];

    $apiResponse = @file_get_contents($apiUrl, false, stream_context_create($arrContextOptions));

    if ($apiResponse) {
      $wheelsData = json_decode($apiResponse, true);
      if (is_array($wheelsData)) {
        $cleanRimName = str_replace(' ', '', strtolower($rimName)); // Usuń spacje, aby ujednolicić np. "FM 427" i "FM427"

        foreach ($wheelsData as $wheel) {
          if (isset($wheel['name']) && str_replace(' ', '', strtolower($wheel['name'])) === $cleanRimName) {
            if (isset($wheel['images']) && is_array($wheel['images']) && count($wheel['images']) > 0) {
              // Baza danych zwraca ścieżki w formacie "wheels_images/ID/plik.jpeg"
              $dbImagePath = ltrim($wheel['images'][0], '/');
              // Wrap the PATH in resize.php so WhatsApp accepts it and we avoid nested URL detection
              $image = "https://forged.dawmacpolska.pl/resize.php?path=" . urlencode($dbImagePath) . "&v=.jpg";
              $found = true;
              break;
            }
          }
        }
      }
    }

    // Final fallback to generic image if not found in db
    if (!$found) {
      $image = "https://forged.dawmacpolska.pl/monoblock.jpeg";
    }
  }
}

?>
<!doctype html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <title><?php echo htmlspecialchars($title); ?></title>
  <meta property="og:title" content="<?php echo htmlspecialchars($title); ?>" />
  <meta property="og:description" content="<?php echo htmlspecialchars($description); ?>" />
  <meta property="og:image" content="<?php echo htmlspecialchars($image); ?>" />
  <meta property="og:image:width" content="800" />
  <meta property="og:image:height" content="800" />
  <meta property="og:url" content="<?php echo htmlspecialchars($url); ?>" />
  <meta property="og:type" content="website" />

  <meta name="twitter:card" content="summary_large_image" />
  <meta name="twitter:title" content="<?php echo htmlspecialchars($title); ?>" />
  <meta name="twitter:description" content="<?php echo htmlspecialchars($description); ?>" />
  <meta name="twitter:image" content="<?php echo htmlspecialchars($image); ?>" />
</head>

<body>
  <!-- Jeśli ten plik zostanie otwarty przez normalną przeglądarkę, to przekierowujemy na front -->
  <p>Przekierowywanie...</p>
  <script>
    window.location.replace("<?php echo htmlspecialchars($url); ?>");
  </script>
</body>

</html>