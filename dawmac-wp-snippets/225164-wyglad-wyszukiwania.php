add_action( 'woocommerce_before_single_product', 'dodaj_szukajke_przez_snippets', 9 );

function dodaj_szukajke_przez_snippets() {
    echo '<div class="moj-customowy-search" style="margin-bottom: 20px;">';
    
    // Generuje wyszukiwarkę tylko dla produktów WooCommerce
    get_product_search_form();
    
    echo '</div>';
}
