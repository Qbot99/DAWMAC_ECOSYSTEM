<?php
/**
 * Schemat tabeli indeksowej.
 *
 * Model "wysoki": jeden wiersz = jedna para (produkt, wartość atrybutu).
 * Produkt z 12 atrybutami ma ~12-15 wierszy. Przy 30k produktów daje to
 * ok. 400-500k wierszy - dla MySQL z dobrym indeksem to nic.
 *
 * Dlaczego nie szeroka tabela (kolumna na atrybut)?
 * 1. Felga może mieć DWA rozstawy (5x112/5x120) - w szerokiej tabeli
 *    nie ma gdzie tego zapisać bez brzydkich hacków.
 * 2. Dodanie nowego atrybutu w przyszłości = zero zmian w schemacie.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Dawmac_Filters_Schema {

	const TABLE = 'dawmac_filter_index';

	/**
	 * Pełna nazwa tabeli z prefixem WP (np. wp_dawmac_filter_index).
	 */
	public static function table_name(): string {
		global $wpdb;
		return $wpdb->prefix . self::TABLE;
	}

	public static function create_table(): void {
		global $wpdb;

		$table   = self::table_name();
		$charset = $wpdb->get_charset_collate();

		// Kluczowe są INDEKSY na dole:
		// - attr_value: serce filtrowania - "daj mi produkty gdzie
		//   pa_rozstaw = 5x112". MySQL skacze po indeksie prosto do celu.
		//   product_id jest w indeksie trzecią kolumną, więc zapytanie
		//   w ogóle nie dotyka danych tabeli (covering index).
		// - attr_numeric: to samo dla zakresów (cena 200-500, ET 30-45).
		// - product: szybkie kasowanie wierszy produktu przy reindeksie.
		$sql = "CREATE TABLE {$table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			product_id BIGINT UNSIGNED NOT NULL,
			attribute VARCHAR(64) NOT NULL,
			value_slug VARCHAR(191) NOT NULL DEFAULT '',
			value_label VARCHAR(191) NOT NULL DEFAULT '',
			value_numeric DECIMAL(14,4) NULL,
			term_id BIGINT UNSIGNED NULL,
			PRIMARY KEY  (id),
			KEY attr_value (attribute, value_slug, product_id),
			KEY attr_numeric (attribute, value_numeric, product_id),
			KEY prod_attr_num (product_id, attribute, value_numeric)
		) {$charset};";
		// prod_attr_num prowadzi po product_id, więc zastępuje dawny indeks
		// product(product_id) (kasowanie wierszy produktu) i JEDNOCZEŚNIE robi
		// ciasny lookup w EXISTS zakresowym ET (bez niego zakres ET ~500 ms,
		// z nim ~30-110 ms).

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		// Tabela kart produktów: wszystko, czego frontend potrzebuje do
		// wyrenderowania kafelka, bez dotykania wp_posts/wp_postmeta
		// w czasie requestu. Jeden wiersz na produkt.
		// search_text = tytuł + oczyszczony opis; po nim szuka wyszukiwarka,
		// dzięki czemu znajduje też lokalizacje magazynowe z opisu (np. "42.L / NHB").
		$cards = self::cards_table_name();
		$sql2  = "CREATE TABLE {$cards} (
			product_id BIGINT UNSIGNED NOT NULL,
			title VARCHAR(255) NOT NULL DEFAULT '',
			url VARCHAR(255) NOT NULL DEFAULT '',
			price DECIMAL(14,2) NULL,
			regular_price DECIMAL(14,2) NULL,
			stock VARCHAR(20) NOT NULL DEFAULT 'instock',
			thumb VARCHAR(255) NOT NULL DEFAULT '',
			search_text LONGTEXT NULL,
			PRIMARY KEY  (product_id)
		) {$charset};";
		dbDelta( $sql2 );

		update_option( 'dawmac_filters_db_version', DAWMAC_FILTERS_VERSION );
	}

	/**
	 * Pełna nazwa tabeli kart (np. wp_dawmac_filter_cards).
	 */
	public static function cards_table_name(): string {
		global $wpdb;
		return $wpdb->prefix . self::TABLE . '_cards';
	}

	/**
	 * Usunięcie tabeli - używane przy pełnym rebuildzie od zera.
	 */
	public static function drop_table(): void {
		global $wpdb;
		$table = self::table_name();
		$cards = self::cards_table_name();
		$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL
		$wpdb->query( "DROP TABLE IF EXISTS {$cards}" ); // phpcs:ignore WordPress.DB.PreparedSQL
	}
}
