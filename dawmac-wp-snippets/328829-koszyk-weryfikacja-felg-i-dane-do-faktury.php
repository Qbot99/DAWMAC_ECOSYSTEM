/**
 * DAWMAC - WERYFIKACJA FELG (ROZBITE POLA) + DANE DO FAKTURY + MAILE + POPRAWIONE CSS (KOMPLETNY KOD)
 */

// 1. Sprawdzanie, czy w koszyku są felgi
function dawmac_cart_has_rims() {
    if ( null === WC()->cart ) return false;
    
    foreach ( WC()->cart->get_cart() as $cart_item ) {
        // Zabezpieczenie na różne warianty sluga kategorii
        $kategorie_felg = array( 'felgi', 'felgi-aluminiowe' );
        
        if ( has_term( $kategorie_felg, 'product_cat', $cart_item['product_id'] ) ) {
            return true;
        }
    }
    return false;
}

// 2. Dodanie pól w koszyku (Auto + Faktura)
add_action( 'woocommerce_before_order_notes', 'dawmac_add_custom_checkout_fields' );
function dawmac_add_custom_checkout_fields( $checkout ) {
    
    // Wymuszenie stylów dla ciemnego motywu + usunięcie "opcjonalne" + białe nazwy płatności
    echo '<style>
        #dawmac_car_verification_fields label, 
        #dawmac_invoice_fields_wrapper label,
        #dawmac_invoice_section .woocommerce-form__label-for-checkbox span,
        #payment ul.payment_methods li label {
            color: #fff !important;
        }
        #dawmac_invoice_fields_wrapper {
            display: none; 
            background: #111; 
            padding: 15px; 
            border: 1px solid #333; 
            margin-top: 10px; 
            border-radius: 5px;
        }
        /* UKRYCIE NAPISU "(opcjonalne)" */
        #dawmac_invoice_fields_wrapper .optional {
            display: none !important;
        }
        /* DODANIE CZERWONEJ GWIAZDKI (WYMAGANE) DO NIPU I FIRMY */
        #dawmac_invoice_fields_wrapper label::after {
            content: " *";
            color: #d10404;
            font-weight: bold;
        }
    </style>';

    // --- SEKCJA: DANE POJAZDU (TYLKO DLA FELG) ---
    if ( dawmac_cart_has_rims() ) {
        echo '<div id="dawmac_car_verification_fields" style="background: #111; padding: 15px; border: 1px solid #d10404; margin-bottom: 20px; border-radius: 5px; color: #fff;">';
        echo '<h3 style="margin-top:0; color:#fff;">Krok weryfikacji dopasowania</h3>';
        echo '<p style="color:#d10404; font-weight:bold; font-size: 13px;">Nasi eksperci sprawdzą parametry przed akceptacją płatności. Podaj dane pojazdu.</p>';

        woocommerce_form_field( 'dawmac_car_make', array(
            'type'        => 'text',
            'class'       => array('form-row-first'),
            'label'       => 'Marka pojazdu',
            'placeholder' => 'np. Porsche',
            'required'    => true,
        ), $checkout->get_value( 'dawmac_car_make' ));

        woocommerce_form_field( 'dawmac_car_model', array(
            'type'        => 'text',
            'class'       => array('form-row-last'),
            'label'       => 'Model i silnik',
            'placeholder' => 'np. 911 Carrera 4S 3.0',
            'required'    => true,
        ), $checkout->get_value( 'dawmac_car_model' ));

        woocommerce_form_field( 'dawmac_car_year', array(
            'type'        => 'text',
            'class'       => array('form-row-wide'),
            'label'       => 'Rok produkcji',
            'placeholder' => 'np. 2023',
            'required'    => true,
        ), $checkout->get_value( 'dawmac_car_year' ));
        
        echo '<div style="clear:both;"></div>';
        echo '</div>';
    }

    // --- SEKCJA: FAKTURA VAT (DLA WSZYSTKICH ZAMÓWIEŃ) ---
    echo '<div id="dawmac_invoice_section" style="margin-bottom: 20px;">';
    
    woocommerce_form_field( 'dawmac_want_invoice', array(
        'type'        => 'checkbox',
        'class'       => array('form-row-wide dawmac-invoice-toggle'),
        'label'       => 'Chcę otrzymać fakturę VAT',
        'required'    => false,
    ), $checkout->get_value( 'dawmac_want_invoice' ));

    // Ukryty kontener na NIP i Firmę
    echo '<div id="dawmac_invoice_fields_wrapper">';
    
    woocommerce_form_field( 'dawmac_invoice_nip', array(
        'type'        => 'text',
        'class'       => array('form-row-first'),
        'label'       => 'Numer NIP',
        'placeholder' => 'np. 1234567890',
        'required'    => false, 
    ), $checkout->get_value( 'dawmac_invoice_nip' ));

    woocommerce_form_field( 'dawmac_invoice_company', array(
        'type'        => 'text',
        'class'       => array('form-row-last'),
        'label'       => 'Nazwa firmy',
        'placeholder' => 'np. Dawmac Sp. z o.o.',
        'required'    => false, 
    ), $checkout->get_value( 'dawmac_invoice_company' ));

    echo '<div style="clear:both;"></div>';
    echo '</div>';
    echo '</div>';

    // Skrypt JS do płynnego pokazywania/ukrywania pól faktury - z delegacją zdarzeń (odporny na AJAX)
    ?>
    <script>
        jQuery(document).ready(function($) {
            function toggleInvoiceFields() {
                if ($('#dawmac_want_invoice').is(':checked')) {
                    $('#dawmac_invoice_fields_wrapper').slideDown();
                } else {
                    $('#dawmac_invoice_fields_wrapper').slideUp();
                }
            }
            
            // Uruchomienie na start
            toggleInvoiceFields(); 
            
            // Delegacja zdarzeń - odporna na odświeżanie AJAX koszyka
            $(document.body).on('change', '#dawmac_want_invoice', function() {
                toggleInvoiceFields();
            });

            // Ponowne sprawdzenie po odświeżeniu koszyka przez WooCommerce
            $(document.body).on('updated_checkout', function() {
                toggleInvoiceFields();
            });
        });
    </script>
    <?php
}

