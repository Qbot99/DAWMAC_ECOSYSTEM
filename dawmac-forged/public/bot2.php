<?php
// Open Graph dla botów social media (WhatsApp/FB/Telegram...) — .htaccess kieruje tu boty.
// v2: dopasowanie felgi także po legacy_name (stare nazwy sprzed ujednolicenia FM/FD/FT),
// żeby linki udostępnione przed migracją dalej pokazywały właściwe zdjęcie.
$uri = $_SERVER['REQUEST_URI'];

$title = "Dawmac Forged | Felgi Kute";
$description = "Ekskluzywne felgi kute na zamówienie - Forged Mono, Duo, Trio i Magnesium.";
$image = "https://forged.dawmacpolska.pl/monoblock.jpeg";
$url = "https://forged.dawmacpolska.pl" . $uri;

if (preg_match('/^\/wheel\/([a-zA-Z0-9_\-%\.]+)/', $uri, $matches)) {
    $rimName = urldecode($matches[1]);
    $title = "Dawmac Forged | Felga " . strtoupper($rimName);

    $apiUrl = "https://api.dawmacpolska.pl/api/forged/list_wheels.php";
    $context = stream_context_create([
        "ssl" => ["verify_peer" => false, "verify_peer_name" => false]
    ]);
    $apiResponse = @file_get_contents($apiUrl, false, $context);

    if ($apiResponse) {
        $wheelsData = json_decode($apiResponse, true);
        if (is_array($wheelsData)) {
            $clean = fn($s) => str_replace(' ', '', strtolower((string)$s));
            $needle = $clean($rimName);

            foreach ($wheelsData as $wheel) {
                $matchesName = isset($wheel['name']) && $clean($wheel['name']) === $needle;
                $matchesLegacy = isset($wheel['legacy_name']) && $wheel['legacy_name'] !== null
                    && $clean($wheel['legacy_name']) === $needle;

                if ($matchesName || $matchesLegacy) {
                    // stary link ze starą nazwą -> tytuł i URL z nową nazwą
                    if ($matchesLegacy && !$matchesName) {
                        $title = "Dawmac Forged | Felga " . strtoupper($wheel['name']);
                        $url = "https://forged.dawmacpolska.pl/wheel/" . rawurlencode($wheel['name']);
                    }
                    $images = array_values(array_filter((array)($wheel['images'] ?? [])));
                    if (!empty($images)) {
                        $image = "https://api.dawmacpolska.pl/forged/" . $images[0];
                    }
                    break;
                }
            }
        }
    }
}
?>
<!doctype html>
<html lang="pl">
<head>
    <meta charset="UTF-8" />
    <title><?= htmlspecialchars($title) ?></title>

    <meta property="og:title" content="<?= htmlspecialchars($title) ?>" />
    <meta property="og:description" content="<?= htmlspecialchars($description) ?>" />
    <meta property="og:image" content="<?= htmlspecialchars($image) ?>" />
    <meta property="og:image:width" content="1200" />
    <meta property="og:image:height" content="630" />
    <meta property="og:url" content="<?= htmlspecialchars($url) ?>" />
    <meta property="og:type" content="website" />

    <meta name="twitter:card" content="summary_large_image" />
    <meta name="twitter:title" content="<?= htmlspecialchars($title) ?>" />
    <meta name="twitter:description" content="<?= htmlspecialchars($description) ?>" />
    <meta name="twitter:image" content="<?= htmlspecialchars($image) ?>" />
</head>
<body>
    <script>
        window.location.replace("<?= htmlspecialchars($url) ?>");
    </script>
</body>
</html>
