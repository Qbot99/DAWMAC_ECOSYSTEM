<?php
/**
 * Oznacza felgę jako "sklep tego nie prowadzi".
 *
 * To decyzja, nie poprawka. Wpis w galerii jest prawidłowy — po prostu nie ma
 * produktu, na którego karcie zdjęcia mogłyby się pokazać. Znika z listy
 * roboczej, ale zostaje w galerii i dalej jest widoczny dla klientów.
 *
 * POST: brand, model   (dowolny zapis, normalizujemy tutaj)
 *       undo=1         — cofnięcie, gdy model wróci do oferty
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

$brand = dawmac_wheel_norm($_POST['brand'] ?? '');
$model = dawmac_wheel_norm($_POST['model'] ?? '');
$undo  = !empty($_POST['undo']);

if ($brand === '' || $model === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Brakuje marki lub modelu.'], JSON_UNESCAPED_UNICODE);
    exit();
}

if ($undo) {
    $stmt = $conn->prepare("DELETE FROM wheel_ignored WHERE brand_norm = ? AND model_norm = ?");
    $stmt->bind_param('ss', $brand, $model);
    $stmt->execute();
    $ile = $stmt->affected_rows;
    $stmt->close();

    echo json_encode(['ok' => true, 'undone' => $ile], JSON_UNESCAPED_UNICODE);
    $conn->close();
    exit();
}

$notatka = trim((string) ($_POST['note'] ?? 'Sklep nie prowadzi tego modelu'));

$stmt = $conn->prepare(
    "INSERT INTO wheel_ignored (brand_norm, model_norm, note, created_at)
     VALUES (?, ?, ?, NOW())
     ON DUPLICATE KEY UPDATE note = VALUES(note)"
);
$stmt->bind_param('sss', $brand, $model, $notatka);

if (!$stmt->execute()) {
    http_response_code(500);
    echo json_encode(['error' => 'Nie udało się zapisać: ' . $stmt->error], JSON_UNESCAPED_UNICODE);
    exit();
}
$stmt->close();

/* Ile aut dotyczy tej decyzji — do komunikatu w panelu. */
$stmt = $conn->prepare(
    "SELECT COUNT(DISTINCT p.id) FROM project p JOIN wheel w ON p.wheel_id = w.id
     WHERE w.brand_norm = ? AND w.model_norm = ?"
);
$stmt->bind_param('ss', $brand, $model);
$stmt->execute();
$auta = (int) ($stmt->get_result()->fetch_row()[0] ?? 0);
$stmt->close();

echo json_encode(['ok' => true, 'cars' => $auta], JSON_UNESCAPED_UNICODE);
$conn->close();
