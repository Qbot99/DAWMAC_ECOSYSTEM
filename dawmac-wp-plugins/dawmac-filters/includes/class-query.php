<?php
/**
 * Silnik zapytań: filtry -> ID produktów + countery.
 *
 * Format wejściowy $filters:
 * [
 *   'pa_srednica' => [ '17' ],                  // OR w ramach atrybutu
 *   'pa_rozstaw'  => [ '5x112', '5x120' ],      // (felga pasuje do 5x112 LUB 5x120)
 *   'price'       => [ 'min' => 200, 'max' => 900 ],  // zakres numeryczny
 * ]
 * Między atrybutami obowiązuje AND.
 *
 * Kształt SQL (zwycięzca benchmarku na danych produkcyjnych):
 * self-JOIN po indeksie pokrywającym (attribute, value_slug, product_id) -
 * każdy atrybut to osobne, tanie wejście w indeks; MySQL nie dotyka danych tabeli.
 *
 * Semantyka counterów (jak w porządnych sklepach):
 * licznik opcji atrybutu A liczy się z pominięciem WŁASNEGO filtra A,
 * ale z zachowaniem wszystkich pozostałych. Dzięki temu po zaznaczeniu
 * "17 cali" opcja "18 cali" pokazuje, ile felg dostaniesz po PRZEŁĄCZENIU,
 * a nie zero.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Dawmac_Filters_Query {

	/**
	 * ID produktów spełniających wszystkie filtry.
	 *
	 * @param array  $filters  Filtry (patrz opis klasy).
	 * @param string $orderby  '' | 'price_asc' | 'price_desc'
	 * @param int    $limit    0 = bez limitu.
	 * @param int    $offset
	 * @return int[]
	 */
	public static function get_product_ids( array $filters, string $orderby = '', int $limit = 0, int $offset = 0 ): array {
		global $wpdb;

		$sql = self::ids_sql( $filters );

		if ( 'price_asc' === $orderby || 'price_desc' === $orderby ) {
			$dir   = 'price_asc' === $orderby ? 'ASC' : 'DESC';
			$cards = Dawmac_Filters_Schema::cards_table_name();
			// Cena z tabeli kart (kolumna na PK product_id) - pojedynczy lookup
			// na produkt, zamiast nested-loop po wierszach 'price' tabeli meta.
			$sql   = "SELECT m.product_id FROM ({$sql}) m
			          JOIN {$cards} c ON c.product_id = m.product_id
			          ORDER BY c.price {$dir}, m.product_id DESC";
		}

		if ( $limit > 0 ) {
			$sql .= $wpdb->prepare( ' LIMIT %d OFFSET %d', $limit, $offset );
		}

		return array_map( 'intval', $wpdb->get_col( $sql ) ); // phpcs:ignore WordPress.DB.PreparedSQL
	}

	/**
	 * Liczba produktów spełniających filtry (do "Znaleziono X felg").
	 */
	public static function count( array $filters ): int {
		global $wpdb;
		$sql = self::ids_sql( $filters );
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM ({$sql}) m" ); // phpcs:ignore WordPress.DB.PreparedSQL
	}

	/**
	 * Strona wyników + total JEDNYM zapytaniem (COUNT(*) OVER() liczy się
	 * na pełnym zbiorze przed LIMIT-em). Bez tego total wymagał drugiego,
	 * równie drogiego przebiegu - na szerokich zakresach ET to było 2x czas.
	 *
	 * Domyślne sortowanie: product_id DESC (najnowsze pierwsze) - jawny
	 * ORDER BY jest konieczny, bo paginacja bez niego jest niedeterministyczna.
	 *
	 * @return array{ids: int[], total: int}
	 */
	public static function get_page( array $filters, string $orderby = '', int $limit = 24, int $offset = 0 ): array {
		global $wpdb;

		$ids_sql = self::ids_sql( $filters );

		if ( 'price_asc' === $orderby || 'price_desc' === $orderby ) {
			$dir   = 'price_asc' === $orderby ? 'ASC' : 'DESC';
			$cards = Dawmac_Filters_Schema::cards_table_name();
			// Cena z tabeli kart (PK) - patrz get_product_ids().
			$sql = "SELECT m.product_id, COUNT(*) OVER() AS _total
			        FROM ({$ids_sql}) m
			        JOIN {$cards} c ON c.product_id = m.product_id
			        ORDER BY c.price {$dir}, m.product_id DESC";
		} else {
			$sql = "SELECT m.product_id, COUNT(*) OVER() AS _total
			        FROM ({$ids_sql}) m
			        ORDER BY m.product_id DESC";
		}

		$sql .= $wpdb->prepare( ' LIMIT %d OFFSET %d', $limit, $offset );
		$rows = $wpdb->get_results( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL

		if ( empty( $rows ) ) {
			// Offset poza zbiorem (albo zero wyników) - total trzeba doliczyć.
			return [ 'ids' => [], 'total' => $offset > 0 ? self::count( $filters ) : 0 ];
		}

		return [
			'ids'   => array_map( fn( $r ) => (int) $r->product_id, $rows ),
			'total' => (int) $rows[0]->_total,
		];
	}

	/**
	 * Countery wszystkich opcji wszystkich atrybutów.
	 *
	 * @return array attribute => [ value_slug => [ 'label' => ..., 'count' => N ] ]
	 */
	public static function get_counters( array $filters, array $attributes = [] ): array {
		global $wpdb;
		$table = Dawmac_Filters_Schema::table_name();

		$constrained = array_keys( array_filter(
			$filters,
			fn( $k ) => 'price' !== $k && 'stock' !== $k,
			ARRAY_FILTER_USE_KEY
		) );

		// Które atrybuty liczymy: jawna lista z UI, a bez niej wszystkie pa_*.
		// Filtrowane atrybuty zawsze muszą być policzone (ich countery pokazują
		// "co się stanie po przełączeniu").
		$attr_where = "c.attribute LIKE 'pa\_%'";
		if ( $attributes ) {
			$attr_where = 'c.attribute IN (' . self::quoted_list( array_unique( array_merge( $attributes, $constrained ) ) ) . ')';
		}

		$out = [];

		// 1. Atrybuty BEZ własnego filtra: jeden wspólny GROUP BY.
		//    COUNT(*) wystarcza - indekser gwarantuje unikalność pary
		//    (product_id, attribute, value_slug), a DISTINCT kosztuje krotnie.
		$not_in = $constrained ? ' AND c.attribute NOT IN (' . self::quoted_list( $constrained ) . ')' : '';

		// Bez żadnych filtrów zbiór "pasujących" to wszystko - JOIN z podzapytaniem
		// tylko by spowalniał. Liczymy prosto po całej tabeli.
		if ( empty( $filters ) ) {
			$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.PreparedSQL
				"SELECT c.attribute, c.value_slug, c.value_label, COUNT(*) AS cnt
				 FROM {$table} c
				 WHERE {$attr_where}
				 GROUP BY c.attribute, c.value_slug"
			);
		} else {
			$ids_sql = self::ids_sql( $filters );
			$rows    = $wpdb->get_results( // phpcs:ignore WordPress.DB.PreparedSQL
				"SELECT c.attribute, c.value_slug, c.value_label, COUNT(*) AS cnt
				 FROM {$table} c
				 JOIN ({$ids_sql}) m ON m.product_id = c.product_id
				 WHERE {$attr_where}{$not_in}
				 GROUP BY c.attribute, c.value_slug"
			);
		}
		foreach ( $rows as $r ) {
			$out[ $r->attribute ][ $r->value_slug ] = [ 'label' => $r->value_label, 'count' => (int) $r->cnt ];
		}

		// 2. Atrybuty Z własnym filtrem: dla każdego osobny zbiór
		//    "wszystkie filtry MINUS ten atrybut".
		foreach ( $constrained as $attr ) {
			$rest = $filters;
			unset( $rest[ $attr ] );

			if ( empty( $rest ) ) {
				$rows = $wpdb->get_results( $wpdb->prepare( // phpcs:ignore WordPress.DB.PreparedSQL
					"SELECT c.value_slug, c.value_label, COUNT(*) AS cnt
					 FROM {$table} c WHERE c.attribute = %s GROUP BY c.value_slug",
					$attr
				) );
			} else {
				$rest_sql = self::ids_sql( $rest );
				$rows     = $wpdb->get_results( $wpdb->prepare( // phpcs:ignore WordPress.DB.PreparedSQL
					"SELECT c.value_slug, c.value_label, COUNT(*) AS cnt
					 FROM {$table} c
					 JOIN ({$rest_sql}) m ON m.product_id = c.product_id
					 WHERE c.attribute = %s
					 GROUP BY c.value_slug",
					$attr
				) );
			}
			foreach ( $rows as $r ) {
				$out[ $attr ][ $r->value_slug ] = [ 'label' => $r->value_label, 'count' => (int) $r->cnt ];
			}
		}

		return $out;
	}

	/**
	 * Buduje SQL zwracający product_id dla zestawu filtrów.
	 * Zero filtrów = wszystkie zaindeksowane produkty.
	 *
	 * Alias tabeli wstrzykiwany jest wprost (nie przez placeholder {a}/{c}
	 * podmieniany po prepare) - aliasy są stałymi kodu (f0/e/c), więc
	 * to bezpieczne, a jednocześnie nie może uszkodzić wpisanej frazy
	 * wyszukiwania zawierającej przypadkiem tekst placeholdera.
	 */
	private static function ids_sql( array $filters ): string {
		$table = Dawmac_Filters_Schema::table_name();
		$cards = Dawmac_Filters_Schema::cards_table_name();

		// Wykluczenia (klucz '__exclude'): attr => [slugi]. Używane m.in. do
		// lustrzanego odwzorowania snippetu "ukryj opony" (NOT IN kategorii).
		$exclude = [];
		if ( isset( $filters['__exclude'] ) && is_array( $filters['__exclude'] ) ) {
			$exclude = $filters['__exclude'];
		}
		unset( $filters['__exclude'] );

		// Produkty ukryte w katalogu (WooCommerce: exclude-from-catalog) nie
		// mają prawa wyskoczyć w wynikach. Zapytanie WordPressa odsiewa je samo,
		// więc bez tego liczba wyników z endpointu nie zgadzała się z tym,
		// co realnie widać na stronie.
		$exclude['product_visibility'] = array_values( array_unique( array_merge(
			$exclude['product_visibility'] ?? [],
			[ 'exclude-from-catalog' ]
		) ) );

		// Wyszukiwarka: każde słowo musi wystąpić w tytule (dane w tabeli kart).
		$search_str = null;
		if ( isset( $filters['search'] ) ) {
			$s = trim( (string) $filters['search'] );
			unset( $filters['search'] );
			$search_str = '' !== $s ? $s : null;
		}

		// Warunki strukturalne (bez generowania SQL - alias dokładamy przy użyciu).
		$conds = [];
		foreach ( $filters as $attr => $values ) {
			$is_range = is_array( $values ) && ( isset( $values['min'] ) || isset( $values['max'] ) );
			$conds[]  = [ 'attr' => (string) $attr, 'values' => $values, 'range' => $is_range ];
		}

		// Brak warunków atrybutowych: kotwica z tabeli KART (product_id = PK;
		// ORDER BY idzie prosto po kluczu, bez skanu tabeli meta).
		if ( empty( $conds ) ) {
			$sql = "SELECT f0.product_id FROM {$cards} f0 WHERE 1=1";
			if ( null !== $search_str ) {
				$w = self::search_sql( 'f0', $search_str );
				if ( '' !== $w ) {
					$sql .= " AND {$w}";
				}
			}
			return $sql . self::exclude_sql( $exclude );
		}

		// Kotwica = pierwszy warunek NIE-zakresowy (listy slugów są selektywne).
		$anchor_idx = null;
		foreach ( $conds as $i => $c ) {
			if ( ! $c['range'] ) {
				$anchor_idx = $i;
				break;
			}
		}

		if ( null === $anchor_idx ) {
			// Same zakresy (np. tylko ET/cena): kotwiczymy z tabeli kart
			// (wszystkie produkty, PK) i każdy zakres jako EXISTS - unika
			// DISTINCT po ~280k wierszy ET (kosztowna temporary table).
			$sql = "SELECT f0.product_id FROM {$cards} f0 WHERE 1=1";
			foreach ( $conds as $c ) {
				$sql .= " AND EXISTS (SELECT 1 FROM {$table} e WHERE e.product_id = f0.product_id AND "
					. self::emit_condition( 'e', $c ) . ')';
			}
		} else {
			$anchor = $conds[ $anchor_idx ];
			unset( $conds[ $anchor_idx ] );
			// DISTINCT: kotwica może być atrybutem multi-value (np. podwójny rozstaw).
			$sql = "SELECT DISTINCT f0.product_id FROM {$table} f0 WHERE "
				. self::emit_condition( 'f0', $anchor );
			foreach ( $conds as $c ) {
				$sql .= " AND EXISTS (SELECT 1 FROM {$table} e WHERE e.product_id = f0.product_id AND "
					. self::emit_condition( 'e', $c ) . ')';
			}
		}

		if ( null !== $search_str ) {
			$w = self::search_sql( 'c', $search_str );
			if ( '' !== $w ) {
				$sql .= " AND EXISTS (SELECT 1 FROM {$cards} c WHERE c.product_id = f0.product_id AND {$w})";
			}
		}

		return $sql . self::exclude_sql( $exclude );
	}

	/**
	 * Warunki wykluczające: NOT EXISTS dla każdej pary attr => slugi.
	 * (np. __exclude ['product_cat' => ['opony']] = snippet "ukryj opony").
	 */
	private static function exclude_sql( array $exclude ): string {
		global $wpdb;
		$table = Dawmac_Filters_Schema::table_name();

		$sql = '';
		foreach ( $exclude as $attr => $slugs ) {
			$slugs = array_filter( array_map( 'strval', (array) $slugs ), static fn( $v ) => '' !== $v );
			if ( ! $slugs || ! preg_match( '/^[a-z0-9_-]{1,64}$/', (string) $attr ) ) {
				continue;
			}
			$sql .= ' AND NOT EXISTS (SELECT 1 FROM ' . $table . ' ex WHERE ex.product_id = f0.product_id AND '
				. $wpdb->prepare( 'ex.attribute = %s', $attr )
				. ' AND ex.value_slug IN (' . self::quoted_list( $slugs ) . '))';
		}
		return $sql;
	}

	/**
	 * SQL jednego warunku atrybutowego z podanym aliasem tabeli.
	 * Alias jest stałą kodu (f0/e) - bezpieczny do wstrzyknięcia w format.
	 */
	private static function emit_condition( string $alias, array $c ): string {
		global $wpdb;
		$attr   = $c['attr'];
		$values = $c['values'];

		if ( $c['range'] ) {
			$sql = $wpdb->prepare( "{$alias}.attribute = %s", $attr );
			if ( isset( $values['min'] ) ) {
				$sql .= $wpdb->prepare( " AND {$alias}.value_numeric >= %f", (float) $values['min'] );
			}
			if ( isset( $values['max'] ) ) {
				$sql .= $wpdb->prepare( " AND {$alias}.value_numeric <= %f", (float) $values['max'] );
			}
			return $sql;
		}

		$vals = array_map( 'strval', (array) $values );
		return $wpdb->prepare( "{$alias}.attribute = %s", $attr )
			. " AND {$alias}.value_slug IN (" . self::quoted_list( $vals ) . ')';
	}

	/**
	 * WHERE dla wyszukiwarki: '<alias>.search_text LIKE %word%' AND ... (każde słowo).
	 * search_text = tytuł + opis, więc znajdujemy też to, co jest WYŁĄCZNIE
	 * w opisie - np. lokalizację magazynową "42.L/NHB" albo kod hali "NHB".
	 * Słowa użytkownika idą jako parametry %s (pełny escaping), alias to stała.
	 */
	private static function search_sql( string $alias, string $search ): string {
		global $wpdb;
		// Ta sama normalizacja co przy indeksowaniu (spacje wokół "/").
		$search = preg_replace( '#\s*/\s*#u', '/', trim( $search ) );
		$words  = preg_split( '/\s+/', (string) $search, 8, PREG_SPLIT_NO_EMPTY );
		$likes = [];
		foreach ( (array) $words as $w ) {
			$likes[] = $wpdb->prepare( "{$alias}.search_text LIKE %s", '%' . $wpdb->esc_like( $w ) . '%' );
		}
		return $likes ? implode( ' AND ', $likes ) : '';
	}

	/**
	 * 'a','b','c' - bezpiecznie przez prepare.
	 */
	private static function quoted_list( array $values ): string {
		global $wpdb;
		return implode( ',', array_map( fn( $v ) => $wpdb->prepare( '%s', $v ), $values ) );
	}
}
