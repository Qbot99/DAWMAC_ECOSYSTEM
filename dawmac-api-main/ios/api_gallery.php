<?php
// Plik: api_gallery.php
require 'db_config.php';
// Ścieżka działa tak samo na serwerze (public_html/ios/) jak i w repo.
require_once __DIR__ . '/../api/gallery/lib/wheel_norm.php';

header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? '';

// ==========================================================
// 0. UPDATE ORDER (Ustawianie GŁÓWNEGO zdjęcia - is_primary)
// ==========================================================
if ($action === 'update_order') {
    $project_id = $_POST['project_id'] ?? 0;
    $order_string = $_POST['new_order'] ?? ''; // Np. "img_1.jpg,img_2.jpg"
    
    // Rozbijamy string na tablicę. Pierwszy element to zdjęcie główne.
    $images = explode(',', $order_string);
    
    if ($project_id && count($images) > 0) {
        $main_image = trim($images[0]); // Pierwsze zdjęcie z listy
        
        try {
            $pdo_gallery->beginTransaction();

            // KROK 1: Resetujemy flagę is_primary dla wszystkich zdjęć tego projektu
            $stmtReset = $pdo_gallery->prepare("UPDATE project_images SET is_primary = 0 WHERE project_id = ?");
            $stmtReset->execute([$project_id]);

            // KROK 2: Ustawiamy is_primary = 1 tylko dla pierwszego zdjęcia z listy
            // Używamy LIKE, bo aplikacja może przysłać samą nazwę pliku lub ścieżkę
            $stmtSet = $pdo_gallery->prepare("UPDATE project_images SET is_primary = 1 WHERE project_id = ? AND image_url LIKE ?");
            $stmtSet->execute([$project_id, "%" . $main_image]);

            $pdo_gallery->commit();
            echo json_encode(["status" => "success"]);
        } catch (Exception $e) {
            $pdo_gallery->rollBack();
            echo json_encode(["status" => "error", "message" => $e->getMessage()]);
        }
    } else {
        echo json_encode(["status" => "error", "message" => "Brak danych"]);
    }
    exit;
}

