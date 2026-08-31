// Preload hero LCP na stronie glownej (desktop + mobile)
add_action('wp_head', function () {
    if (!is_front_page()) {
        return;
    }
    echo '<link rel="preload" as="image" href="https://dawmac.pl/wp-content/uploads/2026/07/hero-desktop.webp" media="(min-width: 768px)">' . "\n";
    echo '<link rel="preload" as="image" href="https://dawmac.pl/wp-content/uploads/2026/05/Gemini_Generated_Image_8oumb38oumb38oum-e1768574382145.webp" media="(max-width: 767px)">' . "\n";
}, 1);
