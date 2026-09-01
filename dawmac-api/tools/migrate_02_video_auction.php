<?php
/**
 * ETAP 2 — pola na film i aukcję.
 *
 * Galeria: project.youtube_url + project.auction_url (film pokazuje AUTO,
 * więc siedzi przy projekcie, nie przy feldze).
 *
 * Forged:  wheel.auction_url. Filmu tu NIE dokładamy — tabela `video`
 * (youtube_url, wheel_id) istnieje od dawna i jest już używana przez
 * list_wheels.php. Wypełniona jest dla 18 z 1141 felg, czyli mechanizm
 * działa, brakowało tylko wygodnego miejsca na wklejenie linku.
 *
 *   php migrate_02_video_auction.php            — podgląd
 *   php migrate_02_video_auction.php --apply    — wykonanie
 *
 * Idempotentny. Wyłącznie ADD COLUMN — nic nie nadpisuje.
 */

$root = getenv('DAWMAC_DOCROOT')
    ?: '/home/klient.dhosting.pl/dawmac/api.dawmacpolska.pl/public_html';

$_SERVER['DOCUMENT_ROOT'] = $root;
$_SERVER['REQUEST_METHOD'] = 'CLI';

// db.php wysyła nagłówki HTTP — w CLI to nieszkodliwe, ale zaśmieca raport.
// Ostrzeżenia idą na stderr, żeby na stdout został sam wynik migracji.
ini_set('display_errors', 'stderr');

$apply = in_array('--apply', $argv, true);
echo $apply ? "TRYB: zapis\n\n" : "TRYB: podgląd. Dodaj --apply.\n\n";

function kolumna_jest(mysqli $c, string $tabela, string $kolumna): bool
{
    $stmt = $c->prepare(
        "SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?"
    );
    $stmt->bind_param('ss', $tabela, $kolumna);
    $stmt->execute();
    $n = (int) ($stmt->get_result()->fetch_row()[0] ?? 0);
    $stmt->close();
    return $n > 0;
}

function dodaj(mysqli $c, string $tabela, string $kolumna, string $typ, bool $apply): void
{
    if (kolumna_jest($c, $tabela, $kolumna)) {
        printf("  %-24s już jest\n", "$tabela.$kolumna");
        return;
    }

    printf("  %-24s BRAK -> %s\n", "$tabela.$kolumna", $apply ? 'dodaję' : 'do dodania');

    if ($apply && !$c->query("ALTER TABLE `$tabela` ADD COLUMN `$kolumna` $typ")) {
        exit("    BŁĄD: " . $c->error . "\n");
    }
}

/* ---------------- galeria ---------------- */
echo "BAZA GALERII\n";
require $root . '/api/gallery/db.php';           // $conn
dodaj($conn, 'project', 'youtube_url', "VARCHAR(255) NULL DEFAULT NULL", $apply);
dodaj($conn, 'project', 'auction_url', "VARCHAR(500) NULL DEFAULT NULL", $apply);
$conn->close();

/* ---------------- forged ----------------- */
echo "\nBAZA FORGED\n";
unset($conn);
require $root . '/api/forged/db.php';            // $conn (druga baza)
dodaj($conn, 'wheel', 'auction_url', "VARCHAR(500) NULL DEFAULT NULL", $apply);
echo "  video.youtube_url        już jest (tabela video, 20 wpisów)\n";
$conn->close();

echo $apply ? "\nGotowe.\n" : "\nNic nie zmieniono.\n";
