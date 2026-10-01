<?php
/**
 * Wspólny początek endpointów bazy dopasowań.
 *
 * Tabele fit_* leżą w tej samej bazie co galeria, więc korzystamy z jej
 * połączenia (i nagłówków CORS). Wszystkie endpointy tutaj tylko czytają.
 */

require __DIR__ . '/../gallery/db.php';
require_once __DIR__ . '/lib/fitment.php';

if (!function_exists('dawmac_fit_respond')) {

    function dawmac_fit_respond(array $data, int $status = 200): void
    {
        global $conn;

        http_response_code($status);
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($conn instanceof mysqli) {
            $conn->close();
        }
        exit();
    }
}
