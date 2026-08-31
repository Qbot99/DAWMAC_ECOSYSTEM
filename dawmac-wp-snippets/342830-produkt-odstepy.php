/**
 * Karta produktu: gęstsze odstępy. Bez okruszków.
 *
 * OKRUSZKI WYCIĘTE 2026-08-31. Na telefonie łamały się po jednym słowie
 * w linii ("Strona / główna / Felgi / Forzza / Titan / 17" / 7.5J / ET40
 * / 5x114.3 / Satin / Black") i zjadały pół ekranu nad zdjęciem. Nazwa
 * produktu jest długa z natury, więc w wąskiej kolumnie nie dało się tego
 * uratować stylami — ścieżka poszła w całości. Zostały same remove_action,
 * żeby WooCommerce ani motyw nie wstawiły jej z powrotem.
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

}, 99 );

add_action( 'wp_head', function () {

	if ( ! function_exists( 'is_product' ) || ! is_product() ) {
		return;
	}

	echo '<style id="dm-produkt-odstepy">
/* Okruszki na karcie produktu - w kazdej postaci, takze wlasne Astry. */
.single-product .woocommerce-breadcrumb,
.single-product .ast-breadcrumbs-wrapper,
.single-product .dm-okruszki { display: none !important; }

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
