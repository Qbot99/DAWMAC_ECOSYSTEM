<?php
// Plik: api_forged.php
require 'db_config.php';

header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? '';

// ==========================================================
// 0. UPDATE ORDER (Ustawianie GŁÓWNEGO zdjęcia - is_primary)
// ==========================================================
if ($action === 'update_order') {
    $wheel_id = $_POST['id'] ?? 0;
    $order_string = $_POST['new_order'] ?? ''; // Np. "img_1.jpg,img_2.jpg"
    
    // Rozbijamy string. Pierwsze zdjęcie z listy ma zostać głównym.
    $images = explode(',', $order_string);
    
    if ($wheel_id && count($images) > 0) {
        $main_image = trim($images[0]); 
        
        try {
            $pdo_forged->beginTransaction();

            // KROK 1: Resetujemy flagę is_primary dla wszystkich zdjęć tej felgi
            // Tabela nazywa się 'image', a klucz obcy to 'wheel_id'
            $stmtReset = $pdo_forged->prepare("UPDATE image SET is_primary = 0 WHERE wheel_id = ?");
            $stmtReset->execute([$wheel_id]);

            // KROK 2: Ustawiamy is_primary = 1 dla pierwszego zdjęcia z listy
            // Uwaga: W bazie felg kolumna ze ścieżką nazywa się 'url'
            $stmtSet = $pdo_forged->prepare("UPDATE image SET is_primary = 1 WHERE wheel_id = ? AND url LIKE ?");
            $stmtSet->execute([$wheel_id, "%" . $main_image]);

            $pdo_forged->commit();
            echo json_encode(["status" => "success"]);
        } catch (Exception $e) {
            $pdo_forged->rollBack();
            echo json_encode(["status" => "error", "message" => $e->getMessage()]);
        }
    } else {
        echo json_encode(["status" => "error", "message" => "Brak danych"]);
    }
    exit;
}

// ==========================================================
// 1. LISTA
// ==========================================================
if ($action === 'list') {
    try {
        // ZMIANA: Dodano sortowanie po 'i.is_primary DESC'
        // Dzięki temu zdjęcie główne (1) jest zwracane jako pierwsze i aplikacja używa go jako miniaturki.
        $sql = "SELECT w.id, w.name, w.series_id, w.description, i.url as image_url 
                FROM wheel w 
                LEFT JOIN image i ON w.id = i.wheel_id 
                ORDER BY w.id DESC, i.is_primary DESC";
        
        $stmt = $pdo_forged->query($sql);
        echo json_encode(["status" => "success", "data" => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    } catch (Exception $e) {
        echo json_encode(["status" => "error", "message" => $e->getMessage()]);
    }
    exit;
}

// ==========================================================
// 2. DELETE WHEEL
// ==========================================================
if ($action === 'delete') {
    $id = $_GET['id'] ?? 0;
    try {
        $pdo_forged->beginTransaction();
        // Usuwamy zdjęcia i samą felgę
        $pdo_forged->prepare("DELETE FROM image WHERE wheel_id = ?")->execute([$id]);
        $pdo_forged->prepare("DELETE FROM wheel WHERE id = ?")->execute([$id]);
        $pdo_forged->commit();
        echo json_encode(["status" => "success"]);
    } catch (Exception $e) {
        $pdo_forged->rollBack();
        echo json_encode(["status" => "error", "message" => $e->getMessage()]);
    }
    exit;
}

// ==========================================================
// 3. DELETE IMAGE
// ==========================================================
if ($action === 'delete_image') {
    $url = $_POST['image_url'] ?? '';
    try {
        // W bazie felg tabela to 'image', a kolumna to 'url'
        $pdo_forged->prepare("DELETE FROM image WHERE url = ?")->execute([$url]);
        echo json_encode(["status" => "success"]);
    } catch (Exception $e) {
        echo json_encode(["status" => "error", "message" => $e->getMessage()]);
    }
    exit;
}

// ==========================================================
// 4. POST (UPLOAD & UPDATE)
// ==========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? null;
    $name = $_POST['name'] ?? '';
    $series_id = $_POST['series_id'] ?? 1;
    $description = $_POST['description'] ?? '';
    $min_weight = $_POST['min_weight'] ?? '';

    try {
        $wheel_id = 0;
        if ($id) {
            // UPDATE
            $pdo_forged->prepare("UPDATE wheel SET name=?, series_id=?, description=?, min_weight=? WHERE id=?")->execute([$name, $series_id, $description, $min_weight, $id]);
            $wheel_id = $id;
        } else {
            // INSERT
            $pdo_forged->prepare("INSERT INTO wheel (name, series_id, description, min_weight) VALUES (?, ?, ?, ?)")->execute([$name, $series_id, $description, $min_weight]);
            $wheel_id = $pdo_forged->lastInsertId();
        }

        // Obsługa uploadu zdjęcia
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = '../forged/wheels_images/';
            if (!file_exists($uploadDir)) mkdir($uploadDir, 0755, true);

            $extension = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION) ?: 'jpg';
            $fileName = uniqid('img_', true) . '.' . $extension;
            
            $targetPath = $uploadDir . $fileName;
            $dbPath = 'wheels_images/' . $fileName;

            if (move_uploaded_file($_FILES['photo']['tmp_name'], $targetPath)) {
                // Dodajemy nowe zdjęcie. Domyślnie is_primary = 0, więc nie psuje obecnego układu.
                $pdo_forged->prepare("INSERT INTO image (url, wheel_id) VALUES (?, ?)")->execute([$dbPath, $wheel_id]);
            }
        }
        echo json_encode(["status" => "success", "id" => $wheel_id]);

    } catch (Exception $e) {
        echo json_encode(["status" => "error", "message" => $e->getMessage()]);
    }
}
?>