<?php
/**
 * Szablon firmowy: znormalizowane dane produktu -> struktura opisu Allegro.
 *
 * Allegro nie przyjmuje HTML-a ze sklepu. Opis to drzewo:
 *
 *   description.sections[]        - max 100 sekcji
 *     .items[]                    - 1 item (pelna szerokosc) albo 2 (dwie kolumny)
 *       { type: TEXT,  content }  - tylko h1, h2, p, ul, ol, li, b
 *       { type: IMAGE, url }      - URL wylacznie z serwerow Allegro
 *
 * Klasa jest czystym PHP i nie dotyka WordPressa - dane produktu dostaje
 * gotowe z class-product-data.php, a URL-e obrazkow przez callable.
 * Dzieki temu tools/preview.php renderuje szablon bez stawiania sklepu.
 */

if ( defined( 'ABSPATH' ) && ! defined( 'DAWMAC_ALLEGRO_VERSION' ) ) {
	exit;
}

require_once __DIR__ . '/class-text.php';

class Dawmac_Allegro_Template {

	/** Etykiety parametrow w sekcji "Parametry" - kolejnosc ma znaczenie. */
	const SPEC_LABELS = [
		'producent'   => 'Producent',
		'model'       => 'Model',
		'srednica'    => 'Średnica',
		'szerokosc'   => 'Szerokość',
		'rozstaw'     => 'Rozstaw śrub',
		'liczba_srub' => 'Liczba śrub',
		'et'          => 'Odsadzenie ET',
		// Wykonczenie, nie kolor: sklep trzyma w pa_kategoria-koloru polke
		// katalogu ("Brazowe i zlote"), a kupujacego interesuje "Brushed Bronze".
		'wykonczenie' => 'Wykończenie',
	];

	const SPEC_LABELS_TYRES = [
		'producent'       => 'Producent',
		'model'           => 'Model',
		'szerokosc_opony' => 'Szerokość',
		'profil'          => 'Profil',
		'srednica_opony'  => 'Średnica',
	];

	/**
	 * Pola wymiarowe zapisujemy w calach z polskim przecinkiem: 19", 8,5".
	 *
	 * Doklejanie jednostki do surowej wartosci ze sklepu dawalo bzdury
	 * w rodzaju '19" cali', bo sklep trzyma srednice juz z cudzyslowem.
	 */
	const SPEC_CALE = [ 'srednica', 'szerokosc', 'srednica_opony' ];

	/**
	 * Tematy, ktorych opis oferty poruszac nie moze - naleza do zakladek
	 * Dostawa i platnosc oraz Zwroty. Wzorce sa waskie, zeby nie lapac
	 * zwyklych slow: "dostawa" tak, ale "dostepny" juz nie.
	 */
	const ZAKAZANE = [
		'Wysyłka i dostawa' => '/\b(wysy[łl]k\w*|wysy[łl]amy|nadajemy|dostaw\w*|przesy[łl]k\w*|kurier\w*|paczkomat\w*|paczk\w*)\b/iu',
		'Płatności'         => '/\b(p[łl]atno[śs]\w*|przelew\w*|za pobraniem|faktur\w*|raty)\b/iu',
		'Zwroty i gwarancja'=> '/\b(zwrot\w*|zwraca\w*|reklamacj\w*|gwarancj\w*|r[ęe]kojmi\w*|odst[ąa]pieni\w*)\b/iu',
	];

	private array $config;

	/** @var callable(string):?string Klucz grafiki -> URL po stronie Allegro. */
	private $resolve_image;

	/**
	 * @param array         $config        Tablica z config/brand.php.
	 * @param callable|null $resolve_image Zwraca URL dla klucza grafiki albo null,
	 *                                     gdy grafiki jeszcze nie wgrano - sekcja
	 *                                     jest wtedy pomijana zamiast psuc oferte.
	 */
	public function __construct( array $config, ?callable $resolve_image = null ) {
		$this->config        = $config;
		$this->resolve_image = $resolve_image ?? static fn( string $key ): ?string => null;
	}

	/**
	 * Buduje kompletny obiekt description dla POST /sale/product-offers.
	 *
	 * @param array $product Dane z Dawmac_Allegro_Product_Data.
	 * @return array{sections: array<int, array{items: array}>}
	 */
	public function build( array $product ): array {
		$sections = [];

		foreach ( $this->config['layout'] as $key ) {
			$section = $this->section( $key, $product );

			if ( null !== $section ) {
				$sections[] = $section;
			}
		}

		$sections = $this->enforce_limits( $sections );

		return [ 'sections' => $sections ];
	}

