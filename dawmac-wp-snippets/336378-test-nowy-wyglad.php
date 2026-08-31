/**
 * DAWMAC – "Nowa wizja" jako DOMYSLNY wyglad katalogu.
 * Wspolny renderer karty rozpoznaje OPONY vs FELGI po kategorii
 * i pokazuje wlasciwe parametry. Dziala natywnie na archiwach
 * (sklep / kategoria / tag) -> filtry FilterEverything, sortowanie,
 * paginacja i licznik dzialaja bez zmian.
 *
 * Gdzie wkleic:
 *   - Code Snippets  -> POMIN pierwsza linie "<?php"
 *   - functions.php motywu potomnego -> wklej bez "<?php"
 */

if (!defined('ABSPATH')) exit;

/* =====================================================================
   WSPOLNY RENDERER KARTY
   $specs: tablica [etykieta => wartosc JUZ sformatowana]; puste pomijane.
   ===================================================================== */
function nwk_card_html($product, array $specs) {
    if (!$product instanceof WC_Product) return '';

    $link = get_permalink($product->get_id());

    $producent = $product->get_attribute('pa_producent');
    $model     = $product->get_attribute('pa_model');
    $tytul = trim($producent . ' ' . $model);
    if ($tytul === '') $tytul = $product->get_name();

    $price_html = '';
    $active = wc_get_price_to_display($product);
    if ($product->is_on_sale() && $product->get_regular_price() !== '') {
        $regular = wc_get_price_to_display($product, ['price' => $product->get_regular_price()]);
        $price_html  = '<span class="nwk-old">' . nwk_fmt_cena($regular) . '</span>';
        $price_html .= '<span class="nwk-new">' . nwk_fmt_cena($active) . '</span>';
    } elseif ($active !== '' && $active !== null) {
        $price_html = '<span class="nwk-new">' . nwk_fmt_cena($active) . '</span>';
    }

    $specs = array_filter($specs, fn($v) => $v !== '' && $v !== null);

    ob_start();
    ?>
    <article class="nwk-card">

        <?php if ($product->is_on_sale()): ?>
            <img class="nwk-sale"
                 src="https://dawmac.pl/wp-content/uploads/2026/02/computer-icons-discounts-and-allowances-sales-red-sale-lable-dc3ddc0a1526425805bbcb50cf90b819.png"
                 alt="Promocja" loading="lazy" decoding="async" />
        <?php endif; ?>

        <?php if ($product->is_in_stock()): ?>
            <span class="nwk-stock" role="img" aria-label="Dostępne w magazynie"></span>
        <?php endif; ?>

        <a href="<?php echo esc_url($link); ?>" class="nwk-imglink">
            <?php echo $product->get_image('woocommerce_thumbnail', ['class' => 'nwk-img']); ?>
        </a>

        <a href="<?php echo esc_url($link); ?>" class="nwk-title"><?php echo esc_html($tytul); ?></a>

        <?php if (!empty($specs)): ?>
        <dl class="nwk-specs">
            <?php foreach ($specs as $etykieta => $wartosc): ?>
                <div class="nwk-spec"><dt><?php echo esc_html($etykieta); ?></dt><dd><?php echo esc_html($wartosc); ?></dd></div>
            <?php endforeach; ?>
        </dl>
        <?php endif; ?>

        <?php if ($price_html): ?>
            <div class="nwk-price"><?php echo $price_html; ?></div>
        <?php endif; ?>
    </article>
    <?php
    return ob_get_clean();
}


/* =====================================================================
   ROZPOZNANIE: czy produkt jest OPONA? (po kategorii product_cat=opony)
   ===================================================================== */
function nwk_is_opona($product) {
    if (!$product instanceof WC_Product) return false;
    return has_term('opony', 'product_cat', $product->get_id());
}

/* Zwraca gotowa tablice parametrow zaleznie od typu produktu. */
function nwk_specs_for($product) {
    if (nwk_is_opona($product)) {
        // OPONY: szerokosc / profil / srednica
        return [
            'Szerokość' => nwk_fmt_normal($product->get_attribute('pa_szerokosc_opony')),
            'Profil'    => nwk_fmt_normal($product->get_attribute('pa_profil')),
            'Średnica'  => nwk_fmt_normal($product->get_attribute('pa_srednica_opony')),
        ];
    }
    // FELGI (domyslnie): srednica / szerokosc / rozstaw / ET
    return [
        'Średnica'  => nwk_fmt_normal($product->get_attribute('pa_srednica')),
        'Szerokość' => nwk_fmt_normal($product->get_attribute('pa_szerokosc')),
        'Rozstaw'   => nwk_fmt_rozstaw($product->get_attribute('pa_rozstaw')),
        'ET'        => nwk_fmt_et($product->get_attribute('pa_et')),
    ];
}


/* =====================================================================
   NOWA WIZJA jako domyslny katalog (archiwa: sklep / kategoria / tag)
   ===================================================================== */

