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

// Zadna sekcja nie moze miec wiecej niz dwoch itemow.
foreach ( $desc['sections'] as $i => $section ) {
	check( "sekcja {$i} ma 1-2 itemy", true, count( $section['items'] ) >= 1 && count( $section['items'] ) <= 2 );
}

// Brak grafik = sekcje banerow po prostu nie powstaja, zamiast psuc oferte.
$bez_grafik = new Dawmac_Allegro_Template( $config, static fn( string $k ): ?string => null );
$d2         = $bez_grafik->build( $felga );

check( 'bez grafik opis dalej jest poprawny', [], Dawmac_Allegro_Template::validate( $d2 ) );
check( 'bez grafik sekcji jest mniej', true, count( $d2['sections'] ) < count( $desc['sections'] ) );

// Dwa bloki TEXT obok siebie - Allegro odrzuca to bledem 422.
$dwa_teksty = [ 'sections' => [ [ 'items' => [
	[ 'type' => 'TEXT', 'content' => '<p>Wysyłka</p>' ],
	[ 'type' => 'TEXT', 'content' => '<p>Gwarancja</p>' ],
] ] ] ];
check( 'dwa TEXT w sekcji zlapane', 1, count( Dawmac_Allegro_Template::validate( $dwa_teksty ) ) );

// TEXT + IMAGE jest poprawne.
$tekst_obraz = [ 'sections' => [ [ 'items' => [
	[ 'type' => 'TEXT', 'content' => '<p>Parametry</p>' ],
	[ 'type' => 'IMAGE', 'url' => 'https://a.allegroimg.com/original/TEST/x' ],
] ] ] ];
check( 'TEXT + IMAGE przechodzi', [], Dawmac_Allegro_Template::validate( $tekst_obraz ) );

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

echo "\n";
printf( "%d przeszlo, %d bledow\n", $pass, $fail );
exit( $fail > 0 ? 1 : 0 );
