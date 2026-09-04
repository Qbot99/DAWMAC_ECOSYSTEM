<?php
/**
 * Audyt wystawionych ofert.
 *
 * Narzedzie CELOWO nie korzysta z Dawmac_Allegro_Mapper ani Product_Data przy
 * ustalaniu wartosci oczekiwanych - rozbiera tytul wlasnym parserem i czyta
 * atrybuty wprost z bazy. Gdyby uzywalo tego samego kodu co budowanie oferty,
 * powtorzyloby jego bledy i potwierdzilo samo siebie.
 *
 * Uruchomienie:
 *   wp eval-file tools/audyt.php            - wszystkie oferty
 *   wp eval-file tools/audyt.php aktywne    - tylko wystawione
 *
 * @package dawmac-allegro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Frazy, ktorych Allegro nie dopuszcza w opisie oferty. */
const AUDYT_ZAKAZANE = [
	'wysyłk', 'wysylk', 'dostaw', 'kurier', 'paczkomat', 'inpost',
	'zwrot', 'reklamacj', 'gwarancj', 'rękojmi', 'rekojmi',
	'płatnoś', 'platnos', 'przelew', 'raty', 'faktur',
	'telefon', 'tel.', 'e-mail', 'email', '@', 'www.', 'http',
	'nie dołączamy', 'nie dolaczamy',
];

