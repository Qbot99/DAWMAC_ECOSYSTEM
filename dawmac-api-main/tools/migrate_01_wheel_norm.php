<?php
/**
 * ETAP 1 — klucz dopasowania galeria ↔ sklep.
 *
 * Dokłada do tabeli `wheel` dwie kolumny z ujednoliconą marką i modelem
 * oraz indeks po nich. NIE rusza istniejących danych: `brand` i `model`
 * zostają dokładnie takie, jakie wpisał pracownik.
 *
 * Uruchamianie (z katalogu tools/ na serwerze):
 *
 *   php migrate_01_wheel_norm.php              — tylko raport, nic nie zapisuje
 *   php migrate_01_wheel_norm.php --apply      — dokłada kolumny i wypełnia
 *   php migrate_01_wheel_norm.php --apply --quiet   — bez listy scaleń
 *
 * Skrypt jest idempotentny: druga i kolejne próby tylko dopełniają braki.
 */

$root = getenv('DAWMAC_DOCROOT')
    ?: '/home/klient.dhosting.pl/dawmac/api.dawmacpolska.pl/public_html';

$_SERVER['DOCUMENT_ROOT'] = $root;
$_SERVER['REQUEST_METHOD'] = 'CLI';

// Ścieżki liczone od DOCUMENT_ROOT, nie od __DIR__ — dzięki temu katalog
// tools/ może leżeć POZA public_html i nie da się go odpalić z przeglądarki.
require $root . '/api/gallery/lib/wheel_norm.php';
require $root . '/api/gallery/db.php';

$apply = in_array('--apply', $argv, true);
$quiet = in_array('--quiet', $argv, true);

echo $apply
    ? "TRYB: zapis (--apply)\n\n"
    : "TRYB: podgląd — nic nie zostanie zapisane. Dodaj --apply, żeby wykonać.\n\n";

/* ------------------------------------------------------------------ */
/* 1. Kolumny i indeks                                                 */
/* ------------------------------------------------------------------ */

function kolumna_istnieje(mysqli $conn, string $tabela, string $kolumna): bool
{
    // SHOW COLUMNS ... LIKE ? nie da się przygotować w MariaDB,
    // dlatego pytamy information_schema — tam placeholdery działają.
    $stmt = $conn->prepare(
        "SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?"
    );
    $stmt->bind_param('ss', $tabela, $kolumna);
    $stmt->execute();
    $ile = (int) ($stmt->get_result()->fetch_row()[0] ?? 0);
    $stmt->close();
    return $ile > 0;
}

$brakujace = [];
foreach (['brand_norm', 'model_norm'] as $kolumna) {
    if (!kolumna_istnieje($conn, 'wheel', $kolumna)) {
        $brakujace[] = $kolumna;
    }
}

if ($brakujace) {
    echo "Brakujące kolumny w `wheel`: " . implode(', ', $brakujace) . "\n";
    if ($apply) {
        $czesci = array_map(
            static fn(string $k): string => "ADD COLUMN `$k` VARCHAR(64) NOT NULL DEFAULT ''",
            $brakujace
        );
        $sql = 'ALTER TABLE `wheel` ' . implode(', ', $czesci);
        if (!$conn->query($sql)) {
            exit("BŁĄD ALTER: " . $conn->error . "\n");
        }
        echo "  -> dodane\n";
    } else {
        echo "  -> ALTER TABLE `wheel` ADD COLUMN … (do wykonania)\n";
    }
} else {
    echo "Kolumny brand_norm/model_norm: już są.\n";
}

$indeks = $conn->query("SHOW INDEX FROM `wheel` WHERE Key_name = 'idx_wheel_norm'");
if ($indeks && $indeks->num_rows === 0) {
    echo "Indeks idx_wheel_norm: brak.\n";
    if ($apply) {
        if (!$conn->query("CREATE INDEX `idx_wheel_norm` ON `wheel` (`brand_norm`, `model_norm`)")) {
            exit("BŁĄD INDEX: " . $conn->error . "\n");
        }
        echo "  -> utworzony\n";
    } else {
        echo "  -> CREATE INDEX idx_wheel_norm (brand_norm, model_norm) (do wykonania)\n";
    }
} elseif ($indeks) {
    echo "Indeks idx_wheel_norm: już jest.\n";
}

/* ------------------------------------------------------------------ */
/* 2. Wypełnienie + raport                                             */
/* ------------------------------------------------------------------ */

$wiersze = $conn->query("SELECT id, brand, model FROM `wheel` ORDER BY id");
if (!$wiersze) {
    exit("BŁĄD SELECT: " . $conn->error . "\n");
}

$grupy = [];      // znormalizowana para -> [ surowe zapisy => ile ]
$doZapisu = [];   // id -> [brand_norm, model_norm]
$puste = 0;

while ($w = $wiersze->fetch_assoc()) {
    $bn = dawmac_wheel_norm($w['brand']);
    $mn = dawmac_wheel_norm($w['model']);

    $doZapisu[(int) $w['id']] = [$bn, $mn];

    if ($bn === '' || $mn === '') {
        $puste++;
    }

    $klucz  = $bn . "\x1f" . $mn;
    $surowy = (string) $w['brand'] . ' / ' . (string) $w['model'];
    $grupy[$klucz][$surowy] = ($grupy[$klucz][$surowy] ?? 0) + 1;
}

$parSurowo = 0;
foreach ($grupy as $warianty) {
    $parSurowo += count($warianty);
}

printf("\nWierszy w `wheel`:      %d\n", count($doZapisu));
printf("Par surowo:             %d\n", $parSurowo);
printf("Par po ujednoliceniu:   %d\n", count($grupy));
printf("Scaleń:                 %d\n", $parSurowo - count($grupy));
printf("Wierszy bez marki lub modelu (do ręcznej poprawy): %d\n", $puste);

if (!$quiet) {
    $doScalenia = array_filter($grupy, static fn(array $w): bool => count($w) > 1);
    uasort($doScalenia, static fn(array $a, array $b): int => array_sum($b) <=> array_sum($a));

    echo "\n=== CO SIĘ SCALI (" . count($doScalenia) . " grup) ===\n";
    foreach ($doScalenia as $klucz => $warianty) {
        [$bn, $mn] = explode("\x1f", $klucz);
        arsort($warianty);
        $opis = [];
        foreach ($warianty as $surowy => $ile) {
            $opis[] = sprintf('"%s" ×%d', $surowy, $ile);
        }
        printf("  %-34s <- %s\n", ($bn . ' / ' . $mn), implode('  ·  ', $opis));
    }
}

if (!$apply) {
    echo "\nNic nie zapisano. Uruchom ponownie z --apply.\n";
    exit(0);
}

$stmt = $conn->prepare("UPDATE `wheel` SET brand_norm = ?, model_norm = ? WHERE id = ?");
$zapisane = 0;

$conn->begin_transaction();
foreach ($doZapisu as $id => [$bn, $mn]) {
    $stmt->bind_param('ssi', $bn, $mn, $id);
    if (!$stmt->execute()) {
        $conn->rollback();
        exit("BŁĄD UPDATE przy id=$id: " . $stmt->error . "\n");
    }
    $zapisane++;
}
$conn->commit();
$stmt->close();

printf("\nWypełnione wiersze: %d\n", $zapisane);
echo "Gotowe.\n";
