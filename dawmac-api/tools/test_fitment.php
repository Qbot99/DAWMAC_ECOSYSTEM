<?php
/**
 * Testy reguł bazy dopasowań — bez serwera i bez bazy.
 *
 *   php tools/test_fitment.php
 *
 * Kod wyjścia 0 = wszystko przeszło. Sprawdza też, czy pliki w data/fitment
 * przechodzą walidację, więc zepsuty plik danych wychodzi tu, a nie na imporcie.
 */

require __DIR__ . '/../api/fitment/lib/fitment.php';

$ok = 0;
$zle = [];

function sprawdz(string $opis, $wynik, $oczekiwane): void
{
    global $ok, $zle;

    if ($wynik === $oczekiwane) {
        $ok++;
        return;
    }

    $zle[] = sprintf(
        "%s\n      jest:       %s\n      oczekiwane: %s",
        $opis,
        json_encode($wynik, JSON_UNESCAPED_UNICODE),
        json_encode($oczekiwane, JSON_UNESCAPED_UNICODE)
    );
}

/* Slug ---------------------------------------------------------------- */

sprawdz('slug: myślnik zostaje', dawmac_fit_slug('Mercedes-Benz'), 'mercedes-benz');
sprawdz('slug: czeskie znaki', dawmac_fit_slug('Škoda'), 'skoda');
sprawdz('slug: nawiasy i spacje', dawmac_fit_slug(' VIII (CD1) '), 'viii-cd1');
sprawdz('slug: polskie znaki', dawmac_fit_slug('Łódź Żółta'), 'lodz-zolta');
sprawdz('slug: plus to nie to samo co brak', dawmac_fit_slug('ID.4 GTX+'), 'id-4-gtx-plus');
sprawdz('slug: pusty', dawmac_fit_slug('  '), '');

/* Rozstaw ------------------------------------------------------------- */

sprawdz('pcd: 5x112', dawmac_fit_parse_pcd('5x112'), ['holes' => 5, 'mm' => 112.0]);
sprawdz('pcd: przecinek i spacje', dawmac_fit_parse_pcd('5 X 114,3'), ['holes' => 5, 'mm' => 114.3]);
sprawdz('pcd: znak mnożenia', dawmac_fit_parse_pcd('6×139.7'), ['holes' => 6, 'mm' => 139.7]);
sprawdz('pcd: brak liczby śrub', dawmac_fit_parse_pcd('112'), null);
sprawdz('pcd: nierealna średnica', dawmac_fit_parse_pcd('5x12'), null);
sprawdz('pcd: etykieta całkowita', dawmac_fit_pcd_label(5, 112.0), '5x112');
sprawdz('pcd: etykieta z ułamkiem', dawmac_fit_pcd_label(5, 114.3), '5x114.3');

/* Felga --------------------------------------------------------------- */

sprawdz('felga: 7.5Jx17 ET51', dawmac_fit_parse_rim('7.5Jx17 ET51'), ['width' => 7.5, 'diameter' => 17, 'et' => 51.0]);
sprawdz('felga: zapis z przecinkiem', dawmac_fit_parse_rim('7,5J x 17 ET 51'), ['width' => 7.5, 'diameter' => 17, 'et' => 51.0]);
sprawdz('felga: ET ze znakiem +', dawmac_fit_parse_rim('8Jx18 ET+40'), ['width' => 8.0, 'diameter' => 18, 'et' => 40.0]);
sprawdz('felga: ujemne ET', dawmac_fit_parse_rim('10x16 ET-25'), ['width' => 10.0, 'diameter' => 16, 'et' => -25.0]);
sprawdz('felga: ET z połówką', dawmac_fit_parse_rim('7Jx17 ET48,5'), ['width' => 7.0, 'diameter' => 17, 'et' => 48.5]);
sprawdz('felga: ET z dwoma miejscami po przecinku', dawmac_fit_parse_rim('7Jx17 ET48.25'), null);
sprawdz('felga: etykieta z połówką', dawmac_fit_rim_label(7.0, 17, 48.5), '7Jx17 ET48.5');
sprawdz('felga: etykieta ujemnego ET', dawmac_fit_rim_label(10.0, 16, -25.0), '10Jx16 ET-25');
sprawdz('felga: szerokość nie co pół cala', dawmac_fit_parse_rim('7.3Jx17 ET51'), null);
sprawdz('felga: brak ET', dawmac_fit_parse_rim('7.5Jx17'), null);
sprawdz('felga: etykieta', dawmac_fit_rim_label(8.0, 18, 40), '8Jx18 ET40');

/* Opona --------------------------------------------------------------- */

sprawdz('opona: bez spacji', dawmac_fit_parse_tire('225/45R17')['label'] ?? null, '225/45 R17');
sprawdz('opona: ZR i indeksy', dawmac_fit_parse_tire('255/35 ZR19 96Y')['label'] ?? null, '255/35 R19');
sprawdz('opona: śmieci', dawmac_fit_parse_tire('R17'), null);

/* Gwint --------------------------------------------------------------- */

