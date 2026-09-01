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

	/** Znacznik miejsca po wycietym namiarze - znak spoza tresci opisow. */
	const MARK = "\x00";

	/** Laczniki, ktore po wycieciu namiaru nie niosą juz nic. */
	const CONNECTORS = '/\b(?:lokalizacja\s+magazynowa|kontakt\w*|zapraszamy(?:\s+na|\s+do)?|więcej\s+(?:na|informacji)|sprawdź(?:\s+na)?|odwiedź|nasz[aey]?\s+(?:stron\w*|sklep\w*)|tel|telefon|kom|e-?mail|mail|nr)\b\s*[:.\-]?\s*/iu';

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
		$s = self::collapse_whitespace( $s );
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
	 * Kontakt i linki poza Allegro.
	 *
	 * Usuwamy CALE ZDANIE, nie sam token. Wyciecie samego adresu zostawia
	 * kikuty w rodzaju "Zapraszamy na ." albo "Kontakt: , tel." - zdanie,
	 * ktore nioslo tylko namiar, po wycieciu namiaru nie niesie juz nic.
	 *
	 * Pracujemy na segmentach tekstu miedzy znacznikami, zeby usuwanie
	 * nie rozjechalo struktury HTML.
	 */
	public static function strip_contacts( string $s ): string {
		$parts = preg_split( '#(<[^>]*>)#', $s, -1, PREG_SPLIT_DELIM_CAPTURE );

		if ( ! is_array( $parts ) ) {
			return self::strip_contact_tokens( $s );
		}

		foreach ( $parts as $i => $part ) {
			if ( '' === $part || '<' === $part[0] ) {
				continue;
			}

			$parts[ $i ] = self::clean_segment( $part );
		}

		return implode( '', $parts );
	}

	/**
	 * Kolejnosc: najpierw MASKUJEMY namiary, potem tniemy na zdania.
	 *
	 * Odwrotnie sie nie da - kropki siedza w srodku "dawmac.pl" i "tel.",
	 * wiec podzial na zdania przed wycieciem rozjezdza sie na nich i zabiera
	 * ze soba sasiedni tekst ("601 234 567 Lokalizacja magazynowa 42.L/NHB"
	 * ladowalo w jednym zdaniu z numerem i znikalo w calosci).
	 *
	 * Po zamaskowaniu zdanie zostaje, jesli poza namiarem cokolwiek niesie.
	 * "Zapraszamy na [x]" to sam lacznik - wypada. "[x] Lokalizacja
	 * magazynowa" niesie tresc - zostaje bez wycietego fragmentu.
	 */
	private static function clean_segment( string $text ): string {
		if ( ! self::has_contact( $text ) ) {
			return $text;
		}

		$masked = preg_replace( self::contact_patterns(), self::MARK, $text );

		if ( ! is_string( $masked ) ) {
			return $text;
		}

		$sentences = preg_split( '/(?<=[.!?])\s+/u', $masked, -1, PREG_SPLIT_NO_EMPTY );

		if ( ! is_array( $sentences ) ) {
			return self::tidy( str_replace( self::MARK, ' ', $masked ) );
		}

		$kept = [];

		foreach ( $sentences as $sentence ) {
			if ( ! str_contains( $sentence, self::MARK ) ) {
				$kept[] = $sentence;
				continue;
			}

			$rest = self::tidy( str_replace( self::MARK, ' ', $sentence ) );
			$rest = self::tidy( preg_replace( self::CONNECTORS, '', $rest ) ?? $rest );

			// Co realnie zostalo poza namiarem i lacznikami.
			$meat = preg_replace( '/[^\p{L}\p{N}]+/u', '', $rest ) ?? $rest;

			if ( mb_strlen( $meat, 'UTF-8' ) >= 12 ) {
				$kept[] = $rest;
			}
		}

		$out = self::tidy( implode( ' ', $kept ) );

		// Spacja z powrotem na brzegach, zeby sasiednie segmenty sie nie zlepily.
		$lead  = preg_match( '/^\s/u', $text ) ? ' ' : '';
		$trail = preg_match( '/\s$/u', $text ) ? ' ' : '';

		return '' === $out ? '' : $lead . $out . $trail;
	}

	/** Sprzatanie po wycieciu: osierocone spacje i interpunkcja. */
	private static function tidy( string $s ): string {
		$s = preg_replace( '/\s{2,}/u', ' ', $s ) ?? $s;
		$s = preg_replace( '/\s+([,.;:!?])/u', '$1', $s ) ?? $s;
		$s = preg_replace( '/([,;:])\s*(?=[,.;:])/u', '', $s ) ?? $s;
		$s = preg_replace( '/^[\s,.;:\-]+/u', '', $s ) ?? $s;

		return trim( $s );
	}

	/**
	 * Wzorce sa celowo waskie, bo katalog felg jest pelen ciagow, ktore
	 * szeroki regex telefoniczny zjadlby jako numer ("225 45 17" to rozmiar
	 * opony, nie telefon).
	 *
	 * @return string[]
	 */
	private static function contact_patterns(): array {
		// KOLEJNOSC MA ZNACZENIE. Domena musi byc na koncu: puszczona przed
		// mailem zjada "dawmac.pl" z "biuro@dawmac.pl" i zostawia "biuro@".
		// Telefon z etykieta przed golym numerem z tego samego powodu.
		return [
			'#\bhttps?://\S+#i',                                      // pelny URL
			'#\bwww\.[a-z0-9-]+(\.[a-z]{2,})+\S*#i',                   // www.cos.pl
			'#\b[\w.+-]+@[\w-]+(\.[\w-]+)+#i',                        // e-mail
			'#(?:tel|tel\.|telefon|kom|kom\.|nr)\s*:?\s*\+?[\d\s-]{9,}#iu', // telefon z etykieta
			'#\+48[\s-]?\d{3}[\s-]?\d{3}[\s-]?\d{3}\b#',              // +48 xxx xxx xxx
			'#\b\d{3}[\s-]\d{3}[\s-]\d{3}\b#',                        // xxx xxx xxx
			'#\b[a-z0-9][a-z0-9-]*(?:\.[a-z0-9-]+)*\.(?:pl|com|eu|net|de|org|shop|store)\b#i', // gola domena
			// Lokalizacja magazynowa ("42.L / NHB", "7.P/AB") - dane wewnetrzne.
			// Siedza w opisach, bo szukarka dawmac-filters po nich szuka;
			// na publicznej ofercie nie maja czego szukac.
			'#\b\d{1,3}\.[A-Z]{1,2}\s*/\s*[A-Z]{2,4}\b#',
		];
	}

	/** Czy fragment zawiera cokolwiek, co Allegro uzna za namiar poza serwisem. */
	private static function has_contact( string $s ): bool {
		foreach ( self::contact_patterns() as $pattern ) {
			if ( preg_match( $pattern, $s ) ) {
				return true;
			}
		}

		return false;
	}

	/** Awaryjne wycinanie samych tokenow - gdy usuwanie zdan zabraloby zbyt duzo. */
	private static function strip_contact_tokens( string $s ): string {
		$out = preg_replace( self::contact_patterns(), '', $s );
		$out = is_string( $out ) ? $out : $s;

		// Po wycieciu zostaja osierocone spacje, interpunkcja i etykiety.
		$out = preg_replace( '/\b(?:kontakt|tel|telefon|kom|e-?mail|mail)\s*[:.]?\s*(?=[,.;:]|$)/iu', '', $out ) ?? $out;
		$out = preg_replace( '/\s{2,}/u', ' ', $out ) ?? $out;
		$out = preg_replace( '/\s+([,.;:!?])/u', '$1', $out ) ?? $out;
		$out = preg_replace( '/([,;:])\s*(?=[,.;:])/u', '', $out ) ?? $out;

		return $out;
	}

	/**
	 * Goly & lamie parsowanie po stronie Allegro. Encje juz poprawne zostawiamy.
	 */
	private static function escape_stray_entities( string $s ): string {
		return preg_replace( '/&(?!(?:[a-z][a-z0-9]{1,10}|#\d{1,6}|#x[0-9a-f]{1,5});)/i', '&amp;', $s ) ?? $s;
	}

	/**
	 * Bloki w config/brand.php sa pisane z wcieciami dla czytelnosci -
	 * do Allegro nie ma po co jechac dwadziescia spacji miedzy <ul> a <li>.
	 */
	private static function collapse_whitespace( string $s ): string {
		$s = preg_replace( '/>\s+</u', '><', $s ) ?? $s;
		$s = preg_replace( '/\s{2,}/u', ' ', $s ) ?? $s;

		return trim( $s );
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
