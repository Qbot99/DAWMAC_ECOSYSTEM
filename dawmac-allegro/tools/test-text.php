<?php
/**
 * Testy sanitizera i szablonu. Bez frameworka - to ma sie odpalac wszedzie,
 * takze na hostingu, gdzie nie ma composera.
 *
 *   php tools/test-text.php     - kod wyjscia 1 przy pierwszym bledzie
 *
 * Kazdy przypadek pochodzi z realnego ksztaltu danych w sklepie albo
 * z reguly, ktorej zlamanie konczy sie odrzuceniem oferty przez Allegro.
 */

declare( strict_types=1 );

require __DIR__ . '/../includes/class-text.php';
require __DIR__ . '/../includes/class-template.php';
require __DIR__ . '/../includes/class-client.php';
require __DIR__ . '/../includes/class-offer.php';
require __DIR__ . '/../includes/class-product-data.php';

if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( $s ) {
		return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' );
	}
}

$pass = 0;
$fail = 0;

function check( string $name, $expected, $actual ): void {
	global $pass, $fail;

	if ( $expected === $actual ) {
		++$pass;
		return;
	}

	++$fail;
	echo "  BLAD: {$name}\n";
	echo "    oczekiwano: " . var_export( $expected, true ) . "\n";
	echo "    otrzymano:  " . var_export( $actual, true ) . "\n";
}

function contains( string $name, string $needle, string $haystack, bool $want = true ): void {
	global $pass, $fail;

	if ( str_contains( $haystack, $needle ) === $want ) {
		++$pass;
		return;
	}

	++$fail;
	$slowo = $want ? 'brakuje' : 'zostalo';
	echo "  BLAD: {$name} - {$slowo} \"{$needle}\"\n";
	echo "    w: {$haystack}\n";
}

echo "SANITIZER\n";

// --- Znaczniki -------------------------------------------------------

check(
	'strong -> b',
	'<p>Felga <b>kuta</b></p>',
	Dawmac_Allegro_Text::clean( '<p>Felga <strong>kuta</strong></p>' )
);

check(
	'niedozwolony tag znika, tresc zostaje',
	'<p>Felga kuta</p>',
	Dawmac_Allegro_Text::clean( '<p><span class="x">Felga</span> <em>kuta</em></p>' )
);

check(
	'atrybuty zdejmowane z dozwolonych tagow',
	'<p>Tekst</p>',
	Dawmac_Allegro_Text::clean( '<p class="lead" style="color:red">Tekst</p>' )
);

check(
	'h3-h6 spadaja do h2',
	'<h2>Parametry</h2>',
	Dawmac_Allegro_Text::clean( '<h4>Parametry</h4>' )
);

check(
	'br staje sie granica akapitu',
	'<p>Pierwsza</p><p>Druga</p>',
	Dawmac_Allegro_Text::clean( '<p>Pierwsza<br>Druga</p>' )
);

check(
	'naglowka nie wolno formatowac',
	'<h1>Felga kuta</h1>',
	Dawmac_Allegro_Text::clean( '<h1>Felga <b>kuta</b></h1>' )
);

check(
	'tabela znika, tresc zostaje w akapicie',
	'<p>Waga 10,2 kg</p>',
	Dawmac_Allegro_Text::clean( '<table><tr><td>Waga 10,2 kg</td></tr></table>' )
);

check(
	'goly & staje sie encja',
	'<p>Felgi &amp; opony</p>',
	Dawmac_Allegro_Text::clean( '<p>Felgi & opony</p>' )
);

check(
	'poprawna encja zostaje nietknieta',
	'<p>Felgi &amp; opony</p>',
	Dawmac_Allegro_Text::clean( '<p>Felgi &amp; opony</p>' )
);

check(
	'pusty akapit po wycieciu znika',
	'',
	Dawmac_Allegro_Text::clean( '<p>https://dawmac.pl</p>' )
);

// --- Namiary poza Allegro --------------------------------------------

echo "\nNAMIARY (powod odrzucania ofert)\n";

$cases = [
	'link w zdaniu'      => '<p>Zapraszamy na www.dawmac.pl</p>',
	'link https'         => '<p>Kup taniej na https://dawmac.pl/felgi</p>',
	'e-mail'             => '<p>Kontakt: biuro@dawmac.pl</p>',
	'telefon z etykieta' => '<p>tel. 601 234 567</p>',
	'telefon +48'        => '<p>+48 601 234 567</p>',
	'gola domena'        => '<p>Sprawdz dawmac.pl</p>',
];

