<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class RLB_Whish_Gateway extends WC_Payment_Gateway {
    private static $thankyou_rendered = false;
    public function __construct() {
        $this->id = 'rlb_whish_bridge';
        $this->method_title = 'Whish Money (RaffleLB Bridge)';
        $this->method_description = 'Whish-to-Whish checkout with payment detection from RaffleLB\'s signed Android notification bridge.';
        $this->has_fields = true;
        $this->supports = array( 'products' );
        $this->init_form_fields();
        $this->init_settings();
        $this->icon = $this->get_checkout_logo_url();
        $this->title = $this->get_option( 'title', 'Pay with Whish Money' );
        $this->description = $this->get_option( 'description', 'Send the exact amount using Whish Money. You may pay from your own Whish account or another person\'s Whish account. RaffleLB will detect the transfer and verify it before confirming your order.' );
        $this->enabled = $this->get_option( 'enabled', 'no' );
        add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
        add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'thankyou_page' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
        add_action( 'wp_footer', array( $this, 'fallback_thankyou_panel' ), 5 );
    }

    public function is_available() {
        if ( ! parent::is_available() ) { return false; }
        if ( ! in_array( strtoupper( get_woocommerce_currency() ), array( 'USD', 'LBP' ), true ) ) { return false; }
        if ( ! trim( (string) $this->get_option( 'whish_number', '' ) ) ) { return false; }
        return true;
    }

    public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array( 'title' => 'Enable/Disable', 'type' => 'checkbox', 'label' => 'Enable Whish Money bridge checkout', 'default' => 'no' ),
            'title' => array( 'title' => 'Title', 'type' => 'text', 'default' => 'Pay with Whish Money' ),
            'description' => array( 'title' => 'Description', 'type' => 'textarea', 'default' => 'Send the exact amount using Whish Money. You may pay from your own Whish account or another person\'s Whish account. RaffleLB will detect the transfer and verify it before confirming your order.' ),
            'whish_number' => array( 'title' => 'Whish Number', 'type' => 'text', 'description' => 'Dedicated Whish number customers should pay.', 'default' => '' ),
            'qr_image_url' => array( 'title' => 'Whish QR Image URL', 'type' => 'text', 'description' => 'Optional Media Library URL to override the bundled official Whish QR image.', 'default' => '' ),
            'checkout_logo_url' => array( 'title' => 'Checkout Whish Logo URL', 'type' => 'text', 'description' => 'Optional custom checkout logo URL. Leave blank to use the bundled tightly-cropped horizontal Whish Money logo on its original white background, positioned before the payment title like the Raffle Points badge.', 'default' => '' ),
            'cancel_cart_grace_minutes' => array( 'title' => 'Cancelled Payment Cart Grace (minutes)', 'type' => 'number', 'default' => '5', 'description' => 'After the customer cancels the Whish waiting screen, restored raffle items remain reserved in the cart for this many minutes. Entering checkout/Whish payment again starts a fresh payment window.' ),
            'support_url' => array( 'title' => 'Support URL', 'type' => 'text', 'description' => 'Optional link used by the Contact Support message on the Whish payment screen. Leave blank to auto-detect Contact / Contact Us.', 'default' => '' ),
            'hold_minutes' => array( 'title' => 'Payment Window (minutes)', 'type' => 'number', 'default' => '15', 'description' => 'If no signed payment event is detected in this period, the unpaid Whish order is cancelled and moved to Trash.' ),
            'match_window_minutes' => array( 'title' => 'Matching Window (minutes)', 'type' => 'number', 'default' => '30', 'description' => 'Maximum age of a pending Whish order considered when matching a payment notification.' ),
            'pilot_verified' => array(
                'title' => 'Incoming Notification Verified',
                'type' => 'checkbox',
                'label' => 'I have captured a genuine incoming Whish payment notification and verified the parser reads the correct amount/currency',
                'default' => 'no',
                'description' => 'Safety gate. Automatic Confirmation cannot activate until this is checked.'
            ),
            'auto_match' => array(
                'title' => 'Automatic Confirmation',
                'type' => 'checkbox',
                'label' => 'Automatically call WooCommerce payment_complete() for a unique safe match',
                'default' => 'no',
                'description' => 'Keep OFF during the pilot. A unique detected payment can instead be confirmed manually in Whish Bridge.'
            ),
            'auto_require_sender_phone' => array(
                'title' => 'Require Sender Phone for Auto Confirmation',
                'type' => 'checkbox',
                'label' => 'Require the Whish notification sender phone to exactly match the optional payer phone entered at checkout',
                'default' => 'yes',
                'description' => 'Only use this when Whish incoming notifications include the sender phone and the customer entered the actual payer phone. The currently verified Whish notification format does not expose a sender phone, so this should remain OFF.'
            ),
        );
    }

    public function payment_fields() {
        if ( $this->description ) { echo wpautop( wp_kses_post( $this->description ) ); }
        echo '<p class="form-row form-row-wide"><label for="rlb_whish_sender_phone">Whish payer phone <span class="optional">(optional)</span></label>';
        echo '<input type="tel" class="input-text" name="rlb_whish_sender_phone" id="rlb_whish_sender_phone" autocomplete="tel" placeholder="03 123 456" /></p>';
        echo "<p style=\"font-size:.9em;opacity:.8\">You may pay from your own Whish account or another person's Whish account. If you already know which Whish number will send the payment, enter it here as an extra matching signal; otherwise leave this field blank.</p>";
    }

    public function validate_fields() {
        $phone = isset( $_POST['rlb_whish_sender_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['rlb_whish_sender_phone'] ) ) : '';
        if ( '' !== trim( $phone ) && strlen( preg_replace( '/\D+/', '', $phone ) ) < 7 ) {
            wc_add_notice( 'Please enter a valid Whish payer phone number or leave the optional field blank.', 'error' );
            return false;
        }
        return true;
    }

    public function process_payment( $order_id ) {
        $order = wc_get_order( $order_id );
        if ( ! $order ) { return array( 'result' => 'failure' ); }

        $phone = isset( $_POST['rlb_whish_sender_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['rlb_whish_sender_phone'] ) ) : '';
        $hold = max( 5, min( 120, (int) $this->get_option( 'hold_minutes', 15 ) ) );
        $now = time();

        if ( function_exists( 'WC' ) && WC()->session ) {
            WC()->session->__unset( 'rlb_whish_cancel_grace_until' );
        }

        $order->update_meta_data( '_rlb_whish_sender_phone', $phone );
        $order->update_meta_data( '_rlb_whish_sender_phone_norm', RLB_Whish_Matcher::normalize_phone( $phone ) );
        $order->update_meta_data( '_rlb_whish_started_at', $now );
        $order->update_meta_data( '_rlb_whish_expires_at', $now + ( $hold * MINUTE_IN_SECONDS ) );
        $order->delete_meta_data( '_rlb_whish_expired' );
        $order->delete_meta_data( '_rlb_whish_expired_at' );
        $order->delete_meta_data( '_rlb_whish_review_hold' );
        // Keep unpaid Whish checkouts as Pending Payment. Moving them to
        // On hold would trigger WooCommerce's standard unpaid-order emails.
        if ( ! $order->has_status( 'pending' ) ) {
            $order->update_status( 'pending', 'Waiting for Whish Money payment detection.' );
        } else {
            $order->add_order_note( 'Waiting for Whish Money payment detection.' );
        }
        $order->save();

        $expire_at = $now + ( $hold * MINUTE_IN_SECONDS );
        if ( function_exists( 'as_next_scheduled_action' ) && function_exists( 'as_schedule_single_action' ) ) {
            if ( ! as_next_scheduled_action( 'rlb_whish_expire_order', array( $order_id ), 'rafflelb-whish' ) ) {
                as_schedule_single_action( $expire_at, 'rlb_whish_expire_order', array( $order_id ), 'rafflelb-whish' );
            }
        } elseif ( ! wp_next_scheduled( 'rlb_whish_expire_order', array( $order_id ) ) ) {
            wp_schedule_single_event( $expire_at, 'rlb_whish_expire_order', array( $order_id ) );
        }
        if ( WC()->cart ) { WC()->cart->empty_cart(); }

        return array( 'result' => 'success', 'redirect' => $this->get_payment_page_url( $order ) );
    }

    private function get_payment_page_url( $order ) {
        return add_query_arg(
            array(
                'rlb_whish_payment' => '1',
                'order_id'          => $order->get_id(),
                'key'               => $order->get_order_key(),
            ),
            home_url( '/whish-payment/' )
        );
    }

    private function get_order_from_request() {
        $order_id = isset( $_GET['order_id'] ) ? absint( $_GET['order_id'] ) : 0;
        $key = isset( $_GET['key'] ) ? wc_clean( wp_unslash( $_GET['key'] ) ) : '';
        if ( ! $order_id || ! $key ) { return false; }
        $order = wc_get_order( $order_id );
        if ( ! $order || ! hash_equals( (string) $order->get_order_key(), (string) $key ) ) { return false; }
        if ( $this->id !== $order->get_payment_method() ) { return false; }
        return $order;
    }

    /**
     * Whish uses its own waiting screen instead of the regular RaffleLB
     * order-received template. The custom checkout plugin intentionally owns
     * that template, so relying on WooCommerce thank-you hooks is not robust.
     */
    public function handle_payment_page_route() {
        // Catch any unpaid Whish order-received URL (including orders created
        // before this version) and send it to the dedicated waiting screen.
        if ( function_exists( 'is_order_received_page' ) && is_order_received_page() ) {
            $order = $this->get_received_order();
            if ( $order && ! $order->is_paid() ) {
                wp_safe_redirect( $this->get_payment_page_url( $order ) );
                exit;
            }
        }

        $request_path = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ), PHP_URL_PATH ) : '';
        $bridge_path  = (string) wp_parse_url( home_url( '/whish-payment/' ), PHP_URL_PATH );
        $is_dedicated = $request_path && untrailingslashit( $request_path ) === untrailingslashit( $bridge_path );
        if ( empty( $_GET['rlb_whish_payment'] ) && ! $is_dedicated ) { return; }

        $order = $this->get_order_from_request();
        if ( ! $order ) {
            wp_die( esc_html__( 'This Whish payment link is invalid or has expired.', 'rafflelb-whish-bridge' ), esc_html__( 'Whish Payment', 'rafflelb-whish-bridge' ), array( 'response' => 404 ) );
        }

        if ( isset( $_POST['rlb_whish_cancel_payment'] ) ) {
            $this->handle_customer_cancel( $order );
        }

        if ( $order->is_paid() ) {
            wp_safe_redirect( $order->get_checkout_order_received_url() );
            exit;
        }

        nocache_headers();
        status_header( 200 );

        // This is a virtual plugin route. Normalize WordPress query flags
        // before get_header() runs wp_head(), otherwise WoodMart/SEO layers
        // can label the browser tab as a 404 and apply error-page styling.
        global $wp_query;
        if ( $wp_query instanceof WP_Query ) {
            $wp_query->is_404      = false;
            $wp_query->is_page     = true;
            $wp_query->is_singular = true;
        }

        wp_enqueue_style( 'rlb-whish-payment', RLB_WHISH_BRIDGE_URL . 'assets/css/payment.css', array(), RLB_WHISH_BRIDGE_VERSION );
        wp_enqueue_script( 'rlb-whish-status', RLB_WHISH_BRIDGE_URL . 'assets/js/payment-status.js', array(), RLB_WHISH_BRIDGE_VERSION, true );

        get_header();
        echo '<main class="rlb-whish-payment-page"><div class="rlb-whish-payment-shell">';
        $this->render_payment_panel( $order );
        echo '</div></main>';
        get_footer();
        exit;
    }

    public function enqueue_assets() {
        $is_received = function_exists( 'is_order_received_page' ) && is_order_received_page();
        $is_checkout = function_exists( 'is_checkout' ) && is_checkout();
        if ( ! $is_received && ! $is_checkout ) { return; }

        wp_enqueue_style( 'rlb-whish-payment', RLB_WHISH_BRIDGE_URL . 'assets/css/payment.css', array(), RLB_WHISH_BRIDGE_VERSION );
        if ( $is_received ) {
            wp_enqueue_script( 'rlb-whish-status', RLB_WHISH_BRIDGE_URL . 'assets/js/payment-status.js', array(), RLB_WHISH_BRIDGE_VERSION, true );
        }
    }

    public function thankyou_page( $order_id ) {
        $order = wc_get_order( $order_id );
        if ( ! $order || $this->id !== $order->get_payment_method() ) { return; }
        self::$thankyou_rendered = true;
        $this->render_payment_panel( $order );
    }

    private function get_received_order() {
        if ( ! function_exists( 'is_order_received_page' ) || ! is_order_received_page() ) { return false; }
        $order_id = absint( get_query_var( 'order-received' ) );
        if ( ! $order_id && isset( $_GET['key'] ) ) {
            $order_id = wc_get_order_id_by_order_key( wc_clean( wp_unslash( $_GET['key'] ) ) );
        }
        if ( ! $order_id ) { return false; }
        $order = wc_get_order( $order_id );
        if ( ! $order || $this->id !== $order->get_payment_method() ) { return false; }
        return $order;
    }

    public function fallback_thankyou_panel() {
        if ( self::$thankyou_rendered ) { return; }
        $order = $this->get_received_order();
        if ( ! $order ) { return; }

        /*
         * RaffleLB Custom Checkout owns the order-received layout and does not
         * always fire the gateway-specific WooCommerce thank-you hook. Render
         * a safe fallback here, then move it to the top of the order page.
         */
        echo '<div id="rlb-whish-fallback-wrap">';
        $this->render_payment_panel( $order );
        echo '</div>';
        ?>
        <script id="rlb-whish-fallback-position">
        (function(){
            var wrap=document.getElementById('rlb-whish-fallback-wrap');
            if(!wrap) return;
            var target=document.querySelector('.woocommerce-order, .woocommerce, .site-content, .main-page-wrapper, main');
            if(target){ target.insertBefore(wrap,target.firstChild); }

            // The custom raffle confirmation hero is appropriate only after
            // payment. While this Whish order is unpaid, correct its wording
            // so the customer is never told that an entry is already confirmed.
            <?php if ( ! $order->is_paid() ) : ?>
            var replacements={
                'ENTRY CONFIRMED':'PAYMENT PENDING',
                'Your raffle entry is in.':'Complete your Whish payment',
                'Your entry order has been received and will be processed according to its payment status.':'Your order is waiting for your Whish transfer. Your raffle entry will be assigned only after payment is confirmed.'
            };
            var walker=document.createTreeWalker(document.body,NodeFilter.SHOW_TEXT);
            var node;
            while((node=walker.nextNode())){
                var t=(node.nodeValue||'').trim();
                if(replacements[t]) node.nodeValue=node.nodeValue.replace(t,replacements[t]);
            }
            <?php endif; ?>
        })();
        </script>
        <?php
        self::$thankyou_rendered = true;
    }

    /**
     * Format the dedicated Whish payment amount with the currency symbol
     * visibly prefixed (for example, $1.00), independent of WooCommerce's
     * global currency-position setting.
     */
    private function format_payment_amount( $order ) {
        $currency = strtoupper( (string) $order->get_currency() );
        $decimals = function_exists( 'wc_get_price_decimals' ) ? wc_get_price_decimals() : 2;
        $amount   = number_format( (float) $order->get_total(), $decimals, '.', ',' );
        $symbol   = function_exists( 'get_woocommerce_currency_symbol' ) ? get_woocommerce_currency_symbol( $currency ) : '';
        $symbol   = trim( wp_strip_all_tags( (string) $symbol ) );

        if ( '' === $symbol ) {
            $symbol = $currency . ' ';
        }

        return $symbol . $amount;
    }

    /**
     * Whish checkout logo. By default this uses the bundled horizontal Whish Money wordmark
     * supplied for RaffleLB, tightly cropped while keeping its original white background. A custom
     * Media Library URL can still be configured later without another update.
     */
    private function get_checkout_logo_url() {
        $configured = trim( (string) $this->get_option( 'checkout_logo_url', '' ) );
        $legacy_default = 'https://upload.wikimedia.org/wikipedia/commons/e/e1/Logo_Whish_Money_%28Lebanon%29.png';

        // v0.1.11 saved the old wide wordmark as the default. Treat that exact
        // legacy value as unset so upgrades immediately use the compact badge.
        if ( '' === $configured || $legacy_default === $configured ) {
            return esc_url_raw( RLB_WHISH_BRIDGE_URL . 'assets/images/whish-money-checkout-logo.png' );
        }

        return esc_url_raw( $configured );
    }

    /**
     * Add a stable class to the WooCommerce gateway icon so RaffleLB can place
     * the Whish mark before the payment title, matching the Raffle Points row.
     */
    public function get_icon() {
        $icon = $this->get_checkout_logo_url();
        if ( ! $icon ) { return ''; }

        return '<span class="rlb-whish-checkout-badge" aria-hidden="true"><img class="rlb-whish-checkout-icon" src="' . esc_url( $icon ) . '" alt="" /></span>';
    }

    private function acquire_order_lock( $order_id ) {
        $key = 'rlb_whish_order_lock_' . absint( $order_id );
        $now = time();
        if ( add_option( $key, $now, '', 'no' ) ) { return $key; }

        $existing = (int) get_option( $key, 0 );
        if ( $existing && $existing < ( $now - 60 ) ) {
            delete_option( $key );
            if ( add_option( $key, $now, '', 'no' ) ) { return $key; }
        }
        return false;
    }

    /**
     * Cancel an unpaid Whish attempt, restore the order lines to the current
     * WooCommerce cart, and shorten the Draw Engine reservation to five
     * minutes (configurable). A fresh Whish checkout gets the normal full
     * payment window again.
     */
    private function handle_customer_cancel( $order ) {
        $order_id = $order instanceof WC_Order ? $order->get_id() : 0;
        $nonce = isset( $_POST['_rlb_whish_cancel_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_rlb_whish_cancel_nonce'] ) ) : '';
        if ( ! $order_id || ! wp_verify_nonce( $nonce, 'rlb_whish_cancel_' . $order_id ) ) {
            wp_die( esc_html__( 'The cancellation request could not be verified. Please reload the payment page and try again.', 'rafflelb-whish-bridge' ), esc_html__( 'Whish Payment', 'rafflelb-whish-bridge' ), array( 'response' => 403 ) );
        }

        $lock = $this->acquire_order_lock( $order_id );
        if ( ! $lock ) {
            wp_safe_redirect( add_query_arg( 'whish_cancel_error', 'busy', $this->get_payment_page_url( $order ) ) );
            exit;
        }

        try {
            $order = wc_get_order( $order_id );
            if ( ! $order || $this->id !== $order->get_payment_method() ) {
                delete_option( $lock );
                wp_safe_redirect( wc_get_cart_url() );
                exit;
            }

            // Do not let a customer cancel once money is already paid or a
            // genuine signed bridge event has claimed the order.
            if ( $order->is_paid() || $order->get_meta( '_rlb_whish_event_id', true ) || $order->get_meta( '_rlb_whish_detected_event_id', true ) ) {
                delete_option( $lock );
                wp_safe_redirect( add_query_arg( 'whish_cancel_error', 'payment_detected', $this->get_payment_page_url( $order ) ) );
                exit;
            }

            if ( ! $order->has_status( array( 'pending', 'on-hold' ) ) ) {
                delete_option( $lock );
                wp_safe_redirect( add_query_arg( 'whish_cancel_error', 'unavailable', $this->get_payment_page_url( $order ) ) );
                exit;
            }

            if ( function_exists( 'wc_load_cart' ) && ( ! function_exists( 'WC' ) || ! WC()->cart || ! WC()->session ) ) {
                wc_load_cart();
            }
            if ( ! function_exists( 'WC' ) || ! WC()->cart || ! WC()->session ) {
                delete_option( $lock );
                wp_safe_redirect( add_query_arg( 'whish_cancel_error', 'cart', $this->get_payment_page_url( $order ) ) );
                exit;
            }

            $cart_before = array();
            foreach ( WC()->cart->get_cart() as $cart_key => $cart_item ) {
                $cart_before[ $cart_key ] = isset( $cart_item['quantity'] ) ? (int) $cart_item['quantity'] : 0;
            }

            $restore_ok = true;
            $previous_mode_exists = array_key_exists( 'rafflelb_purchase_mode', $_REQUEST );
            $previous_mode = $previous_mode_exists ? $_REQUEST['rafflelb_purchase_mode'] : null;

            foreach ( $order->get_items( 'line_item' ) as $item ) {
                if ( ! $item instanceof WC_Order_Item_Product ) { continue; }
                $product = $item->get_product();
                if ( ! $product || ! $product->is_purchasable() ) {
                    $restore_ok = false;
                    break;
                }

                $product_id  = (int) $item->get_product_id();
                $variation_id = (int) $item->get_variation_id();
                $quantity    = max( 1, (int) $item->get_quantity() );
                $variation   = $variation_id && $product instanceof WC_Product_Variation ? $product->get_variation_attributes() : array();
                $mode        = (string) $item->get_meta( '_rafflelb_purchase_mode', true );
                $_REQUEST['rafflelb_purchase_mode'] = 'buy_now' === $mode ? 'buy_now' : 'raffle_entry';

                $added_key = WC()->cart->add_to_cart( $product_id, $quantity, $variation_id, $variation );
                if ( ! $added_key ) {
                    $restore_ok = false;
                    break;
                }
            }

            if ( $previous_mode_exists ) {
                $_REQUEST['rafflelb_purchase_mode'] = $previous_mode;
            } else {
                unset( $_REQUEST['rafflelb_purchase_mode'] );
            }

            if ( ! $restore_ok ) {
                // Roll back only the cart changes made by this restoration.
                foreach ( WC()->cart->get_cart() as $cart_key => $cart_item ) {
                    if ( array_key_exists( $cart_key, $cart_before ) ) {
                        WC()->cart->set_quantity( $cart_key, $cart_before[ $cart_key ], false );
                    } else {
                        WC()->cart->remove_cart_item( $cart_key );
                    }
                }
                WC()->cart->calculate_totals();
                WC()->cart->set_session();
                delete_option( $lock );
                wp_safe_redirect( add_query_arg( 'whish_cancel_error', 'restore', $this->get_payment_page_url( $order ) ) );
                exit;
            }

            foreach ( $order->get_coupon_codes() as $coupon_code ) {
                if ( $coupon_code && ! WC()->cart->has_discount( $coupon_code ) ) {
                    WC()->cart->apply_coupon( $coupon_code );
                }
            }

            $grace = max( 1, min( 15, (int) $this->get_option( 'cancel_cart_grace_minutes', 5 ) ) );
            $base_hold = ( class_exists( 'RaffleLB_Draw_Engine' ) && defined( 'RaffleLB_Draw_Engine::HOLD_MINUTES' ) )
                ? max( $grace, (int) RaffleLB_Draw_Engine::HOLD_MINUTES )
                : 15;
            $now = current_time( 'timestamp' );
            $started = $now - ( max( 0, $base_hold - $grace ) * MINUTE_IN_SECONDS );
            WC()->session->set( 'rafflelb_hold_started_at', $started );
            WC()->session->set( 'rlb_whish_cancel_grace_until', $now + ( $grace * MINUTE_IN_SECONDS ) );

            if ( class_exists( 'RaffleLB_Draw_Engine' ) && is_callable( array( 'RaffleLB_Draw_Engine', 'sync_hold_from_cart' ) ) ) {
                RaffleLB_Draw_Engine::sync_hold_from_cart();
            }
            WC()->cart->calculate_totals();
            WC()->cart->set_session();

            $order->update_meta_data( '_rlb_whish_customer_cancelled', 'yes' );
            $order->update_meta_data( '_rlb_whish_customer_cancelled_at', time() );
            $order->update_meta_data( '_rlb_whish_expired', 'yes' );
            $order->save();
            $order->update_status( 'cancelled', 'Customer cancelled the Whish payment attempt and returned the items to cart.' );
            $order->save();

            if ( function_exists( 'as_unschedule_all_actions' ) ) {
                as_unschedule_all_actions( 'rlb_whish_expire_order', array( $order_id ), 'rafflelb-whish' );
            }
            wp_clear_scheduled_hook( 'rlb_whish_expire_order', array( $order_id ) );

            try {
                $order->delete( false );
            } catch ( Throwable $e ) {
                if ( function_exists( 'wc_get_logger' ) ) {
                    wc_get_logger()->warning(
                        'Could not move customer-cancelled Whish order #' . absint( $order_id ) . ' to Trash: ' . $e->getMessage(),
                        array( 'source' => 'rafflelb-whish-bridge' )
                    );
                }
            }

            if ( function_exists( 'wc_add_notice' ) ) {
                wc_add_notice(
                    sprintf(
                        'Whish payment cancelled. Your raffle reservation is held in the cart for %d more minutes. Returning to Whish checkout starts a fresh payment window.',
                        $grace
                    ),
                    'notice'
                );
            }
            delete_option( $lock );
            wp_safe_redirect( wc_get_cart_url() );
            exit;
        } finally {
            delete_option( $lock );
        }
    }

    /**
     * Resolve the public support destination used by the Whish waiting screen.
     * Admins may override it in the gateway settings. Otherwise use an existing
     * Contact / Contact Us / Support page when available.
     */
    private function get_support_url() {
        $configured = trim( (string) $this->get_option( 'support_url', '' ) );
        if ( $configured ) {
            return esc_url_raw( $configured );
        }

        foreach ( array( 'contact', 'contact-us', 'support' ) as $slug ) {
            $page = get_page_by_path( $slug );
            if ( $page instanceof WP_Post && 'publish' === $page->post_status ) {
                $url = get_permalink( $page );
                if ( $url ) { return esc_url_raw( $url ); }
            }
        }

        return esc_url_raw( home_url( '/contact/' ) );
    }

    /**
     * Use an admin override when provided, otherwise the official Whish QR
     * supplied for this RaffleLB receiving account is bundled with the plugin.
     */
    private function get_whish_qr_url() {
        $configured = trim( (string) $this->get_option( 'qr_image_url', '' ) );
        return $configured ? esc_url_raw( $configured ) : RLB_WHISH_BRIDGE_URL . 'assets/images/whish-qr.jpg';
    }

    private function render_payment_panel( $order ) {
        $number = $this->get_option( 'whish_number', '' );
        $qr = esc_url( $this->get_whish_qr_url() );
        $expires = (int) $order->get_meta( '_rlb_whish_expires_at', true );
        $endpoint = rest_url( RLB_Whish_REST::NS . '/order-status' );
        $display_amount = $this->format_payment_amount( $order );
        $support_url = $this->get_support_url();
        $cancel_grace = max( 1, min( 15, (int) $this->get_option( 'cancel_cart_grace_minutes', 5 ) ) );
        ?>
        <section class="rlb-whish-card" data-order-id="<?php echo esc_attr( $order->get_id() ); ?>" data-order-key="<?php echo esc_attr( $order->get_order_key() ); ?>" data-status-endpoint="<?php echo esc_url( $endpoint ); ?>" data-expires="<?php echo esc_attr( $expires ); ?>">
            <div class="rlb-whish-kicker">WHISH MONEY</div>
            <h2>Complete your payment</h2>
            <p class="rlb-whish-lead">Send exactly <strong class="rlb-whish-amount-highlight"><?php echo esc_html( $display_amount ); ?></strong>. You may pay from your Whish account or another person's Whish account.</p>
            <?php if ( $qr ) : ?>
            <div class="rlb-whish-pay-options">
                <div class="rlb-whish-qr-side">
                    <img class="rlb-whish-qr" src="<?php echo esc_url( $qr ); ?>" alt="Whish payment QR" />
                </div>
                <div class="rlb-whish-pay-copy">
                    <div class="rlb-whish-pay-options-title">Scan to pay with Whish</div>
                    <p class="rlb-whish-qr-hint">Scan the QR from another device, then enter exactly <strong><?php echo esc_html( $display_amount ); ?></strong> in Whish.</p>
                </div>
                <div class="rlb-whish-manual-divider"><span>or send manually</span></div>
            </div>
            <?php endif; ?>
            <div class="rlb-whish-details">
                <?php if ( $number ) : ?><div><span>Send to</span><strong><?php echo esc_html( $number ); ?></strong></div><?php endif; ?>
                <div><span>Amount</span><strong class="rlb-whish-amount-highlight rlb-whish-amount-value"><?php echo esc_html( $display_amount ); ?></strong></div>
                <div><span>Order</span><strong>#<?php echo esc_html( $order->get_order_number() ); ?></strong></div>
            </div>
            <div class="rlb-whish-status is-waiting" aria-live="polite"><span class="rlb-whish-dot"></span><span class="rlb-whish-status-text">Waiting for payment…</span></div>
            <?php
            $cancel_error = isset( $_GET['whish_cancel_error'] ) ? sanitize_key( wp_unslash( $_GET['whish_cancel_error'] ) ) : '';
            if ( $cancel_error ) :
                $cancel_messages = array(
                    'busy'             => 'Payment status is being updated. Please wait a moment and try again.',
                    'payment_detected' => 'A Whish transfer has already been detected for this order, so cancellation is no longer available.',
                    'unavailable'      => 'This payment can no longer be cancelled.',
                    'cart'             => 'Your cart could not be restored. Please try again.',
                    'restore'          => 'The items could not be safely returned to your cart. Your Whish order remains active.',
                );
                $cancel_message = $cancel_messages[ $cancel_error ] ?? 'The payment could not be cancelled. Please try again.';
            ?>
            <div class="rlb-whish-cancel-error" role="alert"><?php echo esc_html( $cancel_message ); ?></div>
            <?php endif; ?>
            <form class="rlb-whish-cancel-form" method="post" action="<?php echo esc_url( $this->get_payment_page_url( $order ) ); ?>">
                <input type="hidden" name="rlb_whish_cancel_payment" value="1" />
                <input type="hidden" name="_rlb_whish_cancel_nonce" value="<?php echo esc_attr( wp_create_nonce( 'rlb_whish_cancel_' . $order->get_id() ) ); ?>" />
                <button type="submit" class="rlb-whish-cancel-button" data-grace-minutes="<?php echo esc_attr( $cancel_grace ); ?>">Cancel payment</button>
                <span class="rlb-whish-cancel-note">Returns your items to cart; raffle reservations keep <?php echo esc_html( $cancel_grace ); ?> minutes.</span>
            </form>
            <p class="rlb-whish-hint"><strong>The RaffleLB customer name does not need to match the Whish sender name.</strong> Please send the exact amount shown. Keep this page open; it updates after the bridge detects your incoming Whish transfer.</p>
            <div class="rlb-whish-support">
                <svg class="rlb-whish-support-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M4 13v-1a8 8 0 0 1 16 0v1"/><path d="M4 13h2.2a1.8 1.8 0 0 1 1.8 1.8v3.4A1.8 1.8 0 0 1 6.2 20H5a2 2 0 0 1-2-2v-3a2 2 0 0 1 1-1.73"/><path d="M20 13h-2.2a1.8 1.8 0 0 0-1.8 1.8v3.4a1.8 1.8 0 0 0 1.8 1.8H19a2 2 0 0 0 2-2v-3a2 2 0 0 0-1-1.73"/><path d="M16 20c-.7 1-2 1.5-4 1.5"/></svg>
                <span>If you have any issue, <a href="<?php echo esc_url( $support_url ); ?>">contact support</a>.</span>
            </div>
        </section>
        <?php
    }
}
