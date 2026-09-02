<?php
/**
 * Produkt WooCommerce -> plaska tablica, ktora rozumie szablon.
 *
 * Warstwa istnieje po to, zeby class-template.php nie wiedzialo nic
 * o WordPressie. Szablon dostaje slownik pol, wiec da sie go odpalic
 * na fixture w tools/preview.php i w testach, bez stawiania sklepu.
 *
 * Mapowanie atrybutow bierzemy ze sklepu dawmac.pl - to te same taksonomie,
 * na ktorych stoi dawmac-filters:
 *
 *   pa_producent, pa_model, pa_srednica, pa_szerokosc,
 *   pa_rozstaw, pa_et, pa_kategoria-koloru        - felgi
 *   pa_producent, pa_model, pa_szerokosc_opony,
 *   pa_profil, pa_srednica_opony                  - opony
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Dawmac_Allegro_Product_Data {

	/** Taksonomia atrybutu -> pole w tablicy wynikowej. */
	const ATTRIBUTE_MAP = [
		'pa_producent'        => 'producent',
		'pa_model'            => 'model',
		'pa_srednica'         => 'srednica',
		'pa_szerokosc'        => 'szerokosc',
		'pa_rozstaw'          => 'rozstaw',
		'pa_et'               => 'et',
		'pa_kategoria-koloru' => 'kolor',
		// pa_kolor trzyma nazwe wykonczenia wprost ("Brushed Bronze"),
		// pa_kategoria-koloru tylko polke w katalogu ("Brazowe i zlote").
		'pa_kolor'            => 'kolor_nazwa',
		'pa_bore'             => 'bore',
		'pa_szerokosc_opony'  => 'szerokosc_opony',
		'pa_profil'           => 'profil',
		'pa_srednica_opony'   => 'srednica_opony',
	];

	/**
	 * Pola, w ktorych felga moze miec wiecej niz jedna wartosc.
	 *
	 * Rozstaw - felga wiercona pod dwa rozstawy (5x112/5x120).
	 * Szerokosc i ET - zestaw mieszany ma inny przod i tyl (8.5J + 10J).
	 *
	 * Zwijanie ich do pierwszej wartosci jest grozne: zestaw mieszany
	 * wygladalby wtedy na jednorodny i podpielibysmy pod oferte 4 felgi
	 * przednie zamiast 2+2. Kupujacy dostalby co innego, niz zamowil.
	 */
	const MULTI_VALUE = [ 'rozstaw', 'szerokosc', 'et' ];

	/** Slug kategorii opon - ten sam, ktorego uzywa dawmac-filters. */
	const TYRES_CAT = 'opony';

	/**
	 * @param WC_Product $product
	 * @return array Dane gotowe dla Dawmac_Allegro_Template.
	 */
	public static function from_wc( $product ): array {
		$id = $product->get_id();

		$data = [
			'id'        => $id,
			'sku'       => $product->get_sku(),
			'title'     => $product->get_name(),
			'opis'      => self::description( $product ),
			'cena'      => self::price( $product ),
			'stan'      => $product->get_stock_quantity(),
			'dostepny'  => $product->is_in_stock(),
			'kategoria' => self::category( $id ),
			'image'     => self::main_image_url( $product ),
			'gallery'   => self::gallery_urls( $product ),
		];

		foreach ( self::ATTRIBUTE_MAP as $taxonomy => $field ) {
			$values = self::terms( $id, $taxonomy );

			if ( ! $values ) {
				continue;
			}

			$data[ $field ] = in_array( $field, self::MULTI_VALUE, true )
				? $values
				: $values[0];
		}

		$data['liczba_srub'] = self::bolt_count( $data['rozstaw'] ?? null );
		$data['wykonczenie'] = self::finish( $data );
		$data['bore']        = self::bore( $data['bore'] ?? null );

		return $data;
	}

	/**
	 * Wartosci atrybutu. Bierzemy nazwy termow, nie slugi - w opisie ma byc
	 * "5x112", a nie "5x112-2" po deduplikacji WordPressa.
	 *
	 * @return string[]
	 */
	private static function terms( int $product_id, string $taxonomy ): array {
		if ( ! taxonomy_exists( $taxonomy ) ) {
			return [];
		}

		$terms = wp_get_post_terms( $product_id, $taxonomy, [ 'fields' => 'names' ] );

		if ( is_wp_error( $terms ) || ! $terms ) {
			return [];
		}

		return array_values( array_filter( array_map( 'trim', $terms ) ) );
	}

	/**
	 * Opis krotki ma pierwszenstwo - jest pisany jako zajawka, wiec lepiej
	 * nadaje sie na naglowek oferty niz pelna sciana tekstu z lokalizacja
	 * magazynowa w srodku.
	 */
	private static function description( $product ): string {
		$short = trim( (string) $product->get_short_description() );

		return '' !== $short ? $short : (string) $product->get_description();
	}

	/** Cena brutto jako string z kropka - tego oczekuje API Allegro. */
	private static function price( $product ): string {
		$price = $product->get_price();

		return '' === $price || null === $price
			? ''
			: number_format( (float) $price, 2, '.', '' );
	}

	/** 'opony' albo 'felgi' - decyduje, ktory zestaw parametrow pokazujemy. */
	private static function category( int $product_id ): string {
		$slugs = wp_get_post_terms( $product_id, 'product_cat', [ 'fields' => 'slugs' ] );

		if ( is_wp_error( $slugs ) ) {
			return 'felgi';
		}

		return in_array( self::TYRES_CAT, $slugs, true ) ? 'opony' : 'felgi';
	}

	private static function main_image_url( $product ): ?string {
		$id = $product->get_image_id();

		return $id ? ( wp_get_attachment_image_url( $id, 'full' ) ?: null ) : null;
	}

	/**
	 * Zdjecia do galerii oferty. Allegro przyjmuje max 16 zdjec na oferte,
	 * a pierwsze musi byc zdjeciem glownym.
	 *
	 * @return string[]
	 */
	private static function gallery_urls( $product ): array {
		$ids  = array_merge(
			array_filter( [ $product->get_image_id() ] ),
			$product->get_gallery_image_ids()
		);
		$urls = [];

		foreach ( array_unique( $ids ) as $id ) {
			$url = wp_get_attachment_image_url( (int) $id, 'full' );

			if ( $url ) {
				$urls[] = $url;
			}
		}

		return array_slice( $urls, 0, 16 );
	}

	/**
	 * Liczba srub z rozstawu: "5x112" -> 5. Allegro ma to osobnym parametrem
	 * wymaganym w kategorii felg, a sklep nie trzyma go jako atrybutu.
	 * Felga z dwoma rozstawami ma je o tej samej liczbie srub (5x112/5x120),
	 * wiec bierzemy pierwszy.
	 */
	public static function bolt_count( $rozstaw ): ?int {
		if ( is_array( $rozstaw ) ) {
			$rozstaw = $rozstaw[0] ?? '';
		}

		if ( ! preg_match( '/^\s*(\d+)\s*[xX]/', (string) $rozstaw, $m ) ) {
			return null;
		}

		$n = (int) $m[1];

		return ( $n >= 3 && $n <= 8 ) ? $n : null;
	}

	/**
	 * Wykonczenie felgi z tytulu produktu: "Brushed Bronze", "Matt Black".
	 *
	 * Atrybut pa_kategoria-koloru trzyma polke w katalogu sklepu ("Brazowe
	 * i zlote"), a nie nazwe wykonczenia - na ofercie wyglada to jak pomylka.
	 * Prawdziwa nazwa siedzi w tytule, wiec zdejmujemy z niego czesc
	 * techniczna, a to, co zostaje, jest wykonczeniem.
	 */
	public static function finish( array $data ): string {
		// Sklep trzyma nazwe wykonczenia w pa_kolor - to zrodlo pewne.
		// Rozbieranie tytulu jest tylko awaryjne, dla produktow bez atrybutu.
		$atrybut = trim( (string) ( $data['kolor_nazwa'] ?? '' ) );

		if ( '' !== $atrybut ) {
			return $atrybut;
		}

		$tytul = (string) ( $data['title'] ?? '' );

		if ( '' === $tytul ) {
			return '';
		}

		$do_wyciecia = array_filter( [
			$data['producent'] ?? '',
			$data['model'] ?? '',
		], static fn( $v ): bool => is_string( $v ) && '' !== trim( $v ) );

		foreach ( $do_wyciecia as $czesc ) {
			$tytul = preg_replace( '/' . preg_quote( (string) $czesc, '/' ) . '/iu', ' ', $tytul ) ?? $tytul;
		}

		$wzorce = [
			'/\bET\s*-?\d{1,3}\b/iu',            // ET35, ET-5
			'/\b\d{1,2}(?:[.,]\d)?\s*J\b/iu',      // 8.5J
			'/\b\d{1,2}(?:[.,]\d)?\s*x\s*\d{2}\b/iu', // 8.5x19
			// Caly ciag rozstawow naraz: "5x112/114.3" musi zniknac w calosci,
			// inaczej po wycieciu "5x112" zostaje samo "114.3".
			'/\b\d\s*x\s*\d{2,3}(?:[.,]\d)?(?:\s*\/\s*\d{2,3}(?:[.,]\d)?)*/iu',
			'/\b\d{2}\s*["\x27]{1,2}/u',           // 19 cali zapisane " albo ''
			'/\b\d{1,2}[.,]\d\b/u',              // luzna 10.5 po ucieciu J
			'/\bBLANK\b/iu',
			'/[\/+]/u',
		];

		$tytul = preg_replace( $wzorce, ' ', $tytul ) ?? $tytul;
		$tytul = preg_replace( '/\s+/u', ' ', $tytul ) ?? $tytul;
		$tytul = trim( $tytul, " \t\n\r-,." );

		// Same cyfry albo jeden znak to nie jest nazwa wykonczenia.
		return preg_match( '/\p{L}{3,}/u', $tytul ) ? $tytul : '';
	}

	/**
	 * Otwor centralny na postac ze slownika Allegro: "72,6".
	 *
	 * Sklep zapisuje go roznie - "72.6", "71,5", a bywa i "CB74.1" -
	 * wiec zdejmujemy litery i ujednolicamy separator na przecinek.
	 */
	public static function bore( $raw ): string {
		$v = is_array( $raw ) ? ( $raw[0] ?? '' ) : $raw;
		$v = str_replace( ',', '.', (string) $v );
		$v = preg_replace( '/[^0-9.]/', '', $v ) ?? '';

		if ( '' === $v || ! is_numeric( $v ) ) {
			return '';
		}

		// 56.00 -> 56, 72.60 -> 72,6
		$v = rtrim( rtrim( number_format( (float) $v, 2, '.', '' ), '0' ), '.' );

		return str_replace( '.', ',', $v );
	}

	/**
	 * ID produktow do wystawienia. Zakres uzgodniony: tylko to, co realnie
	 * lezy na magazynie - oferta na towar, ktorego nie ma, konczy sie
	 * anulowaniem zamowienia, a to bije w ranking ofert i Super Sprzedawce.
	 *
	 * Czytamy z tabeli dawmac-filters zamiast z meta_query, bo indeks ma
	 * to gotowe i nie tyka wp_postmeta przy 32 tys. produktow.
	 *
	 * @return int[]
	 */
	public static function in_stock_ids( int $limit = 0 ): array {
		global $wpdb;

		$index = $wpdb->prefix . 'dawmac_filter_index';
		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $index ) );

		if ( $exists !== $index ) {
			// Bez indeksu dawmac-filters wracamy do zwyklego zapytania Woo.
			return wc_get_products( [
				'status'       => 'publish',
				'stock_status' => 'instock',
				'limit'        => $limit > 0 ? $limit : -1,
				'return'       => 'ids',
			] );
		}

		$sql = "SELECT product_id FROM {$index}
		        WHERE attribute = 'stock' AND value_slug = 'instock'
		        ORDER BY product_id ASC";

		if ( $limit > 0 ) {
			$sql .= $wpdb->prepare( ' LIMIT %d', $limit );
		}

		return array_map( 'intval', $wpdb->get_col( $sql ) );
	}
}
