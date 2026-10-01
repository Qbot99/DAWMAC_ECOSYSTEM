<?php
/**
 * Konta: rejestracja, potwierdzenie e-maila, logowanie, reset hasła,
 * ustawienia konta i jego usunięcie (RODO art. 17, wymóg App Store/Google Play).
 */

declare(strict_types=1);

function route_register(): void
{
    rate_limit('register', client_ip(), 10, 3600);

    $f = new Fields(input());
    $email = $f->email('email');
    $password = (string) (input()['password'] ?? '');
    $name = $f->str('display_name', 60, true, 2, 'Nazwa wyświetlana');
    $city = $f->str('city', 80, false, 0, 'Miasto');
    $phone = $f->str('phone', 30, false, 0, 'Telefon');
    $sellerType = $f->enum('seller_type', ['private', 'company'], false) ?? 'private';
    $companyName = $f->str('company_name', 160, $sellerType === 'company', 2, 'Nazwa firmy');
    $companyNip = $f->str('company_nip', 20, $sellerType === 'company', 10, 'NIP');

    if ($problem = password_problem($password)) {
        $f->error('password', $problem);
    }
    if ($phone !== null && !preg_match('/^\+?[0-9 ()-]{7,20}$/', $phone)) {
        $f->error('phone', 'Niepoprawny numer telefonu.');
    }
    if (!$f->bool('accept_terms')) {
        $f->error('accept_terms', 'Aby założyć konto, zaakceptuj regulamin i politykę prywatności.');
    }
    if (!$f->bool('adult')) {
        $f->error('adult', 'Konto może założyć osoba pełnoletnia.');
    }
    $f->check();

    $exists = db()->prepare('SELECT id, status FROM g_users WHERE email = ?');
    $exists->execute([$email]);
    if ($exists->fetch()) {
        fail(409, 'Konto z tym adresem e-mail już istnieje. Zaloguj się albo zresetuj hasło.', ['email' => 'Adres jest już zajęty.']);
    }

    $marketing = $f->bool('marketing_consent');
    db()->prepare(
        'INSERT INTO g_users (email, password_hash, display_name, phone, city, seller_type, company_name, company_nip,
                              terms_version, terms_accepted_at, marketing_consent, marketing_consent_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, IF(?, NOW(), NULL))'
    )->execute([
        $email, password_hash($password, PASSWORD_DEFAULT), $name, $phone, $city, $sellerType,
        $sellerType === 'company' ? $companyName : null, $sellerType === 'company' ? $companyNip : null,
        terms_version(), (int) $marketing, (int) $marketing,
    ]);
    $id = (int) db()->lastInsertId();

    send_verification($id, $email);

    $user = db()->query('SELECT * FROM g_users WHERE id = ' . $id)->fetch();
    login_user($user);
    json_out(['user' => user_private_view($user)], 201);
}

function send_verification(int $userId, string $email): void
{
    $token = create_token($userId, 'verify', 72);
    send_mail(
        $email,
        'Potwierdź adres e-mail — DAWMAC Giełda',
        "Cześć!\n\nPotwierdź swój adres, żeby dodawać ogłoszenia i pisać wiadomości:\n\n"
        . app_url('/potwierdz?token=' . $token)
        . "\n\nLink jest ważny 72 godziny. Jeśli to nie Ty zakładałeś konto, zignoruj tę wiadomość."
    );
}

function route_resend_verification(): void
{
    $user = require_user(true);
    if ($user['email_verified_at']) {
        json_out(['ok' => true]);
    }
    rate_limit('verify-mail', (string) $user['id'], 3, 3600);
    send_verification((int) $user['id'], $user['email']);
    json_out(['ok' => true]);
}

function route_verify(): void
{
    $token = (string) (input()['token'] ?? '');
    $uid = $token !== '' ? consume_token($token, 'verify') : null;
    if (!$uid) {
        fail(400, 'Link jest nieważny albo już został użyty.');
    }
    db()->prepare('UPDATE g_users SET email_verified_at = COALESCE(email_verified_at, NOW()) WHERE id = ?')->execute([$uid]);
    $user = db()->query('SELECT * FROM g_users WHERE id = ' . $uid)->fetch();
    login_user($user);
    json_out(['user' => user_private_view($user)]);
}

