# Dawmac Allegro

Wtyczka WordPress/WooCommerce łącząca katalog [dawmac.pl](https://dawmac.pl)
z Allegro: szablon firmowy opisów, wystawianie ofert, synchronizacja cen
i stanów, obsługa zamówień.

## Dlaczego własna wtyczka, a nie BaseLinker

Katalog ma ~32 tys. produktów i własny indeks (`dawmac-filters`), z którego
wyciągnięcie listy „co jest na magazynie" to jedno zapytanie. Szablon opisu
ma być spójny z marką, a nie ograniczony edytorem pośrednika. Zamówienia
i logistyka to osobna decyzja — do podjęcia, kiedy pierwsze oferty będą żyć.

## Stan

| Element | Status |
|---|---|
| Szablon firmowy (structured description) | gotowe |
| Sanitizer treści pod wymagania Allegro | gotowe, 44 testy |
| Podgląd szablonu bez sklepu i bez API | gotowe |
| Mapowanie produktu Woo → dane szablonu | gotowe |
| OAuth2 + klient HTTP | gotowe, nieprzetestowane na żywym API |
| Panel: połączenie konta | gotowe |
| Wgrywanie grafik szablonu do Allegro | do zrobienia |
| Mapowanie kategorii i parametrów Allegro | do zrobienia |
| Wystawianie i aktualizacja ofert | do zrobienia |
| Synchronizacja cen i stanów | do zrobienia |
| Pobieranie zamówień | do zrobienia |

## Ograniczenia Allegro, na których stoi szablon

Opis oferty **nie jest HTML-em ze sklepu**. To drzewo sekcji:

```
description.sections[]        max 100 sekcji
  .items[]                    1 item (pełna szerokość) albo 2 (dwie kolumny)
    { type: TEXT,  content }  tylko: h1, h2, p, ul, ol, li, b
    { type: IMAGE, url }      wyłącznie URL z serwerów Allegro
```

Poza tym:

* nagłówków `h1`/`h2` **nie wolno dodatkowo formatować** — `<b>` w środku
  to błąd walidacji,
* znaczniki wyłącznie małymi literami,
* brak `<br/>`, `<i>`, `<a>`, tabel, atrybutów `style`/`class`,
* w opisie **nie mogą** znaleźć się linki, adresy e-mail ani numery telefonu —
  Allegro czyta je jako zachętę do zakupu poza serwisem,
* obrazki z zewnętrznych serwerów są odrzucane; trzeba je najpierw wgrać
  przez `POST /sale/images`.

Sanitizer (`includes/class-text.php`) egzekwuje to wszystko automatycznie,
razem z wycinaniem **lokalizacji magazynowych** (`42.L / NHB`), które siedzą
w opisach produktów, bo szuka po nich wyszukiwarka `dawmac-filters` — i nie
mają czego szukać na publicznej ofercie.

## Podgląd i testy

Jedno i drugie działa bez WordPressa, bez bazy i bez połączenia z Allegro:

```bash
php tools/preview.php
```

Buduje `tools/out/preview.html` — opis wyrenderowany tak, jak pokazuje go
Allegro (sekcje dwukolumnowe zwijają się do jednej kolumny na telefonie).
Zakreskowane pola to miejsca na grafiki, których jeszcze nie wgrano.

```bash
php tools/preview.php --json
```

Surowy JSON, dokładnie to, co poleci do API.

```bash
php tools/test-text.php
```

44 asercje bez frameworka. Osobno pilnują fałszywych alarmów, bo katalog felg
jest pełen ciągów wyglądających jak numer telefonu: `225 45 17`, `8.5x19`,
`3SDM 0.01`.

## Zmiana szablonu

Treść i kolejność sekcji siedzą w `config/brand.php` — kod nie musi się
zmieniać. `layout` ustawia kolejność, usunięcie wpisu wyłącza sekcję.
Miejsca oznaczone `[SPRAWDZ]` to założenia o warunkach handlowych
(czas wysyłki, długość gwarancji) do potwierdzenia.

Z poziomu motywu albo snippetu można nadpisać całość filtrem:

```php
add_filter( 'dawmac_allegro_config', function ( array $config ): array {
    $config['blocks']['shipping']['html'] = '<ul><li>Wysyłka w 48 h.</li></ul>';
    return $config;
} );
```

## Rejestracja aplikacji API

1. Wejdź na `https://apps.developer.allegro.pl` (produkcja) albo
   `https://apps.developer.allegro.pl.allegrosandbox.pl` (sandbox).
2. **Zarejestruj nową aplikację** → typ z dostępem do konta użytkownika
   (Authorization Code), nie „aplikacja bez dostępu".
3. Jako **Redirect URI** wklej dokładnie adres pokazany w panelu wtyczki:
   `https://dawmac.pl/wp-admin/admin.php?page=dawmac-allegro`
   — musi zgadzać się co do znaku, razem z `https` i bez ukośnika na końcu.
4. Zaznacz uprawnienia: oferty (odczyt i zapis), zamówienia (odczyt i zapis).
5. Skopiuj `Client ID` i `Client Secret`.

Limit to 5 aplikacji na konto, a produkcja wymaga włączonej weryfikacji
dwuetapowej.

Sekrety najlepiej trzymać poza bazą — w `wp-config.php`:

```php
define( 'DAWMAC_ALLEGRO_CLIENT_ID', '...' );
define( 'DAWMAC_ALLEGRO_CLIENT_SECRET', '...' );
```

Wtedy pola w panelu są tylko do odczytu, a sekret nie leży w `wp_options`.

## Uwaga o tokenach

**Refresh token Allegro jest jednorazowy.** Każde odświeżenie zwraca nowy
i unieważnia stary. Dlatego odświeżanie idzie pod blokadą MySQL (`GET_LOCK`) —
cron, panel i WP-CLI potrafią trafić w to samo okno, a przegrany zostałby
ze skasowanym tokenem i wywalił połączenie.

Access token żyje 12 godzin, refresh token 3 miesiące. Kod autoryzacyjny
z ekranu zgody żyje **10 sekund**.

## Uruchamianie na serwerze (dhosting)

Domyślne PHP w konsoli to **5.4**, a WP-CLI dławi się limitem 128 MB.
Obie rzeczy trzeba obejść przy każdej komendzie:

```bash
php81 -d memory_limit=768M /usr/bin/wp --path=~/dawmac.pl-aid9/public_html dawmac-allegro status
```

Wygodniej raz dopisać do `~/.bashrc`:

```bash
alias wpd='php81 -d memory_limit=768M /usr/bin/wp --path=/home/klient.dhosting.pl/dawmac/dawmac.pl-aid9/public_html'
```

Wtedy wystarczy `wpd dawmac-allegro status`.

Sam `wp` bez tych przełączników pada — najpierw na PHP 5.4, potem na pamięci
przy ładowaniu motywu Astra.

## Wymagania

PHP 8.1+, WordPress 6.0+, WooCommerce. Podgląd i testy: samo PHP 8.1+.
