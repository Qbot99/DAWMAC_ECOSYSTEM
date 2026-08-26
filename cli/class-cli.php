<?php
/**
 * Komendy WP-CLI:
 *
 *   wp dawmac reindex          - pełny reindex wszystkich produktów (z pomiarem czasu)
 *   wp dawmac reindex --fresh  - najpierw DROP + CREATE tabeli, potem reindex
 *   wp dawmac status           - ile produktów/wierszy jest w indeksie
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Dawmac_Filters_CLI {

	/**
	 * Pełny reindex produktów do tabeli indeksowej.
	 *
	 * ## OPTIONS
	 *
	 * [--fresh]
	 * : Usuń i utwórz tabelę od zera przed indeksowaniem.
	 *
	 * ## EXAMPLES
	 *
	 *     wp dawmac reindex
	 *     wp dawmac reindex --fresh
	 */
	public function reindex( $args, $assoc_args ) {
		if ( ! empty( $assoc_args['fresh'] ) ) {
			WP_CLI::log( 'Odtwarzam tabelę od zera...' );
			Dawmac_Filters_Schema::drop_table();
			Dawmac_Filters_Schema::create_table();
		}

		$bar = null;

		$result = Dawmac_Filters_Indexer::reindex_all(
			function ( $done, $total ) use ( &$bar ) {
				if ( null === $bar ) {
					$bar = \WP_CLI\Utils\make_progress_bar( "Indeksuję {$total} produktów", $total );
				}
				$bar->tick( Dawmac_Filters_Indexer::BATCH_SIZE );
			}
		);

		if ( $bar ) {
			$bar->finish();
		}

		WP_CLI::success( sprintf(
			'Zaindeksowano %d produktów (%d wierszy indeksu) w %.2f s (%.0f produktów/s).',
			$result['products'],
			$result['rows'],
			$result['seconds'],
			$result['seconds'] > 0 ? $result['products'] / $result['seconds'] : 0
		) );
	}

	/**
	 * Testowe zapytanie filtrujące z pomiarem czasu.
	 *
	 * ## OPTIONS
	 *
	 * <query>
	 * : Filtry w formacie query-string, np. "pa_srednica=17&pa_rozstaw=5x112,5x120&price=200-900"
	 *
	 * ## EXAMPLES
	 *
	 *     wp dawmac query "pa_srednica=17&pa_rozstaw=5x112"
	 *     wp dawmac query "pa_et=et35&price=200-900"
	 */
	public function query( $args, $assoc_args ) {
		parse_str( $args[0], $raw );

		$filters = [];
		foreach ( $raw as $attr => $value ) {
			if ( preg_match( '/^(\d+(?:\.\d+)?)-(\d+(?:\.\d+)?)$/', (string) $value, $m ) ) {
				$filters[ $attr ] = [ 'min' => (float) $m[1], 'max' => (float) $m[2] ];
			} else {
				$filters[ $attr ] = explode( ',', (string) $value );
			}
		}

		$t   = microtime( true );
		$ids = Dawmac_Filters_Query::get_product_ids( $filters );
		$t1  = ( microtime( true ) - $t ) * 1000;

		$t        = microtime( true );
		$counters = Dawmac_Filters_Query::get_counters( $filters );
		$t2       = ( microtime( true ) - $t ) * 1000;

		WP_CLI::log( sprintf( 'Wyniki:   %d produktów w %.2f ms', count( $ids ), $t1 ) );
		$n_opts = array_sum( array_map( 'count', $counters ) );
		WP_CLI::log( sprintf( 'Countery: %d opcji w %d atrybutach w %.2f ms', $n_opts, count( $counters ), $t2 ) );
		WP_CLI::log( sprintf( 'RAZEM:    %.2f ms', $t1 + $t2 ) );

		if ( ! empty( $ids ) ) {
			WP_CLI::log( '' );
			WP_CLI::log( 'Przykładowe trafienia:' );
			foreach ( array_slice( $ids, 0, 5 ) as $id ) {
				WP_CLI::log( "  #{$id}  " . get_the_title( $id ) );
			}
		}
	}

	/**
	 * Statystyki indeksu: liczba produktów, wierszy i rozbicie po atrybutach.
	 */
	public function status( $args, $assoc_args ) {
		global $wpdb;
		$table = Dawmac_Filters_Schema::table_name();

		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		if ( ! $exists ) {
			WP_CLI::error( "Tabela {$table} nie istnieje - aktywuj wtyczkę albo odpal: wp dawmac reindex --fresh" );
		}

		$products = (int) $wpdb->get_var( "SELECT COUNT(DISTINCT product_id) FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL
		$rows     = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL

		WP_CLI::log( "Produktów w indeksie: {$products}" );
		WP_CLI::log( "Wierszy indeksu:      {$rows}" );
		WP_CLI::log( '' );

		$breakdown = $wpdb->get_results(
			"SELECT attribute, COUNT(*) AS cnt, COUNT(DISTINCT value_slug) AS distinct_values
			 FROM {$table} GROUP BY attribute ORDER BY cnt DESC" // phpcs:ignore WordPress.DB.PreparedSQL
		);

		\WP_CLI\Utils\format_items( 'table', $breakdown, [ 'attribute', 'cnt', 'distinct_values' ] );
	}
}
