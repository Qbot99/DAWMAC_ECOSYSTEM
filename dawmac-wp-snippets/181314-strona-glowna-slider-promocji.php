
/* =========================================================
   PROMOCJE: losowe felgi + opony, nieskonczone ladowanie.
   Kafelek = ten sam renderer co katalog: nwk_card_html() + nwk_specs_for().
   Zero dublowania - jedno zrodlo prawdy dla karty.
   (Wymaga, by w functions.php/Code Snippets istnial kod "Nowej wizji"
    z funkcjami nwk_card_html() i nwk_specs_for().)
   ========================================================= */

// Owijamy wspolna karte w <li data-id> (data-id potrzebne do doladowania)
function dm_promo_render_card( $product ) {
    if ( ! $product instanceof WC_Product ) return '';
    if ( ! function_exists('nwk_card_html') || ! function_exists('nwk_specs_for') ) return '';

    $card = nwk_card_html( $product, nwk_specs_for( $product ) );
    return '<li class="product" data-id="' . esc_attr( $product->get_id() ) . '">' . $card . '</li>';
}

// Pobiera N LOSOWYCH produktow w promocji (felgi + opony razem),
// z wykluczeniem juz pokazanych. Gdy wszystko pokazane -> losuje od nowa (nieskonczonosc).
function dm_promo_get_ids( $count = 12, $exclude = array() ) {
    $on_sale = wc_get_product_ids_on_sale();
    if ( empty($on_sale) ) return array();

    $args = array(
        'post_type'      => 'product',
        'posts_per_page' => $count,
        'post__in'       => $on_sale,
        'post__not_in'   => $exclude,
        'orderby'        => 'rand',
        'post_status'    => 'publish',
        'fields'         => 'ids',
        'no_found_rows'  => true,
    );
    $q   = new WP_Query($args);
    $ids = $q->posts;

    // wszystko juz pokazane -> losujemy od nowa (petla = nieskonczonosc)
    if ( empty($ids) ) {
        $args['post__not_in'] = array();
        $q   = new WP_Query($args);
        $ids = $q->posts;
    }
    return $ids;
}

// SHORTCODE (ta sama nazwa: [slider_atrybuty])
function dawmac_custom_slider() {
    $ids = dm_promo_get_ids(12);
    if ( empty($ids) ) return '';

    $output  = '<div class="dm-slider-wrapper" data-dm-promo="1">';
    $output .= '<div class="dm-slider-header"><h3 style="color:#d10404;">PROMOCJE</h3><span>Przesuń &rarr;</span></div>';
    $output .= '<ul class="products dm-horizontal-scroll">';
    foreach ( $ids as $id ) {
        $p = wc_get_product($id);
        if ( $p ) $output .= dm_promo_render_card($p);
    }
    $output .= '</ul></div>';
    return $output;
}
add_shortcode('slider_atrybuty', 'dawmac_custom_slider');

// AJAX - doladowanie kolejnych losowych kafelkow
function dawmac_promo_load_more() {
    $exclude = isset($_POST['exclude']) ? array_map('absint', (array) $_POST['exclude']) : array();
    $ids = dm_promo_get_ids(8, $exclude);

    $html = '';
    foreach ( $ids as $id ) {
        $p = wc_get_product($id);
        if ( $p ) $html .= dm_promo_render_card($p);
    }
    wp_send_json_success( array('html' => $html) );
}
add_action('wp_ajax_dm_promo_more',        'dawmac_promo_load_more');
add_action('wp_ajax_nopriv_dm_promo_more', 'dawmac_promo_load_more');
