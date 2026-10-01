<?php
/**
 * Warstwa pracowników: zgłoszenia, ogłoszenia, użytkownicy, log decyzji.
 *
 * Każda decyzja trafia do g_mod_log, a użytkownik, którego dotyczy, dostaje
 * uzasadnienie (DSA art. 17): co, dlaczego, że decyzję podjął człowiek
 * i jak się odwołać.
 */

declare(strict_types=1);

function mod_log(array $staff, string $action, string $type, int $id, ?string $reason): void
{
    db()->prepare('INSERT INTO g_mod_log (staff_id, action, target_type, target_id, reason) VALUES (?, ?, ?, ?, ?)')
        ->execute([$staff['id'], $action, $type, $id, $reason]);
}

function appeal_info(): string
{
    return "\n\nDecyzję podjął pracownik DAWMAC (bez narzędzi automatycznych).\n"
        . 'Jeśli się z nią nie zgadzasz, napisz w ciągu 14 dni na ' . env('CONTACT_EMAIL', 'gielda@dawmac.pl')
        . ' — rozpatrzy ją inna osoba. Możesz też skorzystać z pozasądowego rozstrzygania sporów (art. 21 DSA) lub drogi sądowej.';
}

function mod_reason(): string
{
    $reason = trim((string) (input()['reason'] ?? ''));
    if (mb_strlen($reason) < 10) {
        fail(422, 'Napisz uzasadnienie (co najmniej 10 znaków) — dostanie je użytkownik.', ['reason' => 'Uzasadnienie jest wymagane.']);
    }
    return mb_substr($reason, 0, 2000);
}

function remove_listing(array $staff, array $l, string $reason): void
{
    db()->prepare("UPDATE g_listings SET status = 'removed', removal_reason = ?, removed_by = ?, removed_at = NOW() WHERE id = ?")
        ->execute([$reason, $staff['id'], $l['id']]);
    mod_log($staff, 'listing_remove', 'listing', (int) $l['id'], $reason);
    notify(
        (int) $l['user_id'],
        'moderation',
        'Usunęliśmy Twoje ogłoszenie „' . $l['title'] . '”',
        'Powód: ' . $reason . appeal_info(),
        '/moje',
        ['listing_id' => (int) $l['id']],
        true
    );
}

function ban_user(array $staff, array $u, string $reason): void
{
    if (in_array($u['role'], ['moderator', 'admin'], true)) {
        fail(422, 'Najpierw odbierz rolę pracownika.');
    }
    db()->prepare("UPDATE g_users SET status = 'banned', ban_reason = ? WHERE id = ?")->execute([$reason, $u['id']]);
    mod_log($staff, 'user_ban', 'user', (int) $u['id'], $reason);
    notify((int) $u['id'], 'moderation', 'Twoje konto na DAWMAC Giełdzie zostało zablokowane', 'Powód: ' . $reason . appeal_info(), null, null, true);
}

function route_mod_stats(): void
{
    require_staff();
    $one = fn (string $sql) => (int) db()->query($sql)->fetchColumn();
    json_out([
        'open_reports'    => $one("SELECT COUNT(*) FROM g_reports WHERE status = 'open'"),
        'active_listings' => $one("SELECT COUNT(*) FROM g_listings WHERE status = 'active'"),
        'sell'            => $one("SELECT COUNT(*) FROM g_listings WHERE status = 'active' AND type = 'sell'"),
        'buy'             => $one("SELECT COUNT(*) FROM g_listings WHERE status = 'active' AND type = 'buy'"),
        'users'           => $one("SELECT COUNT(*) FROM g_users WHERE status = 'active'"),
        'new_listings_7d' => $one('SELECT COUNT(*) FROM g_listings WHERE created_at > NOW() - INTERVAL 7 DAY'),
        'new_users_7d'    => $one('SELECT COUNT(*) FROM g_users WHERE created_at > NOW() - INTERVAL 7 DAY'),
    ]);
}

