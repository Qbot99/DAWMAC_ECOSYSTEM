<?php
/**
 * Czyszczenie tekstu pod opis oferty Allegro.
 *
 * Allegro przepuszcza w itemach TEXT dokladnie siedem znacznikow:
 * h1, h2, p, ul, ol, li, b - i nic wiecej. Bez <br/>, bez <i>, bez <a>,
 * bez tabel, bez atrybutow style/class. Naglowkow h1/h2 nie wolno
 * dodatkowo formatowac, wiec <b> w srodku h1 wywala walidacje.
 *
 * Druga rzecz, przez ktora leca odrzucenia ofert: w opisie nie moze byc
 * linkow, maili ani telefonow - Allegro czyta to jako zachete do zakupu
 * poza serwisem. Opisy produktow w Woo maja je regularnie (stopki, kontakt),
 * stad twarde wycinanie zamiast liczenia na to, ze ktos przypilnuje recznie.
 *
 * Klasa jest czystym PHP - bez zaleznosci od WordPressa - zeby dalo sie ja
 * odpalic w tools/preview.php i w testach bez stawiania calego sklepu.
 */

if ( defined( 'ABSPATH' ) && ! defined( 'DAWMAC_ALLEGRO_VERSION' ) ) {
	exit;
}

class Dawmac_Allegro_Text {

	/** Znaczniki, ktore Allegro akceptuje w itemie TEXT. */
	const ALLOWED = [ 'h1', 'h2', 'p', 'ul', 'ol', 'li', 'b' ];

	/** Znaczniki blokowe - opis musi sie z nich skladac na najwyzszym poziomie. */
	const BLOCKS = [ 'h1', 'h2', 'p', 'ul', 'ol' ];

	/**
	 * Pelny przebieg czyszczenia: dowolny HTML -> HTML strawny dla Allegro.
	 */
	public static function clean( string $html ): string {
		$s = self::normalize_tags( $html );
		$s = self::strip_disallowed( $s );
		$s = self::strip_attributes( $s );
		$s = self::unformat_headings( $s );
		$s = self::strip_contacts( $s );
		$s = self::escape_stray_entities( $s );
		$s = self::drop_empty_blocks( $s );
		$s = self::ensure_block_wrapped( $s );

		return trim( $s );
	}

