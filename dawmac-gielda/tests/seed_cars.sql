-- Tylko do testów lokalnych: mini-słownik aut w tym samym kształcie co
-- car_brand / car_model w bazie galerii. Na produkcji giełda czyta
-- prawdziwy słownik (CARS_DB_NAME).
CREATE TABLE IF NOT EXISTS car_brand (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, name VARCHAR(80) NOT NULL);
CREATE TABLE IF NOT EXISTS car_model (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, car_brand_id INT UNSIGNED NOT NULL, name VARCHAR(120) NOT NULL);
INSERT IGNORE INTO car_brand (id, name) VALUES (1,'Audi'),(2,'BMW'),(3,'Mercedes-Benz'),(4,'Volkswagen'),(5,'Skoda');
INSERT IGNORE INTO car_model (id, car_brand_id, name) VALUES
 (1,1,'A3'),(2,1,'A4'),(3,1,'A6'),(4,1,'Q5'),
 (5,2,'Seria 3'),(6,2,'Seria 5'),(7,2,'X5'),
 (8,3,'C-Klasa'),(9,3,'E-Klasa'),
 (10,4,'Golf'),(11,4,'Passat'),
 (12,5,'Octavia'),(13,5,'Superb');
