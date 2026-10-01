<?php
/**
 * E-maile i powiadomienia w aplikacji.
 *
 * MAIL_MODE=mail  — wysyłka funkcją mail() hostingu (dhosting ją obsługuje)
 * MAIL_MODE=log   — zapis do storage/mail.log (lokalnie i w testach)
 */

declare(strict_types=1);

function send_mail(string $to, string $subject, string $text): void
{
    $from = env('MAIL_FROM', 'gielda@dawmac.pl');
    $mode = env('MAIL_MODE', 'mail');

    if ($mode === 'log') {
        $dir = GIELDA_ROOT . '/storage';
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        file_put_contents(
            $dir . '/mail.log',
            sprintf("=== %s\nTo: %s\nSubject: %s\n\n%s\n\n", date('c'), $to, $subject, $text),
            FILE_APPEND
        );
        return;
    }

    $text .= "\n\n--\nDAWMAC Giełda — bezpłatna giełda felg\n" . app_url('/') . "\n";
    $headers = implode("\r\n", [
        'From: DAWMAC Giełda <' . $from . '>',
        'Reply-To: ' . env('CONTACT_EMAIL', $from),
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: 8bit',
    ]);
    $ok = @mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $text, $headers, '-f' . $from);
    if (!$ok) {
        error_log('[gielda] mail() nie wysłał wiadomości do ' . $to);
    }
}

/**
 * Powiadomienie w aplikacji, opcjonalnie też e-mailem.
 * Typy: message, moderation, report, expiring, match (później).
 */
function notify(int $userId, string $type, string $title, ?string $body = null, ?string $link = null, ?array $payload = null, bool $email = false): void
{
    db()->prepare(
        'INSERT INTO g_notifications (user_id, type, title, body, link, payload) VALUES (?, ?, ?, ?, ?, ?)'
    )->execute([$userId, $type, $title, $body, $link, $payload ? json_encode($payload) : null]);

    if ($email) {
        $stmt = db()->prepare("SELECT email FROM g_users WHERE id = ? AND status <> 'deleted'");
        $stmt->execute([$userId]);
        $to = $stmt->fetchColumn();
        if ($to) {
            $text = $title . "\n\n" . ($body ?? '');
            if ($link) {
                $text .= "\n\n" . app_url($link);
            }
            send_mail($to, $title, $text);
        }
    }
}