	/**
	 * Tekst bez zadnych znacznikow - do h1/h2 i do tytulu oferty.
	 */
	public static function plain( string $html ): string {
		$s = preg_replace( '#<(br|/p|/h[1-6]|/li)\s*/?>#i', ' ', $html ) ?? $html;
		$s = strip_tags( $s );
		$s = html_entity_decode( $s, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$s = self::strip_contacts( $s );
		$s = preg_replace( '/\s+/u', ' ', $s ) ?? $s;

		return trim( $s );
	}

	/**
	 * Zamienia to, co ma bliski odpowiednik, zamiast wyrzucac razem z trescia.
	 * <br> nie jest dozwolony, wiec staje sie granica akapitu - inaczej
	 * lista parametrow rozjechalaby sie w jeden ciag.
	 */
	private static function normalize_tags( string $html ): string {
		$map = [
			'#<\s*strong(\s[^>]*)?>#i' => '<b>',
			'#<\s*/\s*strong\s*>#i'    => '</b>',
			'#<\s*(h[3-6])(\s[^>]*)?>#i' => '<h2>',
			'#<\s*/\s*h[3-6]\s*>#i'      => '</h2>',
			'#<\s*div(\s[^>]*)?>#i'      => '<p>',
			'#<\s*/\s*div\s*>#i'         => '</p>',
			'#<\s*br\s*/?\s*>#i'         => '</p><p>',
		];

		$s = preg_replace( array_keys( $map ), array_values( $map ), $html );

		return is_string( $s ) ? $s : $html;
	}

	/**
	 * Wycina wszystko poza dozwolona siodemka. strip_tags() zostawia tresc
	 * w srodku, wiec tekst z <span> czy <a> nie ginie - znika sam znacznik.
	 */
	private static function strip_disallowed( string $html ): string {
		$allow = '<' . implode( '><', self::ALLOWED ) . '>';

		return strip_tags( $html, $allow );
	}

	/**
	 * strip_tags() zostawia atrybuty na dozwolonych znacznikach
	 * (<p class="x"> przechodzi), a Allegro odrzuca wszystko poza golym tagiem.
	 */
	private static function strip_attributes( string $html ): string {
		$tags = implode( '|', self::ALLOWED );

		$s = preg_replace( "#<\s*({$tags})(\s[^>]*)?>#i", '<$1>', $html );
		$s = preg_replace( "#<\s*/\s*({$tags})\s*>#i", '</$1>', $s ?? $html );

		// Allegro wymaga malych liter w znacznikach.
		return preg_replace_callback(
			"#</?({$tags})>#i",
			static fn( array $m ): string => strtolower( $m[0] ),
			$s ?? $html
		) ?? $html;
	}

	/**
	 * "Nie mozesz dodatkowo formatowac tagow h1 i h2" - <b> w naglowku
	 * to blad walidacji po stronie Allegro, wiec zdejmujemy go tutaj.
	 */
	private static function unformat_headings( string $html ): string {
		return preg_replace_callback(
			'#<(h1|h2)>(.*?)</\1>#is',
			static function ( array $m ): string {
				$inner = strip_tags( $m[2] );
				$inner = preg_replace( '/\s+/u', ' ', $inner ) ?? $inner;

				return '<' . $m[1] . '>' . trim( $inner ) . '</' . $m[1] . '>';
			},
			$html
		) ?? $html;
	}

	/**
	 * Kontakt i linki poza Allegro. Wzorce sa celowo waskie, bo katalog felg
	 * jest pelen ciagow, ktore szeroki regex telefoniczny zjadlby jako numer
	 * (np. "225 45 17" to rozmiar opony, nie telefon).
	 */
	public static function strip_contacts( string $s ): string {
		$patterns = [
			// Pelne adresy URL.
			'#\bhttps?://\S+#i',
			'#\bwww\.[a-z0-9-]+(\.[a-z]{2,})+\S*#i',
			// Gole domeny w popularnych TLD (dawmac.pl, sklep.com.pl).
			'#\b[a-z0-9][a-z0-9-]*(?:\.[a-z0-9-]+)*\.(?:pl|com|eu|net|de|org|shop|store)\b#i',
			// E-mail.
			'#\b[\w.+-]+@[\w-]+(\.[\w-]+)+\b#i',
			// Telefon: tylko formy jednoznacznie telefoniczne.
			'#\+48[\s-]?\d{3}[\s-]?\d{3}[\s-]?\d{3}\b#',
			'#\b\d{3}[\s-]\d{3}[\s-]\d{3}\b#',
			'#\b(?:tel|tel\.|telefon|kom|kom\.)\s*:?\s*[\d\s-]{9,}#iu',
		];

		$out = preg_replace( $patterns, '', $s );
		$out = is_string( $out ) ? $out : $s;

		// Po wycieciu zostaja osierocone spacje i interpunkcja.
		$out = preg_replace( '/\s{2,}/u', ' ', $out ) ?? $out;
		$out = preg_replace( '/\s+([,.;:!?])/u', '$1', $out ) ?? $out;

		return $out;
	}

	/**
	 * Goly & lamie parsowanie po stronie Allegro. Encje juz poprawne zostawiamy.
	 */
	private static function escape_stray_entities( string $s ): string {
		return preg_replace( '/&(?!(?:[a-z][a-z0-9]{1,10}|#\d{1,6}|#x[0-9a-f]{1,5});)/i', '&amp;', $s ) ?? $s;
	}

	/**
	 * Puste <p></p> i <li></li> zostaja po zamianie <br> oraz po wycieciu
	 * kontaktow, ktore byly jedyna trescia akapitu.
	 */
	private static function drop_empty_blocks( string $s ): string {
		$before = '';
		$guard  = 0;

		// Petla, bo skasowanie <li> moze osierocic <ul>.
		while ( $before !== $s && $guard < 10 ) {
			$before = $s;
			$s      = preg_replace( '#<(p|li|h1|h2|b)>(?:\s|&nbsp;)*</\1>#i', '', $s ) ?? $s;
			$s      = preg_replace( '#<(ul|ol)>\s*</\1>#i', '', $s ) ?? $s;
			++$guard;
		}

		return $s;
	}

	/**
	 * Tekst luzem, poza znacznikiem blokowym, bywa odrzucany. Jesli po
	 * czyszczeniu zostal sam goly napis - pakujemy go w akapit.
	 */
	private static function ensure_block_wrapped( string $s ): string {
		$s = trim( $s );

		if ( '' === $s ) {
			return '';
		}

		$blocks = implode( '|', self::BLOCKS );

		if ( preg_match( "#^<(?:{$blocks})>#i", $s ) ) {
			return $s;
		}

		return '<p>' . $s . '</p>';
	}

	/**
	 * Dlugosc widocznego tekstu - do pilnowania limitu opisu.
	 */
	public static function length( string $html ): int {
		return mb_strlen( self::plain( $html ), 'UTF-8' );
	}

	/**
	 * Skraca po granicy slowa i domyka wielokropkiem.
	 */
	public static function truncate( string $text, int $max ): string {
		if ( mb_strlen( $text, 'UTF-8' ) <= $max ) {
			return $text;
		}

		$cut   = mb_substr( $text, 0, $max - 1, 'UTF-8' );
		$space = mb_strrpos( $cut, ' ', 0, 'UTF-8' );

		if ( false !== $space && $space > (int) ( $max * 0.6 ) ) {
			$cut = mb_substr( $cut, 0, $space, 'UTF-8' );
		}

		return rtrim( $cut, " \t\n\r\0\x0B,.;:-" ) . '…';
	}
}
