<?php
/**
 * Produkt ze sklepu -> parametry oferty Allegro.
 *
 * Parametry slownikowe wysyla sie przez ID WARTOSCI, nie przez jej nazwe:
 *
 *   { "id": "127098", "valuesIds": ["127098_5"] }   <- slownik
 *   { "id": "224017", "values": ["JR11-8518"] }     <- tekst
 *
 * Stad cache slownikow kategorii - inaczej kazde wystawienie oferty
 * kosztowaloby dodatkowe zapytanie o kilkaset wartosci.
 *
 * USTALENIA HANDLOWE zaszyte w tej klasie:
 *  - cena w sklepie dotyczy KOMPLETU 4 SZTUK,
 *  - przy zestawach mieszanych (8.5J + 10J) w parametr idzie szerokosc
 *    PRZEDNIA, czyli wezsza; pelna konfiguracja trafia do tytulu i opisu,
 *  - przy mieszanych ET pomijamy zamiast wybierac jedno z dwoch -
 *    ten parametr nie jest wymagany, wiec polprawda jest gorsza niz brak.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Dawmac_Allegro_Mapper {

	/** Motoryzacja > Opony i felgi > Felgi > Do samochodow > Aluminiowe. */
	const CATEGORY_WHEELS = '257711';

	const OPT_DICT = 'dawmac_allegro_dict';

	/** ID parametrow w kategorii felg aluminiowych. */
	const P_STAN      = '11323';
	const P_LICZBA    = '128688';
	const P_SREDNICA  = '127096';
	const P_SZEROKOSC = '127098';
	const P_ROZSTAW   = '346';
	const P_ET        = '127097';
	const P_PRODUCENT = '127413';
	const P_KOLOR     = '250589';
	const P_MODEL     = '237206';
	const P_KOD       = '224017';
	const P_WYKONCZ   = '202913';
	const P_OTWOR     = '250611';

	/**
	 * Wykonczenie ze sklepu na slownik Allegro. Klucz to slowo z tytulu,
	 * wartosc - pozycja slownika 202913, ktora trzeba trafic co do znaku.
	 *
	 * Kolejnosc ma znaczenie: bardziej szczegolowe wzorce ida pierwsze,
	 * zeby "bronze matt" nie zlapalo sie na samo "bronze".
	 */
	const WYKONCZENIA = [
		'bronze matt'   => 'BRONZE MATT',
		'matt bronze'   => 'BRONZE MATT',
		'bronze'        => 'BRONZE',
		'hyper silver'  => 'HS - hyper silver',
		'gun metal'     => 'GM - gun metal',
		'matt black'    => 'BM - czarny mat',
		'black matt'    => 'BM - czarny mat',
		'gold'          => 'GOLD - złote',
		'silver'        => 'SI - srebrne',
		'black'         => 'BL - czarne',
		'white'         => 'W - białe',
	];

	/**
	 * Kolory: sklep trzyma liczbe mnoga rodzaju nijakiego ("Czarne"),
	 * Allegro pojedyncza rodzaju meskiego mala litera ("czarny").
	 * "Brazowe i zlote" to jedna kategoria sklepowa na dwa rozne kolory -
	 * rozstrzygamy ja po tytule, a gdy sie nie da, pomijamy parametr.
	 */
	const KOLORY = [
		'białe'      => 'biały',
		'czarne'     => 'czarny',
		'grafitowe'  => 'grafitowy',
		'srebrne'    => 'srebrny',
		'szare'      => 'szary',
		'złote'      => 'złoty',
		'brązowe'    => 'brązowy',
		'niebieskie' => 'niebieski',
		'zielone'    => 'zielony',
		'czerwone'   => 'czerwony',
		'chrom'      => 'chrom',
	];

	/** Slowa z tytulu rozstrzygajace kategorie "Brazowe i zlote". */
	const KOLOR_Z_TYTULU = [
		'gold'   => 'złoty',
		'złot'   => 'złoty',
		'zlot'   => 'złoty',
		'bronze' => 'brązowy',
		'brąz'   => 'brązowy',
		'braz'   => 'brązowy',
	];

	/**
	 * Slownik kategorii: id parametru => [ znormalizowana wartosc => id wartosci ].
	 * Trzymamy w opcji, bo lista producentow ma 813 pozycji i nie ma sensu
	 * ciagac jej przy kazdej ofercie.
	 *
	 * @return array|WP_Error
	 */
	public static function dictionary( string $category = self::CATEGORY_WHEELS, bool $refresh = false ) {
		$cache = get_option( self::OPT_DICT, [] );

		if ( ! $refresh && isset( $cache[ $category ] ) ) {
			return $cache[ $category ];
		}

		$response = Dawmac_Allegro_Client::get( "/sale/categories/{$category}/parameters" );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$dict = [];

		foreach ( $response['parameters'] ?? [] as $p ) {
			$id = (string) $p['id'];

			$dict[ $id ] = [
				'nazwa'    => (string) ( $p['name'] ?? '' ),
				'typ'      => (string) ( $p['type'] ?? '' ),
				'wymagany' => ! empty( $p['required'] ),
				'wartosci' => [],
			];

			foreach ( $p['dictionary'] ?? [] as $v ) {
				$dict[ $id ]['wartosci'][ self::norm( (string) $v['value'] ) ] = (string) $v['id'];
			}
		}

		$cache[ $category ] = $dict;
		update_option( self::OPT_DICT, $cache, 'no' );

		return $dict;
	}

	/**
	 * Parametry opisujace OFERTE, nie produkt.
	 *
	 * Allegro dzieli je na dwie grupy i pilnuje tego podzialu: wskazanie
	 * producenta w sekcji "offer" konczy sie bledem 422. Stan i liczba sztuk
	 * zaleza od konkretnej oferty, reszta opisuje sam produkt i trafia
	 * do productSet.
	 */
	const PARAMETRY_OFERTY = [ self::P_STAN, self::P_LICZBA ];

	/**
	 * Buduje parametry, rozdzielone na te dla oferty i te dla produktu.
	 *
	 * @return array{parameters: array, oferta: array, produkt: array, problemy: string[]}
	 */
	/**
	 * @param array $nadpisz Szerokosc i ET dla jednej pozycji zestawu.
	 *                       Przy zestawie mieszanym kazda pozycja opisuje
	 *                       inna felge, wiec nie da sie ich wyliczyc z produktu.
	 */
	public static function map( array $product, array $dict, array $nadpisz = [] ): array {
		$out      = [];
		$problemy = [];

		$slownik = static function ( string $pid, ?string $wartosc, bool $wymagany, string $etykieta )
			use ( $dict, &$out, &$problemy ): void {

			if ( null === $wartosc || '' === $wartosc ) {
				if ( $wymagany ) {
					$problemy[] = "brak wartości dla: {$etykieta}";
				}
				return;
			}

			$id = $dict[ $pid ]['wartosci'][ self::norm( $wartosc ) ] ?? null;

			if ( null === $id ) {
				$problemy[] = sprintf( '%s: "%s" nie ma w słowniku Allegro', $etykieta, $wartosc );
				return;
			}

			$out[] = [ 'id' => $pid, 'valuesIds' => [ $id ] ];
		};

		// Stale wynikajace z ustalen handlowych.
		$slownik( self::P_STAN, 'Nowy', true, 'Stan' );
		$slownik( self::P_LICZBA, '4 szt.', true, 'Liczba felg w ofercie' );

		$slownik( self::P_PRODUCENT, $product['producent'] ?? null, true, 'Producent felg' );
		$slownik( self::P_SREDNICA, self::srednica( $product ), true, 'Średnica felgi' );
		$slownik( self::P_SZEROKOSC, $nadpisz['szerokosc'] ?? self::szerokosc( $product ), true, 'Szerokość felgi' );
		$slownik( self::P_ROZSTAW, self::rozstaw( $product ), true, 'Rozstaw śrub' );

		// Nieobowiazkowe - brak dopasowania pomijamy zamiast blokowac oferte.
		$et = $nadpisz['et'] ?? self::et( $product );

		if ( null !== $et && isset( $dict[ self::P_ET ]['wartosci'][ self::norm( $et ) ] ) ) {
			$out[] = [ 'id' => self::P_ET, 'valuesIds' => [ $dict[ self::P_ET ]['wartosci'][ self::norm( $et ) ] ] ];
		}

		$kolor = self::kolor( $product );

		if ( null !== $kolor && isset( $dict[ self::P_KOLOR ]['wartosci'][ self::norm( $kolor ) ] ) ) {
			$out[] = [ 'id' => self::P_KOLOR, 'valuesIds' => [ $dict[ self::P_KOLOR ]['wartosci'][ self::norm( $kolor ) ] ] ];
		}

		// Parametry tekstowe. "Kod producenta" jest wymagany - bierzemy model,
		// a gdy go brak, SKU ze sklepu.
		$kod = trim( (string) ( $product['model'] ?? '' ) ) ?: trim( (string) ( $product['sku'] ?? '' ) );

		if ( '' === $kod ) {
			$problemy[] = 'brak wartości dla: Kod producenta (nie ma ani modelu, ani SKU)';
		} else {
			$out[] = [ 'id' => self::P_KOD, 'values' => [ $kod ] ];
		}

		$otwor = trim( (string) ( $product['bore'] ?? '' ) );

		if ( '' !== $otwor ) {
			$id = $dict[ self::P_OTWOR ]['wartosci'][ self::norm( $otwor ) ] ?? null;

			if ( null !== $id ) {
				$out[] = [ 'id' => self::P_OTWOR, 'valuesIds' => [ $id ] ];
			}
		}

		$wyk = self::wykonczenie( $product, $dict );

		if ( null !== $wyk ) {
			$out[] = [ 'id' => self::P_WYKONCZ, 'valuesIds' => [ $wyk ] ];
		}

		if ( ! empty( $product['model'] ) ) {
			$out[] = [ 'id' => self::P_MODEL, 'values' => [ (string) $product['model'] ] ];
		}

		$oferta = $produkt = [];

		foreach ( $out as $p ) {
			if ( in_array( $p['id'], self::PARAMETRY_OFERTY, true ) ) {
				$oferta[] = $p;
			} else {
				$produkt[] = $p;
			}
		}

		return [
			'parameters' => $out,      // caly zestaw - do podgladu i kontroli
			'oferta'     => $oferta,
			'produkt'    => $produkt,
			'problemy'   => $problemy,
		];
	}

	/**
	 * Wykonczenie na ID ze slownika Allegro. Gdy nie ma pewnego trafienia,
	 * zwracamy null - parametr nie jest wymagany, a zgadniete wykonczenie
	 * jest gorsze niz jego brak.
	 */
	private static function wykonczenie( array $product, array $dict ): ?string {
		$tekst = mb_strtolower( trim( (string) ( $product['wykonczenie'] ?? '' ) ), 'UTF-8' );

		if ( '' === $tekst ) {
			return null;
		}

		foreach ( self::WYKONCZENIA as $szukaj => $wartosc ) {
			if ( ! str_contains( $tekst, $szukaj ) ) {
				continue;
			}

			$id = $dict[ self::P_WYKONCZ ]['wartosci'][ self::norm( $wartosc ) ] ?? null;

			if ( null !== $id ) {
				return $id;
			}
		}

		return null;
	}

	/** Sklep trzyma '20"' - Allegro tak samo. Zostawiamy, tylko czyscimy. */
	private static function srednica( array $product ): ?string {
		$v = self::pierwsza( $product['srednica'] ?? null );

		return null === $v ? null : trim( $v );
	}

	/**
	 * Sklep: '8.5J'. Allegro: '8.5"'. Litera J to oznaczenie obrzeza felgi,
	 * nie czesc rozmiaru - stad 0% dopasowania przed normalizacja.
	 *
	 * Przy zestawie mieszanym bierzemy WEZSZA, czyli przednia.
	 */
	/**
	 * Rozklada komplet na pozycje: [szerokosc, ET, ile sztuk].
	 *
	 * Komplet jednorodny to jedna pozycja x4. Zestaw mieszany to dwie
	 * pozycje po dwie felgi - wezsza z nizszym ET z przodu, szersza z tylu.
	 * Bez tego rozbicia oferta opisywalaby tylko przednia felge, a kupujacy
	 * nie wiedzialby, co dostanie na tylna os.
	 *
	 * @return array<int,array{szerokosc:string,et:?string,ile:int}>
	 */
	public static function pozycje( array $product ): array {
		$szer = [];

		foreach ( self::wszystkie( $product['szerokosc'] ?? null ) as $v ) {
			$c = (float) str_replace( ',', '.', preg_replace( '/[^0-9.,]/', '', $v ) ?? '' );

			if ( $c > 0 ) {
				$szer[] = $c;
			}
		}

		$ety = [];

		foreach ( self::wszystkie( $product['et'] ?? null ) as $v ) {
			$c = preg_replace( '/[^0-9-]/', '', (string) $v ) ?? '';

			if ( '' !== $c ) {
				$ety[] = (int) $c;
			}
		}

		$szer = array_values( array_unique( $szer ) );
		sort( $szer );
		sort( $ety );

		if ( ! $szer ) {
			return [];
		}

		// Jedna szerokosc: caly komplet to ta sama felga.
		if ( 1 === count( $szer ) ) {
			return [ [
				'szerokosc' => number_format( $szer[0], 1, '.', '' ) . '"',
				'et'        => 1 === count( $ety ) ? (string) $ety[0] : null,
				'ile'       => 4,
			] ];
		}

		// Zestaw mieszany. ET-y laczymy z szerokosciami po kolei; gdy liczby
		// sie nie zgadzaja, zostawiamy ET pusty zamiast zgadywac.
		$pary = count( $ety ) === count( $szer );
		$out  = [];

		foreach ( $szer as $i => $w ) {
			$out[] = [
				'szerokosc' => number_format( $w, 1, '.', '' ) . '"',
				'et'        => $pary ? (string) $ety[ $i ] : null,
				'ile'       => 2,
			];
		}

		return $out;
	}

	private static function szerokosc( array $product ): ?string {
		$wartosci = self::wszystkie( $product['szerokosc'] ?? null );

		if ( ! $wartosci ) {
			return null;
		}

		$liczby = [];

		foreach ( $wartosci as $v ) {
			$czysta = (float) str_replace( ',', '.', preg_replace( '/[^0-9.,]/', '', $v ) );

			if ( $czysta > 0 ) {
				$liczby[] = $czysta;
			}
		}

		if ( ! $liczby ) {
			return null;
		}

		sort( $liczby );
		$w = $liczby[0];

		// Allegro zapisuje z jednym miejscem po przecinku: 8.5", 9.0", 10.0".
		return number_format( $w, 1, '.', '' ) . '"';
	}

	private static function rozstaw( array $product ): ?string {
		return self::pierwsza( $product['rozstaw'] ?? null );
	}

	/**
	 * Przy zestawie mieszanym sa dwa ET. Parametr przyjmuje jedno,
	 * a nie jest wymagany - wiec zamiast wybierac polprawde, pomijamy go.
	 * Pelna konfiguracja i tak idzie do tytulu oraz listy parametrow w opisie.
	 */
	/**
	 * ET do parametru Allegro - zawsze jedna wartosc.
	 *
	 * Przy zestawie mieszanym podajemy odsadzenie PRZEDNIEJ felgi, spojnie
	 * z szerokoscia, ktora idzie w tym samym parametrze. Zostawianie pola
	 * pustego wypychalo oferte z filtrow wyszukiwarki - kupujacy szukajacy
	 * po ET nie widzial jej wcale. Pelny rozklad obu osi stoi w opisie.
	 */
	private static function et( array $product ): ?string {
		$pozycje = self::pozycje( $product );

		if ( $pozycje && null !== $pozycje[0]['et'] ) {
			return $pozycje[0]['et'];
		}

		$wartosci = self::wszystkie( $product['et'] ?? null );

		return $wartosci ? trim( (string) $wartosci[0] ) : null;
	}

	/** Kolor sklepowy na slownik Allegro, z rozstrzyganiem po tytule. */
	private static function kolor( array $product ): ?string {
		$v = self::pierwsza( $product['kolor'] ?? null );

		if ( null === $v ) {
			return null;
		}

		$klucz = self::norm( $v );

		if ( isset( self::KOLORY[ $klucz ] ) ) {
			return self::KOLORY[ $klucz ];
		}

		// Kategorie zbiorcze ("Brazowe i zlote") rozstrzygamy tytulem.
		$tytul = self::norm( (string) ( $product['title'] ?? '' ) );

		foreach ( self::KOLOR_Z_TYTULU as $szukaj => $kolor ) {
			if ( str_contains( $tytul, $szukaj ) ) {
				return $kolor;
			}
		}

		return null;
	}

	/** @return string[] */
	private static function wszystkie( $v ): array {
		if ( is_array( $v ) ) {
			return array_values( array_filter( array_map( 'strval', $v ), static fn( $x ) => '' !== trim( $x ) ) );
		}

		return '' === trim( (string) $v ) ? [] : [ (string) $v ];
	}

	private static function pierwsza( $v ): ?string {
		$all = self::wszystkie( $v );

		return $all ? $all[0] : null;
	}

	/** Porownanie odporne na wielkosc liter i nadmiarowe spacje. */
	private static function norm( string $v ): string {
		return mb_strtolower( trim( preg_replace( '/\s+/u', ' ', $v ) ?? $v ), 'UTF-8' );
	}

	/** Czysci cache slownikow - nastepne uzycie pobierze je od nowa. */
	public static function flush(): void {
		delete_option( self::OPT_DICT );
	}
}