/** Tytul -> czesci techniczne. Wlasny parser, niezalezny od wtyczki. */
function audyt_z_tytulu( string $tytul ): array {
	$t = html_entity_decode( $tytul, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	$t = str_replace( [ '″', '”', '’’', '×' ], [ '"', '"', '"', 'x' ], $t );

	$out = [ 'srednica' => null, 'pary' => [], 'rozstaw' => [] ];

	if ( preg_match( '/(\d{2})\s*"/u', $t, $m ) ) {
		$out['srednica'] = $m[1] . '"';
	}

	if ( preg_match_all( '/(\d+(?:[.,]\d+)?)\s*J?\s*ET\s*(-?\d+)/iu', $t, $m, PREG_SET_ORDER ) ) {
		foreach ( $m as $x ) {
			$out['pary'][] = [
				'szerokosc' => number_format( (float) str_replace( ',', '.', $x[1] ), 1, '.', '' ),
				'et'        => (string) (int) $x[2],
			];
		}
	}

	if ( preg_match_all( '/(\d)\s*x\s*(\d{2,3}(?:[.,]\d)?)/iu', $t, $m, PREG_SET_ORDER ) ) {
		foreach ( $m as $x ) {
			$out['rozstaw'][] = $x[1] . 'x' . str_replace( ',', '.', $x[2] );
		}
	}

	return $out;
}

/** Atrybut wprost z indeksu filtrow, z pominieciem warstwy wtyczki. */
function audyt_atrybut( int $pid, string $taxonomy ): array {
	global $wpdb;

	return $wpdb->get_col(
		$wpdb->prepare(
			"SELECT value_label FROM {$wpdb->prefix}dawmac_filter_index
			 WHERE product_id = %d AND attribute = %s",
			$pid,
			$taxonomy
		)
	);
}

function audyt_liczba( string $s ): string {
	$n = preg_replace( '/[^0-9.,]/', '', $s ) ?? '';

	return rtrim( rtrim( str_replace( ',', '.', $n ), '0' ), '.' );
}

/** Sprowadza opis barwy do grupy - do porownan miedzy jezykami i slownikami. */
function audyt_barwa( string $t ): ?string {
	$t = mb_strtolower( $t, 'UTF-8' );

	$grupy = [
		'bronze'   => [ 'bronze', 'brąz', 'braz', 'brons' ],
		'titanium' => [ 'titanium', 'tytan', 'titan' ],
		'graphite' => [ 'graphite', 'grafit', 'gun metal', 'gunmetal' ],
		'silver'   => [ 'silver', 'srebr', 'silber' ],
		'gold'     => [ 'gold', 'złot', 'zlot' ],
		'white'    => [ 'white', 'biał', 'bial', 'weiss' ],
		'red'      => [ 'red', 'czerwon', 'candy' ],
		'blue'     => [ 'blue', 'niebiesk', 'granat' ],
		'grey'     => [ 'grey', 'gray', 'szar' ],
		'black'    => [ 'black', 'czarn', 'schwarz' ],
	];

	foreach ( $grupy as $nazwa => $slowa ) {
		foreach ( $slowa as $s ) {
			if ( str_contains( $t, $s ) ) {
				return $nazwa;
			}
		}
	}

	return null;
}

/**
 * Czy wykonczenie ze sklepu ma odpowiednik w slowniku Allegro (202913).
 * Gdy nie ma - puste pole na ofercie jest poprawne, a nie brakiem.
 */
function audyt_slownikowe( string $wykonczenie ): ?string {
	$mapa = [
		'bronze matt' => 'BRONZE MATT', 'matt bronze' => 'BRONZE MATT', 'bronze' => 'BRONZE',
		'hyper silver' => 'HS - hyper silver', 'gun metal' => 'GM - gun metal',
		'matt black' => 'BM - czarny mat', 'black matt' => 'BM - czarny mat',
		'gold' => 'GOLD - złote', 'silver' => 'SI - srebrne',
		'black' => 'BL - czarne', 'white' => 'W - białe',
	];

	$t = mb_strtolower( trim( $wykonczenie ), 'UTF-8' );

	foreach ( $mapa as $szukaj => $wartosc ) {
		if ( str_contains( $t, $szukaj ) ) {
			return $wartosc;
		}
	}

	return null;
}

/** @return array{0:array<string,bool>,1:array<string,string>} kontrole, szczegoly */
function audyt_oferta( int $pid, string $oid ): array {
	global $wpdb;

	$p = wc_get_product( $pid );
	$o = Dawmac_Allegro_Client::get( "/sale/product-offers/{$oid}" );

	if ( ! $p || is_wp_error( $o ) ) {
		return [ [ 'oferta czytelna' => false ], [ 'błąd' => is_wp_error( $o ) ? $o->get_error_message() : 'brak produktu' ] ];
	}

	$par = [];
	foreach ( ( $o['productSet'][0]['product']['parameters'] ?? [] ) as $x ) {
		$par[ $x['name'] ?? $x['id'] ] = trim( implode( ', ', $x['values'] ?? [] ) );
	}

	$opis = '';
	foreach ( ( $o['description']['sections'] ?? [] ) as $s ) {
		foreach ( $s['items'] as $it ) {
			if ( 'TEXT' === $it['type'] ) {
				$opis .= ' ' . $it['content'];
			}
		}
	}
	$opis_txt = mb_strtolower( html_entity_decode( wp_strip_all_tags( $opis ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );

	$tyt   = audyt_z_tytulu( $p->get_name() );
	$przod = $tyt['pary'][0] ?? null;

	$sklep = [
		'srednica'  => audyt_atrybut( $pid, 'pa_srednica' ),
		'szerokosc' => audyt_atrybut( $pid, 'pa_szerokosc' ),
		'et'        => audyt_atrybut( $pid, 'pa_et' ),
		'rozstaw'   => audyt_atrybut( $pid, 'pa_rozstaw' ),
		'bore'      => audyt_atrybut( $pid, 'pa_bore' ),
		'kolor'     => audyt_atrybut( $pid, 'pa_kolor' ),
		'kategoria' => audyt_atrybut( $pid, 'pa_kategoria-koloru' ),
		'producent' => audyt_atrybut( $pid, 'pa_producent' ),
		'model'     => audyt_atrybut( $pid, 'pa_model' ),
	];

	$obrazy_opisu = [];
	foreach ( ( $o['description']['sections'] ?? [] ) as $s ) {
		foreach ( $s['items'] as $it ) {
			if ( 'IMAGE' === $it['type'] ) {
				$obrazy_opisu[] = $it['url'];
			}
		}
	}

	$zakazane = [];
	foreach ( AUDYT_ZAKAZANE as $f ) {
		if ( str_contains( $opis_txt, $f ) ) {
			$zakazane[] = $f;
		}
	}

	$kolor_grupa = audyt_barwa( $sklep['kolor'][0] ?? '' );

	$k = [
		// --- handlowe ---
		'tytuł zgodny ze sklepem'      => $o['name'] === $p->get_name(),
		'tytuł ≤ 75 znaków'            => mb_strlen( $o['name'] ) <= 75,
		'cena zgodna'                  => (float) ( $o['sellingMode']['price']['amount'] ?? -1 ) === (float) $p->get_price(),
		'stan magazynowy zgodny'       => (int) ( $o['stock']['available'] ?? -1 ) === (int) $p->get_stock_quantity(),
		'komplet 4 felg'               => 4 === (int) ( $o['productSet'][0]['quantity']['value'] ?? 0 ),
		'kategoria felg'               => '257711' === (string) ( $o['category']['id'] ?? '' ),

		// --- wymiary: Allegro kontra TYTUŁ ---
		'średnica = tytuł'             => null !== $tyt['srednica'] && audyt_liczba( $par['Średnica felgi'] ?? '' ) === audyt_liczba( $tyt['srednica'] ),
		'szerokość = przednia z tytułu' => null !== $przod && audyt_liczba( $par['Szerokość felgi'] ?? '' ) === audyt_liczba( $przod['szerokosc'] ),
		'ET = przednie z tytułu'       => null !== $przod && ( $par['Odsadzenie (ET)'] ?? '' ) === $przod['et'],
		'rozstaw = tytuł'              => ! empty( $tyt['rozstaw'] ) && in_array( str_replace( ' ', '', $par['Rozstaw śrub'] ?? '' ), $tyt['rozstaw'], true ),

		// --- wymiary: Allegro kontra SKLEP ---
		'średnica = sklep'             => audyt_liczba( $par['Średnica felgi'] ?? '' ) === audyt_liczba( $sklep['srednica'][0] ?? '' ),
		'ET jest wśród wartości sklepu' => in_array( $par['Odsadzenie (ET)'] ?? '', array_map( 'trim', $sklep['et'] ), true ),
		'otwór = sklep'                => '' !== ( $par['Otwór centralny'] ?? '' )
			&& audyt_liczba( $par['Otwór centralny'] ) === audyt_liczba( $sklep['bore'][0] ?? '' ),
		'producent = sklep'            => ( $par['Producent felg'] ?? '' ) === trim( $sklep['producent'][0] ?? '' ),
		'model = sklep'                => ( $par['Model'] ?? '' ) === trim( $sklep['model'][0] ?? '' ),

		// --- kolor i wykończenie ---
		'kolor wypełniony'             => '' !== ( $par['Kolor'] ?? '' ),

		// Kolor na ofercie pochodzi z pa_kategoria-koloru, nie z pa_kolor -
		// pierwsza wersja audytu porownywala go z pa_kolor i zglaszala
		// "Brushed Titanium" kontra "grafitowy" jako blad, choc to sprzedawca
		// zaklasyfikowal te felge jako grafitowa i mial racje.
		'kolor zgodny z kategorią sklepu' => null !== audyt_barwa( $sklep['kategoria'][0] ?? '' )
			&& audyt_barwa( $par['Kolor'] ?? '' ) === audyt_barwa( $sklep['kategoria'][0] ?? '' ),

		// Wykonczenie jest nieobowiazkowe i slownik Allegro nie zna wszystkich
		// wykonczen (nie ma zadnego z tytanem). Bledem jest dopiero sytuacja,
		// gdy wykonczenie DA sie odwzorowac, a mimo to pola nie ma.
		'wykończenie: brak = brak w słowniku' => '' !== ( $par['Wykończenie'] ?? '' )
			|| null === audyt_slownikowe( $sklep['kolor'][0] ?? '' ),
		'wykończenie zgodne z barwą'   => '' === ( $par['Wykończenie'] ?? '' )
			|| ( null !== $kolor_grupa && audyt_barwa( $par['Wykończenie'] ) === $kolor_grupa ),
		'wykończenie sklepu w opisie'  => '' !== ( $sklep['kolor'][0] ?? '' )
			&& str_contains( $opis_txt, mb_strtolower( $sklep['kolor'][0] ) ),
		'pa_kolor spójny z tytułem'    => Dawmac_Allegro_Product_Data::finish_zgodne( $sklep['kolor'][0] ?? '', $p->get_name() ),

		// --- opis ---
		'brak zakazanych fraz'         => [] === $zakazane,
		'sekcja "W zestawie"'          => str_contains( $opis_txt, 'w zestawie' ),
		'pięć pozycji zestawu'         => 5 === substr_count( $opis, '<li>' ) - substr_count( $opis, '<b>' ) + substr_count( $opis, '<li><b>' ) ? true : str_contains( $opis_txt, 'komplet pierścieni centrujących' ),
		'opis wymienia średnicę'       => str_contains( $opis_txt, audyt_liczba( $tyt['srednica'] ?? '' ) ),

		// --- GPSR ---
		'GPSR producent'               => ! empty( $o['productSet'][0]['responsibleProducer']['id'] ),
		'GPSR bezpieczeństwo'          => mb_strlen( $o['productSet'][0]['safetyInformation']['description'] ?? '' ) > 100,

		// --- zdjęcia ---
		'ma zdjęcia'                   => count( $o['images'] ?? [] ) > 0,
		'zdjęcia z serwerów Allegro'   => [] === array_filter( $o['images'] ?? [], static fn( $u ): bool => ! str_contains( $u, 'allegroimg.com' ) ),
		'obrazy opisu są w galerii'    => [] === array_diff( $obrazy_opisu, $o['images'] ?? [] ),
		'limit 16 obrazów'             => count( array_unique( array_merge( $o['images'] ?? [], $obrazy_opisu ) ) ) <= 16,

		// --- dostawa ---
		'cennik dostawy'               => ! empty( $o['delivery']['shippingRates']['id'] ),
		'polityka zwrotów'             => ! empty( $o['afterSalesServices']['returnPolicy']['id'] ),
		'warunki reklamacji'           => ! empty( $o['afterSalesServices']['impliedWarranty']['id'] ),
	];

	// Zestaw mieszany: zdanie w opisie i zgodnosc obu osi z tytulem.
	if ( count( $tyt['pary'] ) > 1 ) {
		$k['zdanie o zestawie mieszanym'] = str_contains( $opis_txt, 'zestaw mieszany' );

		$obie = true;
		foreach ( $tyt['pary'] as $para ) {
			if ( ! str_contains( $opis_txt, 'et' . $para['et'] ) && ! str_contains( $opis_txt, 'et ' . $para['et'] ) ) {
				$obie = false;
			}
		}
		$k['opis podaje ET obu osi'] = $obie;
	}

	$szczegoly = [
		'tytuł'       => $o['name'],
		'zakazane'    => implode( ', ', $zakazane ),
		'pa_kolor'    => $sklep['kolor'][0] ?? '(brak)',
		'wykończenie' => $par['Wykończenie'] ?? '(brak)',
		'kolor'       => $par['Kolor'] ?? '(brak)',
		'ET oferty'   => $par['Odsadzenie (ET)'] ?? '(brak)',
		'ET tytułu'   => implode( ' + ', array_column( $tyt['pary'], 'et' ) ),
	];

	return [ $k, $szczegoly ];
}

// ---------------------------------------------------------------- uruchomienie

global $wpdb;

$tylko_aktywne = in_array( 'aktywne', (array) ( $args ?? [] ), true );

$rows = $wpdb->get_results(
	"SELECT post_id, meta_value FROM {$wpdb->postmeta}
	 WHERE meta_key = '_dawmac_allegro_offer_id' ORDER BY post_id"
);

$pomin  = [ 188599, 188885, 201208, 201216, 201368 ];
$wynik  = [ 'ok' => 0, 'zle' => 0 ];
$awarie = [];

foreach ( $rows as $r ) {
	$pid = (int) $r->post_id;

	if ( in_array( $pid, $pomin, true ) ) {
		continue;
	}

	$o = Dawmac_Allegro_Client::get( "/sale/product-offers/{$r->meta_value}" );

	if ( is_wp_error( $o ) ) {
		continue;
	}

	$status = $o['publication']['status'] ?? '?';

	if ( $tylko_aktywne && 'ACTIVE' !== $status ) {
		continue;
	}

	[ $k, $d ] = audyt_oferta( $pid, (string) $r->meta_value );

	$zle = array_keys( array_filter( $k, static fn( $v ): bool => ! $v ) );

	printf(
		"%-9s %-12s %s\n   %s\n",
		$status,
		$r->meta_value,
		mb_substr( $d['tytuł'] ?? '', 0, 52 ),
		$zle
			? sprintf( 'NIEZGODNE (%d z %d): %s', count( $zle ), count( $k ), implode( ' · ', $zle ) )
			: sprintf( 'wszystkie %d kontroli przeszło', count( $k ) )
	);

	if ( $zle ) {
		++$wynik['zle'];
		$awarie[ (string) $r->meta_value ] = [ $zle, $d ];
	} else {
		++$wynik['ok'];
	}
}

printf( "\n=== bez zastrzeżeń: %d   z uwagami: %d ===\n", $wynik['ok'], $wynik['zle'] );

foreach ( $awarie as $oid => [ $zle, $d ] ) {
	printf( "\n%s\n", $oid );
	foreach ( $d as $klucz => $wartosc ) {
		if ( '' !== $wartosc ) {
			printf( "   %-13s %s\n", $klucz, mb_substr( (string) $wartosc, 0, 60 ) );
		}
	}
}
