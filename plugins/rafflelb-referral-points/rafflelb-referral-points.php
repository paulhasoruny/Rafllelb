<?php
/**
 * Plugin Name: RaffleLB Referral & Points
 * Description: Referral links, Raffle Points rewards, and a WooCommerce "Pay with Raffle Points" payment method for RaffleLB.
 * Version: 1.2.18
 * Author: RaffleLB
 * Text Domain: rafflelb-referral-points
 */

if (!defined('ABSPATH')) exit;

final class RaffleLB_Referral_Points {
    const VERSION = '1.2.18';
    const ENDPOINT = 'refer-and-earn';
    const COOKIE = 'rafflelb_ref';
    const OPT = 'rafflelb_referral_settings';

    public static function init() {
        add_action('init', [__CLASS__, 'register_endpoint']);
        add_filter('query_vars', [__CLASS__, 'query_vars']);
        add_filter('woocommerce_account_menu_items', [__CLASS__, 'account_menu'], 35);
        add_action('woocommerce_account_' . self::ENDPOINT . '_endpoint', [__CLASS__, 'account_page']);

        add_action('template_redirect', [__CLASS__, 'capture_referral']);
        add_action('user_register', [__CLASS__, 'attach_referrer_to_user'], 20);

        add_action('woocommerce_payment_complete', [__CLASS__, 'maybe_award_order_points']);
        add_action('woocommerce_order_status_processing', [__CLASS__, 'maybe_award_order_points']);
        add_action('woocommerce_order_status_completed', [__CLASS__, 'maybe_award_order_points']);

        add_action('woocommerce_order_status_cancelled', [__CLASS__, 'maybe_reverse_order_points']);
        add_action('woocommerce_order_status_refunded', [__CLASS__, 'maybe_reverse_order_points']);
        add_action('woocommerce_order_status_failed', [__CLASS__, 'maybe_reverse_order_points']);

        add_action('woocommerce_order_status_cancelled', [__CLASS__, 'restore_points_payment'], 5);
        add_action('woocommerce_order_status_refunded', [__CLASS__, 'restore_points_payment'], 5);
        add_action('woocommerce_order_status_failed', [__CLASS__, 'restore_points_payment'], 5);

        add_filter('woocommerce_payment_gateways', [__CLASS__, 'register_points_gateway']);
        add_filter('woocommerce_available_payment_gateways', [__CLASS__, 'replace_unavailable_points_gateway_choice'], 10000);
        add_action('woocommerce_review_order_before_payment', [__CLASS__, 'render_unavailable_points_gateway_notice'], 5);

        add_action('admin_menu', [__CLASS__, 'admin_menu']);
        add_action('admin_init', [__CLASS__, 'register_settings']);
        add_action('wp_head', [__CLASS__, 'styles']);
        add_action('wp_footer', [__CLASS__, 'scripts']);
    }

    public static function activate() {
        self::register_endpoint();

        $existing = (array) get_option(self::OPT, []);
        $defaults = [
            'enabled' => 'yes',
            'points_per_dollar' => 1,
            'spend_points_per_dollar' => 10,
            'cookie_days' => 30,
        ];

        if (!$existing) {
            add_option(self::OPT, $defaults);
        } else {
            // Preserve existing settings while migrating away from Raffle Credit.
            update_option(self::OPT, wp_parse_args($existing, $defaults));
        }

        flush_rewrite_rules();
    }

    public static function deactivate() {
        flush_rewrite_rules();
    }

    public static function settings() {
        return wp_parse_args((array) get_option(self::OPT, []), [
            'enabled' => 'yes',
            'points_per_dollar' => 1,
            'spend_points_per_dollar' => 10,
            'cookie_days' => 30,
        ]);
    }

    public static function register_endpoint() {
        add_rewrite_endpoint(self::ENDPOINT, EP_ROOT | EP_PAGES);
    }

    public static function query_vars($vars) {
        $vars[] = self::ENDPOINT;
        return $vars;
    }

    public static function account_menu($items) {
        $items[self::ENDPOINT] = 'Refer & Earn';
        // This runs after RaffleLB Draw Engine's own account_menu filter and
        // rebuilds the array from this fixed order, so any endpoint not
        // listed here (e.g. RaffleLB Notifications' tab) fell through to the
        // "leftover keys" loop below and landed after Logout instead of
        // where it belongs. Keep this order list in sync with whatever
        // endpoints other RaffleLB plugins add.
        $order = ['dashboard', 'rafflelb-notifications', 'rafflelb-entries', 'orders', self::ENDPOINT, 'edit-address', 'edit-account', 'customer-logout'];
        $out = [];

        foreach ($order as $key) {
            if (isset($items[$key])) $out[$key] = $items[$key];
        }
        foreach ($items as $key => $label) {
            if (!isset($out[$key])) $out[$key] = $label;
        }
        return $out;
    }

    public static function referral_code($user_id) {
        $code = get_user_meta($user_id, '_rafflelb_ref_code', true);

        if (!$code) {
            $code = strtoupper(base_convert($user_id + 100000, 10, 36))
                . strtoupper(wp_generate_password(4, false, false));
            update_user_meta($user_id, '_rafflelb_ref_code', $code);
        }

        return $code;
    }

    public static function find_user_by_code($code) {
        $users = get_users([
            'meta_key' => '_rafflelb_ref_code',
            'meta_value' => sanitize_text_field($code),
            'number' => 1,
            'fields' => 'ids',
        ]);

        return $users ? (int) $users[0] : 0;
    }

    public static function capture_referral() {
        if (is_admin() || empty($_GET['ref'])) return;

        $settings = self::settings();
        if ($settings['enabled'] !== 'yes') return;

        $code = strtoupper(sanitize_text_field(wp_unslash($_GET['ref'])));
        $referrer = self::find_user_by_code($code);

        if (!$referrer) return;
        if (is_user_logged_in() && get_current_user_id() === $referrer) return;

        $days = max(1, (int) $settings['cookie_days']);

        setcookie(
            self::COOKIE,
            $code,
            time() + DAY_IN_SECONDS * $days,
            COOKIEPATH ?: '/',
            COOKIE_DOMAIN,
            is_ssl(),
            true
        );

        $_COOKIE[self::COOKIE] = $code;
    }

    public static function attach_referrer_to_user($user_id) {
        if (!$user_id || get_user_meta($user_id, '_rafflelb_referred_by', true)) return;
        if (empty($_COOKIE[self::COOKIE])) return;

        $code = strtoupper(sanitize_text_field(wp_unslash($_COOKIE[self::COOKIE])));
        $referrer = self::find_user_by_code($code);

        if (!$referrer || $referrer === (int) $user_id) return;

        update_user_meta($user_id, '_rafflelb_referred_by', $referrer);
        update_user_meta($user_id, '_rafflelb_referral_joined', current_time('mysql'));
    }

    public static function points($user_id) {
        return max(0, (int) get_user_meta($user_id, '_rafflelb_ref_points', true));
    }

    public static function adjust_points($user_id, $delta) {
        $current = self::points($user_id);
        $new = max(0, $current + (int) $delta);
        update_user_meta($user_id, '_rafflelb_ref_points', $new);
        return $new;
    }

    public static function points_required_for_amount($amount) {
        $settings = self::settings();
        $rate = max(0.01, (float) $settings['spend_points_per_dollar']);
        return (int) ceil(max(0, (float) $amount) * $rate);
    }

    /**
     * Credit a monetary-value refund as Raffle Points exactly once.
     *
     * The caller supplies a stable, private idempotency key. A unique row in
     * wp_options, the balance mutation, and the immutable ledger row are
     * committed in one database transaction while a per-user advisory lock is
     * held. Retrying the same key therefore returns the original record and
     * cannot issue the credit twice.
     */
    public static function credit_refund($user_id, $amount, $idempotency_key, $description, $context = []) {
        $user_id = absint($user_id);
        $amount = max(0, (float) $amount);
        $idempotency_key = sanitize_text_field((string) $idempotency_key);
        $description = sanitize_text_field((string) $description);
        $points = self::points_required_for_amount($amount);

        if (!$user_id || !get_userdata($user_id)) {
            return new WP_Error('rafflelb_points_invalid_user', __('A valid customer is required for this Points refund.', 'rafflelb-referral-points'));
        }
        if ($amount <= 0 || $points <= 0) {
            return new WP_Error('rafflelb_points_zero_refund', __('The refundable paid amount must be greater than zero.', 'rafflelb-referral-points'));
        }
        if ($idempotency_key === '' || $description === '') {
            return new WP_Error('rafflelb_points_invalid_refund', __('The refund key and ledger description are required.', 'rafflelb-referral-points'));
        }

        global $wpdb;
        $marker_name = '_rafflelb_points_credit_' . hash('sha256', $idempotency_key);
        $lock_name = 'rafflelb_points_user_' . $user_id;
        $locked = (int) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, %d)', $lock_name, 5));
        if ($locked !== 1) {
            return new WP_Error('rafflelb_points_refund_lock', __('The Points account is busy. Please retry the refund.', 'rafflelb-referral-points'));
        }

