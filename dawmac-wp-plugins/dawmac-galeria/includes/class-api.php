<?php
/**
 * Rozmowa z galerią: pobranie aut dla danej felgi + cache.
 *
 * Cache jest tu obowiązkowy, nie opcjonalny. Sklep ma 32 630 produktów,
 * ale różnych felg ze zdjęciami jest kilkaset — bez cache każda odsłona
 * karty produktu waliłaby po HTTP do drugiego serwera i zabiła TTFB.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Dawmac_Galeria_API {

	const OPT_URL   = 'dawmac_galeria_api_url';
	const OPT_TTL   = 'dawmac_galeria_cache_h';
	const PREFIX    = 'dawmac_gal_';

	const URL_DOMYSLNY = 'https://api.dawmacpolska.pl/api/gallery/';
	const URL_ZDJEC    = 'https://api.dawmacpolska.pl/gallery/';

	public static function adres(): string {
		$url = trim( (string) get_option( self::OPT_URL, self::URL_DOMYSLNY ) );

		return trailingslashit( '' !== $url ? $url : self::URL_DOMYSLNY );
	}

	public static function ttl_godzin(): int {
		$h = (int) get_option( self::OPT_TTL, 12 );

		return $h > 0 ? min( $h, 168 ) : 12;
	}

	/**
	 * Indeks felg, dla których w galerii SĄ jakiekolwiek zdjęcia.
	 *
	 * To jest ta optymalizacja, bez której cały pomysł nie skaluje się do
	 * 32 tysięcy produktów: felg ze zdjęciami jest ~600, więc dla ponad 95%
	 * kart odpowiedź brzmi "nie ma" i można ją dać bez ruszania sieci.
	 * Indeks to kilkaset krótkich kluczy — kilka kilobajtów w cache.
	 */
	public static function ma_zdjecia( string $brandNorm, string $modelNorm ): bool {
		$klucz = self::PREFIX . 'index';
		$indeks = get_transient( $klucz );

		if ( false === $indeks ) {
			$odp = wp_remote_get(
				self::adres() . 'get_wheels_with_photos.php',
				[
					'timeout'    => 8,
					'user-agent' => 'DawmacGaleria/' . DAWMAC_GALERIA_VERSION . '; ' . home_url( '/' ),
				]
			);

			if ( is_wp_error( $odp ) || 200 !== (int) wp_remote_retrieve_response_code( $odp ) ) {
				// Nie udało się pobrać indeksu — nie blokujemy z tego powodu
				// wyświetlania. Zwracamy true, żeby zapytać wprost o felgę.
				set_transient( $klucz, [], 5 * MINUTE_IN_SECONDS );
				return true;
			}

			$dane   = json_decode( wp_remote_retrieve_body( $odp ), true );
			$lista  = is_array( $dane['wheels'] ?? null ) ? $dane['wheels'] : [];
			$indeks = array_fill_keys( $lista, true );

			set_transient( $klucz, $indeks, self::ttl_godzin() * HOUR_IN_SECONDS );
		}

		// Pusty indeks = nie udało się go pobrać; wtedy pytamy wprost.
		if ( ! $indeks ) {
			return true;
		}

		return isset( $indeks[ $brandNorm . '|' . $modelNorm ] );
	}

	/**
	 * Auta dla felgi. Zwraca tablicę projektów (może być pusta).
	 *
	 * Pustą odpowiedź też cachujemy, tylko krócej — inaczej każdy produkt bez
	 * zdjęć odpytywałby galerię przy każdej odsłonie, a takich produktów jest
	 * większość.
	 */
	public static function projekty_dla_felgi( string $brand, string $model, int $limit = 12 ): array {
		$bn = Dawmac_Galeria_Norm::norm( $brand );
		$mn = Dawmac_Galeria_Norm::norm( $model );

		if ( '' === $bn || '' === $mn ) {
			return [];
		}

		// Najpierw tani odczyt z indeksu — dla większości produktów kończy się tutaj.
		if ( ! self::ma_zdjecia( $bn, $mn ) ) {
			return [];
		}

		$klucz = self::PREFIX . md5( $bn . '|' . $mn . '|' . $limit );
		$cache = get_transient( $klucz );

		if ( false !== $cache ) {
			return is_array( $cache ) ? $cache : [];
		}

		$url = add_query_arg(
			[
				'brand'  => $brand,
				'model'  => $model,
				'limit'  => $limit,
				'images' => 1,
			],
			self::adres() . 'get_projects_by_wheel.php'
		);

		$odp = wp_remote_get(
			$url,
			[
				'timeout'    => 8,
				'user-agent' => 'DawmacGaleria/' . DAWMAC_GALERIA_VERSION . '; ' . home_url( '/' ),
			]
		);

		if ( is_wp_error( $odp ) || 200 !== (int) wp_remote_retrieve_response_code( $odp ) ) {
			// Krótki cache na błąd, żeby padnięta galeria nie spowalniała sklepu
			// przy każdej odsłonie.
			set_transient( $klucz, [], 5 * MINUTE_IN_SECONDS );
			return [];
		}

		$dane = json_decode( wp_remote_retrieve_body( $odp ), true );
		$projekty = is_array( $dane['projects'] ?? null ) ? $dane['projects'] : [];

		set_transient(
			$klucz,
			$projekty,
			( $projekty ? self::ttl_godzin() : 2 ) * HOUR_IN_SECONDS
		);

		return $projekty;
	}

	/**
	 * Pełny adres zdjęcia. W bazie galerii są dwa formaty ścieżek:
	 * "images/plik.jpg" (nowe, z aplikacji) i "/images/2869/plik.webp"
	 * (stare, z podkatalogiem projektu) — oba muszą zadziałać.
	 */
	public static function url_zdjecia( string $sciezka ): string {
		return self::URL_ZDJEC . ltrim( $sciezka, '/' );
	}

	/**
	 * Adres miniatury (thumb700_ przed nazwą pliku).
	 * Gdyby miniatury nie było, front ma fallback na pełny plik.
	 */
	public static function url_miniatury( string $sciezka ): string {
		$sciezka = ltrim( $sciezka, '/' );
		$katalog = dirname( $sciezka );
		$plik    = basename( $sciezka );

		$katalog = ( '.' === $katalog ) ? '' : $katalog . '/';

		return self::URL_ZDJEC . $katalog . 'thumb700_' . $plik;
	}

	/** Kasuje cały cache galerii — po zmianach w dopasowaniu. */
	public static function wyczysc_cache(): void {
		global $wpdb;

		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
				$wpdb->esc_like( '_transient_' . self::PREFIX ) . '%',
				$wpdb->esc_like( '_transient_timeout_' . self::PREFIX ) . '%'
			)
		);
	}
}
