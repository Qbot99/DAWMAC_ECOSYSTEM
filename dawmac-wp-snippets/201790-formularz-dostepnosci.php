/* Formularz "sprawdzimy dostępność" przy produktach bez stanu.
   Domyślnie ZWINIĘTY — pełny formularz z czterema polami wypychał treść
   produktu w dół u każdego, kto tylko oglądał. Rozwija się kliknięciem. */
add_action( 'woocommerce_single_product_summary', 'wstaw_formularz_dla_brakow', 31 );

function wstaw_formularz_dla_brakow() {
    global $product;

    if ( ! $product || $product->is_in_stock() ) {
        return;
    }

    // <details> jest natywne: działa bez JavaScriptu, jest dostępne
    // z klawiatury i czytniki ekranu wiedzą, że to element rozwijany.
    echo '<div class="dark-form-container">';
    echo '<details class="dm-zapytanie">';
    echo '<summary class="dm-zapytanie-naglowek">';
    echo '<span class="dm-zapytanie-tytul">Produkt chwilowo niedostępny</span>';
    echo '<span class="dm-zapytanie-podtytul">Sprawdzimy dostępność i damy znać &mdash; kliknij, aby zapytać</span>';
    echo '</summary>';
    echo '<div class="dm-zapytanie-tresc">';
    echo do_shortcode( '[contact-form-7 id="4b51204" title="Nienazwane"]' );
    echo '</div>';
    echo '</details>';
    echo '</div>';

    echo '<style>
    .stock.out-of-stock { display: none !important; }

    .dm-zapytanie { border: 1px solid #2a2a2c; border-radius: 4px; background: #1b1b1b; }
    .dm-zapytanie[open] { border-color: #d10404; }

    .dm-zapytanie-naglowek {
        display: flex; flex-direction: column; gap: 2px;
        padding: 14px 44px 14px 16px; cursor: pointer; position: relative;
        list-style: none;
    }
    .dm-zapytanie-naglowek::-webkit-details-marker { display: none; }

    /* Strzałka rysowana CSS-em, żeby nie zależeć od czcionki ikon motywu. */
    .dm-zapytanie-naglowek::after {
        content: ""; position: absolute; right: 18px; top: 50%;
        width: 9px; height: 9px; margin-top: -6px;
        border-right: 2px solid #d10404; border-bottom: 2px solid #d10404;
        transform: rotate(45deg); transition: transform .2s ease;
    }
    .dm-zapytanie[open] .dm-zapytanie-naglowek::after {
        transform: rotate(-135deg); margin-top: -2px;
    }

    .dm-zapytanie-tytul { font-weight: 700; font-size: 1rem; }
    .dm-zapytanie-podtytul { font-size: .82rem; opacity: .7; }
    .dm-zapytanie-tresc { padding: 0 16px 16px; }

    @media (prefers-reduced-motion: reduce) {
        .dm-zapytanie-naglowek::after { transition: none; }
    }
    </style>';
}

