<?php
/**
 * Produkt WooCommerce -> kompletna oferta Allegro.
 *
 * Obudowa oferty (lokalizacja, warunki posprzedazowe, podatki, czas wysylki)
 * jest odwzorowana z oferty 14692637195, ktora juz dziala na koncie. Tam,
 * gdzie sprzedawca ma swoje ustawienia, nie wymyslamy wlasnych - rozbieznosc
 * miedzy ofertami to pytania od kupujacych i ryzyko sporu.
 *
 * POWIAZANIE OFERTA <-> PRODUKT trzymamy w meta produktu, NIE w external.id.
 * To pole jest juz zajete: sprzedawca zapisuje w nim lokalizacje magazynowa
 * ("769.G", "941.J") i uzywa jej operacyjnie. Nadpisanie skasowaloby te dane.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Dawmac_Allegro_Offer {

	/** Meta produktu z ID oferty na Allegro. */
	const META_OFFER = '_dawmac_allegro_offer_id';

	/** Meta ze skrotem ostatnio wyslanej tresci - do wykrywania zmian. */
	const META_HASH = '_dawmac_allegro_hash';

	/**
	 * Sklada oferte gotowa do POST /sale/product-offers.
	 *
	 * @param WC_Product $product
	 * @param array      $dict    Slowniki kategorii z Dawmac_Allegro_Mapper.
	 * @param string     $status  INACTIVE (szkic) albo ACTIVE (od razu na sprzedaz).
	 * @return array{offer: array, problemy: string[], dane: array}
	 */
	public static function build( $product, array $dict, string $status = 'INACTIVE' ): array {
		$config = dawmac_allegro_config();
		$o      = $config['oferta'];
		$dane   = Dawmac_Allegro_Product_Data::from_wc( $product );

		$problemy = [];

		// 1. Parametry kategorii.
		$m        = Dawmac_Allegro_Mapper::map( $dane, $dict );
		$problemy = array_merge( $problemy, $m['problemy'] );

		// 2. Zdjecia - musza lezec na serwerach Allegro, takze te w opisie.
		//
		// Limit 16 zdjec na oferte obejmuje TAKZE grafiki uzyte w opisie,
		// nie tylko galerie. Szablon wstawia banery, wiec galeria dostaje
		// tyle, ile po nich zostaje - inaczej Allegro odrzuca cala oferte
		// bledem "Mozesz dodac maksymalnie 16 zdjec".
		$zdjecia = Dawmac_Allegro_Images::product_images(
			array_slice( $dane['gallery'] ?? [], 0, self::limit_galerii( $config ) )
		);

		if ( ! $zdjecia ) {
			$problemy[] = 'nie udało się wgrać żadnego zdjęcia produktu';
		}

		$dane['image']   = $zdjecia[0] ?? null;
		$dane['zdjecia'] = $zdjecia;   // szablon siega po kolejne w dalszych sekcjach

		// 3. Opis z szablonu firmowego.
		$template = dawmac_allegro_template();
		$opis     = $template->build( $dane );
		$problemy = array_merge( $problemy, Dawmac_Allegro_Template::validate( $opis ) );

		// 4. Tytul. Sklepowy jest pisany przez czlowieka i zawiera pelna
		//    konfiguracje mieszana, wiec ma pierwszenstwo, o ile sie miesci.
		$tytul = self::tytul( $dane, $template );

		if ( '' === $tytul ) {
			$problemy[] = 'nie da się zbudować tytułu oferty';
		}

		// 5. Cena.
		$cena = (string) ( $dane['cena'] ?? '' );

		if ( '' === $cena || (float) $cena <= 0 ) {
			$problemy[] = 'produkt nie ma ceny';
		}

		$oferta = [
			'name'     => $tytul,
			'category' => [ 'id' => $o['kategoria'] ],

			// Parametry OFERTY: tylko stan i liczba sztuk. Reszta opisuje
			// produkt i musi isc w productSet - wskazanie producenta tutaj
			// konczy sie bledem 422.
			'parameters' => $m['oferta'],

			'productSet' => self::product_set( $dane, $m, $zdjecia, $tytul, $o, $dict ),

			'description' => $opis,
			'images'   => $zdjecia,

			'sellingMode' => [
				'format' => 'BUY_NOW',
				'price'  => [ 'amount' => $cena, 'currency' => 'PLN' ],
			],

			// Jednostka SET, bo cena dotyczy kompletu czterech felg.
			'stock' => [
				'available' => self::ilosc( $product ),
				'unit'      => $o['jednostka'],
			],

			'location' => $o['lokalizacja'],

			'delivery' => [
				'shippingRates' => [ 'id' => self::cennik( $dane, $o ) ],
				'handlingTime'  => $o['handling_time'],
			],

			'afterSalesServices' => [
				'impliedWarranty' => [ 'id' => $o['po_sprzedazy']['impliedWarranty'] ],
				'returnPolicy'    => [ 'id' => $o['po_sprzedazy']['returnPolicy'] ],
			],

			'payments'    => [ 'invoice' => $o['faktura'] ],
			'taxSettings' => [
				'subject' => 'GOODS',
				'rates'   => [ [ 'rate' => $o['stawka_vat'], 'countryCode' => 'PL' ] ],
			],

			'messageToSellerSettings' => [ 'mode' => 'OPTIONAL' ],

			'publication' => [ 'status' => $status ],
		];

		// Lokalizacja magazynowa w external.id - tak jak w ofertach robionych
		// recznie. Gdy jej nie ma w opisie, pole zostaje puste.
		$magazyn = self::lokalizacja_magazynowa( $product );

		if ( null !== $magazyn ) {
			$oferta['external'] = [ 'id' => $magazyn ];
		}

		return [ 'offer' => $oferta, 'problemy' => $problemy, 'dane' => $dane ];
	}

	/**
	 * Wystawia oferte i zapisuje powiazanie przy produkcie.
	 *
	 * @return string|WP_Error ID oferty na Allegro.
	 */
	public static function publish( $product, array $dict, string $status = 'INACTIVE' ) {
		$b = self::build( $product, $dict, $status );

		if ( $b['problemy'] ) {
			return new WP_Error(
				'dawmac_allegro_oferta',
				'Oferta nie przeszła kontroli: ' . implode( ' | ', $b['problemy'] )
			);
		}

		// /sale/product-offers, bo POST na /sale/offers zwraca 400.
		// Struktura odwzorowana z dzialajacej oferty 14692637195.
		// Produkt z przypisana oferta aktualizujemy zamiast tworzyc druga.
		$istniejaca = self::offer_id( $product->get_id() );

		$response = self::wyslij( $b['offer'], $istniejaca );

		// Allegro potrafi samo rozpoznac produkt w katalogu i odmowic, bo nasz
		// "Kod producenta" rozni sie od katalogowego:
		//
		//   "Produkt juz istnieje w Katalogu... wartosc parametru Kod producenta
		//    w ofercie to `CVR5` i rozni sie od wartosci w produkcie z naszego
		//    katalogu `CVR52090P5H2872BBZ`. Aby wystawic oferte, zmien wartosc na..."
		//
		// To lepsze dopasowanie niz nasze po nazwie - serwis porownuje pelne
		// parametry, nie tekst. Bierzemy podpowiedz i ponawiamy raz.
		if ( is_wp_error( $response ) ) {
			$kod = self::kod_z_bledu( $response );

			// PODPOWIEDZI NIE PRZYJMUJEMY NA SLOWO. Allegro dopasowuje po
			// rozmiarze i ET, a wykonczenia nie sprawdza - podsunelo nam
			// wariant "Double Tinted Black" dla felgi "Brushed Bronze".
			// Rozmiar sie zgadzal, kupujacy dostalby inny kolor.
			if ( null !== $kod && self::kod_pasuje( $kod, $b['dane'] ) ) {
				self::podmien_kod( $b['offer'], $kod );
				$response = self::wyslij( $b['offer'], $istniejaca );
			}
		}

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$id = (string) ( $response['id'] ?? '' );

		if ( '' === $id ) {
			return new WP_Error( 'dawmac_allegro_oferta', 'Allegro nie zwróciło ID oferty.' );
		}

		update_post_meta( $product->get_id(), self::META_OFFER, $id );
		update_post_meta( $product->get_id(), self::META_HASH, self::hash( $b['offer'] ) );

		return $id;
	}

	/**
	 * Wysyla oferte: PATCH gdy juz istnieje, POST gdy nowa.
	 *
	 * Statusu publikacji przy aktualizacji NIE ruszamy - Allegro odrzuca proba
	 * przestawienia zywej oferty na szkic, a poza tym decyzja o tym, co jest
	 * na sprzedazy, nalezy do sprzedawcy, nie do aktualizacji opisu.
	 */
	private static function wyslij( array $offer, ?string $istniejaca ) {
		if ( $istniejaca ) {
			unset( $offer['publication'] );

			return Dawmac_Allegro_Client::patch( "/sale/product-offers/{$istniejaca}", $offer );
		}

		return Dawmac_Allegro_Client::post( '/sale/product-offers', $offer );
	}

	/**
	 * Czy podpowiedziany kod opisuje TO SAMO wykonczenie co nasz towar.
	 *
	 * Parametr Wykonczenie w katalogu jest pusty, wiec porownanie po nim
	 * zawsze wychodzilo negatywnie i odrzucalismy nawet trafne podpowiedzi.
	 * Nosnikiem jest koncowka kodu producenta - inicjaly wykonczenia:
	 *
	 *   CVR71985P5L3572DTB  -> DTB -> Double Tinted Black
	 *   CVR71985P5L4566BBZ  -> BBZ -> Brushed Bronze
	 *   CVR52090P5H2872PBK  -> PBK -> Platinum Black
	 *
	 * Porownujemy inicjaly, nie grupe barwy. Grupa byla za szeroka: Platinum
	 * Black i Double Tinted Black to oba "czarne", a to inne felgi - Allegro
	 * podsunelo nam wlasnie taka zamiane, w dodatku z innym odsadzeniem.
	 */
	private static function kod_pasuje( string $kod, array $dane ): bool {
		$nasze = self::inicjaly( (string) ( $dane['wykonczenie'] ?? '' ) );

		if ( '' === $nasze ) {
			return false;
		}

		if ( ! preg_match( '/([A-Z]{2,4})$/', strtoupper( trim( $kod ) ), $m ) ) {
			return false;
		}

		// BBZ przy "Brushed Bronze" - skrot bywa dluzszy niz same inicjaly,
		// wiec wystarczy, ze sie od nich zaczyna.
		return $m[1] === $nasze || str_starts_with( $m[1], $nasze );
	}

	/** "Double Tinted Black" -> "DTB" */
	private static function inicjaly( string $tekst ): string {
		$out = '';

		foreach ( preg_split( '/[^\p{L}]+/u', trim( $tekst ) ) ?: [] as $slowo ) {
			if ( '' !== $slowo ) {
				$out .= mb_strtoupper( mb_substr( $slowo, 0, 1, 'UTF-8' ), 'UTF-8' );
			}
		}

		return strlen( $out ) >= 2 ? $out : '';
	}


	/** Kod producenta podpowiedziany przez Allegro w tresci bledu. */
	private static function kod_z_bledu( WP_Error $e ): ?string {
		if ( ! preg_match( '/zmie[nń] warto[śs][cć] na\s*`([^`]+)`/iu', $e->get_error_message(), $m ) ) {
			return null;
		}

		$kod = trim( $m[1] );

		return '' !== $kod ? $kod : null;
	}

	/** Podmienia "Kod producenta" w parametrach produktu. */
	private static function podmien_kod( array &$offer, string $kod ): void {
		foreach ( $offer['productSet'] as &$poz ) {
			if ( ! isset( $poz['product']['parameters'] ) ) {
				continue;
			}

			foreach ( $poz['product']['parameters'] as &$par ) {
				if ( Dawmac_Allegro_Mapper::P_KOD === ( $par['id'] ?? '' ) ) {
					$par['values'] = [ $kod ];
				}
			}
			unset( $par );
		}
		unset( $poz );
	}

	/** ID oferty przypisanej do produktu albo null. */
	public static function offer_id( int $product_id ): ?string {
		$id = get_post_meta( $product_id, self::META_OFFER, true );

		return $id ? (string) $id : null;
	}

	/**
	 * Tytul oferty. Sklepowy ma pierwszenstwo - zawiera pelna konfiguracje
	 * mieszana ("8.5J ET25 + 10J ET43"), ktorej parametry Allegro nie udzwigna.
	 */
	private static function tytul( array $dane, Dawmac_Allegro_Template $template ): string {
		$sklepowy = Dawmac_Allegro_Text::plain( (string) ( $dane['title'] ?? '' ) );

		// Allegro nie przyjmuje znakow ozdobnych w tytule.
		$sklepowy = trim( preg_replace( '/[^\p{L}\p{N}\s.,\-\/+"\']/u', '', $sklepowy ) ?? $sklepowy );
		$sklepowy = trim( preg_replace( '/\s+/u', ' ', $sklepowy ) ?? $sklepowy );

		if ( '' !== $sklepowy && mb_strlen( $sklepowy, 'UTF-8' ) <= 75 ) {
			return $sklepowy;
		}

		return $template->build_offer_title( $dane );
	}

	/** Stan magazynowy; brak liczby traktujemy jako jeden komplet. */
	private static function ilosc( $product ): int {
		$q = $product->get_stock_quantity();

		return ( null === $q || $q < 1 ) ? 1 : (int) $q;
	}

	/**
	 * productSet - dwie drogi.
	 *
	 * KATALOG: gdy wszystkie szerokosci maja odpowiednik w katalogu Allegro,
	 * podpinamy sie pod gotowe pozycje. Oferta dziedziczy wtedy dane GPSR
	 * i parametry produktu, i trafia na strone produktu w serwisie.
	 * Pozycje katalogowe sa na POJEDYNCZA FELGE, wiec komplet to 1 pozycja
	 * x 4 szt., a zestaw mieszany 2 pozycje x 2 szt.
	 *
	 * WLASNY PRODUKT: gdy dopasowania brak albo jest niepelne. Wtedy musimy
	 * podac wszystko sami, razem z GPSR - bez producenta odpowiedzialnego
	 * i informacji o bezpieczenstwie oferta nie przejdzie walidacji.
	 *
	 * Niepelne dopasowanie celowo traktujemy jak brak: podpiecie polowy
	 * zestawu mieszanego pod pozycje katalogowa oznacza, ze kupujacy widzi
	 * na stronie produktu co innego, niz dostanie.
	 */
	private static function product_set( array $dane, array $m, array $zdjecia, string $tytul, array $o, array $dict ): array {
		$match = Dawmac_Allegro_Catalog::match( $dane );

		if ( 'pelne' === $match['status'] && $match['productSet'] ) {
			// Wbrew temu, czego mozna by oczekiwac, podpiecie pod pozycje
			// katalogowa NIE dziedziczy danych GPSR - Allegro dalej zwraca
			// RESPONSIBLE_PRODUCER_NOT_SPECIFIED. Dokladamy je do kazdej
			// pozycji zestawu.
			return array_map(
				fn( array $poz ): array => $poz + self::gpsr( $dane, $o ),
				$match['productSet']
			);
		}

		// Wlasny produkt: ZAWSZE jedna pozycja na caly komplet.
		//
		// Rozbicie zestawu mieszanego na dwie pozycje bylo pierwsza proba,
		// ale Allegro odmawia: "Mozesz zmieniac wartosci parametrow tylko
		// pierwszego produktu w zestawie. Parametry pozostalych produktow
		// pobieramy z Katalogu". Druga felga musialaby wiec istniec w katalogu,
		// a wlasnie dla wykonczen spoza katalogu jej tam nie ma.
		//
		// Zostaje jedna pozycja x4. Szerokosc jest wymagana, wiec idzie wezsza
		// (przednia); ET przy dwoch wartosciach zostaje pusty - lepiej go nie
		// podac, niz podac jeden z dwoch i zafalszowac filtry. Pelna
		// konfiguracja stoi w tytule i w parametrach opisu.
		return [ [
			'product' => [
				'name'       => $tytul,
				'category'   => [ 'id' => $o['kategoria'] ],
				'parameters' => $m['produkt'],
				'images'     => $zdjecia,
			],
			'quantity' => [ 'value' => self::ILE_FELG ],
		] + self::gpsr( $dane, $o ) ];
	}

	/** Twardy limit Allegro na oferte. */
	const LIMIT_ZDJEC = 16;

	/** Komplet na oferte - cztery felgi. */
	const ILE_FELG = 4;

	/**
	 * Ile zdjec produktu uzywa sam opis: jedno przy parametrach, jedno przy
	 * "Dlaczego DAWMAC" i dwa w sekcji zdjec.
	 */
	const ZDJEC_W_OPISIE = 4;

	/**
	 * Ile zdjec produktu zmiesci sie w galerii.
	 *
	 * Limit 16 obejmuje galerie ORAZ obrazki opisu - i liczy je OSOBNO,
	 * nawet gdy w opisie stoi dokladnie to samo zdjecie co w galerii.
	 * Wyszlo to dopiero po usunieciu banerow: galeria urosla do 16, opis
	 * dolozyl swoje cztery i Allegro odrzucilo cala oferte komunikatem
	 * "Podany adres obrazka jest nieprawidlowy" - mylacym, bo problemem
	 * byla liczba, nie adres.
	 *
	 * Odejmujemy wiec jedno i drugie: banery, ktore realnie zostaly wgrane,
	 * i zdjecia, po ktore siega opis.
	 */
	private static function limit_galerii( array $config ): int {
		$banery = 0;

		foreach ( array_keys( (array) ( $config['images'] ?? [] ) ) as $klucz ) {
			if ( Dawmac_Allegro_Images::template_image( (string) $klucz ) ) {
				++$banery;
			}
		}

		return max( 1, self::LIMIT_ZDJEC - $banery - self::ZDJEC_W_OPISIE );
	}

	/** Pola GPSR doklejane do kazdej pozycji zestawu. */
	private static function gpsr( array $dane, array $o ): array {
		return [
			'responsibleProducer' => [ 'id' => self::producent_gpsr( $dane, $o ) ],
			'safetyInformation'   => [
				'type'        => 'TEXT',
				'description' => $o['gpsr']['bezpieczenstwo'],
			],
		];
	}

	/**
	 * Producent odpowiedzialny w rozumieniu GPSR. Marki bez wlasnego wpisu
	 * dostaja DAWMAC Polska - jako importer wprowadzajacy towar na rynek UE.
	 */
	private static function producent_gpsr( array $dane, array $o ): string {
		$producent = (string) ( $dane['producent'] ?? '' );

		return $o['gpsr']['producenci'][ $producent ] ?? $o['gpsr']['producenci']['domyslny'];
	}

	/** Cennik dostawy - per producent, z zapasowym dla reszty. */
	private static function cennik( array $dane, array $o ): string {
		$producent = (string) ( $dane['producent'] ?? '' );

		return $o['cenniki_dostawy'][ $producent ] ?? $o['cenniki_dostawy']['domyslny'];
	}

	/**
	 * Lokalizacja magazynowa z opisu produktu ("42.L / NHB", "941.J").
	 * Ten sam wzorzec, ktorym sanitizer wycina ja z publicznego opisu -
	 * tam jest niepozadana, a tutaj jest dokladnie tym, czego trzeba.
	 */
	private static function lokalizacja_magazynowa( $product ): ?string {
		$tekst = $product->get_description() . ' ' . $product->get_short_description();

		if ( preg_match( '#\b\d{1,3}\.[A-Z]{1,2}(?:\s*/\s*[A-Z]{2,4})?\b#', wp_strip_all_tags( $tekst ), $m ) ) {
			return trim( $m[0] );
		}

		return null;
	}

	/** Skrot tresci - zeby aktualizowac tylko to, co sie zmienilo. */
	public static function hash( array $offer ): string {
		return md5( (string) wp_json_encode( $offer ) );
	}
}
