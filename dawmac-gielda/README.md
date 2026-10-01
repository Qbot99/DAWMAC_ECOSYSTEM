# DAWMAC Giełda — bezpłatna giełda felg

Ludzie wystawiają ogłoszenia **„Sprzedam”** (ze swoimi zdjęciami) i **„Kupię”**
(„szukam felg do auta X”), piszą do siebie wiadomości, a pracownicy DAWMAC
moderują zgłoszenia. DAWMAC jest w tle: nic nie pobiera, nie jest stroną transakcji.

Dopasowania „Kupię” ↔ „Sprzedam”, powiadomienia o nich i polecenia AI
(realizacje z galerii, felgi ze sklepu) dojdą w kolejnym etapie — baza jest
na to przygotowana (osobne kolumny na auto, średnicę, rozstaw, ET; tabela
`g_notifications` z typem `match`; zgody `notify_matches` i `marketing_consent`).

| Część | Co | Stack |
|---|---|---|
| `api/` | REST API (konta, ogłoszenia, zdjęcia, wiadomości, zgłoszenia, panel pracownika) | PHP 8.1+, MySQL/MariaDB, bez Composera |
| `web/` | Strona/aplikacja dla użytkowników i panel pracownika, instalowalna na telefonie (PWA) | React, TypeScript, Vite |
| `sql/schema.sql` | Tabele `g_*` | MySQL 8 / MariaDB 10.6+ |
| `tools/` | `nadaj_role.php` (pracownicy), `cron.php` (wygasanie, przypomnienia, retencja) | PHP CLI |
| `tests/` | Pełny test API na żywej bazie (`run.sh`) | PHP |

## Co już działa

- **Konta**: rejestracja z akceptacją regulaminu (zapisana wersja i data), potwierdzenie e-maila, logowanie, reset hasła, osoba prywatna / firma (NIP), zgoda marketingowa osobno i domyślnie wyłączona, eksport danych i usunięcie konta.
- **Ogłoszenia**: „Sprzedam” (wymagane zdjęcie, średnica, rozstaw, ET, stan, cena lub „do negocjacji”) i „Kupię”. Auto ze słownika galerii (`car_brand`, `car_model`). Do 8 zdjęć, okładka, edycja, sprzedane/zakończone/wznów, ważność 60 dni, obserwowane, profil sprzedającego.
- **Zdjęcia**: zmniejszane w przeglądarce, na serwerze zapisywane od nowa jako WebP (1600 px + miniatura 480 px), **bez EXIF/GPS**, z poprawioną orientacją z telefonu.
- **Wyszukiwanie**: typ, marka/model auta, średnica, rozstaw, województwo, stan, cena, firma/prywatny, z oponami, tekst; sortowanie po dacie lub cenie.
- **Kontakt**: wiadomości w giełdzie (e-mail o nowej wiadomości, bez spamu), telefon tylko po kliknięciu „Pokaż numer” i tylko gdy autor na to pozwolił, e-mail nigdy nie jest pokazywany.
- **Zgłoś** (DSA art. 16): przy ogłoszeniu, profilu i rozmowie, także bez konta; potwierdzenie i decyzja e-mailem.
- **Panel pracownika** (`/panel`, role moderator/admin): zgłoszenia, ogłoszenia, użytkownicy, log decyzji. Każde usunięcie/blokada wymaga uzasadnienia, które użytkownik dostaje razem z informacją, że zdecydował człowiek, i jak się odwołać (DSA art. 17).
- **Strony prawne**: projekt regulaminu, polityki prywatności, porady bezpieczeństwa i kontakt (punkt kontaktowy DSA art. 11–12) — **do sprawdzenia przez prawnika**, treść w `web/src/pages/Legal.tsx`.

## Uruchomienie lokalne

