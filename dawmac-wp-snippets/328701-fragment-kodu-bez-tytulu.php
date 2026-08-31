add_action( 'wp_head', 'dawmac_custom_og_tags_products', 5 );
function dawmac_custom_og_tags_products() {
    // Uruchom tylko na stronach pojedynczych produktów WooCommerce
    if ( is_product() ) {
        global $post;
        $product = wc_get_product( $post->ID );

        if ( ! $product ) {
            return;
        }

        // ==========================================
        // 1. ZDJĘCIE (Twój dotychczasowy kod)
        // ==========================================
        if ( has_post_thumbnail( $post->ID ) ) {
            $image_url = wp_get_attachment_image_url( get_post_thumbnail_id( $post->ID ), 'large' );
            if ( $image_url ) {
                echo '<meta property="og:image" content="' . esc_url( $image_url ) . '" />' . "\n";
                echo '<meta property="og:image:secure_url" content="' . esc_url( $image_url ) . '" />' . "\n";
            }
        }

        // ==========================================
        // 2. OPIS DLA WHATSAPP/FACEBOOK (Parametry + Cena)
        // ==========================================
        // Pobieramy atrybuty (sprawdza z 'pa_' lub bez)
        $srednica  = $product->get_attribute('pa_srednica') ?: $product->get_attribute('srednica');
        $szerokosc = $product->get_attribute('pa_szerokosc') ?: $product->get_attribute('szerokosc');
        $rozstaw   = $product->get_attribute('pa_rozstaw') ?: $product->get_attribute('rozstaw');
        $et        = $product->get_attribute('pa_et') ?: $product->get_attribute('et');
        
        // Pobieramy cenę
        $price = $product->get_price();
        $cena = $price ? wp_strip_all_tags( wc_price( $price, array('currency' => get_woocommerce_currency()) ) ) : 'Zapytaj o cenę';

        // Budujemy tekst
        $parts = array();
        if ( !empty($srednica) )  $parts[] = "Średnica: " . $srednica;
        if ( !empty($szerokosc) ) $parts[] = "Szerokość: " . $szerokosc;
        if ( !empty($rozstaw) )   $parts[] = "Rozstaw: " . $rozstaw;
        if ( !empty($et) )        $parts[] = "ET: " . $et;
        if ( !empty($cena) )      $parts[] = "Cena: " . $cena;

        // Jeśli są jakieś parametry, wstawiamy tag og:description do sekcji <head>
        if ( !empty($parts) ) {
            $description = implode( ' | ', $parts );
            echo '<meta property="og:description" content="' . esc_attr( $description ) . '" />' . "\n";
        }
    }
}