	/**
	 * Pojedyncza sekcja. null = pomijamy (brak grafiki, brak danych).
	 */
	private function section( string $key, array $product ): ?array {
		return match ( $key ) {
			'banner_top', 'banner_bottom' => $this->image_section( $key ),
			'headline'                    => $this->headline_section( $product ),
			'spec'                        => $this->spec_section( $product ),
			'fitment'                     => $this->text_block_section( 'fitment' ),
			'about'                       => $this->about_section( $product ),
			'zdjecia'                     => $this->photos_section( $product ),
			'shipping', 'warranty'        => $this->text_block_section( $key ),
			default                       => null,
		};
	}

	/** Baner na pelna szerokosc. */
	private function image_section( string $key ): ?array {
		$url = ( $this->resolve_image )( $key );

		if ( ! $url ) {
			return null;
		}

		return [ 'items' => [ [ 'type' => 'IMAGE', 'url' => $url ] ] ];
	}

	/**
	 * Naglowek: h1 z nazwa produktu + skrocony opis ze sklepu.
	 * Opis z Woo bywa dluga sciana tekstu z kontaktem w stopce, wiec
	 * przechodzi przez sanitizer i limit znakow z konfiguracji.
	 */
	private function headline_section( array $product ): ?array {
		$title = Dawmac_Allegro_Text::plain( (string) ( $product['title'] ?? '' ) );

		if ( '' === $title ) {
			return null;
		}

		$html = '<h1>' . $this->esc( $title ) . '</h1>';

		$intro = Dawmac_Allegro_Text::plain( (string) ( $product['opis'] ?? '' ) );

		// Opisy w rodzaju "Na magazynie" to notatka sklepowa, nie tresc
		// dla kupujacego - pusty akapit wyglada lepiej niz taki.
		if ( mb_strlen( $intro, 'UTF-8' ) < 40 ) {
			$intro = '';
		}

		if ( '' !== $intro ) {
			$limit = (int) ( $this->config['limits']['intro_chars'] ?? 900 );
			$html .= '<p>' . $this->esc( Dawmac_Allegro_Text::truncate( $intro, $limit ) ) . '</p>';
		}

		return [ 'items' => [ [ 'type' => 'TEXT', 'content' => Dawmac_Allegro_Text::clean( $html ) ] ] ];
	}

	/**
	 * Parametry + zdjecie produktu w dwoch kolumnach. Bez zdjecia sekcja
	 * zostaje jednokolumnowa - Allegro nie akceptuje itemu IMAGE bez URL-a.
	 */
	private function spec_section( array $product ): ?array {
		$rows = $this->spec_rows( $product );

		if ( ! $rows ) {
			return null;
		}

		$html = '<h2>Parametry</h2><ul>';

		foreach ( $rows as $label => $value ) {
			$html .= '<li><b>' . $this->esc( $label ) . ':</b> ' . $this->esc( $value ) . '</li>';
		}

		$html .= '</ul>';

		$items = [ [ 'type' => 'TEXT', 'content' => Dawmac_Allegro_Text::clean( $html ) ] ];
		$photo = $product['image'] ?? null;

		if ( is_string( $photo ) && '' !== $photo ) {
			$items[] = [ 'type' => 'IMAGE', 'url' => $photo ];
		}

		return [ 'items' => $items ];
	}

	/**
	 * Pary etykieta => wartosc. Puste pomijamy, zeby w opisie nie zostawaly
	 * wiersze typu "Kolor: -".
	 */
	private function spec_rows( array $product ): array {
		$labels = ( 'opony' === ( $product['kategoria'] ?? 'felgi' ) )
			? self::SPEC_LABELS_TYRES
			: self::SPEC_LABELS;

		$rows = [];

		foreach ( $labels as $field => $label ) {
			$value = $product[ $field ] ?? null;

			if ( is_array( $value ) ) {
				$value = implode( ' / ', array_filter( array_map( 'strval', $value ) ) );
			}

			$value = trim( (string) $value );

			if ( '' === $value || '-' === $value ) {
				continue;
			}

			$rows[ $label ] = in_array( $field, self::SPEC_CALE, true )
				? self::cale( $value )
				: self::ladnie( $value );
		}

		return $rows;
	}

