<?php
/**
 * Bliźniak funkcji normalizującej z galerii (api/gallery/lib/wheel_norm.php).
 *
 * OBIE MUSZĄ DAWAĆ IDENTYCZNY WYNIK. Jeśli zmienisz regułę po jednej stronie,
 * zmień po drugiej i przelicz oba indeksy — inaczej dopasowanie produktów do
 * zdjęć zacznie po cichu gubić trafienia, a nikt tego nie zauważy, bo strona
 * będzie się normalnie wyświetlać, tylko bez zdjęć.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Dawmac_Galeria_Norm {

	/**
	 * "Japan Racing " → "JAPANRACING",  "JR 38" → "JR38"
	 */
	public static function norm( ?string $wartosc ): string {
		$wartosc = (string) $wartosc;

		// Twarda spacja z kopiuj-wklej potrafi udawać zwykłą.
		$wartosc = str_replace( [ "\xC2\xA0", "\xE2\x80\x8B" ], ' ', $wartosc );
		$wartosc = trim( $wartosc );

		if ( '' === $wartosc ) {
			return '';
		}

		$wartosc = mb_strtoupper( $wartosc, 'UTF-8' );
		$wartosc = preg_replace( '~[\s\-_`\x{00B4}\x{2019}\'".]+~u', '', $wartosc );

		return mb_substr( (string) $wartosc, 0, 64, 'UTF-8' );
	}

	/**
	 * Producent i model felgi dla danego produktu, z atrybutów WooCommerce.
	 * Zwraca [ 'brand' => string, 'model' => string ] albo null.
	 */
	public static function felga_produktu( int $product_id ): ?array {
		$producent = self::pierwszy_term( $product_id, 'pa_producent' );
		$model     = self::pierwszy_term( $product_id, 'pa_model' );

		if ( '' === $producent || '' === $model ) {
			return null;
		}

		return [
			'brand' => $producent,
			'model' => $model,
		];
	}

	private static function pierwszy_term( int $product_id, string $taksonomia ): string {
		$terminy = wp_get_post_terms( $product_id, $taksonomia, [ 'fields' => 'names' ] );

		if ( is_wp_error( $terminy ) || ! $terminy ) {
			return '';
		}

		return (string) $terminy[0];
	}
}
