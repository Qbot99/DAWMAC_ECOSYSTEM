<?php
/**
 * Router dla wbudowanego serwera PHP (lokalnie i w testach):
 *   php -S localhost:8080 tests/dev-router.php
 * /api/...     → api/index.php
 * /uploads/... → pliki zdjęć
 */
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

if (str_starts_with($path, '/uploads/')) {
    $file = (getenv('UPLOAD_DIR') ?: __DIR__ . '/../uploads') . substr($path, strlen('/uploads'));
    if (is_file($file) && !str_contains($path, '..')) {
        header('Content-Type: image/webp');
        readfile($file);
        return true;
    }
    http_response_code(404);
    return true;
}

if (str_starts_with($path, '/api/') || $path === '/api') {
    $_SERVER['SCRIPT_NAME'] = '/api/index.php';
    $_SERVER['PATH_INFO'] = substr($path, 4);
    require __DIR__ . '/../api/index.php';
    return true;
}

http_response_code(404);
echo 'dev-router: tylko /api i /uploads (front: npm run dev w web/)';
return true;
