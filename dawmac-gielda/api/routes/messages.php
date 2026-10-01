<?php
/**
 * Wiadomości między użytkownikami (zawsze przy ogłoszeniu) i powiadomienia.
 * Kontakt przez czat zamiast pokazywania e-maila — mniej danych osobowych
 * na widoku i mniej oszustw.
 */

declare(strict_types=1);

function conversation_for(int $convId, array $user): array
{
    $stmt = db()->prepare(
        'SELECT c.*, l.title AS listing_title, l.type AS listing_type, l.status AS listing_status,
                o.display_name AS owner_name, o.status AS owner_status, x.display_name AS other_name, x.status AS other_status
         FROM g_conversations c
         JOIN g_listings l ON l.id = c.listing_id
         JOIN g_users o ON o.id = c.owner_id
         JOIN g_users x ON x.id = c.other_id
         WHERE c.id = ?'
    );
    $stmt->execute([$convId]);
    $c = $stmt->fetch();
    if (!$c || !in_array((int) $user['id'], [(int) $c['owner_id'], (int) $c['other_id']], true)) {
        fail(404, 'Nie ma takiej rozmowy.');
    }
    return $c;
}

function conversation_view(array $c, array $user): array
{
    $mine = (int) $c['owner_id'] === (int) $user['id'] ? 'owner' : 'other';
    $partner = $mine === 'owner' ? 'other' : 'owner';
    return [
        'id'              => (int) $c['id'],
        'listing'         => [
            'id' => (int) $c['listing_id'], 'title' => $c['listing_title'],
            'type' => $c['listing_type'], 'status' => $c['listing_status'],
        ],
        'partner'         => [
            'id'           => (int) $c[$partner . '_id'],
            'display_name' => $c[$partner . '_name'],
            'active'       => $c[$partner . '_status'] === 'active',
        ],
        'i_am_owner'      => $mine === 'owner',
        'last_message_at' => $c['last_message_at'],
        'unread'          => (int) $c[$mine . '_read_id'] < (int) $c['last_message_id'],
        'last_message'    => $c['last_body'] ?? null,
    ];
}

function insert_message(array $conv, array $sender, string $body): void
{
    $pdo = db();
    $pdo->prepare('INSERT INTO g_messages (conversation_id, sender_id, body) VALUES (?, ?, ?)')
        ->execute([$conv['id'], $sender['id'], $body]);
    $msgId = (int) $pdo->lastInsertId();
    $side = (int) $conv['owner_id'] === (int) $sender['id'] ? 'owner' : 'other';
    $pdo->prepare("UPDATE g_conversations SET last_message_at = NOW(), last_message_id = ?, {$side}_read_id = ? WHERE id = ?")
        ->execute([$msgId, $msgId, $conv['id']]);

    $recipient = $side === 'owner' ? (int) $conv['other_id'] : (int) $conv['owner_id'];
    $r = $pdo->prepare('SELECT notify_messages FROM g_users WHERE id = ?');
    $r->execute([$recipient]);
    $wantsEmail = (bool) $r->fetchColumn();

    // Jeden e-mail na rozmowę, dopóki odbiorca jej nie przeczyta — bez zasypywania skrzynki.
    $unreadBefore = $pdo->prepare(
        "SELECT COUNT(*) FROM g_notifications WHERE user_id = ? AND type = 'message' AND read_at IS NULL
         AND JSON_EXTRACT(payload, '$.conversation_id') = ?"
    );
    $unreadBefore->execute([$recipient, (int) $conv['id']]);
    $first = (int) $unreadBefore->fetchColumn() === 0;

    if ($first) {
        notify(
            $recipient,
            'message',
            'Nowa wiadomość: ' . ($conv['listing_title'] ?? 'ogłoszenie'),
            $sender['display_name'] . ': ' . mb_substr($body, 0, 200),
            '/wiadomosci/' . $conv['id'],
            ['conversation_id' => (int) $conv['id']],
            $wantsEmail
        );
    }
}

/** Pierwsza wiadomość do autora ogłoszenia (albo kolejna w tej samej rozmowie). */
function route_listing_message(string $listingId): void
{
    $user = require_user();
    rate_limit('message', (string) $user['id'], 60, 3600);

    $body = message_body();
    $l = load_listing((int) $listingId);
    if (!$l || !can_see_listing($l, $user) || $l['status'] !== 'active') {
        fail(404, 'To ogłoszenie nie przyjmuje już wiadomości.');
    }
    if ((int) $l['user_id'] === (int) $user['id']) {
        fail(422, 'To Twoje ogłoszenie.');
    }

    db()->prepare('INSERT IGNORE INTO g_conversations (listing_id, owner_id, other_id) VALUES (?, ?, ?)')
        ->execute([$l['id'], $l['user_id'], $user['id']]);
    $id = db()->prepare('SELECT id FROM g_conversations WHERE listing_id = ? AND other_id = ?');
    $id->execute([$l['id'], $user['id']]);
    $conv = conversation_for((int) $id->fetchColumn(), $user);

    insert_message($conv, $user, $body);
    json_out(['conversation_id' => (int) $conv['id']], 201);
}

