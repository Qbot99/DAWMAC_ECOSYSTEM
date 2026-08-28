<?php
/**
 * Integracja NATYWNA - wtyczka jako czysty silnik filtrów.
 *
 * Zasada (wymóg Huberta): niczego nie zmieniamy w obecnej stronie.
 * Siatkę produktów renderuje normalny WordPress/WooCommerce, więc wszystkie
 * snippety (nwk kafelki, ukryj opony, sortowanie, licznik) i cały CSS
 * sklepu działają dokładnie jak dotychczas. My robimy dwie rzeczy:
 *
 *  1. WIDGET z formularzem filtrów w tym samym sidebarze, w którym siedział
 *     Filter Everything (astra-woo-shop-sidebar / #secondary).
 *  2. Hook woocommerce_product_query: parametry df_* z URL-a zamieniamy
 *     na listę ID z naszej tabeli indeksowej (kilka-kilkanaście ms) i
 *     wstrzykujemy jako post__in. Sortowanie, paginacja, tax_query innych
 *     snippetów - nietknięte, komponują się z naszym zawężeniem.
 *
 * Do tego lekki JS: po zmianie filtra pobiera tę samą stronę i podmienia
 * #primary (identycznie jak robiło to FE z enable_ajax + posts_container
 * "#primary"), z fallbackiem na zwykłe przeładowanie.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Dawmac_Filters_Native {

	const WIDGET_ID_BASE = 'dawmac_filters_widget';
	const SIDEBAR_ID     = 'astra-woo-shop-sidebar';

	public static function init(): void {
		add_action( 'widgets_init', [ __CLASS__, 'register_widget' ] );
		add_action( 'woocommerce_product_query', [ __CLASS__, 'filter_product_query' ], 20 );
		add_action( 'wp_enqueue_scripts', [ __CLASS__, 'assets' ] );
		add_action( 'template_redirect', [ __CLASS__, 'redirect_legacy_fe_urls' ], 0 );
		add_action( 'template_redirect', [ __CLASS__, 'redirect_product_search' ], 1 );
		add_filter( 'sidebars_widgets', [ __CLASS__, 'suppress_fe_widget_on_shop' ] );
	}

	/** Slug kategorii opon (własny zestaw atrybutów). */
	const TYRES_CAT = 'opony';

	/**
	 * Gdzie działa Dawmac: '' (nigdzie), 'shop' (katalog felg) albo
	 * 'opony' (kategoria opon - INNE atrybuty). Pozostałe kategorie
	 * zostają przy Filter Everything.
	 */
	public static function context(): string {
		if ( ! did_action( 'wp' ) ) {
			return ''; // za wcześnie, query jeszcze nie rozstrzygnięte
		}
		if ( function_exists( 'is_product_category' ) && is_product_category( self::TYRES_CAT ) ) {
			return self::TYRES_CAT;
		}
		if ( function_exists( 'is_shop' ) && is_shop() ) {
			return 'shop';
		}
		$shop_id = function_exists( 'wc_get_page_id' ) ? (int) wc_get_page_id( 'shop' ) : 0;
		return ( $shop_id > 0 && (int) get_queried_object_id() === $shop_id ) ? 'shop' : '';
	}

	/**
	 * Kontekst wyliczony z OBIEKTU zapytania - działa już na pre_get_posts,
	 * czyli tam, gdzie odpala się woocommerce_product_query. Globalne
	 * is_shop()/is_product_category() są tam jeszcze niegotowe (akcja 'wp'
	 * leci później), więc pytamy wprost $q.
	 */
	public static function context_for_query( $q ): string {
		if ( ! $q instanceof WP_Query ) {
			return '';
		}
		if ( $q->is_tax( 'product_cat' ) ) {
			$slug = (string) $q->get( 'product_cat' );
			$slug = substr( strrchr( '/' . $slug, '/' ), 1 ); // "rodzic/opony" -> "opony"
			return self::TYRES_CAT === $slug ? self::TYRES_CAT : '';
		}
		if ( $q->is_post_type_archive( 'product' ) ) {
			return 'shop';
		}
		return '';
	}

	/** Czy na tej stronie w ogóle się pokazujemy. */
	public static function shop_context(): bool {
		return '' !== self::context();
	}

	/**
	 * Sortowanie wartości filtra "po ludzku".
	 *
	 * Etykiety mieszają liczbę z tekstem ("8.25J", "10.5J", '17"', "5x112"),
	 * a is_numeric() na takich zwraca false - poprzednio wpadały więc w
	 * sortowanie naturalne, które kropkę traktuje jak separator i ustawiało
	 * 5.5J przed 5J oraz 8.5J przed 8.25J.
	 *
	 * Teraz: porównujemy liczbę WIODĄCĄ (5J < 5.5J < 6J, 8.25J < 8.5J,
	 * 205 < 225, 17" < 18"), a przy remisie schodzimy do sortowania
	 * naturalnego po całej etykiecie (5x100 przed 5x112, marki alfabetycznie).
	 */
	public static function sort_option_slugs( array $opts ): array {
		$lead_num = static function ( string $label ) {
			$clean = str_replace( ',', '.', trim( $label ) );
			return preg_match( '/^-?\d+(?:\.\d+)?/', $clean, $m ) ? (float) $m[0] : null;
		};

		$slugs = array_keys( $opts );
		usort( $slugs, static function ( $a, $b ) use ( $opts, $lead_num ) {
			$la = (string) ( $opts[ $a ]['label'] ?? $a );
			$lb = (string) ( $opts[ $b ]['label'] ?? $b );
			$na = $lead_num( $la );
			$nb = $lead_num( $lb );

			if ( null !== $na && null !== $nb ) {
				return $na !== $nb ? ( $na <=> $nb ) : strnatcasecmp( $la, $lb );
			}
			if ( null !== $na ) {
				return -1; // wartości liczbowe przed czysto tekstowymi
			}
			if ( null !== $nb ) {
				return 1;
			}
			return strnatcasecmp( $la, $lb );
		} );

		return $slugs;
	}

	/** Zestaw filtrów zależny od kontekstu (felgi vs opony). */
	public static function attributes_for( string $context ): array {
		if ( ! class_exists( 'Dawmac_Filters_Frontend' ) ) {
			return [];
		}
		return self::TYRES_CAT === $context
			? Dawmac_Filters_Frontend::ATTRIBUTES_TYRES
			: Dawmac_Filters_Frontend::ATTRIBUTES;
	}

	/**
	 * Na stronie sklepu (gdy Dawmac aktywny) chowamy widget Filter
	 * Everything - FE zostaje WŁĄCZONY i normalnie obsługuje kategorie
	 * (opony itd.), tylko sklep przejmujemy my. Czysty podział ról.
	 */
	public static function suppress_fe_widget_on_shop( $sidebars ) {
		if ( is_admin() || ! is_array( $sidebars ) ) {
			return $sidebars;
		}
		if ( ! self::engine_on() || ! self::shop_context() ) {
			return $sidebars;
		}
		if ( isset( $sidebars[ self::SIDEBAR_ID ] ) && is_array( $sidebars[ self::SIDEBAR_ID ] ) ) {
			$sidebars[ self::SIDEBAR_ID ] = array_values( array_filter(
				$sidebars[ self::SIDEBAR_ID ],
				static fn( $w ) => ! str_starts_with( (string) $w, 'wpc_filters_widget' )
			) );
		}
		return $sidebars;
	}

	/**
	 * Wyszukiwarka ze strony głównej (i każda inna szukająca produktów)
	 * jedzie na natywnym ?s=...&post_type=product - czyli po staremu, wolno
	 * i bez przeszukiwania naszego indeksu.
	 *
	 * Przekierowujemy taki request na sklep z parametrem df_s: formularz
	 * i jego stylowanie zostają NIETKNIĘTE (to ta sama "metalowa
	 * wyszukiwarka"), zmienia się tylko silnik, który liczy wyniki.
	 */
	public static function redirect_product_search(): void {
		if ( ! self::engine_on() || ! is_search() ) {
			return;
		}

		// Tylko wyszukiwanie produktów - wpisy/strony zostawiamy WordPressowi.
		$post_type = get_query_var( 'post_type' );
		$post_type = is_array( $post_type ) ? reset( $post_type ) : $post_type;
		if ( 'product' !== $post_type ) {
			return;
		}

		$term = trim( (string) get_search_query( false ) );
		if ( '' === $term ) {
			return;
		}

		$shop_id = function_exists( 'wc_get_page_id' ) ? (int) wc_get_page_id( 'shop' ) : 0;
		if ( $shop_id <= 0 ) {
			return;
		}

		$target = add_query_arg( 'df_s', rawurlencode( $term ), get_permalink( $shop_id ) );
		wp_safe_redirect( $target, 302 ); // 302: wyniki wyszukiwania są dynamiczne
		exit;
	}

	/**
	 * Stare "ładne" adresy Filter Everything, np.
	 *   /felgi-aluminiowe/rozstaw-5x112/srednica-20/dostepnosc-instock/
	 * żyły na regułach przepisywania FE - po jego wyłączeniu dają 404,
	 * a siedzą w Google i zakładkach klientów. Przepinamy je trwałym 301
	 * na nasze parametry df_* (serwer wyrenderuje przefiltrowaną stronę).
	 */
	public static function redirect_legacy_fe_urls(): void {
		if ( ! is_404() || ! self::engine_on() ) {
			return;
		}

		$shop_id   = function_exists( 'wc_get_page_id' ) ? (int) wc_get_page_id( 'shop' ) : 0;
		$shop_slug = $shop_id ? (string) get_post_field( 'post_name', $shop_id ) : '';
		if ( '' === $shop_slug ) {
			return;
		}

		$path = (string) wp_parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH );
		if ( ! preg_match( '#^/' . preg_quote( $shop_slug, '#' ) . '/(.+?)/?$#', $path, $m ) ) {
			return;
		}

		// Znane prefiksy = atrybuty z indeksu (pa_rozstaw -> "rozstaw"),
		// najdłuższe najpierw (np. "kategoria-koloru" przed "kolor").
		global $wpdb;
		$attrs = $wpdb->get_col(
			'SELECT DISTINCT attribute FROM ' . Dawmac_Filters_Schema::table_name()
			. " WHERE attribute LIKE 'pa\\_%'"
		);
		$prefixes = [];
		foreach ( $attrs as $a ) {
			$prefixes[ substr( $a, 3 ) ] = $a;
		}
		$prefixes['kolor'] = $prefixes['kolor'] ?? ( $prefixes['kategoria-koloru'] ?? null );
		uksort( $prefixes, static fn( $x, $y ) => strlen( $y ) <=> strlen( $x ) );

		$args    = [];
		$matched = 0;

		foreach ( array_filter( explode( '/', $m[1] ) ) as $seg ) {
			if ( 'page' === $seg || ctype_digit( $seg ) ) {
				continue; // paginację starych adresów pomijamy
			}
			// Dostępność FE -> nasz checkbox "W magazynie".
			if ( str_starts_with( $seg, 'dostepnosc-' ) ) {
				if ( str_contains( $seg, 'instock' ) ) {
					$args['df_instock'] = '1';
					$matched++;
				}
				continue;
			}
			foreach ( $prefixes as $prefix => $tax ) {
				if ( ! $tax || ! str_starts_with( $seg, $prefix . '-' ) ) {
					continue;
				}
				$value = substr( $seg, strlen( $prefix ) + 1 );
				// FE potrafiło łączyć wartości "-or-".
				foreach ( explode( '-or-', $value ) as $slug ) {
					if ( '' !== $slug ) {
						$args[ 'df_f[' . $tax . '][]' ][] = $slug;
						$matched++;
					}
				}
				break;
			}
		}

		if ( 0 === $matched ) {
			return; // to nie był adres filtrów - zostaw prawdziwe 404
		}

		// Ręczne składanie query (add_query_arg nie umie tablic z []).
		$pairs = [];
		foreach ( $args as $key => $val ) {
			foreach ( (array) $val as $v ) {
				$pairs[] = rawurlencode( $key ) . '=' . rawurlencode( $v );
			}
		}
		$target = get_permalink( $shop_id ) . '?' . implode( '&', $pairs );

		wp_safe_redirect( $target, 301 );
		exit;
	}

	public static function register_widget(): void {
		register_widget( 'Dawmac_Filters_Widget' );
	}

	/** Czy silnik ma działać na tym request (tryb dawmac albo podgląd admina). */
	public static function engine_on(): bool {
		if ( ! class_exists( 'Dawmac_Filters_Admin' ) ) {
			return true;
		}
		if ( Dawmac_Filters_Admin::is_dawmac_active() ) {
			return true;
		}
		return isset( $_GET['dawmac_preview'] ) && current_user_can( 'activate_plugins' );
	}

	public static function assets(): void {
		if ( ! self::engine_on() || ! self::shop_context() ) {
			return;
		}
		wp_enqueue_style( 'dawmac-filters' );
		wp_register_script( 'dawmac-native', DAWMAC_FILTERS_URL . 'assets/native.js', [], DAWMAC_FILTERS_VERSION, true );
		wp_enqueue_script( 'dawmac-native' );
	}

	/**
	 * Parametry df_* z GET -> tablica filtrów dla Dawmac_Filters_Query.
	 * Ta sama walidacja co w endpoint.php (is_numeric+is_finite, tylko pa_*).
	 */
	public static function filters_from_request(): array {
		$filters = [];

		if ( isset( $_GET['df_f'] ) && is_array( $_GET['df_f'] ) ) {
			foreach ( $_GET['df_f'] as $attr => $vals ) {
				if ( ! preg_match( '/^pa_[a-z0-9_-]{1,50}$/', (string) $attr ) ) {
					continue;
				}
				$vals = is_array( $vals ) ? $vals : explode( ',', (string) $vals );
				$vals = array_filter( array_map( 'trim', array_map( 'strval', $vals ) ), static fn( $v ) => '' !== $v );
				$vals = array_slice( array_values( $vals ), 0, 200 );
				if ( $vals ) {
					$filters[ $attr ] = $vals;
				}
			}
		}

		foreach ( [ 'price' => 'price', 'et' => 'pa_et' ] as $param => $attr ) {
			$range = [];
			foreach ( [ 'min', 'max' ] as $bound ) {
				$raw = $_GET[ 'df_' . $param . '_' . $bound ] ?? null;
				if ( is_scalar( $raw ) && '' !== $raw && is_numeric( $raw ) && is_finite( (float) $raw ) ) {
					$range[ $bound ] = (float) $raw;
				}
			}
			if ( $range ) {
				$filters[ $attr ] = $range;
			}
		}

		if ( ! empty( $_GET['df_instock'] ) ) {
			$filters['stock'] = [ 'instock' ];
		}

		if ( isset( $_GET['df_s'] ) && is_string( $_GET['df_s'] ) && '' !== trim( $_GET['df_s'] ) ) {
			$filters['search'] = mb_substr( trim( $_GET['df_s'] ), 0, 100 );
		}

		return $filters;
	}

	/**
	 * Serce integracji: zawęź główne zapytanie produktów do ID z indeksu.
	 */
	public static function filter_product_query( $q ): void {
		// Sklep (felgi) i kategoria opon. Pozostałe kategorie: Filter Everything.
		if ( ! self::engine_on() || '' === self::context_for_query( $q ) ) {
			return;
		}
		$filters = self::filters_from_request();
		if ( empty( $filters ) ) {
			return; // bez filtrów nie dotykamy zapytania w ogóle
		}

		$ids = Dawmac_Filters_Query::get_product_ids( $filters );

		// Szanuj wcześniejsze post__in (np. inny snippet): część wspólna.
		$existing = (array) $q->get( 'post__in' );
		if ( $existing ) {
			$ids = array_values( array_intersect( $existing, $ids ) );
		}

		$q->set( 'post__in', $ids ?: [ 0 ] ); // pusty wynik = brak produktów
	}

	/**
	 * Listy opcji do formularza - z tego samego cache co endpoint
	 * (rozgrzewany po reindeksie; w razie braku liczony raz).
	 */
	public static function options( string $context = 'shop' ): array {
		global $wpdb;
		$key = Dawmac_Filters_Indexer::cache_key_for( $context );

		// Czytamy WPROST z bazy, nie przez get_option().
		// Powód: endpoint.php działa pod SHORTINIT (bez object cache) i
		// zapisuje te listy surowym SQL-em. Przy włączonym cache obiektów
		// (Redis/LiteSpeed) WordPress pamiętał wtedy stare "tej opcji nie ma"
		// i sidebar renderował się bez list - zostawały same ET i Cena.
		$cached = $wpdb->get_var( $wpdb->prepare(
			"SELECT option_value FROM {$wpdb->options} WHERE option_name = %s LIMIT 1",
			$key
		) );
		if ( $cached ) {
			$decoded = json_decode( $cached, true );
			if ( is_array( $decoded ) ) {
				return $decoded;
			}
		}
		if ( class_exists( 'Dawmac_Filters_Indexer' ) ) {
			Dawmac_Filters_Indexer::warm_counters_cache();
			$fresh   = $wpdb->get_var( $wpdb->prepare(
				"SELECT option_value FROM {$wpdb->options} WHERE option_name = %s LIMIT 1",
				$key
			) );
			$decoded = json_decode( (string) $fresh, true );
			if ( is_array( $decoded ) ) {
				return $decoded;
			}
		}
		return [];
	}

	/**
	 * Jednorazowe wpięcie widgetu do sidebara sklepu (obok widgetu FE -
	 * nie ruszamy jego wpisu; gdy FE aktywny, nasz widget renderuje '').
	 * Idempotentne - bezpieczne przy każdej aktywacji.
	 */
	public static function ensure_widget_placed(): void {
		$sidebars = get_option( 'sidebars_widgets', [] );
		if ( ! is_array( $sidebars ) ) {
			return;
		}
		$target = $sidebars[ self::SIDEBAR_ID ] ?? null;
		if ( null === $target || ! is_array( $target ) ) {
			return; // brak sidebara sklepu (inny motyw) - nic nie robimy
		}
		foreach ( $target as $wid ) {
			if ( str_starts_with( (string) $wid, self::WIDGET_ID_BASE . '-' ) ) {
				return; // już wpięty
			}
		}
		$instances   = get_option( 'widget_' . self::WIDGET_ID_BASE, [] );
		$instances   = is_array( $instances ) ? $instances : [];
		$instances[1] = [];
		$instances['_multiwidget'] = 1;
		update_option( 'widget_' . self::WIDGET_ID_BASE, $instances );

		array_unshift( $sidebars[ self::SIDEBAR_ID ], self::WIDGET_ID_BASE . '-1' );
		update_option( 'sidebars_widgets', $sidebars );
	}
}

