<?php
/**
 * Podglad szablonu firmowego - bez WordPressa, bez API, bez wystawiania oferty.
 *
 * Sklada opis z fixture'ow, przepuszcza przez walidator i renderuje HTML,
 * ktory nasladuje sposob, w jaki Allegro wyswietla sekcje: kolumna pelnej
 * szerokosci albo dwie po polowie, zwijane do jednej na telefonie.
 *
 * Uruchamianie:
 *
 *   php tools/preview.php                 - buduje tools/out/preview.html
 *   php tools/preview.php --json          - wypisuje surowy JSON opisu
 *   php tools/preview.php --check         - sam walidator, kod wyjscia 1 przy bledach
 *
 * Kod wyjscia 1 przy bledach walidacji, wiec nadaje sie do CI.
 */

declare( strict_types=1 );

require __DIR__ . '/../includes/class-text.php';
require __DIR__ . '/../includes/class-template.php';

$config = require __DIR__ . '/../config/brand.php';

/**
 * Fixture'y odwzorowuja realne dane ze sklepu: te same atrybuty, ten sam
 * ksztalt opisu (razem ze smieciami, ktore sanitizer ma wyciac).
 */
$FIXTURES = [
	'dawmac-forged' => [
		'id'          => 101,
		'sku'         => 'DF-FM115-9519',
		'title'       => 'Dawmac Forged FM115 9.5x19 5x112 ET35 Brushed Bronze',
		'producent'   => 'Dawmac Forged',
		'model'       => 'FM115',
		'srednica'    => '19',
		'szerokosc'   => '9.5',
		'rozstaw'     => [ '5x112' ],
		'et'          => '35',
		'kolor'       => 'Brazowy',
		'liczba_srub' => 5,
		'kategoria'   => 'felgi',
		'image'       => 'PRODUCT_PHOTO',
		// Celowo brudny opis: link, telefon, <br>, <strong>, <table>.
		'opis'        => '<p>Felga <strong>kuta</strong> z bloku aluminium 6061-T6.<br>'
			. 'Waga 10,2 kg, wykonczenie szczotkowany braz.</p>'
			. '<table><tr><td>Zapraszamy na www.dawmac.pl</td></tr></table>'
			. '<p>Kontakt: biuro@dawmac.pl, tel. 601 234 567</p>'
			. '<p>Lokalizacja magazynowa 42.L / NHB</p>',
	],

	'japan-racing'  => [
		'id'          => 102,
		'sku'         => 'JR20-8518',
		'title'       => 'Japan Racing JR20 8.5x18 5x112/5x120 ET40 Hyper Black',
		'producent'   => 'Japan Racing',
		'model'       => 'JR20',
		'srednica'    => '18',
		'szerokosc'   => '8.5',
		'rozstaw'     => [ '5x112', '5x120' ],
		'et'          => '40',
		'kolor'       => 'Czarny',
		'liczba_srub' => 5,
		'kategoria'   => 'felgi',
		'image'       => 'PRODUCT_PHOTO',
		'opis'        => '<p>Felga odlewana, malowana proszkowo.</p>',
	],

	'opona'         => [
		'id'              => 103,
		'sku'             => 'CONT-2254517',
		'title'           => 'Continental SportContact 2 225/45 R17',
		'producent'       => 'Continental',
		'model'           => 'SportContact2',
		'szerokosc_opony' => '225',
		'profil'          => '45',
		'srednica_opony'  => '17',
		'kategoria'       => 'opony',
		'image'           => 'PRODUCT_PHOTO',
		// "225 45 17" nie moze zostac zjedzone przez wzorzec telefonu.
		'opis'            => '<p>Opona letnia w rozmiarze 225 45 17, indeks predkosci Y.</p>',
	],
];

/**
 * W podgladzie nie mamy jeszcze URL-i z serwerow Allegro, wiec podstawiamy
 * adresy o tym samym ksztalcie. Walidator dzieki temu sprawdza realna regule,
 * a renderer rozpoznaje je po sufiksie i rysuje opisane pole zamiast obrazka.
 */