function route_login(): void
{
    rate_limit('login', client_ip(), 20, 900);

    $in = input();
    $email = mb_strtolower(trim((string) ($in['email'] ?? '')));
    $password = (string) ($in['password'] ?? '');
    rate_limit('login-email', $email, 10, 900);

    $stmt = db()->prepare("SELECT * FROM g_users WHERE email = ? AND status <> 'deleted'");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        fail(401, 'Zły e-mail lub hasło.');
    }
    if ($user['status'] === 'banned') {
        fail(403, 'Konto jest zablokowane. Powód: ' . ($user['ban_reason'] ?: 'brak') . '. Odwołanie: ' . env('CONTACT_EMAIL', ''));
    }
    if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
        db()->prepare('UPDATE g_users SET password_hash = ? WHERE id = ?')
            ->execute([password_hash($password, PASSWORD_DEFAULT), $user['id']]);
    }
    login_user($user);
    json_out(['user' => user_private_view($user)]);
}

function route_logout(): void
{
    logout_user();
    json_out(['ok' => true]);
}

function route_me(): void
{
    $user = current_user();
    if (!$user) {
        json_out(['user' => null]);
    }
    $unread = db()->prepare('SELECT COUNT(*) FROM g_notifications WHERE user_id = ? AND read_at IS NULL');
    $unread->execute([$user['id']]);
    $msgs = db()->prepare(
        'SELECT COUNT(*) FROM g_conversations
         WHERE (owner_id = ? AND owner_read_id < last_message_id)
            OR (other_id = ? AND other_read_id < last_message_id)'
    );
    $msgs->execute([$user['id'], $user['id']]);
    json_out([
        'user'                 => user_private_view($user),
        'unread_notifications' => (int) $unread->fetchColumn(),
        'unread_conversations' => (int) $msgs->fetchColumn(),
    ]);
}

function route_forgot(): void
{
    rate_limit('forgot', client_ip(), 5, 3600);
    $email = mb_strtolower(trim((string) (input()['email'] ?? '')));
    $stmt = db()->prepare("SELECT id, email FROM g_users WHERE email = ? AND status = 'active'");
    $stmt->execute([$email]);
    if ($user = $stmt->fetch()) {
        $token = create_token((int) $user['id'], 'reset', 2);
        send_mail(
            $user['email'],
            'Reset hasła — DAWMAC Giełda',
            "Ustaw nowe hasło pod tym linkiem (ważny 2 godziny):\n\n" . app_url('/nowe-haslo?token=' . $token)
            . "\n\nJeśli to nie Ty prosiłeś o reset, zignoruj tę wiadomość."
        );
    }
    // Ta sama odpowiedź niezależnie od tego, czy konto istnieje.
    json_out(['ok' => true]);
}

function route_reset(): void
{
    $in = input();
    $password = (string) ($in['password'] ?? '');
    if ($problem = password_problem($password)) {
        fail(422, $problem, ['password' => $problem]);
    }
    $uid = consume_token((string) ($in['token'] ?? ''), 'reset');
    if (!$uid) {
        fail(400, 'Link jest nieważny albo już został użyty.');
    }
    // Reset przez e-mail jednocześnie potwierdza adres.
    db()->prepare('UPDATE g_users SET password_hash = ?, email_verified_at = COALESCE(email_verified_at, NOW()) WHERE id = ?')
        ->execute([password_hash($password, PASSWORD_DEFAULT), $uid]);
    $user = db()->query('SELECT * FROM g_users WHERE id = ' . $uid)->fetch();
    login_user($user);
    json_out(['user' => user_private_view($user)]);
}