/**
 * Widget z formularzem filtrów (GET, parametry df_*).
 * Markup celowo prosty: nagłówki h3 i pola dziedziczą stylowanie sidebara
 * z ARKUSZA GLOWNEGO sklepu (sekcja 5: #secondary h3, inputy itd.),
 * a resztę dostarcza nasz filters.css (sekcje .dawmac-group).
 */
class Dawmac_Filters_Widget extends WP_Widget {

	public function __construct() {
		parent::__construct(
			Dawmac_Filters_Native::WIDGET_ID_BASE,
			'Hubert - Filtry (Dawmac)',
			[ 'description' => 'Szybkie filtry felg oparte o tabelę indeksową.' ]
		);
	}

	public function widget( $args, $instance ) {
		// Renderujemy się TYLKO na stronie sklepu i tylko gdy silnik włączony.
		// Kategorie (opony) => nic nie pokazujemy, tam rządzi Filter Everything.
		if ( ! Dawmac_Filters_Native::engine_on() || ! Dawmac_Filters_Native::shop_context() ) {
			return;
		}

		// Kontekst decyduje o zestawie filtrów: felgi vs opony (inne atrybuty).
		$context = Dawmac_Filters_Native::context();
		$options = Dawmac_Filters_Native::options( $context );
		$attrs   = Dawmac_Filters_Native::attributes_for( $context );
		$get     = $_GET;

		$checked = static function ( $attr, $slug ) use ( $get ) {
			$v = $get['df_f'][ $attr ] ?? [];
			$v = is_array( $v ) ? $v : explode( ',', (string) $v );
			return in_array( $slug, $v, true );
		};
		$val = static fn( $key ) => isset( $get[ $key ] ) && is_scalar( $get[ $key ] ) ? (string) $get[ $key ] : '';

		// Kontekst dla JS: strona sklepu (mirror snippetu "ukryj opony")
		// albo archiwum kategorii (zawężenie do bieżącej kategorii).
		$shop_id  = function_exists( 'wc_get_page_id' ) ? (int) wc_get_page_id( 'shop' ) : 0;
		$is_shop  = ( function_exists( 'is_shop' ) && is_shop() )
			|| ( $shop_id > 0 && (int) get_queried_object_id() === $shop_id );
		$cat_slug = '';
		if ( function_exists( 'is_product_category' ) && is_product_category() ) {
			$qo       = get_queried_object();
			$cat_slug = $qo->slug ?? '';
		}

		echo $args['before_widget'] ?? '';
		?>
		<form class="dawmac-native" id="dawmac-native" method="get" action=""
			data-endpoint="<?php echo esc_url( DAWMAC_FILTERS_URL . 'endpoint.php' ); ?>"
			data-shop="<?php echo $is_shop ? '1' : ''; ?>"
			data-cat="<?php echo esc_attr( $cat_slug ); ?>">
			<div class="dawmac-group dawmac-search-group">
				<label class="dawmac-search">
					<span class="screen-reader-text">Szukaj felg</span>
					<input type="search" name="df_s" class="dawmac-search-input"
						placeholder="<?php echo Dawmac_Filters_Native::TYRES_CAT === $context ? 'Szukaj opon…' : 'Szukaj felg…'; ?>"
						value="<?php echo esc_attr( $val( 'df_s' ) ); ?>" autocomplete="off">
				</label>
			</div>

			<?php foreach ( $attrs as $attr => $label ) : ?>
				<?php if ( 'pa_et' === $attr ) : ?>
					<details class="dawmac-group" <?php echo ( $val( 'df_et_min' ) || $val( 'df_et_max' ) ) ? 'open' : ''; ?>>
						<summary><?php echo esc_html( $label ); ?></summary>
						<div class="dawmac-price">
							<input type="number" name="df_et_min" placeholder="od" value="<?php echo esc_attr( $val( 'df_et_min' ) ); ?>">
							<span>-</span>
							<input type="number" name="df_et_max" placeholder="do" value="<?php echo esc_attr( $val( 'df_et_max' ) ); ?>">
						</div>
					</details>
					<?php continue; ?>
				<?php endif; ?>

				<?php
				$opts = $options[ $attr ] ?? [];
				if ( ! $opts ) {
					continue;
				}
				$slugs = Dawmac_Filters_Native::sort_option_slugs( $opts );
				$has_active = ! empty( $get['df_f'][ $attr ] );
				?>
				<details class="dawmac-group" <?php echo $has_active ? 'open' : ''; ?>>
					<summary><?php echo esc_html( $label ); ?><?php if ( $has_active ) : ?><span class="df-active"><?php echo count( (array) $get['df_f'][ $attr ] ); ?></span><?php endif; ?></summary>
					<ul>
						<?php foreach ( $slugs as $slug ) : ?>
							<li><label>
								<input type="checkbox" name="df_f[<?php echo esc_attr( $attr ); ?>][]"
									value="<?php echo esc_attr( $slug ); ?>" <?php echo $checked( $attr, $slug ) ? 'checked' : ''; ?>>
								<span><?php echo esc_html( $opts[ $slug ]['label'] ); ?></span>
							</label></li>
						<?php endforeach; ?>
					</ul>
				</details>
			<?php endforeach; ?>

			<details class="dawmac-group" <?php echo ( $val( 'df_price_min' ) || $val( 'df_price_max' ) ) ? 'open' : ''; ?>>
				<summary>Cena</summary>
				<div class="dawmac-price">
					<input type="number" name="df_price_min" min="0" placeholder="od" value="<?php echo esc_attr( $val( 'df_price_min' ) ); ?>">
					<span>-</span>
					<input type="number" name="df_price_max" min="0" placeholder="do" value="<?php echo esc_attr( $val( 'df_price_max' ) ); ?>">
				</div>
			</details>

			<div class="dawmac-group">
				<label class="dawmac-stock">
					<input type="checkbox" name="df_instock" value="1" <?php echo $val( 'df_instock' ) ? 'checked' : ''; ?>>
					<span>W magazynie</span>
				</label>
			</div>

			<?php if ( isset( $_GET['dawmac_preview'] ) ) : ?>
				<input type="hidden" name="dawmac_preview" value="1">
			<?php endif; ?>

			<button type="submit" class="dawmac-more" style="width:100%">Filtruj</button>
			<button type="button" class="dawmac-clear">Wyczyść filtry</button>
		</form>
		<?php
		echo $args['after_widget'] ?? '';
	}
}