foreach ( $cases as $name => $html ) {
	$out = Dawmac_Allegro_Text::clean( $html );

	contains( $name, 'dawmac', $out, false );
	contains( $name . ' (cyfry telefonu)', '601', $out, false );
}

// Kluczowy przypadek: mail nie moze zostawic kikuta "biuro@".
contains(
	'mail nie zostawia kikuta',
	'@',
	Dawmac_Allegro_Text::clean( '<p>Kontakt: biuro@dawmac.pl, tel. 601 234 567</p>' ),
	false
);

// Lokalizacja magazynowa to dane wewnetrzne - README dawmac-filters
// potwierdza, ze siedza w opisach produktow.
$mag = Dawmac_Allegro_Text::clean( '<p>Felga kuta 6061-T6. Lokalizacja magazynowa 42.L / NHB</p>' );
contains( 'lokalizacja magazynowa wycieta', '42.L', $mag, false );
contains( 'lokalizacja magazynowa bez kikuta', 'Lokalizacja', $mag, false );
contains( 'tresc produktowa zostaje', '6061-T6', $mag );

// --- Falszywe alarmy --------------------------------------------------

echo "\nFALSZYWE ALARMY (katalog felg jest pelen liczb)\n";

$rozmiary = [
	'rozmiar opony'    => '<p>Opona 225 45 17 w rozmiarze letnim</p>',
	'rozmiar felgi'    => '<p>Felga 8.5x19 ET35</p>',
	'rozstaw podwojny' => '<p>Pasuje do 5x112 oraz 5x120</p>',
	'model z kropka'   => '<p>Model 3SDM 0.01 w rozmiarze 19 cali</p>',
	'kod modelu'       => '<p>Felga HX022 oraz SSA03</p>',
];

foreach ( $rozmiary as $name => $html ) {
	$out   = Dawmac_Allegro_Text::clean( $html );
	$plain = Dawmac_Allegro_Text::plain( $html );

	check( $name . ' - tresc nietknieta', $plain, Dawmac_Allegro_Text::plain( $out ) );
}

// --- Szablon ----------------------------------------------------------

echo "\nSZABLON\n";

$config   = require __DIR__ . '/../config/brand.php';
$template = new Dawmac_Allegro_Template(
	$config,
	static fn( string $k ): string => 'https://a.allegroimg.com/original/TEST/' . $k
);

$felga = [
	'title'       => 'Dawmac Forged FM115 9.5x19',
	'producent'   => 'Dawmac Forged',
	'model'       => 'FM115',
	'srednica'    => '19',
	'szerokosc'   => '9.5',
	'rozstaw'     => [ '5x112', '5x120' ],
	'et'          => '35',
	'liczba_srub' => 5,
	'kategoria'   => 'felgi',
	'opis'        => '<p>Felga kuta.</p>',
	'image'       => 'https://a.allegroimg.com/original/TEST/photo',
];

$desc = $template->build( $felga );

check( 'opis przechodzi walidacje', [], Dawmac_Allegro_Template::validate( $desc ) );

check(
	'tytul felgi',
	'Dawmac Forged FM115 9.5x19 5x112/5x120 ET35',
	$template->build_offer_title( $felga )
);

$opona = [
	'title'           => 'Continental SportContact 2',
	'producent'       => 'Continental',
	'model'           => 'SportContact2',
	'szerokosc_opony' => '225',
	'profil'          => '45',
	'srednica_opony'  => '17',
	'kategoria'       => 'opony',
	'opis'            => '<p>Opona letnia.</p>',
];

check(
	'tytul opony ma rozmiar, nie ma ET',
	'Continental SportContact2 225/45 R17',
	$template->build_offer_title( $opona )
);

// Outlet i inny towar nie-nowy: zadnego "nowe" w opisie, za to punkt
// o outlecie. Zwykly komplet zostaje bez zmian.
$tekst_opisu = static function ( array $d ): string {
	$t = '';
	foreach ( $d['sections'] as $s ) {
		foreach ( $s['items'] as $it ) {
			$t .= 'TEXT' === $it['type'] ? $it['content'] : '';
		}
	}
	return $t;
};

