<?php
/**
 * Plugin Name: Dawmac Galeria na produktach
 * Description: Pokazuje na karcie produktu zdjęcia aut klientów jeżdżących na tej feldze, zaciągane z galerii dawmacpolska.pl.
 * Version:     0.1.0
 * Author:      Hubert
 * Requires PHP: 8.1
 * Requires at least: 6.0
 * Text Domain: dawmac-galeria
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'DAWMAC_GALERIA_VERSION', '0.1.0' );
define( 'DAWMAC_GALERIA_DIR', plugin_dir_path( __FILE__ ) );
define( 'DAWMAC_GALERIA_URL', plugin_dir_url( __FILE__ ) );

require_once DAWMAC_GALERIA_DIR . 'includes/class-norm.php';
require_once DAWMAC_GALERIA_DIR . 'includes/class-api.php';
require_once DAWMAC_GALERIA_DIR . 'includes/class-produkt.php';
require_once DAWMAC_GALERIA_DIR . 'includes/class-admin.php';
require_once DAWMAC_GALERIA_DIR . 'includes/class-sync.php';

Dawmac_Galeria_Produkt::init();
Dawmac_Galeria_Admin::init();
Dawmac_Galeria_Sync::init();

/**
 * Odinstalowanie nie zostawia śmieci: kasujemy ustawienia i cache.
 * Zdjęcia i tak nie są nasze — leżą w galerii.
 */
register_deactivation_hook( __FILE__, [ 'Dawmac_Galeria_API', 'wyczysc_cache' ] );
register_deactivation_hook( __FILE__, [ 'Dawmac_Galeria_Sync', 'odplanuj' ] );
