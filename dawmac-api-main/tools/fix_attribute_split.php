<?php
/**
 * Naprawa nazw producenta i modelu w atrybutach WooCommerce.
 *
 * Problem: w kilku miejscach nazwa marki została ucięta w złym miejscu —
 * pa_producent = "OE", pa_model = "MS IFG10", zamiast "OEMS" i "IFG10".
 * To psuje filtry sklepu, wygląda źle na kafelkach i uniemożliwia
 * powiązanie produktu ze zdjęciami z galerii.
 *
 * Skrypt przenosi wskazany człon z modelu do marki:
 *   marka "Elegance" + modele "Wheels E1FF"  ->  "Elegance Wheels" + "E1FF"
 *
 * Zmienia WYŁĄCZNIE nazwy terminów. Slugi zostają nietknięte, dzięki czemu
 * dotychczasowe adresy filtrów dalej działają, a Google nie dostaje setek
 * przekierowań. Slug można wyrównać osobno, jeśli kiedyś będzie potrzeba.
 *
 * URUCHAMIANIE (z katalogu WordPressa, przez WP-CLI):
 *
 *   wp eval-file fix_attribute_split.php Elegance Wheels
 *   wp eval-file fix_attribute_split.php Elegance Wheels apply
 *
 * Uwaga: potrzebne jest --skip-themes i podniesiona pamięć, bo motyw Astra
 * przy pełnym starcie wywraca się na limicie 128 MB:
 *
 *   php80 -d memory_limit=768M wp-cli.phar eval-file ... --skip-themes
 */

/*
 * WP-CLI odrzuca w eval-file flagi, których samo nie zna, więc potwierdzenie
 * zapisu przyjmujemy jako zwykły trzeci argument.
 */
$argumenty = array_values($args ?? []);

if (count($argumenty) < 2) {
    WP_CLI::error('Użycie: eval-file fix_attribute_split.php <marka> <człon-do-przeniesienia> [apply]');
}

[$marka, $czlon] = $argumenty;
$apply = ($argumenty[2] ?? '') === 'apply';

WP_CLI::log($apply ? "TRYB: zapis\n" : "TRYB: podgląd — nic nie zostanie zapisane. Dopisz argument: apply\n");

/* ---------------- producent ---------------- */

$term = get_term_by('name', $marka, 'pa_producent');

if (!$term) {
    WP_CLI::error("Nie ma producenta o nazwie \"$marka\".");
}

$nowaMarka = $marka . ' ' . $czlon;

/*
 * Gdyby docelowa marka już istniała, zmiana nazwy zrobiłaby dwa terminy
 * o tej samej nazwie zamiast je połączyć. Takie scalenie wymaga przeniesienia
 * przypisań produktów i jest osobną operacją — tu tylko o tym mówimy.
 */
if (get_term_by('name', $nowaMarka, 'pa_producent')) {
    WP_CLI::error("Producent \"$nowaMarka\" już istnieje — potrzebne jest scalenie, nie zmiana nazwy.");
}

WP_CLI::log(sprintf('Producent: "%s" -> "%s"  (%d produktów)', $marka, $nowaMarka, $term->count));

/* ---------------- modele ---------------- */

/*
 * UWAGA: pa_model jest wspólny dla WSZYSTKICH marek. Model "Wheels FR4"
 * należy do Ferrady, a nie do Elegance, choć obie mają modele zaczynające się
 * od "Wheels ". Dlatego bierzemy wyłącznie te modele, które faktycznie
 * występują na produktach TEJ marki — inaczej obcięlibyśmy przedrostek
 * cudzym modelom i zrobili większy bałagan, niż naprawiamy.
 */
global $wpdb;

$idModeli = $wpdb->get_col($wpdb->prepare("
    SELECT DISTINCT ttm.term_id
    FROM {$wpdb->term_relationships} trp
    JOIN {$wpdb->term_taxonomy} ttp ON ttp.term_taxonomy_id = trp.term_taxonomy_id
    JOIN {$wpdb->term_relationships} trm ON trm.object_id = trp.object_id
    JOIN {$wpdb->term_taxonomy} ttm ON ttm.term_taxonomy_id = trm.term_taxonomy_id
    WHERE ttp.term_id = %d AND ttm.taxonomy = 'pa_model'
", $term->term_id));

$modele = $idModeli
    ? get_terms([
        'taxonomy'   => 'pa_model',
        'hide_empty' => false,
        'include'    => $idModeli,
    ])
    : [];

$doZmiany = [];
$pomijane = [];

foreach ($modele as $m) {
    if (!str_starts_with($m->name, $czlon . ' ')) {
        continue;
    }

    // Ten sam model bywa przypięty do produktów kilku marek. Obcięcie
    // przedrostka zmieniłoby go także tam, więc taki przypadek zgłaszamy
    // zamiast po cichu zmieniać.
    $inneMarki = $wpdb->get_var($wpdb->prepare("
        SELECT COUNT(DISTINCT ttp.term_id)
        FROM {$wpdb->term_relationships} trm
        JOIN {$wpdb->term_taxonomy} ttm ON ttm.term_taxonomy_id = trm.term_taxonomy_id
        JOIN {$wpdb->term_relationships} trp ON trp.object_id = trm.object_id
        JOIN {$wpdb->term_taxonomy} ttp ON ttp.term_taxonomy_id = trp.term_taxonomy_id
        WHERE ttm.term_id = %d AND ttp.taxonomy = 'pa_producent' AND ttp.term_id <> %d
    ", $m->term_id, $term->term_id));

    if ((int) $inneMarki > 0) {
        $pomijane[] = sprintf('%s (używany też przez %d inną markę)', $m->name, (int) $inneMarki);
        continue;
    }

    $nowa = trim(substr($m->name, strlen($czlon) + 1));

    if ($nowa === '') {
        $pomijane[] = $m->name . ' (po obcięciu zostałaby pusta nazwa)';
        continue;
    }

    $doZmiany[] = [$m, $nowa];
}

if (!$doZmiany) {
    WP_CLI::warning("Nie znaleziono modeli zaczynających się od \"$czlon \".");
}

foreach ($doZmiany as [$m, $nowa]) {
    WP_CLI::log(sprintf('  model: "%s" -> "%s"  (%d prod.)', $m->name, $nowa, $m->count));
}

foreach ($pomijane as $p) {
    WP_CLI::warning('  pomijam: ' . $p);
}

if (!$apply) {
    WP_CLI::success('Podgląd zakończony. Nic nie zapisano.');
    return;
}

/* ---------------- zapis ---------------- */

$wynik = wp_update_term($term->term_id, 'pa_producent', ['name' => $nowaMarka]);

if (is_wp_error($wynik)) {
    WP_CLI::error('Producent: ' . $wynik->get_error_message());
}

$zmienione = 0;

foreach ($doZmiany as [$m, $nowa]) {
    $wynik = wp_update_term($m->term_id, 'pa_model', ['name' => $nowa]);

    if (is_wp_error($wynik)) {
        WP_CLI::warning(sprintf('  model "%s": %s', $m->name, $wynik->get_error_message()));
        continue;
    }

    $zmienione++;
}

WP_CLI::success(sprintf('Zmieniono producenta i %d modeli.', $zmienione));
WP_CLI::log('Pamiętaj o przeliczeniu słownika: php tools/sync_wheel_dict.php --apply');