$nowa = $tekst_opisu( $template->build( $felga + [ 'adnotacje' => [ 'stan' => 'Nowy', 'outlet' => false, 'dekielki' => true ] ] ) );
contains( 'nowa: cztery nowe felgi', 'cztery nowe felgi', $nowa );
contains( 'nowa: fabrycznie nowe', 'fabrycznie nowe', $nowa );
contains( 'nowa: bez sekcji o stanie', 'Zanim kupisz', $nowa, false );

$outlet = $tekst_opisu( $template->build( $felga + [ 'adnotacje' => [ 'stan' => 'Używany', 'outlet' => true, 'dekielki' => true ] ] ) );
contains( 'outlet: bez "nowe felgi"', 'nowe felgi', $outlet, false );
contains( 'outlet: bez "fabrycznie nowe"', 'fabrycznie nowe', $outlet, false );
contains( 'outlet: zostaje "cztery felgi"', 'cztery felgi aluminiowe', $outlet );
contains( 'outlet: punkt o outlecie', 'Felgi z outletu', $outlet );
contains( 'outlet: bez zdania o eksploatacji', 'eksploatowane', $outlet, false );

$regen = $tekst_opisu( $template->build( $felga + [ 'adnotacje' => [ 'stan' => 'Regenerowany', 'outlet' => false, 'dekielki' => true ] ] ) );
contains( 'regenerowane: bez "nowe felgi"', 'nowe felgi', $regen, false );
contains( 'regenerowane: punkt o regeneracji', 'Felgi regenerowane', $regen );

// Uwagi sprzedawcy z prawdziwych opisow sklepowych (outlet, 1.10.2026).
$uwagi = [
	'OEMS: kierunki i kolory' => [
		"OEMS IFG10 19'' 8,5J ET32 5x112 bore 66,56 Komplet felg wyprzedażowych. 1 felga kierunek prawy, 3 felgi kierunek lewy - widoczne na zdjęciach. 3 felgi w kolorze Silver, 1 w kolorze Silver Machined (polerowany front) Brak dakielków w zestawie. Felgi na Magazynie ! ZAPRASZAMY ! 602.K / PN2 Cena podana w ogłoszeniu jest ceną brutto",
		'Komplet felg wyprzedażowych. 1 felga kierunek prawy, 3 felgi kierunek lewy - widoczne na zdjęciach. 3 felgi w kolorze Silver, 1 w kolorze Silver Machined (polerowany front) Brak dakielków w zestawie.',
	],
	'Arceo: rozne kolory' => [
		'Arceo Valencia 19" 9,5J ET40 5x112 bore 73,1 Felgi w różnych kolorach, do malowania we własnym zakresie. Felgi proste. Brak uszkodzeń, wad i ubytków. Brak dekielków. Max Load 750 kg Felgi na magazynie. ZAPRASZAMY! 599.K / PN1',
		'Felgi w różnych kolorach, do malowania we własnym zakresie. Felgi proste. Brak uszkodzeń, wad i ubytków. Brak dekielków.',
	],
	'Arceo: kolor przed zdaniem' => [
		'Arceo Valencia 18" 8,5J ET35 5x108 bore 73,1 Kolor: Silver Diamond Komplet felg wyprzedażowych. Felgi proste, brak dekielków w zestawie. Waga Felgi: 10,6kg Max Load 700kg Felgi na magazynie. ZAPRASZAMY! 601.K / PN6',
		'Komplet felg wyprzedażowych. Felgi proste, brak dekielków w zestawie.',
	],
	'JR37: tylko kolor - nic' => [
		'Japan Racing JR37 SET-JR#343 19" 8,5J ET35 5x112 bore 66,6 Kolor: Gloss Black Max Load 690 kg Felgi na magazynie. ZAPRASZAMY! 961.K / NH5 JROUTD',
		'',
	],
	'CVR8: rysy, podwojna kropka' => [
		'Concaver CVR8 SET-CVR#101 19" 8,5J ET40 5x112 bore 72,6 Kolor: Matt Black Max Load 725 kg Felgi mają drobne rysy w pobliżu otworów na śruby.. Felgi na magazynie. ZAPRASZAMY 954.K / NH11 1CVROUTD',
		'Felgi mają drobne rysy w pobliżu otworów na śruby.',
	],
	'ST3: kolor na koncu' => [
		'Stuttgart ST3 (01W) 19" 8,5J ET38 bore 72,6 3 Felgi wyprzedażowe w rozstawie 5x120, 1 felga w rozstawie 5x112. W cenie felg 2 dystanse zmieniające rozstaw. Kolor: Silver Felga na magazynie! ZAPRASZAMY! 504.G / NH9',
		'3 Felgi wyprzedażowe w rozstawie 5x120, 1 felga w rozstawie 5x112. W cenie felg 2 dystanse zmieniające rozstaw.',
	],
];

