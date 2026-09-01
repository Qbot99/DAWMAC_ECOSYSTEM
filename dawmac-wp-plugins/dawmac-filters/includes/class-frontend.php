<?php
/**
 * Frontend: shortcode [dawmac_filters] renderujący sidebar filtrów + siatkę
 * produktów. Cała interakcja idzie przez lekki endpoint (endpoint.php) -
 * kliknięcie filtra NIE przeładowuje strony i NIE dotyka WordPressa.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Dawmac_Filters_Frontend {

	/**
	 * Atrybuty w sidebarze, w kolejności wyświetlania.
	 * Klucz = taksonomia w indeksie, wartość = etykieta sekcji.
	 */
	const ATTRIBUTES = [
		'pa_producent'        => 'Producent',
		'pa_srednica'         => 'Średnica',
		'pa_szerokosc'        => 'Szerokość',
		'pa_rozstaw'          => 'Rozstaw',
		'pa_et'               => 'ET',
		'pa_kategoria-koloru' => 'Kolor',
	];

	/**
	 * Opony mają WŁASNE atrybuty (inne niż felgi) - zestaw odpowiada temu,
	 * co snippet nwk_specs_for() pokazuje na kafelku opony.
	 */
	const ATTRIBUTES_TYRES = [
		'pa_producent'       => 'Producent',
		'pa_szerokosc_opony' => 'Szerokość',
		'pa_profil'          => 'Profil',
		'pa_srednica_opony'  => 'Średnica',
	];

	public static function init(): void {
		add_shortcode( 'dawmac_filters', [ __CLASS__, 'render' ] );
		add_action( 'wp_enqueue_scripts', [ __CLASS__, 'register_assets' ] );
		// UWAGA: świadomie BEZ podmiany szablonu sklepu. Wymóg: wtyczka ma
		// niczego nie zmieniać na stronie - sklep renderuje natywny WooCommerce
		// (snippety nwk, CSS, sortowanie, licznik), a filtrowanie robi
		// Dawmac_Filters_Native przez post__in na głównym zapytaniu.
	}

	public static function register_assets(): void {
		wp_register_style(
			'dawmac-filters',
			DAWMAC_FILTERS_URL . 'assets/filters.css',
			[],
			DAWMAC_FILTERS_VERSION
		);
		wp_register_script(
			'dawmac-filters',
			DAWMAC_FILTERS_URL . 'assets/filters.js',
			[],
			DAWMAC_FILTERS_VERSION,
			true
		);
	}

	public static function render(): string {
		// Tryb uśpiony (rollback do Filter Everything): nic nie renderujemy
		// ani nie ładujemy zasobów. Wyjątek: zalogowany admin z ?dawmac_preview=1
		// widzi pełne filtry (podgląd na produkcji PRZED przełączeniem sklepu).
		if ( class_exists( 'Dawmac_Filters_Admin' ) && ! Dawmac_Filters_Admin::is_dawmac_active() ) {
			$preview = isset( $_GET['dawmac_preview'] ) && current_user_can( 'activate_plugins' );
			if ( ! $preview ) {
				if ( current_user_can( 'activate_plugins' ) ) {
					return '<p style="padding:12px;background:#fff8e5;border:1px solid #e0c56e">'
						. 'Dawmac Filtry są uśpione (aktywny Filter Everything). '
						. 'Podgląd: <a href="' . esc_url( add_query_arg( 'dawmac_preview', '1' ) ) . '">zobacz filtry na tej stronie</a>, '
						. 'włączenie: <a href="' . esc_url( admin_url( 'admin.php?page=dawmac-filters' ) ) . '">panel Dawmac Filtry</a>.</p>';
				}
				return '';
			}
			// przelot dalej - pełny render dla podglądu
		}

		wp_enqueue_style( 'dawmac-filters' );
		wp_enqueue_script( 'dawmac-filters' );

		// Config jako JSON w markupie - wp_localize_script bywa zawodny
		// w motywach blokowych (FSE renderuje treść poza normalnym cyklem).
		$config = wp_json_encode( [
			'endpoint'   => DAWMAC_FILTERS_URL . 'endpoint.php',
			'attributes' => self::ATTRIBUTES,
			'perPage'    => 24,
		] );

		// Sam szkielet - wszystko wypełnia JS z odpowiedzi endpointu.
		return '<script type="application/json" id="dawmac-filters-config">' . $config . '</script>
		<div class="dawmac-filters" id="dawmac-filters">
			<aside class="dawmac-side" aria-label="Filtry produktów">
				<div class="dawmac-group dawmac-search-group">
					<label class="dawmac-search">
						<span class="screen-reader-text">Szukaj felg</span>
						<input type="search" class="dawmac-search-input" placeholder="Szukaj felg…" autocomplete="off">
					</label>
				</div>
				<div class="dawmac-sidebar"></div>
			</aside>
			<main class="dawmac-results">
				<div class="dawmac-chips" hidden></div>
				<div class="dawmac-toolbar">
					<span class="dawmac-total" aria-live="polite"></span>
					<select class="dawmac-sort" aria-label="Sortowanie">
						<option value="">Domyślnie</option>
						<option value="price_asc">Cena: rosnąco</option>
						<option value="price_desc">Cena: malejąco</option>
					</select>
				</div>
				<div class="dawmac-grid nwk-wrap" aria-live="polite"></div>
				<button class="dawmac-more" hidden>Pokaż więcej</button>
			</main>
		</div>';
	}
}
