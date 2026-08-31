add_action( 'wp', function () {
	if ( ! function_exists( 'is_product' ) || ! is_product() ) {
		return;
	}

	// Zdejmij okruszki z sekcji podsumowania produktu (dowolny priorytet 1-30).
	for ( $i = 1; $i <= 30; $i++ ) {
		remove_action( 'woocommerce_single_product_summary', 'woocommerce_breadcrumb', $i );
	}

	// Na wszelki wypadek: inne typowe miejsca.
	remove_action( 'woocommerce_before_single_product', 'woocommerce_breadcrumb', 20 );
	remove_action( 'woocommerce_before_main_content', 'woocommerce_breadcrumb', 20 );

	// Wstaw okruszki na górę, nad galerią i tytułem.
	add_action( 'woocommerce_before_main_content', 'woocommerce_breadcrumb', 20 );
}, 99 );
