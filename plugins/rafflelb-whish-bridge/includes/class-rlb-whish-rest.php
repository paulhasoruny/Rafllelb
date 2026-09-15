<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class RLB_Whish_REST {
    const NS = 'rafflelb-whish/v1';
    const OFFICIAL_WHISH_PACKAGE = 'money.whish.android';

    public static function init() {
        add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
    }

    public static function routes() {
        register_rest_route( self::NS, '/payment-event', array(
            'methods' => 'POST', 'callback' => array( __CLASS__, 'payment_event' ), 'permission_callback' => '__return_true',
        ) );
        register_rest_route( self::NS, '/ping', array(
            'methods' => 'POST', 'callback' => array( __CLASS__, 'ping' ), 'permission_callback' => '__return_true',
        ) );
        register_rest_route( self::NS, '/order-status', array(
            'methods' => 'GET', 'callback' => array( __CLASS__, 'order_status' ), 'permission_callback' => '__return_true',
            'args' => array( 'order_id' => array( 'required' => true ), 'key' => array( 'required' => true ) ),
        ) );
    }

    private static function clean_text( $value, $max = 1000 ) {
        $value = wp_strip_all_tags( (string) $value, true );
        if ( function_exists( 'mb_substr' ) ) { return mb_substr( $value, 0, $max ); }
        return substr( $value, 0, $max );
    }

    private static function verify_signed_request( WP_REST_Request $request ) {
        $timestamp = trim( (string) $request->get_header( 'x-rlb-timestamp' ) );
        $nonce = trim( (string) $request->get_header( 'x-rlb-nonce' ) );
        $signature = strtolower( trim( (string) $request->get_header( 'x-rlb-signature' ) ) );
        $bridge_id = trim( (string) $request->get_header( 'x-rlb-bridge-id' ) );
        $signature_version = trim( (string) $request->get_header( 'x-rlb-signature-version' ) );
        $expected_bridge_id = (string) get_option( 'rlb_whish_bridge_id', 'rafflelb-phone-1' );
        $secret = (string) get_option( 'rlb_whish_bridge_secret', '' );

        if ( ! $timestamp || ! $nonce || ! $signature || ! $bridge_id || ! $secret ) {
            return new WP_Error( 'rlb_auth_missing', 'Missing bridge authentication headers.', array( 'status' => 401 ) );
        }
        if ( ! ctype_digit( $timestamp ) || strlen( $timestamp ) > 12 ) {
            return new WP_Error( 'rlb_timestamp', 'Invalid bridge timestamp.', array( 'status' => 401 ) );
        }
        if ( strlen( $nonce ) < 16 || strlen( $nonce ) > 120 ) {
            return new WP_Error( 'rlb_nonce', 'Invalid bridge nonce.', array( 'status' => 401 ) );
        }
        if ( ! preg_match( '/^[a-f0-9]{64}$/', $signature ) ) {
            return new WP_Error( 'rlb_signature', 'Invalid bridge signature format.', array( 'status' => 401 ) );
        }
        if ( ! hash_equals( $expected_bridge_id, $bridge_id ) ) {
            return new WP_Error( 'rlb_bridge_id', 'Unknown bridge ID.', array( 'status' => 401 ) );
        }
        if ( abs( time() - (int) $timestamp ) > 300 ) {
            return new WP_Error( 'rlb_timestamp', 'Bridge request timestamp is outside the allowed window.', array( 'status' => 401 ) );
        }

        $nonce_key = 'rlb_whish_nonce_' . md5( $bridge_id . '|' . $nonce );
        if ( get_transient( $nonce_key ) ) {
            return new WP_Error( 'rlb_replay', 'Duplicate bridge request.', array( 'status' => 409 ) );
        }

        $body = $request->get_body();
        if ( '2' === $signature_version ) {
            $canonical = $bridge_id . "\n" . $timestamp . "\n" . $nonce . "\n" . strtoupper( $request->get_method() ) . "\n" . $request->get_route() . "\n" . $body;
        } else {
            // v0.1.0 compatibility during the private pilot.
            $canonical = $timestamp . "\n" . $nonce . "\n" . $body;
        }
        $expected = hash_hmac( 'sha256', $canonical, $secret );
        if ( ! hash_equals( $expected, $signature ) ) {
            return new WP_Error( 'rlb_signature', 'Invalid bridge signature.', array( 'status' => 401 ) );
        }

        set_transient( $nonce_key, 1, 10 * MINUTE_IN_SECONDS );
        return true;
    }

    public static function ping( WP_REST_Request $request ) {
        $auth = self::verify_signed_request( $request );
        if ( is_wp_error( $auth ) ) { return $auth; }
        return rest_ensure_response( array(
            'ok' => true,
            'server_time' => time(),
            'version' => RLB_WHISH_BRIDGE_VERSION,
            'whish_package' => self::OFFICIAL_WHISH_PACKAGE,
        ) );
    }

    public static function payment_event( WP_REST_Request $request ) {
        $auth = self::verify_signed_request( $request );
        if ( is_wp_error( $auth ) ) { return $auth; }

        $payload = json_decode( $request->get_body(), true );
        if ( ! is_array( $payload ) ) {
            return new WP_Error( 'rlb_json', 'Invalid JSON body.', array( 'status' => 400 ) );
        }

        $event_id = sanitize_text_field( $payload['event_id'] ?? '' );
        if ( ! $event_id || strlen( $event_id ) > 80 ) {
            return new WP_Error( 'rlb_event_id', 'Invalid event ID.', array( 'status' => 400 ) );
        }
        $existing = RLB_Whish_Storage::get_by_event_id( $event_id );
        if ( $existing ) {
            return rest_ensure_response( array(
                'ok' => true,
                'duplicate' => true,
                'event_status' => $existing->status,
                'matched_order_id' => (int) $existing->matched_order_id,
            ) );
        }

        $source_package = sanitize_text_field( $payload['source_package'] ?? '' );
        if ( ! $source_package || ! hash_equals( self::OFFICIAL_WHISH_PACKAGE, $source_package ) ) {
            return new WP_Error( 'rlb_package', 'Notification source package is not the official configured Whish package.', array( 'status' => 403 ) );
        }

        $amount = isset( $payload['amount'] ) && is_numeric( $payload['amount'] ) ? (float) $payload['amount'] : null;
        $currency = isset( $payload['currency'] ) ? strtoupper( sanitize_text_field( $payload['currency'] ) ) : '';
        if ( $currency && ! in_array( $currency, array( 'USD', 'LBP' ), true ) ) { $currency = ''; }
        if ( null !== $amount && $amount <= 0 ) { $amount = null; }

        $received_ms = isset( $payload['received_at_ms'] ) && is_numeric( $payload['received_at_ms'] ) ? (int) $payload['received_at_ms'] : 0;
        $received_at = $received_ms ? gmdate( 'Y-m-d H:i:s', (int) floor( $received_ms / 1000 ) ) : current_time( 'mysql', true );
        $sender_phone = sanitize_text_field( $payload['sender_phone'] ?? '' );
        $fingerprint = strtolower( sanitize_text_field( $payload['fingerprint'] ?? '' ) );
        if ( $fingerprint && ! preg_match( '/^[a-f0-9]{64}$/', $fingerprint ) ) { $fingerprint = ''; }

        // Keep only the operational fields we need and truncate notification text.
        $payload['event_id'] = $event_id;
        $payload['source_package'] = $source_package;
        $payload['notification_title'] = self::clean_text( $payload['notification_title'] ?? '', 300 );
        $payload['notification_text'] = self::clean_text( $payload['notification_text'] ?? '', 1200 );
        $payload['amount'] = $amount;
        $payload['currency'] = $currency;
        $payload['sender_phone'] = $sender_phone;
        $payload['fingerprint'] = $fingerprint;
        $payload['received_at_ms'] = $received_ms;

        $row_id = RLB_Whish_Storage::insert_event( array(
            'event_id' => $event_id,
            'created_at' => current_time( 'mysql', true ),
            'received_at' => $received_at,
            'bridge_id' => sanitize_text_field( $request->get_header( 'x-rlb-bridge-id' ) ),
            'source_package' => $source_package,
            'amount' => $amount,
            'currency' => $currency ?: null,
            'sender_phone' => $sender_phone,
            'fingerprint' => $fingerprint,
            'status' => 'received',
            'matched_order_id' => null,
            'payload' => wp_json_encode( $payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
            'note' => '',
        ) );
        if ( ! $row_id ) {
            // A simultaneous replay may have won the UNIQUE event_id race.
            $existing = RLB_Whish_Storage::get_by_event_id( $event_id );
            if ( $existing ) {
                return rest_ensure_response( array(
                    'ok' => true,
                    'duplicate' => true,
                    'event_status' => $existing->status,
                    'matched_order_id' => (int) $existing->matched_order_id,
                ) );
            }
            return new WP_Error( 'rlb_store', 'Could not store event.', array( 'status' => 500 ) );
        }

        // A second notification with the same payment content in a short window is
        // never auto-confirmed. It may be a re-posted Android notification. Legitimate
        // identical transfers can still be reviewed manually rather than guessed.
        if ( $fingerprint ) {
            $near_duplicate = RLB_Whish_Storage::recent_fingerprint( $fingerprint, $received_at, 15, $event_id );
            if ( $near_duplicate ) {
                RLB_Whish_Storage::update_event( $row_id, array(
                    'status' => 'possible_duplicate',
                    'note' => 'A recent bridge event has the same payment-content fingerprint (' . sanitize_text_field( $near_duplicate->event_id ) . '). Automatic matching was stopped for manual review.',
                ) );
                return rest_ensure_response( array(
                    'ok' => true,
                    'event_id' => $event_id,
                    'status' => 'possible_duplicate',
                    'matched_order_id' => 0,
                ) );
            }
        }

        $result = RLB_Whish_Matcher::match_and_process( $payload, $row_id );
        return rest_ensure_response( array_merge( array( 'ok' => true, 'event_id' => $event_id ), $result ) );
    }

    public static function order_status( WP_REST_Request $request ) {
        if ( ! function_exists( 'wc_get_order' ) ) {
            return new WP_Error( 'woocommerce', 'WooCommerce unavailable.', array( 'status' => 503 ) );
        }
        $order = wc_get_order( absint( $request->get_param( 'order_id' ) ) );
        $key = sanitize_text_field( (string) $request->get_param( 'key' ) );
        if ( ! $order || ! hash_equals( $order->get_order_key(), $key ) ) {
            return new WP_Error( 'not_found', 'Order not found.', array( 'status' => 404 ) );
        }
        if ( 'rlb_whish_bridge' !== $order->get_payment_method() ) {
            return new WP_Error( 'wrong_gateway', 'Order does not use Whish Bridge.', array( 'status' => 400 ) );
        }

        return rest_ensure_response( array(
            'order_id' => $order->get_id(),
            'status' => $order->get_status(),
            'paid' => $order->is_paid(),
            'detected' => (bool) $order->get_meta( '_rlb_whish_detected_event_id', true ),
            'expired' => 'yes' === $order->get_meta( '_rlb_whish_expired', true ),
            'redirect' => $order->is_paid() ? $order->get_view_order_url() : '',
        ) );
    }
}
