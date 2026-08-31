/**
 * Karta produktu: okruszki na samej górze + gęstsze odstępy.
 *
 * Poprzednia wersja robiła remove_action w pętli 1-30 i dodawała okruszki
 * przez woocommerce_before_main_content. Nie działało — Astra ma własny
 * system okruszków, a w podsumowaniu zostawał ukryty <nav> o zerowej
 * wysokości. Teraz wypisujemy je sami, jako pierwszą rzecz w treści.
 *
 * Odstępy: cena miała 56 px marginesu pod spodem, a WooCommerce zostawiał
 * pusty <p> z kolejnymi 25 px. Na telefonie dawało to dwa ekrany pustki
 * między ceną a resztą.
 */

add_action( 'wp', function () {

	if ( ! function_exists( 'is_product' ) || ! is_product() ) {
		return;
	}

	for ( $i = 1; $i <= 30; $i++ ) {
		remove_action( 'woocommerce_single_product_summary', 'woocommerce_breadcrumb', $i );
	}
	remove_action( 'woocommerce_before_single_product', 'woocommerce_breadcrumb', 20 );
	remove_action( 'woocommerce_before_main_content', 'woocommerce_breadcrumb', 20 );

	add_action( 'woocommerce_before_main_content', 'dawmac_okruszki_na_gorze', 1 );
	add_action( 'astra_primary_content_top', 'dawmac_okruszki_na_gorze', 1 );

}, 99 );

function dawmac_okruszki_na_gorze() {

	// Oba haki mogą wystrzelić na tym samym widoku — wypisujemy raz.
	static $juz = false;

	if ( $juz || ! function_exists( 'woocommerce_breadcrumb' ) ) {
		return;
	}

	$juz = true;

	echo '<div class="dm-okruszki">';
	woocommerce_breadcrumb( array(
		'delimiter'   => ' / ',
		'wrap_before' => '<nav class="woocommerce-breadcrumb dm-okruszki-nav">',
		'wrap_after'  => '</nav>',
	) );
	echo '</div>';
}

add_action( 'wp_head', function () {

	if ( ! function_exists( 'is_product' ) || ! is_product() ) {
		return;
	}

	echo '<style id="dm-produkt-odstepy">
.dm-okruszki { margin: 0 0 10px; font-size: .82rem; line-height: 1.4; }
.dm-okruszki .dm-okruszki-nav { margin: 0; padding: 0; }

/* Kopie okruszkow w innych miejscach - precz. */
.single-product .summary > .woocommerce-breadcrumb,
.single-product .entry-summary > .woocommerce-breadcrumb { display: none !important; }

/* Cena miala 56 px pod spodem - to byla najwieksza dziura. */
.single-product .summary .price,
.single-product .entry-summary .price { margin-bottom: 10px !important; }

/* Pusty <p>, ktory WooCommerce zostawia po opisie skroconym. */
.single-product .summary > p:empty,
.single-product .entry-summary > p:empty { display: none !important; margin: 0 !important; }

.single-product .wc-price-history { margin: 0 0 10px !important; }

/* "Brak w magazynie" jest ukryty - niech nie zostawia marginesow. */
.single-product .summary .stock,
.single-product .entry-summary .stock { margin: 0 !important; }

.single-product .dark-form-container { margin-top: 10px !important; margin-bottom: 10px !important; }

/* Sekcje pod spodem bez nadmiarowego zapasu. */
.single-product .woocommerce-Tabs-panel { margin-top: 16px; margin-bottom: 16px; }
.single-product .shop_attributes th,
.single-product .shop_attributes td { padding-top: 5px; padding-bottom: 5px; }
</style>';

}, 20 );