function message_body(): string
{
    $body = trim((string) (input()['body'] ?? ''));
    if ($body === '') {
        fail(422, 'Wiadomość jest pusta.', ['body' => 'Napisz wiadomość.']);
    }
    if (mb_strlen($body) > 3000) {
        fail(422, 'Wiadomość może mieć najwyżej 3000 znaków.', ['body' => 'Za długa wiadomość.']);
    }
    return $body;
}

function route_conversations(): void
{
    $user = require_user(true);
    $stmt = db()->prepare(
        'SELECT c.*, l.title AS listing_title, l.type AS listing_type, l.status AS listing_status,
                o.display_name AS owner_name, o.status AS owner_status, x.display_name AS other_name, x.status AS other_status,
                (SELECT body FROM g_messages m WHERE m.conversation_id = c.id ORDER BY m.id DESC LIMIT 1) AS last_body
         FROM g_conversations c
         JOIN g_listings l ON l.id = c.listing_id
         JOIN g_users o ON o.id = c.owner_id
         JOIN g_users x ON x.id = c.other_id
         WHERE c.owner_id = ? OR c.other_id = ?
         ORDER BY c.last_message_at DESC LIMIT 200'
    );
    $stmt->execute([$user['id'], $user['id']]);
    json_out(['items' => array_map(fn ($c) => conversation_view($c, $user), $stmt->fetchAll())]);
}

function route_conversation_show(string $id): void
{
    $user = require_user(true);
    $c = conversation_for((int) $id, $user);
    $m = db()->prepare('SELECT id, sender_id, body, created_at FROM g_messages WHERE conversation_id = ? ORDER BY id');
    $m->execute([$c['id']]);
    $messages = array_map(fn ($r) => [
        'id' => (int) $r['id'], 'mine' => (int) $r['sender_id'] === (int) $user['id'],
        'body' => $r['body'], 'created_at' => $r['created_at'],
    ], $m->fetchAll());

    $side = (int) $c['owner_id'] === (int) $user['id'] ? 'owner' : 'other';
    db()->prepare("UPDATE g_conversations SET {$side}_read_id = last_message_id WHERE id = ?")->execute([$c['id']]);
    db()->prepare(
        "UPDATE g_notifications SET read_at = NOW() WHERE user_id = ? AND type = 'message' AND read_at IS NULL
         AND JSON_EXTRACT(payload, '$.conversation_id') = ?"
    )->execute([$user['id'], (int) $c['id']]);

    json_out(['conversation' => conversation_view($c, $user), 'messages' => $messages]);
}

function route_conversation_reply(string $id): void
{
    $user = require_user();
    rate_limit('message', (string) $user['id'], 60, 3600);
    $c = conversation_for((int) $id, $user);
    $partnerActive = (int) $c['owner_id'] === (int) $user['id'] ? $c['other_status'] : $c['owner_status'];
    if ($partnerActive !== 'active') {
        fail(422, 'Ta osoba nie ma już aktywnego konta.');
    }
    insert_message($c, $user, message_body());
    json_out(['ok' => true], 201);
}

function route_notifications(): void
{
    $user = require_user(true);
    $stmt = db()->prepare('SELECT id, type, title, body, link, read_at, created_at FROM g_notifications WHERE user_id = ? ORDER BY id DESC LIMIT 100');
    $stmt->execute([$user['id']]);
    json_out(['items' => array_map(fn ($n) => [...$n, 'id' => (int) $n['id'], 'read' => $n['read_at'] !== null], $stmt->fetchAll())]);
}

function route_notifications_read(): void
{
    $user = require_user(true);
    $ids = input()['ids'] ?? null;
    if (is_array($ids) && $ids) {
        $in = implode(',', array_map('intval', $ids));
        db()->prepare("UPDATE g_notifications SET read_at = NOW() WHERE user_id = ? AND id IN ($in) AND read_at IS NULL")
            ->execute([$user['id']]);
    } else {
        db()->prepare('UPDATE g_notifications SET read_at = NOW() WHERE user_id = ? AND read_at IS NULL')->execute([$user['id']]);
    }
    json_out(['ok' => true]);
}
