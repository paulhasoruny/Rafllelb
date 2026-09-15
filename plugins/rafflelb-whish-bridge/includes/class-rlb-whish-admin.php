<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class RLB_Whish_Admin {
    public static function init() {
        add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
        add_action( 'admin_post_rlb_whish_rotate_secret', array( __CLASS__, 'rotate_secret' ) );
        add_action( 'admin_post_rlb_whish_confirm_event', array( __CLASS__, 'confirm_event' ) );
        add_action( 'admin_post_rlb_whish_ignore_event', array( __CLASS__, 'ignore_event' ) );
        add_filter( 'plugin_action_links_' . plugin_basename( RLB_WHISH_BRIDGE_FILE ), array( __CLASS__, 'plugin_links' ) );
    }

    public static function plugin_links( $links ) {
        array_unshift( $links, '<a href="' . esc_url( admin_url( 'admin.php?page=rlb-whish-bridge' ) ) . '">Whish Bridge</a>' );
        return $links;
    }

    public static function menu() {
        add_submenu_page( 'woocommerce', 'Whish Bridge', 'Whish Bridge', 'manage_woocommerce', 'rlb-whish-bridge', array( __CLASS__, 'page' ) );
    }

    public static function rotate_secret() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) { wp_die( 'Unauthorized' ); }
        check_admin_referer( 'rlb_whish_rotate_secret' );
        update_option( 'rlb_whish_bridge_secret', wp_generate_password( 64, false, false ), false );
        wp_safe_redirect( admin_url( 'admin.php?page=rlb-whish-bridge&rotated=1' ) );
        exit;
    }

    public static function confirm_event() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) { wp_die( 'Unauthorized' ); }
        check_admin_referer( 'rlb_whish_confirm_event' );
        $event_id = sanitize_text_field( wp_unslash( $_POST['event_id'] ?? '' ) );
        $row = RLB_Whish_Storage::get_by_event_id( $event_id );
        $result = 'not_confirmed';

        if ( $row && $row->matched_order_id && in_array( $row->status, array( 'detected', 'matched', 'review_required' ), true ) ) {
            $order = wc_get_order( (int) $row->matched_order_id );
            if ( $order && ! $order->is_paid() ) {
                $payload = json_decode( (string) $row->payload, true );
                if ( is_array( $payload ) ) {
                    $confirmation = RLB_Whish_Matcher::confirm_order( $order, $payload, (int) $row->id, 'manual' );
                    $result = sanitize_key( $confirmation['status'] ?? 'not_confirmed' );
                }
            }
        }

        wp_safe_redirect( admin_url( 'admin.php?page=rlb-whish-bridge&confirm=' . rawurlencode( $result ) ) );
        exit;
    }

    public static function ignore_event() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) { wp_die( 'Unauthorized' ); }
        check_admin_referer( 'rlb_whish_ignore_event' );
        $event_id = sanitize_text_field( wp_unslash( $_POST['event_id'] ?? '' ) );
        $row = RLB_Whish_Storage::get_by_event_id( $event_id );

        if ( $row && $row->matched_order_id && ! in_array( $row->status, array( 'paid_auto', 'paid_manual' ), true ) ) {
            $order = wc_get_order( (int) $row->matched_order_id );
            if ( $order && ! $order->is_paid() ) {
                $claimed = (string) $order->get_meta( '_rlb_whish_detected_event_id', true );
                if ( $claimed && hash_equals( $claimed, $event_id ) ) {
                    $order->delete_meta_data( '_rlb_whish_detected_event_id' );
                    $order->delete_meta_data( '_rlb_whish_detected_at' );
                    $order->delete_meta_data( '_rlb_whish_review_hold' );
                    $order->add_order_note( 'Whish bridge event ' . $event_id . ' was ignored by an administrator. The order is available for a future payment match.' );

                    $expires = (int) $order->get_meta( '_rlb_whish_expires_at', true );
                    if ( $expires && time() >= $expires && $order->has_status( array( 'pending', 'on-hold' ) ) ) {
                        $order->update_meta_data( '_rlb_whish_expired', 'yes' );
                        $order->update_meta_data( '_rlb_whish_expired_at', time() );
                        $order->save();
                        $order->update_status( 'cancelled', 'Whish payment window expired after the previously detected bridge event was rejected.' );
                    } else {
                        $order->save();
                        if ( $expires && ! wp_next_scheduled( 'rlb_whish_expire_order', array( $order->get_id() ) ) ) {
                            wp_schedule_single_event( max( time() + 5, $expires ), 'rlb_whish_expire_order', array( $order->get_id() ) );
                        }
                    }
                }
            }
            RLB_Whish_Storage::update_event( (int) $row->id, array( 'status' => 'ignored', 'note' => 'Event ignored by administrator.' ) );
        }

        wp_safe_redirect( admin_url( 'admin.php?page=rlb-whish-bridge&ignored=1' ) );
        exit;
    }

    private static function order_link( $order_id ) {
        $order = function_exists( 'wc_get_order' ) ? wc_get_order( (int) $order_id ) : false;
        if ( ! $order ) { return ''; }
        if ( method_exists( $order, 'get_edit_order_url' ) ) {
            return $order->get_edit_order_url();
        }
        return admin_url( 'post.php?post=' . (int) $order_id . '&action=edit' );
    }

    public static function page() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) { return; }
        $secret = (string) get_option( 'rlb_whish_bridge_secret', '' );
        $bridge_id = (string) get_option( 'rlb_whish_bridge_id', 'rafflelb-phone-1' );
        $endpoint = rest_url( RLB_Whish_REST::NS . '/payment-event' );
        $ping = rest_url( RLB_Whish_REST::NS . '/ping' );
        $events = RLB_Whish_Storage::recent( 100 );
        $settings = get_option( 'woocommerce_rlb_whish_bridge_settings', array() );
        $auto_requested = isset( $settings['auto_match'] ) && 'yes' === $settings['auto_match'];
        $pilot_verified = isset( $settings['pilot_verified'] ) && 'yes' === $settings['pilot_verified'];
        $auto_effective = $auto_requested && $pilot_verified;
        ?>
        <div class="wrap"><h1>RaffleLB Whish Bridge</h1>
            <?php if ( isset( $_GET['rotated'] ) ) : ?><div class="notice notice-success"><p>Bridge secret rotated. Update the Android app before sending more events.</p></div><?php endif; ?>
            <?php if ( isset( $_GET['ignored'] ) ) : ?><div class="notice notice-success"><p>Bridge event ignored and its pending-order claim was released.</p></div><?php endif; ?>
            <?php if ( isset( $_GET['confirm'] ) ) : ?><div class="notice <?php echo 'paid' === sanitize_key( wp_unslash( $_GET['confirm'] ) ) ? 'notice-success' : 'notice-warning'; ?>"><p>Manual confirmation result: <strong><?php echo esc_html( sanitize_key( wp_unslash( $_GET['confirm'] ) ) ); ?></strong></p></div><?php endif; ?>

            <p><strong>Pilot rule:</strong> keep Automatic Confirmation OFF until a genuine incoming Whish notification has been captured and verified.</p>
            <table class="widefat striped" style="max-width:1050px"><tbody>
                <tr><th style="width:220px">Bridge ID</th><td><code><?php echo esc_html( $bridge_id ); ?></code></td></tr>
                <tr><th>Official Whish package</th><td><code><?php echo esc_html( RLB_Whish_REST::OFFICIAL_WHISH_PACKAGE ); ?></code> — events from any other Android package are rejected.</td></tr>
                <tr><th>Ping endpoint</th><td><code><?php echo esc_html( $ping ); ?></code></td></tr>
                <tr><th>Payment endpoint</th><td><code><?php echo esc_html( $endpoint ); ?></code></td></tr>
                <tr><th>Bridge secret</th><td><input type="password" value="<?php echo esc_attr( $secret ); ?>" readonly style="width:520px" onclick="this.type='text'" /> <em>Click to reveal.</em></td></tr>
                <tr><th>Automatic Confirmation</th><td><strong><?php echo $auto_effective ? 'ACTIVE' : 'OFF / BLOCKED'; ?></strong><?php if ( $auto_requested && ! $pilot_verified ) : ?> — requested in gateway settings, but the verified-notification safety gate is still OFF.<?php endif; ?></td></tr>
            </tbody></table>
            <p><a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=rlb_whish_rotate_secret' ), 'rlb_whish_rotate_secret' ) ); ?>" onclick="return confirm('Rotate the secret? The Android app will stop working until updated.');">Rotate secret</a> <a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=wc-settings&tab=checkout&section=rlb_whish_bridge' ) ); ?>">Gateway settings</a></p>

            <h2>Recent bridge events</h2>
            <table class="widefat striped"><thead><tr><th>Received (UTC)</th><th>Event</th><th>Amount</th><th>Sender</th><th>Status</th><th>Order</th><th>Notification / Actions</th></tr></thead><tbody>
            <?php if ( ! $events ) : ?><tr><td colspan="7">No events yet.</td></tr><?php endif; ?>
            <?php foreach ( $events as $e ) :
                $payload = json_decode( (string) $e->payload, true );
                $title = is_array( $payload ) ? (string) ( $payload['notification_title'] ?? '' ) : '';
                $text = is_array( $payload ) ? (string) ( $payload['notification_text'] ?? '' ) : '';
                $order_url = $e->matched_order_id ? self::order_link( (int) $e->matched_order_id ) : '';
            ?>
                <tr>
                    <td><?php echo esc_html( $e->received_at ?: $e->created_at ); ?></td>
                    <td><code style="font-size:10px"><?php echo esc_html( $e->event_id ); ?></code><br><small><?php echo esc_html( $e->source_package ); ?></small></td>
                    <td><?php echo esc_html( trim( $e->amount . ' ' . $e->currency ) ); ?></td>
                    <td><?php echo $e->sender_phone ? esc_html( $e->sender_phone ) : '—'; ?></td>
                    <td><strong><?php echo esc_html( $e->status ); ?></strong><br><small><?php echo esc_html( $e->note ); ?></small></td>
                    <td><?php echo $e->matched_order_id && $order_url ? '<a href="' . esc_url( $order_url ) . '">#' . (int) $e->matched_order_id . '</a>' : '—'; ?></td>
                    <td>
                        <?php if ( $title || $text ) : ?>
                            <details style="margin-bottom:8px"><summary>View captured notification</summary><div style="max-width:520px;white-space:pre-wrap;padding:8px 0"><strong><?php echo esc_html( $title ); ?></strong><?php if ( $title && $text ) echo "\n"; ?><?php echo esc_html( $text ); ?></div></details>
                        <?php endif; ?>
                        <?php if ( in_array( $e->status, array( 'detected', 'matched', 'review_required' ), true ) && $e->matched_order_id ) : ?>
                            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;margin-right:6px" onsubmit="return confirm('Confirm this Whish payment after reviewing the amount, sender and captured notification?');"><input type="hidden" name="action" value="rlb_whish_confirm_event"><input type="hidden" name="event_id" value="<?php echo esc_attr( $e->event_id ); ?>"><?php wp_nonce_field( 'rlb_whish_confirm_event' ); ?><button class="button button-small button-primary">Confirm payment</button></form>
                            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block" onsubmit="return confirm('Ignore this bridge event and release the order so a future payment can match?');"><input type="hidden" name="action" value="rlb_whish_ignore_event"><input type="hidden" name="event_id" value="<?php echo esc_attr( $e->event_id ); ?>"><?php wp_nonce_field( 'rlb_whish_ignore_event' ); ?><button class="button button-small">Ignore</button></form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody></table>
        </div><?php
    }
}