foreach ( $uwagi as $nazwa => [ $wej, $want ] ) {
	check( "uwagi: {$nazwa}", $want, Dawmac_Allegro_Product_Data::uwagi_z_tekstu( $wej ) );
}

$z_uwagami = $tekst_opisu( $template->build( $felga + [ 'adnotacje' => [ 'stan' => 'Używany', 'outlet' => true, 'dekielki' => false, 'uwagi' => 'Felgi w różnych kolorach, do malowania we własnym zakresie.' ] ] ) );
contains( 'outlet: uwagi sprzedawcy w opisie', 'Felgi w różnych kolorach, do malowania we własnym zakresie.', $z_uwagami );
$bez_odchylen = $tekst_opisu( $template->build( $felga + [ 'adnotacje' => [ 'stan' => 'Nowy', 'outlet' => false, 'dekielki' => true, 'uwagi' => 'Felgi kute.' ] ] ) );
contains( 'zwykly komplet: bez uwag sprzedawcy', 'Od sprzedawcy', $bez_odchylen, false );

$mieszany = [ 'szerokosc' => [ '10J', '11J' ], 'et' => [ '35', '40' ] ] + $felga;
contains( 'zestaw 2+2: zdanie o przodzie i tyle', 'zestaw mieszany', $tekst_opisu( $template->build( $mieszany ) ) );
$nietypowy = $tekst_opisu( $template->build( $mieszany + [ 'adnotacje' => [ 'stan' => 'Używany', 'outlet' => true, 'dekielki' => true, 'niejednorodny' => '1 felga o szerokości 10J, 3 felgi o szerokości 11J', 'uwagi' => '1 felga o szerokości 10J, 3 felgi o szerokości 11J.' ] ] ) );
contains( 'zestaw 1+3: bez zdania o przodzie i tyle', 'zestaw mieszany', $nietypowy, false );
contains( 'zestaw 1+3: uwagi sprzedawcy', '1 felga o szerokości 10J, 3 felgi o szerokości 11J.', $nietypowy );

// Straznik przed publikacja: stan kontra opis.
$oferta_z = static fn( string $stan, string $nazwa, string $opis ): array => [
	'name'        => $nazwa,
	'parameters'  => [ [ 'name' => 'Stan', 'values' => [ $stan ] ], [ 'name' => 'Liczba felg w ofercie', 'values' => [ '4 szt.' ] ] ],
	'description' => [ 'sections' => [ [ 'items' => [ [ 'type' => 'TEXT', 'content' => '<h2>W zestawie</h2>' . $opis ] ] ] ] ],
	'productSet'  => [ [
		'product'             => [ 'parameters' => [ [ 'name' => 'Otwór centralny', 'values' => [ '66,6' ] ] ] ],
		'responsibleProducer' => [ 'id' => 'x' ],
		'safetyInformation'   => [ 'description' => 'x' ],
	] ],
];
check( 'zarzuty: nowa i nowe felgi - czysto', [], Dawmac_Allegro_Offer::zarzuty( $oferta_z( 'Nowy', 'JR37 19"', 'cztery nowe felgi' ) ) );
check( 'zarzuty: uzywana z "nowe felgi"', [ 'opis mówi o nowych felgach, a stan to Używany' ], Dawmac_Allegro_Offer::zarzuty( $oferta_z( 'Używany', 'JR37 19"', 'cztery nowe felgi' ) ) );
check( 'zarzuty: outlet jako nowy', [ 'outlet ze stanem "Nowy"' ], Dawmac_Allegro_Offer::zarzuty( $oferta_z( 'Nowy', 'Outlet Japan Racing JR37 19"', 'cztery felgi' ) ) );
check( 'zarzuty: outlet jako uzywany - czysto', [], Dawmac_Allegro_Offer::zarzuty( $oferta_z( 'Używany', 'Outlet Japan Racing JR37 19"', 'cztery felgi' ) ) );