function route_mod_reports(): void
{
    require_staff();
    $status = in_array($_GET['status'] ?? 'open', ['open', 'resolved', 'dismissed'], true) ? ($_GET['status'] ?? 'open') : 'open';
    $stmt = db()->prepare(
        'SELECT r.*, l.title AS listing_title, l.status AS listing_status,
                ru.display_name AS reported_name, ru.email AS reported_email, ru.status AS reported_status,
                rep.display_name AS reporter_account, rep.email AS reporter_account_email,
                h.display_name AS handled_by_name
         FROM g_reports r
         LEFT JOIN g_listings l ON l.id = r.listing_id
         LEFT JOIN g_users ru ON ru.id = r.reported_user_id
         LEFT JOIN g_users rep ON rep.id = r.reporter_id
         LEFT JOIN g_users h ON h.id = r.handled_by
         WHERE r.status = ? ORDER BY r.created_at ' . ($status === 'open' ? 'ASC' : 'DESC') . ' LIMIT 200'
    );
    $stmt->execute([$status]);
    $items = array_map(fn ($r) => [
        'id'          => (int) $r['id'],
        'reason'      => $r['reason'],
        'reason_label'=> REPORT_REASONS[$r['reason']] ?? $r['reason'],
        'details'     => $r['details'],
        'status'      => $r['status'],
        'created_at'  => $r['created_at'],
        'listing'     => $r['listing_id'] ? ['id' => (int) $r['listing_id'], 'title' => $r['listing_title'], 'status' => $r['listing_status']] : null,
        'reported_user' => $r['reported_user_id'] ? [
            'id' => (int) $r['reported_user_id'], 'display_name' => $r['reported_name'],
            'email' => $r['reported_email'], 'status' => $r['reported_status'],
        ] : null,
        'reporter'    => $r['reporter_id']
            ? ['account' => true, 'name' => $r['reporter_account'], 'email' => $r['reporter_account_email']]
            : ['account' => false, 'name' => $r['reporter_name'], 'email' => $r['reporter_email']],
        'resolution_note' => $r['resolution_note'],
        'handled_by'  => $r['handled_by_name'],
        'handled_at'  => $r['handled_at'],
    ], $stmt->fetchAll());
    json_out(['items' => $items]);
}

/**
 * Rozpatrzenie zgłoszenia: dismiss (bez naruszenia), remove_listing, ban_user.
 */
function route_mod_report_resolve(string $id): void
{
    $staff = require_staff();
    $stmt = db()->prepare('SELECT r.*, rep.email AS reporter_account_email FROM g_reports r LEFT JOIN g_users rep ON rep.id = r.reporter_id WHERE r.id = ?');
    $stmt->execute([(int) $id]);
    $r = $stmt->fetch();
    if (!$r) {
        fail(404, 'Nie ma takiego zgłoszenia.');
    }
    if ($r['status'] !== 'open') {
        fail(409, 'To zgłoszenie jest już rozpatrzone.');
    }
    $action = input()['action'] ?? '';
    $reason = mod_reason();

    switch ($action) {
        case 'dismiss':
            $status = 'dismissed';
            $outcome = 'Nie stwierdziliśmy naruszenia regulaminu ani prawa. Treść pozostaje opublikowana.';
            break;
        case 'remove_listing':
            $l = $r['listing_id'] ? load_listing((int) $r['listing_id']) : null;
            if (!$l) {
                fail(422, 'Zgłoszenie nie dotyczy istniejącego ogłoszenia.');
            }
            if ($l['status'] !== 'removed') {
                remove_listing($staff, $l, $reason);
            }
            $status = 'resolved';
            $outcome = 'Ogłoszenie zostało usunięte.';
            break;
        case 'ban_user':
            $u = $r['reported_user_id'] ? db()->query('SELECT * FROM g_users WHERE id = ' . (int) $r['reported_user_id'])->fetch() : null;
            if (!$u) {
                fail(422, 'Zgłoszenie nie dotyczy istniejącego konta.');
            }
            if ($u['status'] === 'active') {
                ban_user($staff, $u, $reason);
            }
            $status = 'resolved';
            $outcome = 'Konto użytkownika zostało zablokowane.';
            break;
        default:
            fail(422, 'Nieznana decyzja.');
    }

    db()->prepare('UPDATE g_reports SET status = ?, resolution_note = ?, handled_by = ?, handled_at = NOW() WHERE id = ?')
        ->execute([$status, $reason, $staff['id'], $r['id']]);
    mod_log($staff, 'report_' . $action, 'report', (int) $r['id'], $reason);

    $to = $r['reporter_account_email'] ?: $r['reporter_email'];
    if ($to) {
        send_mail($to, 'Decyzja w sprawie zgłoszenia nr ' . $r['id'] . ' — DAWMAC Giełda', $outcome . appeal_info());
    }
    json_out(['ok' => true, 'status' => $status]);
}

