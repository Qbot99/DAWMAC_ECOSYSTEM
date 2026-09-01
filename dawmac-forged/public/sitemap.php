<?php
// Dynamiczna sitemapa: strona główna + adres każdej felgi (/wheel/NAZWA).
// Google indeksuje felgi osobno, mimo że UI otwiera je w lightboxie.
header('Content-Type: application/xml; charset=utf-8');

const BASE = 'https://forged.dawmacpolska.pl';
const CACHE_FILE = __DIR__ . '/sitemap-cache.xml';
const CACHE_TTL = 86400; // doba

if (file_exists(CACHE_FILE) && (time() - filemtime(CACHE_FILE)) < CACHE_TTL) {
    readfile(CACHE_FILE);
    exit;
}

$urls = [BASE . '/', BASE . '/d2'];

// D2 Forged: modele wynikają wprost z plików zdjęć w /d2/wheels/
foreach (glob(__DIR__ . '/d2/wheels/*.webp') ?: [] as $f) {
    $urls[] = BASE . '/d2/wheel/' . rawurlencode(basename($f, '.webp'));
}

$context = stream_context_create([
    "ssl" => ["verify_peer" => false, "verify_peer_name" => false]
]);
$apiResponse = @file_get_contents('https://api.dawmacpolska.pl/api/forged/list_wheels.php', false, $context);
if ($apiResponse) {
    $wheels = json_decode($apiResponse, true);
    if (is_array($wheels)) {
        foreach ($wheels as $w) {
            if (!empty($w['name'])) {
                $urls[] = BASE . '/wheel/' . rawurlencode($w['name']);
            }
        }
    }
}

$xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
$xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
foreach ($urls as $u) {
    $xml .= "  <url><loc>" . htmlspecialchars($u, ENT_XML1) . "</loc></url>\n";
}
$xml .= "</urlset>\n";

@file_put_contents(CACHE_FILE, $xml);
echo $xml;
