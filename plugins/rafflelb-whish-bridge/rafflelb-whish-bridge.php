<?php
/**
 * Plugin Name: RaffleLB Whish Bridge
 * Description: Adds a WooCommerce Whish Money checkout method and a signed Android-notification bridge for payment detection.
 * Version: 0.1.16
 * Author: RaffleLB
 * Requires Plugins: woocommerce
 * Requires PHP: 8.1
 * Text Domain: rafflelb-whish-bridge
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'RLB_WHISH_BRIDGE_VERSION', '0.1.16' );
define( 'RLB_WHISH_BRIDGE_FILE', __FILE__ );
define( 'RLB_WHISH_BRIDGE_DIR', plugin_dir_path( __FILE__ ) );
define( 'RLB_WHISH_BRIDGE_URL', plugin_dir_url( __FILE__ ) );

require_once RLB_WHISH_BRIDGE_DIR . 'includes/class-rlb-whish-storage.php';
require_once RLB_WHISH_BRIDGE_DIR . 'includes/class-rlb-whish-matcher.php';
require_once RLB_WHISH_BRIDGE_DIR . 'includes/class-rlb-whish-rest.php';
require_once RLB_WHISH_BRIDGE_DIR . 'includes/class-rlb-whish-admin.php';

register_activation_hook( __FILE__, function() {
    RLB_Whish_Storage::install();
    if ( ! get_option( 'rlb_whish_bridge_secret' ) ) {
        update_option( 'rlb_whish_bridge_secret', wp_generate_password( 64, false, false ), false );
    }
    if ( ! get_option( 'rlb_whish_bridge_id' ) ) {
        update_option( 'rlb_whish_bridge_id', 'rafflelb-phone-1', false );
    }
} );

// Uploaded replacements of an already-active plugin do not reliably run the
// activation hook. Keep the private event-table schema current on version change.
add_action( 'plugins_loaded', array( 'RLB_Whish_Storage', 'maybe_install' ), 5 );

add_action( 'before_woocommerce_init', function() {
    if ( class_exists( '\\Automattic\\WooCommerce\\Utilities\\FeaturesUtil' ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
    }
} );

add_action( 'plugins_loaded', function() {
    if ( ! class_exists( 'WooCommerce' ) || ! class_exists( 'WC_Payment_Gateway' ) ) {
        return;
    }
    require_once RLB_WHISH_BRIDGE_DIR . 'includes/class-rlb-whish-gateway.php';

    add_filter( 'woocommerce_payment_gateways', function( $gateways ) {
        $gateways[] = 'RLB_Whish_Gateway';
        return $gateways;
    } );

    RLB_Whish_REST::init();
    RLB_Whish_Admin::init();
}, 20 );

/**
 * True when the current request is the dedicated public Whish payment screen.
 * Keep this in the main plugin so title/404/body filters work even when the
 * WooCommerce gateway object has not yet been instantiated.
 */
function rlb_whish_is_payment_route_request() {
    $request_path = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ), PHP_URL_PATH ) : '';
    $bridge_path  = (string) wp_parse_url( home_url( '/whish-payment/' ), PHP_URL_PATH );

    return ( $request_path && untrailingslashit( $request_path ) === untrailingslashit( $bridge_path ) ) || ! empty( $_GET['rlb_whish_payment'] );
}

// `/whish-payment/` is a virtual plugin route, not a WordPress Page. Prevent
// WordPress/theme/SEO layers from treating it as a 404 and give it a proper
// browser title.
add_filter( 'pre_handle_404', function( $preempt, $wp_query ) {
    return rlb_whish_is_payment_route_request() ? true : $preempt;
}, 1, 2 );

add_filter( 'pre_get_document_title', function( $title ) {
    return rlb_whish_is_payment_route_request() ? 'Whish Payment - RAFFLELB' : $title;
}, 9999 );

add_filter( 'wp_title', function( $title ) {
    return rlb_whish_is_payment_route_request() ? 'Whish Payment - RAFFLELB' : $title;
}, 9999 );

// Common SEO title layers can otherwise restore a 404 title after WordPress.
add_filter( 'wpseo_title', function( $title ) {
    return rlb_whish_is_payment_route_request() ? 'Whish Payment - RAFFLELB' : $title;
}, 9999 );
add_filter( 'rank_math/frontend/title', function( $title ) {
    return rlb_whish_is_payment_route_request() ? 'Whish Payment - RAFFLELB' : $title;
}, 9999 );
add_filter( 'aioseo_title', function( $title ) {
    return rlb_whish_is_payment_route_request() ? 'Whish Payment - RAFFLELB' : $title;
}, 9999 );

add_filter( 'body_class', function( $classes ) {
    if ( ! rlb_whish_is_payment_route_request() ) { return $classes; }
    $classes = array_values( array_diff( $classes, array( 'error404' ) ) );
    $classes[] = 'rlb-whish-payment-route';
    return array_values( array_unique( $classes ) );
}, 9999 );