// ==========================================================
// 1. GET DATA (Pobranie marek i modeli do formularza)
// ==========================================================
if ($action === 'get_data') {
    try {
        $brands = $pdo_gallery->query("SELECT id, name FROM car_brand ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
        $models = $pdo_gallery->query("SELECT id, name, car_brand_id FROM car_model ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(["brands" => $brands, "models" => $models]);
    } catch (Exception $e) {
        echo json_encode(["status" => "error", "message" => $e->getMessage()]);
    }
    exit;
}

// ==========================================================
// 2. LISTA (Pobieranie projektów)
// ==========================================================
if ($action === 'list') {
    try {
        // ZMIANA: Sortujemy po 'pi.is_primary DESC'. 
        // Dzięki temu zdjęcie z is_primary=1 "wskoczy" jako pierwsze w wynikach dla danego projektu.
        // Aplikacja iOS weźmie pierwszy wynik jako miniaturkę.
        $sql = "SELECT p.id as project_id, cb.name as brand_name, cm.name as model_name, 
                       w.brand as wheel_brand, w.model as wheel_model, w.params as wheel_params, 
                       pi.image_url
                FROM project p
                LEFT JOIN car_brand cb ON p.car_brand_id = cb.id
                LEFT JOIN car_model cm ON p.car_model_id = cm.id
                LEFT JOIN wheel w ON p.wheel_id = w.id
                LEFT JOIN project_images pi ON p.id = pi.project_id
                ORDER BY p.id DESC, pi.is_primary DESC"; 
        
        $stmt = $pdo_gallery->query($sql);
        $projects = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(["status" => "success", "data" => $projects]);
    } catch (Exception $e) {
        echo json_encode(["status" => "error", "message" => $e->getMessage()]);
    }
    exit;
}

// ==========================================================
// 3. DELETE PROJECT
// ==========================================================
if ($action === 'delete') {
    $id = $_GET['id'] ?? 0;
    try {
        $pdo_gallery->beginTransaction();
        $pdo_gallery->prepare("DELETE FROM project_images WHERE project_id = ?")->execute([$id]);
        $pdo_gallery->prepare("DELETE FROM project WHERE id = ?")->execute([$id]);
        $pdo_gallery->commit();
        echo json_encode(["status" => "success"]);
    } catch (Exception $e) {
        $pdo_gallery->rollBack();
        echo json_encode(["status" => "error", "message" => $e->getMessage()]);
    }
    exit;
}

// ==========================================================
// 4. DELETE IMAGE
// ==========================================================
if ($action === 'delete_image') {
    $url = $_POST['image_url'] ?? '';
    try {
        $pdo_gallery->prepare("DELETE FROM project_images WHERE image_url = ?")->execute([$url]);
        echo json_encode(["status" => "success"]);
    } catch (Exception $e) {
        echo json_encode(["status" => "error", "message" => $e->getMessage()]);
    }
    exit;
}

// ==========================================================
// 5. POST (UPLOAD & UPDATE - Edycja i Dodawanie)
// ==========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $project_id = $_POST['project_id'] ?? null;
    $brand_id = $_POST['car_brand_id'] ?? null;
    $model_id = $_POST['car_model_id'] ?? null;
    $w_brand = $_POST['wheel_brand'] ?? '';
    $w_model = $_POST['wheel_model'] ?? '';
    $w_params = $_POST['wheel_params'] ?? '';

    try {
        // --- A. ZAPIS DANYCH TEKSTOWYCH ---
        if (!$project_id) {
            // INSERT: Tworzymy nowy wpis
            $stmtW = $pdo_gallery->prepare("INSERT INTO wheel (brand, model, params, brand_norm, model_norm) VALUES (?, ?, ?, ?, ?)");
            $stmtW->execute([$w_brand, $w_model, $w_params, dawmac_wheel_norm($w_brand), dawmac_wheel_norm($w_model)]);
            $wheel_id = $pdo_gallery->lastInsertId();

            $stmtP = $pdo_gallery->prepare("INSERT INTO project (car_brand_id, car_model_id, wheel_id) VALUES (?, ?, ?)");
            $stmtP->execute([$brand_id, $model_id, $wheel_id]);
            $project_id = $pdo_gallery->lastInsertId();
        } else {
            // UPDATE: Edycja istniejącego wpisu
            
            // 1. Aktualizacja danych felg
            $stmtU = $pdo_gallery->prepare("UPDATE wheel w JOIN project p ON p.wheel_id = w.id SET w.brand=?, w.model=?, w.params=?, w.brand_norm=?, w.model_norm=? WHERE p.id=?");
            $stmtU->execute([$w_brand, $w_model, $w_params, dawmac_wheel_norm($w_brand), dawmac_wheel_norm($w_model), $project_id]);
            
            // 2. Aktualizacja marki i modelu auta (jeśli zostały przesłane)
            if ($brand_id && $model_id) {
                $stmtCar = $pdo_gallery->prepare("UPDATE project SET car_brand_id=?, car_model_id=? WHERE id=?");
                $stmtCar->execute([$brand_id, $model_id, $project_id]);
            }
        }

        // --- B. OBSŁUGA PLIKU (ZDJĘCIA) ---
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = '../gallery/images/';
            if (!file_exists($uploadDir)) mkdir($uploadDir, 0755, true);

            // Unikalna nazwa pliku
            $extension = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION) ?: 'jpg';
            $fileName = uniqid('img_', true) . '.' . $extension;
            
            $targetPath = $uploadDir . $fileName;
            $dbPath = 'images/' . $fileName;

            if (move_uploaded_file($_FILES['photo']['tmp_name'], $targetPath)) {
                // Przy dodawaniu nowego zdjęcia is_primary jest domyślnie 0 (chyba że baza ma inaczej ustawione default)
                // Nie musimy tu nic zmieniać
                $stmtImg = $pdo_gallery->prepare("INSERT INTO project_images (project_id, image_url) VALUES (?, ?)");
                $stmtImg->execute([$project_id, $dbPath]);
            }
        }
        
        echo json_encode(["status" => "success", "project_id" => $project_id]);

    } catch (Exception $e) {
        echo json_encode(["status" => "error", "message" => $e->getMessage()]);
    }
}
?>