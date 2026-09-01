<?php
/**
 * Lista felg, dla których w ogóle są zdjęcia w galerii.
 *
 * Sklep ma 32 630 produktów, a felg ze zdjęciami jest kilkaset. Bez tego
 * indeksu każda karta produktu — także te, które nigdy zdjęć nie dostaną —
 * strzelałaby po HTTP do galerii przy każdym wygaśnięciu cache.
 *
 * Wtyczka pobiera tę listę RAZ i dopiero gdy felga na niej jest, pyta
 * o konkretne auta. Odpowiedź jest mała (kilkaset krótkich kluczy),
 * więc trzymanie jej w cache sklepu nic nie kosztuje.
 */

require 'db.php';

$res = $conn->query(
    "SELECT DISTINCT CONCAT(w.brand_norm, '|', w.model_norm) AS klucz
     FROM wheel w
     JOIN project p ON p.wheel_id = w.id
     LEFT JOIN project_images i ON i.project_id = p.id
     WHERE w.brand_norm <> '' AND w.model_norm <> ''
       AND p.show_in_store = 1
       AND i.id IS NOT NULL"
);

$klucze = [];

while ($r = $res->fetch_row()) {
    $klucze[] = $r[0];
}

echo json_encode(
    ['count' => count($klucze), 'wheels' => $klucze],
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
);

$conn->close();
