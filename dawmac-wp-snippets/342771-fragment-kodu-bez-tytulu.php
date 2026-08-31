/**
 * Kategoria "dawmac-forged" - pokazuj tylko produkty na magazynie
 */
add_action( 'woocommerce_product_query', 'dawmac_forged_tylko_na_magazynie', 20 );
function dawmac_forged_tylko_na_magazynie( $q ) {

    if ( is_admin() || ! $q->is_main_query() ) {
        return;
    }

    // działa też dla podkategorii - usuń drugi warunek jeśli ma być tylko sama dawmac-forged
    if ( ! is_product_category( 'dawmac-forged' ) && ! term_is_ancestor_of_dawmac_forged() ) {
        return;
    }

    $tax_query = (array) $q->get( 'tax_query' );

    $tax_query[] = array(
        'taxonomy' => 'product_visibility',
        'field'    => 'name',
        'terms'    => 'outofstock',
        'operator' => 'NOT IN',
    );

    $q->set( 'tax_query', $tax_query );
}

/**
 * Pomocnicza - sprawdza czy aktualna kategoria jest dzieckiem dawmac-forged
 */
function term_is_ancestor_of_dawmac_forged() {
    if ( ! is_product_category() ) {
        return false;
    }
    $term   = get_queried_object();
    $parent = get_term_by( 'slug', 'dawmac-forged', 'product_cat' );

    if ( ! $term || ! $parent || is_wp_error( $parent ) ) {
        return false;
    }
    return term_is_ancestor_of( $parent->term_id, $term->term_id, 'product_cat' );
}