sprawdz('gwint: M14x1.5', dawmac_fit_parse_thread('M14x1.5'), 'M14x1.5');
sprawdz('gwint: małe litery i przecinek', dawmac_fit_parse_thread('m12 x 1,25'), 'M12x1.25');
sprawdz('gwint: calowy', dawmac_fit_parse_thread('1/2"-20 UNF'), '1/2-20 UNF');
sprawdz('gwint: śmieci', dawmac_fit_parse_thread('14'), null);

/* Dopasowanie felgi do piasty ----------------------------------------- */

$bmw  = ['pcd_holes' => 5, 'pcd_mm' => 112.0, 'center_bore' => 66.6];
$audi = ['pcd_holes' => 5, 'pcd_mm' => '112.00', 'center_bore' => '66.50']; // tak oddaje mysqli
$golf = ['pcd_holes' => 5, 'pcd_mm' => 112.0, 'center_bore' => 57.1];
$tesla = ['pcd_holes' => 5, 'pcd_mm' => 114.3, 'center_bore' => 64.1];

$felga666 = ['pcd_holes' => 5, 'pcd_mm' => 112.0, 'center_bore' => 66.6];
$felga571 = ['pcd_holes' => 5, 'pcd_mm' => 112.0, 'center_bore' => 57.1];

sprawdz('dopasowanie: ten sam otwór', dawmac_fit_check($felga666, $bmw)['needs_ring'], false);
sprawdz('dopasowanie: 66.6 na 66.5 bez pierścienia', dawmac_fit_check($felga666, $audi), [
    'fits' => true, 'needs_ring' => false, 'reason' => 'Pasuje bez pierścienia.',
]);
sprawdz('dopasowanie: większy otwór = pierścień', dawmac_fit_check($felga666, $golf), [
    'fits' => true, 'needs_ring' => true, 'reason' => 'Pasuje z pierścieniem centrującym 66.6 → 57.1.',
]);
sprawdz('dopasowanie: mniejszy otwór nie wejdzie', dawmac_fit_check($felga571, $bmw)['fits'], false);
sprawdz('dopasowanie: inny rozstaw', dawmac_fit_check($felga666, $tesla)['fits'], false);
sprawdz('dopasowanie: 5x114.3 to nie 5x115', dawmac_fit_check(
    ['pcd_holes' => 5, 'pcd_mm' => 115.0, 'center_bore' => 70.1], $tesla
)['fits'], false);

/* Walidacja generacji ------------------------------------------------- */

$dobra = [
    'name' => 'VIII', 'years' => [2019, null], 'pcd' => '5x112', 'center_bore' => 57.1,
    'thread' => 'M14x1.5', 'fastener' => 'bolt', 'torque_nm' => 140, 'verified' => false,
    'wheels' => [['size' => '6.5Jx16 ET46', 'tire' => '205/55 R16', 'oem' => true]],
];

sprawdz('walidacja: poprawny wpis', dawmac_fit_validate_generation($dobra), []);
sprawdz('walidacja: center_bore jako tekst', count(dawmac_fit_validate_generation(['center_bore' => '57.1'] + $dobra)), 1);
sprawdz('walidacja: rok końca przed początkiem', count(dawmac_fit_validate_generation(['years' => [2019, 2010]] + $dobra)), 1);
sprawdz('walidacja: nieznany rodzaj mocowania', count(dawmac_fit_validate_generation(['fastener' => 'screw'] + $dobra)), 1);
sprawdz('walidacja: mocowanie nieznane (null)', dawmac_fit_validate_generation(['fastener' => null] + $dobra), []);
$bezMocowania = $dobra;
unset($bezMocowania['fastener']);
sprawdz('walidacja: brak pola mocowania', dawmac_fit_validate_generation($bezMocowania), []);
sprawdz('walidacja: verified jako tekst', count(dawmac_fit_validate_generation(['verified' => 'tak'] + $dobra)), 1);
sprawdz(
    'walidacja: opona R17 na felgę 16"',
    dawmac_fit_validate_generation(['wheels' => [['size' => '6.5Jx16 ET46', 'tire' => '225/45 R17']]] + $dobra),
    ['wheels[0]: opona R17 nie pasuje do felgi 16"']
);
sprawdz('walidacja: pusty wpis łapie wszystko naraz', count(dawmac_fit_validate_generation([])), 5);

/* Pliki danych w repozytorium ----------------------------------------- */

foreach (glob(__DIR__ . '/../data/fitment/*.json') ?: [] as $plik) {
    $dane = json_decode((string) file_get_contents($plik), true);
    sprawdz(basename($plik) . ': poprawny JSON', is_array($dane), true);

    foreach ($dane['makes'] ?? [] as $make) {
        foreach ($make['models'] ?? [] as $model) {
            foreach ($model['generations'] ?? [] as $g) {
                $opis = basename($plik) . ": {$make['name']} {$model['name']} " . ($g['name'] ?? '?');
                sprawdz($opis, dawmac_fit_validate_generation($g), []);
            }
        }
    }
}

/* --------------------------------------------------------------------- */

if ($zle) {
    echo "BŁĘDY (" . count($zle) . "):\n";
    foreach ($zle as $z) {
        echo "  - $z\n";
    }
    echo "\nPrzeszło $ok, nie przeszło " . count($zle) . ".\n";
    exit(1);
}

echo "Wszystkie testy przeszły ($ok).\n";
