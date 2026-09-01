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

		$dane['image'] = $zdjecia[0] ?? null;

		// 3. Opis z szablonu firmowego.
		$template = dawmac_allegro_template();
		$opis     = $template->build( $dane );
		$problemy = array_merge( $problemy, Dawmac_Allegro_Template::validate( $opis ) );

		// 4. Tytul. Sklepowy jest pisany przez czlowieka i zawiera pelna
		//    konfiguracje schodkowa, wiec ma pierwszenstwo, o ile sie miesci.
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

			'productSet' => self::product_set( $dane, $m, $zdjecia, $tytul, $o ),

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

		$response = $istniejaca
			? Dawmac_Allegro_Client::patch( "/sale/product-offers/{$istniejaca}", $b['offer'] )
			: Dawmac_Allegro_Client::post( '/sale/product-offers', $b['offer'] );

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

	/** ID oferty przypisanej do produktu albo null. */
	public static function offer_id( int $product_id ): ?string {
		$id = get_post_meta( $product_id, self::META_OFFER, true );

		return $id ? (string) $id : null;
	}

	/**
	 * Tytul oferty. Sklepowy ma pierwszenstwo - zawiera pelna konfiguracje
	 * schodkowa ("8.5J ET25 + 10J ET43"), ktorej parametry Allegro nie udzwigna.
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
	 * x 4 szt., a zestaw schodkowy 2 pozycje x 2 szt.
	 *
	 * WLASNY PRODUKT: gdy dopasowania brak albo jest niepelne. Wtedy musimy
	 * podac wszystko sami, razem z GPSR - bez producenta odpowiedzialnego
	 * i informacji o bezpieczenstwie oferta nie przejdzie walidacji.
	 *
	 * Niepelne dopasowanie celowo traktujemy jak brak: podpiecie polowy
	 * zestawu schodkowego pod pozycje katalogowa oznacza, ze kupujacy widzi
	 * na stronie produktu co innego, niz dostanie.
	 */
	private static function product_set( array $dane, array $m, array $zdjecia, string $tytul, array $o ): array {
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

		return [ [
			'product' => [
				'name'       => $tytul,
				'category'   => [ 'id' => $o['kategoria'] ],
				'parameters' => $m['produkt'],
				'images'     => $zdjecia,
			],
			'quantity' => [ 'value' => 1 ],
		] + self::gpsr( $dane, $o ) ];
	}

	/**
	 * Ile zdjec produktu zmiesci sie w galerii.
	 *
	 * Allegro liczy razem galerie i grafiki opisu, a limit to 16. Odejmujemy
	 * banery szablonu, ktore realnie zostaly wgrane - te niewgrane i tak
	 * nie trafia do opisu, wiec nie zajmuja miejsca.
	 */
	private static function limit_galerii( array $config ): int {
		$banery = 0;

		foreach ( array_keys( (array) ( $config['images'] ?? [] ) ) as $klucz ) {
			if ( Dawmac_Allegro_Images::template_image( (string) $klucz ) ) {
				++$banery;
			}
		}

		return max( 1, 16 - $banery );
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
