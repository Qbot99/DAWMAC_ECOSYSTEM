<?php
/**
 * Wspólny start API giełdy: konfiguracja z .env, baza, sesja, odpowiedzi JSON.
 *
 * Celowo bez Composera — na dhostingu wystarczy wgrać katalog api/ i plik .env
 * piętro wyżej (poza public_html), bez instalowania zależności.
 */

declare(strict_types=1);

const GIELDA_ROOT = __DIR__ . '/../..';

/**
 * Wczytuje KEY=VALUE z pliku .env. Zmienne środowiskowe serwera mają
 * pierwszeństwo, więc na testach można nadpisać wszystko bez ruszania pliku.
 */
function gielda_load_env(): void
{
    $candidates = array_filter([
        getenv('GIELDA_ENV_FILE') ?: null,
        GIELDA_ROOT . '/.env',
        ($_SERVER['DOCUMENT_ROOT'] ?? '') !== '' ? $_SERVER['DOCUMENT_ROOT'] . '/../.env' : null,
    ]);

    foreach ($candidates as $file) {
        if (!is_file($file)) {
            continue;
        }
        foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
                continue;
            }
            [$key, $value] = array_map('trim', explode('=', $line, 2));
            $value = trim($value, "\"'");
            if (getenv($key) === false) {
                $_ENV[$key] = $value;
            }
        }
        return;
    }
}

function env(string $key, ?string $default = null): ?string
{
    $v = getenv($key);
    if ($v !== false) {
        return $v;
    }
    return $_ENV[$key] ?? $default;
}

/** Wersja regulaminu, którą akceptuje rejestrujący się użytkownik. */
function terms_version(): string
{
    return env('TERMS_VERSION', '2026-10-01');
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $pdo = gielda_connect(env('GIELDA_DB_NAME', ''));
    }
    return $pdo;
}

/**
 * Słownik aut (car_brand, car_model) z bazy galerii. Gdy CARS_DB_NAME
 * nie jest ustawione, szukamy tabel w bazie giełdy.
 */
function cars_db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $name = env('CARS_DB_NAME', '');
        $pdo = ($name === '' || $name === env('GIELDA_DB_NAME')) ? db() : gielda_connect($name);
    }
    return $pdo;
}

function gielda_connect(string $dbname): PDO
{
    $host = env('DB_SERVER', 'localhost');
    $dsn = "mysql:host={$host};dbname={$dbname};charset=utf8mb4";
    try {
        $pdo = new PDO($dsn, env('DB_USER', ''), env('DB_PASSWORD', ''), [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
        // NOW() w bazie i date() w PHP mają pokazywać ten sam czas (Europe/Warsaw).
        $pdo->exec("SET time_zone = '" . date('P') . "'");
        return $pdo;
    } catch (PDOException $e) {
        error_log('[gielda] DB: ' . $e->getMessage());
        json_error(500, 'Baza danych jest chwilowo niedostępna.');
    }
}

// ---------------------------------------------------------------- HTTP

/** Błąd, który handler może rzucić — router zamienia go na odpowiedź JSON. */
final class ApiError extends RuntimeException
{
    public function __construct(public int $status, string $message, public array $fields = [])
    {
        parent::__construct($message);
    }
}

function fail(int $status, string $message, array $fields = []): never
{
    throw new ApiError($status, $message, $fields);
}

function json_out(mixed $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function json_error(int $status, string $message, array $fields = []): never
{
    $body = ['error' => $message];
    if ($fields) {
        $body['fields'] = $fields;
    }
    json_out($body, $status);
}

/** Dane z żądania: JSON albo zwykły formularz (multipart przy zdjęciach). */
function input(): array
{
    static $data = null;
    if ($data !== null) {
        return $data;
    }
    $type = $_SERVER['CONTENT_TYPE'] ?? '';
    if (str_starts_with($type, 'application/json')) {
        $raw = file_get_contents('php://input');
        $data = json_decode($raw ?: '[]', true);
        if (!is_array($data)) {
            fail(400, 'Niepoprawny JSON.');
        }
    } else {
        $data = $_POST;
    }
    return $data;
}

function client_ip(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/**
 * Prosty limiter w bazie: najwyżej $max zdarzeń na $seconds dla danego klucza.
 */
function rate_limit(string $action, string $key, int $max, int $seconds): void
{
    if (env('RATE_LIMIT_OFF') === '1') {
        return;
    }
    $bucket = substr($action . ':' . $key, 0, 120);
    $pdo = db();
    $pdo->prepare('DELETE FROM g_rate_limit WHERE bucket = ? AND window_end < NOW()')->execute([$bucket]);
    $pdo->prepare(
        'INSERT INTO g_rate_limit (bucket, hits, window_end) VALUES (?, 1, NOW() + INTERVAL ? SECOND)
         ON DUPLICATE KEY UPDATE hits = hits + 1'
    )->execute([$bucket, $seconds]);
    $hits = (int) $pdo->query('SELECT hits FROM g_rate_limit WHERE bucket = ' . $pdo->quote($bucket))->fetchColumn();
    if ($hits > $max) {
        fail(429, 'Za dużo prób. Spróbuj ponownie za chwilę.');
    }
}

function app_url(string $path = ''): string
{
    return rtrim(env('APP_URL', 'http://localhost:5173'), '/') . $path;
}

// ---------------------------------------------------------------- start

gielda_load_env();

date_default_timezone_set('Europe/Warsaw');
