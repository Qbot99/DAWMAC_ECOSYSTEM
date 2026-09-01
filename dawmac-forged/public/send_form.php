<?php
// Formularz kontaktowy Dawmac Forged -> mail na skrzynkę firmową.
// Wgrać do public_html/ obok index.html (frontend POST-uje na /send_form.php).

header('Content-Type: application/json; charset=utf-8');

const RECIPIENT = 'dawmacpolska@gmail.com';
const FROM_DOMAIN = 'forged.dawmacpolska.pl';
const RATE_LIMIT_SECONDS = 30;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) {
    $data = $_POST; // fallback dla zwykłego form-encoded
}

// Honeypot: pole "website" jest ukryte w CSS — wypełniają je tylko boty.
// Odpowiadamy 200, żeby bot myślał, że się udało.
if (!empty($data['website'])) {
    echo json_encode(['ok' => true]);
    exit;
}

$field = function (string $key, int $max) use ($data): string {
    $v = trim((string)($data[$key] ?? ''));
    $v = str_replace(["\r", "\n"], ' ', $v); // ochrona przed header injection
    return mb_substr($v, 0, $max);
};

$name    = $field('name', 100);
$contact = $field('contact', 150);
$car     = $field('car', 150);
$wheel   = $field('wheel', 50);
$lang    = in_array($data['lang'] ?? '', ['pl', 'en', 'de'], true) ? $data['lang'] : 'pl';
$message = mb_substr(trim((string)($data['message'] ?? '')), 0, 3000);

if ($name === '' || $contact === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required fields']);
    exit;
}

// Prosty rate-limit per IP (plik w katalogu tymczasowym)
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$stamp = sys_get_temp_dir() . '/forged_form_' . md5($ip);
if (file_exists($stamp) && (time() - (int)filemtime($stamp)) < RATE_LIMIT_SECONDS) {
    http_response_code(429);
    echo json_encode(['error' => 'Too many requests']);
    exit;
}
@touch($stamp);

$subject = '[Forged] Zapytanie' . ($wheel !== '' ? " o felgę $wheel" : '') . " od $name";

$body = "Nowe zapytanie ze strony forged.dawmacpolska.pl\n"
      . str_repeat('-', 46) . "\n"
      . "Imię:        $name\n"
      . "Kontakt:     $contact\n"
      . ($car !== '' ? "Samochód:    $car\n" : '')
      . ($wheel !== '' ? "Felga:       $wheel\n" : '')
      . "Język:       " . strtoupper($lang) . "\n"
      . "IP:          $ip\n"
      . "Data:        " . date('Y-m-d H:i:s') . "\n"
      . str_repeat('-', 46) . "\n\n"
      . ($message !== '' ? $message : '(brak treści wiadomości)') . "\n";

$headers = 'From: Dawmac Forged <no-reply@' . FROM_DOMAIN . ">\r\n"
         . "X-Mailer: PHP/" . PHP_VERSION . "\r\n"
         . "Content-Type: text/plain; charset=UTF-8\r\n";
// Reply-To tylko gdy kontakt wygląda na e-mail — wtedy „Odpowiedz" działa od ręki
if (filter_var($contact, FILTER_VALIDATE_EMAIL)) {
    $headers .= "Reply-To: $contact\r\n";
}

$sent = mail(RECIPIENT, '=?UTF-8?B?' . base64_encode($subject) . '?=', $body, $headers);

if (!$sent) {
    http_response_code(500);
    echo json_encode(['error' => 'Mail failed']);
    exit;
}

echo json_encode(['ok' => true]);
