add_action( 'woocommerce_product_query', 'ukryj_opony_na_stronie_sklepu' );

function ukryj_opony_na_stronie_sklepu( $q ) {
    // Ukrywa opony na głównej stronie sklepu
    if ( ! is_admin() && is_shop() && $q->is_main_query() ) {
        
        $tax_query = (array) $q->get( 'tax_query' );

        $tax_query[] = array(
            'taxonomy' => 'product_cat',
            'field'    => 'slug',
            // Slug kategorii to 'opony' małymi literami — sprawdzone w bazie.
            // Wcześniej było 'Opony'; WordPress to sanityzuje, więc działało,
            // ale przy dopasowaniu po slugu nie ma powodu polegać na tym.
            'terms'    => array( 'opony' ),
            'operator' => 'NOT IN'
        );

        $q->set( 'tax_query', $tax_query );
    }
}
