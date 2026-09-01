# Dawmac Filters

Wtyczka WordPress/WooCommerce do **błyskawicznego filtrowania dużego katalogu produktów**.
Powstała dla sklepu [dawmac.pl](https://dawmac.pl) (felgi i opony, ~32 tys. produktów),
w którym filtrowanie oparte o `meta_query` / taksonomie potrafiło mielić po kilka sekund.

## Skąd przyspieszenie

WordPress trzyma atrybuty produktów w tabelach taksonomii - przy 32 tys. produktów
to ponad 800 tys. wierszy relacji, a każdy zaznaczony filtr dokłada kolejne złączenie.
Ta wtyczka odwraca moment wykonania pracy: **liczy przy zapisie produktu, nie przy odczycie**.

* przy zapisie produktu jego atrybuty trafiają do płaskiej tabeli indeksowej
  z indeksem pokrywającym `(attribute, value_slug, product_id)`,
* filtrowanie to wtedy jedno zapytanie po indeksie (kotwica + `EXISTS`) zamiast łańcucha złączeń,
* karty produktów (tytuł, cena, miniatura, tekst do wyszukiwania) mają własną tabelę,
  więc odpowiedź nie dotyka `wp_posts` ani `wp_postmeta`.

Efekt na produkcji: kliknięcie filtra ~20 ms po stronie serwera zamiast setek milisekund.

## Jak to działa na stronie

Wtyczka celowo **niczego nie zmienia w wyglądzie sklepu** - siatkę produktów renderuje
natywny WooCommerce (razem z motywem i własnymi snippetami sklepu). Wtyczka dokłada:

* **widget z filtrami** w panelu bocznym sklepu,
* zawężenie głównego zapytania (`post__in` z indeksu) - działa też bez JavaScriptu,
* **lekki endpoint** (`endpoint.php`, tryb `SHORTINIT` - bez ładowania wtyczek i motywu),
  z którego korzysta AJAX przy zmianie filtrów.

## Funkcje

* filtry: producent, średnica, szerokość, rozstaw, ET (zakres od-do), kolor, cena, dostępność,
* osobny zestaw filtrów dla opon (szerokość / profil / średnica),
* wyszukiwarka przeszukująca tytuły **i opisy** (m.in. lokalizacje magazynowe typu `42.L/NHB`),
* chipy aktywnych filtrów, stan filtrów w adresie URL (linki do udostępnienia),
* panel w kokpicie z przełącznikiem silnika filtrów i rollbackiem jednym kliknięciem,
* komendy WP-CLI: `wp dawmac reindex`, `wp dawmac status`, `wp dawmac query "..."`.

## Wymagania

PHP 8.1+, WordPress 6.0+, WooCommerce.

## Instalacja

1. Wgraj katalog `dawmac-filters` do `wp-content/plugins/`.
2. Włącz wtyczkę w kokpicie (tabele indeksowe utworzą się same).
3. Zbuduj indeks: `wp dawmac reindex`.
4. Włącz nowy silnik w panelu **Hubert → Dawmac Filtry**.

Indeks aktualizuje się potem sam - przy zapisie, usunięciu i zmianie statusu produktu.
