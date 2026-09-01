<?php
/**
 * Panel wtyczki: dane aplikacji, połączenie konta, stan integracji.
 *
 * Świadomie jedna strona bez zakładek - dopóki integracja robi jedną rzecz,
 * rozbijanie ustawień na ekrany tylko utrudnia sprawdzenie, co jest podpięte.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Dawmac_Allegro_Admin {

	const SLUG       = 'dawmac-allegro';
	const CAPABILITY = 'manage_woocommerce';

	public static function init(): void {
		add_action( 'admin_menu', [ self::class, 'menu' ] );
		add_action( 'admin_init', [ self::class, 'handle_actions' ] );
	}

	public static function menu(): void {
		add_menu_page(
			'Dawmac Allegro',
			'Allegro',
			self::CAPABILITY,
			self::SLUG,
			[ self::class, 'render' ],
			'dashicons-cart',
			57
		);
	}

	/**
	 * Zapis ustawień i powrót z autoryzacji. Wpięte w admin_init, bo powrót
	 * z Allegro musi zdążyć wymienić kod na token - kod żyje 10 sekund,
	 * więc nie ma miejsca na krok pośredni.
	 */
	public static function handle_actions(): void {
		if ( ! is_admin() || ! current_user_can( self::CAPABILITY ) ) {
			return;
		}

		if ( ( $_GET['page'] ?? '' ) !== self::SLUG ) {
			return;
		}

		// Powrót z ekranu zgody Allegro.
		if ( isset( $_GET['code'], $_GET['state'] ) ) {
			$result = Dawmac_Allegro_Auth::handle_callback(
				sanitize_text_field( wp_unslash( $_GET['code'] ) ),
				sanitize_text_field( wp_unslash( $_GET['state'] ) )
			);

			self::redirect_with( is_wp_error( $result ) ? $result->get_error_message() : 'Konto Allegro połączone.' );
		}

		if ( isset( $_GET['error'] ) ) {
			self::redirect_with( 'Allegro odrzuciło autoryzację: ' . sanitize_text_field( wp_unslash( $_GET['error'] ) ) );
		}

		if ( ! isset( $_POST['dawmac_allegro_action'] ) ) {
			return;
		}

		check_admin_referer( 'dawmac_allegro' );

		$action = sanitize_text_field( wp_unslash( $_POST['dawmac_allegro_action'] ) );

		if ( 'save' === $action ) {
			$env = sanitize_text_field( wp_unslash( $_POST['env'] ?? 'sandbox' ) );
			update_option( Dawmac_Allegro_Auth::OPT_ENV, in_array( $env, [ 'sandbox', 'production' ], true ) ? $env : 'sandbox' );

			// Stałe w wp-config.php mają pierwszeństwo - wtedy pola są tylko do odczytu.
			if ( ! defined( 'DAWMAC_ALLEGRO_CLIENT_ID' ) ) {
				update_option( Dawmac_Allegro_Auth::OPT_CLIENT, [
					'client_id'     => sanitize_text_field( wp_unslash( $_POST['client_id'] ?? '' ) ),
					'client_secret' => sanitize_text_field( wp_unslash( $_POST['client_secret'] ?? '' ) ),
				], 'no' );
			}

			self::redirect_with( 'Zapisano.' );
		}

		if ( 'connect' === $action ) {
			wp_redirect( Dawmac_Allegro_Auth::authorize_url() );
			exit;
		}

		if ( 'disconnect' === $action ) {
			Dawmac_Allegro_Auth::disconnect();
			self::redirect_with( 'Konto rozłączone.' );
		}
	}

	private static function redirect_with( string $message ): void {
		set_transient( 'dawmac_allegro_notice', $message, 60 );
		wp_safe_redirect( admin_url( 'admin.php?page=' . self::SLUG ) );
		exit;
	}

	public static function render(): void {
		$env       = Dawmac_Allegro_Auth::env();
		$creds     = Dawmac_Allegro_Auth::credentials();
		$locked    = defined( 'DAWMAC_ALLEGRO_CLIENT_ID' );
		$connected = Dawmac_Allegro_Auth::is_connected();
		$notice    = get_transient( 'dawmac_allegro_notice' );

		delete_transient( 'dawmac_allegro_notice' );

		echo '<div class="wrap"><h1>Dawmac Allegro</h1>';

		if ( $notice ) {
			printf( '<div class="notice notice-info is-dismissible"><p>%s</p></div>', esc_html( $notice ) );
		}

		printf(
			'<p>Środowisko: <b>%s</b> &middot; Konto: <b style="color:%s">%s</b></p>',
			esc_html( 'sandbox' === $env ? 'sandbox (testowe)' : 'produkcja' ),
			$connected ? '#1a7f37' : '#b32d2e',
			esc_html( $connected ? 'połączone' : 'nie połączone' )
		);

		if ( ! Dawmac_Allegro_Auth::is_configured() ) {
			printf(
				'<div class="notice notice-warning"><p>Najpierw zarejestruj aplikację na <code>%s</code> '
				. 'i wklej dane poniżej. Jako <b>Redirect URI</b> podaj dokładnie:<br><code>%s</code></p></div>',
				esc_html( Dawmac_Allegro_Auth::apps_url() ),
				esc_html( Dawmac_Allegro_Auth::redirect_uri() )
			);
		}

		echo '<form method="post"><table class="form-table"><tbody>';

		echo '<tr><th scope="row">Środowisko</th><td><select name="env">';
		foreach ( [ 'sandbox' => 'sandbox (testowe)', 'production' => 'produkcja' ] as $value => $label ) {
			printf(
				'<option value="%s"%s>%s</option>',
				esc_attr( $value ),
				selected( $env, $value, false ),
				esc_html( $label )
			);
		}
		echo '</select><p class="description">Zmiana środowiska nie kasuje tokenów, ale token z sandboxa '
			. 'nie działa na produkcji - po przełączeniu trzeba połączyć konto ponownie.</p></td></tr>';

		printf(
			'<tr><th scope="row">Client ID</th><td><input type="text" class="regular-text" name="client_id" value="%s"%s></td></tr>',
			esc_attr( $creds['client_id'] ),
			$locked ? ' readonly' : ''
		);

		printf(
			'<tr><th scope="row">Client Secret</th><td><input type="password" class="regular-text" name="client_secret" value="%s"%s>'
			. '<p class="description">%s</p></td></tr>',
			esc_attr( $creds['client_secret'] ),
			$locked ? ' readonly' : '',
			$locked
				? 'Wartości pochodzą ze stałych w wp-config.php.'
				: 'Bezpieczniej trzymać to w wp-config.php jako DAWMAC_ALLEGRO_CLIENT_ID i DAWMAC_ALLEGRO_CLIENT_SECRET.'
		);

		echo '<tr><th scope="row">Redirect URI</th><td><code>' . esc_html( Dawmac_Allegro_Auth::redirect_uri() )
			. '</code><p class="description">Musi zgadzać się co do znaku z adresem w apps.developer.allegro.pl.</p></td></tr>';

		echo '</tbody></table>';

		wp_nonce_field( 'dawmac_allegro' );
		echo '<input type="hidden" name="dawmac_allegro_action" value="save">';
		submit_button( 'Zapisz' );
		echo '</form>';

		if ( Dawmac_Allegro_Auth::is_configured() ) {
			echo '<hr><form method="post">';
			wp_nonce_field( 'dawmac_allegro' );
			printf(
				'<input type="hidden" name="dawmac_allegro_action" value="%s">',
				$connected ? 'disconnect' : 'connect'
			);
			submit_button(
				$connected ? 'Rozłącz konto' : 'Połącz konto Allegro',
				$connected ? 'delete' : 'primary',
				'submit',
				false
			);
			echo '</form>';
		}

		echo '</div>';
	}
}
