# Grafiki szablonu

Trzy pliki, pod dokładnie tymi nazwami — są zapisane w `config/brand.php`,
więc rozbieżność w nazwie oznacza po cichu pominiętą sekcję opisu.

| Plik | Rozmiar | Gdzie ląduje |
|---|---|---|
| `dawmac-banner-top.jpg` | 1200 × 400 | otwarcie oferty, pełna szerokość |
| `dawmac-trust.jpg` | 1000 × 1000 | obok listy „Dlaczego DAWMAC", pół szerokości |
| `dawmac-banner-bottom.jpg` | 1200 × 250 | domknięcie oferty, pełna szerokość |

JPG, do 1 MB. Po wrzuceniu:

```bash
wp dawmac-allegro images
```

Kluczem cache'a jest skrót pliku, więc podmiana grafiki wymusza ponowne
wgranie, a powtórne odpalenie komendy bez zmian nic nie kosztuje.

## Czego w grafikach być nie może

Allegro moderuje treść obrazków i zdejmuje oferty za:

* adresy WWW, e-maile, numery telefonu,
* zachęty do kontaktu lub zakupu poza serwisem,
* ceny i kwoty (cena jest polem oferty i się zmienia),
* obietnice, których oferta nie potwierdza („darmowa wysyłka"),
* cudze logotypy (BBS, OZ, Japan Racing i tak dalej).

## Grafiki generowane przez AI

Jeśli któraś powstanie w AI, **przed pierwszym wgraniem** trzeba przestawić:

```php
add_filter( 'dawmac_allegro_ai_images', '__return_true' );
```

Allegro nie pozwala zmienić tego oznaczenia po wgraniu pliku.
