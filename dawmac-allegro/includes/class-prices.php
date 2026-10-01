<?php
/**
 * Synchronizacja cen ofert z cenami w sklepie.
 *
 * Oferta ma cene z momentu wystawienia - zmiana ceny produktu w sklepie
 * nie przenosi sie sama. Ta klasa wyrownuje roznice wedlug reguly cennika:
 * marki z listy 'bez_narzutu' ida po cenie sklepowej, pozostale dostaja
 * staly narzut pokrywajacy prowizje Allegro.
 *
 * @package dawmac-allegro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Dawmac_Allegro_Prices {

	/** Nazwa zdarzenia cyklicznego. */
	const CRON = 'dawmac_allegro_ceny';

	/** Odstep miedzy publikacjami, zeby nie wywolac zabezpieczen Allegro. */
	const PRZERWA_US = 250000;

	/**
	 * Cena oferty dla produktu, wedlug reguly z konfiguracji.
	 *
	 * @return float 0 gdy produkt nie ma ceny
	 */
	public static function docelowa( WC_Product $product, array $dane, array $config ): float {
		$baza = (float) $product->get_price();

		if ( $baza <= 0 ) {
			return 0.0;
		}

		$cennik = $config['cennik'] ?? [];
		$marka  = mb_strtolower( trim( (string) ( $dane['producent'] ?? '' ) ), 'UTF-8' );

		foreach ( (array) ( $cennik['bez_narzutu'] ?? [] ) as $wolna ) {
			if ( mb_strtolower( trim( (string) $wolna ), 'UTF-8' ) === $marka ) {
				return $baza;
			}
		}

		return $baza + (float) ( $cennik['narzut'] ?? 0 );
	}

	/**
	 * Wyrownuje ceny wszystkich ofert. Dotyka tylko tych, ktore sie roznia.
	 *
	 * @param bool $na_sucho Gdy true, tylko raportuje bez zmian na Allegro.
	 * @return array{zmienione:int,bez_zmian:int,bledy:int,roznica:float,szczegoly:string[]}
	 */
	public static function sync( bool $na_sucho = false ): array {
		global $wpdb;

		$config = dawmac_allegro_config();

		$wynik = [
			'zmienione' => 0,
			'bez_zmian' => 0,
			'bledy'     => 0,
			'roznica'   => 0.0,
			'szczegoly' => [],
		];

		$rows = $wpdb->get_results(
			"SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key='_dawmac_allegro_offer_id'"
		);

		foreach ( $rows as $r ) {
			$p = wc_get_product( (int) $r->post_id );

			if ( ! $p ) {
				continue;
			}

			$o = Dawmac_Allegro_Client::get( "/sale/product-offers/{$r->meta_value}" );

			if ( is_wp_error( $o ) || 'ENDED' === ( $o['publication']['status'] ?? '' ) ) {
				continue;
			}

			$dane     = Dawmac_Allegro_Product_Data::from_wc( $p );
			$docelowa = self::docelowa( $p, $dane, $config );

			if ( $docelowa <= 0 ) {
				continue;
			}

			// Cennik dostawy pilnowany przy okazji - to tez czesc tego, ile
			// placi kupujacy. 1.10.2026 okazalo sie, ze 735 ofert mialo
			// darmowa wysylke; tego samego dnia doszly cenniki wedlug srednicy.
			$dostawa = Dawmac_Allegro_Offer::cennik_dostawy( $dane, $config['oferta']['cenniki_dostawy'] ?? [] );

			if ( '' !== $dostawa && ( $o['delivery']['shippingRates']['id'] ?? '' ) !== $dostawa && ! $na_sucho ) {
				$d = Dawmac_Allegro_Client::patch( "/sale/product-offers/{$r->meta_value}", [
					'delivery' => [
						'shippingRates' => [ 'id' => $dostawa ],
						'handlingTime'  => $o['delivery']['handlingTime'] ?? 'PT48H',
					],
				] );

				if ( ! is_wp_error( $d ) ) {
					$wynik['dostawa'] = ( $wynik['dostawa'] ?? 0 ) + 1;
				}
			}

			$obecna = (float) ( $o['sellingMode']['price']['amount'] ?? 0 );

			if ( abs( $docelowa - $obecna ) < 0.01 ) {
				++$wynik['bez_zmian'];
				continue;
			}

			$wynik['szczegoly'][] = sprintf(
				'%s: %.2f -> %.2f zł (%s)',
				mb_substr( (string) ( $o['name'] ?? '' ), 0, 44 ),
				$obecna,
				$docelowa,
				$dane['producent'] ?? '?'
			);

			if ( $na_sucho ) {
				++$wynik['zmienione'];
				$wynik['roznica'] += $docelowa - $obecna;
				continue;
			}

			$w = Dawmac_Allegro_Client::patch( "/sale/product-offers/{$r->meta_value}", [
				'sellingMode' => [
					'price' => [ 'amount' => number_format( $docelowa, 2, '.', '' ), 'currency' => 'PLN' ],
				],
			] );

			if ( is_wp_error( $w ) ) {
				++$wynik['bledy'];
				continue;
			}

			++$wynik['zmienione'];
			$wynik['roznica'] += $docelowa - $obecna;

			usleep( self::PRZERWA_US );
		}

		update_option( 'dawmac_allegro_ceny_ostatnio', [
			'kiedy'     => time(),
			'zmienione' => $wynik['zmienione'],
			'bledy'     => $wynik['bledy'],
			'roznica'   => $wynik['roznica'],
		], false );

		return $wynik;
	}

	/**
	 * Zadanie dobowe. Rejestrujemy je raz; WordPress sam pilnuje terminu.
	 */
	public static function init(): void {
		add_action( self::CRON, [ __CLASS__, 'sync' ] );

		if ( ! wp_next_scheduled( self::CRON ) ) {
			// Start o 4 rano - poza szczytem ruchu w sklepie.
			wp_schedule_event( strtotime( 'tomorrow 4:00' ), 'daily', self::CRON );
		}
	}
}