function route_update_me(): void
{
    $user = require_user(true);
    $in = input();
    $f = new Fields($in);
    $set = [];

    if ($f->has('display_name')) {
        $set['display_name'] = $f->str('display_name', 60, true, 2, 'Nazwa wyświetlana');
    }
    if ($f->has('city')) {
        $set['city'] = $f->str('city', 80, false, 0, 'Miasto');
    }
    if ($f->has('phone')) {
        $phone = $f->str('phone', 30, false, 0, 'Telefon');
        if ($phone !== null && !preg_match('/^\+?[0-9 ()-]{7,20}$/', $phone)) {
            $f->error('phone', 'Niepoprawny numer telefonu.');
        }
        $set['phone'] = $phone;
    }
    if ($f->has('seller_type')) {
        $type = $f->enum('seller_type', ['private', 'company'], true, 'Sprzedaję jako');
        $set['seller_type'] = $type;
        $set['company_name'] = $type === 'company' ? $f->str('company_name', 160, true, 2, 'Nazwa firmy') : null;
        $set['company_nip'] = $type === 'company' ? $f->str('company_nip', 20, true, 10, 'NIP') : null;
    }
    foreach (['notify_matches', 'notify_messages'] as $k) {
        if ($f->has($k)) {
            $set[$k] = (int) $f->bool($k);
        }
    }
    if ($f->has('marketing_consent')) {
        $m = $f->bool('marketing_consent');
        if ($m !== (bool) $user['marketing_consent']) {
            $set['marketing_consent'] = (int) $m;
            $set['marketing_consent_at'] = $m ? date('Y-m-d H:i:s') : null;
        }
    }
    if ($f->bool('accept_terms')) {
        $set['terms_version'] = terms_version();
        $set['terms_accepted_at'] = date('Y-m-d H:i:s');
    }
    if ($f->has('new_password')) {
        if (!password_verify((string) ($in['current_password'] ?? ''), $user['password_hash'])) {
            $f->error('current_password', 'Obecne hasło jest niepoprawne.');
        } elseif ($problem = password_problem((string) $in['new_password'])) {
            $f->error('new_password', $problem);
        } else {
            $set['password_hash'] = password_hash((string) $in['new_password'], PASSWORD_DEFAULT);
        }
    }
    $f->check();

    if ($set) {
        $cols = implode(', ', array_map(fn ($k) => "$k = ?", array_keys($set)));
        db()->prepare("UPDATE g_users SET $cols WHERE id = ?")->execute([...array_values($set), $user['id']]);
    }
    $fresh = db()->query('SELECT * FROM g_users WHERE id = ' . (int) $user['id'])->fetch();
    json_out(['user' => user_private_view($fresh)]);
}

/**
 * Usunięcie konta. Ogłoszenia, zdjęcia i rozmowy znikają; wiersz konta
 * zostaje zanonimizowany (status deleted), żeby zgłoszenia i log moderacji
 * nie traciły spójności.
 */
function route_delete_me(): void
{
    $user = require_user(true);
    if (!password_verify((string) (input()['password'] ?? ''), $user['password_hash'])) {
        fail(422, 'Podaj obecne hasło, żeby usunąć konto.', ['password' => 'Niepoprawne hasło.']);
    }
    $uid = (int) $user['id'];

    $ids = db()->prepare('SELECT id FROM g_listings WHERE user_id = ?');
    $ids->execute([$uid]);
    foreach ($ids->fetchAll(PDO::FETCH_COLUMN) as $lid) {
        delete_listing_files((int) $lid);
    }

    $pdo = db();
    $pdo->beginTransaction();
    $pdo->prepare('DELETE FROM g_listings WHERE user_id = ?')->execute([$uid]);
    $pdo->prepare('DELETE FROM g_conversations WHERE owner_id = ? OR other_id = ?')->execute([$uid, $uid]);
    $pdo->prepare('DELETE FROM g_notifications WHERE user_id = ?')->execute([$uid]);
    $pdo->prepare('DELETE FROM g_favorites WHERE user_id = ?')->execute([$uid]);
    $pdo->prepare('DELETE FROM g_tokens WHERE user_id = ?')->execute([$uid]);
    $pdo->prepare(
        "UPDATE g_users SET status = 'deleted', email = CONCAT('usuniete-', id, '@invalid'), password_hash = '',
            display_name = 'Konto usunięte', phone = NULL, city = NULL, company_name = NULL, company_nip = NULL,
            marketing_consent = 0, role = 'user'
         WHERE id = ?"
    )->execute([$uid]);
    $pdo->commit();

    logout_user();
    json_out(['ok' => true]);
}

/** Eksport danych konta (RODO art. 15/20). */
function route_export_me(): void
{
    $user = require_user(true);
    $uid = (int) $user['id'];
    $q = function (string $sql) use ($uid) {
        $s = db()->prepare($sql);
        $s->execute([$uid]);
        return $s->fetchAll();
    };
    $profile = user_private_view($user);
    $profile['terms_accepted_at'] = $user['terms_accepted_at'];
    $profile['marketing_consent_at'] = $user['marketing_consent_at'];
    header('Content-Disposition: attachment; filename="dawmac-gielda-moje-dane.json"');
    json_out([
        'konto'         => $profile,
        'ogloszenia'    => $q('SELECT * FROM g_listings WHERE user_id = ?'),
        'wiadomosci'    => $q('SELECT m.conversation_id, m.body, m.created_at FROM g_messages m WHERE m.sender_id = ?'),
        'obserwowane'   => $q('SELECT listing_id, created_at FROM g_favorites WHERE user_id = ?'),
        'powiadomienia' => $q('SELECT type, title, body, created_at FROM g_notifications WHERE user_id = ?'),
    ]);
}