	/**
	 * Wymiar w calach po polsku: '8.5J' -> '8,5"', '19"' -> '19"'.
	 * Kilka wartosci (zestaw schodkowy) laczymy plusem: '8,5" + 10"'.
	 */
	private static function cale( string $value ): string {
		$out = [];

		foreach ( preg_split( '#\s*[/+]\s*#', $value ) ?: [ $value ] as $part ) {
			$liczba = str_replace( ',', '.', preg_replace( '/[^0-9.,]/', '', $part ) ?? '' );

			if ( '' === $liczba ) {
				continue;
			}

			$out[] = str_replace( '.', ',', rtrim( rtrim( $liczba, '0' ), '.' ) ) . '"';
		}

		return $out ? implode( ' + ', $out ) : $value;
	}

	/**
	 * Wartosc ze sklepu na tekst dla kupujacego. Sklep trzyma kategorie
	 * w liczbie mnogiej ("Brazowe i zlote") - na ofercie ma stac kolor,
	 * a nie nazwa polki w katalogu.
	 */
	private static function ladnie( string $value ): string {
		$mapa = [
			'białe'           => 'Biały',
			'czarne'          => 'Czarny',
			'srebrne'         => 'Srebrny',
			'grafitowe'       => 'Grafitowy',
			'szare'           => 'Szary',
			'brązowe i złote' => 'Brązowy lub złoty',
			'brązowe'         => 'Brązowy',
			'złote'           => 'Złoty',
			'niebieskie'      => 'Niebieski',
			'zielone'         => 'Zielony',
			'czerwone'        => 'Czerwony',
		];

		return $mapa[ mb_strtolower( trim( $value ), 'UTF-8' ) ] ?? $value;
	}

	/** Staly blok tekstowy z konfiguracji, jedna kolumna. */
	private function text_block_section( string $name ): ?array {
		$content = $this->block_html( $name );

		return $content ? [ 'items' => [ [ 'type' => 'TEXT', 'content' => $content ] ] ] : null;
	}

	/**
	 * "Dlaczego DAWMAC" + obrazek obok. Klucz 'produkt' oznacza kolejne
	 * zdjecie produktu zamiast grafiki firmowej.
	 */
	private function about_section( array $product ): ?array {
		$content = $this->block_html( 'about' );

		if ( ! $content ) {
			return null;
		}

		$items = [ [ 'type' => 'TEXT', 'content' => $content ] ];
		$key   = $this->config['blocks']['about']['image'] ?? null;

		$url = ( 'produkt' === $key )
			? $this->photo( $product, 1 )
			: ( $key ? ( $this->resolve_image )( $key ) : null );

		if ( $url ) {
			$items[] = [ 'type' => 'IMAGE', 'url' => $url ];
		}

		return [ 'items' => $items ];
	}

	/**
	 * Dwa kolejne zdjecia produktu obok siebie. Sekcja powstaje tylko wtedy,
	 * gdy sa oba - pojedyncze zdjecie na pol szerokosci wyglada jak pomylka.
	 */
	private function photos_section( array $product ): ?array {
		$a = $this->photo( $product, 2 );
		$b = $this->photo( $product, 3 );

		if ( ! $a || ! $b ) {
			return null;
		}

		return [ 'items' => [
			[ 'type' => 'IMAGE', 'url' => $a ],
			[ 'type' => 'IMAGE', 'url' => $b ],
		] ];
	}

	/**
	 * N-te zdjecie produktu po stronie Allegro. Zdjecie 0 siedzi w sekcji
	 * parametrow, wiec kolejne sekcje siegaja po dalsze i sie nie powtarzaja.
	 */
	private function photo( array $product, int $n ): ?string {
		$lista = $product['zdjecia'] ?? [];

		return is_array( $lista ) && isset( $lista[ $n ] ) ? (string) $lista[ $n ] : null;
	}

	/** Naglowek h2 + tresc bloku, po sanitacji. */
	private function block_html( string $name ): ?string {
		$block = $this->config['blocks'][ $name ] ?? null;

		if ( ! is_array( $block ) ) {
			return null;
		}

		$html = '';

		if ( ! empty( $block['title'] ) ) {
			$html .= '<h2>' . $this->esc( Dawmac_Allegro_Text::plain( $block['title'] ) ) . '</h2>';
		}

		$html .= (string) ( $block['html'] ?? '' );
		$clean = Dawmac_Allegro_Text::clean( $html );

		return '' !== $clean ? $clean : null;
	}

