<?php
/**
 * Wymaganie zalogowania dla endpointów zmieniających dane.
 *
 * Panel loguje przez api/login.php, które zapisuje $_SESSION['user'].
 * Endpointy galerii siedzą na tej samej domenie, więc widzą tę samą sesję.
 *
 * UWAGA: istniejące endpointy zapisu (add_wheel, edit_project,
 * delete_project, delete_image) NIE mają tego sprawdzenia i są dostępne
 * dla każdego, kto zna adres — przy Access-Control-Allow-Origin: * także
 * z dowolnej strony. Dołożenie im tego guarda to osobna zmiana, którą
 * trzeba wykonać razem z testem logowania, żeby nie odciąć panelu.
 */

if (!function_exists('dawmac_require_login')) {

    function dawmac_require_login(): void
    {
        // Skrypty konsolowe (migracje, cron) nie mają sesji i nie potrzebują jej.
        if (PHP_SAPI === 'cli') {
            return;
        }

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (empty($_SESSION['user'])) {
            http_response_code(401);
            echo json_encode(
                ['error' => 'Wymagane zalogowanie.'],
                JSON_UNESCAPED_UNICODE
            );
            exit();
        }
    }
}
