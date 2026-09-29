<?php
/**
 * Produkt WooCommerce -> plaska tablica, ktora rozumie szablon.
 *
 * Warstwa istnieje po to, zeby class-template.php nie wiedzialo nic
 * o WordPressie. Szablon dostaje slownik pol, wiec da sie go odpalic
 * na fixture w tools/preview.php i w testach, bez stawiania sklepu.
 *
 * Mapowanie atrybutow bierzemy ze sklepu dawmac.pl - to te same taksonomie,
 * na ktorych stoi dawmac-filters:
 *
 *   pa_producent, pa_model, pa_srednica, pa_szerokosc,
 *   pa_rozstaw, pa_et, pa_kategoria-koloru        - felgi
 *   pa_producent, pa_model, pa_szerokosc_opony,
 *   pa_profil, pa_srednica_opony                  - opony
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Dawmac_Allegro_Product_Data {

	/** Taksonomia atrybutu -> pole w tablicy wynikowej. */
	const ATTRIBUTE_MAP = [
		'pa_producent'        => 'producent',
		'pa_model'            => 'model',
		'pa_srednica'         => 'srednica',
		'pa_szerokosc'        => 'szerokosc',
		'pa_rozstaw'          => 'rozstaw',
		'pa_et'               => 'et',
		'pa_kategoria-koloru' => 'kolor',
		// pa_kolor trzyma nazwe wykonczenia wprost ("Brushed Bronze"),
		// pa_kategoria-koloru tylko polke w katalogu ("Brazowe i zlote").
		'pa_kolor'            => 'kolor_nazwa',
		'pa_bore'             => 'bore',
		'pa_szerokosc_opony'  => 'szerokosc_opony',
		'pa_profil'           => 'profil',
		'pa_srednica_opony'   => 'srednica_opony',
	];

	/**
	 * Pola, w ktorych felga moze miec wiecej niz jedna wartosc.
	 *
	 * Rozstaw - felga wiercona pod dwa rozstawy (5x112/5x120).
	 * Szerokosc i ET - zestaw mieszany ma inny przod i tyl (8.5J + 10J).
	 *
	 * Zwijanie ich do pierwszej wartosci jest grozne: zestaw mieszany
	 * wygladalby wtedy na jednorodny i podpielibysmy pod oferte 4 felgi
	 * przednie zamiast 2+2. Kupujacy dostalby co innego, niz zamowil.
	 */
	const MULTI_VALUE = [ 'rozstaw', 'szerokosc', 'et' ];

	/** Slug kategorii opon - ten sam, ktorego uzywa dawmac-filters. */
	const TYRES_CAT = 'opony';

	/**
	 * @param WC_Product $product
	 * @return array Dane gotowe dla Dawmac_Allegro_Template.
	 */
	public static function from_wc( $product ): array {
		$id = $product->get_id();

		$data = [
			'id'        => $id,
			'sku'       => $product->get_sku(),
			'title'     => $product->get_name(),
			'opis'      => self::description( $product ),
			'cena'      => self::price( $product ),
			'stan'      => $product->get_stock_quantity(),
			'dostepny'  => $product->is_in_stock(),
			'kategoria' => self::category( $id ),
			'image'     => self::main_image_url( $product ),
			'gallery'   => self::gallery_urls( $product ),
		];

		foreach ( self::ATTRIBUTE_MAP as $taxonomy => $field ) {
			$values = self::terms( $id, $taxonomy );

			if ( ! $values ) {
				continue;
			}

			$data[ $field ] = in_array( $field, self::MULTI_VALUE, true )
				? $values
				: $values[0];
		}

		$data['liczba_srub'] = self::bolt_count( $data['rozstaw'] ?? null );
		$data['model']       = self::model( $data );
		$data['wykonczenie'] = self::finish( $data );
		$data['adnotacje']   = self::adnotacje( $product );
		$data['bore']        = self::bore( $data['bore'] ?? null );

		// Otwor centralny: gdy nie ma atrybutu, szukamy w opisie ("bore 73,1").
		if ( '' === $data['bore'] ) {
			$data['bore'] = self::bore_z_opisu( $product );
		}

		// Watpliwe wykonczenie POMIJAMY zamiast blokowac produkt. Tytul i tak
		// podaje kolor, a wolimy nie podac informacji niz podac zla.
		// Zrodlowa wartosc zostaje w 'wykonczenie_sklep' - do przegladu.
		$data['wykonczenie_sklep'] = $data['wykonczenie'];

		if ( ! self::wykonczenie_do_oferty( $data ) ) {
			$data['wykonczenie'] = '';
		}

		return $data;
	}

	/**
	 * Wartosci atrybutu. Bierzemy nazwy termow, nie slugi - w opisie ma byc
	 * "5x112", a nie "5x112-2" po deduplikacji WordPressa.
	 *
	 * @return string[]
	 */
	private static function terms( int $product_id, string $taxonomy ): array {
		if ( ! taxonomy_exists( $taxonomy ) ) {
			return [];
		}

		$terms = wp_get_post_terms( $product_id, $taxonomy, [ 'fields' => 'names' ] );

		if ( is_wp_error( $terms ) || ! $terms ) {
			return [];
		}

		return array_values( array_filter( array_map( 'trim', $terms ) ) );
	}

	/**
	 * Opis krotki ma pierwszenstwo - jest pisany jako zajawka, wiec lepiej
	 * nadaje sie na naglowek oferty niz pelna sciana tekstu z lokalizacja
	 * magazynowa w srodku.
	 */
	private static function description( $product ): string {
		$short = trim( (string) $product->get_short_description() );

		return '' !== $short ? $short : (string) $product->get_description();
	}

	/** Cena brutto jako string z kropka - tego oczekuje API Allegro. */
	private static function price( $product ): string {
		$price = $product->get_price();

		return '' === $price || null === $price
			? ''
			: number_format( (float) $price, 2, '.', '' );
	}

	/** 'opony' albo 'felgi' - decyduje, ktory zestaw parametrow pokazujemy. */
	private static function category( int $product_id ): string {
		$slugs = wp_get_post_terms( $product_id, 'product_cat', [ 'fields' => 'slugs' ] );

		if ( is_wp_error( $slugs ) ) {
			return 'felgi';
		}

		return in_array( self::TYRES_CAT, $slugs, true ) ? 'opony' : 'felgi';
	}

	private static function main_image_url( $product ): ?string {
		$id = $product->get_image_id();

		return $id ? ( wp_get_attachment_image_url( $id, 'full' ) ?: null ) : null;
	}

	/**
	 * Zdjecia do galerii oferty. Allegro przyjmuje max 16 zdjec na oferte,
	 * a pierwsze musi byc zdjeciem glownym.
	 *
	 * @return string[]
	 */
	private static function gallery_urls( $product ): array {
		$ids  = array_merge(
			array_filter( [ $product->get_image_id() ] ),
			$product->get_gallery_image_ids()
		);
		$urls = [];

		foreach ( array_unique( $ids ) as $id ) {
			$url = wp_get_attachment_image_url( (int) $id, 'full' );

			if ( $url ) {
				$urls[] = $url;
			}
		}

		return array_slice( $urls, 0, 16 );
	}

	/**
	 * Liczba srub z rozstawu: "5x112" -> 5. Allegro ma to osobnym parametrem
	 * wymaganym w kategorii felg, a sklep nie trzyma go jako atrybutu.
	 * Felga z dwoma rozstawami ma je o tej samej liczbie srub (5x112/5x120),
	 * wiec bierzemy pierwszy.
	 */
	public static function bolt_count( $rozstaw ): ?int {
		if ( is_array( $rozstaw ) ) {
			$rozstaw = $rozstaw[0] ?? '';
		}

		if ( ! preg_match( '/^\s*(\d+)\s*[xX]/', (string) $rozstaw, $m ) ) {
			return null;
		}

		$n = (int) $m[1];

		return ( $n >= 3 && $n <= 8 ) ? $n : null;
	}

	/**
	 * Oznaczenie modelu. Gdy sklep nie ma atrybutu, bierzemy je z tytulu.
	 *
	 * Bez tego wtyczka siegala po SKU, ktore u sprzedawcy bywa pelna nazwa
	 * produktu - a ta trafiala do parametru "Kod producenta" i przekraczala
	 * limit znakow Allegro ("MODEL:1005 19\" 9.5J ET50 5x112 Dark Anthracite
	 * Gloss Polished" to 61 znakow). Model stoi w tytule przed rozmiarem.
	 */
	public static function model( array $data ): string {
		$model = trim( (string) ( $data['model'] ?? '' ) );

		if ( '' !== $model ) {
			return $model;
		}

		$tytul = trim( (string) ( $data['title'] ?? '' ) );

		if ( '' === $tytul ) {
			return '';
		}

		// Producenta z poczatku tytulu odcinamy, potem bierzemy wszystko
		// do pierwszego elementu rozmiaru (17", 8.5J, 5x112, ET35).
		$producent = trim( (string) ( $data['producent'] ?? '' ) );

		if ( '' !== $producent ) {
			$tytul = preg_replace( '/^' . preg_quote( $producent, '/' ) . '\s*/iu', '', $tytul ) ?? $tytul;
		}

		$czesci = preg_split( '/\s+/u', $tytul ) ?: [];
		$out    = [];

		foreach ( $czesci as $slowo ) {
			if ( preg_match( '/^\d{1,2}(?:[.,]\d)?["\x27]|^\d{1,2}(?:[.,]\d)?J|^ET|^\d\s*x\s*\d{2,3}|^\d{2}["\x27]/iu', $slowo ) ) {
				break;
			}

			$out[] = $slowo;

			if ( count( $out ) >= 3 ) {
				break;
			}
		}

		return mb_substr( trim( implode( ' ', $out ) ), 0, 40 );
	}

	/**
	 * Wykonczenie felgi z tytulu produktu: "Brushed Bronze", "Matt Black".
	 *
	 * Atrybut pa_kategoria-koloru trzyma polke w katalogu sklepu ("Brazowe
	 * i zlote"), a nie nazwe wykonczenia - na ofercie wyglada to jak pomylka.
	 * Prawdziwa nazwa siedzi w tytule, wiec zdejmujemy z niego czesc
	 * techniczna, a to, co zostaje, jest wykonczeniem.
	 */
	public static function finish( array $data ): string {
		// pa_kolor jest zrodlem pierwszego wyboru - czesc tytulow w ogole nie
		// niesie wykonczenia ("MODEL:YA001 19\" 8J ET45 5x112") i wtedy atrybut
		// jest jedyna informacja. Rozbieznosci NIE rozstrzygamy tu automatycznie:
		// od tego jest watpliwe_wykonczenie() i lista do weryfikacji.
		$atrybut = trim( (string) ( $data['kolor_nazwa'] ?? '' ) );
		$tytul   = (string) ( $data['title'] ?? '' );

		if ( '' !== $atrybut ) {
			return $atrybut;
		}


		if ( '' === $tytul ) {
			return '';
		}

		$do_wyciecia = array_filter( [
			$data['producent'] ?? '',
			$data['model'] ?? '',
		], static fn( $v ): bool => is_string( $v ) && '' !== trim( $v ) );

		foreach ( $do_wyciecia as $czesc ) {
			$tytul = preg_replace( '/' . preg_quote( (string) $czesc, '/' ) . '/iu', ' ', $tytul ) ?? $tytul;
		}

		$wzorce = [
			'/\bET\s*-?\d{1,3}\b/iu',            // ET35, ET-5
			'/\b\d{1,2}(?:[.,]\d)?\s*J\b/iu',      // 8.5J
			'/\b\d{1,2}(?:[.,]\d)?\s*x\s*\d{2}\b/iu', // 8.5x19
			// Caly ciag rozstawow naraz: "5x112/114.3" musi zniknac w calosci,
			// inaczej po wycieciu "5x112" zostaje samo "114.3".
			'/\b\d\s*x\s*\d{2,3}(?:[.,]\d)?(?:\s*\/\s*\d{2,3}(?:[.,]\d)?)*/iu',
			'/\b\d{2}\s*["\x27]{1,2}/u',           // 19 cali zapisane " albo ''
			'/\b\d{1,2}[.,]\d\b/u',              // luzna 10.5 po ucieciu J
			'/\bBLANK\b/iu',
			'/[\/+]/u',
		];

		$tytul = preg_replace( $wzorce, ' ', $tytul ) ?? $tytul;
		$tytul = preg_replace( '/\s+/u', ' ', $tytul ) ?? $tytul;
		$tytul = trim( $tytul, " \t\n\r-,." );

		// Same cyfry albo jeden znak to nie jest nazwa wykonczenia.
		return preg_match( '/\p{L}{3,}/u', $tytul ) ? $tytul : '';
	}

	/**
	 * Czy wykonczenie mozna pokazac na ofercie.
	 *
	 * Samo "nie zgadza sie z tytulem" to za malo, zeby je wyciac: wiele
	 * tytulow w ogole nie podaje koloru ("AXE EX11 19\" 8.5J ET20 5X120"),
	 * a wtedy atrybut jest jedynym zrodlem i zwykle jest dobry. Wycinamy
	 * tylko wtedy, gdy atrybut wyglada na smieci albo przeczy tytulowi.
	 */
	public static function wykonczenie_do_oferty( array $data ): bool {
		$w = trim( (string) ( $data['wykonczenie'] ?? '' ) );

		if ( '' === $w ) {
			return false;
		}

		$tytul = (string) ( $data['title'] ?? '' );

		if ( self::finish_zgodne( $w, $tytul ) ) {
			return true;
		}

		// Smieci po imporcie: cyfry, znaki laczace, zdania handlowe, uwagi o wadach.
		if ( preg_match( '/\d|[+:\/]|\s-\s/u', $w ) || mb_strlen( $w ) > 40 || count( preg_split( '/\s+/u', $w ) ) > 5 ) {
			return false;
		}

		foreach ( [ 'model', 'promocja', 'posiadamy', 'zapraszamy', 'komplet', 'wyprzeda', 'felg', 'brak',
			'uszkodz', 'outlet', 'sztuk', 'jedna', 'dwie', 'technologia', 'metoda', 'mozliw', 'możliw', 'polerowane', 'czarn', 'srebrn' ] as $zle ) {
			if ( str_contains( mb_strtolower( $w, 'UTF-8' ), $zle ) ) {
				return false;
			}
		}

		// Sprzecznosc: tytul podaje barwe, a atrybut innej grupy barw.
		$gw = self::grupy_barw( $w );
		$gt = self::grupy_barw( $tytul );

		if ( $gw && $gt && ! array_intersect( $gw, $gt ) ) {
			return false;
		}

		return true;
	}

	/** @return string[] grupy barw wystepujace w tekscie */
	private static function grupy_barw( string $t ): array {
		$t = mb_strtolower( $t, 'UTF-8' );
		$grupy = [
			'black'  => [ 'black', 'schwarz', 'noir' ],
			'silver' => [ 'silver', 'silber', 'chrome', 'hyper silver' ],
			'grey'   => [ 'grey', 'gray', 'grau', 'gunmetal', 'gun metal', 'graphite', 'anthracite', 'anthracit', 'titan' ],
			'bronze' => [ 'bronze', 'copper' ],
			'gold'   => [ 'gold' ],
			'white'  => [ 'white', 'weiss' ],
			'red'    => [ 'red', 'rot' ],
			'blue'   => [ 'blue' ],
			'green'  => [ 'green' ],
		];
		$out = [];
		foreach ( $grupy as $g => $slowa ) {
			foreach ( $slowa as $s ) {
				if ( preg_match( '/\b' . preg_quote( $s, '/' ) . '\b/u', $t ) ) {
					$out[] = $g;
					break;
				}
			}
		}
		return $out;
	}

	/**
	 * Czy wykonczenie wymaga sprawdzenia przez czlowieka.
	 *
	 * Sprzedawca nie wystawia takich produktow automatycznie - trafiaja na
	 * liste do weryfikacji. Proba rozstrzygania tego w kodzie skonczyla sie
	 * gorzej niz problem: przy tytulach bez wykonczenia "poprawka" z tytulu
	 * dawala "MODEL:YA001" zamiast poprawnego "Silver" z atrybutu.
	 */
	public static function watpliwe_wykonczenie( array $data ): bool {
		$atrybut = trim( (string) ( $data['kolor_nazwa'] ?? '' ) );

		if ( '' === $atrybut ) {
			return true;
		}

		return ! self::finish_zgodne( $atrybut, (string) ( $data['title'] ?? '' ) );
	}

	/**
	 * Czy wartosc pa_kolor jest wiarygodna: tytul konczy sie nia albo jej
	 * skrotem. Katalogi felg zapisuja wykonczenie na koncu nazwy, wiec
	 * rozbieznosc znaczy, ze atrybut mowi o czym innym niz produkt.
	 */
	public static function finish_zgodne( string $atrybut, string $tytul ): bool {
		$norm = static function ( string $s ): string {
			$s = html_entity_decode( $s, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
			$s = str_replace( [ '″', '”', '×' ], [ '"', '"', 'x' ], $s );

			return trim( preg_replace( '/[^a-z0-9]+/u', ' ', mb_strtolower( $s, 'UTF-8' ) ) ?? '' );
		};

		$a = $norm( $atrybut );
		$t = $norm( $tytul );

		if ( '' === $a || '' === $t ) {
			return false;
		}

		if ( str_ends_with( $t, $a ) ) {
			return true;
		}

		// Ten sam kolor zapisany inaczej: "Gloss Black" i "Black Glossy",
		// "Gunmetal" i "Gun Metal", "Matte" i "Matt". Porownujemy zbior slow
		// z konca tytulu po sprowadzeniu synonimow do jednej formy.
		$syn = static function ( string $x ): array {
			$x = str_replace( [ 'gun metal', 'gunmetall' ], 'gunmetal', $x );
			$zamiany = [
				'glossy' => 'gloss', 'shiny' => 'gloss', 'matte' => 'matt', 'mat' => 'matt',
				'gray' => 'grey', 'polish' => 'polished', 'machine' => 'machined',
				'machiend' => 'machined', 'brush' => 'brushed', 'anthracit' => 'anthracite',
				'titan' => 'titanium', 'lackiert' => '', 'with' => '', 'and' => '', 'w' => '',
			];
			$out = [];
			foreach ( explode( ' ', $x ) as $w ) {
				if ( '' === $w ) { continue; }
				$w = $zamiany[ $w ] ?? $w;
				if ( '' !== $w ) { $out[ $w ] = true; }
			}
			ksort( $out );
			return array_keys( $out );
		};

		$sa = $syn( $a );
		$tw = explode( ' ', $t );
		$ogon = $syn( implode( ' ', array_slice( $tw, -count( explode( ' ', $a ) ) - 1 ) ) );

		if ( $sa && ! array_diff( $sa, $ogon ) ) {
			return true;
		}

		// Tytul bywa skrocony do inicjalow: "Black Polished Face" -> "BPF".
		$ini = '';

		foreach ( explode( ' ', $a ) as $slowo ) {
			if ( '' !== $slowo ) {
				$ini .= $slowo[0];
			}
		}

		return strlen( $ini ) >= 2 && str_ends_with( $t, $ini );
	}

	/**
	 * Co opis sklepowy mowi o stanie towaru.
	 *
	 * Szablon zaklada komplet czterech nowych felg z dekielkami. Czesc
	 * produktow tego nie spelnia i opis musi to powiedziec wprost, inaczej
	 * kupujacy dostaje co innego, niz przeczytal.
	 *
	 * @return array{dekielki:bool,uszkodzenie:string,uzywane:bool,sztuk:?int,niejednorodny:string}
	 */
	public static function adnotacje( WC_Product $product ): array {
		// Tytul pomijamy - niesie nazwe i rozmiar, nie stan towaru. Pole koloru
		// czytamy, bo bywa w nim wpisana wada ("Black Matt Jedna felga posiada
		// delikatne uszkodzenie rantu").
		$tekst = html_entity_decode(
			wp_strip_all_tags( $product->get_description() . ' ' . $product->get_short_description() . '. ' . $product->get_attribute( 'pa_kolor' ) ),
			ENT_QUOTES | ENT_HTML5,
			'UTF-8'
		);
		$tekst = trim( preg_replace( '/\s+/u', ' ', $tekst ) ?? $tekst );
		$maly  = mb_strtolower( $tekst, 'UTF-8' );

		$out = [
			'dekielki'      => true,
			'uszkodzenie'   => '',
			'uzywane'       => false,
			'sztuk'         => null,
			'niejednorodny' => '',
		];

		foreach ( [ 'brak dekli', 'brak dekielk', 'brak kapsli', 'brak deki' ] as $f ) {
			if ( str_contains( $maly, $f ) ) {
				$out['dekielki'] = false;
			}
		}

		$out['uszkodzenie'] = self::wytnij_wokol(
			$tekst,
			[ 'uszkodz', 'rysy', 'zarysow', 'obtar', 'wady lakieru', 'utleniaj', 'ślady po demontażu' ]
		);

		foreach ( [ 'ex-demo', 'ex demo', 'odnowion', 'z demontażu', 'ślady użytkowania' ] as $f ) {
			if ( str_contains( $maly, $f ) ) {
				$out['uzywane'] = true;
			}
		}

		if ( preg_match( '/\b([2356])\s*(?:szt|sztuk)/iu', $tekst, $m ) ) {
			$out['sztuk'] = (int) $m[1];
		}

		$out['niejednorodny'] = self::wytnij_wokol(
			$tekst,
			[ 'kierunek prawy', 'felgi o szerokości', 'w rozstawie', 'sztuki posiadają', 'kolejne 2' ]
		);

		return $out;
	}

	/**
	 * Fragment opisu wokol pierwszego trafionego slowa.
	 *
	 * Opisy sklepowe bywaja jednym ciagiem bez kropek, wiec nie dzielimy ich
	 * na zdania, tylko wycinamy okno. Zdania zaprzeczajace ("brak uszkodzen",
	 * "bez wad") pomijamy - mowia dokladnie odwrotnie niz szukamy.
	 *
	 * @param string[] $slowa
	 */
	private static function wytnij_wokol( string $tekst, array $slowa ): string {
		$maly = mb_strtolower( $tekst, 'UTF-8' );
		$poz  = null;

		foreach ( $slowa as $f ) {
			$i = 0;

			while ( true ) {
				$i = mb_strpos( $maly, $f, $i );

				if ( false === $i ) {
					break;
				}

				// Zaprzeczenie tuz przed trafieniem = to nie jest wada.
				$kontekst = mb_substr( $maly, max( 0, $i - 24 ), 24 );

				if ( ! preg_match( '/\b(brak|bez|nie)\s*\S*\s*$/u', $kontekst ) ) {
					if ( null === $poz || $i < $poz ) {
						$poz = $i;
					}
					break;
				}

				$i += mb_strlen( $f );
			}
		}

		if ( null === $poz ) {
			return '';
		}

		// Poczatek zdania. Opisy sklepowe nie maja kropek miedzy specyfikacja
		// a uwaga o stanie ("Kolor: Black Matt Jedna felga posiada..."), wiec
		// szukamy wielkiej litery rozpoczynajacej slowo - to granica zdania
		// bez interpunkcji. Kropka, gdy jest, ma pierwszenstwo.
		$od    = max( 0, $poz - 110 );
		$przed = mb_substr( $tekst, $od, $poz - $od );
		$krop  = mb_strrpos( $przed, '. ' );

		if ( false !== $krop ) {
			$od += $krop + 2;
		} elseif ( preg_match_all( '/(?<=[a-ząćęłńóśźż0-9]\s)\p{Lu}/u', $przed, $m, PREG_OFFSET_CAPTURE ) ) {
			$ostatnia = end( $m[0] );
			$od      += mb_strlen( substr( $przed, 0, $ostatnia[1] ) );
		}

		$wycinek = mb_substr( $tekst, $od, 230 );
		$koniec  = mb_strpos( $wycinek, '. ' );

		if ( false !== $koniec ) {
			$wycinek = mb_substr( $wycinek, 0, $koniec + 1 );
		}

		return trim( $wycinek );
	}

	/**
	 * Otwor centralny wyczytany z opisu, gdy brak atrybutu pa_bore.
	 *
	 * Opisy podaja go jako "bore 73,1", "bore: 66.6", "otwor centralny 72,6",
	 * a przy Dawmac Forged czasem "srednica 66,9". Zakres 50-115 mm odsiewa
	 * srednice felgi w calach (15-24), ktora tez bywa nazwana "srednica".
	 */
	public static function bore_z_opisu( WC_Product $product ): string {
		$tekst = wp_strip_all_tags( $product->get_description() . ' ' . $product->get_short_description() );

		if ( ! preg_match_all( '/\b(?:bore|otw[oó]r(?:\s+centralny)?|średnica(?:\s+otworu)?|cb)\s*:?\s*(\d{2,3}(?:[.,]\d{1,2})?)/iu', $tekst, $m ) ) {
			return '';
		}

		foreach ( $m[1] as $v ) {
			$n = (float) str_replace( ',', '.', $v );

			if ( $n >= 50 && $n <= 115 ) {
				return self::bore( $v );
			}
		}

		return '';
	}

	/**
	 * Otwor centralny na postac ze slownika Allegro: "72,6".
	 *
	 * Sklep zapisuje go roznie - "72.6", "71,5", a bywa i "CB74.1" -
	 * wiec zdejmujemy litery i ujednolicamy separator na przecinek.
	 */
	public static function bore( $raw ): string {
		$v = is_array( $raw ) ? ( $raw[0] ?? '' ) : $raw;
		$v = str_replace( ',', '.', (string) $v );
		$v = preg_replace( '/[^0-9.]/', '', $v ) ?? '';

		if ( '' === $v || ! is_numeric( $v ) ) {
			return '';
		}

		// 56.00 -> 56, 72.60 -> 72,6
		$v = rtrim( rtrim( number_format( (float) $v, 2, '.', '' ), '0' ), '.' );

		return str_replace( '.', ',', $v );
	}

	/**
	 * ID produktow do wystawienia. Zakres uzgodniony: tylko to, co realnie
	 * lezy na magazynie - oferta na towar, ktorego nie ma, konczy sie
	 * anulowaniem zamowienia, a to bije w ranking ofert i Super Sprzedawce.
	 *
	 * Czytamy z tabeli dawmac-filters zamiast z meta_query, bo indeks ma
	 * to gotowe i nie tyka wp_postmeta przy 32 tys. produktow.
	 *
	 * @return int[]
	 */
	public static function in_stock_ids( int $limit = 0 ): array {
		global $wpdb;

		$index = $wpdb->prefix . 'dawmac_filter_index';
		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $index ) );

		if ( $exists !== $index ) {
			// Bez indeksu dawmac-filters wracamy do zwyklego zapytania Woo.
			return wc_get_products( [
				'status'       => 'publish',
				'stock_status' => 'instock',
				'limit'        => $limit > 0 ? $limit : -1,
				'return'       => 'ids',
			] );
		}

		$sql = "SELECT product_id FROM {$index}
		        WHERE attribute = 'stock' AND value_slug = 'instock'
		        ORDER BY product_id ASC";

		if ( $limit > 0 ) {
			$sql .= $wpdb->prepare( ' LIMIT %d', $limit );
		}

		return array_map( 'intval', $wpdb->get_col( $sql ) );
	}
}