/*
 * Front-end bridge router.
 *
 * Important: WooCommerce does not instantiate every payment-gateway class on
 * every normal front-end request. A gateway-owned template_redirect hook can
 * therefore be missing after checkout. Register the waiting-page router from
 * the main plugin so the dedicated Whish URL is always handled before
 * WordPress/theme canonical redirects can send it elsewhere.
 */
add_action( 'template_redirect', function() {
    if ( ! class_exists( 'RLB_Whish_Gateway' ) ) { return; }

    $request_path = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ), PHP_URL_PATH ) : '';
    $bridge_path  = (string) wp_parse_url( home_url( '/whish-payment/' ), PHP_URL_PATH );
    $is_bridge_request = ( $request_path && untrailingslashit( $request_path ) === untrailingslashit( $bridge_path ) ) || ! empty( $_GET['rlb_whish_payment'] );
    $is_received = function_exists( 'is_order_received_page' ) && is_order_received_page();

    if ( ! $is_bridge_request && ! $is_received ) { return; }

    $gateway = new RLB_Whish_Gateway();
    $gateway->handle_payment_page_route();
}, -1000 );

add_filter( 'redirect_canonical', function( $redirect_url, $requested_url ) {
    $request_path = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ), PHP_URL_PATH ) : '';
    $bridge_path  = (string) wp_parse_url( home_url( '/whish-payment/' ), PHP_URL_PATH );
    if ( $request_path && untrailingslashit( $request_path ) === untrailingslashit( $bridge_path ) ) {
        return false;
    }
    return $redirect_url;
}, 1, 2 );

add_action( 'rlb_whish_expire_order', function( $order_id ) {
    if ( ! function_exists( 'wc_get_order' ) ) { return; }
    $order = wc_get_order( $order_id );
    if ( ! $order || 'rlb_whish_bridge' !== $order->get_payment_method() || $order->is_paid() ) { return; }

    // Critical safeguard: once a signed bridge event has been safely matched
    // to this order, keep it available for verification instead of expiring it.
    $detected_event = (string) $order->get_meta( '_rlb_whish_detected_event_id', true );
    if ( $detected_event ) {
        if ( 'yes' !== $order->get_meta( '_rlb_whish_review_hold', true ) ) {
            $order->update_meta_data( '_rlb_whish_review_hold', 'yes' );
            $order->add_order_note( 'Whish payment was detected before expiry. Automatic cleanup was skipped while payment verification is pending.' );
            $order->save();
        }
        return;
    }

    if ( $order->has_status( array( 'pending', 'on-hold' ) ) ) {
        // Save the expiry marker BEFORE the status transition so WooCommerce
        // email filters can suppress cancellation/unpaid notifications.
        $order->update_meta_data( '_rlb_whish_expired', 'yes' );
        $order->update_meta_data( '_rlb_whish_expired_at', time() );
        $order->save();

        $order->update_status( 'cancelled', 'Whish payment window expired without a verified or detected payment.' );
        $order->save();

        // Keep a recoverable audit trail while removing the abandoned order
        // from the normal Orders list. WC_Order::delete(false) maps to Trash
        // for both classic order storage and HPOS-compatible data stores.
        try {
            $order->delete( false );
        } catch ( Throwable $e ) {
            // If trashing is unavailable for any reason, the cancelled order
            // remains safe and unpaid rather than being force-deleted.
            if ( function_exists( 'wc_get_logger' ) ) {
                wc_get_logger()->warning(
                    'Could not move expired Whish order #' . absint( $order_id ) . ' to Trash: ' . $e->getMessage(),
                    array( 'source' => 'rafflelb-whish-bridge' )
                );
            }
        }
    }
} );

/**
 * Suppress WooCommerce order emails while a Whish order is still unpaid.
 * Once a bridge event is verified, _rlb_whish_event_id is stored immediately
 * before payment_complete(), so the normal paid-order emails are allowed.
 */
function rlb_whish_email_enabled_after_payment_only( $enabled, $object = null, $email = null ) {
    if ( ! $enabled || ! $object || ! is_a( $object, 'WC_Order' ) ) { return $enabled; }
    if ( 'rlb_whish_bridge' !== $object->get_payment_method() ) { return $enabled; }

    if ( $object->is_paid() || (string) $object->get_meta( '_rlb_whish_event_id', true ) ) {
        return $enabled;
    }

    return false;
}

foreach ( array(
    'new_order',
    'cancelled_order',
    'failed_order',
    'customer_failed_order',
    'customer_on_hold_order',
    'customer_processing_order',
    'customer_completed_order',
    'customer_refunded_order',
    'customer_invoice',
    'customer_note',
) as $rlb_whish_email_id ) {
    add_filter( 'woocommerce_email_enabled_' . $rlb_whish_email_id, 'rlb_whish_email_enabled_after_payment_only', 9999, 3 );
}
