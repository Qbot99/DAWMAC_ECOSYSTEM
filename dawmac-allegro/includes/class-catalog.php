<?php
/**
 * Dopasowanie produktu ze sklepu do katalogu produktow Allegro.
 *
 * Po co: oferta podpieta pod pozycje katalogowa dziedziczy jej dane GPSR
 * i parametry, i trafia na strone produktu Allegro. Wlasny produkt tego
 * nie dostaje - trzeba przy nim podac wszystko samodzielnie.
 *
 * WAZNE, JAK KATALOG JEST ZBUDOWANY: pozycje sa na POJEDYNCZA FELGE
 * ("Felga aluminiowa Concaver CVR1 9.5\" x 20\" 5x112 ET 20"), nie na komplet.
 * Dlatego:
 *   - komplet jednorodny  = 1 pozycja katalogowa x 4 szt.,
 *   - zestaw schodkowy    = 2 pozycje (przod i tyl) x 2 szt. kazda.
 *
 * Dopasowanie jest zachowawcze: model, srednica, szerokosc i rozstaw musza
 * zgadzac sie co do wartosci. Wolimy wystawic wlasny produkt niz podpiac
 * oferte pod niewlasciwa pozycje katalogu - to drugie konczy sie zwrotem
 * i sporem, bo kupujacy dostaje co innego, niz pokazywala strona produktu.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Dawmac_Allegro_Catalog {

	/** Cache dopasowania przy produkcie - katalog nie zmienia sie z minuty na minute. */
	const META_MATCH = '_dawmac_allegro_catalog';

	/** Ile felg liczymy na komplet. */
	const NA_KOMPLET = 4;

	/**
	 * Szuka pozycji katalogowych dla produktu.
	 *
	 * @return array{
	 *   status: string,            pelne|czesciowe|brak
	 *   productSet: array,         gotowe do wyslania (puste, gdy nie pelne)
	 *   trafienia: array,          szerokosc => [id, nazwa]
	 *   szerokosci: float[]
	 * }
	 */
	public static function match( array $dane, bool $odswiez = false ): array {
		$pid = (int) ( $dane['id'] ?? 0 );

		if ( ! $odswiez && $pid ) {
			$cache = get_post_meta( $pid, self::META_MATCH, true );

			if ( is_array( $cache ) && isset( $cache['status'] ) ) {
				return $cache;
			}
		}

		$szerokosci = self::szerokosci( $dane );
		$wynik      = [ 'status' => 'brak', 'productSet' => [], 'trafienia' => [], 'szerokosci' => $szerokosci ];

		if ( ! $szerokosci ) {
			return self::zapisz( $pid, $wynik );
		}

		$kandydaci = self::szukaj( $dane );

		foreach ( $szerokosci as $w ) {
			$hit = self::wybierz( $kandydaci, $dane, $w );

			if ( $hit ) {
				$wynik['trafienia'][ (string) $w ] = $hit;
			}
		}

		$ile = count( $wynik['trafienia'] );

		if ( 0 === $ile ) {
			return self::zapisz( $pid, $wynik );
		}

		$wynik['status'] = ( $ile === count( $szerokosci ) ) ? 'pelne' : 'czesciowe';

		if ( 'pelne' === $wynik['status'] ) {
			// Komplet dzielimy rowno miedzy znalezione pozycje: 4 przy jednej
			// szerokosci, 2+2 przy schodkowym.
			$na_pozycje = intdiv( self::NA_KOMPLET, $ile );

			foreach ( $wynik['trafienia'] as $hit ) {
				$wynik['productSet'][] = [
					'product'  => [ 'id' => $hit['id'] ],
					'quantity' => [ 'value' => $na_pozycje ],
				];
			}
		}

		return self::zapisz( $pid, $wynik );
	}

	/**
	 * Kandydaci z katalogu. Fraza celowo bez ET - w sklepie i w katalogu
	 * potrafi sie roznic, a jest tylko zawezeniem, nie rozroznikiem.
	 */
	private static function szukaj( array $dane ): array {
		$fraza = trim( sprintf(
			'%s %s %s %s',
			$dane['producent'] ?? '',
			$dane['model'] ?? '',
			self::srednica( $dane ),
			self::rozstaw( $dane )
		) );

		$r = Dawmac_Allegro_Client::get( '/sale/products', [
			'phrase'      => $fraza,
			'category.id' => Dawmac_Allegro_Mapper::CATEGORY_WHEELS,
			'limit'       => 20,
		] );

		if ( is_wp_error( $r ) ) {
			return [];
		}

		$out = [];

		foreach ( $r['products'] ?? [] as $p ) {
			$out[] = [
				'id'    => (string) ( $p['id'] ?? '' ),
				'nazwa' => (string) ( $p['name'] ?? '' ),
				'k'     => self::klucz( (string) ( $p['name'] ?? '' ) ),
			];
		}

		return $out;
	}

	/** Pierwszy kandydat zgodny co do modelu, srednicy, szerokosci i rozstawu. */
	private static function wybierz( array $kandydaci, array $dane, float $szerokosc ): ?array {
		$model    = self::norm_model( (string) ( $dane['model'] ?? '' ) );
		$srednica = self::srednica( $dane );
		$rozstaw  = self::norm_rozstaw( self::rozstaw( $dane ) );

		foreach ( $kandydaci as $c ) {
			if ( '' === $model || $c['k']['model'] !== $model ) {
				continue;
			}

			if ( (string) $c['k']['srednica'] !== (string) $srednica ) {
				continue;
			}

			if ( null === $c['k']['szerokosc'] || abs( $c['k']['szerokosc'] - $szerokosc ) > 0.01 ) {
				continue;
			}

			// Rozstaw w nazwie katalogowej bywa pominiety - wtedy nie blokujemy.
			if ( '' !== $c['k']['rozstaw'] && $c['k']['rozstaw'] !== $rozstaw ) {
				continue;
			}

			return [ 'id' => $c['id'], 'nazwa' => $c['nazwa'] ];
		}

		return null;
	}

	/** Rozklada nazwe katalogowa na czesci techniczne. */
	private static function klucz( string $nazwa ): array {
		$n = mb_strtolower( str_replace( ',', '.', $nazwa ), 'UTF-8' );

		preg_match( '/\b((?:jr|cvr)\s*-?\s*\d+)\b/u', $n, $m );
		$model = isset( $m[1] ) ? self::norm_model( $m[1] ) : '';

		// "9.5" x 20"" albo "12.5 x 21""
		preg_match( '/(\d{1,2}(?:\.\d)?)\s*"?\s*x\s*(\d{2})\s*"/u', $n, $m );

		preg_match( '/\b(\d)x(\d{2,3}(?:\.\d)?)\b/u', $n, $r );

		return [
			'model'     => $model,
			'szerokosc' => isset( $m[1] ) ? (float) $m[1] : null,
			'srednica'  => $m[2] ?? '',
			'rozstaw'   => isset( $r[1] ) ? self::norm_rozstaw( $r[1] . 'x' . $r[2] ) : '',
		];
	}

	/** @return float[] posortowane rosnaco */
	private static function szerokosci( array $dane ): array {
		$v = $dane['szerokosc'] ?? [];
		$v = is_array( $v ) ? $v : [ $v ];
		$out = [];

		foreach ( $v as $x ) {
			$f = (float) str_replace( ',', '.', preg_replace( '/[^0-9.,]/', '', (string) $x ) );

			if ( $f > 0 ) {
				$out[] = $f;
			}
		}

		sort( $out );

		return array_values( array_unique( $out ) );
	}

	private static function srednica( array $dane ): string {
		$v = $dane['srednica'] ?? '';
		$v = is_array( $v ) ? ( $v[0] ?? '' ) : $v;

		return preg_replace( '/[^0-9]/', '', (string) $v ) ?? '';
	}

	private static function rozstaw( array $dane ): string {
		$v = $dane['rozstaw'] ?? '';
		$v = is_array( $v ) ? ( $v[0] ?? '' ) : $v;

		return trim( (string) $v );
	}

	private static function norm_model( string $m ): string {
		return mb_strtolower( preg_replace( '/[\s-]/', '', $m ) ?? $m, 'UTF-8' );
	}

	/** "5x114,30" -> "5x114.3"; "5x100" zostaje "5x100". */
	private static function norm_rozstaw( string $r ): string {
		$r = str_replace( ',', '.', mb_strtolower( trim( $r ), 'UTF-8' ) );

		if ( ! preg_match( '/^(\d)x(\d{2,3}(?:\.\d+)?)$/', $r, $m ) ) {
			return $r;
		}

		$liczba = $m[2];

		if ( str_contains( $liczba, '.' ) ) {
			$liczba = rtrim( rtrim( $liczba, '0' ), '.' );
		}

		return $m[1] . 'x' . $liczba;
	}

	private static function zapisz( int $pid, array $wynik ): array {
		if ( $pid ) {
			update_post_meta( $pid, self::META_MATCH, $wynik );
		}

		return $wynik;
	}

	/** Kasuje zapamietane dopasowania - do ponownego przeliczenia. */
	public static function flush(): void {
		global $wpdb;

		$wpdb->delete( $wpdb->postmeta, [ 'meta_key' => self::META_MATCH ] );
	}
}