function route_mod_listings(): void
{
    require_staff();
    $where = ['1=1'];
    $args = [];
    $status = $_GET['status'] ?? '';
    if (in_array($status, ['active', 'sold', 'closed', 'expired', 'removed'], true)) {
        $where[] = 'l.status = ?';
        $args[] = $status;
    }
    if (ctype_digit((string) ($_GET['user_id'] ?? ''))) {
        $where[] = 'l.user_id = ?';
        $args[] = (int) $_GET['user_id'];
    }
    $q = trim((string) ($_GET['q'] ?? ''));
    if ($q !== '') {
        if (ctype_digit($q)) {
            $where[] = 'l.id = ?';
            $args[] = (int) $q;
        } else {
            $where[] = '(l.title LIKE ? OR u.email LIKE ? OR u.display_name LIKE ?)';
            $like = '%' . addcslashes($q, '%_\\') . '%';
            array_push($args, $like, $like, $like);
        }
    }
    $stmt = db()->prepare(
        'SELECT l.*, u.display_name, u.email, u.seller_type, u.company_name, u.created_at AS user_since,
                (SELECT COUNT(*) FROM g_reports r WHERE r.listing_id = l.id) AS reports
         FROM g_listings l JOIN g_users u ON u.id = l.user_id
         WHERE ' . implode(' AND ', $where) . ' ORDER BY l.created_at DESC LIMIT 200'
    );
    $stmt->execute($args);
    $rows = $stmt->fetchAll();
    $images = listing_images(array_column($rows, 'id'));
    $staff = current_user();
    json_out(['items' => array_map(function ($r) use ($images, $staff) {
        $v = listing_view($r, array_slice($images[(int) $r['id']] ?? [], 0, 1), $staff);
        $v['seller']['email'] = $r['email'];
        $v['reports'] = (int) $r['reports'];
        $v['removal_reason'] = $r['removal_reason'];
        return $v;
    }, $rows)]);
}

function route_mod_listing_remove(string $id): void
{
    $staff = require_staff();
    $l = load_listing((int) $id);
    if (!$l) {
        fail(404, 'Nie ma takiego ogłoszenia.');
    }
    if ($l['status'] === 'removed') {
        fail(409, 'Ogłoszenie jest już usunięte.');
    }
    remove_listing($staff, $l, mod_reason());
    json_out(['ok' => true]);
}

function route_mod_listing_restore(string $id): void
{
    $staff = require_staff();
    $l = load_listing((int) $id);
    if (!$l || $l['status'] !== 'removed') {
        fail(404, 'To ogłoszenie nie jest usunięte.');
    }
    $reason = mod_reason();
    db()->prepare(
        "UPDATE g_listings SET status = 'active', removal_reason = NULL, removed_by = NULL, removed_at = NULL,
            expires_at = GREATEST(expires_at, NOW() + INTERVAL 7 DAY) WHERE id = ?"
    )->execute([$l['id']]);
    mod_log($staff, 'listing_restore', 'listing', (int) $l['id'], $reason);
    notify((int) $l['user_id'], 'moderation', 'Przywróciliśmy Twoje ogłoszenie „' . $l['title'] . '”', $reason, '/ogloszenie/' . $l['id'], null, true);
    json_out(['ok' => true]);
}

