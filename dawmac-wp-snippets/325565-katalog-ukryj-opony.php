/**
 * Strona sklepu: bez opon (sklep to katalog felg, opony mają własną kategorię).
 *
 * HISTORIA TEGO SNIPPETU — warto przeczytać przed kolejną zmianą:
 *
 * Pierwotna wersja dopasowywała kategorię po slugu 'Opony' wielką literą,
 * przy slugu 'opony'. Nie pasowało, więc snippet od zawsze był pustym
 * przebiegiem — opony NIGDY nie były ukrywane, a nikt tego nie zauważył.
 *
 * Poprawienie slugu na 'opony' sprawiło, że zaczął działać — i strona sklepu
 * zrobiła się PUSTA. Samo zapytanie jest poprawne (w izolacji zwraca 32 596
 * produktów), więc problem leży w tym, JAK tax_query było dopisywane:
 * nadpisywało strukturę zbudowaną wcześniej przez WooCommerce.
 *
 * Ta wersja: wykluczenie po ID kategorii, priorytet 99 (po WooCommerce
 * i po dawmac-filters), z zachowaniem istniejących warunków i relacji AND.
 */
add_action( 'woocommerce_product_query', 'dawmac_sklep_bez_opon', 99 );

function dawmac_sklep_bez_opon( $q ) {

	if ( is_admin() || ! is_shop() || ! $q->is_main_query() ) {
		return;
	}

	$opony = get_term_by( 'slug', 'opony', 'product_cat' );

	if ( ! $opony || is_wp_error( $opony ) ) {
		return;
	}

	$istniejace = $q->get( 'tax_query' );
	$istniejace = is_array( $istniejace ) ? $istniejace : array();

	$istniejace[] = array(
		'taxonomy'         => 'product_cat',
		'field'            => 'term_id',
		'terms'            => array( (int) $opony->term_id ),
		'operator'         => 'NOT IN',
		// Bez tego wykluczenie nie objęłoby podkategorii opon.
		'include_children' => true,
	);

	// Relacja jawnie, żeby dopisany warunek nie rozsypał struktury
	// zbudowanej wcześniej przez WooCommerce.
	if ( count( $istniejace ) > 1 && ! isset( $istniejace['relation'] ) ) {
		$istniejace['relation'] = 'AND';
	}

	$q->set( 'tax_query', $istniejace );
}
