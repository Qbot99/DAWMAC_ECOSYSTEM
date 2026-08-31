<?php
/**
 * Usuwa z nazw modeli wklejony przedrostek "Model:".
 *
 * W pa_model trafiły nazwy w rodzaju "MODEL:MUGELLO", "Model: 6811",
 * "MODEL: MG355" — ktoś przekleił etykietę razem z wartością. Psuje to
 * listy wyboru, filtry i dopasowanie do galerii.
 *
 * TNIEMY WYŁĄCZNIE TAM, GDZIE JEST DWUKROPEK. To nie jest szczegół:
 * Judd ma prawdziwe modele "Model One", "Model Two" i "Model Three"
 * (łącznie 236 produktów). Reguła bez dwukropka zrobiłaby z nich
 * "One", "Two" i "Three".
 *
 * URUCHAMIANIE:
 *   wp eval-file fix_model_prefix.php            — podgląd
 *   wp eval-file fix_model_prefix.php apply      — zapis
 *
 * Potrzebne --skip-themes i podniesiona pamięć (motyw Astra wywraca się
 * na domyślnym limicie 128 MB).
 */

$apply = (($args[0] ?? '') === 'apply');

WP_CLI::log($apply ? "TRYB: zapis\n" : "TRYB: podgląd — nic nie zostanie zapisane. Dopisz argument: apply\n");

$modele = get_terms([
    'taxonomy'   => 'pa_model',
    'hide_empty' => false,
]);

if (is_wp_error($modele)) {
    WP_CLI::error($modele->get_error_message());
}

$doZmiany = [];
$kolizje  = [];

/* Nazwy zajęte — po obcięciu przedrostka nie chcemy dubla. */
$zajete = [];
foreach ($modele as $m) {
    $zajete[mb_strtolower($m->name)] = $m->term_id;
}

foreach ($modele as $m) {
    /*
     * "MODEL:X1", "Model: 6811", "model : abc" — dwukropek jest warunkiem.
     * Dodatkowo łapiemy samo "model " małymi literami ("model I02"),
     * bo to ten sam błąd, a mała litera wyklucza prawdziwą nazwę modelu.
     */
    if (preg_match('~^\s*model\s*:\s*(.+)$~iu', $m->name, $dop)) {
        $nowa = trim($dop[1]);
    } elseif (preg_match('~^model\s+(.+)$~u', $m->name, $dop)) {
        $nowa = trim($dop[1]);
    } else {
        continue;
    }

    if ($nowa === '') {
        continue;
    }

    $klucz = mb_strtolower($nowa);

    if (isset($zajete[$klucz]) && $zajete[$klucz] !== $m->term_id) {
        $kolizje[] = sprintf('"%s" -> "%s" (nazwa już zajęta przez inny model)', $m->name, $nowa);
        continue;
    }

    $doZmiany[] = [$m, $nowa];
}

if (!$doZmiany && !$kolizje) {
    WP_CLI::success('Nie ma czego sprzątać.');
    return;
}

foreach ($doZmiany as [$m, $nowa]) {
    WP_CLI::log(sprintf('  "%s" -> "%s"  (%d prod.)', $m->name, $nowa, $m->count));
}

foreach ($kolizje as $k) {
    WP_CLI::warning('  pomijam: ' . $k);
}

if (!$apply) {
    WP_CLI::success(sprintf('Podgląd: %d do zmiany, %d pominiętych.', count($doZmiany), count($kolizje)));
    return;
}

$zmienione = 0;

foreach ($doZmiany as [$m, $nowa]) {
    $wynik = wp_update_term($m->term_id, 'pa_model', ['name' => $nowa]);

    if (is_wp_error($wynik)) {
        WP_CLI::warning(sprintf('  "%s": %s', $m->name, $wynik->get_error_message()));
        continue;
    }

    $zmienione++;
}

WP_CLI::success(sprintf('Posprzątane modele: %d.', $zmienione));
WP_CLI::log('Pamiętaj o przeliczeniu słownika: php tools/sync_wheel_dict.php --apply');
