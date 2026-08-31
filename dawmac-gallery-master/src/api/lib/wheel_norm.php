<?php
/**
 * Ujednolicanie nazw felg — JEDNO miejsce dla całego ekosystemu.
 *
 * Ta sama funkcja musi dawać identyczny wynik po stronie galerii (PHP) i po
 * stronie sklepu (wtyczka WordPress). Jeśli kiedykolwiek zmienisz tu regułę,
 * zmień ją też w bliźniaczej funkcji we wtyczce i przeliczy oba indeksy —
 * inaczej dopasowanie produkt ↔ galeria zacznie po cichu gubić trafienia.
 *
 * Zasada: normalizacja NIE nadpisuje oryginalnych pól `brand` / `model`.
 * Wynik ląduje w osobnych kolumnach `brand_norm` / `model_norm`, a to,
 * co wpisał pracownik, zostaje nietknięte i dalej jest tym, co widzi klient.
 */

if (!function_exists('dawmac_wheel_norm')) {

    /**
     * "Japan Racing " → "JAPANRACING",  "JR 38" → "JR38",  "jr38" → "JR38"
     *
     * Usuwamy: białe znaki (w tym twardą spację), myślniki, podkreślenia,
     * apostrofy, akcenty i kropki. To dokładnie te znaki, które w bazie
     * różnicują zapisy tej samej felgi.
     */
    function dawmac_wheel_norm(?string $value): string
    {
        $value = (string) $value;

        // Twarda spacja z kopiuj-wklej potrafi udawać zwykłą.
        $value = str_replace(["\xC2\xA0", "\xE2\x80\x8B"], ' ', $value);
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        $value = function_exists('mb_strtoupper')
            ? mb_strtoupper($value, 'UTF-8')
            : strtoupper($value);

        $value = preg_replace('~[\s\-_`\x{00B4}\x{2019}\'".]+~u', '', $value);

        // Ucinamy do długości kolumny, żeby indeks nie zgubił dopasowania
        // na skrajnie długim wpisie.
        return mb_substr((string) $value, 0, 64, 'UTF-8');
    }

    /**
     * Czy para marka+model nadaje się do dopasowania.
     * Puste pole = projekt, którego nie da się podpiąć pod żaden produkt,
     * i to jest informacja dla listy roboczej, nie błąd.
     */
    function dawmac_wheel_is_matchable(?string $brand, ?string $model): bool
    {
        return dawmac_wheel_norm($brand) !== '' && dawmac_wheel_norm($model) !== '';
    }
}
