<?php
/**
 * Baza dopasowań felg do aut — reguły w JEDNYM miejscu.
 *
 * Z tych funkcji korzystają endpointy api/fitment, import danych
 * (tools/import_fitment.php) i testy (tools/test_fitment.php).
 * Nic tu nie dotyka bazy — same parsowanie, walidacja i porównanie,
 * dzięki czemu da się to sprawdzić bez serwera.
 *
 * Zapis, który przyjmujemy, to ten z katalogów i wheel-size:
 *   rozstaw  "5x112", "5x114.3"
 *   felga    "7.5Jx17 ET51"
 *   opona    "225/45 R17"
 *   gwint    "M14x1.5", "1/2-20 UNF"
 */

if (!function_exists('dawmac_fit_slug')) {

    /**
     * Różnica otworu centralnego (mm), którą uznajemy za ten sam otwór.
     *
     * Ta sama piasta bywa podawana jako 66.5 i 66.6 (Audi vs BMW), 64.1
     * i 64.0 — to zaokrąglenia katalogów, nie różne auta. Większa różnica
     * oznacza pierścień centrujący, a felga z MNIEJSZYM otworem po prostu
     * nie wejdzie na piastę.
     */
    define('DAWMAC_FIT_CB_TOLERANCE', 0.1);

    /** "Mercedes-Benz" → "mercedes-benz",  "Škoda" → "skoda",  "VIII (CD1)" → "viii-cd1" */
    function dawmac_fit_slug(?string $value): string
    {
        $value = trim((string) $value);

        $value = strtr($value, [
            'ą' => 'a', 'ć' => 'c', 'ę' => 'e', 'ł' => 'l', 'ń' => 'n', 'ó' => 'o', 'ś' => 's', 'ź' => 'z', 'ż' => 'z',
            'Ą' => 'a', 'Ć' => 'c', 'Ę' => 'e', 'Ł' => 'l', 'Ń' => 'n', 'Ó' => 'o', 'Ś' => 's', 'Ź' => 'z', 'Ż' => 'z',
            'ä' => 'a', 'ö' => 'o', 'ü' => 'u', 'ß' => 'ss', 'Ä' => 'a', 'Ö' => 'o', 'Ü' => 'u',
            'š' => 's', 'č' => 'c', 'ř' => 'r', 'ž' => 'z', 'ý' => 'y', 'á' => 'a', 'í' => 'i', 'é' => 'e', 'ě' => 'e', 'ů' => 'u', 'ú' => 'u',
            'Š' => 's', 'Č' => 'c', 'Ř' => 'r', 'Ž' => 'z', 'Ý' => 'y', 'Á' => 'a', 'Í' => 'i', 'É' => 'e', 'Ě' => 'e', 'Ů' => 'u', 'Ú' => 'u',
            'ë' => 'e', 'è' => 'e', 'ê' => 'e', 'à' => 'a', 'â' => 'a', 'ç' => 'c', 'ñ' => 'n', 'ô' => 'o', 'î' => 'i', 'ï' => 'i',
            'Ë' => 'e', 'È' => 'e', 'Ê' => 'e', 'À' => 'a', 'Â' => 'a', 'Ç' => 'c', 'Ñ' => 'n', 'Ô' => 'o', 'Î' => 'i', 'Ï' => 'i',
            '+' => ' plus ',
        ]);

        $value = strtolower($value);
        $value = preg_replace('~[^a-z0-9]+~', '-', $value);

        return substr(trim((string) $value, '-'), 0, 96);
    }

    /**
     * "5x112" → [5, 112.0],  "5 x 114,3" → [5, 114.3],  "5×120" → [5, 120.0]
     * Zwraca null, gdy zapis nie jest rozstawem albo liczby są nierealne.
     *
     * @return array{holes:int, mm:float}|null
     */
    function dawmac_fit_parse_pcd(?string $value): ?array
    {
        $value = str_replace(['×', ','], ['x', '.'], (string) $value);

        if (!preg_match('~^\s*(\d{1,2})\s*[xX]\s*(\d{2,3}(?:\.\d{1,2})?)\s*$~', $value, $m)) {
            return null;
        }

        $holes = (int) $m[1];
        $mm    = (float) $m[2];

        if ($holes < 3 || $holes > 10 || $mm < 90 || $mm > 210) {
            return null;
        }

        return ['holes' => $holes, 'mm' => $mm];
    }

    /** [5, 114.3] → "5x114.3",  [5, 112.0] → "5x112" */
    function dawmac_fit_pcd_label(int $holes, float $mm): string
    {
        return $holes . 'x' . dawmac_fit_num($mm);
    }

    /**
     * "7.5Jx17 ET51" → width 7.5, diameter 17, et 51
     * Przyjmuje też "7,5J x 17 ET 51", "8Jx18 ET+40", "7x16 ET-5".
     *
     * @return array{width:float, diameter:int, et:int}|null
     */
    function dawmac_fit_parse_rim(?string $value): ?array
    {
        $value = str_replace(['×', ','], ['x', '.'], (string) $value);

        if (!preg_match('~^\s*(\d{1,2}(?:\.\d)?)\s*J?\s*[xX]\s*(\d{2})\s*ET\s*([+-]?\d{1,3})\s*$~i', $value, $m)) {
            return null;
        }

        $width    = (float) $m[1];
        $diameter = (int) $m[2];
        $et       = (int) $m[3];

        // Szerokość felgi idzie co pół cala — 7.3J to literówka, nie felga.
        if ($width < 3 || $width > 14 || fmod($width * 2, 1.0) !== 0.0) {
            return null;
        }
        if ($diameter < 12 || $diameter > 26 || $et < -100 || $et > 100) {
            return null;
        }

        return ['width' => $width, 'diameter' => $diameter, 'et' => $et];
    }

    /** (7.5, 17, 51) → "7.5Jx17 ET51" */
    function dawmac_fit_rim_label(float $width, int $diameter, int $et): string
    {
        return dawmac_fit_num($width) . 'Jx' . $diameter . ' ET' . $et;
    }

    /**
     * "225/45 R17" → szerokość 225, profil 45, średnica 17, zapis ujednolicony.
     * Przyjmuje "225/45R17" i "225/45 ZR17". Indeks nośności i prędkości
     * ("91W") obcinamy — nie wpływa na dopasowanie felgi.
     *
     * @return array{width:int, aspect:int, diameter:int, label:string}|null
     */
    function dawmac_fit_parse_tire(?string $value): ?array
    {
        if (!preg_match('~^\s*(\d{3})\s*/\s*(\d{2})\s*Z?R\s*(\d{2})\b~i', (string) $value, $m)) {
            return null;
        }

        $width    = (int) $m[1];
        $aspect   = (int) $m[2];
        $diameter = (int) $m[3];

        if ($width < 125 || $width > 395 || $aspect < 20 || $aspect > 90 || $diameter < 12 || $diameter > 26) {
            return null;
        }

        return [
            'width'    => $width,
            'aspect'   => $aspect,
            'diameter' => $diameter,
            'label'    => sprintf('%d/%d R%d', $width, $aspect, $diameter),
        ];
    }

    /**
     * "M14x1.5" → "M14x1.5",  "m12 x 1,25" → "M12x1.25",  "1/2\"-20 UNF" → "1/2-20 UNF"
     * Calowe gwinty mają amerykańskie auta (Ford, Jeep, część Dodge).
     */
    function dawmac_fit_parse_thread(?string $value): ?string
    {
        $value = str_replace(['×', ','], ['x', '.'], (string) $value);

        if (preg_match('~^\s*M\s*(\d{2})\s*[xX]\s*(\d(?:\.\d{1,2})?)\s*$~i', $value, $m)) {
            return 'M' . (int) $m[1] . 'x' . dawmac_fit_num((float) $m[2]);
        }

        if (preg_match('~^\s*(\d{1,2}/\d{1,2})\s*"?\s*-\s*(\d{2})\s*(?:UNF)?\s*$~i', $value, $m)) {
            return $m[1] . '-' . $m[2] . ' UNF';
        }

        return null;
    }

    /**
     * Czy felga wejdzie na piastę auta.
     *
     * Rozstaw musi się zgadzać co do milimetra — 5x114.3 i 5x115 to różne
     * felgi. Otwór centralny felgi może być większy (wtedy pierścień),
     * ale nie mniejszy.
     *
     * @param array{pcd_holes:int, pcd_mm:float, center_bore:float} $wheel
     * @param array{pcd_holes:int, pcd_mm:float, center_bore:float} $car
     * @return array{fits:bool, needs_ring:bool, reason:string}
     */
    function dawmac_fit_check(array $wheel, array $car): array
    {
        $samePcd = (int) $wheel['pcd_holes'] === (int) $car['pcd_holes']
            && dawmac_fit_hundredths($wheel['pcd_mm']) === dawmac_fit_hundredths($car['pcd_mm']);

        if (!$samePcd) {
            return ['fits' => false, 'needs_ring' => false, 'reason' => 'Inny rozstaw śrub.'];
        }

        // Liczymy w setnych milimetra, żeby 66.6 - 66.5 nie dało 0.0999999.
        $diff = dawmac_fit_hundredths($wheel['center_bore']) - dawmac_fit_hundredths($car['center_bore']);
        $tol  = dawmac_fit_hundredths(DAWMAC_FIT_CB_TOLERANCE);

        if ($diff < -$tol) {
            return ['fits' => false, 'needs_ring' => false, 'reason' => 'Otwór centralny felgi jest mniejszy niż piasta.'];
        }

        if ($diff > $tol) {
            return [
                'fits'       => true,
                'needs_ring' => true,
                'reason'     => sprintf(
                    'Pasuje z pierścieniem centrującym %s → %s.',
                    dawmac_fit_num((float) $wheel['center_bore']),
                    dawmac_fit_num((float) $car['center_bore'])
                ),
            ];
        }

        return ['fits' => true, 'needs_ring' => false, 'reason' => 'Pasuje bez pierścienia.'];
    }

    /**
     * Sprawdza jedną generację z pliku importu. Zwraca listę błędów
     * po polsku — pusta lista znaczy, że wpis można zapisać.
     *
     * Walidacja jest ostra celowo: te dane trafią do klienta, który na ich
     * podstawie kupi felgi. Lepiej odrzucić wpis, niż zapisać zgadywany.
     *
     * @return string[]
     */
    function dawmac_fit_validate_generation(array $g): array
    {
        $errors = [];
        $year   = (int) date('Y');

        if (dawmac_fit_slug($g['name'] ?? '') === '') {
            $errors[] = 'brak nazwy generacji';
        }

        $years = $g['years'] ?? null;
        $from  = is_array($years) ? ($years[0] ?? null) : null;
        $to    = is_array($years) ? ($years[1] ?? null) : null;

        if (!is_int($from) || $from < 1950 || $from > $year + 2) {
            $errors[] = 'years[0] musi być rokiem rozpoczęcia produkcji';
        } elseif ($to !== null && (!is_int($to) || $to < $from || $to > $year + 2)) {
            $errors[] = 'years[1] musi być rokiem zakończenia (albo null, gdy produkcja trwa)';
        }

        if (dawmac_fit_parse_pcd($g['pcd'] ?? null) === null) {
            $errors[] = 'nieprawidłowy rozstaw "' . ($g['pcd'] ?? '') . '" (oczekiwany np. 5x112)';
        }

        $cb = $g['center_bore'] ?? null;
        if ((!is_int($cb) && !is_float($cb)) || $cb < 40 || $cb > 180) {
            $errors[] = 'center_bore musi być liczbą w mm (np. 57.1)';
        }

        if (dawmac_fit_parse_thread($g['thread'] ?? null) === null) {
            $errors[] = 'nieprawidłowy gwint "' . ($g['thread'] ?? '') . '" (oczekiwany np. M14x1.5)';
        }

        // Śruba czy nakrętka bywa nieznana (zbiór z alukonfiguratora jej nie
        // ma). Wtedy null: lepiej "nie wiemy" niż zgadywanie po marce.
        $fastener = $g['fastener'] ?? null;
        if ($fastener !== null && !in_array($fastener, ['bolt', 'nut'], true)) {
            $errors[] = 'fastener musi być "bolt" (śruba), "nut" (nakrętka) albo null, gdy nieznany';
        }

        $torque = $g['torque_nm'] ?? null;
        if ($torque !== null && (!is_int($torque) || $torque < 50 || $torque > 300)) {
            $errors[] = 'torque_nm musi być liczbą całkowitą 50-300 albo null';
        }

        if (array_key_exists('verified', $g) && !is_bool($g['verified'])) {
            $errors[] = 'verified musi być true albo false';
        }

        $wheels = $g['wheels'] ?? [];
        if (!is_array($wheels)) {
            $errors[] = 'wheels musi być listą';
            $wheels = [];
        }

        foreach (array_values($wheels) as $i => $w) {
            $rim = dawmac_fit_parse_rim($w['size'] ?? null);
            if ($rim === null) {
                $errors[] = "wheels[$i]: nieprawidłowy rozmiar \"" . ($w['size'] ?? '') . '" (oczekiwany np. 7.5Jx17 ET51)';
                continue;
            }

            if (!in_array($w['axle'] ?? 'both', ['both', 'front', 'rear'], true)) {
                $errors[] = "wheels[$i]: axle musi być both, front albo rear";
            }

            if (isset($w['tire'])) {
                $tire = dawmac_fit_parse_tire($w['tire']);
                if ($tire === null) {
                    $errors[] = "wheels[$i]: nieprawidłowa opona \"{$w['tire']}\" (oczekiwana np. 225/45 R17)";
                } elseif ($tire['diameter'] !== $rim['diameter']) {
                    $errors[] = "wheels[$i]: opona R{$tire['diameter']} nie pasuje do felgi {$rim['diameter']}\"";
                }
            }

            if (isset($w['oem']) && !is_bool($w['oem'])) {
                $errors[] = "wheels[$i]: oem musi być true albo false";
            }
        }

        return $errors;
    }

    /**
     * Wiersz fit_generation z bazy → to, co oddaje API.
     * Jedno miejsce, żeby lista, karta auta i wyszukiwarka mówiły tym samym językiem.
     */
    function dawmac_fit_generation_out(array $row): array
    {
        $from = (int) $row['year_from'];
        $to   = $row['year_to'] !== null ? (int) $row['year_to'] : null;

        return [
            'id'          => (int) $row['id'],
            'name'        => $row['name'],
            'slug'        => $row['slug'],
            'years'       => $from . '-' . ($to ?? ''),
            'year_from'   => $from,
            'year_to'     => $to,
            'pcd'         => dawmac_fit_pcd_label((int) $row['pcd_holes'], (float) $row['pcd_mm']),
            'center_bore' => (float) $row['center_bore'],
            'thread'      => $row['thread'],
            'fastener'    => $row['fastener'],
            'torque_nm'   => $row['torque_nm'] !== null ? (int) $row['torque_nm'] : null,
            'verified'    => (bool) $row['verified'],
            'source'      => $row['source'],
        ];
    }

    /** Wiersz fit_wheel z bazy → to, co oddaje API. */
    function dawmac_fit_wheel_out(array $row): array
    {
        return [
            'axle'     => $row['axle'],
            'size'     => dawmac_fit_rim_label((float) $row['width'], (int) $row['diameter'], (int) $row['et']),
            'diameter' => (int) $row['diameter'],
            'width'    => (float) $row['width'],
            'et'       => (int) $row['et'],
            'tire'     => $row['tire'] !== '' ? $row['tire'] : null,
            'oem'      => (bool) $row['is_oem'],
        ];
    }

    /** 112.0 → "112",  114.3 → "114.3",  1.25 → "1.25" */
    function dawmac_fit_num(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }

    /** Liczba w setnych jako int — porównania bez błędów zmiennoprzecinkowych. */
    function dawmac_fit_hundredths($value): int
    {
        return (int) round((float) $value * 100);
    }
}
