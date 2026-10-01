<?php
/**
 * ETAP 5 — baza dopasowań felg do aut (własny odpowiednik wheel-size).
 *
 * Cztery tabele, od ogółu do szczegółu:
 *
 *   fit_make        marka auta              Volkswagen
 *   fit_model       model                   Golf
 *   fit_generation  generacja + piasta      VIII, 2019-, 5x112, CB 57.1, M14x1.5, śruba
 *   fit_wheel       rozmiary felg i opon    7.5Jx17 ET51, 225/45 R17, fabryczny
 *
 * Parametry piasty siedzą na generacji, bo w obrębie generacji praktycznie
 * się nie zmieniają, a to one decydują, czy felga w ogóle wejdzie.
 *
 * Tabele NIE są połączone z car_brand/car_model galerii: tamte to etykiety
 * zdjęć wpisywane ręcznie, te to dane techniczne z kontrolą źródła.
 * Połączenie ich to osobna decyzja.
 *
 * Dane wgrywa się przez tools/import_fitment.php, nie ręcznie.
 *
 *   php migrate_05_fitment.php            — podgląd
 *   php migrate_05_fitment.php --apply    — tworzy tabele (bezpieczne do powtórzenia)
 */

$root = getenv('DAWMAC_DOCROOT')
    ?: '/home/klient.dhosting.pl/dawmac/api.dawmacpolska.pl/public_html';

$_SERVER['DOCUMENT_ROOT'] = $root;
$_SERVER['REQUEST_METHOD'] = 'CLI';
ini_set('display_errors', 'stderr');

$apply = in_array('--apply', $argv, true);

$tabele = [
    'fit_make' => "
        CREATE TABLE IF NOT EXISTS `fit_make` (
            `id`         INT AUTO_INCREMENT PRIMARY KEY,
            `name`       VARCHAR(64) NOT NULL,
            `slug`       VARCHAR(96) NOT NULL,
            `country`    CHAR(2) NULL,
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME NOT NULL,
            UNIQUE KEY `uq_fit_make_slug` (`slug`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    'fit_model' => "
        CREATE TABLE IF NOT EXISTS `fit_model` (
            `id`         INT AUTO_INCREMENT PRIMARY KEY,
            `make_id`    INT NOT NULL,
            `name`       VARCHAR(96) NOT NULL,
            `slug`       VARCHAR(96) NOT NULL,
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME NOT NULL,
            UNIQUE KEY `uq_fit_model` (`make_id`, `slug`),
            CONSTRAINT `fk_fit_model_make` FOREIGN KEY (`make_id`)
                REFERENCES `fit_make` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    // verified = 1 znaczy: człowiek potwierdził dane. Import nie nadpisze
    // takiego wpisu danymi niezweryfikowanymi — to bezpiecznik pod
    // przyszłe automatyczne dopisywanie nowych modeli.
    'fit_generation' => "
        CREATE TABLE IF NOT EXISTS `fit_generation` (
            `id`          INT AUTO_INCREMENT PRIMARY KEY,
            `model_id`    INT NOT NULL,
            `name`        VARCHAR(96) NOT NULL,
            `slug`        VARCHAR(96) NOT NULL,
            `year_from`   SMALLINT NOT NULL,
            `year_to`     SMALLINT NULL,
            `pcd_holes`   TINYINT UNSIGNED NOT NULL,
            `pcd_mm`      DECIMAL(6,2) NOT NULL,
            `center_bore` DECIMAL(6,2) NOT NULL,
            `thread`      VARCHAR(16) NOT NULL,
            `fastener`    ENUM('bolt','nut') NOT NULL,
            `torque_nm`   SMALLINT UNSIGNED NULL,
            `source`      VARCHAR(255) NULL,
            `verified`    TINYINT(1) NOT NULL DEFAULT 0,
            `created_at`  DATETIME NOT NULL,
            `updated_at`  DATETIME NOT NULL,
            UNIQUE KEY `uq_fit_generation` (`model_id`, `slug`),
            KEY `idx_fit_generation_pcd` (`pcd_holes`, `pcd_mm`, `center_bore`),
            CONSTRAINT `fk_fit_generation_model` FOREIGN KEY (`model_id`)
                REFERENCES `fit_model` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    // tire = '' zamiast NULL, bo NULL w kluczu unikalnym przepuszcza duplikaty.
    'fit_wheel' => "
        CREATE TABLE IF NOT EXISTS `fit_wheel` (
            `id`            INT AUTO_INCREMENT PRIMARY KEY,
            `generation_id` INT NOT NULL,
            `axle`          ENUM('both','front','rear') NOT NULL DEFAULT 'both',
            `diameter`      TINYINT UNSIGNED NOT NULL,
            `width`         DECIMAL(3,1) NOT NULL,
            `et`            SMALLINT NOT NULL,
            `tire`          VARCHAR(24) NOT NULL DEFAULT '',
            `is_oem`        TINYINT(1) NOT NULL DEFAULT 0,
            UNIQUE KEY `uq_fit_wheel` (`generation_id`, `axle`, `diameter`, `width`, `et`, `tire`),
            CONSTRAINT `fk_fit_wheel_generation` FOREIGN KEY (`generation_id`)
                REFERENCES `fit_generation` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
];

if (!$apply) {
    echo "TRYB: podgląd. Dodaj --apply.\n\n";
    echo "Zostaną utworzone (jeśli ich nie ma): " . implode(', ', array_keys($tabele)) . "\n";
    echo "\nNic nie zapisano.\n";
    exit(0);
}

require $root . '/api/gallery/db.php';

echo "TRYB: zapis\n\n";

// Kolejność ma znaczenie: klucze obce wskazują na tabele utworzone wyżej.
foreach ($tabele as $nazwa => $sql) {
    $conn->query($sql) or exit("Błąd CREATE TABLE $nazwa: " . $conn->error . "\n");
    echo "Tabela $nazwa gotowa.\n";
}

echo "\nDalej: php import_fitment.php  (podgląd i walidacja danych z data/fitment)\n";
