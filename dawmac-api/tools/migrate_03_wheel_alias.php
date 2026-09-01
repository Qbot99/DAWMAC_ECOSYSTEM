<?php
/**
 * ETAP 3 — tabela aliasów felg.
 *
 * DLACZEGO NIE AUTOMATYCZNE SCALANIE PO PODOBIEŃSTWIE:
 * model felgi to kod, w którym jedna cyfra oznacza inną felgę. W danych są
 * pary różniące się dokładnie jednym znakiem, które NIE są literówkami:
 *
 *   Stuttgart ST4 vs ST3 · Platin P115 vs P113 · VEEMANN VM2 vs VM1
 *   ROTA (439 produktów) vs YOTA (139) — obie to prawdziwe marki w sklepie
 *
 * 79 par z galerii (165 aut) różni się właśnie cyfrą modelu. Zautomatyzowane
 * scalenie zniszczyłoby te dane bez możliwości odtworzenia.
 *
 * Dlatego: podobieństwo służy WYŁĄCZNIE do podpowiadania w wyszukiwarce,
 * a trwałe powiązanie zapisuje się tutaj — raz, świadomie, i działa na zawsze.
 *
 *   php migrate_03_wheel_alias.php            — podgląd
 *   php migrate_03_wheel_alias.php --apply     — tworzy tabelę + bezpieczny zasiew
 *   php migrate_03_wheel_alias.php --apply --backfill  — dodatkowo przelicza istniejące wpisy
 */

$root = getenv('DAWMAC_DOCROOT')
    ?: '/home/klient.dhosting.pl/dawmac/api.dawmacpolska.pl/public_html';

$_SERVER['DOCUMENT_ROOT'] = $root;
$_SERVER['REQUEST_METHOD'] = 'CLI';
ini_set('display_errors', 'stderr');

require $root . '/api/gallery/lib/wheel_norm.php';
require $root . '/api/gallery/db.php';

$apply    = in_array('--apply', $argv, true);
$backfill = in_array('--backfill', $argv, true);

echo $apply ? "TRYB: zapis\n\n" : "TRYB: podgląd. Dodaj --apply.\n\n";

/* ------------------------------------------------------------------ */
/* Tabela                                                              */
/* ------------------------------------------------------------------ */

