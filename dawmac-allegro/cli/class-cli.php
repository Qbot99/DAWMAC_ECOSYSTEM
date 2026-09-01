<?php
/**
 * Komendy WP-CLI. Wiekszosc pracy przy 32 tys. produktow i tak dzieje sie
 * z konsoli - panel jest do polaczenia konta, nie do mielenia katalogu.
 *
 *   wp dawmac-allegro status
 *   wp dawmac-allegro images
 *   wp dawmac-allegro opis <id_produktu>
 *   wp dawmac-allegro kategorie "felgi aluminiowe 19"
 *   wp dawmac-allegro parametry <id_kategorii>
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Dawmac_Allegro_CLI {

	/**
	 * Stan integracji: srodowisko, polaczenie, waznosc tokenu, grafiki.
	 *
	 * ## EXAMPLES
	 *
	 *     wp dawmac-allegro status
	 */
	public function status(): void {
		$env    = Dawmac_Allegro_Auth::env();
		$tokens = get_option( Dawmac_Allegro_Auth::OPT_TOKENS, [] );

		WP_CLI::line( 'Środowisko:    ' . ( 'sandbox' === $env ? 'sandbox (testowe)' : 'PRODUKCJA' ) );
		WP_CLI::line( 'API:           ' . Dawmac_Allegro_Auth::api_base() );
		WP_CLI::line( 'Aplikacja:     ' . ( Dawmac_Allegro_Auth::is_configured() ? 'skonfigurowana' : 'BRAK client_id/secret' ) );
		WP_CLI::line( 'Konto:         ' . ( Dawmac_Allegro_Auth::is_connected() ? 'połączone' : 'NIE POŁĄCZONE' ) );

		if ( ! empty( $tokens['expires_at'] ) ) {
			$left = (int) $tokens['expires_at'] - time();
			WP_CLI::line( 'Access token:  ' . ( $left > 0
				? sprintf( 'ważny jeszcze %d min', (int) round( $left / 60 ) )
				: 'wygasł (odświeży się przy pierwszym żądaniu)' ) );
		}

		$images = get_option( Dawmac_Allegro_Images::OPT_CACHE, [] );
		$config = dawmac_allegro_config();

		WP_CLI::line( sprintf(
			'Grafiki:       %d z %d wgranych',
			count( $images ),
			count( (array) ( $config['images'] ?? [] ) )
		) );

		WP_CLI::line( sprintf( 'Na magazynie:  %d produktów', count( Dawmac_Allegro_Product_Data::in_stock_ids() ) ) );
	}

	/**
	 * Wgrywa grafiki szablonu na serwery Allegro.
	 *
	 * Pliki, ktore sie nie zmienily, sa pomijane - kluczem jest skrot pliku,
	 * wiec powtorne odpalenie nic nie kosztuje.
	 *
	 * ## EXAMPLES
	 *
	 *     wp dawmac-allegro images
	 */
	public function images(): void {
		$report = Dawmac_Allegro_Images::sync_template_images();

		if ( ! $report ) {
			WP_CLI::warning( 'Nie ma czego wgrywać - sprawdź sekcję images w config/brand.php.' );
			return;
		}

		$errors = 0;

		foreach ( $report as $key => $result ) {
			if ( str_starts_with( $result, 'BŁĄD' ) || str_starts_with( $result, 'BLAD' ) || str_starts_with( $result, 'BRAK' ) ) {
				WP_CLI::line( WP_CLI::colorize( sprintf( '  %%R%-16s%%n %s', $key, $result ) ) );
				++$errors;
				continue;
			}

			WP_CLI::line( sprintf( '  %-16s %s', $key, $result ) );
		}

		if ( $errors ) {
			WP_CLI::error( sprintf( '%d grafik nie wgrano.', $errors ) );
		}

		WP_CLI::success( 'Grafiki szablonu gotowe.' );
	}

	/**
	 * Buduje opis oferty dla produktu ze sklepu i sprawdza go walidatorem.
	 *
	 * ## OPTIONS
	 *
	 * <id>
	 * : ID produktu WooCommerce.
	 *
	 * [--json]
	 * : Wypisz surowy JSON zamiast podsumowania.
	 *
	 * ## EXAMPLES
	 *
	 *     wp dawmac-allegro opis 12345
	 *     wp dawmac-allegro opis 12345 --json
	 */
	public function opis( array $args, array $flags ): void {
		$product = wc_get_product( (int) $args[0] );

		if ( ! $product ) {
			WP_CLI::error( 'Nie ma produktu o ID ' . (int) $args[0] );
		}

		$data     = Dawmac_Allegro_Product_Data::from_wc( $product );
		$template = dawmac_allegro_template();
		$desc     = $template->build( $data );
		$errors   = Dawmac_Allegro_Template::validate( $desc );

		if ( isset( $flags['json'] ) ) {
			WP_CLI::line( wp_json_encode( $desc, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );
			return;
		}

		$title = $template->build_offer_title( $data );

		WP_CLI::line( 'Produkt:  ' . $product->get_name() );
		WP_CLI::line( sprintf( 'Tytuł:    %s (%d/75)', $title, mb_strlen( $title, 'UTF-8' ) ) );
		WP_CLI::line( 'Sekcji:   ' . count( $desc['sections'] ) );
		WP_CLI::line( 'Magazyn:  ' . ( $data['dostepny'] ? 'jest' : 'BRAK - ta oferta nie powinna powstać' ) );

		if ( $errors ) {
			foreach ( $errors as $e ) {
				WP_CLI::line( WP_CLI::colorize( '  %R' . $e . '%n' ) );
			}
			WP_CLI::error( sprintf( '%d błędów walidacji.', count( $errors ) ) );
		}

		WP_CLI::success( 'Opis przechodzi walidację.' );
	}

	/**
	 * Podpowiada kategorie Allegro dla podanej nazwy.
	 *
	 * Sluzy do zmapowania kategorii sklepu na drzewo Allegro - bez tego
	 * nie da sie wystawic oferty, bo wymagane parametry zaleza od kategorii.
	 *
	 * ## OPTIONS
	 *
	 * <fraza>
	 * : Nazwa produktu albo kategorii, np. "felgi aluminiowe 19".
	 *
	 * ## EXAMPLES
	 *
	 *     wp dawmac-allegro kategorie "felgi aluminiowe 5x112"
	 */
	public function kategorie( array $args ): void {
		$response = Dawmac_Allegro_Client::get( '/sale/matching-categories', [ 'name' => (string) $args[0] ] );

		if ( is_wp_error( $response ) ) {
			WP_CLI::error( $response->get_error_message() );
		}

		$rows = [];

		foreach ( ( $response['matchingCategories'] ?? [] ) as $cat ) {
			$rows[] = [
				'id'   => $cat['id'] ?? '',
				'nazwa' => $cat['name'] ?? '',
				'ścieżka' => implode( ' > ', array_column( $cat['parents'] ?? [], 'name' ) ),
			];
		}

		if ( ! $rows ) {
			WP_CLI::warning( 'Allegro nie podpowiedziało żadnej kategorii.' );
			return;
		}

		WP_CLI\Utils\format_items( 'table', $rows, [ 'id', 'nazwa', 'ścieżka' ] );
	}

	/**
	 * Wypisuje parametry wymagane w kategorii Allegro.
	 *
	 * To jest wejscie do mapowania: kolumna "wymagany" mowi, czego oferta
	 * nie przyjmie bez wartosci, a "słownik" - czy wartosc musi pochodzic
	 * z zamknietej listy, czy moze byc dowolna.
	 *
	 * ## OPTIONS
	 *
	 * <id>
	 * : ID kategorii Allegro (z komendy "kategorie").
	 *
	 * [--wymagane]
	 * : Pokaż tylko parametry wymagane.
	 *
	 * ## EXAMPLES
	 *
	 *     wp dawmac-allegro parametry 257773
	 *     wp dawmac-allegro parametry 257773 --wymagane
	 */
	public function parametry( array $args, array $flags ): void {
		$id       = (string) $args[0];
		$response = Dawmac_Allegro_Client::get( "/sale/categories/{$id}/parameters" );

		if ( is_wp_error( $response ) ) {
			WP_CLI::error( $response->get_error_message() );
		}

		$only_required = isset( $flags['wymagane'] );
		$rows          = [];

		foreach ( ( $response['parameters'] ?? [] ) as $p ) {
			$required = ! empty( $p['required'] );

			if ( $only_required && ! $required ) {
				continue;
			}

			$values = $p['dictionary'] ?? [];

			$rows[] = [
				'id'       => $p['id'] ?? '',
				'nazwa'    => $p['name'] ?? '',
				'typ'      => $p['type'] ?? '',
				'wymagany' => $required ? 'TAK' : '',
				'jednostka' => $p['unit'] ?? '',
				'słownik'  => $values
					? sprintf( '%d wartości: %s…', count( $values ), implode( ', ', array_slice( array_column( $values, 'value' ), 0, 3 ) ) )
					: 'dowolna',
			];
		}

		if ( ! $rows ) {
			WP_CLI::warning( 'Brak parametrów do pokazania.' );
			return;
		}

		WP_CLI\Utils\format_items( 'table', $rows, [ 'id', 'nazwa', 'typ', 'wymagany', 'jednostka', 'słownik' ] );
	}
}
