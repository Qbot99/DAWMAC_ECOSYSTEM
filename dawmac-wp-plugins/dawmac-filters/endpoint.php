<?php
/**
 * Lekki endpoint filtrów - wołany bezpośrednio:
 *   GET /wp-content/plugins/dawmac-filters/endpoint.php?f[pa_srednica]=17&f[pa_rozstaw]=5x112,5x120
 *
 * Parametry:
 *   f[<atrybut>]  lista slugów po przecinku (OR w ramach atrybutu)
 *   price_min / price_max
 *   instock=1     tylko dostępne
 *   orderby       price_asc | price_desc
 *   page          od 1 (domyślnie 1)
 *   per_page      domyślnie 24, max 60
 *
 * SHORTINIT = WordPress ładuje TYLKO wp-config + $wpdb (bez wtyczek,
 * bez motywu, bez REST API). To różnica między ~10 ms a ~500 ms na
 * shared hostingu. Endpoint jest w 100% read-only.
 */

define( 'SHORTINIT', true );

// Nic nie może wydrukować się przed/w trakcie JSON-a: gdyby jakikolwiek
// notice/warning trafił do outputu (np. przy dziwnym wejściu i włączonym
// display_errors na hostingu), zepsułby odpowiedź i ujawnił ścieżki.
// Twarde wyłączenie na czas życia tego endpointu.
@ini_set( 'display_errors', '0' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors, WordPress.PHP.IniSet

// Znajdź wp-load.php. DOCUMENT_ROOT działa przy wywołaniu przez HTTP;
// fallback na ścieżkę względną obsługuje typowe instalacje bez symlinków.
$dawmac_wp_load = rtrim( $_SERVER['DOCUMENT_ROOT'] ?? '', '/' ) . '/wp-load.php';
if ( ! is_file( $dawmac_wp_load ) ) {
	$dawmac_wp_load = dirname( __DIR__, 3 ) . '/wp-load.php';
}
if ( ! is_file( $dawmac_wp_load ) ) {
	http_response_code( 500 );
	header( 'Content-Type: application/json; charset=utf-8' );
	echo '{"error":"wp-load not found"}';
	exit;
}

$dawmac_t0 = microtime( true );
require $dawmac_wp_load;

// Pod SHORTINIT nie ma ABSPATH-owych helperów wtyczek - ładujemy swoje klasy
// ręcznie. Zależą wyłącznie od $wpdb, więc działają.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
require_once __DIR__ . '/includes/class-schema.php';
require_once __DIR__ . '/includes/class-query.php';

global $wpdb;

// ---------------------------------------------------------------------------
// 1. Parsowanie i sanityzacja wejścia.
// ---------------------------------------------------------------------------
$filters = [];

if ( isset( $_GET['f'] ) && is_array( $_GET['f'] ) ) {
	foreach ( $_GET['f'] as $attr => $csv ) {
		// Twarda walidacja nazwy atrybutu: pa_* albo kategoria produktowa
		// (product_cat - kontekst stron kategorii w trybie natywnym).
		if ( ! preg_match( '/^(pa_[a-z0-9_-]{1,50}|product_cat)$/', (string) $attr ) ) {
			continue;
		}
		// Dwie legalne formy wartości: CSV ("17,18") albo tablica skalarów
		// (f[pa_x][]=17&f[pa_x][]=18 - tak wysyła formularz trybu natywnego).
		// Elementy nieskalarne odrzucamy (zagnieżdżone tablice rzucałyby
		// "Array to string conversion" - Warning przed JSON + wyciek ścieżki).
		if ( is_array( $csv ) ) {
			$raw_values = array_filter( $csv, 'is_scalar' );
		} elseif ( is_scalar( $csv ) ) {
			$raw_values = explode( ',', (string) $csv );
		} else {
			continue;
		}
		// Callback odrzuca TYLKO puste stringi - domyślny array_filter usunąłby
		// też '0', a to prawidłowy slug (np. ET '0'), co dawało cały katalog.
		$values = array_filter(
			array_map( 'trim', array_map( 'strval', $raw_values ) ),
			static fn( $v ) => '' !== $v
		);
		// Limit z zapasem powyżej najliczniejszego atrybutu (producent ~149),
		// żeby "zaznacz wszystko" nie ucinało po cichu części wyboru.
		$values = array_slice( array_values( $values ), 0, 200 );
		if ( $values ) {
			$filters[ $attr ] = $values;
		}
	}
}

// Zakresy numeryczne: cena i ET (dwa pola od-do w UI).
// is_numeric() jest KLUCZOWE: bez tego (float)'abc' = 0.0 zostałoby użyte
// jako realny filtr (np. price_max=0 -> zero wyników mimo pełnego katalogu).
foreach ( [ 'price' => 'price', 'et' => 'pa_et' ] as $param => $attr ) {
	$range = [];
	foreach ( [ 'min', 'max' ] as $bound ) {
		$raw = $_GET[ $param . '_' . $bound ] ?? null;
		// is_finite odsiewa 1e400 -> INF, które przeszłoby is_numeric,
		// a potem trafiłoby jako 'INF'/'inf' do SQL (błąd + ciche 0 wyników).
		if ( is_scalar( $raw ) && is_numeric( $raw ) && is_finite( (float) $raw ) ) {
			$range[ $bound ] = (float) $raw;
		}
	}
	if ( $range ) {
		$filters[ $attr ] = $range;
	}
}

if ( ! empty( $_GET['instock'] ) ) {
	$filters['stock'] = [ 'instock' ];
}

// Wykluczenie kategorii (lustrzane odwzorowanie snippetu "ukryj opony"
// na stronie sklepu): not_cat=opony lub lista po przecinku.
if ( isset( $_GET['not_cat'] ) && is_string( $_GET['not_cat'] ) && '' !== $_GET['not_cat'] ) {
	$not = array_filter( array_map( 'trim', explode( ',', $_GET['not_cat'] ) ) );
	$not = array_values( array_filter( $not, static fn( $s ) => preg_match( '/^[a-z0-9_-]{1,64}$/', $s ) ) );
	if ( $not ) {
		$filters['__exclude'] = [ 'product_cat' => array_slice( $not, 0, 10 ) ];
	}
}

// Wyszukiwarka: fraza z pola SZUKAJ (tytuły produktów).
// is_string chroni przed s[]=... (Array to string conversion).
if ( isset( $_GET['s'] ) && is_string( $_GET['s'] ) && '' !== trim( $_GET['s'] ) ) {
	$filters['search'] = mb_substr( trim( $_GET['s'] ), 0, 100 );
}

$orderby  = in_array( $_GET['orderby'] ?? '', [ 'price_asc', 'price_desc' ], true ) ? $_GET['orderby'] : '';
$page     = max( 1, (int) ( $_GET['page'] ?? 1 ) );
$per_page = min( 60, max( 1, (int) ( $_GET['per_page'] ?? 24 ) ) );

// Atrybuty liczone w counterach (kolejność = kolejność w sidebarze).
$ui_attributes = [
	'pa_producent',
	'pa_srednica',
	'pa_szerokosc',
	'pa_rozstaw',
	'pa_et',
	'pa_kategoria-koloru',
];

// ---------------------------------------------------------------------------
// 2. Zapytania.
// ---------------------------------------------------------------------------
// Jedno zapytanie: strona ID-ków + total (COUNT(*) OVER()).
$page_result = Dawmac_Filters_Query::get_page( $filters, $orderby, $per_page, ( $page - 1 ) * $per_page );
$total       = $page_result['total'];
$ids         = $page_result['ids'];

// Listy opcji do sidebara (decyzja: BEZ przeliczania counterów per klik -
// za drogie przy 800k+ wierszy i niepotrzebne w UI). Zwracamy pełne listy
// wartości (policzone raz na całym katalogu, cache w wp_options,
// unieważniany przy reindeksie) - tylko gdy frontend o nie prosi
// (options=1: pierwsze ładowanie strony). Kolejne kliknięcia = same wyniki.
$options = null;
if ( ! empty( $_GET['options'] ) ) {
	$cached = $wpdb->get_var(
		"SELECT option_value FROM {$wpdb->options} WHERE option_name = 'dawmac_filters_counters_cache' LIMIT 1"
	);
	$options = $cached ? json_decode( $cached, true ) : null;

	if ( null === $options ) {
		$options = Dawmac_Filters_Query::get_counters( [], $ui_attributes );
		$json    = $wpdb->prepare( '%s', wp_json_encode_dawmac( $options ) );
		$wpdb->query(
			"REPLACE INTO {$wpdb->options} (option_name, option_value, autoload)
			 VALUES ('dawmac_filters_counters_cache', {$json}, 'no')" // phpcs:ignore WordPress.DB.PreparedSQL
		);
	}
}

// ---------------------------------------------------------------------------
// 3. Karty produktów z tabeli kart (bez wp_posts/wp_postmeta).
// ---------------------------------------------------------------------------
$products = [];
if ( $ids ) {
	$cards_table = Dawmac_Filters_Schema::cards_table_name();
	$id_list     = implode( ',', array_map( 'intval', $ids ) );
	$rows        = $wpdb->get_results(
		"SELECT product_id, title, url, price, regular_price, stock, thumb
		 FROM {$cards_table} WHERE product_id IN ({$id_list})" // phpcs:ignore WordPress.DB.PreparedSQL
	);
	$by_id = [];
	foreach ( $rows as $r ) {
		$by_id[ (int) $r->product_id ] = $r;
	}

	$siteurl = $wpdb->get_var(
		"SELECT option_value FROM {$wpdb->options} WHERE option_name = 'siteurl' LIMIT 1"
	);
	$siteurl = rtrim( (string) $siteurl, '/' );

	// Atrybuty do kafelka "Nowa Wizja" (markup i formatery zgodne ze
	// snippetem nwk_* i sekcją 20 Additional CSS sklepu): specs
	// srednica/szerokosc/rozstaw/et + producent/model do tytułu.
	// Surowe TABLICE wartości - formatowanie (ET min-max, BLANK, Custom)
	// robi frontend identycznie jak nwk_fmt_*.
	$card_attrs = [];
	$attr_rows  = $wpdb->get_results(
		"SELECT product_id, attribute, value_slug, value_label
		 FROM " . Dawmac_Filters_Schema::table_name() . "
		 WHERE product_id IN ({$id_list})
		   AND attribute IN ('pa_srednica','pa_szerokosc','pa_rozstaw','pa_et','pa_producent','pa_model',
		                     'pa_szerokosc_opony','pa_profil','pa_srednica_opony','product_cat')
		 ORDER BY value_numeric, value_label" // phpcs:ignore WordPress.DB.PreparedSQL
	);
	foreach ( $attr_rows as $ar ) {
		$pid = (int) $ar->product_id;
		if ( 'product_cat' === $ar->attribute ) {
			// Kategoria jako slug - po niej frontend rozpoznaje oponę
			// (odpowiednik nwk_is_opona() ze snippetu sklepu).
			$card_attrs[ $pid ]['cat'][] = $ar->value_slug;
			continue;
		}
		$key = substr( $ar->attribute, 3 ); // pa_srednica -> srednica
		$card_attrs[ $pid ][ $key ][] = $ar->value_label;
	}

	foreach ( $ids as $id ) {
		if ( ! isset( $by_id[ $id ] ) ) {
			continue;
		}
		$r     = $by_id[ $id ];
		$attrs = $card_attrs[ $id ] ?? [];
		$products[] = [
			'id'            => (int) $r->product_id,
			'title'         => $r->title,
			// ?p=ID zawsze działa i przekierowuje na ładny permalink.
			'url'           => $siteurl . '/?p=' . (int) $r->product_id,
			'price'         => null !== $r->price ? (float) $r->price : null,
			'regular_price' => null !== $r->regular_price ? (float) $r->regular_price : null,
			'stock'         => $r->stock,
			'thumb'         => $r->thumb ? $siteurl . '/wp-content/uploads/' . $r->thumb : '',
			'attrs'         => $attrs,
		];
	}
}

// ---------------------------------------------------------------------------
// 4. Odpowiedź.
// ---------------------------------------------------------------------------
header( 'Content-Type: application/json; charset=utf-8' );
header( 'Cache-Control: no-store' );

echo wp_json_encode_dawmac( [
	'total'    => $total,
	'page'     => $page,
	'per_page' => $per_page,
	'products' => $products,
	'options'  => $options,
	'took_ms'  => round( ( microtime( true ) - $dawmac_t0 ) * 1000, 1 ),
] );
exit;

/**
 * json_encode z flagami jak w wp_json_encode (niedostępnym pod SHORTINIT).
 */
function wp_json_encode_dawmac( $data ): string {
	return (string) json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
}