// 3. Walidacja - wymuszenie wypełnienia odpowiednich pól
add_action( 'woocommerce_checkout_process', 'dawmac_validate_custom_fields' );
function dawmac_validate_custom_fields() {
    
    // Walidacja aut (tylko jak są felgi)
    if ( dawmac_cart_has_rims() ) {
        if ( empty( $_POST['dawmac_car_make'] ) ) wc_add_notice( 'Proszę podać <strong>Markę pojazdu</strong>.', 'error' );
        if ( empty( $_POST['dawmac_car_model'] ) ) wc_add_notice( 'Proszę podać <strong>Model pojazdu</strong>.', 'error' );
        if ( empty( $_POST['dawmac_car_year'] ) ) wc_add_notice( 'Proszę podać <strong>Rok produkcji</strong>.', 'error' );
    }

    // Walidacja faktury (jeśli zaznaczył checkbox)
    if ( !empty( $_POST['dawmac_want_invoice'] ) ) {
        if ( empty( $_POST['dawmac_invoice_nip'] ) ) wc_add_notice( 'Zaznaczono chęć otrzymania faktury. Proszę podać <strong>NIP</strong>.', 'error' );
        if ( empty( $_POST['dawmac_invoice_company'] ) ) wc_add_notice( 'Zaznaczono chęć otrzymania faktury. Proszę podać <strong>Nazwę firmy</strong>.', 'error' );
		
    }
}

// 4. Zapisanie danych do bazy zamówienia
add_action( 'woocommerce_checkout_update_order_meta', 'dawmac_save_custom_fields' );
function dawmac_save_custom_fields( $order_id ) {
    
    if ( !empty( $_POST['dawmac_car_make'] ) ) update_post_meta( $order_id, 'Marka pojazdu', sanitize_text_field( $_POST['dawmac_car_make'] ) );
    if ( !empty( $_POST['dawmac_car_model'] ) ) update_post_meta( $order_id, 'Model i silnik', sanitize_text_field( $_POST['dawmac_car_model'] ) );
    if ( !empty( $_POST['dawmac_car_year'] ) ) update_post_meta( $order_id, 'Rok produkcji', sanitize_text_field( $_POST['dawmac_car_year'] ) );

    if ( !empty( $_POST['dawmac_want_invoice'] ) ) {
        update_post_meta( $order_id, 'Faktura VAT', 'TAK' );
        if ( !empty( $_POST['dawmac_invoice_nip'] ) ) update_post_meta( $order_id, 'NIP', sanitize_text_field( $_POST['dawmac_invoice_nip'] ) );
        if ( !empty( $_POST['dawmac_invoice_company'] ) ) update_post_meta( $order_id, 'Nazwa firmy', sanitize_text_field( $_POST['dawmac_invoice_company'] ) );
    }
}

// 5. Zależność płatności od wybranej metody wysyłki
add_filter( 'woocommerce_available_payment_gateways', 'dawmac_conditional_payment_gateways' );
function dawmac_conditional_payment_gateways( $available_gateways ) {
    if ( is_admin() ) return $available_gateways;

    $chosen_shipping_methods = WC()->session->get( 'chosen_shipping_methods' );
    if ( empty( $chosen_shipping_methods ) ) return $available_gateways;

    $chosen_shipping = $chosen_shipping_methods[0];

    // Dokładne ID wysyłek wyciągnięte ze strony
    $przedplata_methods = array( 'flat_rate:19', 'flat_rate:21' ); // Kurier przedpłata, Paleta przedpłata
    $pobranie_methods   = array( 'flat_rate:20', 'flat_rate:23' ); // Kurier pobranie, Paleta pobranie

    // Logika: Wybrano wysyłkę za pobraniem -> usuwamy Przelew Bankowy ('bacs')
    if ( in_array( $chosen_shipping, $pobranie_methods ) ) {
        if ( isset( $available_gateways['bacs'] ) ) {
            unset( $available_gateways['bacs'] );
        }
    } 
    // Logika: Wybrano wysyłkę z przedpłatą -> usuwamy Płatność przy odbiorze ('cod')
    elseif ( in_array( $chosen_shipping, $przedplata_methods ) ) {
        if ( isset( $available_gateways['cod'] ) ) {
            unset( $available_gateways['cod'] );
        }
    }

    return $available_gateways;
}

