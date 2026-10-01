-- DAWMAC Giełda — schemat bazy.
--
-- Wszystkie tabele mają prefiks g_, więc giełda może stać w osobnej bazie
-- albo obok tabel galerii. Słownik aut (car_brand, car_model) NIE jest tu
-- kopiowany — czytamy go z bazy galerii (CARS_DB_NAME w .env). W ogłoszeniu
-- zapisujemy id z tego słownika ORAZ nazwy w chwili dodania, żeby ogłoszenie
-- nie „zgubiło” auta, gdy ktoś poprawi słownik.
--
-- Uruchomienie: mysql -u USER -p BAZA < sql/schema.sql  (można puszczać wiele razy)

SET NAMES utf8mb4;

-- Użytkownicy giełdy i pracownicy (rola). Pracownik to zwykłe konto
-- z rolą moderator/admin — nadaje się ją narzędziem tools/nadaj_role.php.
CREATE TABLE IF NOT EXISTS g_users (
    id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email              VARCHAR(190) NOT NULL,
    password_hash      VARCHAR(255) NOT NULL,
    display_name       VARCHAR(60)  NOT NULL,
    phone              VARCHAR(30)  NULL,
    city               VARCHAR(80)  NULL,
    seller_type        ENUM('private','company') NOT NULL DEFAULT 'private',
    company_name       VARCHAR(160) NULL,
    company_nip        VARCHAR(20)  NULL,
    role               ENUM('user','moderator','admin') NOT NULL DEFAULT 'user',
    status             ENUM('active','banned','deleted') NOT NULL DEFAULT 'active',
    ban_reason         TEXT NULL,
    email_verified_at  DATETIME NULL,
    -- Regulamin: którą wersję zaakceptował i kiedy (dowód zawarcia umowy).
    terms_version      VARCHAR(20) NOT NULL,
    terms_accepted_at  DATETIME NOT NULL,
    -- Zgoda marketingowa DAWMAC — osobna, domyślnie wyłączona (PKE art. 398).
    marketing_consent      TINYINT(1) NOT NULL DEFAULT 0,
    marketing_consent_at   DATETIME NULL,
    -- Powiadomienia o dopasowaniach — część usługi, do wyłączenia.
    notify_matches     TINYINT(1) NOT NULL DEFAULT 1,
    notify_messages    TINYINT(1) NOT NULL DEFAULT 1,
    created_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_login_at      DATETIME NULL,
    UNIQUE KEY uq_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Jednorazowe tokeny: potwierdzenie e-maila i reset hasła.
-- Trzymamy tylko skrót tokenu, nie sam token.
CREATE TABLE IF NOT EXISTS g_tokens (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED NOT NULL,
    purpose     ENUM('verify','reset') NOT NULL,
    token_hash  CHAR(64) NOT NULL,
    expires_at  DATETIME NOT NULL,
    used_at     DATETIME NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_hash (token_hash),
    KEY idx_user (user_id),
    CONSTRAINT fk_tokens_user FOREIGN KEY (user_id) REFERENCES g_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ogłoszenia „sprzedam” i „kupię”.
-- Pola techniczne (średnica, rozstaw, ET...) są osobnymi kolumnami, nie
-- tekstem w opisie — na nich później oprze się dopasowanie.
CREATE TABLE IF NOT EXISTS g_listings (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         INT UNSIGNED NOT NULL,
    type            ENUM('sell','buy') NOT NULL,
    status          ENUM('active','sold','closed','expired','removed') NOT NULL DEFAULT 'active',
    title           VARCHAR(120) NOT NULL,
    description     TEXT NOT NULL,

    -- Auto: id ze słownika galerii + nazwy z chwili dodania.
    car_brand_id    INT UNSIGNED NULL,
    car_model_id    INT UNSIGNED NULL,
    car_brand_name  VARCHAR(80) NULL,
    car_model_name  VARCHAR(120) NULL,
    car_year_from   SMALLINT UNSIGNED NULL,
    car_year_to     SMALLINT UNSIGNED NULL,

    -- Felga. Przy „kupię” to wymagania (puste = obojętne).
    wheel_brand     VARCHAR(80) NULL,
    wheel_model     VARCHAR(80) NULL,
    diameter        DECIMAL(4,1) NULL,   -- cale, np. 18.0
    width           DECIMAL(4,1) NULL,   -- cale, np. 8.5
    width_rear      DECIMAL(4,1) NULL,   -- przy różnej szerokości tył
    pcd             VARCHAR(20) NULL,    -- rozstaw, np. 5x112
    et              SMALLINT NULL,       -- odsadzenie
    et_rear         SMALLINT NULL,
    center_bore     DECIMAL(4,1) NULL,   -- otwór centralny, mm
    quantity        TINYINT UNSIGNED NULL,
    `condition`     ENUM('new','used','damaged') NULL,
    with_tyres      TINYINT(1) NOT NULL DEFAULT 0,
    tyre_info       VARCHAR(120) NULL,

    price           INT UNSIGNED NULL,   -- zł; przy „kupię” to budżet
    price_negotiable TINYINT(1) NOT NULL DEFAULT 0,
    city            VARCHAR(80) NOT NULL,
    voivodeship     VARCHAR(40) NULL,
    show_phone      TINYINT(1) NOT NULL DEFAULT 0,

    removal_reason  TEXT NULL,
    removed_by      INT UNSIGNED NULL,
    removed_at      DATETIME NULL,

    views           INT UNSIGNED NOT NULL DEFAULT 0,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    expires_at      DATETIME NOT NULL,

    KEY idx_list (status, type, created_at),
    KEY idx_car (car_brand_id, car_model_id),
    KEY idx_size (diameter, pcd),
    KEY idx_user (user_id),
    FULLTEXT KEY ft_text (title, description, wheel_brand, wheel_model, car_brand_name, car_model_name),
    CONSTRAINT fk_listings_user FOREIGN KEY (user_id) REFERENCES g_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS g_listing_images (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    listing_id  INT UNSIGNED NOT NULL,
    file        VARCHAR(120) NOT NULL,   -- nazwa pliku w UPLOAD_DIR/{listing_id}/
    width       SMALLINT UNSIGNED NOT NULL,
    height      SMALLINT UNSIGNED NOT NULL,
    position    TINYINT UNSIGNED NOT NULL DEFAULT 0,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_listing (listing_id, position),
    CONSTRAINT fk_images_listing FOREIGN KEY (listing_id) REFERENCES g_listings(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Obserwowane ogłoszenia.
CREATE TABLE IF NOT EXISTS g_favorites (
    user_id     INT UNSIGNED NOT NULL,
    listing_id  INT UNSIGNED NOT NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, listing_id),
    CONSTRAINT fk_fav_user FOREIGN KEY (user_id) REFERENCES g_users(id) ON DELETE CASCADE,
    CONSTRAINT fk_fav_listing FOREIGN KEY (listing_id) REFERENCES g_listings(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Rozmowy między użytkownikami, zawsze przy konkretnym ogłoszeniu.
-- Jedna rozmowa na parę (ogłoszenie, pytający).
CREATE TABLE IF NOT EXISTS g_conversations (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    listing_id       INT UNSIGNED NOT NULL,
    owner_id         INT UNSIGNED NOT NULL,   -- autor ogłoszenia
    other_id         INT UNSIGNED NOT NULL,   -- osoba, która napisała
    -- Nieprzeczytane = id ostatniej wiadomości > id ostatniej przeczytanej
    -- (po id, nie po czasie — dwie wiadomości w tej samej sekundzie).
    last_message_id  INT UNSIGNED NOT NULL DEFAULT 0,
    owner_read_id    INT UNSIGNED NOT NULL DEFAULT 0,
    other_read_id    INT UNSIGNED NOT NULL DEFAULT 0,
    last_message_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_pair (listing_id, other_id),
    KEY idx_owner (owner_id, last_message_at),
    KEY idx_other (other_id, last_message_at),
    CONSTRAINT fk_conv_listing FOREIGN KEY (listing_id) REFERENCES g_listings(id) ON DELETE CASCADE,
    CONSTRAINT fk_conv_owner FOREIGN KEY (owner_id) REFERENCES g_users(id) ON DELETE CASCADE,
    CONSTRAINT fk_conv_other FOREIGN KEY (other_id) REFERENCES g_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS g_messages (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    conversation_id  INT UNSIGNED NOT NULL,
    sender_id        INT UNSIGNED NOT NULL,
    body             TEXT NOT NULL,
    created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_conv (conversation_id, id),
    CONSTRAINT fk_msg_conv FOREIGN KEY (conversation_id) REFERENCES g_conversations(id) ON DELETE CASCADE,
    CONSTRAINT fk_msg_sender FOREIGN KEY (sender_id) REFERENCES g_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Zgłoszenia treści (DSA art. 16). Zgłosić może każdy, także bez konta —
-- wtedy podaje imię i e-mail. Zgłaszać można ogłoszenie albo użytkownika.
CREATE TABLE IF NOT EXISTS g_reports (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    listing_id       INT UNSIGNED NULL,
    reported_user_id INT UNSIGNED NULL,
    reporter_id      INT UNSIGNED NULL,
    reporter_name    VARCHAR(100) NULL,
    reporter_email   VARCHAR(190) NULL,
    reason           ENUM('stolen','counterfeit','hidden_damage','foreign_photos','scam','personal_data','offensive','other') NOT NULL,
    details          TEXT NOT NULL,
    good_faith       TINYINT(1) NOT NULL DEFAULT 0,
    status           ENUM('open','resolved','dismissed') NOT NULL DEFAULT 'open',
    resolution_note  TEXT NULL,
    handled_by       INT UNSIGNED NULL,
    handled_at       DATETIME NULL,
    created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_status (status, created_at),
    CONSTRAINT fk_rep_listing FOREIGN KEY (listing_id) REFERENCES g_listings(id) ON DELETE SET NULL,
    CONSTRAINT fk_rep_user FOREIGN KEY (reported_user_id) REFERENCES g_users(id) ON DELETE SET NULL,
    CONSTRAINT fk_rep_reporter FOREIGN KEY (reporter_id) REFERENCES g_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Powiadomienia w aplikacji. Dziś: nowa wiadomość, decyzja moderacji,
-- wygasające ogłoszenie. Później: dopasowania (type='match') —
-- payload trzyma id drugiego ogłoszenia.
CREATE TABLE IF NOT EXISTS g_notifications (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED NOT NULL,
    type        VARCHAR(30) NOT NULL,
    title       VARCHAR(160) NOT NULL,
    body        TEXT NULL,
    link        VARCHAR(255) NULL,
    payload     JSON NULL,
    read_at     DATETIME NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_user (user_id, read_at, id),
    CONSTRAINT fk_notif_user FOREIGN KEY (user_id) REFERENCES g_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ślad decyzji pracowników (kto, co, kiedy, dlaczego). Dowód do
-- uzasadnień z art. 17 DSA. Bez kluczy obcych: wpis ma przetrwać
-- usunięcie ogłoszenia lub konta.
CREATE TABLE IF NOT EXISTS g_mod_log (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    staff_id     INT UNSIGNED NOT NULL,
    action       VARCHAR(40) NOT NULL,
    target_type  ENUM('listing','user','report') NOT NULL,
    target_id    INT UNSIGNED NOT NULL,
    reason       TEXT NULL,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_target (target_type, target_id),
    KEY idx_staff (staff_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Prosty limiter (logowanie, rejestracja, wiadomości, zgłoszenia).
CREATE TABLE IF NOT EXISTS g_rate_limit (
    bucket      VARCHAR(120) NOT NULL,
    hits        INT UNSIGNED NOT NULL,
    window_end  DATETIME NOT NULL,
    PRIMARY KEY (bucket)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
