<?php
require 'db.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['gallery_id'])) {
        $gallery_id = $_POST['gallery_id'];
        echo "Odebrane gallery_id: " . htmlspecialchars($gallery_id);
        
        $conn->begin_transaction(); // Rozpoczęcie transakcji
        $response = [];

        // Usuwanie zdjęć z `project_images`
        $stmt = $conn->prepare("DELETE FROM project_images WHERE project_id = ?");
        if ($stmt) {
            $stmt->bind_param("i", $gallery_id);
            if (!$stmt->execute()) {
                $response["errors"][] = "Błąd usuwania zdjęć: " . $stmt->error;
                $conn->rollback();
                exit(json_encode($response));
            }
            $stmt->close();
        } else {
            $response["errors"][] = "Błąd zapytania SQL (project_images): " . $conn->error;
            $conn->rollback();
            exit(json_encode($response));
        }

        // Pobranie `wheel_id` powiązanego z projektem
        $stmt = $conn->prepare("SELECT wheel_id FROM project WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param("i", $gallery_id);
            $stmt->execute();
            $stmt->bind_result($wheel_id);
            $stmt->fetch();
            $stmt->close();
        } else {
            $response["errors"][] = "Błąd zapytania SQL (pobieranie wheel_id): " . $conn->error;
            $conn->rollback();
            exit(json_encode($response));
        }

        // Usuwanie projektu z `project`
        $stmt = $conn->prepare("DELETE FROM project WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param("i", $gallery_id);
            if (!$stmt->execute()) {
                $response["errors"][] = "Błąd usuwania projektu: " . $stmt->error;
                $conn->rollback();
                exit(json_encode($response));
            }
            $stmt->close();
        } else {
            $response["errors"][] = "Błąd zapytania SQL (project): " . $conn->error;
            $conn->rollback();
            exit(json_encode($response));
        }

        // Usuwanie powiązanego rekordu `wheel`, jeśli istnieje
        if (!empty($wheel_id)) {
            $stmt = $conn->prepare("DELETE FROM wheel WHERE id = ?");
            if ($stmt) {
                $stmt->bind_param("i", $wheel_id);
                if (!$stmt->execute()) {
                    $response["errors"][] = "Błąd usuwania wheel: " . $stmt->error;
                    $conn->rollback();
                    exit(json_encode($response));
                }
                $stmt->close();
            } else {
                $response["errors"][] = "Błąd zapytania SQL (wheel): " . $conn->error;
                $conn->rollback();
                exit(json_encode($response));
            }
        }
        
        $conn->commit(); // Zatwierdzenie transakcji
    } else {
        echo json_encode(["error" => "Brak gallery_id w przesłanych danych."]);
    }
} else {
    echo json_encode(["error" => "Nieprawidłowa metoda żądania. Oczekiwano POST."]);
}

$conn->close();

    // Przekierowanie lub odpowiedź JSON
    if (empty($response["errors"])) {
        header('Location: /adminPanel');
    } else {
        echo json_encode($response);
    }
?>