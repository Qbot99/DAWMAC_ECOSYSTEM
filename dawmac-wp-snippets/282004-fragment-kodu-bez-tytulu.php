/**
 * Ustawienie domyślnego sortowania po cenie rosnąco w WooCommerce
 */
add_filter('woocommerce_default_catalog_orderby', 'custom_default_catalog_orderby');

function custom_default_catalog_orderby() {
     return 'price'; // 'price' to sortowanie od najniższej, 'price-desc' od najwyższej
}
