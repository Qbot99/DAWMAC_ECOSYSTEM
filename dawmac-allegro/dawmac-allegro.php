<?php
/**
 * Plugin Name:       Dawmac Allegro
 * Description:       Wystawianie i synchronizacja ofert Allegro z katalogu WooCommerce - szablon firmowy, ceny, stany, zamówienia.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      8.1
 * Author:            Hubert
 * Text Domain:       dawmac-allegro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'DAWMAC_ALLEGRO_VERSION', '1.0.0' );
define( 'DAWMAC_ALLEGRO_DIR', plugin_dir_path( __FILE__ ) );
define( 'DAWMAC_ALLEGRO_URL', plugin_dir_url( __FILE__ ) );

require_once DAWMAC_ALLEGRO_DIR . 'includes/class-text.php';
require_once DAWMAC_ALLEGRO_DIR . 'includes/class-template.php';
require_once DAWMAC_ALLEGRO_DIR . 'includes/class-product-data.php';
require_once DAWMAC_ALLEGRO_DIR . 'includes/class-auth.php';
require_once DAWMAC_ALLEGRO_DIR . 'includes/class-client.php';
require_once DAWMAC_ALLEGRO_DIR . 'includes/class-images.php';
require_once DAWMAC_ALLEGRO_DIR . 'includes/class-admin.php';

/**
 * Konfiguracja szablonu. Filtr pozwala nadpisać treść z motywu albo
 * ze snippetu, bez edycji pliku we wtyczce.
 */
function dawmac_allegro_config(): array {
	static $config = null;

	if ( null === $config ) {
		$config = apply_filters( 'dawmac_allegro_config', require DAWMAC_ALLEGRO_DIR . 'config/brand.php' );
	}

	return $config;
}

/**
 * Gotowy builder opisu, z podpiętym rozwiązywaniem grafik.
 *
 * Grafiki szablonu muszą leżeć na serwerach Allegro - do czasu wgrania
 * resolver zwraca null, a sekcje banerów po prostu nie powstają.
 * Oferta jest wtedy uboższa, ale poprawna.
 */
function dawmac_allegro_template(): Dawmac_Allegro_Template {
	return new Dawmac_Allegro_Template(
		dawmac_allegro_config(),
		static fn( string $key ): ?string => Dawmac_Allegro_Images::template_image( $key )
	);
}

add_action( 'plugins_loaded', static function (): void {
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', static function (): void {
			echo '<div class="notice notice-error"><p><b>Dawmac Allegro</b> wymaga aktywnego WooCommerce.</p></div>';
		} );

		return;
	}

	Dawmac_Allegro_Admin::init();
} );

// Komendy konsolowe - w stylu dawmac-filters, do odpalania z hostingu.
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once DAWMAC_ALLEGRO_DIR . 'cli/class-cli.php';
	WP_CLI::add_command( 'dawmac-allegro', 'Dawmac_Allegro_CLI' );
}
