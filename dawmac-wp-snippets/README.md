# Snippety WPCode z dawmac.pl

Kopia odniesienia. Źródłem prawdy jest WPCode w WordPressie —
ten katalog daje temu kodowi historię zmian, której wcześniej nie miał.

## Edycja z linii poleceń — i pułapka, która zdjęła sklep

WPCode trzyma w opcji `wpcode_snippets` dwa pola: `code` i `compiled_code`.
**U wszystkich normalnych snippetów `compiled_code` jest PUSTE** i WPCode
wykonuje `code`. Wpisanie tam czegokolwiek psuje wykonanie snippetu —
u nas zdjęło stronę sklepu (`<main>` ze 152 tys. znaków do 8), a objaw
wyglądał na błąd w logice snippetu, nie w sposobie zapisu.

Skasowanie całej opcji też nie jest wyjściem — wyłącza wszystkie snippety naraz.

Do edycji służy [wpcode-update.php](wpcode-update.php):

```bash
wp eval-file wpcode-update.php 343177 nowy-kod.php "Nowa nazwa" --skip-themes
wp eval-file wpcode-update.php 328701 off --skip-themes
```

Wymaga `--skip-themes` i podniesionej pamięci — motyw Astra wywraca WP-CLI na 128 MB.

## Aktywne (13)

| Nazwa | ID | Typ | Znaków | Plik |
|---|---|---|---:|---|
| Katalog: Dawmac Forged tylko na magazynie | 342771 | php | 1278 | [342771-katalog-dawmac-forged-tylko-na-magazynie.php](342771-katalog-dawmac-forged-tylko-na-magazynie.php) |
| Katalog: bez licznika wyników | 334951 | php | 161 | [334951-katalog-bez-licznika-wynikow.php](334951-katalog-bez-licznika-wynikow.php) |
| Katalog: sortowanie po cenie | 208128 | php | 1055 | [208128-katalog-sortowanie-po-cenie.php](208128-katalog-sortowanie-po-cenie.php) |
| Katalog: wygląd kafelków (PRODUKCJA) | 336378 | php | 9468 | [336378-katalog-wyglad-kafelkow-produkcja.php](336378-katalog-wyglad-kafelkow-produkcja.php) |
| Koszyk: weryfikacja felg i dane do faktury | 328829 | php | 13410 | [328829-koszyk-weryfikacja-felg-i-dane-do-faktury.php](328829-koszyk-weryfikacja-felg-i-dane-do-faktury.php) |
| Produkt: formularz dostępności (zwijany) | 201790 | php | 2401 | [201790-produkt-formularz-dostepnosci-zwijany.php](201790-produkt-formularz-dostepnosci-zwijany.php) |
| Produkt: okruszki nad galerią | 342830 | php | 680 | [342830-produkt-okruszki-nad-galeria.php](342830-produkt-okruszki-nad-galeria.php) |
| Produkt: wyszukiwarka nad kartą | 225164 | php | 338 | [225164-produkt-wyszukiwarka-nad-karta.php](225164-produkt-wyszukiwarka-nad-karta.php) |
| SEO: Open Graph i Twitter Card | 343177 | php | 7795 | [343177-seo-open-graph-i-twitter-card.php](343177-seo-open-graph-i-twitter-card.php) |
| SEO: Schema AutoPartsStore | 334751 | html | 3019 | [334751-seo-schema-autopartsstore.html](334751-seo-schema-autopartsstore.html) |
| Strona główna: slider promocji | 181314 | php | 3067 | [181314-strona-glowna-slider-promocji.php](181314-strona-glowna-slider-promocji.php) |
| Wydajność: preload obrazka hero (LCP) | 342588 | php | 495 | [342588-wydajnosc-preload-obrazka-hero-lcp.php](342588-wydajnosc-preload-obrazka-hero-lcp.php) |
| ukryj opony | 325565 | php | 626 | [325565-ukryj-opony.php](325565-ukryj-opony.php) |

## Wyłączone 31.08.2026

| ID | Dlaczego |
|---|---|
| 328701 | Wypisywał własne `og:image` i `og:description` obok #343177 — tagi dublowały się na karcie produktu. Opis z parametrów felgi przeniesiony do #343177. |
| 282004 | Ustawiał domyślne sortowanie, gdy #208128 ograniczał listę opcji. Scalone w jeden snippet. |