	/**
	 * Twarde limity Allegro: max 100 sekcji, max 2 itemy w sekcji,
	 * plus wlasny sufit na dlugosc calego opisu.
	 */
	private function enforce_limits( array $sections ): array {
		$max_sections = (int) ( $this->config['limits']['sections'] ?? 100 );
		$max_chars    = (int) ( $this->config['limits']['description_chars'] ?? 45000 );

		$sections = array_slice( $sections, 0, $max_sections );

		$out   = [];
		$chars = 0;

		foreach ( $sections as $section ) {
			$section['items'] = array_slice( $section['items'], 0, 2 );

			foreach ( $section['items'] as $item ) {
				if ( 'TEXT' === $item['type'] ) {
					$chars += mb_strlen( $item['content'], 'UTF-8' );
				}
			}

			if ( $chars > $max_chars ) {
				break;
			}

			$out[] = $section;
		}

		return $out;
	}

	/**
	 * Tytul oferty (max 75 znakow). Allegro odrzuca znaki ozdobne
	 * i ciagi wielkich liter, wiec zostawiamy waski zestaw.
	 */
	public function build_offer_title( array $product ): string {
		$tyres = 'opony' === ( $product['kategoria'] ?? 'felgi' );

		$parts = array_filter( [
			$product['producent'] ?? '',
			$product['model'] ?? '',
			$this->size_label( $product ),
			$tyres ? '' : $this->rozstaw_label( $product ),
			! $tyres && isset( $product['et'] ) && '' !== (string) $product['et'] ? 'ET' . $product['et'] : '',
		] );

		$title = implode( ' ', array_map( 'strval', $parts ) );
		$title = preg_replace( '/[^\p{L}\p{N}\s.,\-\/x]/u', '', $title ) ?? $title;
		$title = preg_replace( '/\s+/u', ' ', $title ) ?? $title;

		$limit = (int) ( $this->config['limits']['title_chars'] ?? 75 );

		return trim( mb_substr( trim( $title ), 0, $limit, 'UTF-8' ) );
	}

	/**
	 * Rozmiar w formie, ktora kupujacy wpisuje w wyszukiwarke Allegro:
	 * "8.5x19" dla felgi, "225/45 R17" dla opony.
	 */
	private function size_label( array $product ): string {
		if ( 'opony' === ( $product['kategoria'] ?? 'felgi' ) ) {
			$w = trim( (string) ( $product['szerokosc_opony'] ?? '' ) );
			$p = trim( (string) ( $product['profil'] ?? '' ) );
			$d = trim( (string) ( $product['srednica_opony'] ?? '' ) );

			if ( '' === $w || '' === $d ) {
				return '';
			}

			return '' !== $p ? "{$w}/{$p} R{$d}" : "{$w} R{$d}";
		}

		$w = trim( (string) ( $product['szerokosc'] ?? '' ) );
		$d = trim( (string) ( $product['srednica'] ?? '' ) );

		if ( '' === $w || '' === $d ) {
			return $d !== '' ? $d . '"' : '';
		}

		return $w . 'x' . $d;
	}

	/** Felga moze pasowac do dwoch rozstawow - w tytule laczymy ukosnikiem. */
	private function rozstaw_label( array $product ): string {
		$r = $product['rozstaw'] ?? '';

		if ( is_array( $r ) ) {
			$r = implode( '/', array_filter( array_map( 'strval', $r ) ) );
		}

		return trim( (string) $r );
	}