// 6. Zmiana tekstu przycisku na dole koszyka
add_filter( 'woocommerce_order_button_text', 'dawmac_custom_button_text' );
function dawmac_custom_button_text( $button_text ) {
    if ( dawmac_cart_has_rims() ) {
        return 'Wyślij do weryfikacji';
    }
    return $button_text;
}

// 7. Wyświetlenie zebranych danych w panelu admina (w szczegółach zamówienia)
add_action( 'woocommerce_admin_order_data_after_billing_address', 'dawmac_display_custom_fields_in_admin', 10, 1 );
function dawmac_display_custom_fields_in_admin( $order ) {
    $make = $order->get_meta( 'Marka pojazdu' );
    $model = $order->get_meta( 'Model i silnik' );
    $year = $order->get_meta( 'Rok produkcji' );
    $invoice = $order->get_meta( 'Faktura VAT' );
    $nip = $order->get_meta( 'NIP' );
    $company = $order->get_meta( 'Nazwa firmy' );

    // Pojazd
    if ( $make || $model || $year ) {
        echo '<div style="background-color: #d10404; color: #fff; padding: 10px; margin-top: 15px; border-radius: 4px;">';
        echo '<strong><span class="dashicons dashicons-car"></span> POJAZD DO WERYFIKACJI:</strong><br/>';
        if($make) echo 'Marka: ' . esc_html( $make ) . '<br/>';
        if($model) echo 'Model: ' . esc_html( $model ) . '<br/>';
        if($year) echo 'Rok: ' . esc_html( $year );
        echo '</div>';
    }

    // Faktura
    if ( $invoice === 'TAK' ) {
        echo '<div style="background-color: #eeeeee; color: #333; padding: 10px; margin-top: 15px; border-radius: 4px; border: 1px solid #ccc;">';
        echo '<strong><span class="dashicons dashicons-media-document"></span> DANE DO FAKTURY:</strong><br/>';
        if($nip) echo 'NIP: ' . esc_html( $nip ) . '<br/>';
        if($company) echo 'Firma: ' . esc_html( $company );
        echo '</div>';
    }
}

// 8. Wyświetlenie danych w e-mailach (dla Ciebie i Klienta)
add_action( 'woocommerce_email_order_meta', 'dawmac_add_custom_fields_to_emails', 10, 3 );
function dawmac_add_custom_fields_to_emails( $order, $sent_to_admin, $plain_text ) {
    $make = $order->get_meta( 'Marka pojazdu' );
    $model = $order->get_meta( 'Model i silnik' );
    $year = $order->get_meta( 'Rok produkcji' );
    $invoice = $order->get_meta( 'Faktura VAT' );
    $nip = $order->get_meta( 'NIP' );
    $company = $order->get_meta( 'Nazwa firmy' );
    
    if ( $plain_text ) {
        // Wersja dla maili tekstowych
        if ( $make || $model ) {
            echo "\n==========\nPOJAZD DO WERYFIKACJI:\n";
            echo "Marka: $make\nModel: $model\nRok: $year\n==========\n";
        }
        if ( $invoice === 'TAK' ) {
            echo "\n==========\nDANE DO FAKTURY:\n";
            echo "NIP: $nip\nFirma: $company\n==========\n";
        }
    } else {
        // Wersja dla maili HTML
        if ( $make || $model ) {
            echo '<div style="margin-bottom: 20px; border: 2px solid #d10404; padding: 15px; background-color: #fff9f9;">';
            echo '<h3 style="color: #d10404; margin-top: 0;">Pojazd do weryfikacji</h3>';
            echo '<ul style="list-style: none; padding: 0; margin: 0; font-size: 15px;">';
            if($make) echo '<li style="color:#000;"><strong>Marka:</strong> ' . esc_html($make) . '</li>';
            if($model) echo '<li style="color:#000;"><strong>Model:</strong> ' . esc_html($model) . '</li>';
            if($year) echo '<li style="color:#000;"><strong>Rok:</strong> ' . esc_html($year) . '</li>';
            echo '</ul></div>';
        }
        if ( $invoice === 'TAK' ) {
            echo '<div style="margin-bottom: 20px; border: 1px solid #ccc; padding: 15px; background-color: #f5f5f5;">';
            echo '<h3 style="color: #333; margin-top: 0;">Dane do faktury VAT</h3>';
            echo '<ul style="list-style: none; padding: 0; margin: 0; font-size: 15px;">';
            if($nip) echo '<li style="color:#000;"><strong>NIP:</strong> ' . esc_html($nip) . '</li>';
            if($company) echo '<li style="color:#000;"><strong>Firma:</strong> ' . esc_html($company) . '</li>';
            echo '</ul></div>';
        }
    }
}
