<?php
/**
 * Panel przełączania: Dawmac Filtry <-> Filter Everything, jednym kliknięciem.
 *
 * Idea bezpiecznika wdrożenia:
 *  - Instalacja tej wtyczki NICZEGO nie zmienia na żywym sklepie, dopóki
 *    świadomie nie klikniesz "Włącz Dawmac Filtry" (przy aktywnym Filter
 *    Everything domyślny tryb to 'filter_everything' = uśpiony).
 *  - "Włącz Dawmac" -> nasze filtry działają, Filter Everything zostaje
 *    zdezaktywowany (ale NIE usunięty).
 *  - "Przywróć Filter Everything" (rollback) -> Filter Everything z powrotem
 *    aktywny, nasze filtry usypiają. Ta wtyczka zostaje aktywna, więc przycisk
 *    powrotu jest zawsze pod ręką, a indeks dalej aktualizuje się w tle.
 *
 * Nic nie jest kasowane - przełącznik flipuje tylko stan aktywności.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Dawmac_Filters_Admin {

	const MODE_OPTION = 'dawmac_filters_mode';
	const TOGGLE_ACTION = 'dawmac_filters_toggle';

	public static function init(): void {
		add_action( 'admin_menu', [ __CLASS__, 'add_menu' ] );
		add_action( 'admin_bar_menu', [ __CLASS__, 'admin_bar' ], 100 );
		add_action( 'admin_post_' . self::TOGGLE_ACTION, [ __CLASS__, 'handle_toggle' ] );
	}

	/** Aktualny tryb: 'dawmac' albo 'filter_everything'. */
	public static function current_mode(): string {
		return get_option( self::MODE_OPTION, 'dawmac' );
	}

	/** Czy nasze filtry mają się renderować. */
	public static function is_dawmac_active(): bool {
		return 'dawmac' === self::current_mode();
	}

	/**
	 * Ustala domyślny tryb przy aktywacji: jeśli Filter Everything jest aktywny,
	 * zaczynamy jako UŚPIONY (nie ruszamy żywego sklepu). Bez FE - od razu 'dawmac'.
	 * Nie nadpisuje istniejącego wyboru.
	 */
	public static function set_default_mode(): void {
		if ( false !== get_option( self::MODE_OPTION, false ) ) {
			return; // wybór już istnieje - nie ruszamy
		}
		$fe = self::fe_plugin_file();
		$mode = ( $fe && is_plugin_active( $fe ) ) ? 'filter_everything' : 'dawmac';
		add_option( self::MODE_OPTION, $mode );
	}

	/**
	 * Ścieżka pluginu Filter Everything (dowolna wersja) - wykrywana po nazwie,
	 * nie po sztywnym slugu. '' jeśli nie znaleziono.
	 */
	public static function fe_plugin_file(): string {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		foreach ( get_plugins() as $file => $data ) {
			if ( false !== stripos( $data['Name'], 'Filter Everything' ) ) {
				return $file;
			}
		}
		return '';
	}

	public static function add_menu(): void {
		add_menu_page(
			'Hubert - Dawmac Filtry',
			'Hubert',
			'activate_plugins',
			'dawmac-filters',
			[ __CLASS__, 'render_page' ],
			'dashicons-filter',
			58
		);
	}

	/** Szybki przełącznik w górnym pasku admina (jedno kliknięcie z każdej strony). */
	public static function admin_bar( $bar ): void {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		$is_dawmac = self::is_dawmac_active();
		$target    = $is_dawmac ? 'filter_everything' : 'dawmac';
		$label     = $is_dawmac
			? 'Filtry: Dawmac ✓ - przełącz na Filter Everything'
			: 'Filtry: Filter Everything - włącz Dawmac';

		$url = wp_nonce_url(
			admin_url( 'admin-post.php?action=' . self::TOGGLE_ACTION . '&mode=' . $target ),
			self::TOGGLE_ACTION
		);

		$bar->add_node( [
			'id'    => 'dawmac-filters-toggle',
			'title' => '⇄ ' . $label,
			'href'  => $url,
			'meta'  => [ 'title' => 'Przełącz silnik filtrów' ],
		] );
	}

	/** Wykonuje przełączenie po kliknięciu (z paska lub ze strony). */
	public static function handle_toggle(): void {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			wp_die( 'Brak uprawnień.' );
		}
		check_admin_referer( self::TOGGLE_ACTION );

		require_once ABSPATH . 'wp-admin/includes/plugin.php';

		$target = ( 'dawmac' === ( $_REQUEST['mode'] ?? '' ) ) ? 'dawmac' : 'filter_everything';
		$fe     = self::fe_plugin_file();
		$notice = '';

		if ( 'dawmac' === $target ) {
			update_option( self::MODE_OPTION, 'dawmac' );
			// Filter Everything ZOSTAJE aktywny - obsługuje kategorie
			// (m.in. opony). Na samej stronie sklepu jego widget jest
			// wyciszany filtrem sidebars_widgets. Czysty podział ról.
			if ( $fe && ! is_plugin_active( $fe ) ) {
				$res = activate_plugin( $fe );
				if ( is_wp_error( $res ) ) {
					$notice = 'fe_error';
				}
			}
			if ( '' === $notice ) {
				$notice = 'dawmac';
			}
		} else {
			update_option( self::MODE_OPTION, 'filter_everything' );
			if ( $fe && ! is_plugin_active( $fe ) ) {
				$res = activate_plugin( $fe );
				if ( is_wp_error( $res ) ) {
					$notice = 'fe_error';
				}
			}
			if ( '' === $notice ) {
				$notice = 'fe';
			}
		}

		wp_safe_redirect( add_query_arg(
			[ 'page' => 'dawmac-filters', 'switched' => $notice ],
			admin_url( 'admin.php' )
		) );
		exit;
	}

	public static function render_page(): void {
		$mode      = self::current_mode();
		$is_dawmac = 'dawmac' === $mode;
		$fe        = self::fe_plugin_file();
		$fe_active = $fe && is_plugin_active( $fe );
		$fe_name   = $fe ? ( get_plugin_data( ABSPATH . 'wp-content/plugins/' . $fe )['Name'] ?? $fe ) : '';

		$switched = $_GET['switched'] ?? '';
		echo '<div class="wrap"><h1>Hubert - Dawmac Filtry '
			. '<span style="font-size:14px;font-weight:400;color:#666;vertical-align:middle;background:#f0f0f1;border:1px solid #ccd0d4;border-radius:99px;padding:3px 12px;margin-left:8px">'
			. 'wersja ' . esc_html( DAWMAC_FILTERS_VERSION ) . '</span></h1>';

		if ( 'dawmac' === $switched ) {
			echo '<div class="notice notice-success"><p><strong>Włączono Dawmac Filtry na stronie sklepu (felgi).</strong> Filter Everything działa dalej i obsługuje kategorie - w tym opony.</p></div>';
		} elseif ( 'fe' === $switched ) {
			echo '<div class="notice notice-success"><p><strong>Przywrócono Filter Everything wszędzie.</strong> Nasze filtry uśpione (indeks aktualizuje się dalej w tle).</p></div>';
		} elseif ( 'fe_error' === $switched ) {
			echo '<div class="notice notice-error"><p>Nie udało się aktywować Filter Everything - sprawdź plugin ręcznie w zakładce Wtyczki.</p></div>';
		}

		// Aktualny stan.
		$state_color = $is_dawmac ? '#1a7f37' : '#8a6d00';
		echo '<div style="background:#fff;border:1px solid #ccd0d4;border-left:4px solid ' . esc_attr( $state_color ) . ';padding:14px 18px;margin:18px 0;max-width:720px">';
		echo '<h2 style="margin-top:0">Strona sklepu (felgi): <span style="color:' . esc_attr( $state_color ) . '">'
			. ( $is_dawmac ? 'Dawmac Filtry (nowy, szybki)' : 'Filter Everything (stary)' ) . '</span></h2>';
		echo '<p><strong>Kategorie (m.in. opony): zawsze Filter Everything</strong> - tam nic nie zmieniamy.</p>';
		echo '<p>Filter Everything wykryty: ' . ( $fe ? '<code>' . esc_html( $fe_name ) . '</code> - ' . ( $fe_active ? 'aktywny' : 'nieaktywny' ) : '<em>nie znaleziono</em>' ) . '</p>';
		echo '</div>';

		// Przyciski przełączania.
		echo '<div style="display:flex;gap:16px;flex-wrap:wrap;max-width:720px">';

		// -> Dawmac
		$to_dawmac = wp_nonce_url( admin_url( 'admin-post.php?action=' . self::TOGGLE_ACTION . '&mode=dawmac' ), self::TOGGLE_ACTION );
		printf(
			'<a href="%s" class="button button-primary button-hero" %s>➜ Włącz Dawmac Filtry%s</a>',
			esc_url( $to_dawmac ),
			$is_dawmac ? 'style="pointer-events:none;opacity:.5"' : '',
			$is_dawmac ? ' (aktywne)' : '<br><small>tylko strona sklepu; opony zostają na FE</small>'
		);

		// -> Filter Everything (rollback)
		$to_fe = wp_nonce_url( admin_url( 'admin-post.php?action=' . self::TOGGLE_ACTION . '&mode=filter_everything' ), self::TOGGLE_ACTION );
		printf(
			'<a href="%s" class="button button-hero" style="border-color:#b32d2e;color:#b32d2e;%s" onclick="return confirm(\'Przywrócić Filter Everything i uśpić Dawmac Filtry?\')">↩ Przywróć Filter Everything%s</a>',
			esc_url( $to_fe ),
			$is_dawmac ? '' : 'pointer-events:none;opacity:.5',
			$is_dawmac ? '<br><small>rollback - cofnij zmiany</small>' : ' (aktywne)'
		);

		echo '</div>';

		// Stan indeksu i nocnego odświeżania.
		$last = get_option( Dawmac_Filters_Indexer::LAST_OPTION );
		$next = wp_next_scheduled( Dawmac_Filters_Indexer::NIGHTLY_HOOK );
		$fmt  = static fn( $ts ) => wp_date( 'j.m.Y H:i', (int) $ts );

		echo '<div style="background:#fff;border:1px solid #ccd0d4;padding:14px 18px;margin:18px 0;max-width:720px">';
		echo '<h3 style="margin-top:0">Indeks produktów</h3><p style="margin:0">';
		if ( is_array( $last ) && ! empty( $last['finished'] ) ) {
			printf(
				'Ostatnie odświeżenie: <strong>%s</strong> (%d produktów, %d wierszy, %d s)',
				esc_html( $fmt( $last['finished'] ) ),
				(int) $last['products'],
				(int) $last['rows'],
				(int) $last['seconds']
			);
			if ( ! empty( $last['usuniete'] ) ) {
				printf( ', usunięto nieaktualnych: %d', (int) $last['usuniete'] );
			}
		} else {
			echo 'Nocne odświeżanie jeszcze się nie wykonało.';
		}
		echo '<br>Następne zaplanowane: <strong>' . ( $next ? esc_html( $fmt( $next ) ) : 'brak' ) . '</strong>';
		echo '<br><span style="color:#666">Indeks aktualizuje się też na bieżąco przy każdym zapisie produktu; nocny przebieg łapie zmiany zrobione poza panelem (importy, edycje w bazie).</span>';
		echo '</p></div>';

		echo '<p style="margin-top:22px;max-width:720px;color:#555">'
			. 'Przełącznik jest też w górnym pasku (⇄) - działa z każdej strony wp-admin. '
			. 'Nic nie jest usuwane: przełączamy tylko, który silnik filtrów jest aktywny. '
			. 'Rollback zawsze o jedno kliknięcie.</p>';

		echo '</div>';
	}
}
