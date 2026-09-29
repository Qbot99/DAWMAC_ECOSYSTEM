<?php
/**
 * Stan magazynowy: oferta na Allegro ma odpowiadac temu, co lezy w sklepie.
 *
 * Bez tego sprzedana w sklepie felga wisiala dalej na Allegro i mozna ja bylo
 * kupic drugi raz - 29.09.2026 znalazly sie cztery takie oferty.
 *
 * Dwa tory:
 *   1. Natychmiast - zmiana stanu produktu w WooCommerce kolejkuje
 *      synchronizacje tej jednej oferty. Wysylka idzie w tle, zeby nie
 *      spowalniac zamowienia w sklepie.
 *   2. Raz na dobe - pelne uzgodnienie wszystkich ofert, jako siatka
 *      bezpieczenstwa na zdarzenia, ktore ominely tor pierwszy.
 *
 * @package dawmac-allegro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Dawmac_Allegro_Stock {

	const HOOK_JEDEN = 'dawmac_allegro_stan_jeden';
	const CRON       = 'dawmac_allegro_stan';

	/**
	 * Znacznik "te oferte zakonczyl brak towaru". Wznawiamy WYLACZNIE takie.
	 *
	 * Oferty konczone z innych powodow - bo opis mowil "nowe" o felgach
	 * ex-demo albo uszkodzonych - nie moga wrocic same tylko dlatego, ze
	 * towar jest na stanie. Wrocilyby z tym samym klamliwym opisem.
	 */
	const ZNACZNIK = '_dawmac_allegro_konczona_przez_stan';

	public static function init(): void {
		// Zmiana statusu i zmiana ilosci - WooCommerce odpala oba przy
		// zamowieniu, przy recznej edycji i przy imporcie.
		add_action( 'woocommerce_product_set_stock_status', [ __CLASS__, 'kolejkuj' ], 20, 1 );
		add_action( 'woocommerce_product_set_stock', [ __CLASS__, 'kolejkuj_produkt' ], 20, 1 );

		add_action( self::HOOK_JEDEN, [ __CLASS__, 'sync_jeden' ], 10, 1 );
		add_action( self::CRON, [ __CLASS__, 'uzgodnij' ] );

		if ( ! wp_next_scheduled( self::CRON ) ) {
			// 4:15 - po synchronizacji cen, poza szczytem ruchu.
			wp_schedule_event( strtotime( 'tomorrow 4:15' ), 'daily', self::CRON );
		}
	}

	/** @param WC_Product $product */
	public static function kolejkuj_produkt( $product ): void {
		if ( $product instanceof WC_Product ) {
			self::kolejkuj( $product->get_id() );
		}
	}

	/**
	 * Wstawia synchronizacje jednej oferty do kolejki w tle.
	 *
	 * Action Scheduler (czesc WooCommerce) ponawia nieudane zadania;
	 * gdy go brak, wracamy do zwyklego zdarzenia WordPressa.
	 */
	public static function kolejkuj( $product_id ): void {
		$pid = (int) $product_id;

		if ( $pid <= 0 || ! Dawmac_Allegro_Offer::offer_id( $pid ) ) {
			return;
		}

		if ( function_exists( 'as_enqueue_async_action' ) ) {
			as_enqueue_async_action( self::HOOK_JEDEN, [ $pid ], 'dawmac-allegro' );
			return;
		}

		if ( ! wp_next_scheduled( self::HOOK_JEDEN, [ $pid ] ) ) {
			wp_schedule_single_event( time(), self::HOOK_JEDEN, [ $pid ] );
		}
	}

	/** Czy produkt jest fizycznie dostepny. */
	public static function dostepny( WC_Product $p ): bool {
		if ( 'instock' !== $p->get_stock_status() ) {
			return false;
		}

		if ( $p->managing_stock() ) {
			return (int) $p->get_stock_quantity() > 0;
		}

		return true;
	}

	/**
	 * Doprowadza jedna oferte do zgodnosci ze sklepem.
	 *
	 * @return string co zrobiono: zakonczona, wznowiona, ilosc, bez_zmian, blad:...
	 */
	public static function sync_jeden( $product_id ): string {
		$pid = (int) $product_id;
		$oid = Dawmac_Allegro_Offer::offer_id( $pid );
		$p   = wc_get_product( $pid );

		if ( ! $oid || ! $p ) {
			return 'bez_oferty';
		}

		$o = Dawmac_Allegro_Client::get( "/sale/product-offers/{$oid}" );

		if ( is_wp_error( $o ) ) {
			return 'blad:' . $o->get_error_message();
		}

		$status = $o['publication']['status'] ?? '';
		$jest   = self::dostepny( $p );

		// Towaru nie ma, oferta zyje - konczymy, zanim ktos kupi.
		if ( ! $jest && 'ACTIVE' === $status ) {
			$r = self::komenda( $oid, 'END' );

			if ( is_wp_error( $r ) ) {
				return 'blad:' . $r->get_error_message();
			}

			update_post_meta( $pid, self::ZNACZNIK, time() );
			return 'zakonczona';
		}

		if ( ! $jest ) {
			return 'bez_zmian';
		}

		// Towar wrocil - wznawiamy, ale tylko oferte zakonczona przez brak
		// towaru, i przez sciezke z kontrola tresci.
		if ( 'ENDED' === $status ) {
			if ( ! get_post_meta( $pid, self::ZNACZNIK, true ) ) {
				return 'zakonczona_recznie';
			}

			$r = Dawmac_Allegro_Offer::aktywuj( $oid );

			if ( is_wp_error( $r ) ) {
				return 'blad:' . $r->get_error_message();
			}

			delete_post_meta( $pid, self::ZNACZNIK );
			return 'wznowiona';
		}

		// Oferta aktywna - pilnujemy tylko ilosci.
		$ilosc = $p->managing_stock() ? max( 1, (int) $p->get_stock_quantity() ) : 1;

		if ( 'ACTIVE' === $status && (int) ( $o['stock']['available'] ?? 0 ) !== $ilosc ) {
			$r = Dawmac_Allegro_Client::patch( "/sale/product-offers/{$oid}", [
				'stock' => [ 'available' => $ilosc, 'unit' => $o['stock']['unit'] ?? 'SET' ],
			] );
			return is_wp_error( $r ) ? 'blad:' . $r->get_error_message() : 'ilosc';
		}

		return 'bez_zmian';
	}

	/**
	 * Pelne uzgodnienie: kazda oferta kontra stan w sklepie.
	 *
	 * @return array<string,int> licznik wynikow
	 */
	public static function uzgodnij(): array {
		global $wpdb;

		$wynik = [];
		$pids  = $wpdb->get_col(
			"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key='_dawmac_allegro_offer_id'"
		);

		foreach ( $pids as $pid ) {
			$co = self::sync_jeden( (int) $pid );
			$k  = str_starts_with( $co, 'blad:' ) ? 'blad' : $co;

			$wynik[ $k ] = ( $wynik[ $k ] ?? 0 ) + 1;

			if ( in_array( $k, [ 'zakonczona', 'wznowiona', 'ilosc' ], true ) ) {
				usleep( 400000 );
			}
		}

		update_option( 'dawmac_allegro_stan_ostatnio', [ 'kiedy' => time() ] + $wynik, false );

		return $wynik;
	}

	/** Komenda publikacji dla jednej oferty (END albo ACTIVATE). */
	private static function komenda( string $oid, string $akcja ) {
		return Dawmac_Allegro_Client::put(
			'/sale/offer-publication-commands/' . wp_generate_uuid4(),
			[
				'publication'   => [ 'action' => $akcja ],
				'offerCriteria' => [ [ 'offers' => [ [ 'id' => $oid ] ], 'type' => 'CONTAINS_OFFERS' ] ],
			]
		);
	}
}
