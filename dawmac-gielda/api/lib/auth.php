<?php
/**
 * Sesja i uprawnienia.
 *
 * Logowanie trzyma id użytkownika w sesji PHP (ciasteczko HttpOnly,
 * SameSite=Lax). Każde żądanie zmieniające dane musi mieć nagłówek
 * X-Gielda: 1 — przeglądarka nie wyśle go z obcej strony bez zgody CORS,
 * więc to wystarcza jako ochrona przed CSRF.
 */

declare(strict_types=1);

function session_begin(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    session_name('GIELDA_SID');
    session_set_cookie_params([
        'lifetime' => 60 * 60 * 24 * 30,
        'path'     => '/',
        'secure'   => env('COOKIE_SECURE', '1') === '1',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    ini_set('session.gc_maxlifetime', (string) (60 * 60 * 24 * 30));
    session_start();
}

function current_user(): ?array
{
    static $cache = false;
    if ($cache !== false) {
        return $cache;
    }
    session_begin();
    $id = $_SESSION['uid'] ?? null;
    if (!$id) {
        return $cache = null;
    }
    $stmt = db()->prepare('SELECT * FROM g_users WHERE id = ?');
    $stmt->execute([$id]);
    $user = $stmt->fetch() ?: null;
    if (!$user || $user['status'] === 'deleted') {
        unset($_SESSION['uid']);
        return $cache = null;
    }
    return $cache = $user;
}

/** Zalogowany i niezablokowany użytkownik — inaczej 401/403. */
function require_user(bool $allowUnverified = false): array
{
    $user = current_user();
    if (!$user) {
        fail(401, 'Zaloguj się, żeby to zrobić.');
    }
    if ($user['status'] === 'banned') {
        fail(403, 'Konto jest zablokowane. Powód: ' . ($user['ban_reason'] ?: 'brak'));
    }
    if (!$allowUnverified && !$user['email_verified_at']) {
        fail(403, 'Najpierw potwierdź adres e-mail (link w wiadomości po rejestracji).');
    }
    return $user;
}

function is_staff(?array $user): bool
{
    return $user && in_array($user['role'], ['moderator', 'admin'], true);
}

function require_staff(bool $adminOnly = false): array
{
    $user = require_user();
    $ok = $adminOnly ? $user['role'] === 'admin' : is_staff($user);
    if (!$ok) {
        fail(403, 'Brak uprawnień.');
    }
    return $user;
}

function login_user(array $user): void
{
    session_begin();
    session_regenerate_id(true);
    $_SESSION['uid'] = (int) $user['id'];
    db()->prepare('UPDATE g_users SET last_login_at = NOW() WHERE id = ?')->execute([$user['id']]);
}

function logout_user(): void
{
    session_begin();
    $_SESSION = [];
    session_destroy();
}

/** Tworzy jednorazowy token i zwraca jego jawną postać (do linku w e-mailu). */
function create_token(int $userId, string $purpose, int $ttlHours): string
{
    $token = bin2hex(random_bytes(24));
    db()->prepare('DELETE FROM g_tokens WHERE user_id = ? AND purpose = ? AND used_at IS NULL')
        ->execute([$userId, $purpose]);
    db()->prepare(
        'INSERT INTO g_tokens (user_id, purpose, token_hash, expires_at) VALUES (?, ?, ?, NOW() + INTERVAL ? HOUR)'
    )->execute([$userId, $purpose, hash('sha256', $token), $ttlHours]);
    return $token;
}

/** Zużywa token; zwraca id użytkownika albo null, gdy zły lub przeterminowany. */
function consume_token(string $token, string $purpose): ?int
{
    $stmt = db()->prepare(
        'SELECT id, user_id FROM g_tokens
         WHERE token_hash = ? AND purpose = ? AND used_at IS NULL AND expires_at > NOW()'
    );
    $stmt->execute([hash('sha256', $token), $purpose]);
    $row = $stmt->fetch();
    if (!$row) {
        return null;
    }
    db()->prepare('UPDATE g_tokens SET used_at = NOW() WHERE id = ?')->execute([$row['id']]);
    return (int) $row['user_id'];
}

/** Dane konta, które wolno oddać samemu właścicielowi. */
function user_private_view(array $u): array
{
    return [
        'id'                => (int) $u['id'],
        'email'             => $u['email'],
        'display_name'      => $u['display_name'],
        'phone'             => $u['phone'],
        'city'              => $u['city'],
        'seller_type'       => $u['seller_type'],
        'company_name'      => $u['company_name'],
        'company_nip'       => $u['company_nip'],
        'role'              => $u['role'],
        'status'            => $u['status'],
        'email_verified'    => (bool) $u['email_verified_at'],
        'marketing_consent' => (bool) $u['marketing_consent'],
        'notify_matches'    => (bool) $u['notify_matches'],
        'notify_messages'   => (bool) $u['notify_messages'],
        'terms_version'     => $u['terms_version'],
        'terms_current'     => $u['terms_version'] === terms_version(),
        'created_at'        => $u['created_at'],
    ];
}

/** Publiczny profil — bez e-maila i telefonu. */
function user_public_view(array $u): array
{
    return [
        'id'           => (int) $u['id'],
        'display_name' => $u['display_name'],
        'city'         => $u['city'],
        'seller_type'  => $u['seller_type'],
        'company_name' => $u['seller_type'] === 'company' ? $u['company_name'] : null,
        'since'        => substr((string) $u['created_at'], 0, 10),
    ];
}