if ($apply) {
    $conn->query("
        CREATE TABLE IF NOT EXISTS `wheel_alias` (
            `id`         INT AUTO_INCREMENT PRIMARY KEY,
            `from_brand` VARCHAR(64) NOT NULL,
            `from_model` VARCHAR(64) NOT NULL DEFAULT '',
            `to_brand`   VARCHAR(64) NOT NULL,
            `to_model`   VARCHAR(64) NOT NULL DEFAULT '',
            `note`       VARCHAR(255) NULL,
            `created_at` DATETIME NOT NULL,
            UNIQUE KEY `uq_wheel_alias` (`from_brand`, `from_model`),
            KEY `idx_alias_brand` (`from_brand`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ") or exit("Błąd CREATE TABLE: " . $conn->error . "\n");
    echo "Tabela wheel_alias gotowa.\n\n";
}

/* ------------------------------------------------------------------ */
/* Zasiew — TYLKO przypadki, których nie da się podważyć               */
/* ------------------------------------------------------------------ */

/*
 * Kryterium przyjęcia do zasiewu jest wąskie celowo:
 *  - marka źródłowa NIE ISTNIEJE w katalogu sklepu (czyli na pewno literówka),
 *  - marka docelowa jest jedna i jednoznaczna,
 *  - alias dotyczy MARKI, nie modelu (modele różnią się cyframi, patrz wyżej).
 *
 * "Forza" nie występuje w sklepie ani razu, a "Forzza" ma 496 produktów.
 * Tu nie ma czego rozstrzygać.
 */
$zasiew = [
    ['FORZA', '', 'FORZZA', '', 'Forza -> Forzza: "Forza" nie istnieje w katalogu sklepu'],
];

echo "ZASIEW (aliasy marek, bezsporne):\n";

foreach ($zasiew as [$fb, $fm, $tb, $tm, $note]) {
    // Nie wpisujemy aliasu na markę, która sama występuje w sklepie —
    // to by znaczyło, że obie są prawdziwe i mapowanie byłoby błędem.
    $stmt = $conn->prepare("SELECT COUNT(*) FROM wheel_dict WHERE brand_norm = ? AND active = 1");
    $stmt->bind_param('s', $fb);
    $stmt->execute();
    $wSklepie = (int) ($stmt->get_result()->fetch_row()[0] ?? 0);
    $stmt->close();

    $stmt = $conn->prepare("SELECT COUNT(*) FROM wheel_dict WHERE brand_norm = ? AND active = 1");
    $stmt->bind_param('s', $tb);
    $stmt->execute();
    $cel = (int) ($stmt->get_result()->fetch_row()[0] ?? 0);
    $stmt->close();

    if ($wSklepie > 0) {
        printf("  POMIJAM %s -> %s : %s istnieje w sklepie (%d pozycji), to nie literówka\n", $fb, $tb, $fb, $wSklepie);
        continue;
    }
    if ($cel === 0) {
        printf("  POMIJAM %s -> %s : celu nie ma w słowniku\n", $fb, $tb);
        continue;
    }

    printf("  %s -> %s  (cel: %d pozycji w słowniku)\n", $fb, $tb, $cel);

    if ($apply) {
        $stmt = $conn->prepare(
            "INSERT INTO wheel_alias (from_brand, from_model, to_brand, to_model, note, created_at)
             VALUES (?, ?, ?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE to_brand = VALUES(to_brand), to_model = VALUES(to_model), note = VALUES(note)"
        );
        $stmt->bind_param('sssss', $fb, $fm, $tb, $tm, $note);
        $stmt->execute();
        $stmt->close();
    }
}

if (!$apply) {
    echo "\nNic nie zapisano.\n";
    exit(0);
}

/* ------------------------------------------------------------------ */
/* Przeliczenie istniejących wpisów przez aliasy                       */
/* ------------------------------------------------------------------ */

if (!$backfill) {
    $n = $conn->query("SELECT COUNT(*) n FROM wheel_alias")->fetch_assoc()['n'];
    echo "\nAliasów w tabeli: $n. Dodaj --backfill, żeby przeliczyć istniejące wpisy galerii.\n";
    exit(0);
}

$aliasy = [];
$r = $conn->query("SELECT from_brand, from_model, to_brand, to_model FROM wheel_alias");
while ($a = $r->fetch_assoc()) {
    $aliasy[$a['from_brand'] . "\x1f" . $a['from_model']] = [$a['to_brand'], $a['to_model']];
}

$zmienione = 0;
$r = $conn->query("SELECT id, brand_norm, model_norm FROM wheel WHERE brand_norm <> ''");
$doZmiany = [];

while ($w = $r->fetch_assoc()) {
    $b = $w['brand_norm'];
    $m = $w['model_norm'];

    // Najpierw alias pełnej pary, potem alias samej marki.
    $para  = $aliasy[$b . "\x1f" . $m] ?? null;
    $marka = $aliasy[$b . "\x1f"] ?? null;

    if ($para !== null) {
        [$nb, $nm] = [$para[0], $para[1] !== '' ? $para[1] : $m];
    } elseif ($marka !== null) {
        [$nb, $nm] = [$marka[0], $m];
    } else {
        continue;
    }

    if ($nb !== $b || $nm !== $m) {
        $doZmiany[] = [(int) $w['id'], $nb, $nm];
    }
}

$stmt = $conn->prepare("UPDATE wheel SET brand_norm = ?, model_norm = ? WHERE id = ?");
$conn->begin_transaction();
foreach ($doZmiany as [$id, $nb, $nm]) {
    $stmt->bind_param('ssi', $nb, $nm, $id);
    $stmt->execute();
    $zmienione++;
}
$conn->commit();
$stmt->close();

printf("\nPrzeliczone wiersze wheel: %d\n", $zmienione);
echo "Pamiętaj: oryginalne brand/model zostały nietknięte — zmienił się tylko klucz dopasowania.\n";
