<?php
/**
 * Indekser: przepisuje dane produktów do płaskiej tabeli indeksowej.
 *
 * Co trafia do indeksu (kolumna `attribute`):
 *  - wszystkie atrybuty produktowe WooCommerce (taksonomie pa_*),
 *  - kategorie (product_cat),
 *  - cena jako 'price' (value_numeric - do filtrów zakresowych),
 *  - stan magazynowy jako 'stock' (instock / outofstock / onbackorder).
 *
 * Strategia wydajności: pracujemy WSADOWO. Dla paczki 500 produktów
 * wykonujemy 3 SELECT-y + 1 DELETE + 1 multi-INSERT, zamiast
 * setek pojedynczych zapytań, które robi WordPress przy podejściu
 * "po bożemu" (wc_get_product w pętli).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Dawmac_Filters_Indexer {

	const BATCH_SIZE = 500;

	/**
	 * Pełny reindex wszystkich opublikowanych produktów.
	 *
	 * @param callable|null $progress Callback ( $done, $total ) do raportowania postępu (używa go WP-CLI).
	 * @return array{products:int, rows:int, seconds:float}
	 */
	public static function reindex_all( ?callable $progress = null ): array {
		global $wpdb;

		$start = microtime( true );

		// Same ID-ki, posortowane - lekkie zapytanie nawet dla 30k wierszy.
		$ids = $wpdb->get_col(
			"SELECT ID FROM {$wpdb->posts}
			 WHERE post_type = 'product' AND post_status = 'publish'
			 ORDER BY ID ASC"
		);

		$total     = count( $ids );
		$done      = 0;
		$rows      = 0;
		$batches   = array_chunk( array_map( 'intval', $ids ), self::BATCH_SIZE );

		foreach ( $batches as $batch ) {
			$rows += self::index_batch( $batch );
			$done += count( $batch );
			if ( $progress ) {
				$progress( $done, $total );
			}
		}

		// Rozgrzej cache list opcji do sidebara. Bez tego pierwsze wejście
		// na sklep po reindeksie płaci ~1,5 s za GROUP BY po całym indeksie;
		// tutaj policzymy to raz, w tle CLI.
		self::warm_counters_cache();

		return [
			'products' => $total,
			'rows'     => $rows,
			'seconds'  => round( microtime( true ) - $start, 2 ),
		];
	}

	/**
	 * Przelicza i zapisuje cache list opcji sidebara (to samo, co robi
	 * endpoint przy pierwszym żądaniu z options=1) - dla atrybutów UI.
	 */
	public static function warm_counters_cache(): void {
		global $wpdb;

		if ( ! class_exists( 'Dawmac_Filters_Query' ) || ! class_exists( 'Dawmac_Filters_Frontend' ) ) {
			return;
		}

		// Felgi (cały katalog).
		$attrs    = array_keys( Dawmac_Filters_Frontend::ATTRIBUTES );
		$counters = Dawmac_Filters_Query::get_counters( [], $attrs );
		update_option( self::cache_key_for( 'shop' ), wp_json_encode( $counters ), 'no' );

		// Opony: listy liczone TYLKO wśród opon - inaczej w filtrze
		// producenta wylądowałoby 150 marek felg zamiast 10 marek opon.
		$tyre_attrs    = array_keys( Dawmac_Filters_Frontend::ATTRIBUTES_TYRES );
		$tyre_counters = Dawmac_Filters_Query::get_counters(
			[ 'product_cat' => [ Dawmac_Filters_Native::TYRES_CAT ] ],
			$tyre_attrs
		);
		update_option( self::cache_key_for( Dawmac_Filters_Native::TYRES_CAT ), wp_json_encode( $tyre_counters ), 'no' );

		// Właśnie rozgrzaliśmy - skasuj ewentualny zaplanowany warm, żeby
		// nie liczyć drugi raz (np. po pełnym reindeksie, który woła to wprost).
		if ( function_exists( 'wp_clear_scheduled_hook' ) ) {
			wp_clear_scheduled_hook( self::WARM_HOOK );
		}
	}

	/**
	 * Reindex pojedynczego produktu - podpięty pod hooki zapisu,
	 * więc indeks aktualizuje się sam po każdej edycji w adminie.
	 */
	public static function index_product( int $product_id ): void {
		if ( 'product' !== get_post_type( $product_id ) ) {
			return;
		}
		self::index_batch( [ $product_id ] );
	}

	/**
	 * Usunięcie produktu z indeksu (hook before_delete_post).
	 */
	public static function delete_product( int $post_id ): void {
		if ( 'product' !== get_post_type( $post_id ) ) {
			return;
		}
		self::purge_product( $post_id );
	}

	/**
	 * Zmiana statusu produktu (kosz, szkic, prywatny, przywrócenie).
	 *
	 * before_delete_post łapie TYLKO trwałe usunięcie - produkt wrzucony do
	 * kosza zostawał w indeksie jako "duch" i mógł wyskoczyć w wynikach
	 * (endpoint czyta nasz indeks, nie wp_posts). Tu domykamy cykl życia.
	 */
	public static function on_status_change( $new_status, $old_status, $post ): void {
		if ( ! $post instanceof WP_Post || 'product' !== $post->post_type || $new_status === $old_status ) {
			return;
		}
		if ( 'publish' === $new_status ) {
			self::index_batch( [ (int) $post->ID ] );   // publikacja / przywrócenie z kosza
		} elseif ( 'publish' === $old_status ) {
			self::purge_product( (int) $post->ID );     // kosz / szkic / prywatny
		}
	}

	/** Usuwa produkt z obu tabel i unieważnia listy filtrów. */
	private static function purge_product( int $post_id ): void {
		global $wpdb;
		$wpdb->delete( Dawmac_Filters_Schema::table_name(), [ 'product_id' => $post_id ], [ '%d' ] );
		$wpdb->delete( Dawmac_Filters_Schema::cards_table_name(), [ 'product_id' => $post_id ], [ '%d' ] );

		// Zniknięty produkt mógł być jedynym nosicielem jakiejś wartości -
		// unieważnij cache list opcji sidebara (tak jak przy edycji).
		self::invalidate_cache();
	}

	const CACHE_OPTION = 'dawmac_filters_counters_cache';
	const WARM_HOOK    = 'dawmac_filters_warm_cache';

	/** Klucz cache list opcji dla kontekstu ('shop' | 'opony'). */
	public static function cache_key_for( string $context ): string {
		return 'shop' === $context || '' === $context
			? self::CACHE_OPTION
			: self::CACHE_OPTION . '_' . sanitize_key( $context );
	}

	/**
	 * Unieważnia cache list filtrów NATYCHMIAST (poprawność), a przeliczenie
	 * zleca w tle przez WP-Cron - z debounce, żeby seria zapisów (import CSV,
	 * masowa edycja) zaplanowała rozgrzanie tylko RAZ, a nie tysiąc razy.
	 * Gdyby cron nie zdążył, endpoint i tak sam doliczy przy pierwszym odczycie.
	 */
	private static function invalidate_cache(): void {
		delete_option( self::CACHE_OPTION );
		delete_option( self::cache_key_for( 'opony' ) );

		if ( function_exists( 'wp_next_scheduled' ) && ! wp_next_scheduled( self::WARM_HOOK ) ) {
			// +60 s: okno na skleszczenie wielu zapisów w jedno przeliczenie.
			wp_schedule_single_event( time() + 60, self::WARM_HOOK );
		}
	}

	/**
	 * Właściwa robota: zaindeksuj paczkę produktów.
	 *
	 * @param int[] $ids Lista ID produktów (już zwalidowane inty).
	 * @return int Liczba wstawionych wierszy.
	 */
	private static function index_batch( array $ids ): int {
		global $wpdb;

		if ( empty( $ids ) ) {
			return 0;
		}

		$table   = Dawmac_Filters_Schema::table_name();
		$id_list = implode( ',', $ids ); // bezpieczne: same inty po array_map('intval')

		// 1. Czyścimy stare wiersze tej paczki (reindex = delete + insert,
		//    prościej i pewniej niż wyliczanie różnic).
		$wpdb->query( "DELETE FROM {$table} WHERE product_id IN ({$id_list})" ); // phpcs:ignore WordPress.DB.PreparedSQL

		$rows = [];

		// 2. Atrybuty (pa_*) i kategorie - JEDNYM zapytaniem dla całej paczki.
		$terms = $wpdb->get_results(
			"SELECT tr.object_id AS product_id, tt.taxonomy, t.term_id, t.slug, t.name
			 FROM {$wpdb->term_relationships} tr
			 JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
			 JOIN {$wpdb->terms} t          ON t.term_id = tt.term_id
			 WHERE tr.object_id IN ({$id_list})
			   AND (tt.taxonomy LIKE 'pa\_%' OR tt.taxonomy = 'product_cat')" // phpcs:ignore WordPress.DB.PreparedSQL
		);

		foreach ( $terms as $t ) {
			$rows[] = [
				'product_id'    => (int) $t->product_id,
				'attribute'     => $t->taxonomy,
				'value_slug'    => $t->slug,
				'value_label'   => $t->name,
				'value_numeric' => self::extract_numeric( $t->name ),
				'term_id'       => (int) $t->term_id,
			];
		}

		// 3. Cena i stan magazynowy z postmeta - też jednym zapytaniem
		//    (plus dane do kart: cena regularna i miniatura).
		$meta = $wpdb->get_results(
			"SELECT post_id AS product_id, meta_key, meta_value
			 FROM {$wpdb->postmeta}
			 WHERE post_id IN ({$id_list})
			   AND meta_key IN ('_price', '_regular_price', '_stock_status', '_thumbnail_id')" // phpcs:ignore WordPress.DB.PreparedSQL
		);

		$card_meta = [];
		foreach ( $meta as $m ) {
			$card_meta[ (int) $m->product_id ][ $m->meta_key ] = $m->meta_value;
		}

		foreach ( $meta as $m ) {
			if ( '_price' === $m->meta_key && '' !== $m->meta_value ) {
				$rows[] = [
					'product_id'    => (int) $m->product_id,
					'attribute'     => 'price',
					'value_slug'    => '',
					'value_label'   => $m->meta_value,
					'value_numeric' => (float) $m->meta_value,
					'term_id'       => null,
				];
			}
			if ( '_stock_status' === $m->meta_key ) {
				$rows[] = [
					'product_id'    => (int) $m->product_id,
					'attribute'     => 'stock',
					'value_slug'    => $m->meta_value,
					'value_label'   => $m->meta_value,
					'value_numeric' => null,
					'term_id'       => null,
				];
			}
		}

		// 4. Jeden zbiorczy INSERT dla całej paczki.
		self::bulk_insert( $rows );

		// 5. Karty produktów: tytuł + slug z wp_posts, miniatura
		//    z _wp_attached_file załącznika. Frontend dostanie gotowe
		//    dane bez dotykania wp_posts/wp_postmeta w czasie requestu.
		self::build_cards( $ids, $card_meta );

		// 6. Dane się zmieniły -> unieważnij (i zaplanuj rozgrzanie) cache.
		self::invalidate_cache();

		return count( $rows );
	}

	/**
	 * Tekst do przeszukiwania: tytuł + opis oczyszczony z HTML.
	 *
	 * Dzięki temu wyszukiwarka znajduje rzeczy, które są TYLKO w opisie -
	 * m.in. lokalizacje magazynowe w formacie "342.L / NH9" (kod hali:
	 * NHB, PP, P1, NH...). Encje HTML rozwijamy, białe znaki zwijamy,
	 * żeby "42.L/NHB" i "42.L / NHB" dało się znaleźć tak samo.
	 */
	private static function searchable_text( string $title, string $excerpt, string $content ): string {
		$raw = $title . ' ' . $excerpt . ' ' . $content;
		$raw = strip_shortcodes( $raw );
		$raw = wp_strip_all_tags( $raw, true );
		$raw = html_entity_decode( $raw, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$raw = str_replace( "\xc2\xa0", ' ', $raw );          // twarda spacja z edytora
		$raw = preg_replace( '/\s+/u', ' ', $raw );
		// "342.L / NH9" -> "342.L/NH9": po normalizacji obie pisownie
		// (ze spacjami i bez) trafiają w ten sam zapis w bazie.
		$raw = preg_replace( '#\s*/\s*#u', '/', (string) $raw );
		$raw = trim( (string) $raw );

		// Bezpiecznik na patologicznie długie opisy (średnia ~2 kB, max ~20 kB).
		return function_exists( 'mb_substr' ) ? mb_substr( $raw, 0, 8000 ) : substr( $raw, 0, 8000 );
	}

	/**
	 * Buduje/odświeża wiersze w tabeli kart dla paczki produktów.
	 *
	 * @param int[] $ids       ID produktów.
	 * @param array $card_meta [product_id][meta_key] => meta_value (już pobrane).
	 */
	private static function build_cards( array $ids, array $card_meta ): void {
		global $wpdb;

		$cards_table = Dawmac_Filters_Schema::cards_table_name();
		$id_list     = implode( ',', $ids );

		$posts = $wpdb->get_results(
			"SELECT ID, post_title, post_name, post_excerpt, post_content
			 FROM {$wpdb->posts} WHERE ID IN ({$id_list})" // phpcs:ignore WordPress.DB.PreparedSQL
		);

		// Miniatury: id załącznika -> ścieżka pliku w rozmiarze
		// woocommerce_thumbnail - DOKŁADNIE ten sam plik, który serwuje
		// natywna karta sklepu (kadrowanie/waga identyczne; oryginały
		// potrafią ważyć po kilka MB i mieć inny kadr).
		$thumb_ids = array_filter( array_map(
			fn( $m ) => (int) ( $m['_thumbnail_id'] ?? 0 ),
			$card_meta
		) );
		$files = [];
		if ( $thumb_ids ) {
			$thumb_list = implode( ',', array_unique( $thumb_ids ) );
			$res = $wpdb->get_results(
				"SELECT post_id, meta_key, meta_value FROM {$wpdb->postmeta}
				 WHERE post_id IN ({$thumb_list})
				   AND meta_key IN ('_wp_attached_file', '_wp_attachment_metadata')" // phpcs:ignore WordPress.DB.PreparedSQL
			);
			$attached = [];
			$sized    = [];
			foreach ( $res as $r ) {
				$aid = (int) $r->post_id;
				if ( '_wp_attached_file' === $r->meta_key ) {
					$attached[ $aid ] = $r->meta_value;
					continue;
				}
				$meta = maybe_unserialize( $r->meta_value );
				$file = $meta['sizes']['woocommerce_thumbnail']['file'] ?? '';
				if ( '' !== $file ) {
					$sized[ $aid ] = $file; // sama nazwa pliku, bez katalogu
				}
			}
			foreach ( $attached as $aid => $orig ) {
				if ( isset( $sized[ $aid ] ) ) {
					$dir           = dirname( $orig );
					$files[ $aid ] = ( '.' === $dir ? '' : $dir . '/' ) . $sized[ $aid ];
				} else {
					$files[ $aid ] = $orig; // brak wygenerowanej miniatury: oryginał
				}
			}
		}

		$values = [];
		foreach ( $posts as $p ) {
			$pid   = (int) $p->ID;
			$meta  = $card_meta[ $pid ] ?? [];
			$thumb = $files[ (int) ( $meta['_thumbnail_id'] ?? 0 ) ] ?? '';

			$values[] = $wpdb->prepare(
				'(%d, %s, %s, %s, %s, %s, %s, %s)',
				$pid,
				$p->post_title,
				$p->post_name,
				( $meta['_price'] ?? '' ) !== '' ? $meta['_price'] : 'NULL_SENTINEL',
				( $meta['_regular_price'] ?? '' ) !== '' ? $meta['_regular_price'] : 'NULL_SENTINEL',
				$meta['_stock_status'] ?? 'instock',
				$thumb,
				self::searchable_text( $p->post_title, $p->post_excerpt, $p->post_content )
			);
		}

		if ( empty( $values ) ) {
			return;
		}

		// REPLACE = insert-or-update po kluczu głównym (product_id).
		$sql = "REPLACE INTO {$cards_table} (product_id, title, url, price, regular_price, stock, thumb, search_text) VALUES "
			. implode( ',', $values );
		$sql = str_replace( "'NULL_SENTINEL'", 'NULL', $sql );
		$wpdb->query( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL
	}

	/**
	 * Multi-row INSERT: "INSERT INTO ... VALUES (...), (...), (...)".
	 * Rząd wielkości szybszy niż 500 osobnych $wpdb->insert().
	 */
	private static function bulk_insert( array $rows ): void {
		global $wpdb;

		if ( empty( $rows ) ) {
			return;
		}

		$table  = Dawmac_Filters_Schema::table_name();
		$values = [];

		foreach ( $rows as $r ) {
			$values[] = $wpdb->prepare(
				'(%d, %s, %s, %s, %s, %s)',
				$r['product_id'],
				$r['attribute'],
				$r['value_slug'],
				$r['value_label'],
				$r['value_numeric'] ?? 'NULL_SENTINEL',
				$r['term_id'] ?? 'NULL_SENTINEL'
			);
		}

		// prepare() nie umie NULL-i, więc podmieniamy sentinel po fakcie.
		$sql = "INSERT INTO {$table} (product_id, attribute, value_slug, value_label, value_numeric, term_id) VALUES "
			. implode( ',', $values );
		$sql = str_replace( "'NULL_SENTINEL'", 'NULL', $sql );

		$wpdb->query( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL
	}

	/**
	 * Wyciąga wartość liczbową z etykiety, żeby dało się filtrować zakresem.
	 * "ET35" -> 35, "17\"" -> 17, "5x112" -> null (to nie jest jedna liczba).
	 */
	private static function extract_numeric( string $label ): ?float {
		$clean = str_replace( ',', '.', trim( $label ) );

		// Czysta liczba ("35", "17.5")?
		if ( is_numeric( $clean ) ) {
			return (float) $clean;
		}

		// Liczba z prefiksem/sufiksem tekstowym ("ET35", "17\"", "72.6mm"),
		// ale NIE wzory typu "5x112" - te zostają czysto tekstowe.
		if ( preg_match( '/^[[:alpha:]]*\s*(-?\d+(?:\.\d+)?)\s*[^\d]*$/u', $clean, $m ) ) {
			return (float) $m[1];
		}

		return null;
	}
}