        try {
            $wpdb->query('START TRANSACTION');
            $existing_value = $wpdb->get_var($wpdb->prepare(
                "SELECT option_value FROM {$wpdb->options} WHERE option_name=%s LIMIT 1 FOR UPDATE",
                $marker_name
            ));
            if ($existing_value !== null) {
                $wpdb->query('COMMIT');
                $existing = maybe_unserialize($existing_value);
                return [
                    'credited'  => false,
                    'duplicate' => true,
                    'points'    => absint(is_array($existing) ? ($existing['points'] ?? 0) : 0),
                    'record'    => is_array($existing) ? $existing : [],
                ];
            }

            $balance_row = $wpdb->get_row($wpdb->prepare(
                "SELECT umeta_id, meta_value FROM {$wpdb->usermeta}
                 WHERE user_id=%d AND meta_key=%s ORDER BY umeta_id ASC LIMIT 1 FOR UPDATE",
                $user_id,
                '_rafflelb_ref_points'
            ));
            $before = $balance_row ? max(0, (int) $balance_row->meta_value) : 0;
            $after = $before + $points;

            if ($balance_row) {
                $balance_saved = $wpdb->query($wpdb->prepare(
                    "UPDATE {$wpdb->usermeta} SET meta_value=%s WHERE user_id=%d AND meta_key=%s",
                    (string) $after,
                    $user_id,
                    '_rafflelb_ref_points'
                ));
                $balance_saved = $balance_saved !== false;
            } else {
                $balance_saved = (bool) $wpdb->insert(
                    $wpdb->usermeta,
                    ['user_id' => $user_id, 'meta_key' => '_rafflelb_ref_points', 'meta_value' => (string) $after],
                    ['%d', '%s', '%s']
                );
            }

            $private_context = [];
            foreach (['raffle_id', 'product_id', 'order_id'] as $key) {
                if (isset($context[$key])) $private_context[$key] = absint($context[$key]);
            }
            $record = [
                'version'     => 1,
                'type'        => sanitize_key((string) ($context['type'] ?? 'refund')),
                'points'      => $points,
                'amount'      => wc_format_decimal($amount, function_exists('wc_get_price_decimals') ? wc_get_price_decimals() : 2),
                'before'      => $before,
                'after'       => $after,
                'description' => $description,
                'context'     => $private_context,
                'created_at'  => current_time('mysql', true),
            ];
            $marker_saved = (bool) $wpdb->insert(
                $wpdb->options,
                ['option_name' => $marker_name, 'option_value' => maybe_serialize($record), 'autoload' => 'no'],
                ['%s', '%s', '%s']
            );
            $ledger_saved = (bool) $wpdb->insert(
                $wpdb->usermeta,
                ['user_id' => $user_id, 'meta_key' => '_rafflelb_points_ledger_entry', 'meta_value' => maybe_serialize($record)],
                ['%d', '%s', '%s']
            );

            if (!$balance_saved || !$marker_saved || !$ledger_saved) {
                $wpdb->query('ROLLBACK');
                return new WP_Error('rafflelb_points_refund_save', __('The Points refund could not be committed. No credit was issued.', 'rafflelb-referral-points'));
            }

            $wpdb->query('COMMIT');
            clean_user_cache($user_id);
            wp_cache_delete($marker_name, 'options');
            do_action('rafflelb_points_refund_credited', $user_id, $points, $amount, $description, $private_context);

            return [
                'credited'  => true,
                'duplicate' => false,
                'points'    => $points,
                'record'    => $record,
            ];
        } finally {
            $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock_name));
        }
    }

    public static function maybe_award_order_points($order_id) {
        if (!function_exists('wc_get_order')) return;

        $order = wc_get_order($order_id);
        if (!$order) return;

        if ($order->get_meta('_rafflelb_ref_points_awarded', true)) return;

        // Orders paid entirely with Raffle Points do not generate new referral points.
        if ($order->get_payment_method() === 'rafflelb_points') return;

        $customer_id = (int) $order->get_customer_id();
        if (!$customer_id) return;

        $referrer = (int) get_user_meta($customer_id, '_rafflelb_referred_by', true);
        if (!$referrer || $referrer === $customer_id) return;

        $settings = self::settings();
        if ($settings['enabled'] !== 'yes') return;

        $amount = max(0, (float) $order->get_total());
        $points = (int) floor($amount * max(0, (float) $settings['points_per_dollar']));

        if ($points <= 0) return;

        self::adjust_points($referrer, $points);

        $order->update_meta_data('_rafflelb_ref_points_awarded', $points);
        $order->update_meta_data('_rafflelb_referrer_user_id', $referrer);
        $order->add_order_note(sprintf(
            'RaffleLB referral reward: %d point(s) awarded to user #%d.',
            $points,
            $referrer
        ));
        $order->save();
    }

    public static function maybe_reverse_order_points($order_id) {
        if (!function_exists('wc_get_order')) return;

        $order = wc_get_order($order_id);
        if (!$order) return;

        $points = (int) $order->get_meta('_rafflelb_ref_points_awarded', true);
        $referrer = (int) $order->get_meta('_rafflelb_referrer_user_id', true);

        if ($points <= 0 || !$referrer) return;
        if ($order->get_meta('_rafflelb_ref_points_reversed', true)) return;

        self::adjust_points($referrer, -$points);

        $order->update_meta_data('_rafflelb_ref_points_reversed', 'yes');
        $order->add_order_note(sprintf(
            'RaffleLB referral reward reversed: %d point(s) removed from user #%d.',
            $points,
            $referrer
        ));
        $order->save();
    }

    public static function restore_points_payment($order_id) {
        if (!function_exists('wc_get_order')) return;

        $order = wc_get_order($order_id);
        if (!$order) return;

        if ($order->get_payment_method() !== 'rafflelb_points') return;
        if (!$order->get_meta('_rafflelb_points_payment_deducted', true)) return;
        if ($order->get_meta('_rafflelb_points_payment_restored', true)) return;

        $points = (int) $order->get_meta('_rafflelb_points_payment_amount', true);
        $user_id = (int) $order->get_meta('_rafflelb_points_payment_user', true);

        if ($points <= 0 || !$user_id) return;

        self::adjust_points($user_id, $points);

        $order->update_meta_data('_rafflelb_points_payment_restored', 'yes');
        $order->add_order_note(sprintf(
            'Raffle Points restored: %d point(s) returned to user #%d.',
            $points,
            $user_id
        ));
        $order->save();
    }

    public static function register_points_gateway($gateways) {
        if (class_exists('RaffleLB_Points_Gateway')) {
            $gateways[] = 'RaffleLB_Points_Gateway';
        }
        return $gateways;
    }

    /**
     * A checkout refresh may retain a previously selected Points method after
     * the cart total or the customer's balance changes. Once the gateway is no
     * longer available, replace that stale session value with WooCommerce's
     * first currently available gateway (in its already-filtered order).
     */
    public static function replace_unavailable_points_gateway_choice($gateways) {
        if (!function_exists('WC') || !WC()->session) return $gateways;
        if ((string) WC()->session->get('chosen_payment_method') !== 'rafflelb_points') return $gateways;
        if (isset($gateways['rafflelb_points'])) return $gateways;

        foreach ($gateways as $gateway_id => $gateway) {
            WC()->session->set('chosen_payment_method', $gateway_id);
            return $gateways;
        }

        WC()->session->__unset('chosen_payment_method');
        return $gateways;
    }

    /**
     * Keep the customer's balance context visible at checkout without exposing
     * an insufficient Points balance as a selectable WooCommerce gateway.
     */
    public static function render_unavailable_points_gateway_notice() {
        if (!is_user_logged_in() || !function_exists('WC') || !WC()->cart) return;

        $total = (float) WC()->cart->get_total('edit');
        $required = self::points_required_for_amount($total);
        $balance = self::points(get_current_user_id());

        if ($required <= 0 || $balance >= $required) return;

        echo '<div class="rafflelb-points-payment-box rafflelb-points-payment-unavailable">';
        echo '<strong>Raffle Points unavailable for this order</strong>';
        echo '<div class="rl-points-row"><span class="rl-points-label">Raffle Points balance</span><strong class="rl-points-value">' . esc_html($balance) . '</strong></div>';
        echo '<div class="rl-points-row"><span class="rl-points-label">Points required</span><strong class="rl-points-value">' . esc_html($required) . '</strong></div>';
        echo '<p class="rl-points-status">You need <strong>' . esc_html($required - $balance) . '</strong> more Raffle Points to use this payment method.</p>';
        echo '</div>';
    }

    public static function referred_customers($referrer_id) {
        return get_users([
            'meta_key' => '_rafflelb_referred_by',
            'meta_value' => $referrer_id,
            'fields' => ['ID', 'display_name', 'user_registered'],
            'number' => 100,
        ]);
    }

    public static function successful_referrals($referrer_id) {
        if (!function_exists('wc_get_orders')) return 0;

        $count = 0;

        foreach (self::referred_customers($referrer_id) as $user) {
            $orders = wc_get_orders([
                'customer_id' => $user->ID,
                'status' => ['processing', 'completed'],
                'limit' => 1,
                'return' => 'ids',
            ]);

            if ($orders) $count++;
        }

        return $count;
    }

    public static function account_page_premium_v125() {
        if (!is_user_logged_in()) return;

        $user_id = get_current_user_id();
        $settings = self::settings();
        $code = self::referral_code($user_id);
        $link = add_query_arg('ref', rawurlencode($code), home_url('/'));
        $points = self::points($user_id);
        $earn_rate = max(0, (float)$settings['points_per_dollar']);
        $spend_rate = max(0.01, (float)$settings['spend_points_per_dollar']);
        $customers = self::referred_customers($user_id);
        $joined = count($customers);
        $activity = [];
        $success = 0;
        $earned_total = 0;

        foreach ($customers as $customer) {
            $orders = function_exists('wc_get_orders') ? wc_get_orders([
                'customer_id' => $customer->ID,
                'status'      => ['processing', 'completed'],
                'limit'       => 20,
                'orderby'     => 'date',
                'order'       => 'DESC',
            ]) : [];
            $qualified = !empty($orders);
            if ($qualified) $success++;
            $customer_points = 0;
            $latest_order = $qualified ? reset($orders) : false;
            foreach ($orders as $order) {
                if ((int)$order->get_meta('_rafflelb_referrer_user_id', true) !== $user_id) continue;
                if ($order->get_meta('_rafflelb_ref_points_reversed', true)) continue;
                $customer_points += max(0, (int)$order->get_meta('_rafflelb_ref_points_awarded', true));
            }
            $earned_total += $customer_points;
            $activity[] = [
                'name'      => $customer->display_name ?: ('Member #' . $customer->ID),
                'joined'    => $customer->user_registered,
                'qualified' => $qualified,
                'points'    => $customer_points,
                'order'     => $latest_order,
            ];
        }
        usort($activity, static function($a, $b) {
            return strtotime((string)$b['joined']) <=> strtotime((string)$a['joined']);
        });

        $conversion = $joined > 0 ? (int)round(($success / $joined) * 100) : 0;
        $cash_value = $points / $spend_rate;
        $example_purchase = $earn_rate > 0 ? ($spend_rate / $earn_rate) : 0;
        $example_points = $spend_rate;
        $fmt = static function($number) {
            return rtrim(rtrim(number_format((float)$number, 2, '.', ','), '0'), '.');
        };
        $ticket_icon = plugins_url('assets/rafflelb-ticket-icon.webp', __FILE__);

        echo '<section class="rlre" aria-label="Refer and Earn">';
        echo '<header class="rlre-hero"><div><span class="rlre-eyebrow">GROW YOUR REWARDS</span><h2>Refer <em>&amp; Earn</em></h2><p>Invite your friends, give them the chance to win, and earn Raffle Points from every qualifying purchase they complete.</p></div><aside class="rlre-hero-process" aria-label="Referral journey"><span>REFERRAL JOURNEY</span><div><b><i>01</i><small>SHARE</small></b><em aria-hidden="true"></em><b><i>02</i><small>CONNECT</small></b><em aria-hidden="true"></em><b><i>03</i><small>EARN</small></b></div></aside></header>';

        echo '<div class="rlre-stats">';
        echo '<article><i class="is-brand" aria-hidden="true"><img src="' . esc_url($ticket_icon) . '" alt=""></i><strong>' . esc_html(number_format_i18n($points)) . '</strong><h3>Raffle Points</h3><p>Available balance</p></article>';
        echo '<article><i class="is-feature" aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="9" cy="8" r="3"/><path d="M3.5 19v-1.2A4.8 4.8 0 0 1 8.3 13h1.4a4.8 4.8 0 0 1 4.8 4.8V19"/><circle cx="17.5" cy="8.5" r="2.4"/><path d="M16.3 13.4h1.1a4.1 4.1 0 0 1 4.1 4.1V19"/></svg></i><strong>' . esc_html($joined) . '</strong><h3>Friends Joined</h3><p>Accounts connected to you</p></article>';
        echo '<article><i class="is-feature" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M12 3 20 6v5c0 5-3.4 8.8-8 10-4.6-1.2-8-5-8-10V6l8-3Z"/><path d="m8.5 12 2.2 2.2 4.8-5"/></svg></i><strong>' . esc_html($success) . '</strong><h3>Qualified Referrals</h3><p>Completed qualifying orders</p></article>';
        echo '<article><i class="is-feature" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M4 20V10M10 20v-6M16 20V8M22 20H2"/><path d="m4 7 5-4 5 3 6-4"/><path d="m16.5 2 3.5.1-.2 3.5"/></svg></i><strong>' . esc_html($conversion) . '%</strong><h3>Qualification Rate</h3><p>Joined friends who qualified</p></article>';
        echo '</div>';

        echo '<div class="rlre-main-grid">';
        echo '<article class="rlre-panel rlre-share"><div class="rlre-panel-title"><i class="is-share" aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="18" cy="5" r="2.75"/><circle cx="6" cy="12" r="2.75"/><circle cx="18" cy="19" r="2.75"/><path d="m8.45 10.68 7.1-4.2M8.45 13.32l7.1 4.2"/></svg></i><div><h3>Your Personal Link</h3><p>Share this link with friends so their account connects to yours.</p></div></div>';
        echo '<div class="rlre-link"><input id="rl-ref-url" aria-label="Your RaffleLB referral link" readonly value="' . esc_attr($link) . '"><button type="button" id="rl-copy-ref"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="9" y="9" width="11" height="11" rx="2"/><path d="M15 9V6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v7a2 2 0 0 0 2 2h3"/></svg><span class="rlre-copy-label">COPY LINK</span></button></div>';
        echo '<div class="rlre-share-actions"><span>Or share directly</span><div><a class="is-whatsapp" target="_blank" rel="noopener" href="https://wa.me/?text=' . rawurlencode('Join RaffleLB: ' . $link) . '" aria-label="Share on WhatsApp"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.075-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.876 1.213 3.074.149.198 2.095 3.2 5.076 4.487.709.306 1.262.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.981.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.89-9.884a9.82 9.82 0 0 1 6.988 2.892 9.825 9.825 0 0 1 2.9 6.988c-.003 5.45-4.437 9.884-9.884 9.884m8.413-18.297A11.815 11.815 0 0 0 12.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 0 0-3.48-8.413Z"/></svg><span>WHATSAPP</span></a></div></div>';
        echo '</article>';

        echo '<article class="rlre-panel rlre-wallet"><div class="rlre-panel-title"><i class="is-brand" aria-hidden="true"><img src="' . esc_url($ticket_icon) . '" alt=""></i><div><h3>Points Wallet</h3><p>Your referral reward balance</p></div></div>';
        echo '<div class="rlre-wallet-value"><strong>' . esc_html(number_format_i18n($points)) . '</strong><span>POINTS</span></div>';
        echo '<div class="rlre-wallet-lines"><div><span>Checkout value</span><strong>' . wp_kses_post(wc_price($cash_value)) . '</strong></div><div><span>Referral points earned</span><strong>' . esc_html(number_format_i18n($earned_total)) . '</strong></div><div><span>Redemption rate</span><strong>' . esc_html($fmt($spend_rate)) . ' pts = $1</strong></div></div>';
        echo '</article>';
        echo '</div>';

        echo '<section class="rlre-reward-rule"><div class="rlre-rule-icon" aria-hidden="true">$</div><div><span>YOUR REFERRAL REWARD</span><h3>Every $' . esc_html($fmt($example_purchase)) . ' purchase earns you $1 equivalent in points.</h3><p>When a friend joins through your link and completes a qualifying paid purchase of $' . esc_html($fmt($example_purchase)) . ', you receive ' . esc_html($fmt($example_points)) . ' Raffle Points. At the current rate, those points equal $1 at checkout.</p></div><div class="rlre-rule-rate"><strong>10%</strong><span>REWARD VALUE</span></div></section>';

        echo '<section class="rlre-how"><div class="rlre-section-title"><span>HOW IT WORKS</span><h3>Three simple steps</h3></div><div class="rlre-steps">';
        echo '<article><i>1</i><div><h4>Share your link</h4><p>Send your personal referral link to a friend.</p></div></article>';
        echo '<article><i>2</i><div><h4>Your friend joins</h4><p>Their new account is securely connected to yours.</p></div></article>';
        echo '<article><i>3</i><div><h4>Earn from purchases</h4><p>You receive points after their qualifying order is paid.</p></div></article>';
        echo '</div></section>';

        echo '<section class="rlre-activity"><div class="rlre-activity-head"><div class="rlre-section-title"><span>REFERRAL ACTIVITY</span><h3>Your latest referrals</h3></div><strong>' . esc_html($joined) . ' TOTAL</strong></div>';
        if (!$activity) {
            echo '<div class="rlre-empty"><i class="rlre-empty-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="9" cy="8" r="3.5"/><path d="M3.5 20v-1.5A5.5 5.5 0 0 1 9 13a5.5 5.5 0 0 1 5.5 5.5V20"/><path d="M19 8v6M16 11h6"/></svg></i><div><strong>No referrals yet</strong><p>Share your personal link to start earning Raffle Points.</p></div></div>';
        } else {
            echo '<div class="rlre-activity-list">';
            foreach (array_slice($activity, 0, 8) as $item) {
                $initial = function_exists('mb_substr') ? mb_substr($item['name'], 0, 1) : substr($item['name'], 0, 1);
                $date = date_i18n(get_option('date_format'), strtotime((string)$item['joined']));
                echo '<article><span class="rlre-person-icon">' . esc_html(strtoupper($initial)) . '</span><div><strong>' . esc_html($item['name']) . '</strong><small>Joined ' . esc_html($date) . '</small></div><span class="' . ($item['qualified'] ? 'is-qualified' : 'is-joined') . '">' . ($item['qualified'] ? 'QUALIFIED' : 'JOINED') . '</span><b>' . ($item['points'] > 0 ? '+' . esc_html(number_format_i18n($item['points'])) . ' pts' : 'Pending') . '</b></article>';
            }
            echo '</div>';
        }
        echo '</section>';

        echo '<footer class="rlre-note"><i aria-hidden="true">?</i><div><h3>Qualifying referral rules</h3><p>Rewards apply only to paid qualifying orders made by friends connected through your link. Orders paid entirely with Raffle Points do not generate additional referral points. Rewards may be reversed if an order is cancelled, failed, or refunded. Self-referrals are not eligible.</p></div></footer>';
        echo '</section>';
        ?>
        <style id="rafflelb-refer-earn-premium-v125">
        .rlre,.rlre *{box-sizing:border-box}.rlre{--lime:#baff00;--bg:#090c09;--card:#101410;--line:#2b332b;width:100%;color:#fff;font-family:Inter,"Segoe UI",Arial,sans-serif!important;-webkit-font-smoothing:antialiased}.rlre a{text-decoration:none!important}
        .rlre-hero{position:relative;display:flex;align-items:flex-start;justify-content:space-between;gap:30px;padding:4px 2px 22px}.rlre-eyebrow{display:block;margin-bottom:7px;color:var(--lime)!important;font-size:11px!important;font-weight:850!important;letter-spacing:.12em!important}.rlre-hero h2{margin:0!important;color:#fff!important;font-size:43px!important;line-height:1!important;font-weight:900!important;letter-spacing:-.045em!important}.rlre-hero h2 em{color:var(--lime)!important;font-style:normal}.rlre-hero p{max-width:680px;margin:10px 0 0!important;color:#b7beb4!important;font-size:15px!important;line-height:1.55!important}.rlre-hero>strong{margin-top:7px;color:var(--lime)!important;font-size:20px!important;line-height:1.12!important;font-weight:800!important;font-style:italic;letter-spacing:-.02em!important;text-align:right;transform:rotate(-4deg)}.rlre-hero>strong b{color:#fff!important;font-weight:inherit!important}
        .rlre-stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin-bottom:14px}.rlre-stats article{min-width:0;padding:18px 16px;border:1px solid var(--line);border-radius:14px;background:linear-gradient(145deg,#131713,#0c100c);text-align:center}.rlre-stats article:first-child{border-color:rgba(186,255,0,.34);background:linear-gradient(145deg,rgba(186,255,0,.10),#0d110d)}.rlre-stats i{display:flex;width:34px;height:34px;align-items:center;justify-content:center;margin:0 auto 8px;border:1px solid rgba(186,255,0,.35);border-radius:10px;color:var(--lime)!important;font-size:16px!important;font-style:normal!important;font-weight:850!important}.rlre-stats strong{display:block;color:var(--lime)!important;font-size:29px!important;line-height:1!important;font-weight:850!important}.rlre-stats h3{margin:7px 0 3px!important;color:#fff!important;font-size:15px!important;line-height:1.2!important;font-weight:750!important}.rlre-stats p{margin:0!important;color:#9fa79c!important;font-size:11px!important;line-height:1.35!important}
        .rlre-main-grid{display:grid;grid-template-columns:minmax(0,1.45fr) minmax(300px,.55fr);gap:14px;margin-bottom:14px}.rlre-panel,.rlre-reward-rule,.rlre-how,.rlre-activity,.rlre-note{border:1px solid var(--line);border-radius:15px;background:linear-gradient(145deg,#111511,#0c100c)}.rlre-panel{padding:20px}.rlre-share{background:radial-gradient(circle at 95% 0,rgba(186,255,0,.075),transparent 34%),linear-gradient(145deg,#111511,#0c100c)}.rlre-panel-title{display:flex;align-items:center;gap:12px}.rlre-panel-title>i{display:flex;width:38px;height:38px;flex:0 0 38px;align-items:center;justify-content:center;border:1px solid rgba(186,255,0,.36);border-radius:10px;color:var(--lime)!important;font-size:19px!important;font-style:normal!important}.rlre-panel-title h3{margin:0!important;color:#fff!important;font-size:18px!important;line-height:1.2!important;font-weight:750!important}.rlre-panel-title p{margin:4px 0 0!important;color:#aab2a7!important;font-size:12px!important;line-height:1.35!important}.rlre-link{display:flex;gap:9px;margin-top:18px}.rlre-link input{flex:1;min-width:0;height:49px!important;margin:0!important;padding:0 14px!important;border:1px solid #303830!important;border-radius:10px!important;background:#080b08!important;color:#fff!important;-webkit-text-fill-color:#fff!important;font-family:Inter,"Segoe UI",Arial,sans-serif!important;font-size:13px!important;box-shadow:none!important}.rlre-link button{display:inline-flex!important;min-width:145px;align-items:center!important;justify-content:center!important;gap:8px!important;border:0!important;border-radius:10px!important;background:var(--lime)!important;color:#050705!important;-webkit-text-fill-color:#050705!important;font-family:Inter,"Segoe UI",Arial,sans-serif!important;font-size:12px!important;font-weight:850!important}.rlre-share-actions{margin-top:14px}.rlre-share-actions>span{display:block;margin-bottom:8px;color:#a2aaa0!important;font-size:11px!important}.rlre-share-actions>div{display:flex;gap:8px}.rlre-share-actions a,.rlre-mini-copy{display:flex!important;width:38px!important;height:38px!important;align-items:center!important;justify-content:center!important;border:1px solid #394139!important;border-radius:50%!important;background:#171b17!important;color:#fff!important;-webkit-text-fill-color:#fff!important;font-family:Inter,Arial,sans-serif!important;font-size:14px!important;font-weight:800!important}.rlre-share-actions .is-whatsapp{border-color:rgba(37,211,102,.55)!important;background:#25d366!important;color:#fff!important}.rlre-mini-copy{margin:0!important;padding:0!important;cursor:pointer!important}
        .rlre-wallet-value{display:flex;align-items:baseline;gap:8px;margin:19px 0 14px}.rlre-wallet-value strong{color:var(--lime)!important;font-size:39px!important;line-height:1!important;font-weight:850!important;letter-spacing:-.035em!important}.rlre-wallet-value span{color:#d8ded5!important;font-size:11px!important;font-weight:750!important;letter-spacing:.08em!important}.rlre-wallet-lines{display:grid;gap:0;border-top:1px solid rgba(255,255,255,.08)}.rlre-wallet-lines>div{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:9px 0;border-bottom:1px solid rgba(255,255,255,.07)}.rlre-wallet-lines span{color:#9ea69b!important;font-size:11px!important}.rlre-wallet-lines strong,.rlre-wallet-lines strong *{color:#fff!important;font-size:12px!important;font-weight:700!important}
        .rlre-reward-rule{display:grid;grid-template-columns:52px minmax(0,1fr) auto;align-items:center;gap:16px;margin-bottom:14px;padding:18px 20px;border-color:rgba(186,255,0,.26);background:radial-gradient(circle at 100% 50%,rgba(186,255,0,.085),transparent 26%),#0e120e}.rlre-rule-icon{display:flex;width:50px;height:50px;align-items:center;justify-content:center;border-radius:50%;background:var(--lime);color:#050705!important;font-size:22px!important;font-weight:900!important}.rlre-reward-rule>div:nth-child(2)>span{display:block;margin-bottom:5px;color:var(--lime)!important;font-size:10px!important;font-weight:850!important;letter-spacing:.11em!important}.rlre-reward-rule h3{margin:0!important;color:#fff!important;font-size:20px!important;line-height:1.25!important;font-weight:800!important}.rlre-reward-rule p{margin:6px 0 0!important;color:#abb3a8!important;font-size:12px!important;line-height:1.5!important}.rlre-rule-rate{min-width:95px;padding-left:18px;border-left:1px solid rgba(255,255,255,.10);text-align:center}.rlre-rule-rate strong{display:block;color:var(--lime)!important;font-size:28px!important;line-height:1!important;font-weight:900!important}.rlre-rule-rate span{display:block;margin-top:5px;color:#aab2a7!important;font-size:9px!important;font-weight:800!important;letter-spacing:.08em!important}
        .rlre-how{margin-bottom:14px;padding:19px 20px}.rlre-section-title>span{display:block;margin-bottom:5px;color:var(--lime)!important;font-size:10px!important;font-weight:850!important;letter-spacing:.11em!important}.rlre-section-title h3{margin:0!important;color:#fff!important;font-size:20px!important;font-weight:800!important}.rlre-steps{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px;margin-top:15px}.rlre-steps article{display:flex;align-items:center;gap:11px;min-width:0;padding:13px;border:1px solid rgba(255,255,255,.08);border-radius:11px;background:rgba(255,255,255,.018)}.rlre-steps i{display:flex;width:34px;height:34px;flex:0 0 34px;align-items:center;justify-content:center;border-radius:50%;background:var(--lime);color:#050705!important;font-size:12px!important;font-style:normal!important;font-weight:850!important}.rlre-steps h4{margin:0 0 3px!important;color:#fff!important;font-size:13px!important;font-weight:750!important}.rlre-steps p{margin:0!important;color:#9da59b!important;font-size:10px!important;line-height:1.4!important}
        .rlre-activity{margin-bottom:14px;padding:19px 20px}.rlre-activity-head{display:flex;align-items:flex-end;justify-content:space-between;gap:16px;margin-bottom:14px}.rlre-activity-head>strong{color:var(--lime)!important;font-size:11px!important;font-weight:800!important;letter-spacing:.08em!important}.rlre-activity-list{overflow:hidden;border:1px solid rgba(255,255,255,.08);border-radius:11px}.rlre-activity-list article{display:grid;grid-template-columns:38px minmax(0,1fr) 96px 90px;align-items:center;gap:10px;min-height:58px;padding:9px 12px;border-top:1px solid rgba(255,255,255,.07)}.rlre-activity-list article:first-child{border-top:0}.rlre-person-icon{display:flex;width:34px;height:34px;align-items:center;justify-content:center;border-radius:50%;background:#2b302b;color:#fff!important;font-size:11px!important;font-weight:750!important}.rlre-activity-list article>div{min-width:0}.rlre-activity-list article>div strong{display:block;overflow:hidden;color:#fff!important;font-size:12px!important;font-weight:700!important;text-overflow:ellipsis;white-space:nowrap}.rlre-activity-list article>div small{display:block;margin-top:3px;color:#929a90!important;font-size:9px!important}.rlre-activity-list article>span:nth-child(3){display:inline-flex;width:max-content;min-height:24px;align-items:center;padding:0 8px;border-radius:999px;font-size:9px!important;font-weight:800!important}.rlre-activity-list .is-qualified{background:rgba(186,255,0,.09);color:var(--lime)!important}.rlre-activity-list .is-joined{background:rgba(255,255,255,.055);color:#bbc2b8!important}.rlre-activity-list article>b{color:var(--lime)!important;font-size:11px!important;text-align:right}.rlre-empty{display:flex;align-items:center;gap:13px;padding:21px;border:1px solid rgba(255,255,255,.08);border-radius:11px}.rlre-empty>i{display:flex;width:40px;height:40px;align-items:center;justify-content:center;border:1px solid rgba(186,255,0,.34);border-radius:50%;color:var(--lime)!important;font-size:20px!important;font-style:normal!important}.rlre-empty strong{display:block;color:#fff!important;font-size:14px!important}.rlre-empty p{margin:3px 0 0!important;color:#9da69b!important;font-size:11px!important}
        .rlre-note{display:flex;align-items:flex-start;gap:13px;padding:17px 19px}.rlre-note>i{display:flex;width:35px;height:35px;flex:0 0 35px;align-items:center;justify-content:center;border:2px solid var(--lime);border-radius:50%;color:var(--lime)!important;font-size:15px!important;font-style:normal!important;font-weight:850!important}.rlre-note h3{margin:0 0 4px!important;color:#fff!important;font-size:14px!important;font-weight:750!important}.rlre-note p{margin:0!important;color:#9da59a!important;font-size:11px!important;line-height:1.55!important}
        @media(max-width:1050px){.rlre-stats{grid-template-columns:1fr 1fr}.rlre-main-grid{grid-template-columns:1fr}.rlre-hero>strong{font-size:17px!important}}
        @media(max-width:700px){.rlre-hero{display:block;padding-bottom:18px}.rlre-hero h2{font-size:34px!important}.rlre-hero p{font-size:13px!important}.rlre-hero>strong{display:none}.rlre-stats{gap:8px}.rlre-stats article{padding:14px 10px}.rlre-stats strong{font-size:24px!important}.rlre-stats h3{font-size:12px!important}.rlre-stats p{font-size:9px!important}.rlre-panel,.rlre-how,.rlre-activity{padding:15px}.rlre-link{flex-direction:column}.rlre-link button{min-height:45px}.rlre-reward-rule{grid-template-columns:44px minmax(0,1fr);padding:16px}.rlre-rule-icon{width:42px;height:42px}.rlre-rule-rate{grid-column:1/-1;display:flex;align-items:baseline;justify-content:center;gap:7px;padding:11px 0 0;border-left:0;border-top:1px solid rgba(255,255,255,.09)}.rlre-rule-rate strong{font-size:23px!important}.rlre-reward-rule h3{font-size:17px!important}.rlre-steps{grid-template-columns:1fr}.rlre-activity-list article{grid-template-columns:34px minmax(0,1fr) auto;padding:10px}.rlre-activity-list article>b{grid-column:2;text-align:left}.rlre-activity-list article>span:nth-child(3){grid-column:3;grid-row:1/3}.rlre-note{padding:15px}.rlre-note p{font-size:10px!important}}
        @media(max-width:430px){.rlre-stats{grid-template-columns:1fr 1fr}.rlre-stats article{padding:13px 7px}.rlre-stats i{width:30px;height:30px}.rlre-stats strong{font-size:21px!important}.rlre-stats h3{font-size:11px!important}.rlre-panel-title h3{font-size:16px!important}.rlre-wallet-value strong{font-size:32px!important}}
        </style>
        <script id="rafflelb-refer-earn-secondary-copy-v125">
        (function(){document.addEventListener('click',function(e){var b=e.target.closest('[data-rl-copy-secondary]');if(!b)return;var input=document.getElementById('rl-ref-url');if(!input)return;if(navigator.clipboard)navigator.clipboard.writeText(input.value);b.textContent='✓';setTimeout(function(){b.textContent='↗'},1500);});})();
        </script>
        <?php
    }

    public static function account_page() {
        self::account_page_premium_v125();
        return;

        /* Legacy renderer retained below for rollback/reference. */
        if (!is_user_logged_in()) return;

        $user_id = get_current_user_id();
        $settings = self::settings();

        $code = self::referral_code($user_id);
        $link = add_query_arg('ref', rawurlencode($code), home_url('/'));

        $points = self::points($user_id);
        $spend_rate = max(0.01, (float) $settings['spend_points_per_dollar']);
        $customers = self::referred_customers($user_id);
        $success = self::successful_referrals($user_id);
        $joined = count($customers);
        $conversion = $joined > 0 ? (int) round(($success / $joined) * 100) : 0;
        $site_icon = function_exists('get_site_icon_url') ? get_site_icon_url(192) : '';

        echo '<div class="rl-ref-wrap">';

        echo '<div class="rl-ref-head">';
        if ($site_icon) echo '<img class="rl-ref-brand-icon" src="' . esc_url($site_icon) . '" alt="">';
        echo '<div><span>GROW YOUR REWARDS</span>';
        echo '<h2>Refer &amp; Earn</h2>';
        echo '<p>Invite friends with your personal link and track every qualified referral clearly.</p></div>';
        echo '</div>';

        echo '<div class="rl-ref-stats">';
        echo '<div><span>RAFFLE POINTS</span><b>' . esc_html($points) . '</b><small>Available rewards balance</small></div>';
        echo '<div><span>FRIENDS JOINED</span><b>' . esc_html($joined) . '</b><small>Accounts connected to you</small></div>';
        echo '<div><span>QUALIFIED REFERRALS</span><b>' . esc_html($success) . '</b><small>Completed qualifying orders</small></div>';
        echo '<div><span>QUALIFICATION RATE</span><b>' . esc_html($conversion) . '%</b><small>Joined friends who qualified</small></div>';
        echo '</div>';

        echo '<div class="rl-ref-feature-grid">';
        echo '<div class="rl-ref-card rl-ref-share-card">';
        echo '<span class="rl-ref-card-kicker">YOUR PERSONAL LINK</span><h3>Invite friends. Earn rewards.</h3>';
        echo '<p>New members who join through this link are connected to your account.</p>';
        echo '<div class="rl-ref-link"><input id="rl-ref-url" aria-label="Your RaffleLB referral link" readonly value="' . esc_attr($link) . '"><button type="button" id="rl-copy-ref">COPY LINK</button></div>';
        echo '<a class="rl-wa" target="_blank" rel="noopener" href="https://wa.me/?text='
            . rawurlencode('Join RaffleLB: ' . $link)
            . '">SHARE ON WHATSAPP</a>';
        echo '</div>';

        echo '<div class="rl-ref-card rl-ref-wallet-card"><span class="rl-ref-card-kicker">POINTS WALLET</span><h3>Pay with Raffle Points</h3>';
        echo '<div class="rl-ref-wallet-value"><strong>' . esc_html($points) . '</strong><span>POINTS</span></div>';
        echo '<p>Use your points directly at checkout when the balance covers the full order.</p>';
        echo '<p class="rl-ref-rate">Current rate: <strong>'
            . esc_html(rtrim(rtrim(number_format($spend_rate, 2, '.', ''), '0'), '.'))
            . ' Raffle Points per $1</strong>.</p>';
        echo '</div>';
        echo '</div>';

        echo '<div class="rl-ref-card rl-ref-history"><div class="rl-ref-history-head"><div><span class="rl-ref-card-kicker">REFERRAL ACTIVITY</span><h3>Your referrals</h3></div><strong>' . esc_html($joined) . ' TOTAL</strong></div>';

        if (!$customers) {
            echo '<p>No referrals yet. Share your link to get started.</p>';
        } else {
            echo '<div class="rl-ref-table">';
            echo '<div class="rl-ref-tr rl-ref-th"><span>FRIEND</span><span>JOINED</span><span>STATUS</span></div>';

            foreach ($customers as $user) {
                $orders = function_exists('wc_get_orders')
                    ? wc_get_orders([
                        'customer_id' => $user->ID,
                        'status' => ['processing', 'completed'],
                        'limit' => 1,
                        'return' => 'ids'
                    ])
                    : [];

                $status = $orders ? 'QUALIFIED' : 'JOINED';
                $name = $user->display_name ?: ('Member #' . $user->ID);

                echo '<div class="rl-ref-tr">';
                echo '<span>' . esc_html($name) . '</span>';
                echo '<span>' . esc_html(date_i18n(get_option('date_format'), strtotime($user->user_registered))) . '</span>';
                echo '<span class="' . ($orders ? 'ok' : 'pending') . '">' . esc_html($status) . '</span>';
                echo '</div>';
            }

            echo '</div>';
        }

        echo '</div>';

        echo '<p class="rl-ref-note">Referral points are awarded only for qualifying paid orders. Orders paid entirely with Raffle Points do not generate additional referral points. Points may be reversed if a qualifying order is cancelled, failed, or refunded. Self-referrals are not eligible.</p>';

        echo '</div>';
    }

    public static function admin_menu() {
        add_submenu_page(
            'woocommerce',
            'RaffleLB Referrals',
            'RaffleLB Referrals',
            'manage_woocommerce',
            'rafflelb-referrals',
            [__CLASS__, 'admin_page']
        );

        add_submenu_page(
            'woocommerce',
            'RaffleLB User Points',
            'RaffleLB User Points',
            'manage_woocommerce',
            'rafflelb-user-points',
            [__CLASS__, 'admin_user_points_page']
        );
    }

    public static function register_settings() {
        register_setting(
            'rafflelb_referral_group',
            self::OPT,
            [__CLASS__, 'sanitize_settings']
        );
    }

    public static function sanitize_settings($value) {
        return [
            'enabled' => (($value['enabled'] ?? '') === 'yes') ? 'yes' : 'no',
            'points_per_dollar' => max(0, (float) ($value['points_per_dollar'] ?? 1)),
            'spend_points_per_dollar' => max(0.01, (float) ($value['spend_points_per_dollar'] ?? 10)),
            'cookie_days' => max(1, (int) ($value['cookie_days'] ?? 30)),
        ];
    }

    public static function admin_page() {
        if (!current_user_can('manage_woocommerce')) return;

        $settings = self::settings();

        echo '<div class="wrap">';
        echo '<h1>RaffleLB Referral &amp; Points</h1>';
        echo '<form method="post" action="options.php">';

        settings_fields('rafflelb_referral_group');

        echo '<table class="form-table">';

        echo '<tr><th>Program enabled</th><td><label>';
        echo '<input type="checkbox" name="' . esc_attr(self::OPT) . '[enabled]" value="yes" '
            . checked($settings['enabled'], 'yes', false)
            . '> Enable referral program and Raffle Points payment';
        echo '</label></td></tr>';

        echo '<tr><th>Points earned per $1</th><td>';
        echo '<input type="number" min="0" step="0.1" name="' . esc_attr(self::OPT) . '[points_per_dollar]" value="' . esc_attr($settings['points_per_dollar']) . '">';
        echo '<p class="description">Points awarded to the referrer for each $1 in a qualifying paid order.</p>';
        echo '</td></tr>';

        echo '<tr><th>Points needed per $1 at checkout</th><td>';
        echo '<input type="number" min="0.01" step="0.01" name="' . esc_attr(self::OPT) . '[spend_points_per_dollar]" value="' . esc_attr($settings['spend_points_per_dollar']) . '">';
        echo '<p class="description">Example: 10 means a $10 order costs 100 Raffle Points.</p>';
        echo '</td></tr>';

        echo '<tr><th>Referral cookie duration</th><td>';
        echo '<input type="number" min="1" name="' . esc_attr(self::OPT) . '[cookie_days]" value="' . esc_attr($settings['cookie_days']) . '"> days';
        echo '</td></tr>';

        echo '</table>';

        submit_button();

        echo '</form>';
        echo '<p><strong>Payment rule:</strong> Raffle Points can pay an order only when the customer has enough points for the full order total. Split payments are not enabled in this version.</p>';
        echo '<p><strong>Referral rule:</strong> qualifying cash/card paid orders can earn referral points. Orders paid with Raffle Points do not create new referral points.</p>';
        echo '</div>';
    }

    public static function admin_user_points_page() {
        if (!current_user_can('manage_woocommerce')) return;

        $search = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
        $paged  = isset($_GET['paged']) ? max(1, (int) $_GET['paged']) : 1;
        $per_page = 50;

        $args = [
            'number' => $per_page,
            'offset' => ($paged - 1) * $per_page,
            'orderby' => 'registered',
            'order' => 'DESC',
            'count_total' => true,
        ];

        if ($search !== '') {
            $args['search'] = '*' . $search . '*';
            $args['search_columns'] = ['user_login', 'user_email', 'display_name'];
        }

        $query = new WP_User_Query($args);
        $users = $query->get_results();
        $total = (int) $query->get_total();
        $pages = max(1, (int) ceil($total / $per_page));

        echo '<div class="wrap">';
        echo '<h1>RaffleLB User Points</h1>';
        echo '<p>View the current Raffle Points balance for every registered customer.</p>';

        echo '<form method="get" style="margin:16px 0">';
        echo '<input type="hidden" name="page" value="rafflelb-user-points">';
        echo '<input type="search" name="s" value="' . esc_attr($search) . '" placeholder="Search name or email" style="min-width:280px"> ';
        submit_button('Search Users', 'secondary', '', false);
        echo '</form>';

        echo '<table class="widefat striped" style="max-width:1100px">';
        echo '<thead><tr><th>User</th><th>Email</th><th style="width:150px">Raffle Points</th><th style="width:180px">Successful Referrals</th><th style="width:180px">Referral Code</th></tr></thead><tbody>';

        if (!$users) {
            echo '<tr><td colspan="5">No users found.</td></tr>';
        } else {
            foreach ($users as $user) {
                $uid = (int) $user->ID;
                $points = self::points($uid);
                $success = self::successful_referrals($uid);
                $code = self::referral_code($uid);
                echo '<tr>';
                echo '<td><strong>' . esc_html($user->display_name ?: $user->user_login) . '</strong><br><small>User #' . esc_html($uid) . '</small></td>';
                echo '<td>' . esc_html($user->user_email) . '</td>';
                echo '<td><strong>' . esc_html($points) . '</strong></td>';
                echo '<td>' . esc_html($success) . '</td>';
                echo '<td><code>' . esc_html($code) . '</code></td>';
                echo '</tr>';
            }
        }
        echo '</tbody></table>';

        if ($pages > 1) {
            echo '<div class="tablenav"><div class="tablenav-pages" style="margin:16px 0">';
            echo paginate_links([
                'base' => add_query_arg(['page' => 'rafflelb-user-points', 's' => $search, 'paged' => '%#%'], admin_url('admin.php')),
                'format' => '',
                'current' => $paged,
                'total' => $pages,
            ]);
            echo '</div></div>';
        }

        echo '</div>';
    }

    public static function styles() {
        if (is_admin()) return;

        echo '<style>
        /* Checkout presentation is intentionally owned by RaffleLB Dark Checkout. */

        /* Floating Refer & Earn launcher - always understandable */
        .rl-ref-float{
            position:fixed;left:22px;bottom:22px;z-index:9998;
            display:flex;align-items:center;gap:10px;
            min-height:52px;padding:6px 16px 6px 7px;
            background:#c8ff00!important;
            border:1px solid rgba(0,0,0,.18);
            border-radius:999px;
            box-shadow:0 10px 30px rgba(0,0,0,.30);
            text-decoration:none!important;
            font-family:inherit;
            transition:transform .18s ease,box-shadow .18s ease;
        }
        .rl-ref-float:hover,.rl-ref-float:focus{
            transform:translateY(-2px);
            box-shadow:0 14px 34px rgba(0,0,0,.34);
        }
        .rl-ref-float-icon{
            width:38px;height:38px;border-radius:50%;
            display:flex;align-items:center;justify-content:center;
            background:#090b09!important;color:#c8ff00!important;
            -webkit-text-fill-color:#c8ff00!important;
            border:1px solid rgba(0,0,0,.18);
            font-weight:900;font-size:16px;letter-spacing:-.5px;
            flex:0 0 38px;
        }
        .rl-ref-float-label{
            display:block!important;max-width:none!important;overflow:visible!important;
            white-space:nowrap;opacity:1!important;padding:0!important;
            background:transparent!important;border:0!important;border-radius:0!important;
            color:#050605!important;-webkit-text-fill-color:#050605!important;
            font-size:12px;font-weight:900;letter-spacing:.65px;line-height:1;
        }
        .rl-ref-float-label:after{
            content:"  •  EARN POINTS";
            font-size:9px;font-weight:800;letter-spacing:.45px;opacity:.72;
        }
        @media(max-width:767px){
            .rl-ref-float{
                left:max(12px,env(safe-area-inset-left));
                bottom:calc(18px + env(safe-area-inset-bottom));
                width:132px;min-width:132px;max-width:132px;
                height:44px;min-height:44px;max-height:44px;
                padding:4px 10px 4px 4px;gap:7px;
                justify-content:flex-start;
                border-radius:999px;
                box-sizing:border-box;
                box-shadow:0 6px 18px rgba(0,0,0,.26);
                pointer-events:auto;
            }
            .rl-ref-float-icon{width:34px;height:34px;flex:0 0 34px;font-size:15px}
            .rl-ref-float-label{position:static!important;display:block!important;width:auto!important;height:auto!important;max-width:none!important;margin:0!important;padding:0!important;overflow:visible!important;clip:auto!important;clip-path:none!important;white-space:nowrap!important;color:#050605!important;-webkit-text-fill-color:#050605!important;font-size:10px!important;font-weight:900!important;line-height:1!important;letter-spacing:.25px!important}
            .rl-ref-float-label:after{display:none}
        }
        </style>';

        if (!function_exists('is_account_page') || !is_account_page()) return;

        echo '<style>
        .rl-ref-wrap{--lime:#c8ff00;--bg:#0b0d0b;--card:#111411;--line:#2b302b;color:#f5f5f2;max-width:1050px}
        .rl-ref-wrap *{box-sizing:border-box}
        .rl-ref-head{padding:28px;border:1px solid var(--line);border-radius:18px;background:linear-gradient(135deg,#111411,#0b0d0b)}
        .rl-ref-head>span{color:var(--lime);font-weight:900;letter-spacing:2px;font-size:12px}
        .rl-ref-head h2{color:#fff!important;font-size:32px;margin:8px 0}
        .rl-ref-head p{color:#b9beb9!important;margin:0}
        .rl-ref-stats{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin:16px 0}
        .rl-ref-stats>div,.rl-ref-card{background:var(--card);border:1px solid var(--line);border-radius:16px;padding:22px}
        .rl-ref-stats b{display:block;color:var(--lime);font-size:28px}
        .rl-ref-stats span{color:#929992;font-size:11px;letter-spacing:1px;font-weight:800}
        .rl-ref-card{margin:16px 0}
        .rl-ref-card h3{color:#fff!important;margin:0 0 8px;font-size:19px}
        .rl-ref-card p,.rl-ref-card small{color:#b8beb8!important}
        .rl-ref-card strong{color:#fff}
        .rl-ref-rate{margin-bottom:0!important}
        .rl-ref-link{display:flex;gap:10px;margin-top:15px}
        .rl-ref-link input{flex:1;min-width:0;background:#080a08!important;border:1px solid #343a34!important;color:#fff!important;border-radius:10px;padding:13px!important}
        .rl-ref-link button,.rl-wa{background:var(--lime)!important;color:#070807!important;border:0!important;border-radius:999px!important;padding:13px 20px!important;font-weight:900!important;text-decoration:none!important;display:inline-block}
        #rl-copy-ref,#rl-copy-ref:hover,#rl-copy-ref:focus{color:#000!important;-webkit-text-fill-color:#000!important}
        .rl-wa{margin-top:12px;background:transparent!important;color:var(--lime)!important;border:1px solid #5b6b20!important}
        .rl-ref-table{margin-top:12px}
        .rl-ref-tr{display:grid;grid-template-columns:1.4fr 1fr .7fr;gap:14px;padding:12px 4px;border-bottom:1px solid #242824;color:#d8ddd8}
        .rl-ref-th{color:#8f978f;font-size:11px;font-weight:900;letter-spacing:1px}
        .rl-ref-tr .ok{color:var(--lime);font-weight:900}
        .rl-ref-tr .pending{color:#c0c5c0}
        .rl-ref-note{font-size:12px!important;color:#858b85!important}
        @media(max-width:767px){
            .rl-ref-stats{grid-template-columns:1fr}
            .rl-ref-link{flex-direction:column}
            .rl-ref-tr{grid-template-columns:1fr}
            .rl-ref-th{display:none}
            .rl-ref-head h2{font-size:25px}
        }
        </style>';
    }

    public static function scripts() {
        if (is_admin()) return;

        $refer_url = function_exists('wc_get_account_endpoint_url')
            ? wc_get_account_endpoint_url(self::ENDPOINT)
            : home_url('/my-account/' . self::ENDPOINT . '/');

        echo '<a class="rl-ref-float" href="' . esc_url($refer_url) . '" aria-label="Refer & Earn">';
        echo '<span class="rl-ref-float-icon">R</span>';
        echo '<span class="rl-ref-float-label">REFER &amp; EARN</span>';
        echo '</a>';

        if (!function_exists('is_account_page') || !is_account_page()) return;

        echo '<script>
        document.addEventListener("click",function(e){
            var button=e.target&&e.target.closest?e.target.closest("#rl-copy-ref"):null;
            if(button){
                var i=document.getElementById("rl-ref-url");
                if(!i)return;
                if(navigator.clipboard){navigator.clipboard.writeText(i.value);}
                var label=button.querySelector(".rlre-copy-label");
                if(label){
                    label.textContent="COPIED";
                    setTimeout(function(){label.textContent="COPY LINK";},1600);
                }
            }
        });
        </script>';
    }
}

add_action('plugins_loaded', function() {
    if (!class_exists('WooCommerce') || !class_exists('WC_Payment_Gateway')) {
        return;
    }

    if (!class_exists('RaffleLB_Points_Gateway')) {
        class RaffleLB_Points_Gateway extends WC_Payment_Gateway {
            public function __construct() {
                $this->id = 'rafflelb_points';
                $this->method_title = 'Raffle Points';
                $this->method_description = 'Allows logged-in customers to pay the full order total with Raffle Points.';
                $this->has_fields = true;
                $this->supports = ['products'];

                $this->title = 'Pay with Raffle Points';
                $this->description = '';

                $this->enabled = 'yes';
            }

            public function is_available() {
                if (!parent::is_available()) return false;

                $settings = RaffleLB_Referral_Points::settings();
                if ($settings['enabled'] !== 'yes') return false;

                if (!is_user_logged_in()) return false;
                if (!function_exists('WC') || !WC()->cart) return false;

                $total = (float) WC()->cart->get_total('edit');
                if ($total <= 0) return false;

                $required = RaffleLB_Referral_Points::points_required_for_amount($total);
                $balance = RaffleLB_Referral_Points::points(get_current_user_id());

                return $required > 0 && $balance >= $required;
            }

            public function payment_fields() {
                if (!is_user_logged_in()) {
                    echo '<p>Please log in to use Raffle Points.</p>';
                    return;
                }

                $balance = RaffleLB_Referral_Points::points(get_current_user_id());
                $total = (function_exists('WC') && WC()->cart)
                    ? (float) WC()->cart->get_total('edit')
                    : 0;
                $required = RaffleLB_Referral_Points::points_required_for_amount($total);

                echo '<div class="rafflelb-points-payment-box">';
                echo '<div class="rl-points-row"><span class="rl-points-label">Raffle Points balance</span><strong class="rl-points-value">' . esc_html($balance) . '</strong></div>';
                echo '<div class="rl-points-row"><span class="rl-points-label">Points required</span><strong class="rl-points-value">' . esc_html($required) . '</strong></div>';

                if ($balance >= $required && $required > 0) {
                    echo '<p class="rl-points-status rl-points-ok">You have enough Raffle Points to pay for this order.</p>';
                } else {
                    $missing = max(0, $required - $balance);
                    echo '<p class="rl-points-status">You need <strong>' . esc_html($missing) . '</strong> more Raffle Points to use this payment method.</p>';
                }

                echo '<p class="rl-points-note">Your full order will be paid using Raffle Points.</p>';
                echo '</div>';
            }

            public function validate_fields() {
                if (!is_user_logged_in()) {
                    wc_add_notice('Please log in to pay with Raffle Points.', 'error');
                    return false;
                }

                if (!function_exists('WC') || !WC()->cart) {
                    wc_add_notice('Your cart could not be verified. Please refresh checkout and try again.', 'error');
                    return false;
                }

                $required = RaffleLB_Referral_Points::points_required_for_amount((float) WC()->cart->get_total('edit'));
                $balance = RaffleLB_Referral_Points::points(get_current_user_id());

                if ($required <= 0 || $balance < $required) {
                    wc_add_notice('You do not have enough Raffle Points to pay for this order.', 'error');
                    return false;
                }

                return true;
            }

            public function process_payment($order_id) {
                $order = wc_get_order($order_id);

                if (!$order) {
                    wc_add_notice('Unable to process this order. Please try again.', 'error');
                    return ['result' => 'failure'];
                }

                $user_id = get_current_user_id();
                if (!$user_id || (int) $order->get_customer_id() !== (int) $user_id) {
                    wc_add_notice('Your Raffle Points account could not be verified.', 'error');
                    return ['result' => 'failure'];
                }

                // Idempotency: if points were already deducted for this order, do not deduct again.
                if ($order->get_meta('_rafflelb_points_payment_deducted', true)) {
                    return [
                        'result' => 'success',
                        'redirect' => $this->get_return_url($order),
                    ];
                }

                $required = RaffleLB_Referral_Points::points_required_for_amount((float) $order->get_total());
                $balance = RaffleLB_Referral_Points::points($user_id);

                if ($required <= 0 || $balance < $required) {
                    wc_add_notice(
                        sprintf(
                            'You need %d Raffle Points for this order. Your current balance is %d.',
                            $required,
                            $balance
                        ),
                        'error'
                    );
                    return ['result' => 'failure'];
                }

                RaffleLB_Referral_Points::adjust_points($user_id, -$required);

                $order->update_meta_data('_rafflelb_points_payment_amount', $required);
                $order->update_meta_data('_rafflelb_points_payment_user', $user_id);
                $order->update_meta_data('_rafflelb_points_payment_deducted', 'yes');
                $order->add_order_note(sprintf(
                    'Paid with %d Raffle Point(s).',
                    $required
                ));
                $order->save();

                // Mark the order paid so the normal WooCommerce / RaffleLB Draw Engine flow continues.
                $order->payment_complete('raffle-points-' . $order->get_id());

                if (function_exists('WC') && WC()->cart) {
                    WC()->cart->empty_cart();
                }

                return [
                    'result' => 'success',
                    'redirect' => $this->get_return_url($order),
                ];
            }
        }
    }

    RaffleLB_Referral_Points::init();
}, 20);

register_activation_hook(__FILE__, ['RaffleLB_Referral_Points', 'activate']);
register_deactivation_hook(__FILE__, ['RaffleLB_Referral_Points', 'deactivate']);



/* =========================================================
 * v1.1.7 — Admin Manual Raffle Points Manager
 * ========================================================= */
if (!function_exists('rafflelb_points_admin_balance_key')) {
    function rafflelb_points_admin_balance_key() {
        return '_rafflelb_ref_points';
    }
}

if (!function_exists('rafflelb_points_admin_get_balance')) {
    function rafflelb_points_admin_get_balance($user_id) {
        $key = rafflelb_points_admin_balance_key();
        return (float) get_user_meta((int)$user_id, $key, true);
    }
}

if (!function_exists('rafflelb_points_admin_set_balance')) {
    function rafflelb_points_admin_set_balance($user_id, $new_balance) {
        $new_balance = max(0, (float)$new_balance);
        update_user_meta((int)$user_id, rafflelb_points_admin_balance_key(), $new_balance);
        return $new_balance;
    }
}

if (!function_exists('rafflelb_points_admin_log_adjustment')) {
    function rafflelb_points_admin_log_adjustment($user_id, $amount, $before, $after, $note='') {
        $log = get_user_meta((int)$user_id, '_rafflelb_points_admin_history', true);
        if (!is_array($log)) $log = array();
        $log[] = array(
            'time'   => current_time('mysql'),
            'amount' => (float)$amount,
            'before' => (float)$before,
            'after'  => (float)$after,
            'note'   => sanitize_text_field($note),
            'admin'  => get_current_user_id(),
        );
        if (count($log) > 100) $log = array_slice($log, -100);
        update_user_meta((int)$user_id, '_rafflelb_points_admin_history', $log);
    }
}

add_action('admin_menu', function() {
    if (!current_user_can('manage_woocommerce')) return;
    add_submenu_page(
        'woocommerce',
        'Raffle Points',
        'Raffle Points',
        'manage_woocommerce',
        'rafflelb-points-manager',
        'rafflelb_render_points_manager'
    );
});

function rafflelb_render_points_manager() {
    if (!current_user_can('manage_woocommerce')) return;

    $message = '';
    $error = '';
    $selected_user = null;

    if (!empty($_POST['rafflelb_points_action'])) {
        check_admin_referer('rafflelb_points_admin_action','rafflelb_points_nonce');

        $lookup = isset($_POST['rafflelb_user_lookup']) ? sanitize_text_field(wp_unslash($_POST['rafflelb_user_lookup'])) : '';
        $user = false;

        if (is_email($lookup)) {
            $user = get_user_by('email', $lookup);
        }
        if (!$user && $lookup !== '') {
            $user = get_user_by('login', $lookup);
        }
        if (!$user && ctype_digit($lookup)) {
            $user = get_user_by('id', (int)$lookup);
        }
        if (!$user && $lookup !== '') {
            $q = new WP_User_Query(array(
                'number' => 1,
                'meta_query' => array(
                    'relation' => 'OR',
                    array('key'=>'billing_phone','value'=>$lookup,'compare'=>'LIKE'),
                    array('key'=>'phone','value'=>$lookup,'compare'=>'LIKE'),
                ),
            ));
            $found = $q->get_results();
            if (!empty($found)) $user = $found[0];
        }

        if (!$user) {
            $error = 'User not found.';
        } else {
            $selected_user = $user;
            $action = sanitize_key($_POST['rafflelb_points_action']);
            if (in_array($action, array('add','remove'), true)) {
                $amount = isset($_POST['rafflelb_points_amount']) ? (float) wc_format_decimal(wp_unslash($_POST['rafflelb_points_amount'])) : 0;
                $note = isset($_POST['rafflelb_points_note']) ? sanitize_text_field(wp_unslash($_POST['rafflelb_points_note'])) : '';

                if ($amount <= 0) {
                    $error = 'Enter a points amount greater than 0.';
                } else {
                    $before = rafflelb_points_admin_get_balance($user->ID);
                    $delta = $action === 'add' ? $amount : -$amount;
                    $after = max(0, $before + $delta);
                    $actual_delta = $after - $before;
                    rafflelb_points_admin_set_balance($user->ID, $after);
                    rafflelb_points_admin_log_adjustment($user->ID, $actual_delta, $before, $after, $note);
                    $message = sprintf(
                        '%s points %s. New balance: %s',
                        wc_format_localized_decimal(abs($actual_delta)),
                        $actual_delta >= 0 ? 'added' : 'removed',
                        wc_format_localized_decimal($after)
                    );
                }
            }
        }
    }

    if (!$selected_user && !empty($_GET['user_id'])) {
        $selected_user = get_user_by('id', (int)$_GET['user_id']);
    }

    $balance = $selected_user ? rafflelb_points_admin_get_balance($selected_user->ID) : null;
    $history = $selected_user ? get_user_meta($selected_user->ID, '_rafflelb_points_admin_history', true) : array();
    if (!is_array($history)) $history = array();

    ?>
    <div class="wrap rafflelb-points-admin">
      <h1>Raffle Points Manager</h1>
      <p>Manually add or remove Raffle Points for any registered user.</p>

      <?php if ($message): ?>
        <div class="notice notice-success is-dismissible"><p><?php echo esc_html($message); ?></p></div>
      <?php endif; ?>
      <?php if ($error): ?>
        <div class="notice notice-error"><p><?php echo esc_html($error); ?></p></div>
      <?php endif; ?>

      <div style="max-width:920px;background:#fff;border:1px solid #dcdcde;border-radius:10px;padding:22px;margin-top:18px;">
        <form method="post">
          <?php wp_nonce_field('rafflelb_points_admin_action','rafflelb_points_nonce'); ?>
          <table class="form-table" role="presentation">
            <tr>
              <th><label for="rafflelb_user_lookup">User</label></th>
              <td>
                <input type="text" class="regular-text" id="rafflelb_user_lookup" name="rafflelb_user_lookup"
                       value="<?php echo esc_attr($selected_user ? $selected_user->user_email : ''); ?>"
                       placeholder="Email, username, user ID or phone" required>
                <?php if ($selected_user): ?>
                  <p><strong><?php echo esc_html($selected_user->display_name); ?></strong> — <?php echo esc_html($selected_user->user_email); ?></p>
                <?php endif; ?>
              </td>
            </tr>
            <tr>
              <th>Current balance</th>
              <td><strong style="font-size:24px;"><?php echo $selected_user ? esc_html(wc_format_localized_decimal($balance)) : '—'; ?> Raffle Points</strong></td>
            </tr>
            <tr>
              <th><label for="rafflelb_points_amount">Points</label></th>
              <td><input type="number" min="0.01" step="0.01" id="rafflelb_points_amount" name="rafflelb_points_amount" value="100" required></td>
            </tr>
            <tr>
              <th><label for="rafflelb_points_note">Reason / note</label></th>
              <td><input type="text" class="regular-text" id="rafflelb_points_note" name="rafflelb_points_note" placeholder="Example: Test credit, refund, promotion"></td>
            </tr>
          </table>
          <p class="submit">
            <button class="button button-primary" name="rafflelb_points_action" value="add">Add Points</button>
            <button class="button" name="rafflelb_points_action" value="remove" onclick="return confirm('Remove these points from this user?');">Remove Points</button>
          </p>
        </form>
      </div>

      <?php if ($selected_user): ?>
      <div style="max-width:920px;background:#fff;border:1px solid #dcdcde;border-radius:10px;padding:22px;margin-top:18px;">
        <h2>Manual adjustment history</h2>
        <?php if (empty($history)): ?>
          <p>No manual adjustments yet.</p>
        <?php else: ?>
          <table class="widefat striped">
            <thead><tr><th>Date</th><th>Change</th><th>Before</th><th>After</th><th>Reason</th><th>Admin</th></tr></thead>
            <tbody>
            <?php foreach (array_reverse($history) as $row):
                $admin = !empty($row['admin']) ? get_user_by('id',(int)$row['admin']) : false;
            ?>
              <tr>
                <td><?php echo esc_html($row['time'] ?? ''); ?></td>
                <td><strong><?php echo ((float)($row['amount'] ?? 0) >= 0 ? '+' : '') . esc_html(wc_format_localized_decimal((float)($row['amount'] ?? 0))); ?></strong></td>
                <td><?php echo esc_html(wc_format_localized_decimal((float)($row['before'] ?? 0))); ?></td>
                <td><?php echo esc_html(wc_format_localized_decimal((float)($row['after'] ?? 0))); ?></td>
                <td><?php echo esc_html($row['note'] ?? ''); ?></td>
                <td><?php echo esc_html($admin ? $admin->display_name : ''); ?></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>
      <?php endif; ?>

      <div style="max-width:1100px;background:#fff;border:1px solid #dcdcde;border-radius:10px;padding:22px;margin-top:18px;">
        <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;">
          <div>
            <h2 style="margin:0 0 6px;">All Users & Raffle Points</h2>
            <p style="margin:0;color:#646970;">See every registered user and their current Raffle Points balance.</p>
          </div>
        </div>

        <?php
          $points_users = get_users(array(
              'orderby' => 'display_name',
              'order'   => 'ASC',
              'fields'  => array('ID','display_name','user_email','user_login')
          ));
        ?>

        <table class="widefat striped" style="margin-top:16px;">
          <thead>
            <tr>
              <th>User</th>
              <th>Email</th>
              <th>Username</th>
              <th style="width:160px;">Raffle Points</th>
              <th style="width:110px;">Manage</th>
            </tr>
          </thead>
          <tbody>
          <?php if (empty($points_users)): ?>
            <tr><td colspan="5">No users found.</td></tr>
          <?php else: ?>
            <?php foreach ($points_users as $u): 
              $u_balance = rafflelb_points_admin_get_balance($u->ID);
            ?>
              <tr>
                <td><strong><?php echo esc_html($u->display_name); ?></strong></td>
                <td><?php echo esc_html($u->user_email); ?></td>
                <td><?php echo esc_html($u->user_login); ?></td>
                <td><strong><?php echo esc_html(wc_format_localized_decimal($u_balance)); ?></strong></td>
                <td>
                  <a class="button button-small" href="<?php echo esc_url(admin_url('admin.php?page=rafflelb-points-manager&user_id=' . (int)$u->ID)); ?>">Manage</a>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php
}



/* =========================================================
 * v1.1.9 — Header Raffle Points Balance
 * ========================================================= */
if (!function_exists('rafflelb_points_header_balance_html')) {
    function rafflelb_points_header_balance_html() {
        if (!is_user_logged_in()) return '';

        $user_id = get_current_user_id();
        $balance = function_exists('rafflelb_points_admin_get_balance')
            ? rafflelb_points_admin_get_balance($user_id)
            : (float) get_user_meta($user_id, '_rafflelb_ref_points', true);

        $balance_text = wc_format_localized_decimal($balance);

        $points_url = function_exists('wc_get_account_endpoint_url')
            ? wc_get_account_endpoint_url('refer-and-earn')
            : home_url('/my-account/refer-and-earn/');

        $icon_url = plugins_url('assets/rafflelb-ticket-icon.webp', __FILE__);

        return '<a class="rafflelb-header-points" href="' . esc_url($points_url) . '" aria-label="' . esc_attr(sprintf('Raffle Points: %s', $balance_text)) . '">' .
            '<span class="rafflelb-header-points-icon" aria-hidden="true"><img src="' . esc_url($icon_url) . '" alt=""></span>' .
            '<span class="rafflelb-header-points-count">' . esc_html($balance_text) . '</span>' .
        '</a>';
    }
}

add_shortcode('rafflelb_points_header', function() {
    return rafflelb_points_header_balance_html();
});

add_action('wp_enqueue_scripts', function() {
    if (is_admin() || !is_user_logged_in()) return;

    wp_register_style('rafflelb-points-header-inline', false, array(), '1.2.4');
    wp_enqueue_style('rafflelb-points-header-inline');

    $css = <<<'CSS'
.rafflelb-header-points{
    display:inline-flex!important;
    align-items:center!important;
    justify-content:center!important;
    gap:5px!important;
    height:36px!important;
    min-width:36px!important;
    padding:0 8px 0 5px!important;
    margin:0 4px!important;
    border-radius:999px!important;
    border:1px solid rgba(0,0,0,.14)!important;
    background:#fff!important;
    color:#101510!important;
    text-decoration:none!important;
    line-height:1!important;
    vertical-align:middle!important;
    box-sizing:border-box!important;
    transition:.18s ease!important;
    flex:0 0 auto!important;
    white-space:nowrap!important;
}
.rafflelb-header-points:hover{
    border-color:#baff00!important;
    box-shadow:0 0 0 2px rgba(186,255,0,.14)!important;
}
.rafflelb-header-points-icon{
    display:inline-flex!important;
    align-items:center!important;
    justify-content:center!important;
    width:24px!important;
    height:24px!important;
    flex:0 0 24px!important;
    overflow:visible!important;
}
.rafflelb-header-points-icon img{
    display:block!important;
    width:24px!important;
    height:24px!important;
    max-width:none!important;
    object-fit:contain!important;
}
.rafflelb-header-points-count{
    color:#101510!important;
    font-size:11px!important;
    font-weight:900!important;
    font-family:Inter,Arial,sans-serif!important;
    white-space:nowrap!important;
    line-height:1!important;
}

/* Logged-in mobile header: preserve Account + Points + Cart on one row. */
@media(max-width:767px){
    body.logged-in .rafflelb-header-points{
        height:34px!important;
        min-width:0!important;
        max-width:70px!important;
        padding:0 6px 0 3px!important;
        margin:0 1px!important;
        gap:2px!important;
    }
    body.logged-in .rafflelb-header-points-icon{
        width:24px!important;
        height:24px!important;
        flex-basis:24px!important;
    }
    body.logged-in .rafflelb-header-points-icon img{
        width:24px!important;
        height:24px!important;
    }
    body.logged-in .rafflelb-header-points-count{
        font-size:10px!important;
        font-weight:900!important;
        letter-spacing:-.02em!important;
    }

    /* Keep the right-side WoodMart tools from wrapping or shrinking away. */
    body.logged-in .whb-mobile-right,
    body.logged-in .whb-col-right,
    body.logged-in .whb-column.whb-col-right{
        display:flex!important;
        align-items:center!important;
        justify-content:flex-end!important;
        flex-wrap:nowrap!important;
        gap:2px!important;
        min-width:max-content!important;
    }
    body.logged-in .wd-header-my-account,
    body.logged-in .wd-tools-element.wd-header-my-account,
    body.logged-in .wd-header-cart,
    body.logged-in .wd-tools-element.wd-header-cart{
        display:inline-flex!important;
        visibility:visible!important;
        opacity:1!important;
        flex:0 0 auto!important;
        margin-left:0!important;
        margin-right:0!important;
    }

    /* Signed-in header needs a little extra room for the points balance. */
    body.logged-in .whb-mobile-center .wd-logo img,
    body.logged-in .whb-col-center .wd-logo img,
    body.logged-in .wd-logo img,
    body.logged-in .site-logo img{
        max-width:190px!important;
        width:auto!important;
        height:auto!important;
    }
}

@media(max-width:390px){
    body.logged-in .rafflelb-header-points{
        max-width:62px!important;
        padding-right:4px!important;
    }
    body.logged-in .rafflelb-header-points-count{
        font-size:9px!important;
    }
    body.logged-in .whb-mobile-center .wd-logo img,
    body.logged-in .whb-col-center .wd-logo img,
    body.logged-in .wd-logo img,
    body.logged-in .site-logo img{
        max-width:176px!important;
    }
}
CSS;
    wp_add_inline_style('rafflelb-points-header-inline', $css);
}, 30);

/**
 * Auto-place the balance beside the account/cart icons in the WoodMart header.
 * The shortcode remains available as a fallback for Header Builder.
 */
add_action('wp_footer', function() {
    if (!is_user_logged_in() || is_admin()) return;

    $html = rafflelb_points_header_balance_html();
    if (!$html) return;
    ?>
    <script>
    (function(){
        function firstVisible(selector){
            var nodes = document.querySelectorAll(selector);
            for(var i=0;i<nodes.length;i++){
                if(nodes[i].getClientRects().length && getComputedStyle(nodes[i]).display !== 'none') return nodes[i];
            }
            return nodes[0] || null;
        }

        function placeRafflePoints(){
            var badge = document.querySelector('.rafflelb-header-points');
            if(!badge){
                var wrap = document.createElement('div');
                wrap.innerHTML = <?php echo wp_json_encode($html); ?>;
                badge = wrap.firstElementChild;
                if(!badge) return;
            }

            var cart = firstVisible('.wd-header-cart, .wd-tools-element.wd-header-cart');
            var account = firstVisible('.wd-header-my-account, .wd-tools-element.wd-header-my-account');

            if(cart && cart.parentNode){
                if(badge.parentNode !== cart.parentNode || badge.nextSibling !== cart){
                    cart.parentNode.insertBefore(badge, cart);
                }
                return;
            }
            if(account && account.parentNode){
                if(account.nextSibling){
                    account.parentNode.insertBefore(badge, account.nextSibling);
                } else {
                    account.parentNode.appendChild(badge);
                }
            }
        }

        if(document.readyState === 'loading'){
            document.addEventListener('DOMContentLoaded', placeRafflePoints);
        } else {
            placeRafflePoints();
        }
        setTimeout(placeRafflePoints, 400);
        setTimeout(placeRafflePoints, 1200);
        window.addEventListener('resize', function(){ setTimeout(placeRafflePoints, 80); });
        window.addEventListener('orientationchange', function(){ setTimeout(placeRafflePoints, 180); });
    })();
    </script>
    <?php
}, 999);



/* =========================================================
 * v1.1.12 — Raffle Points payment status handling
 * ========================================================= */
if (!function_exists('rafflelb_points_order_is_raffle_only')) {
    function rafflelb_points_order_is_raffle_only($order) {
        if (!$order || !is_a($order, 'WC_Order')) return false;

        $found = false;
        foreach ($order->get_items() as $item) {
            $mode = sanitize_key((string) $item->get_meta('_rafflelb_purchase_mode', true));
            if ($mode === '') {
                $mode = sanitize_key((string) $item->get_meta('Purchase mode', true));
            }

            // Any direct Buy Now item means this is not a raffle-only order.
            if ($mode === 'buy_now') return false;

            $found = true;
        }

        return $found;
    }
}

/**
 * When Raffle Points successfully pays a raffle-only order, mark it Completed.
 * Direct Buy Now orders remain Processing so physical/digital fulfillment
 * still has to be handled.
 */
add_action('woocommerce_payment_complete', function($order_id) {
    $order = wc_get_order($order_id);
    if (!$order) return;

    if ($order->get_payment_method() !== 'rafflelb_points') return;
    if (!rafflelb_points_order_is_raffle_only($order)) return;

    if ($order->has_status(array('processing','on-hold','pending'))) {
        $order->update_status(
            'completed',
            'RaffleLB: raffle-only order paid successfully with Raffle Points; marked completed automatically.'
        );
    }
}, 50);

/**
 * Fallback for gateways that set Processing directly without firing
 * woocommerce_payment_complete in the expected order.
 */
add_action('woocommerce_order_status_processing', function($order_id) {
    $order = wc_get_order($order_id);
    if (!$order) return;

    if ($order->get_payment_method() !== 'rafflelb_points') return;
    if (!rafflelb_points_order_is_raffle_only($order)) return;

    $order->update_status(
        'completed',
        'RaffleLB: raffle-only Raffle Points order auto-completed.'
    );
}, 50);

/* v1.2.0 — presentation-only premium account styling. */
add_action('wp_head', function() {
    if (!is_user_logged_in() || !function_exists('is_account_page') || !is_account_page()) return;
    $url = plugins_url('assets/account-referral-premium.css', __FILE__);
    echo '<link id="rafflelb-referral-account-v120-css" rel="stylesheet" href="' . esc_url(add_query_arg('ver', '1.2.1', $url)) . '" media="all">';
}, 1000);

/* v1.2.6 — load and enforce the intended Refer & Earn typeface. */
add_action('wp_enqueue_scripts', function() {
    if (is_admin() || !is_user_logged_in() || !function_exists('is_account_page') || !is_account_page()) return;
    wp_enqueue_style(
        'rafflelb-referral-inter-v126',
        'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap',
        [],
        null
    );
}, 5);

/* Printed last so Woodmart and the shared account stylesheet cannot restore
 * the condensed theme font or recolor lime controls after this plugin renders. */
add_action('wp_footer', function() {
    if (!is_user_logged_in() || !function_exists('is_account_page') || !is_account_page()) return;
    ?>
    <style id="rafflelb-refer-earn-final-v1214">
    html body .woocommerce-MyAccount-content .rlre,
    html body .woocommerce-MyAccount-content .rlre *:not(svg):not(path){
        font-family:Inter,"Segoe UI",Arial,sans-serif!important;
        font-stretch:normal!important;
        text-rendering:optimizeLegibility!important;
    }
    html body .rlre .rlre-eyebrow{font-size:12px!important;font-weight:800!important}
    html body .rlre .rlre-hero h2{font-size:46px!important;line-height:1!important;font-weight:900!important}
    html body .rlre .rlre-hero p{font-size:16px!important;line-height:1.6!important;font-weight:450!important}
    html body .rlre .rlre-hero-process{
        width:420px!important;
        max-width:43%!important;
        flex:0 0 auto!important;
        margin:1px 0 0!important;
        padding:18px 20px!important;
        border:1px solid rgba(186,255,0,.22)!important;
        border-radius:14px!important;
        background:linear-gradient(145deg,rgba(186,255,0,.055),rgba(255,255,255,.012))!important;
        box-shadow:inset 0 1px 0 rgba(255,255,255,.035),0 14px 34px rgba(0,0,0,.16)!important;
        font-family:Inter,"Segoe UI",Arial,sans-serif!important;
        font-stretch:normal!important;
        text-rendering:optimizeLegibility!important;
    }
    html body .rlre .rlre-hero-process *{
        font-family:Inter,"Segoe UI",Arial,sans-serif!important;
        font-stretch:normal!important;
    }
    html body .rlre .rlre-hero-process>span{
        display:block!important;
        margin:0 0 15px!important;
        color:#aeb6ab!important;
        font-size:12px!important;
        line-height:1!important;
        font-weight:750!important;
        letter-spacing:.10em!important;
    }
    html body .rlre .rlre-hero-process>div{
        display:flex!important;
        align-items:center!important;
        justify-content:space-between!important;
        gap:10px!important;
    }
    html body .rlre .rlre-hero-process b{
        display:flex!important;
        min-width:0!important;
        align-items:center!important;
        gap:8px!important;
        color:#fff!important;
        font-style:normal!important;
        font-weight:800!important;
    }
    html body .rlre .rlre-hero-process i{
        display:grid!important;
        width:35px!important;
        height:35px!important;
        min-width:35px!important;
        place-items:center!important;
        border:1px solid rgba(186,255,0,.55)!important;
        border-radius:10px!important;
        background:rgba(186,255,0,.10)!important;
        color:#baff00!important;
        font-size:12px!important;
        line-height:1!important;
        font-style:normal!important;
        font-weight:800!important;
        letter-spacing:.02em!important;
    }
    html body .rlre .rlre-hero-process small{
        color:#f4f7f1!important;
        font-size:12px!important;
        line-height:1!important;
        font-weight:750!important;
        letter-spacing:.035em!important;
    }
    html body .rlre .rlre-hero-process em{
        display:block!important;
        width:22px!important;
        height:1px!important;
        flex:1 1 22px!important;
        max-width:30px!important;
        background:linear-gradient(90deg,rgba(186,255,0,.15),rgba(186,255,0,.65),rgba(186,255,0,.15))!important;
        font-style:normal!important;
    }
    @media(max-width:1050px) and (min-width:701px){
        html body .rlre .rlre-hero{display:block!important}
        html body .rlre .rlre-hero-process{width:100%!important;max-width:none!important;margin:20px 0 0!important}
    }

    html body .rlre .rlre-stats strong{font-size:31px!important;font-weight:850!important}
    html body .rlre .rlre-stats h3{font-size:17px!important;line-height:1.25!important;font-weight:750!important}
    html body .rlre .rlre-stats p{font-size:13px!important;line-height:1.45!important;font-weight:450!important}

    html body .rlre .rlre-panel-title h3{font-size:20px!important;line-height:1.25!important;font-weight:750!important}
    html body .rlre .rlre-panel-title p{font-size:14px!important;line-height:1.5!important;font-weight:450!important}
    html body .rlre .rlre-link input{font-size:14px!important;font-weight:500!important}
    html body .rlre #rl-copy-ref,
    html body .rlre #rl-copy-ref:hover,
    html body .rlre #rl-copy-ref:focus,
    html body .rlre #rl-copy-ref:active{
        border:1px solid #baff00!important;
        background:#baff00!important;
        background-image:none!important;
        color:#050705!important;
        -webkit-text-fill-color:#050705!important;
        font-size:13px!important;
        font-weight:850!important;
        opacity:1!important;
        filter:none!important;
        text-shadow:none!important;
        box-shadow:0 9px 22px rgba(186,255,0,.14)!important;
    }
    html body .rlre #rl-copy-ref *{color:#050705!important;-webkit-text-fill-color:#050705!important}
    html body .rlre #rl-copy-ref svg{
        display:block!important;
        width:19px!important;
        height:19px!important;
        flex:0 0 19px!important;
        fill:none!important;
        stroke:#050705!important;
        stroke-width:1.8!important;
        stroke-linecap:round!important;
        stroke-linejoin:round!important;
    }

    html body .rlre .rlre-share-actions>span{font-size:13px!important;font-weight:500!important}
    html body .rlre .rlre-share-actions .is-whatsapp,
    html body .rlre .rlre-share-actions .is-whatsapp:hover,
    html body .rlre .rlre-share-actions .is-whatsapp:focus{
        display:inline-flex!important;
        width:auto!important;
        min-width:145px!important;
        height:43px!important;
        align-items:center!important;
        justify-content:center!important;
        gap:9px!important;
        padding:0 17px!important;
        border:1px solid #25d366!important;
        border-radius:10px!important;
        background:#25d366!important;
        color:#fff!important;
        -webkit-text-fill-color:#fff!important;
        font-size:12px!important;
        font-weight:800!important;
        letter-spacing:.025em!important;
        opacity:1!important;
        filter:none!important;
    }
    html body .rlre .is-whatsapp svg{display:block!important;width:22px!important;height:22px!important;flex:0 0 22px!important;fill:#fff!important;stroke:none!important}
    html body .rlre .is-whatsapp span{color:#fff!important;-webkit-text-fill-color:#fff!important}

    html body .rlre .rlre-stats i.is-brand{width:48px!important;height:38px!important;border:0!important;background:transparent!important}
    html body .rlre .rlre-stats i.is-brand img{display:block!important;width:44px!important;height:38px!important;max-width:none!important;object-fit:contain!important}
    html body .rlre .rlre-stats i.is-feature{
        width:44px!important;
        height:44px!important;
        flex:0 0 44px!important;
        border:1px solid rgba(186,255,0,.5)!important;
        border-radius:12px!important;
        background:linear-gradient(145deg,rgba(186,255,0,.13),rgba(186,255,0,.035))!important;
        box-shadow:inset 0 1px 0 rgba(255,255,255,.05),0 8px 24px rgba(0,0,0,.2)!important;
    }
    html body .rlre .rlre-stats i.is-feature svg{
        display:block!important;
        width:23px!important;
        height:23px!important;
        fill:none!important;
        stroke:#baff00!important;
        stroke-width:1.7!important;
        stroke-linecap:round!important;
        stroke-linejoin:round!important;
    }
    html body .rlre .rlre-panel-title>i.is-brand{
        border:0!important;
        background:transparent!important;
    }
    html body .rlre .rlre-panel-title>i.is-brand img{
        display:block!important;
        width:38px!important;
        height:38px!important;
        max-width:none!important;
        object-fit:contain!important;
    }
    html body .rlre .rlre-panel-title>i.is-share svg{
        display:block!important;
        width:21px!important;
        height:21px!important;
        fill:none!important;
        stroke:#baff00!important;
        stroke-width:1.7!important;
        stroke-linecap:round!important;
        stroke-linejoin:round!important;
    }

    html body .rlre .rlre-wallet-value strong{font-size:42px!important;font-weight:850!important}
    html body .rlre .rlre-wallet-value span{font-size:13px!important;font-weight:700!important}
    html body .rlre .rlre-wallet-lines span{font-size:13px!important;line-height:1.4!important;font-weight:500!important}
    html body .rlre .rlre-wallet-lines strong,
    html body .rlre .rlre-wallet-lines strong *{font-size:14px!important;line-height:1.4!important;font-weight:700!important}

    html body .rlre .rlre-rule-icon,
    html body .rlre .rlre-steps i{
        background:#baff00!important;
        color:#050705!important;
        -webkit-text-fill-color:#050705!important;
        opacity:1!important;
        text-shadow:none!important;
    }
    html body .rlre .rlre-reward-rule>div:nth-child(2)>span{font-size:11px!important;font-weight:800!important}
    html body .rlre .rlre-reward-rule h3{font-size:22px!important;line-height:1.3!important;font-weight:800!important}
    html body .rlre .rlre-reward-rule p{font-size:14px!important;line-height:1.6!important;font-weight:450!important}
    html body .rlre .rlre-rule-rate strong{font-size:30px!important;font-weight:900!important}
    html body .rlre .rlre-rule-rate span{font-size:10px!important;font-weight:750!important}

    html body .rlre .rlre-section-title>span{font-size:11px!important;font-weight:800!important}
    html body .rlre .rlre-section-title h3{font-size:22px!important;line-height:1.25!important;font-weight:800!important}
    html body .rlre .rlre-steps h4{font-size:15px!important;line-height:1.3!important;font-weight:750!important}
    html body .rlre .rlre-steps p{font-size:13px!important;line-height:1.5!important;font-weight:450!important}

    html body .rlre .rlre-activity-head>strong{
        display:inline-flex!important;
        min-height:28px!important;
        align-items:center!important;
        justify-content:center!important;
        padding:0 10px!important;
        border:1px solid rgba(186,255,0,.25)!important;
        border-radius:999px!important;
        background:rgba(186,255,0,.065)!important;
        color:#baff00!important;
        font-size:11px!important;
        font-weight:800!important;
        white-space:nowrap!important;
    }
    html body .rlre .rlre-activity-list article>div strong{font-size:14px!important;font-weight:700!important}
    html body .rlre .rlre-activity-list article>div small{font-size:12px!important;line-height:1.4!important}
    html body .rlre .rlre-activity-list article>span:nth-child(3){font-size:10px!important;font-weight:750!important}
    html body .rlre .rlre-activity-list article>b{font-size:13px!important;font-weight:750!important}
    html body .rlre .rlre-empty strong{font-size:16px!important;font-weight:750!important}
    html body .rlre .rlre-empty p{font-size:13px!important;line-height:1.5!important}
    html body .rlre .rlre-empty>i.rlre-empty-icon{
        display:grid!important;
        place-items:center!important;
        width:48px!important;
        height:48px!important;
        min-width:48px!important;
        max-width:48px!important;
        min-height:48px!important;
        max-height:48px!important;
        flex:0 0 48px!important;
        aspect-ratio:1/1!important;
        padding:0!important;
        overflow:hidden!important;
        line-height:0!important;
        box-sizing:border-box!important;
        border:1px solid rgba(186,255,0,.48)!important;
        border-radius:13px!important;
        background:linear-gradient(145deg,rgba(186,255,0,.13),rgba(186,255,0,.025))!important;
        box-shadow:inset 0 1px 0 rgba(255,255,255,.05),0 10px 28px rgba(0,0,0,.2)!important;
    }
    html body .rlre .rlre-empty>i.rlre-empty-icon svg{
        display:block!important;
        width:25px!important;
        height:25px!important;
        min-width:25px!important;
        max-width:25px!important;
        min-height:25px!important;
        max-height:25px!important;
        aspect-ratio:1/1!important;
        flex:none!important;
        fill:none!important;
        stroke:#baff00!important;
        stroke-width:1.7!important;
        stroke-linecap:round!important;
        stroke-linejoin:round!important;
    }
    html body .rlre .rlre-note h3{font-size:16px!important;font-weight:750!important}
    html body .rlre .rlre-note p{font-size:13px!important;line-height:1.65!important;font-weight:450!important}

    @media(max-width:700px){
        html body .rlre .rlre-hero h2{font-size:37px!important}
        html body .rlre .rlre-hero p{font-size:14px!important}
        html body .rlre .rlre-hero-process{
            width:100%!important;
            max-width:none!important;
            margin:18px 0 0!important;
            padding:16px!important;
        }
        html body .rlre .rlre-hero-process>span{margin-bottom:13px!important;font-size:11px!important}
        html body .rlre .rlre-hero-process>div{gap:7px!important}
        html body .rlre .rlre-hero-process b{gap:6px!important}
        html body .rlre .rlre-hero-process i{width:31px!important;height:31px!important;min-width:31px!important;font-size:11px!important}
        html body .rlre .rlre-hero-process small{font-size:10px!important;font-weight:750!important}
        html body .rlre .rlre-hero-process em{max-width:20px!important}
        html body .rlre #rl-copy-ref,
        html body .rlre #rl-copy-ref:hover,
        html body .rlre #rl-copy-ref:focus,
        html body .rlre #rl-copy-ref:active{
            border-color:#8fbd16!important;
            background:#82ad0b!important;
            background-image:linear-gradient(135deg,#8fbd16,#759f05)!important;
            color:#071006!important;
            -webkit-text-fill-color:#071006!important;
            box-shadow:none!important;
        }
        html body .rlre #rl-copy-ref *,
        html body .rlre #rl-copy-ref svg{
            color:#071006!important;
            -webkit-text-fill-color:#071006!important;
            stroke:#071006!important;
        }
        html body .rlre .rlre-stats strong{font-size:26px!important}
        html body .rlre .rlre-stats h3{font-size:13px!important}
        html body .rlre .rlre-stats p{font-size:11px!important}
        html body .rlre .rlre-reward-rule h3{font-size:18px!important}
        html body .rlre .rlre-reward-rule p{font-size:13px!important}
        html body .rlre .rlre-section-title h3{font-size:20px!important}
        html body .rlre .rlre-steps h4{font-size:14px!important}
        html body .rlre .rlre-steps p{font-size:12px!important}
        html body .rlre .rlre-activity{padding:18px 14px!important}
        html body .rlre .rlre-activity-head{
            display:grid!important;
            grid-template-columns:minmax(0,1fr) auto!important;
            align-items:end!important;
            gap:10px!important;
            margin-bottom:16px!important;
        }
        html body .rlre .rlre-activity-head>strong{
            min-height:27px!important;
            padding:0 9px!important;
            font-size:10px!important;
        }
        html body .rlre .rlre-empty{
            display:flex!important;
            flex-direction:column!important;
            align-items:center!important;
            justify-content:center!important;
            gap:13px!important;
            min-height:190px!important;
            padding:25px 18px!important;
            border-radius:13px!important;
            background:radial-gradient(circle at 50% 0,rgba(186,255,0,.05),transparent 52%),rgba(255,255,255,.012)!important;
            text-align:center!important;
        }
        html body .rlre .rlre-empty>div{width:100%!important;min-width:0!important}
        html body .rlre .rlre-empty>i.rlre-empty-icon{width:54px!important;height:54px!important;min-width:54px!important;max-width:54px!important;min-height:54px!important;max-height:54px!important;flex:0 0 54px!important;border-radius:15px!important}
        html body .rlre .rlre-empty>i.rlre-empty-icon svg{width:28px!important;height:28px!important;min-width:28px!important;max-width:28px!important;min-height:28px!important;max-height:28px!important}
        html body .rlre .rlre-empty strong{font-size:17px!important;line-height:1.3!important}
        html body .rlre .rlre-empty p{max-width:290px!important;margin:6px auto 0!important;font-size:13px!important;line-height:1.55!important}
        html body .rlre .rlre-note p{font-size:11px!important}
    }
    </style>
    <?php
}, PHP_INT_MAX);