```bash
# baza (MariaDB/MySQL)
mysql -u root -e "CREATE DATABASE gielda CHARACTER SET utf8mb4; CREATE USER 'gielda'@'localhost' IDENTIFIED BY 'gielda'; GRANT ALL ON gielda.* TO 'gielda'@'localhost';"
mysql -u gielda -pgielda gielda < sql/schema.sql
mysql -u gielda -pgielda gielda < tests/seed_cars.sql      # mini-słownik aut, tylko lokalnie

# API (konfiguracja z tests/test.env: e-maile lądują w storage/mail.log)
set -a; . tests/test.env; set +a
GIELDA_ENV_FILE=/dev/null php -S 127.0.0.1:8099 tests/dev-router.php

# front, w drugim terminalu
cd web && npm install && npm run dev                       # http://localhost:5173
```

Konto pracownika: zarejestruj się normalnie, potem `php tools/nadaj_role.php twoj@email admin`.

Testy: `./tests/run.sh` (czyści bazę z `tests/test.env`, 87 sprawdzeń całej ścieżki).

## Wdrożenie na dhosting

Giełda stoi pod `dawmac.pl/gielda`, w podkatalogu sklepu (ma własny `.htaccess`, więc reguły WordPressa jej nie dotyczą):

```
~/dawmac.pl-aid9/
├── gielda-app/          ← poza public_html: .env, tools/, sql/
└── public_html/
    └── gielda/
        ├── (zawartość web/dist, razem z .htaccess)
        ├── api/         ← katalog api/ (index.php, .htaccess, lib/, routes/)
        └── uploads/     ← zdjęcia, zapisywalne przez PHP
```

1. Baza: `sql/schema.sql` w nowej bazie. Słownik aut z galerii: `CARS_DB_NAME` = baza galerii, jeśli użytkownik giełdy ma do niej odczyt; jeśli nie (na dhostingu użytkownik bazy widzi tylko swoją bazę, a `open_basedir` nie wpuszcza PHP strony do plików galerii) — `CARS_DB_NAME` puste i `GALLERY_ENV_FILE` = ścieżka do `.env` galerii: `tools/cron.php` kopiuje wtedy co dobę `car_brand` i `car_model` z galerii do bazy giełdy (te same id). Pierwszą kopię zrób od razu, uruchamiając cron ręcznie.
2. `.env` z `.env.example` w `gielda-app/`: baza, `APP_URL=https://dawmac.pl/gielda`, `UPLOAD_DIR`, `UPLOAD_URL=/gielda/uploads`, dane firmy (`OPERATOR_INFO`) i e-maile. Narzędzia z `gielda-app/tools/` znajdą go same (`gielda-app/api/` to kopia `api/`); API w `public_html` dostaje ścieżkę przez `SetEnv GIELDA_ENV_FILE /…/gielda-app/.env` na początku `public_html/gielda/api/.htaccess` (LiteSpeed na dhostingu to obsługuje). Nie kładź `.env` w `public_html/gielda/`.
3. Front: `cd web && npm run build -- --base=/gielda/`, wgrać `web/dist/*` do `public_html/gielda/`.
4. API: wgrać `api/` do `public_html/gielda/api/`.
5. Cron w dPanelu, raz dziennie: `php83 ~/…/tools/cron.php` (domyślne `php` w konsoli dhostingu to 5.4).
6. Konto admina: rejestracja + `php83 tools/nadaj_role.php EMAIL admin`.
7. W Cloudflare nie cachować `/gielda/api/*` (domyślnie JSON nie jest cachowany, ale reguła „Cache Everything” by to zepsuła).

PHP potrzebuje rozszerzeń `pdo_mysql`, `gd` z WebP, `mbstring` (opcjonalnie `exif` — do obracania zdjęć z telefonu).

## Przed startem (z analizy prawnej)

- Prawnik sprawdza regulamin i politykę prywatności, uzupełnić dane firmy i okres licencji na zdjęcia („[X] miesięcy”).
- Umowy powierzenia (hosting, poczta).
- Instrukcja dla moderatorów: decyzje tylko na podstawie regulaminu i prawa.
