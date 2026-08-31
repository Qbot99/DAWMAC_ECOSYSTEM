<?php
/**
 * Zapisuje decyzję z listy roboczej: "to, co wpisano, oznacza tamtą felgę".
 *
 * Działa na PARACH, nie na pojedynczych autach — jedna decyzja poprawia od razu
 * wszystkie wpisy zapisane tak samo. Rozstrzygnięcie "JAPANRACNIG to Japan
 * Racing" naprawia trzy auta jednym kliknięciem.
 *
 * POST:
 *   from_brand, from_model  — co jest wpisane w galerii (dowolny zapis)
 *   to_brand,   to_model    — poprawna felga ze słownika
 *   scope = pair | brand    — czy alias dotyczy tej pary, czy całej marki
 *
 * Oryginalne pola brand/model w tabeli wheel zostają NIETKNIĘTE. Zmienia się
 * tylko klucz dopasowania, więc w galerii dalej widać to, co wpisał pracownik.
 */

require 'db.php';
require_once __DIR__ . '/lib/wheel_norm.php';
require_once __DIR__ . '/lib/auth_guard.php';

dawmac_require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Wymagane POST.'], JSON_UNESCAPED_UNICODE);
    exit();
}

$fromBrand = dawmac_wheel_norm($_POST['from_brand'] ?? '');
$fromModel = dawmac_wheel_norm($_POST['from_model'] ?? '');
$toBrand   = dawmac_wheel_norm($_POST['to_brand'] ?? '');
$toModel   = dawmac_wheel_norm($_POST['to_model'] ?? '');
$scope     = ($_POST['scope'] ?? 'pair') === 'brand' ? 'brand' : 'pair';

if ($fromBrand === '' || $toBrand === '' || $toModel === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Brakuje danych felgi.'], JSON_UNESCAPED_UNICODE);
    exit();
}

if ($scope === 'pair' && $fromModel === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Alias pary wymaga modelu źródłowego.'], JSON_UNESCAPED_UNICODE);
    exit();
}

/* Cel musi istnieć w słowniku — inaczej zapisalibyśmy alias donikąd. */
$stmt = $conn->prepare("SELECT COUNT(*) FROM wheel_dict WHERE brand_norm = ? AND model_norm = ?");
$stmt->bind_param('ss', $toBrand, $toModel);
$stmt->execute();
$istnieje = (int) ($stmt->get_result()->fetch_row()[0] ?? 0);
$stmt->close();

if ($istnieje === 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Wskazanej felgi nie ma w słowniku sklepu.'], JSON_UNESCAPED_UNICODE);
    exit();
}

/* Alias marki zapisujemy z pustym modelem — obejmuje wtedy wszystkie modele. */
$aliasModelOd = $scope === 'brand' ? '' : $fromModel;
$aliasModelDo = $scope === 'brand' ? '' : $toModel;
$notatka      = 'Z listy roboczej w panelu';

$stmt = $conn->prepare(
    "INSERT INTO wheel_alias (from_brand, from_model, to_brand, to_model, note, created_at)
     VALUES (?, ?, ?, ?, ?, NOW())
     ON DUPLICATE KEY UPDATE to_brand = VALUES(to_brand), to_model = VALUES(to_model), note = VALUES(note)"
);
$stmt->bind_param('sssss', $fromBrand, $aliasModelOd, $toBrand, $aliasModelDo, $notatka);

if (!$stmt->execute()) {
    http_response_code(500);
    echo json_encode(['error' => 'Nie udało się zapisać: ' . $stmt->error], JSON_UNESCAPED_UNICODE);
    exit();
}
$stmt->close();

/* Przeliczenie kluczy w istniejących wpisach — od razu, żeby efekt był widoczny. */
if ($scope === 'brand') {
    $stmt = $conn->prepare("UPDATE wheel SET brand_norm = ? WHERE brand_norm = ?");
    $stmt->bind_param('ss', $toBrand, $fromBrand);
} else {
    $stmt = $conn->prepare(
        "UPDATE wheel SET brand_norm = ?, model_norm = ? WHERE brand_norm = ? AND model_norm = ?"
    );
    $stmt->bind_param('ssss', $toBrand, $toModel, $fromBrand, $fromModel);
}

$stmt->execute();
$zmienioneFelgi = $stmt->affected_rows;
$stmt->close();

/* Ile aut na tym zyskało. */
$stmt = $conn->prepare(
    "SELECT COUNT(DISTINCT p.id) FROM project p JOIN wheel w ON p.wheel_id = w.id
     WHERE w.brand_norm = ? AND w.model_norm = ?"
);
$stmt->bind_param('ss', $toBrand, $toModel);
$stmt->execute();
$auta = (int) ($stmt->get_result()->fetch_row()[0] ?? 0);
$stmt->close();

echo json_encode([
    'ok'             => true,
    'scope'          => $scope,
    'wheels_updated' => $zmienioneFelgi,
    'cars_on_target' => $auta,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

$conn->close();
