add_filter( 'woocommerce_catalog_orderby', 'zostaw_tylko_sortowanie_po_cenie' );

function zostaw_tylko_sortowanie_po_cenie( $opcje ) {
    // Tworzymy nową tablicę tylko z dwiema opcjami
    $nowe_opcje = array(
        'price'      => 'Cena: od najniższej',
        'price-desc' => 'Cena: od najwyższej'
    );

    return $nowe_opcje;
}
