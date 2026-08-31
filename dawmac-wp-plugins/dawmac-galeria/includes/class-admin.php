<?php
/**
 * Ustawienia wtyczki + przycisk czyszczenia cache.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Dawmac_Galeria_Admin {

	const PAGE  = 'dawmac-galeria';
	const GROUP = 'dawmac_galeria';
	const ACTION_CACHE = 'dawmac_galeria_wyczysc';

	public static function init(): void {
		add_action( 'admin_menu', [ __CLASS__, 'menu' ] );
		add_action( 'admin_init', [ __CLASS__, 'ustawienia' ] );
		add_action( 'admin_post_' . self::ACTION_CACHE, [ __CLASS__, 'wyczysc' ] );
	}

	public static function menu(): void {
		add_submenu_page(
			'woocommerce',
			'Galeria na produktach',
			'Galeria na produktach',
			'manage_options',
			self::PAGE,
			[ __CLASS__, 'strona' ]
		);
	}

	public static function ustawienia(): void {
		register_setting( self::GROUP, Dawmac_Galeria_API::OPT_URL, [
			'type' => 'string', 'sanitize_callback' => 'esc_url_raw',
			'default' => Dawmac_Galeria_API::URL_DOMYSLNY,
		] );
		register_setting( self::GROUP, Dawmac_Galeria_API::OPT_TTL, [
			'type' => 'integer', 'sanitize_callback' => 'absint', 'default' => 12,
		] );
		register_setting( self::GROUP, Dawmac_Galeria_Produkt::OPT_MIEJSCE, [
			'type' => 'string',
			'sanitize_callback' => static fn( $v ): string =>
				in_array( $v, [ 'zakladka', 'pod_zdjeciami', 'pod_cena', 'pod_opisem', 'nie' ], true ) ? $v : 'zakladka',
			'default' => 'zakladka',
		] );
		register_setting( self::GROUP, Dawmac_Galeria_Produkt::OPT_ILE, [
			'type' => 'integer', 'sanitize_callback' => 'absint', 'default' => 8,
		] );
	}

	public static function strona(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1>Galeria na produktach</h1>
			<p>Karta produktu pokazuje auta klientów jeżdżące na tej feldze. Dopasowanie idzie po
			atrybutach <code>pa_producent</code> i <code>pa_model</code>.</p>

			<form method="post" action="options.php">
				<?php settings_fields( self::GROUP ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">Gdzie pokazać</th>
						<td>
							<select name="<?php echo esc_attr( Dawmac_Galeria_Produkt::OPT_MIEJSCE ); ?>">
								<option value="zakladka" <?php selected( Dawmac_Galeria_Produkt::miejsce(), 'zakladka' ); ?>>
									jako zakładka obok opisu (zalecane)
								</option>
								<option value="pod_zdjeciami" <?php selected( Dawmac_Galeria_Produkt::miejsce(), 'pod_zdjeciami' ); ?>>
									pod zdjęciami produktu
								</option>
								<option value="pod_cena" <?php selected( Dawmac_Galeria_Produkt::miejsce(), 'pod_cena' ); ?>>
									zaraz pod ceną, nad opisem (najlepiej widoczne)
								</option>
								<option value="pod_opisem" <?php selected( Dawmac_Galeria_Produkt::miejsce(), 'pod_opisem' ); ?>>
									sekcją pod opisem produktu
								</option>
								<option value="nie" <?php selected( Dawmac_Galeria_Produkt::miejsce(), 'nie' ); ?>>
									nie pokazuj (zostaje shortcode)
								</option>
							</select>
							<p class="description">
								Zakładka niczego nie przesuwa na stronie i pojawia się tylko wtedy,
								gdy dla tej felgi są zdjęcia.
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">Ile zdjęć</th>
						<td>
							<input type="number" min="0" max="60" class="small-text"
								name="<?php echo esc_attr( Dawmac_Galeria_Produkt::OPT_ILE ); ?>"
								value="<?php echo esc_attr( (string) (int) get_option( Dawmac_Galeria_Produkt::OPT_ILE, 24 ) ); ?>">
							<p class="description">
								0 = wszystkie, jakie galeria ma dla tej felgi (do 60).
								Przy pasku poziomym liczba zdjęć nie wydłuża strony.
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">Cache</th>
						<td>
							<input type="number" min="1" max="168" class="small-text"
								name="<?php echo esc_attr( Dawmac_Galeria_API::OPT_TTL ); ?>"
								value="<?php echo esc_attr( (string) Dawmac_Galeria_API::ttl_godzin() ); ?>"> godzin
							<p class="description">
								Bez cache każda odsłona karty produktu odpytywałaby galerię po HTTP.
								Przy 32 tysiącach produktów to nie wchodzi w grę.
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="dmg-url">Adres API galerii</label></th>
						<td>
							<input type="url" id="dmg-url" class="regular-text code"
								name="<?php echo esc_attr( Dawmac_Galeria_API::OPT_URL ); ?>"
								value="<?php echo esc_attr( Dawmac_Galeria_API::adres() ); ?>">
						</td>
					</tr>
				</table>
				<?php submit_button( 'Zapisz ustawienia' ); ?>
			</form>

			<hr>
			<h2>Cache</h2>
			<p>Wyczyść po poprawkach dopasowania w panelu galerii — inaczej karty produktów
			pokażą stary stan aż do wygaśnięcia cache.</p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION_CACHE ); ?>">
				<?php wp_nonce_field( self::ACTION_CACHE ); ?>
				<?php submit_button( 'Wyczyść cache galerii', 'secondary', 'submit', false ); ?>
			</form>

			<hr>
			<h2>Wstawienie ręczne</h2>
			<p>Gdybyś chciał sekcję w konkretnym miejscu szablonu:</p>
			<p><code>[dawmac_galeria_produktu ile="8"]</code></p>
		</div>
		<?php
	}

	public static function wyczysc(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Brak uprawnień.' );
		}

		check_admin_referer( self::ACTION_CACHE );
		Dawmac_Galeria_API::wyczysc_cache();

		wp_safe_redirect( add_query_arg( 'page', self::PAGE, admin_url( 'admin.php' ) ) );
		exit;
	}
}
