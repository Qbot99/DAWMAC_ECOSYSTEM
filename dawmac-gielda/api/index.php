<?php
/**
 * DAWMAC Giełda — jedno wejście do API.
 *
 * .htaccess kieruje /api/cokolwiek tutaj; bez mod_rewrite działa też
 * /api/index.php/cokolwiek albo /api/index.php?r=/cokolwiek.
 */

declare(strict_types=1);

require __DIR__ . '/lib/bootstrap.php';
require __DIR__ . '/lib/validate.php';
require __DIR__ . '/lib/auth.php';
require __DIR__ . '/lib/mail.php';
require __DIR__ . '/lib/images.php';
require __DIR__ . '/routes/auth.php';
require __DIR__ . '/routes/listings.php';
require __DIR__ . '/routes/messages.php';
require __DIR__ . '/routes/reports.php';
require __DIR__ . '/routes/moderation.php';

const ROUTES = [
    // config i słownik aut
    ['GET',    '/config',                        'route_config'],
    ['GET',    '/cars/brands',                   'route_car_brands'],
    ['GET',    '/cars/models',                   'route_car_models'],
    // konto
    ['POST',   '/auth/register',                 'route_register'],
    ['POST',   '/auth/verify',                   'route_verify'],
    ['POST',   '/auth/resend',                   'route_resend_verification'],
    ['POST',   '/auth/login',                    'route_login'],
    ['POST',   '/auth/logout',                   'route_logout'],
    ['POST',   '/auth/forgot',                   'route_forgot'],
    ['POST',   '/auth/reset',                    'route_reset'],
    ['GET',    '/me',                            'route_me'],
    ['POST',   '/me',                            'route_update_me'],
    ['POST',   '/me/delete',                     'route_delete_me'],
    ['GET',    '/me/export',                     'route_export_me'],
    ['GET',    '/me/listings',                   'route_my_listings'],
    ['GET',    '/me/favorites',                  'route_my_favorites'],
    ['GET',    '/me/notifications',              'route_notifications'],
    ['POST',   '/me/notifications/read',         'route_notifications_read'],
    // ogłoszenia
    ['GET',    '/listings',                      'route_listings_index'],
    ['POST',   '/listings',                      'route_listing_create'],
    ['GET',    '/listings/(\d+)',                'route_listing_show'],
    ['POST',   '/listings/(\d+)',                'route_listing_update'],
    ['DELETE', '/listings/(\d+)',                'route_listing_delete'],
    ['POST',   '/listings/(\d+)/status',         'route_listing_status'],
    ['GET',    '/listings/(\d+)/phone',          'route_listing_phone'],
    ['POST',   '/listings/(\d+)/images',         'route_listing_add_images'],
    ['POST',   '/listings/(\d+)/images/order',   'route_listing_order_images'],
    ['DELETE', '/listings/(\d+)/images/(\d+)',   'route_listing_delete_image'],
    ['POST',   '/listings/(\d+)/favorite',       'route_favorite_toggle'],
    ['POST',   '/listings/(\d+)/messages',       'route_listing_message'],
    ['GET',    '/users/(\d+)',                   'route_user_profile'],
    // wiadomości
    ['GET',    '/conversations',                 'route_conversations'],
    ['GET',    '/conversations/(\d+)',           'route_conversation_show'],
    ['POST',   '/conversations/(\d+)',           'route_conversation_reply'],
    // zgłoszenia
    ['POST',   '/reports',                       'route_report_create'],
    // panel pracownika
    ['GET',    '/mod/stats',                     'route_mod_stats'],
    ['GET',    '/mod/reports',                   'route_mod_reports'],
    ['POST',   '/mod/reports/(\d+)',             'route_mod_report_resolve'],
    ['GET',    '/mod/listings',                  'route_mod_listings'],
    ['POST',   '/mod/listings/(\d+)/remove',     'route_mod_listing_remove'],
    ['POST',   '/mod/listings/(\d+)/restore',    'route_mod_listing_restore'],
    ['GET',    '/mod/users',                     'route_mod_users'],
    ['POST',   '/mod/users/(\d+)/ban',           'route_mod_user_ban'],
    ['POST',   '/mod/users/(\d+)/unban',         'route_mod_user_unban'],
    ['POST',   '/mod/users/(\d+)/role',          'route_mod_user_role'],
    ['GET',    '/mod/log',                       'route_mod_log'],
];

function request_path(): string
{
    if (isset($_GET['r'])) {
        return '/' . trim((string) $_GET['r'], '/');
    }
    if (!empty($_SERVER['PATH_INFO'])) {
        return '/' . trim($_SERVER['PATH_INFO'], '/');
    }
    $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
    if ($base !== '' && str_starts_with($uri, $base)) {
        $uri = substr($uri, strlen($base));
    }
    $uri = preg_replace('~^/index\.php~', '', $uri);
    return '/' . trim($uri, '/');
}

function send_cors(): void
{
    // Front i API stoją na tej samej domenie; CORS tylko dla serwera deweloperskiego.
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    $allowed = array_filter(array_map('trim', explode(',', env('CORS_ORIGINS', ''))));
    if ($origin !== '' && in_array($origin, $allowed, true)) {
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, X-Gielda');
        header('Vary: Origin');
    }
}

send_cors();
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Ochrona przed CSRF: zapis tylko z nagłówkiem, którego obca strona nie doda bez CORS.
if ($method !== 'GET' && ($_SERVER['HTTP_X_GIELDA'] ?? '') !== '1') {
    json_error(403, 'Brak nagłówka X-Gielda.');
}

$path = request_path();
$methodMatched = false;

try {
    foreach (ROUTES as [$m, $pattern, $handler]) {
        if (!preg_match('~^' . $pattern . '$~', $path, $params)) {
            continue;
        }
        if ($m !== $method) {
            $methodMatched = true;
            continue;
        }
        array_shift($params);
        $handler(...$params);
        exit;
    }
    json_error($methodMatched ? 405 : 404, $methodMatched ? 'Niedozwolona metoda.' : 'Nie ma takiego adresu API.');
} catch (ApiError $e) {
    json_error($e->status, $e->getMessage(), $e->fields);
} catch (Throwable $e) {
    error_log('[gielda] ' . $e::class . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    if (db()->inTransaction()) {
        db()->rollBack();
    }
    json_error(500, 'Coś poszło nie tak po naszej stronie. Spróbuj ponownie.');
}