$resolve_image = static function ( string $key ) use ( $config ): ?string {
	return isset( $config['images'][ $key ] )
		? 'https://a.allegroimg.com/original/PLACEHOLDER/' . $key
		: null;
};

$template = new Dawmac_Allegro_Template( $config, $resolve_image );

$results = [];
$failed  = false;

foreach ( $FIXTURES as $slug => $product ) {
	$product['image'] = 'https://a.allegroimg.com/original/PLACEHOLDER/product_photo';

	$description = $template->build( $product );
	$errors      = Dawmac_Allegro_Template::validate( $description );

	$results[ $slug ] = [
		'product'     => $product,
		'title'       => $template->build_offer_title( $product ),
		'description' => $description,
		'errors'      => $errors,
	];

	if ( $errors ) {
		$failed = true;
	}
}

/* ---------------------------------------------------------------- */
/* Tryby CLI                                                         */
/* ---------------------------------------------------------------- */

$argv = $argv ?? [];

if ( in_array( '--json', $argv, true ) ) {
	foreach ( $results as $slug => $r ) {
		echo "=== {$slug} ===\n";
		echo json_encode( $r['description'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ), "\n\n";
	}
	exit( $failed ? 1 : 0 );
}

echo "SZABLON FIRMOWY - kontrola\n\n";

foreach ( $results as $slug => $r ) {
	$sections = count( $r['description']['sections'] );
	$chars    = 0;

	foreach ( $r['description']['sections'] as $s ) {
		foreach ( $s['items'] as $i ) {
			if ( 'TEXT' === $i['type'] ) {
				$chars += mb_strlen( $i['content'], 'UTF-8' );
			}
		}
	}

	printf( "  %-16s %d sekcji, %s znakow\n", $slug, $sections, number_format( $chars, 0, ',', ' ' ) );
	printf( "  %-16s tytul (%d/75): %s\n", '', mb_strlen( $r['title'], 'UTF-8' ), $r['title'] );

	if ( $r['errors'] ) {
		foreach ( $r['errors'] as $e ) {
			echo "    BLAD: {$e}\n";
		}
	} else {
		echo "    walidacja: OK\n";
	}

	echo "\n";
}

if ( in_array( '--check', $argv, true ) ) {
	exit( $failed ? 1 : 0 );
}

/* ---------------------------------------------------------------- */
/* Render HTML                                                       */
/* ---------------------------------------------------------------- */

$out_dir = __DIR__ . '/out';

if ( ! is_dir( $out_dir ) ) {
	mkdir( $out_dir, 0755, true );
}

$path = $out_dir . '/preview.html';
file_put_contents( $path, render_preview( $results ) );

echo "Podglad: {$path}\n";
exit( $failed ? 1 : 0 );


/**
 * Renderuje sekcje tak, jak pokazuje je Allegro: jedna kolumna na pelna
 * szerokosc albo dwie po polowie, zwijane do jednej ponizej 720 px.
 */
function render_preview( array $results ): string {
	$body = '';

	foreach ( $results as $slug => $r ) {
		$body .= '<article class="offer">';
		$body .= '<header class="offer-head">';
		$body .= '<span class="slug">' . h( $slug ) . '</span>';
		$body .= '<h2 class="offer-title">' . h( $r['title'] ) . '</h2>';
		$body .= '<p class="meta">tytul oferty ' . mb_strlen( $r['title'], 'UTF-8' ) . '/75 znakow'
			. ' &middot; ' . count( $r['description']['sections'] ) . ' sekcji</p>';

		if ( $r['errors'] ) {
			$body .= '<ul class="errors">';
			foreach ( $r['errors'] as $e ) {
				$body .= '<li>' . h( $e ) . '</li>';
			}
			$body .= '</ul>';
		}

		$body .= '</header><div class="allegro">';

		foreach ( $r['description']['sections'] as $section ) {
			$cols  = count( $section['items'] );
			$body .= '<section class="sec cols-' . $cols . '">';

			foreach ( $section['items'] as $item ) {
				$body .= 'IMAGE' === $item['type']
					? render_image( (string) $item['url'] )
					: '<div class="txt">' . $item['content'] . '</div>';
			}

			$body .= '</section>';
		}

		$body .= '</div></article>';
	}

	return page( $body );
}

/** Placeholdery rysujemy jako opisane pole - realny URL jako <img>. */
function render_image( string $url ): string {
	if ( ! str_contains( $url, '/PLACEHOLDER/' ) ) {
		return '<div class="img"><img src="' . h( $url ) . '" alt=""></div>';
	}

	$key = substr( $url, strrpos( $url, '/' ) + 1 );

	return '<div class="img ph"><span>grafika: <b>' . h( $key ) . '</b></span></div>';
}

function h( string $s ): string {
	return htmlspecialchars( $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
}

function page( string $body ): string {
	$css = <<<'CSS'
	*{box-sizing:border-box}
	body{margin:0;padding:24px;background:#f4f5f7;color:#1a1a1a;
	     font:15px/1.5 -apple-system,BlinkMacSystemFont,"Segoe UI",Arial,sans-serif}
	.wrap{max-width:1040px;margin:0 auto}
	h1.page{font-size:20px;margin:0 0 6px}
	p.lead{margin:0 0 24px;color:#5b6470}
	.offer{background:#fff;border:1px solid #e3e6ea;border-radius:8px;margin-bottom:28px;overflow:hidden}
	.offer-head{padding:16px 20px;border-bottom:1px solid #eef0f3;background:#fafbfc}
	.slug{font:12px/1 ui-monospace,SFMono-Regular,Menlo,monospace;color:#8a929c;
	      text-transform:uppercase;letter-spacing:.06em}
	.offer-title{font-size:17px;margin:6px 0 4px}
	.meta{margin:0;font-size:13px;color:#6b7280}
	.errors{margin:10px 0 0;padding:10px 12px 10px 28px;background:#fff3f3;
	        border:1px solid #f3c2c2;border-radius:6px;color:#a11}
	.errors li{font-size:13px}
	/* Obszar odwzorowujacy szerokosc opisu na Allegro. */
	.allegro{padding:20px;max-width:1000px}
	.sec{display:grid;gap:16px;margin-bottom:20px}
	.sec.cols-1{grid-template-columns:1fr}
	.sec.cols-2{grid-template-columns:1fr 1fr}
	.txt h1{font-size:22px;margin:0 0 10px;font-weight:700}
	.txt h2{font-size:18px;margin:0 0 8px;font-weight:700}
	.txt p{margin:0 0 10px}
	.txt ul,.txt ol{margin:0 0 10px;padding-left:20px}
	.txt li{margin-bottom:4px}
	.img img{width:100%;height:auto;display:block;border-radius:4px}
	.img.ph{display:flex;align-items:center;justify-content:center;min-height:150px;
	        background:repeating-linear-gradient(45deg,#eef1f4,#eef1f4 10px,#e6eaef 10px,#e6eaef 20px);
	        border:1px dashed #c3cbd4;border-radius:4px;color:#5b6470;font-size:13px}
	@media(max-width:720px){.sec.cols-2{grid-template-columns:1fr}}
CSS;

	return '<!doctype html><html lang="pl"><head><meta charset="utf-8">'
		. '<meta name="viewport" content="width=device-width,initial-scale=1">'
		. '<title>Szablon firmowy DAWMAC - podglad</title><style>' . $css . '</style></head><body><div class="wrap">'
		. '<h1 class="page">Szablon firmowy DAWMAC</h1>'
		. '<p class="lead">Podglad opisu oferty zlozonego z danych produktu. '
		. 'Sekcje dwukolumnowe zwijaja sie do jednej kolumny na telefonie - tak samo jak na Allegro.</p>'
		. $body . '</div></body></html>';
}