// Zadna sekcja nie moze miec wiecej niz dwoch itemow.
foreach ( $desc['sections'] as $i => $section ) {
	check( "sekcja {$i} ma 1-2 itemy", true, count( $section['items'] ) >= 1 && count( $section['items'] ) <= 2 );
}

// Brak grafiki = sekcja banera po prostu nie powstaje, zamiast psuc oferte.
//
// Domyslna konfiguracja nie ma juz banerow, wiec test buduje wlasna -
// inaczej sprawdzalby, ze zero sekcji to mniej niz zero.
$z_banerem = $config;
$z_banerem['images'] = [ 'banner_top' => 'assets/banners/dawmac-banner-top.jpg' ];
$z_banerem['layout'] = array_merge( [ 'banner_top' ], $config['layout'] );

$jest = ( new Dawmac_Allegro_Template( $z_banerem, static fn( string $k ): string => 'https://a.allegroimg.com/original/TEST/' . $k ) )->build( $felga );
$brak = ( new Dawmac_Allegro_Template( $z_banerem, static fn( string $k ): ?string => null ) )->build( $felga );

check( 'z banerem opis jest poprawny',     [], Dawmac_Allegro_Template::validate( $jest ) );
check( 'bez grafiki opis dalej poprawny',  [], Dawmac_Allegro_Template::validate( $brak ) );
check( 'bez grafiki sekcji jest mniej', true, count( $brak['sections'] ) < count( $jest['sections'] ) );

// Dwa bloki TEXT obok siebie - Allegro odrzuca to bledem 422.
$dwa_teksty = [ 'sections' => [ [ 'items' => [
	[ 'type' => 'TEXT', 'content' => '<p>Parametry techniczne</p>' ],
	[ 'type' => 'TEXT', 'content' => '<p>Dobór do auta</p>' ],
] ] ] ];
check( 'dwa TEXT w sekcji zlapane', 1, count( Dawmac_Allegro_Template::validate( $dwa_teksty ) ) );

// TEXT + IMAGE jest poprawne.
$tekst_obraz = [ 'sections' => [ [ 'items' => [
	[ 'type' => 'TEXT', 'content' => '<p>Parametry</p>' ],
	[ 'type' => 'IMAGE', 'url' => 'https://a.allegroimg.com/original/TEST/x' ],
] ] ] ];
check( 'TEXT + IMAGE przechodzi', [], Dawmac_Allegro_Template::validate( $tekst_obraz ) );

// Treści zastrzeżone dla zakładek oferty. Allegro odrzuciło ofertę
// 18890951777 właśnie za nie, a kara idzie do zawieszenia konta.
$zakazane = [
	'wysyłka'    => '<p>Nadajemy w 48 godzin od zaksięgowania wpłaty.</p>',
	'dostawa'    => '<p>Dostawa kurierem na terenie kraju.</p>',
	'płatność'   => '<p>Możliwa płatność przelewem lub za pobraniem.</p>',
	'zwrot'      => '<p>14 dni na zwrot bez podania przyczyny.</p>',
	'reklamacja' => '<p>Reklamacje rozpatrujemy w 14 dni.</p>',
	'gwarancja'  => '<p>Udzielamy 24 miesięcy gwarancji.</p>',
	'paczkomat'  => '<p>Wysyłamy też do paczkomatu.</p>',
];

foreach ( $zakazane as $co => $html ) {
	$d = [ 'sections' => [ [ 'items' => [ [ 'type' => 'TEXT', 'content' => $html ] ] ] ] ];
	check( "zakazane w opisie: {$co}", true, count( Dawmac_Allegro_Template::validate( $d ) ) > 0 );
}

// Treść produktowa nie może wpadać w te wzorce.
$dozwolone = [
	'dostępność'  => '<p>Felga dostępna w rozmiarze 8.5x19.</p>',
	'parametry'   => '<p>Rozstaw śrub 5x112, odsadzenie ET35.</p>',
	'wykończenie' => '<p>Wykończenie szczotkowany brąz, malowane proszkowo.</p>',
	'materiał'    => '<p>Kuta z bloku aluminium 6061-T6, waga 10,2 kg.</p>',
];