function route_mod_users(): void
{
    require_staff();
    $q = trim((string) ($_GET['q'] ?? ''));
    $where = ["status <> 'deleted'"];
    $args = [];
    if ($q !== '') {
        if (ctype_digit($q)) {
            $where[] = 'id = ?';
            $args[] = (int) $q;
        } else {
            $where[] = '(email LIKE ? OR display_name LIKE ? OR phone LIKE ?)';
            $like = '%' . addcslashes($q, '%_\\') . '%';
            array_push($args, $like, $like, $like);
        }
    }
    if (in_array($_GET['status'] ?? '', ['active', 'banned'], true)) {
        $where[] = 'status = ?';
        $args[] = $_GET['status'];
    }
    $stmt = db()->prepare(
        'SELECT u.*, (SELECT COUNT(*) FROM g_listings l WHERE l.user_id = u.id) AS listings,
                (SELECT COUNT(*) FROM g_reports r WHERE r.reported_user_id = u.id) AS reports
         FROM g_users u WHERE ' . implode(' AND ', $where) . ' ORDER BY u.created_at DESC LIMIT 200'
    );
    $stmt->execute($args);
    json_out(['items' => array_map(fn ($u) => [
        ...user_private_view($u),
        'ban_reason'    => $u['ban_reason'],
        'last_login_at' => $u['last_login_at'],
        'listings'      => (int) $u['listings'],
        'reports'       => (int) $u['reports'],
    ], $stmt->fetchAll())]);
}

function mod_target_user(string $id): array
{
    $stmt = db()->prepare("SELECT * FROM g_users WHERE id = ? AND status <> 'deleted'");
    $stmt->execute([(int) $id]);
    return $stmt->fetch() ?: fail(404, 'Nie ma takiego użytkownika.');
}

function route_mod_user_ban(string $id): void
{
    $staff = require_staff();
    $u = mod_target_user($id);
    if ($u['status'] === 'banned') {
        fail(409, 'Konto jest już zablokowane.');
    }
    ban_user($staff, $u, mod_reason());
    json_out(['ok' => true]);
}

function route_mod_user_unban(string $id): void
{
    $staff = require_staff();
    $u = mod_target_user($id);
    if ($u['status'] !== 'banned') {
        fail(409, 'Konto nie jest zablokowane.');
    }
    $reason = mod_reason();
    db()->prepare("UPDATE g_users SET status = 'active', ban_reason = NULL WHERE id = ?")->execute([$u['id']]);
    mod_log($staff, 'user_unban', 'user', (int) $u['id'], $reason);
    notify((int) $u['id'], 'moderation', 'Odblokowaliśmy Twoje konto na DAWMAC Giełdzie', $reason, null, null, true);
    json_out(['ok' => true]);
}

/** Nadawanie ról pracownikom — tylko administrator. */
function route_mod_user_role(string $id): void
{
    $admin = require_staff(true);
    $u = mod_target_user($id);
    $role = input()['role'] ?? '';
    if (!in_array($role, ['user', 'moderator', 'admin'], true)) {
        fail(422, 'Nieznana rola.');
    }
    if ((int) $u['id'] === (int) $admin['id']) {
        fail(422, 'Nie możesz zmienić własnej roli.');
    }
    db()->prepare('UPDATE g_users SET role = ? WHERE id = ?')->execute([$role, $u['id']]);
    mod_log($admin, 'user_role_' . $role, 'user', (int) $u['id'], null);
    json_out(['ok' => true]);
}

function route_mod_log(): void
{
    require_staff();
    $rows = db()->query(
        'SELECT m.*, u.display_name AS staff_name FROM g_mod_log m LEFT JOIN g_users u ON u.id = m.staff_id
         ORDER BY m.id DESC LIMIT 300'
    )->fetchAll();
    json_out(['items' => array_map(fn ($r) => [...$r, 'id' => (int) $r['id'], 'target_id' => (int) $r['target_id']], $rows)]);
}