	/** Encje HTML dla tresci wstawianej miedzy znaczniki. */
	private function esc( string $s ): string {
		return htmlspecialchars( $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
	}

	/**
	 * Kontrola przed wyslaniem do API. Zwraca liste problemow - pusta znaczy,
	 * ze struktura spelnia wymagania Allegro.
	 *
	 * @return string[]
	 */
	public static function validate( array $description ): array {
		$errors   = [];
		$sections = $description['sections'] ?? [];

		if ( ! $sections ) {
			return [ 'Opis nie ma zadnej sekcji - Allegro wymaga przynajmniej jednej.' ];
		}

		if ( count( $sections ) > 100 ) {
			$errors[] = sprintf( 'Za duzo sekcji: %d (limit 100).', count( $sections ) );
		}

		$allowed = implode( '|', Dawmac_Allegro_Text::ALLOWED );
		$chars   = 0;

		foreach ( $sections as $i => $section ) {
			$items = $section['items'] ?? [];
			$n     = count( $items );

			if ( $n < 1 || $n > 2 ) {
				$errors[] = sprintf( 'Sekcja %d ma %d itemow - dozwolone 1 albo 2.', $i + 1, $n );
			}

			// Allegro odrzuca sekcje zlozona z DWOCH itemow TEXT
			// ("Uklad sekcji skladajacy sie z dwoch elementow typu TEXT
			// nie jest dozwolony", HTTP 422). Dwie kolumny musza zawierac
			// co najmniej jeden obrazek.
			if ( 2 === $n
				&& 'TEXT' === ( $items[0]['type'] ?? '' )
				&& 'TEXT' === ( $items[1]['type'] ?? '' ) ) {
				$errors[] = sprintf( 'Sekcja %d to dwa bloki TEXT obok siebie - Allegro tego nie przyjmie.', $i + 1 );
			}

			foreach ( $items as $j => $item ) {
				$where = sprintf( 'sekcja %d, item %d', $i + 1, $j + 1 );

				if ( 'IMAGE' === ( $item['type'] ?? '' ) ) {
					if ( empty( $item['url'] ) ) {
						$errors[] = "IMAGE bez URL-a ({$where}).";
					} elseif ( ! preg_match( '#^https://[a-z0-9.-]*allegroimg\.com/#i', (string) $item['url'] ) ) {
						$errors[] = "Obrazek spoza serwerow Allegro ({$where}) - wgraj go przez POST /sale/images.";
					}
					continue;
				}

				if ( 'TEXT' !== ( $item['type'] ?? '' ) ) {
					$errors[] = sprintf( 'Nieznany typ itemu "%s" (%s).', (string) ( $item['type'] ?? '' ), $where );
					continue;
				}

				$content = (string) ( $item['content'] ?? '' );
				$chars  += mb_strlen( $content, 'UTF-8' );

				if ( preg_match_all( "#</?([a-z0-9]+)#i", $content, $m ) ) {
					foreach ( array_unique( $m[1] ) as $tag ) {
						if ( ! in_array( strtolower( $tag ), Dawmac_Allegro_Text::ALLOWED, true ) ) {
							$errors[] = sprintf( 'Niedozwolony znacznik <%s> (%s).', $tag, $where );
						}
					}
				}

				// Sprawdzamy TRESC naglowka, nie sam ciag - wzorzec typu
				// "<h1>[^<]*<" lapie wlasny tag zamykajacy i krzyczy zawsze.
				if ( preg_match_all( '#<(h1|h2)>(.*?)</\\1>#is', $content, $h ) ) {
					foreach ( $h[2] as $inner ) {
						if ( str_contains( $inner, '<' ) ) {
							$errors[] = "Formatowanie w srodku h1/h2 ({$where}) - Allegro tego nie przyjmie.";
							break;
						}
					}
				}

				if ( preg_match( '#https?://|www\.|@[a-z0-9-]+\.[a-z]{2,}#i', $content ) ) {
					$errors[] = "Link lub adres e-mail w tresci ({$where}) - oferta zostanie odrzucona.";
				}

				// Tresci zastrzezone dla zakladek oferty. Allegro odrzucilo
				// oferte 18890951777 wlasnie za to: "Usun z opisu przedmiotu
				// dane dotyczace wysylki, dostawy, platnosci" oraz "W opisie
				// oferty umieszczasz informacje dotyczace zwrotu towaru".
				// Kara idzie do zawieszenia konta, wiec blokujemy u siebie.
				foreach ( self::ZAKAZANE as $etykieta => $wzorzec ) {
					if ( preg_match( $wzorzec, $content ) ) {
						$errors[] = sprintf(
							'%s w opisie (%s) - te informacje moga byc tylko w zakladkach oferty.',
							$etykieta,
							$where
						);
					}
				}
			}
		}

		if ( $chars > 50000 ) {
			$errors[] = sprintf( 'Opis ma %d znakow - limit Allegro to 50 000.', $chars );
		}

		return $errors;
	}
}
