<?php
/**
 * ETAP 4 — felgi, których sklep już nie prowadzi.
 *
 * "Calibre Vintage" ma 52 auta w galerii, a sklep tego modelu nie sprzedaje.
 * To NIE jest błąd do naprawienia: wpis jest prawidłowy, zdjęcia są dobre,
 * po prostu nie ma produktu, na którego karcie mogłyby się pokazać.
 *
 * Podpięcie ich pod inny model Calibre byłoby gorsze niż zostawienie —
 * klient zobaczyłby cudze Vintage na karcie felgi, która wygląda inaczej.
 *
 * Dlatego zamiast tego zapisujemy decyzję i taki wpis znika z listy roboczej.
 * Zdjęcia zostają w galerii i dalej są widoczne pod dawmac.pl/galeria/.
 * Gdyby model kiedyś wrócił do oferty, wystarczy usunąć wiersz z tej tabeli.
 *
 *   php migrate_04_wheel_ignored.php --apply
 */

$root = getenv('DAWMAC_DOCROOT')
    ?: '/home/klient.dhosting.pl/dawmac/api.dawmacpolska.pl/public_html';

$_SERVER['DOCUMENT_ROOT'] = $root;
$_SERVER['REQUEST_METHOD'] = 'CLI';
ini_set('display_errors', 'stderr');

require $root . '/api/gallery/db.php';

$apply = in_array('--apply', $argv, true);
echo $apply ? "TRYB: zapis\n\n" : "TRYB: podgląd. Dodaj --apply.\n\n";

if (!$apply) {
    echo "Utworzy tabelę wheel_ignored (brand_norm, model_norm, note, created_at).\n";
    exit(0);
}

$conn->query("
    CREATE TABLE IF NOT EXISTS `wheel_ignored` (
        `id`         INT AUTO_INCREMENT PRIMARY KEY,
        `brand_norm` VARCHAR(64) NOT NULL,
        `model_norm` VARCHAR(64) NOT NULL,
        `note`       VARCHAR(255) NULL,
        `created_at` DATETIME NOT NULL,
        UNIQUE KEY `uq_wheel_ignored` (`brand_norm`, `model_norm`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
") or exit("Błąd CREATE TABLE: " . $conn->error . "\n");

echo "Tabela wheel_ignored gotowa.\n";