/* 1) Klasa na <body> – wlacza layout nowej wizji z CSS (sekcja 20). */
add_filter('body_class', function ($classes) {
    if (function_exists('is_shop') &&
        (is_shop() || is_product_category() || is_product_tag())) {
        $classes[] = 'nowa-wizja-sklepu';
    }
    return $classes;
});

/* 2) Liczba produktow na strone. */
add_filter('loop_shop_per_page', function () {
    return 32;
}, 20);

/* 3) Dodaj klase nwk-wrap do <ul class="products">. */
add_filter('woocommerce_product_loop_start', function ($html) {
    if (strpos($html, 'nwk-wrap') === false) {
        $html = preg_replace('/class="([^"]*\bproducts\b[^"]*)"/', 'class="$1 nwk-wrap"', $html, 1);
    }
    return $html;
});

/* 4) Na archiwach: zdejmij domyslne elementy karty i podstaw nasza. */
add_action('woocommerce_before_shop_loop', function () {
    remove_action('woocommerce_before_shop_loop_item',       'woocommerce_template_loop_product_link_open', 10);
    remove_action('woocommerce_before_shop_loop_item_title', 'woocommerce_show_product_loop_sale_flash',    10);
    remove_action('woocommerce_before_shop_loop_item_title', 'woocommerce_template_loop_product_thumbnail', 10);
    remove_action('woocommerce_shop_loop_item_title',        'woocommerce_template_loop_product_title',     10);
    remove_action('woocommerce_after_shop_loop_item_title',  'woocommerce_template_loop_rating',             5);
    remove_action('woocommerce_after_shop_loop_item_title',  'woocommerce_template_loop_price',             10);
    remove_action('woocommerce_after_shop_loop_item',        'woocommerce_template_loop_product_link_close', 5);
    remove_action('woocommerce_after_shop_loop_item',        'woocommerce_template_loop_add_to_cart',       10);

    add_action('woocommerce_before_shop_loop_item', 'nwk_render_loop_card', 10);
}, 5);

/* 5) Render karty – sam rozpoznaje opone vs felge. */
function nwk_render_loop_card() {
    global $product;
    if (!$product instanceof WC_Product) return;
    echo nwk_card_html($product, nwk_specs_for($product));
}


/* =====================================================================
   OPONY – shortcode [opony_nwk] ZOSTAJE (zgodnosc wsteczna), ale juz
   niepotrzebny: kategoria opon dziala teraz natywnie jak felgi.
   ===================================================================== */
add_shortcode('opony_nwk', 'nwk_render_opony');

function nwk_render_opony($atts) {
    $atts = shortcode_atts([
        'category' => 'opony',
        'limit'    => '16',
        'columns'  => '4',
        'paginate' => 'true',
    ], $atts);

    $paged = max(1, (int) get_query_var('paged'));
    if ($paged < 2) $paged = max(1, (int) get_query_var('page'));

    $q = new WP_Query([
        'post_type'      => 'product',
        'posts_per_page' => (int) $atts['limit'],
        'paged'          => $paged,
        'post_status'    => 'publish',
        'tax_query'      => [[
            'taxonomy' => 'product_cat',
            'field'    => 'slug',
            'terms'    => $atts['category'],
        ]],
    ]);

    if (!$q->have_posts()) return '<p style="color:#888">Brak produktów.</p>';

    ob_start();
    echo '<div class="nwk-wrap nwk-cols-' . (int) $atts['columns'] . '">';
    while ($q->have_posts()) {
        $q->the_post();
        $product = wc_get_product(get_the_ID());
        if (!$product) continue;
        echo nwk_card_html($product, nwk_specs_for($product));
    }
    echo '</div>';

    if ($atts['paginate'] === 'true' && $q->max_num_pages > 1) {
        echo '<nav class="nwk-pagination">';
        echo paginate_links([
            'total'   => $q->max_num_pages,
            'current' => $paged,
            'format'  => '?paged=%#%',
        ]);
        echo '</nav>';
    }

    wp_reset_postdata();
    return ob_get_clean();
}


/* =====================================================================
   FORMATERY PARAMETROW (wspolne)
   ===================================================================== */

function nwk_fmt_et($val) {
    if (!$val) return '';
    $arr = array_filter(array_map('trim', explode(',', $val)));
    if (count($arr) >= 4) {
        $nums = array_map(fn($v) => (int) preg_replace('/[^0-9\-]/', '', $v), $arr);
        sort($nums, SORT_NUMERIC);
        return min($nums) . '–' . max($nums);
    }
    return $val;
}
function nwk_fmt_rozstaw($val) {
    if (!$val) return '';
    $arr = array_filter(array_map('trim', explode(',', $val)));
    return count($arr) >= 3 ? 'BLANK' : $val;
}
function nwk_fmt_normal($val) {
    if (!$val) return '';
    return mb_strlen($val) > 18 ? 'Custom' : $val;
}
function nwk_fmt_cena($a) {
    return number_format((float) $a, 0, ',', ' ') . ' zł';
}
