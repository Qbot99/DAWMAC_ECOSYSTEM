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
		'producent'      => 'Producent',
		'model'          => 'Model',
		'srednica'       => 'Srednica',
		'szerokosc'      => 'Szerokosc',
		'rozstaw'        => 'Rozstaw srub',
		'liczba_srub'    => 'Liczba srub',
		'et'             => 'Odsadzenie ET',
		'kolor'          => 'Kolor',
		'sku'            => 'Indeks',
	];

	const SPEC_LABELS_TYRES = [
		'producent'       => 'Producent',
		'model'           => 'Model',
		'szerokosc_opony' => 'Szerokosc',
		'profil'          => 'Profil',
		'srednica_opony'  => 'Srednica',
		'sku'             => 'Indeks',
	];

	/** Jednostki doklejane do wartosci liczbowych. */
	const SPEC_UNITS = [
		'srednica'       => ' cali',
		'szerokosc'      => ' cala',
		'srednica_opony' => ' cali',
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
			'about'                       => $this->about_section(),
			'shipping'                    => $this->shipping_section(),
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

			$rows[ $label ] = $value . ( self::SPEC_UNITS[ $field ] ?? '' );
		}

		return $rows;
	}

	/** Staly blok tekstowy z konfiguracji, jedna kolumna. */
	private function text_block_section( string $name ): ?array {
		$content = $this->block_html( $name );

		return $content ? [ 'items' => [ [ 'type' => 'TEXT', 'content' => $content ] ] ] : null;
	}

	/** "Dlaczego DAWMAC" + grafika zaufania obok. */
	private function about_section(): ?array {
		$content = $this->block_html( 'about' );

		if ( ! $content ) {
			return null;
		}

		$items = [ [ 'type' => 'TEXT', 'content' => $content ] ];
		$key   = $this->config['blocks']['about']['image'] ?? null;
		$url   = $key ? ( $this->resolve_image )( $key ) : null;

		if ( $url ) {
			$items[] = [ 'type' => 'IMAGE', 'url' => $url ];
		}

		return [ 'items' => $items ];
	}

	/** Wysylka i gwarancja obok siebie - dwa bloki tekstowe w jednej sekcji. */
	private function shipping_section(): ?array {
		$items = [];

		foreach ( [ 'shipping', 'warranty' ] as $name ) {
			$content = $this->block_html( $name );

			if ( $content ) {
				$items[] = [ 'type' => 'TEXT', 'content' => $content ];
			}
		}

		return $items ? [ 'items' => $items ] : null;
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
		$parts = array_filter( [
			$product['producent'] ?? '',
			$product['model'] ?? '',
			$this->size_label( $product ),
			$this->rozstaw_label( $product ),
			isset( $product['et'] ) && '' !== (string) $product['et'] ? 'ET' . $product['et'] : '',
		] );

		$title = implode( ' ', array_map( 'strval', $parts ) );
		$title = preg_replace( '/[^\p{L}\p{N}\s.,\-\/x]/u', '', $title ) ?? $title;
		$title = preg_replace( '/\s+/u', ' ', $title ) ?? $title;

		$limit = (int) ( $this->config['limits']['title_chars'] ?? 75 );

		return trim( mb_substr( trim( $title ), 0, $limit, 'UTF-8' ) );
	}

	/** "8.5x19" - rozmiar w formie, ktora kupujacy wpisuje w wyszukiwarke. */
	private function size_label( array $product ): string {
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

				if ( preg_match( "#<(h1|h2)>[^<]*<#i", $content ) ) {
					$errors[] = "Formatowanie w srodku h1/h2 ({$where}) - Allegro tego nie przyjmie.";
				}

				if ( preg_match( '#https?://|www\.|@[a-z0-9-]+\.[a-z]{2,}#i', $content ) ) {
					$errors[] = "Link lub adres e-mail w tresci ({$where}) - oferta zostanie odrzucona.";
				}
			}
		}

		if ( $chars > 50000 ) {
			$errors[] = sprintf( 'Opis ma %d znakow - limit Allegro to 50 000.', $chars );
		}

		return $errors;
	}
}
