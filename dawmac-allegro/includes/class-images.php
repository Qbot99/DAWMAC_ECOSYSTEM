<?php
/**
 * Wgrywanie grafik na serwery Allegro.
 *
 * Obrazki w OPISIE oferty nie moga wskazywac na obcy serwer - dokumentacja
 * mowi wprost, ze w opisie uzywa sie tylko zdjec przeslanych do Allegro.
 * (Dla galerii oferty zewnetrzne adresy sa dopuszczone, ale nie mieszamy
 * dwoch drog - jedna sciezka, jeden format URL-a, jeden zestaw bledow.)
 *
 * POST /sale/images przyjmuje dwie formy:
 *  - JSON {"url": "..."} - Allegro samo pobiera plik spod adresu,
 *  - surowe bajty z Content-Type obrazka.
 *
 * Odpowiedz zawiera "location" - i to jest adres, ktorego uzywamy w ofercie.
 *
 * Banery szablonu wgrywamy RAZ i trzymamy adresy w opcji. Kluczem cache'a
 * jest skrot pliku, wiec podmiana banera na dysku wymusza ponowne wgranie,
 * a samo odpalenie synchronizacji drugi raz - nie.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Dawmac_Allegro_Images {

	const OPT_CACHE = 'dawmac_allegro_images';

	/** Formaty, ktore Allegro przyjmuje. */
	const MIME = [
		'jpg'  => 'image/jpeg',
		'jpeg' => 'image/jpeg',
		'png'  => 'image/png',
		'webp' => 'image/webp',
	];

	/**
	 * Adres grafiki szablonu po stronie Allegro albo null, gdy jeszcze
	 * jej nie wgrano. Szablon pomija wtedy sekcje zamiast psuc oferte.
	 */
	public static function template_image( string $key ): ?string {
		$cache = get_option( self::OPT_CACHE, [] );

		return isset( $cache[ $key ]['url'] ) ? (string) $cache[ $key ]['url'] : null;
	}

	/**
	 * Wgrywa wszystkie grafiki z config/brand.php, ktorych jeszcze nie ma
	 * albo ktore zmienily sie na dysku.
	 *
	 * @return array<string, string> Klucz grafiki => komunikat o wyniku.
	 */
	public static function sync_template_images(): array {
		$config = dawmac_allegro_config();
		$cache  = get_option( self::OPT_CACHE, [] );
		$report = [];

		foreach ( (array) ( $config['images'] ?? [] ) as $key => $relative ) {
			$path = self::resolve_path( (string) $relative );

			if ( ! $path ) {
				$report[ $key ] = 'BRAK PLIKU: ' . $relative;
				continue;
			}

			$hash = md5_file( $path );

			if ( isset( $cache[ $key ]['hash'] ) && $cache[ $key ]['hash'] === $hash ) {
				$report[ $key ] = 'bez zmian';
				continue;
			}

			$url = self::upload_file( $path );

			if ( is_wp_error( $url ) ) {
				$report[ $key ] = 'BLAD: ' . $url->get_error_message();
				continue;
			}

			$cache[ $key ] = [
				'url'      => $url,
				'hash'     => $hash,
				'uploaded' => time(),
			];

			$report[ $key ] = 'wgrane';
		}

		update_option( self::OPT_CACHE, $cache, 'no' );

		return $report;
	}

	/**
	 * Wgrywa plik z dysku. Idziemy bajtami, a nie przez {"url": ...},
	 * bo grafiki szablonu leza w katalogu wtyczki i nie musza byc
	 * publicznie dostepne, zeby Allegro moglo je pobrac.
	 *
	 * @return string|WP_Error Adres po stronie Allegro.
	 */
	public static function upload_file( string $path ) {
		if ( ! is_readable( $path ) ) {
			return new WP_Error( 'dawmac_allegro_image', 'Nie mogę odczytać pliku: ' . $path );
		}

		$ext  = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );
		$mime = self::MIME[ $ext ] ?? '';

		if ( '' === $mime ) {
			return new WP_Error(
				'dawmac_allegro_image',
				sprintf( 'Format "%s" nie jest obsługiwany. Allegro przyjmuje: %s.', $ext, implode( ', ', array_keys( self::MIME ) ) )
			);
		}

		$bytes = file_get_contents( $path );

		if ( false === $bytes ) {
			return new WP_Error( 'dawmac_allegro_image', 'Nie udało się wczytać pliku: ' . $path );
		}

		$response = Dawmac_Allegro_Client::post_binary( '/sale/images', $bytes, $mime );

		return self::location( $response );
	}

	/**
	 * Wgrywa obrazek, ktory Allegro pobiera samo spod adresu. Uzywamy tego
	 * dla zdjec produktow - lezą juz na dawmac.pl i nie ma sensu przepychac
	 * ich przez PHP drugi raz.
	 *
	 * @return string|WP_Error
	 */
	public static function upload_from_url( string $url ) {
		if ( ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
			return new WP_Error( 'dawmac_allegro_image', 'Niepoprawny adres obrazka: ' . $url );
		}

		$response = Dawmac_Allegro_Client::post( '/sale/images', [
			'url' => $url,
			// Wymagane od czasu AI Actu. Grafiki i zdjęcia produktów są nasze,
			// nie generowane - gdyby to się zmieniło, trzeba podać true,
			// bo oznaczenia NIE DA SIĘ zmienić po wgraniu pliku.
			'isAiCoCreated' => (bool) apply_filters( 'dawmac_allegro_ai_images', false ),
		] );

		return self::location( $response );
	}

	/**
	 * Zdjecia produktu na serwerach Allegro, z cache po adresie zrodlowym.
	 * Bez cache kazda aktualizacja oferty wgrywalaby te same pliki od nowa.
	 *
	 * @param string[] $urls Adresy w sklepie.
	 * @return string[] Adresy po stronie Allegro (pomijamy te, ktorych nie udalo sie wgrac).
	 */
	public static function product_images( array $urls ): array {
		$cache   = get_option( self::OPT_CACHE . '_products', [] );
		$out     = [];
		$changed = false;

		foreach ( array_slice( $urls, 0, 16 ) as $url ) {
			$key = md5( $url );

			if ( isset( $cache[ $key ] ) ) {
				$out[] = (string) $cache[ $key ];
				continue;
			}

			$uploaded = self::upload_from_url( $url );

			if ( is_wp_error( $uploaded ) ) {
				continue;
			}

			$cache[ $key ] = $uploaded;
			$out[]         = $uploaded;
			$changed       = true;
		}

		if ( $changed ) {
			update_option( self::OPT_CACHE . '_products', $cache, 'no' );
		}

		return $out;
	}

	/** Wyciaga "location" z odpowiedzi albo przekazuje blad dalej. */
	private static function location( $response ) {
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$url = $response['location'] ?? '';

		if ( '' === $url ) {
			return new WP_Error( 'dawmac_allegro_image', 'Allegro nie zwróciło adresu wgranego obrazka.' );
		}

		return (string) $url;
	}

	/** Sciezka wzgledem katalogu wtyczki albo bezwzgledna. */
	private static function resolve_path( string $relative ): ?string {
		$path = str_starts_with( $relative, '/' )
			? $relative
			: DAWMAC_ALLEGRO_DIR . ltrim( $relative, '/' );

		return is_readable( $path ) ? $path : null;
	}

	/** Czysci cache - nastepna synchronizacja wgra wszystko od nowa. */
	public static function flush(): void {
		delete_option( self::OPT_CACHE );
		delete_option( self::OPT_CACHE . '_products' );
	}
}
