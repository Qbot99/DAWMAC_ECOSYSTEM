<?php
/**
 * Codzienne sprzątanie giełdy. Cron w dPanelu: raz na dobę, np. 4:10,
 * Program PHP, plik ~/…/dawmac-gielda/tools/cron.php
 *
 * 1. Ogłoszenia po terminie → status expired (autor dostaje powiadomienie).
 * 2. Przypomnienie 3 dni przed wygaśnięciem.
 * 3. Retencja (zgodnie z polityką prywatności):
 *    - ogłoszenia zakończone/wygasłe/usunięte dłużej niż 12 miesięcy → usunięte ze zdjęciami,
 *    - przeczytane powiadomienia starsze niż 6 miesięcy → usunięte,
 *    - zużyte i przeterminowane tokeny, stare wpisy limitera → usunięte.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit("Tylko z konsoli.\n");
}

require __DIR__ . '/../api/lib/bootstrap.php';
require __DIR__ . '/../api/lib/mail.php';
require __DIR__ . '/../api/lib/images.php';

$pdo = db();
$log = fn (string $m) => print(date('Y-m-d H:i:s') . " $m\n");

// 1. Wygasłe
$expired = $pdo->query("SELECT id, user_id, title FROM g_listings WHERE status = 'active' AND expires_at <= NOW()")->fetchAll();
foreach ($expired as $l) {
    $pdo->prepare("UPDATE g_listings SET status = 'expired' WHERE id = ?")->execute([$l['id']]);
    notify((int) $l['user_id'], 'expired', 'Ogłoszenie „' . $l['title'] . '” wygasło',
        'Możesz je wznowić jednym kliknięciem w „Moje ogłoszenia”.', '/moje', ['listing_id' => (int) $l['id']], true);
}
$log('wygasło: ' . count($expired));

// 2. Przypomnienia (raz — okno 3 dni do 2 dni przed końcem, cron codzienny)
$soon = $pdo->query(
    "SELECT id, user_id, title FROM g_listings WHERE status = 'active'
     AND expires_at > NOW() + INTERVAL 2 DAY AND expires_at <= NOW() + INTERVAL 3 DAY"
)->fetchAll();
foreach ($soon as $l) {
    notify((int) $l['user_id'], 'expiring', 'Ogłoszenie „' . $l['title'] . '” wygaśnie za 3 dni',
        'Jeśli felgi są nadal aktualne, przedłuż ogłoszenie w „Moje ogłoszenia”.', '/moje', ['listing_id' => (int) $l['id']], true);
}
$log('przypomnienia: ' . count($soon));

// 3. Retencja
$old = $pdo->query(
    "SELECT id FROM g_listings WHERE status IN ('sold','closed','expired','removed') AND updated_at < NOW() - INTERVAL 12 MONTH"
)->fetchAll(PDO::FETCH_COLUMN);
foreach ($old as $id) {
    delete_listing_files((int) $id);
    $pdo->prepare('DELETE FROM g_listings WHERE id = ?')->execute([$id]);
}
$log('usunięte stare ogłoszenia: ' . count($old));

$n = $pdo->exec('DELETE FROM g_notifications WHERE read_at IS NOT NULL AND created_at < NOW() - INTERVAL 6 MONTH');
$log("usunięte powiadomienia: $n");
$pdo->exec('DELETE FROM g_tokens WHERE used_at IS NOT NULL OR expires_at < NOW()');
$pdo->exec('DELETE FROM g_rate_limit WHERE window_end < NOW()');
$log('gotowe');
