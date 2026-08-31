/**
 * Katalog: sortowanie wyłącznie po cenie.
 *
 * Scalone z dwóch osobnych snippetów (#208128 ograniczał listę opcji,
 * #282004 ustawiał domyślną) — trzymanie tego w dwóch miejscach groziło
 * tym, że ktoś wyłączy jeden i zostanie niespójny stan: domyślne sortowanie
 * po cenie przy pełnej liście opcji albo odwrotnie.
 *
 * Uwaga: domyślne sortowanie da się też ustawić natywnie w
 * Wygląd -> Dostosuj -> WooCommerce -> Katalog produktów. Zostaje tutaj,
 * żeby obie decyzje były widoczne obok siebie i nie rozjechały się przy
 * zmianie motywu.
 */

/* Lista opcji w rozwijanym menu — bez popularności, ocen i "domyślnego". */
add_filter( 'woocommerce_catalog_orderby', 'dawmac_sortowanie_opcje' );

function dawmac_sortowanie_opcje( $opcje ) {
	return array(
		'price'      => 'Cena: od najniższej',
		'price-desc' => 'Cena: od najwyższej',
	);
}

/* Co jest wybrane, zanim klient cokolwiek kliknie. */
add_filter( 'woocommerce_default_catalog_orderby', 'dawmac_sortowanie_domyslne' );

function dawmac_sortowanie_domyslne() {
	return 'price';
}
