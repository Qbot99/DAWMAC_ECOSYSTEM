<?php
/**
 * Plugin Name: Dawmac Filters
 * Description: Błyskawiczne filtrowanie produktów WooCommerce oparte o płaską tabelę indeksową (zamiast wolnych meta_query).
 * Version:     0.1.27
 * Author:      Hubert
 * Requires PHP: 8.1
 * Requires at least: 6.0
 * Text Domain: dawmac-filters
 */

// Bezpiecznik: plik odpalony bezpośrednio (poza WordPressem) nie może nic zrobić.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'DAWMAC_FILTERS_VERSION', '0.1.27' );
define( 'DAWMAC_FILTERS_DIR', plugin_dir_path( __FILE__ ) );
define( 'DAWMAC_FILTERS_URL', plugin_dir_url( __FILE__ ) );

require_once DAWMAC_FILTERS_DIR . 'includes/class-schema.php';
require_once DAWMAC_FILTERS_DIR . 'includes/class-indexer.php';
require_once DAWMAC_FILTERS_DIR . 'includes/class-query.php';
require_once DAWMAC_FILTERS_DIR . 'includes/class-frontend.php';
require_once DAWMAC_FILTERS_DIR . 'includes/class-native.php';
require_once DAWMAC_FILTERS_DIR . 'includes/class-admin.php';

Dawmac_Filters_Frontend::init();
Dawmac_Filters_Native::init();
// Zawsze: menu rejestruje się w adminie, a przełącznik w pasku pokazuje się
// też na froncie (jedno kliknięcie wprost ze strony sklepu).
Dawmac_Filters_Admin::init();

/**
 * Aktywacja wtyczki: tworzymy tabelę indeksową.
 * dbDelta (wewnątrz Schema::create_table) jest idempotentne,
 * więc ponowna aktywacja niczego nie psuje.
 */
register_activation_hook( __FILE__, 'dawmac_filters_activate' );

/**
 * Aktywacja: tabele + ustalenie domyślnego trybu (przy aktywnym Filter
 * Everything startujemy jako uśpieni, żeby nie ruszać żywego sklepu).
 */
function dawmac_filters_activate() {
	Dawmac_Filters_Schema::create_table();
	if ( class_exists( 'Dawmac_Filters_Admin' ) ) {
		Dawmac_Filters_Admin::set_default_mode();
	}
	// Wepnij widget filtrów do sidebara sklepu (idempotentne; obok wpisu FE,
	// którego nie ruszamy - gdy FE aktywny, nasz widget renderuje pustkę).
	Dawmac_Filters_Native::ensure_widget_placed();

	// Zaplanuj nocne odświeżanie indeksu.
	Dawmac_Filters_Indexer::schedule_nightly();
}

/**
 * Rejestracja komend WP-CLI (tylko gdy WP-CLI jest obecne,
 * czyli w terminalu - nie na froncie sklepu).
 */
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once DAWMAC_FILTERS_DIR . 'cli/class-cli.php';
	WP_CLI::add_command( 'dawmac', 'Dawmac_Filters_CLI' );
}

/**
 * Indeks aktualizuje się sam przy zapisie produktu.
 * woocommerce_update_product łapie zarówno edycję w adminie,
 * jak i importy CSV oraz zmiany przez REST API.
 */
add_action( 'woocommerce_update_product', [ 'Dawmac_Filters_Indexer', 'index_product' ], 10, 1 );
add_action( 'woocommerce_new_product', [ 'Dawmac_Filters_Indexer', 'index_product' ], 10, 1 );
add_action( 'before_delete_post', [ 'Dawmac_Filters_Indexer', 'delete_product' ], 10, 1 );
// Kosz/szkic/przywrócenie: before_delete_post łapie tylko trwałe usunięcie.
add_action( 'transition_post_status', [ 'Dawmac_Filters_Indexer', 'on_status_change' ], 10, 3 );

// Rozgrzewanie cache list filtrów w tle (zaplanowane po edycji/usunięciu),
// żeby to nie gość płacił za przeliczenie przy pierwszym wejściu.
add_action( 'dawmac_filters_warm_cache', [ 'Dawmac_Filters_Indexer', 'warm_counters_cache' ] );

// Nocne odświeżenie całego indeksu (03:30) - łapie zmiany, które ominęły
// hooki: importy po SQL, edycje w bazie, zmiany hurtowe. Leci porcjami,
// więc nie wywraca się na limicie czasu PHP.
add_action( 'dawmac_filters_nightly_reindex', [ 'Dawmac_Filters_Indexer', 'run_nightly' ] );
add_action( 'dawmac_filters_reindex_chunk', [ 'Dawmac_Filters_Indexer', 'process_chunk' ] );
add_action( 'init', [ 'Dawmac_Filters_Indexer', 'schedule_nightly' ] );

register_deactivation_hook( __FILE__, [ 'Dawmac_Filters_Indexer', 'unschedule_nightly' ] );
