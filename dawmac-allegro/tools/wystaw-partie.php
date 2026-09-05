<?php
/**
 * Wystawianie paczki partiami, z mozliwoscia wznowienia.
 *
 * Kazde uruchomienie przerabia tyle produktow, ile zmiesci sie w budzecie
 * czasu, i konczy sie czysto. Produkty juz przerobione sa pomijane, wiec
 * skrypt mozna puszczac wielokrotnie az do wyczerpania listy.
 *
 * Oferty powstaja jako SZKICE. Wystawienie na sprzedaz to osobny krok,
 * po audycie - zeby zaden blad nie trafil od razu przed kupujacych.
 *
 * Uruchomienie:
 *   wp eval-file tools/wystaw-partie.php 600 40
 *                                        |   |
 *                                        |   limit produktow (0 = bez limitu)
 *                                        budzet czasu w sekundach
 *
 * @package dawmac-allegro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$budzet = (int) ( $args[0] ?? 600 );
$limit  = (int) ( $args[1] ?? 0 );
$start  = time();

$dict = Dawmac_Allegro_Mapper::dictionary();

if ( ! $dict ) {
	echo "BŁĄD: brak słowników kategorii.\n";
	return;
}

$ids  = Dawmac_Allegro_Product_Data::in_stock_ids();
$stan = get_option( 'dawmac_allegro_partia', [ 'zrobione' => 0, 'pominiete' => 0, 'bledy' => [] ] );

$w_tej_partii = 0;
$nowe         = 0;
$odlozone     = 0;
$bledy        = 0;

foreach ( $ids as $pid ) {
	if ( time() - $start >= $budzet ) {
		echo "-- budżet czasu wyczerpany --\n";
		break;
	}

	if ( $limit > 0 && $w_tej_partii >= $limit ) {
		echo "-- limit partii osiągnięty --\n";
		break;
	}

	// Juz ma oferte - pomijamy bez zadnego zapytania do API.
	if ( Dawmac_Allegro_Offer::offer_id( $pid ) ) {
		continue;
	}

	$p = wc_get_product( $pid );

	if ( ! $p ) {
		continue;
	}

	++$w_tej_partii;

	$d = Dawmac_Allegro_Product_Data::from_wc( $p );

	// Watpliwe wykonczenie idzie na liste do weryfikacji, nie na oferte.
	if ( Dawmac_Allegro_Product_Data::watpliwe_wykonczenie( $d ) ) {
		++$odlozone;
		continue;
	}

	$wynik = Dawmac_Allegro_Offer::publish( $p, $dict, 'INACTIVE' );

	if ( is_wp_error( $wynik ) ) {
		++$bledy;
		$stan['bledy'][ $pid ] = mb_substr( $wynik->get_error_message(), 0, 160 );
		printf( "  BŁĄD  #%-7d %s\n", $pid, mb_substr( $wynik->get_error_message(), 0, 90 ) );
		continue;
	}

	++$nowe;
	printf( "  ok    #%-7d %-11s %s\n", $pid, $wynik, mb_substr( $p->get_name(), 0, 46 ) );
}

$stan['zrobione']  += $nowe;
$stan['pominiete'] += $odlozone;
update_option( 'dawmac_allegro_partia', $stan, false );

$zostalo = 0;
foreach ( $ids as $pid ) {
	if ( ! Dawmac_Allegro_Offer::offer_id( $pid ) ) {
		++$zostalo;
	}
}

printf(
	"\nPARTIA: nowe %d · odłożone %d · błędy %d · czas %ds\nŁĄCZNIE: utworzonych %d · zostało do przerobienia %d\n",
	$nowe, $odlozone, $bledy, time() - $start, $stan['zrobione'], $zostalo
);
