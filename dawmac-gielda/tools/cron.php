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
 * 4. Słownik aut (car_brand, car_model) z galerii → kopia w bazie giełdy,
 *    gdy ustawione jest GALLERY_ENV_FILE (giełda ma własnego użytkownika bazy
 *    i nie widzi galerii; PHP z konsoli nie ma open_basedir, więc tu się da).
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

// 4. Słownik aut — błąd galerii nie zatrzymuje reszty sprzątania
$galleryEnv = env('GALLERY_ENV_FILE', '');
if ($galleryEnv !== '') {
    try {
        $log('słownik aut: ' . sync_car_dictionary($galleryEnv, $pdo));
    } catch (Throwable $e) {
        $log('słownik aut: BŁĄD ' . $e->getMessage());
    }
}
$log('gotowe');

/**
 * Zastępuje car_brand i car_model w bazie giełdy zawartością z galerii
 * (te same id, więc car_brand_id / car_model_id w ogłoszeniach się zgadzają).
 * Jedna transakcja: strona do końca widzi starą kopię.
 */
function sync_car_dictionary(string $file, PDO $pdo): string
{
    $g = is_file($file) ? gielda_parse_env($file) : [];
    if (empty($g['DB_NAME'])) {
        throw new RuntimeException("brak pliku albo DB_NAME w $file");
    }
    $src = new PDO(
        'mysql:host=' . ($g['DB_SERVER'] ?? 'localhost') . ';dbname=' . $g['DB_NAME'] . ';charset=utf8mb4',
        $g['DB_USER'] ?? '',
        $g['DB_PASSWORD'] ?? '',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    // Galeria ma modele bez marki — w giełdzie model wybiera się zawsze w ramach marki.
    $brands = $src->query('SELECT id, name FROM car_brand WHERE name IS NOT NULL')->fetchAll(PDO::FETCH_NUM);
    $models = $src->query('SELECT id, car_brand_id, name FROM car_model WHERE car_brand_id IS NOT NULL AND name IS NOT NULL')
        ->fetchAll(PDO::FETCH_NUM);
    if (!$brands) {
        throw new RuntimeException('car_brand w galerii jest pusty, kopia bez zmian');
    }

    $pdo->exec('CREATE TABLE IF NOT EXISTS car_brand (id INT UNSIGNED PRIMARY KEY, name VARCHAR(255) NOT NULL)
                ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    $pdo->exec('CREATE TABLE IF NOT EXISTS car_model (id INT UNSIGNED PRIMARY KEY, car_brand_id INT UNSIGNED NOT NULL,
                name VARCHAR(255) NOT NULL, KEY idx_brand (car_brand_id))
                ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

    $pdo->beginTransaction();
    try {
        $pdo->exec('DELETE FROM car_model');
        $pdo->exec('DELETE FROM car_brand');
        insert_rows($pdo, 'car_brand (id, name)', $brands);
        insert_rows($pdo, 'car_model (id, car_brand_id, name)', $models);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
    return count($brands) . ' marek, ' . count($models) . ' modeli';
}

function insert_rows(PDO $pdo, string $into, array $rows): void
{
    foreach (array_chunk($rows, 500) as $chunk) {
        $row = '(' . implode(', ', array_fill(0, count($chunk[0]), '?')) . ')';
        $pdo->prepare("INSERT INTO $into VALUES " . implode(', ', array_fill(0, count($chunk), $row)))
            ->execute(array_merge(...$chunk));
    }
}