foreach ( $dozwolone as $co => $html ) {
	$d = [ 'sections' => [ [ 'items' => [ [ 'type' => 'TEXT', 'content' => $html ] ] ] ] ];
	check( "dozwolone w opisie: {$co}", [], Dawmac_Allegro_Template::validate( $d ) );
}

// Walidator ma lapac obrazek spoza Allegro.
$obcy = [ 'sections' => [ [ 'items' => [ [ 'type' => 'IMAGE', 'url' => 'https://dawmac.pl/foto.jpg' ] ] ] ] ];
check( 'obcy obrazek zlapany', 1, count( Dawmac_Allegro_Template::validate( $obcy ) ) );

echo "\nNAGLOWEK USER-AGENT (zly = zablokowany klucz API)\n";

// Generator Allegro dopuszcza spacje w nazwie i wersje typu "v1.0"
// albo "2026.06.24" - waski wzorzec odrzucalby poprawne naglowki.
$agents = [
	// Wartosc realnie wygenerowana przez narzedzie Allegro dla tej aplikacji.
	'Dawmac-Sklep/1.0.0 (+https://dawmac.pl)'                    => true,
	'Dawmac Sklep/1.0.0 (+https://dawmac.pl/integracja-allegro)' => true,
	'DawmacSklep/1.0.0 (+https://dawmac.pl)'                     => true,
	'DawmacSklep/v1.0 (+https://dawmac.pl)'                      => true,
	'DawmacSklep/2026.06.24 (+https://dawmac.pl)'                => true,
	'DawmacSklep/1.0.0 (+http://dawmac.pl)'                      => true,
	'Mozilla/5.0'                                                => false,
	'DawmacSklep'                                                => false,
	'DawmacSklep/1.0.0'                                          => false,
	'DawmacSklep (+https://dawmac.pl)'                           => false,
	'WordPress/6.4; https://dawmac.pl'                           => false,
	''                                                           => false,
];

foreach ( $agents as $value => $want ) {
	check(
		sprintf( 'user-agent %s', '' === $value ? '(pusty)' : $value ),
		$want,
		Dawmac_Allegro_Client::valid_user_agent( (string) $value )
	);
}

// Cennik dostawy: marka przed srednica, progi jak w cenniku wysylek sklepu.
// Progi celowo nie po kolei - kolejnosc w konfiguracji nie moze miec znaczenia.
$cenniki = [
	'marki'    => [ 'Japan Racing' => 'jr' ],
	'srednice' => [ 20 => 's20', 99 => 's21', 18 => 's18', 19 => 's19' ],
	'domyslny' => 'dom',
];
$dostawy = [
	'JR po marce, nie po srednicy'  => [ [ 'producent' => 'Japan Racing', 'srednica' => '22"' ], 'jr' ],
	'marka bez wielkosci liter'     => [ [ 'producent' => 'japan racing ', 'srednica' => '19"' ], 'jr' ],
	'15 cali'                       => [ [ 'producent' => 'Wrath Wheels', 'srednica' => '15"' ], 's18' ],
	'18 cali'                       => [ [ 'producent' => 'Wrath Wheels', 'srednica' => '18' ], 's18' ],
	'19 cali'                       => [ [ 'producent' => 'Wrath Wheels', 'srednica' => '19"' ], 's19' ],
	'20 cali'                       => [ [ 'producent' => 'Wrath Wheels', 'srednica' => '20"' ], 's20' ],
	'21 cali'                       => [ [ 'producent' => 'Wrath Wheels', 'srednica' => '21"' ], 's21' ],
	'24 cale'                       => [ [ 'producent' => 'Wrath Wheels', 'srednica' => '24"' ], 's21' ],
	'rozne srednice - wieksza'      => [ [ 'producent' => 'Wrath Wheels', 'srednica' => [ '19"', '20"' ] ], 's20' ],
	'brak srednicy'                 => [ [ 'producent' => 'Wrath Wheels' ], 'dom' ],
	'pusta srednica'                => [ [ 'producent' => 'Wrath Wheels', 'srednica' => '' ], 'dom' ],
];

foreach ( $dostawy as $nazwa => [ $dane, $want ] ) {
	check( "dostawa: {$nazwa}", $want, Dawmac_Allegro_Offer::cennik_dostawy( $dane, $cenniki ) );
}

echo "\n";
printf( "%d przeszlo, %d bledow\n", $pass, $fail );
exit( $fail > 0 ? 1 : 0 );
