<?php
/**
 * Plugin Name: RaffleLB Account
 * Description: Existing RaffleLB account presentation with reversible Draw Engine delegation.
 * Version: 0.1.13
 * Author: RaffleLB
 * Requires PHP: 7.4
 */
if (!defined('ABSPATH')) { exit; }

final class RaffleLB_Account {
    const VERSION = '0.1.13';
    public static function ready() {
        return class_exists('RaffleLB\\Core\\Contracts')
            && version_compare(\RaffleLB\Core\Contracts::VERSION, '0.1.0', '>=')
            && defined('RaffleLB_Draw_Engine::ACCOUNT_BRIDGE_VERSION')
            && RaffleLB_Draw_Engine::ACCOUNT_BRIDGE_VERSION === '1';
    }
    private static function public_early_closure($product_id) {
        if (!class_exists('RaffleLB_Draw_Engine') || !method_exists('RaffleLB_Draw_Engine', 'selection_bridge_public_closure')) return null;
        $closure = RaffleLB_Draw_Engine::selection_bridge_public_closure(absint($product_id));
        if (!is_array($closure) || empty($closure['closed_early'])) return null;
        $mode = sanitize_key((string) ($closure['mode'] ?? ''));
        $refund_status = sanitize_key((string) ($closure['refund_status'] ?? ''));
        if ($mode === '' && in_array($refund_status, ['processing', 'complete'], true)) {
            $closure['mode'] = 'cancel_refund';
        } elseif (!in_array($mode, ['selection', 'cancel_refund'], true)) {
            $closure['mode'] = 'selection';
        }
        return $closure;
    }

    private static function order_item_purchase_mode($item) {
        if (!$item instanceof WC_Order_Item_Product) return '';
        $mode = sanitize_key((string) $item->get_meta('_rafflelb_purchase_mode', true));
        if ($mode === '') $mode = sanitize_key((string) $item->get_meta('rafflelb_purchase_mode', true));
        return $mode;
    }

    /** Customer-safe cancellation/refund state for one raffle order line. */
    private static function cancelled_raffle_item_info($order, $item) {
        if (!$order instanceof WC_Order || !$item instanceof WC_Order_Item_Product) return null;
        if (self::order_item_purchase_mode($item) !== 'raffle_entry') return null;

        $product_id = absint($item->get_product_id());
        if (!$product_id) return null;
        $closure = self::public_early_closure($product_id);
        if (!is_array($closure) || sanitize_key((string) ($closure['mode'] ?? '')) !== 'cancel_refund') return null;

        $refund_status = sanitize_key((string) ($closure['refund_status'] ?? 'processing'));
        $refund = $order->get_meta('_rafflelb_early_close_points_refund_' . $product_id, true);
        $points = is_array($refund) ? absint($refund['points'] ?? 0) : 0;
        $credited_at = is_array($refund) ? sanitize_text_field((string) ($refund['credited_at'] ?? '')) : '';

        return [
            'product_id'    => $product_id,
            'status'        => $refund_status === 'complete' ? 'complete' : 'processing',
            'points'        => $points,
            'credited_at'   => $credited_at,
            'public_note'   => sanitize_textarea_field((string) ($closure['note'] ?? '')),
        ];
    }

    /** Aggregate cancellation state without changing the real Woo order. */
    private static function order_raffle_cancellation_summary($order) {
        if (!is_object($order) && function_exists('wc_get_order')) $order = wc_get_order($order);
        if (!$order instanceof WC_Order) return ['raffle_items'=>0,'cancelled_items'=>0,'points'=>0,'all_complete'=>false,'credited_at'=>'','public_note'=>''];

        $summary = ['raffle_items'=>0,'cancelled_items'=>0,'points'=>0,'all_complete'=>true,'credited_at'=>'','public_note'=>''];
        $seen_products = [];
        foreach ($order->get_items('line_item') as $item) {
            if (!$item instanceof WC_Order_Item_Product || self::order_item_purchase_mode($item) !== 'raffle_entry') continue;
            $summary['raffle_items']++;
            $info = self::cancelled_raffle_item_info($order, $item);
            if (!$info) continue;
            $summary['cancelled_items']++;
            if ($info['status'] !== 'complete') $summary['all_complete'] = false;
            if ($summary['public_note'] === '' && $info['public_note'] !== '') $summary['public_note'] = $info['public_note'];
            if ($summary['credited_at'] === '' && $info['credited_at'] !== '') $summary['credited_at'] = $info['credited_at'];
            if (!isset($seen_products[$info['product_id']])) {
                $summary['points'] += absint($info['points']);
                $seen_products[$info['product_id']] = true;
            }
        }
        if ($summary['cancelled_items'] < 1) $summary['all_complete'] = false;
        return $summary;
    }

    private static function refund_date_label($gmt_mysql) {
        $gmt_mysql = sanitize_text_field((string) $gmt_mysql);
        if ($gmt_mysql === '') return '';
        $local = function_exists('get_date_from_gmt') ? get_date_from_gmt($gmt_mysql) : $gmt_mysql;
        $timestamp = strtotime($local);
        return $timestamp ? date_i18n(get_option('date_format'), $timestamp) : '';
    }

    public static function account_hard_contrast_fix() {
        if (!function_exists('is_account_page') || !is_account_page()) return;
        ?>
        <style id="rafflelb-account-hard-contrast-v0187">
            /* FINAL account-page override. Intentionally loaded after WoodMart and
               after the older RaffleLB contrast block. */

            /* Normal dark account inputs: white text on dark field. */
            html body.woocommerce-account .woocommerce input.input-text,
            html body.woocommerce-account .woocommerce input[type="text"],
            html body.woocommerce-account .woocommerce input[type="email"],
            html body.woocommerce-account .woocommerce input[type="password"],
            html body.woocommerce-account .woocommerce textarea,
            html body.woocommerce-account .woocommerce select {
                color:#ffffff !important;
                -webkit-text-fill-color:#ffffff !important;
                caret-color:#caff16 !important;
            }

            /* Chrome / Android autofill: force a DARK background + WHITE text.
               This avoids the unreadable light-blue autofill state entirely. */
            html body.woocommerce-account .woocommerce input.input-text:-webkit-autofill,
            html body.woocommerce-account .woocommerce input.input-text:-webkit-autofill:hover,
            html body.woocommerce-account .woocommerce input.input-text:-webkit-autofill:focus,
            html body.woocommerce-account .woocommerce input[type="text"]:-webkit-autofill,
            html body.woocommerce-account .woocommerce input[type="text"]:-webkit-autofill:hover,
            html body.woocommerce-account .woocommerce input[type="text"]:-webkit-autofill:focus,
            html body.woocommerce-account .woocommerce input[type="email"]:-webkit-autofill,
            html body.woocommerce-account .woocommerce input[type="email"]:-webkit-autofill:hover,
            html body.woocommerce-account .woocommerce input[type="email"]:-webkit-autofill:focus,
            html body.woocommerce-account .woocommerce input[type="password"]:-webkit-autofill,
            html body.woocommerce-account .woocommerce input[type="password"]:-webkit-autofill:hover,
            html body.woocommerce-account .woocommerce input[type="password"]:-webkit-autofill:focus {
                -webkit-text-fill-color:#ffffff !important;
                color:#ffffff !important;
                caret-color:#caff16 !important;
                -webkit-box-shadow:0 0 0 1000px #171b14 inset !important;
                box-shadow:0 0 0 1000px #171b14 inset !important;
                background-color:#171b14 !important;
                border-color:#525a4d !important;
                transition:background-color 99999s ease-out 0s !important;
            }

            html body.woocommerce-account .woocommerce input::placeholder,
            html body.woocommerce-account .woocommerce textarea::placeholder {
                color:#9da398 !important;
                -webkit-text-fill-color:#9da398 !important;
                opacity:1 !important;
            }

            /* WooCommerce View Order intro: remove WoodMart's white MARK boxes. */
            html body.woocommerce-account .woocommerce-MyAccount-content mark,
            html body.woocommerce-account .woocommerce-MyAccount-content mark.order-number,
            html body.woocommerce-account .woocommerce-MyAccount-content mark.order-date,
            html body.woocommerce-account .woocommerce-MyAccount-content mark.order-status {
                display:inline !important;
                background:#20251d !important;
                background-color:#20251d !important;
                color:#ffffff !important;
                -webkit-text-fill-color:#ffffff !important;
                padding:2px 6px !important;
                margin:0 2px !important;
                border:0 !important;
                border-radius:4px !important;
                box-shadow:none !important;
                opacity:1 !important;
            }

            html body.woocommerce-account .woocommerce-MyAccount-content > p:first-child,
            html body.woocommerce-account .woocommerce-MyAccount-content > p:first-child * {
                color:#f1f3ee !important;
                -webkit-text-fill-color:#f1f3ee !important;
            }
        </style>

        <script id="rafflelb-account-hard-contrast-js-v0187">
        (function(){
            function fixRaffleLBAccountContrast(){
                /* Inline !important beats late theme/plugin declarations for View Order. */
                document.querySelectorAll(
                    '.woocommerce-MyAccount-content mark, ' +
                    '.woocommerce-MyAccount-content mark.order-number, ' +
                    '.woocommerce-MyAccount-content mark.order-date, ' +
                    '.woocommerce-MyAccount-content mark.order-status'
                ).forEach(function(el){
                    el.style.setProperty('background', '#20251d', 'important');
                    el.style.setProperty('background-color', '#20251d', 'important');
                    el.style.setProperty('color', '#ffffff', 'important');
                    el.style.setProperty('-webkit-text-fill-color', '#ffffff', 'important');
                    el.style.setProperty('padding', '2px 6px', 'important');
                    el.style.setProperty('border-radius', '4px', 'important');
                });
            }

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', fixRaffleLBAccountContrast);
            } else {
                fixRaffleLBAccountContrast();
            }
            window.addEventListener('load', fixRaffleLBAccountContrast);
            setTimeout(fixRaffleLBAccountContrast, 500);
        })();
        </script>
        <?php
    }

    public static function register_endpoint(){ add_rewrite_endpoint('rafflelb-entries',EP_ROOT|EP_PAGES); }

    public static function query_vars($vars){ $vars[]='rafflelb-entries'; return $vars; }

    public static function account_menu($items){
        // Simplify the standard WooCommerce menu so it feels like RaffleLB.
        unset($items['downloads']);

        $new = [];

        if (isset($items['dashboard'])) {
            $new['dashboard'] = 'Dashboard';
        }
        if (isset($items['orders'])) {
            $new['orders'] = 'Orders';
        }

        $new['rafflelb-entries'] = 'My Raffles';

        // This filter fully rebuilds the menu (see the allowlist above), so a
        // Notifications tab added by RaffleLB Notifications via its own
        // woocommerce_account_menu_items hook would otherwise be silently
        // dropped. That plugin is optional, so only add the tab when it's
        // active and has actually registered its endpoint.
        if (class_exists('RaffleLB_Notifications')) {
            $new['rafflelb-notifications'] = 'Notifications';
        }

        if (isset($items['edit-address'])) {
            $new['edit-address'] = 'Addresses';
        }
        if (isset($items['edit-account'])) {
            $new['edit-account'] = 'Account Details';
        }
        if (isset($items['customer-logout'])) {
            $new['customer-logout'] = 'Logout';
        }

        return $new;
    }

    public static function final_account_menu_order($items) {
        // This single order drives both layouts cleanly: read left to right
        // on desktop it ends in Logout, and on the mobile 2-column grid it
        // pairs as (Dashboard,Notifications) (My Raffles,Orders)
        // (Addresses,Account Details) (Refer & Earn,Logout) - no leftover
        // odd item, so no full-width "divider" rows are needed.
        $order = ['dashboard', 'rafflelb-notifications', 'rafflelb-entries', 'orders', 'edit-address', 'edit-account', 'refer-and-earn', 'customer-logout'];

        $out = [];
        foreach ($order as $key) {
            if (isset($items[$key])) {
                $out[$key] = $items[$key];
                unset($items[$key]);
            }
        }

        // Anything left over (an endpoint this list doesn't know about yet)
        // still renders, just after everything above.
        foreach ($items as $key => $label) {
            $out[$key] = $label;
        }

        return $out;
    }

    public static function current_user_wins() {
        if (!is_user_logged_in()) return [];

        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT r.*
             FROM {$wpdb->prefix}" . \RaffleLB\Core\Contracts::RESULT_TABLE . " r
             WHERE r.user_id=%d
             ORDER BY r.selected_at DESC, r.id DESC",
            get_current_user_id()
        ));
    }

    public static function dismissed_win_ids($user_id) {
        $ids = get_user_meta($user_id, '_rafflelb_dismissed_wins', true);
        return is_array($ids) ? array_map('absint', $ids) : [];
    }

    public static function ajax_dismiss_winner_banner() {
        check_ajax_referer('rafflelb_dismiss_winner', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error();
        }

        $ids = isset($_POST['ids']) ? array_map('absint', (array) wp_unslash($_POST['ids'])) : [];
        $ids = array_filter($ids);
        if (!$ids) {
            wp_send_json_error();
        }

        $user_id = get_current_user_id();
        $dismissed = array_values(array_unique(array_merge(self::dismissed_win_ids($user_id), $ids)));
        update_user_meta($user_id, '_rafflelb_dismissed_wins', $dismissed);

        wp_send_json_success();
    }

    public static function account_winner_summary() {
        $wins = self::current_user_wins();
        if (!$wins) return;

        // Only hides this dashboard banner once acknowledged — the underlying
        // draw result record is never touched, so My Raffles / order history
        // continue to show the win permanently regardless of dismissal.
        $dismissed = self::dismissed_win_ids(get_current_user_id());
        $wins = array_values(array_filter($wins, function ($w) use ($dismissed) {
            return !in_array((int) $w->id, $dismissed, true);
        }));
        if (!$wins) return;

        $win_ids = wp_list_pluck($wins, 'id');
        $ids_attr = esc_attr(implode(',', array_map('absint', $win_ids)));
        $nonce = wp_create_nonce('rafflelb_dismiss_winner');

        $dismiss_inline_style = 'all:unset!important;box-sizing:border-box!important;position:absolute!important;'
            .'top:16px!important;right:16px!important;left:auto!important;width:30px!important;height:30px!important;'
            .'display:flex!important;align-items:center!important;justify-content:center!important;'
            .'border:1px solid rgba(186,255,0,.45)!important;border-radius:50%!important;'
            .'background-color:#10130e!important;background-image:none!important;'
            .'color:#e3e8dd!important;-webkit-text-fill-color:#e3e8dd!important;'
            .'font-size:17px!important;line-height:1!important;cursor:pointer!important;';
        $kicker_inline_style = 'display:inline-block!important;background-color:#baff00!important;background-image:none!important;'
            .'color:#0b0d08!important;-webkit-text-fill-color:#0b0d08!important;font-size:11px!important;'
            .'font-weight:800!important;letter-spacing:.12em!important;border-radius:999px!important;'
            .'padding:6px 12px!important;margin:0 0 12px!important;';

        echo '<div class="rafflelb-winner-summary" data-win-ids="'.$ids_attr.'" data-nonce="'.esc_attr($nonce).'">';
        echo '<button type="button" id="rafflelb-winner-dismiss-btn" class="rafflelb-winner-dismiss" aria-label="Dismiss" style="'.esc_attr($dismiss_inline_style).'">&times;</button>';
        echo '<div class="rafflelb-winner-kicker" style="'.esc_attr($kicker_inline_style).'">WINNER</div>';
        echo '<h3>You have a winning RaffleLB entry</h3>';

        foreach ($wins as $win) {
            $win_product = function_exists('wc_get_product') ? wc_get_product((int) $win->product_id) : false;
            $thumb = $win_product ? $win_product->get_image('woocommerce_thumbnail', ['loading' => 'lazy']) : '';

            echo '<div class="rafflelb-winner-summary-item">';
                echo '<div class="rafflelb-winner-summary-head">';
                    if ($thumb) {
                        echo '<div class="rafflelb-winner-summary-thumb">'.wp_kses_post($thumb).'</div>';
                    }
                    echo '<div class="rafflelb-winner-summary-title">';
                        echo '<span>Raffle</span><strong>'.esc_html(get_the_title($win->product_id)).'</strong>';
                    echo '</div>';
                echo '</div>';
                echo '<div class="rafflelb-winner-summary-row">';
                echo '<div><span>Winning Entry</span><strong>#'.esc_html(str_pad((string)$win->entry_number,3,'0',STR_PAD_LEFT)).'</strong></div>';
                echo '<div><span>Selected</span><strong>'.esc_html($win->selected_at).'</strong></div>';
                echo '</div>';
            echo '</div>';
        }

        echo '<p class="rafflelb-winner-summary-note">Your winning result has been permanently recorded in the RaffleLB selection record.</p>';
        echo '<a id="rafflelb-view-winning-entry-btn" class="button rafflelb-view-winning-entry" href="'.esc_url(wc_get_account_endpoint_url('rafflelb-entries')).'">VIEW MY WINNING ENTRY</a>';
        echo '</div>';

        ?>
        <style>
        /* Self-contained: does not rely on the .rafflelb-account-page body class,
           which was not matching on this page and silently blocking the earlier
           border/button styling. Scoped tightly to these two unique classes. */
        .rafflelb-winner-summary{
            position:relative!important;
            border:2px solid #baff00!important;
            border-radius:16px!important;
            background:linear-gradient(135deg,#171d12 0%,#0b0d08 100%)!important;
            padding:24px 26px!important;
            margin:0 0 22px!important;
            box-shadow:0 18px 40px rgba(0,0,0,.35),0 0 0 1px rgba(186,255,0,.12) inset!important;
        }
        .rafflelb-winner-summary .rafflelb-winner-kicker{
            display:inline-block!important;
            background:#baff00!important;
            color:#0b0d08!important;
            font-size:11px!important;
            font-weight:800!important;
            letter-spacing:.12em!important;
            border-radius:999px!important;
            padding:6px 12px!important;
            margin:0 0 12px!important;
        }
        .rafflelb-winner-summary h3{
            margin:0 0 16px!important;
            color:#fff!important;
            font-size:20px!important;
            line-height:1.3!important;
        }
        .rafflelb-winner-summary-item{
            margin:0 0 16px!important;
        }
        .rafflelb-winner-summary-head{
            display:flex!important;
            align-items:center!important;
            gap:12px!important;
            margin:0 0 12px!important;
        }
        .rafflelb-winner-summary-thumb{
            flex:0 0 auto!important;
            width:52px!important;
            height:52px!important;
            border:1px solid rgba(186,255,0,.35)!important;
            border-radius:12px!important;
            overflow:hidden!important;
            background:#0c0f0a!important;
        }
        .rafflelb-winner-summary-thumb img{
            display:block!important;
            width:100%!important;
            height:100%!important;
            object-fit:cover!important;
        }
        .rafflelb-winner-summary-title{
            flex:1 1 auto!important;
            min-width:0!important;
        }
        .rafflelb-winner-summary-title span{
            display:block!important;
            color:#9da594!important;
            font-size:11px!important;
            text-transform:uppercase!important;
            letter-spacing:.08em!important;
            margin:0 0 3px!important;
        }
        .rafflelb-winner-summary-title strong{
            display:block!important;
            color:#fff!important;
            font-size:16px!important;
            line-height:1.3!important;
        }
        .rafflelb-winner-summary-row{
            display:grid!important;
            grid-template-columns:repeat(2,minmax(0,1fr))!important;
            gap:10px!important;
            min-width:0!important;
            margin:0!important;
        }
        .rafflelb-winner-summary-row>div{
            border:1px solid rgba(186,255,0,.18)!important;
            border-radius:12px!important;
            background:rgba(255,255,255,.02)!important;
            padding:12px 14px!important;
            min-width:0!important;
        }
        .rafflelb-winner-summary-row span{
            display:block!important;
            color:#9da594!important;
            font-size:11px!important;
            text-transform:uppercase!important;
            letter-spacing:.08em!important;
            margin:0 0 4px!important;
        }
        .rafflelb-winner-summary-row strong{color:#fff!important;}
        .rafflelb-winner-summary-note{color:#c1c7bb!important;margin:0 0 16px!important;}
        #rafflelb-view-winning-entry-btn{
            display:inline-block!important;
            background:#baff00!important;
            color:#0b0d08!important;
            -webkit-text-fill-color:#0b0d08!important;
            border:none!important;
            border-radius:10px!important;
            padding:14px 22px!important;
            font-weight:800!important;
            font-size:13px!important;
            letter-spacing:.04em!important;
            text-decoration:none!important;
            text-shadow:none!important;
            opacity:1!important;
        }
        #rafflelb-view-winning-entry-btn:hover,
        #rafflelb-view-winning-entry-btn:focus{
            background:#a8e600!important;
            color:#0b0d08!important;
            -webkit-text-fill-color:#0b0d08!important;
        }

        #rafflelb-winner-dismiss-btn{
            all:unset!important;
            box-sizing:border-box!important;
            position:absolute!important;
            top:16px!important;
            right:16px!important;
            width:30px!important;
            height:30px!important;
            display:flex!important;
            align-items:center!important;
            justify-content:center!important;
            border:1px solid rgba(186,255,0,.45)!important;
            border-radius:50%!important;
            background:rgba(255,255,255,.08)!important;
            color:#e3e8dd!important;
            -webkit-text-fill-color:#e3e8dd!important;
            font-size:17px!important;
            line-height:1!important;
            cursor:pointer!important;
            transition:background .15s ease,color .15s ease,border-color .15s ease,transform .15s ease!important;
        }
        #rafflelb-winner-dismiss-btn:hover{
            background:#baff00!important;
            color:#0b0d08!important;
            border-color:#baff00!important;
            transform:scale(1.06)!important;
        }
        @media(max-width:600px){
            .rafflelb-winner-summary{padding:20px 18px!important;}
            .rafflelb-winner-summary h3{font-size:18px!important;}
            .rafflelb-winner-summary-title strong{font-size:15px!important;}
            .rafflelb-winner-summary-row{gap:8px!important;}
            .rafflelb-winner-summary-row>div{padding:10px 12px!important;}
        }
        </style>
        <script>
        document.querySelectorAll('.rafflelb-winner-summary .rafflelb-winner-dismiss').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var box = btn.closest('.rafflelb-winner-summary');
                if (!box) return;
                btn.disabled = true;
                var body = new URLSearchParams();
                body.append('action', 'rafflelb_dismiss_winner_banner');
                body.append('nonce', box.getAttribute('data-nonce'));
                (box.getAttribute('data-win-ids') || '').split(',').forEach(function (id) {
                    if (id) body.append('ids[]', id);
                });
                fetch('<?php echo esc_url(admin_url('admin-ajax.php')); ?>', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: body.toString()
                }).then(function () {
                    box.style.display = 'none';
                }).catch(function () {
                    btn.disabled = false;
                });
            });
        });
        </script>
        <?php
    }

    /**
     * A customer-facing raffle order must never expose WooCommerce's cancel
     * action. Direct-purchase orders keep WooCommerce's normal policy.
     */
    public static function remove_customer_cancel_action($actions, $order) {
        if (self::order_contains_raffle_entry($order) && isset($actions['cancel'])) {
            unset($actions['cancel']);
        }
        return $actions;
    }

    /**
     * Return true when an order contains at least one explicitly raffle-mode
     * item. A Buy Direct-only order has only `buy_now` item metadata.
     */
    public static function order_contains_raffle_entry($order) {
        if (!is_object($order) && function_exists('wc_get_order')) {
            $order = wc_get_order($order);
        }
        if (!$order instanceof WC_Order) return false;

        foreach ($order->get_items('line_item') as $item) {
            if (!$item instanceof WC_Order_Item_Product) continue;

            $mode = sanitize_key((string) $item->get_meta('_rafflelb_purchase_mode', true));
            if ($mode === '') {
                $mode = sanitize_key((string) $item->get_meta('rafflelb_purchase_mode', true));
            }
            if ($mode === 'raffle_entry') return true;
        }

        return false;
    }

    /**
     * A friendly raffle payment label is appropriate only when every explicit
     * purchase-mode line is a raffle entry. Mixed and Buy Direct orders retain
     * WooCommerce's native status wording.
     */
    public static function order_is_raffle_only($order) {
        if (!is_object($order) && function_exists('wc_get_order')) {
            $order = wc_get_order($order);
        }
        if (!$order instanceof WC_Order) return false;

        $has_raffle = false;
        foreach ($order->get_items('line_item') as $item) {
            if (!$item instanceof WC_Order_Item_Product) continue;

            $mode = sanitize_key((string) $item->get_meta('_rafflelb_purchase_mode', true));
            if ($mode === '') {
                $mode = sanitize_key((string) $item->get_meta('rafflelb_purchase_mode', true));
            }
            if ($mode !== 'raffle_entry') return false;
            $has_raffle = true;
        }

        return $has_raffle;
    }

    /**
     * True only when every explicit purchase-mode line is a Buy Direct item.
     * Untagged legacy/store lines retain WooCommerce's default eligibility and
     * are not granted the RaffleLB COD processing exception.
     */
    public static function order_is_buy_direct_only($order) {
        if (!is_object($order) && function_exists('wc_get_order')) {
            $order = wc_get_order($order);
        }
        if (!$order instanceof WC_Order) return false;

        $has_direct = false;
        foreach ($order->get_items('line_item') as $item) {
            if (!$item instanceof WC_Order_Item_Product) continue;

            $mode = sanitize_key((string) $item->get_meta('_rafflelb_purchase_mode', true));
            if ($mode === '') {
                $mode = sanitize_key((string) $item->get_meta('rafflelb_purchase_mode', true));
            }
            if ($mode !== 'buy_now') return false;
            $has_direct = true;
        }

        return $has_direct;
    }

    /**
     * Keep WooCommerce's status labels global and apply the raffle-specific
     * Paid wording only in customer-facing Account presentation.
     */
    public static function customer_order_status_label($order) {
        if (!is_object($order) && function_exists('wc_get_order')) {
            $order = wc_get_order($order);
        }
        if (!$order instanceof WC_Order) return '';

        $cancellation = self::order_raffle_cancellation_summary($order);
        if (self::order_is_raffle_only($order)
            && $cancellation['raffle_items'] > 0
            && $cancellation['cancelled_items'] === $cancellation['raffle_items']) {
            return 'Raffle Cancelled';
        }

        if ($order->has_status('processing') && self::order_is_raffle_only($order)) {
            return 'Paid';
        }

        return wc_get_order_status_name($order->get_status());
    }

    /**
     * This filter is also used by WooCommerce's customer cancel endpoint, not
     * only the My Account button. Returning no eligible statuses for a raffle
     * or mixed order preserves administrator cancellation/refund capabilities.
     */
    public static function customer_cancellable_statuses($statuses, $order) {
        if (self::order_contains_raffle_entry($order)) return [];

        // WooCommerce normally limits customer cancellation to pending/failed.
        // RaffleLB additionally permits a still-processing, tangible Buy Direct
        // COD order, based on the gateway ID rather than its display label.
        if (
            $order instanceof WC_Order
            && $order->has_status('processing')
            && $order->get_payment_method() === 'cod'
            && self::order_is_buy_direct_only($order)
            && !in_array('processing', $statuses, true)
        ) {
            $statuses[] = 'processing';
        }

        return $statuses;
    }

    public static function account_entries_grouped(){
        global $wpdb;
        $uid = get_current_user_id();
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT e.*, r.id AS result_id, r.entry_id AS winning_entry_id,
                    r.entry_number AS winning_entry_number, r.user_id AS winner_user_id,
                    r.selected_at AS winner_selected_at,
                    r.fulfillment_status, r.contacted_at, r.claimed_at, r.fulfilled_at
             FROM {$wpdb->prefix}".\RaffleLB\Core\Contracts::ENTRY_TABLE." e
             LEFT JOIN {$wpdb->prefix}".\RaffleLB\Core\Contracts::RESULT_TABLE." r ON r.product_id=e.product_id
             WHERE e.user_id=%d AND e.status='active'
             ORDER BY e.created_at DESC, e.id DESC",
            $uid
        ));

        echo '<section class="rlmy" data-rlmy-tabs>';
        echo '<header class="rlmy-heading"><h2>My Entries</h2><p>View all your raffle entries and ticket numbers.</p></header>';
        if (!$rows) {
            echo '<div class="rlmy-empty"><strong>No raffle entries yet</strong><p>Your tickets will appear here after you enter a raffle.</p></div></section>';
            return;
        }

        $groups = [];
        foreach ($rows as $row) {
            $pid = absint($row->product_id);
            if (!isset($groups[$pid])) $groups[$pid] = [];
            $groups[$pid][] = $row;
        }

        $counts = ['active'=>0, 'won'=>0, 'past'=>0];
        foreach ($groups as $pid => $tickets) {
            $first = reset($tickets);
            $status = (string)get_post_meta($pid, \RaffleLB\Core\Contracts::META_DRAW_STATUS, true);
            $closure = self::public_early_closure($pid);
            $is_cancelled = is_array($closure) && ($closure['mode'] ?? '') === 'cancel_refund';
            $bucket = ($status === 'winner_selected')
                ? ((int)$first->winner_user_id === $uid ? 'won' : 'past')
                : ($is_cancelled ? 'past' : 'active');
            $counts[$bucket]++;
        }

        echo '<nav class="rlmy-tabs" aria-label="Raffle entry filters">';
        foreach (['active'=>'Active','won'=>'Won','past'=>'Past'] as $key=>$label) {
            echo '<button type="button" class="'.($key === 'active' ? 'is-active' : '').'" data-rlmy-tab="'.esc_attr($key).'">'.esc_html($label).' <span>('.esc_html($counts[$key]).')</span></button>';
        }
        echo '</nav><div class="rlmy-list">';

        foreach ($groups as $pid => $tickets) {
            $first = reset($tickets);
            $product = function_exists('wc_get_product') ? wc_get_product($pid) : false;
            $title = $product ? $product->get_name() : get_the_title($pid);
            $url = get_permalink($pid);
            $selection_url = '';
            if ($product && class_exists('RaffleLB_Selection_Adapter') && method_exists('RaffleLB_Selection_Adapter', 'selection_url')) {
                $selection_url = RaffleLB_Selection_Adapter::selection_url($product);
            } elseif ($product) {
                $selection_url = home_url('/selection/' . $product->get_slug() . '/');
            }
            $image = $product ? $product->get_image('woocommerce_thumbnail', ['loading'=>'lazy']) : '';
            $draw_status = (string)get_post_meta($pid, \RaffleLB\Core\Contracts::META_DRAW_STATUS, true);
            $closure = self::public_early_closure($pid);
            $is_cancelled = is_array($closure) && ($closure['mode'] ?? '') === 'cancel_refund';
            $is_complete = $draw_status === 'winner_selected';
            $is_awaiting = $draw_status === 'ready_to_draw' && !$is_cancelled;
            $is_won = $is_complete && (int)$first->winner_user_id === $uid;
            $bucket = $is_complete ? ($is_won ? 'won' : 'past') : ($is_cancelled ? 'past' : 'active');
            $card_status_label = $is_complete ? 'Completed' : ($is_cancelled ? 'Cancelled' : ($is_awaiting ? 'Awaiting Selection' : 'Active'));

            $fulfillment_status = $is_won && !empty($first->fulfillment_status)
                ? sanitize_key((string) $first->fulfillment_status)
                : 'pending';
            $fulfillment_labels = [
                'pending'   => 'Pending Fulfillment',
                'contacted' => 'Winner Contacted',
                'claimed'   => 'Prize Claimed',
                'fulfilled' => 'Prize Fulfilled',
            ];
            if (!isset($fulfillment_labels[$fulfillment_status])) $fulfillment_status = 'pending';
            $fulfillment_label = $fulfillment_labels[$fulfillment_status];
            $fulfillment_date = '';
            if ($fulfillment_status === 'contacted' && !empty($first->contacted_at)) {
                $fulfillment_date = wp_date('j M Y', strtotime((string) $first->contacted_at));
            } elseif ($fulfillment_status === 'claimed' && !empty($first->claimed_at)) {
                $fulfillment_date = wp_date('j M Y', strtotime((string) $first->claimed_at));
            } elseif ($fulfillment_status === 'fulfilled' && !empty($first->fulfilled_at)) {
                $fulfillment_date = wp_date('j M Y', strtotime((string) $first->fulfilled_at));
            }

            $stats = RaffleLB_Draw_Engine::account_stats($pid, true);
            $total = $stats ? (int)$stats['total'] : 0;
            $claimed = $stats ? (int)$stats['claimed'] : 0;
            $percent = $stats ? (int)$stats['percent'] : 0;
            $left = max(0, $total - $claimed);

            $entry_price = $product ? (float)$product->get_price() : 0.0;
            $order = function_exists('wc_get_order') ? wc_get_order((int)$first->order_id) : false;
            if ($order) {
                foreach ($order->get_items('line_item') as $item) {
                    if ((int)$item->get_product_id() === $pid && (int)$item->get_quantity() > 0) {
                        $entry_price = (float)$item->get_total() / (int)$item->get_quantity();
                        break;
                    }
                }
            }
            $ticket_numbers = array_map(static function($ticket){
                return '#'.str_pad((string)$ticket->entry_number, 3, '0', STR_PAD_LEFT);
            }, $tickets);
            $closed_at = (string) get_post_meta($pid, '_rafflelb_draw_closed_at', true);
            $side_label = $is_cancelled ? 'Closure Date' : 'Selection Date';
            if ($is_complete && !empty($first->winner_selected_at)) {
                $date_value = wp_date('j M Y', strtotime((string)$first->winner_selected_at));
            } elseif (($is_cancelled || $is_awaiting) && $closed_at !== '') {
                $date_value = wp_date('j M Y', strtotime($closed_at));
            } elseif ($is_awaiting) {
                $date_value = 'Awaiting Selection';
            } elseif ($is_cancelled) {
                $date_value = 'Closed Early';
            } else {
                $date_value = 'When full';
            }

            echo '<article class="rlmy-card '.($is_complete ? 'is-complete ' : '').($is_cancelled ? 'is-cancelled ' : '').($is_won ? 'is-won' : '').'" data-rlmy-bucket="'.esc_attr($bucket).'"'.($bucket !== 'active' ? ' hidden' : '').'>';
            echo '<div class="rlmy-media" style="display:flex!important;flex-direction:column!important;align-items:center!important;overflow:visible!important"><a href="'.esc_url($url).'">'.wp_kses_post($image).'</a><span class="rlmy-status rlmy-status-media">'.esc_html($card_status_label).'</span></div>';
            echo '<div class="rlmy-content"><div class="rlmy-titleblock"><h3><a href="'.esc_url($url).'">'.esc_html($title).'</a></h3><span class="rlmy-status rlmy-status-inline">'.esc_html($card_status_label).'</span></div>';
            echo '<div class="rlmy-stats"><div><span style="font-family:var(--rl-font, &quot;Manrope&quot;, sans-serif)!important;font-size:14px!important;font-weight:600!important;line-height:1.25!important">Entries</span><strong style="font-family:var(--rl-font, &quot;Manrope&quot;, sans-serif)!important;font-size:16px!important;font-weight:750!important;line-height:1.3!important">'.esc_html(count($tickets)).'</strong></div><div><span style="font-family:var(--rl-font, &quot;Manrope&quot;, sans-serif)!important;font-size:14px!important;font-weight:600!important;line-height:1.25!important">Entry Price</span><strong style="font-family:var(--rl-font, &quot;Manrope&quot;, sans-serif)!important;font-size:16px!important;font-weight:750!important;line-height:1.3!important">'.wp_kses_post(wc_price($entry_price)).'</strong></div><div><span style="font-family:var(--rl-font, &quot;Manrope&quot;, sans-serif)!important;font-size:14px!important;font-weight:600!important;line-height:1.25!important">Total Spent</span><strong style="font-family:var(--rl-font, &quot;Manrope&quot;, sans-serif)!important;font-size:16px!important;font-weight:750!important;line-height:1.3!important">'.wp_kses_post(wc_price($entry_price * count($tickets))).'</strong></div></div>';
            echo '<div class="rlmy-ticket-label">Your Ticket Numbers</div><div class="rlmy-tickets">';
            foreach ($ticket_numbers as $number) echo '<span>'.esc_html($number).'</span>';
            echo '</div>';
            if (!$is_complete && !$is_cancelled && !$is_awaiting && $total > 0) {
                echo '<div class="rlmy-progress"><div class="rlmy-track"><i style="width:'.esc_attr($percent).'%"></i></div><div><span>'.esc_html($claimed).' / '.esc_html($total).' entries sold</span><span>'.esc_html($left).' left</span></div></div>';
            } elseif ($is_won) {
                echo '<div class="rlmy-winner"><span aria-hidden="true">&#127942;</span><div><strong>You Won!</strong><small>Winning Ticket: #'.esc_html(str_pad((string)$first->winning_entry_number,3,'0',STR_PAD_LEFT)).'</small></div></div>';

                $fulfillment_messages = [
                    'pending'   => 'Our team will contact you regarding your prize.',
                    'contacted' => 'RaffleLB has contacted you regarding prize fulfillment.',
                    'claimed'   => 'Your prize has been marked as claimed.',
                    'fulfilled' => 'Prize fulfillment has been completed.',
                ];
                echo '<div class="rlmy-fulfillment is-'.esc_attr($fulfillment_status).'">';
                echo '<div><span>Prize Fulfillment</span><strong>'.esc_html($fulfillment_label).'</strong></div>';
                echo '<p>'.esc_html($fulfillment_messages[$fulfillment_status]);
                if ($fulfillment_date !== '') echo ' <small>'.esc_html($fulfillment_date).'</small>';
                echo '</p></div>';
            }
            echo '</div><aside class="rlmy-side"><span>'.esc_html($side_label).'</span><strong>'.esc_html($date_value).'</strong><div class="rlb-raffle-actions"><a class="rlb-raffle-action rlb-raffle-action--secondary" href="'.esc_url($url).'"><span class="rlb-raffle-action__label">View Raffle</span><span class="rlb-raffle-action__arrow" aria-hidden="true">&#8594;</span></a>';
            if ($selection_url !== '') {
                echo '<a class="rlb-raffle-action rlb-raffle-action--primary" href="'.esc_url($selection_url).'"><span class="rlb-raffle-action__label">View Selection Status</span><span class="rlb-raffle-action__arrow" aria-hidden="true">&#8594;</span></a>';
            }
            echo '</div></aside>';
            echo '</article>';
        }
        echo '</div><div class="rlmy-no-tab" hidden>No entries in this section.</div></section>';
        ?>
        <script>
        (function(){
            var root=document.querySelector('[data-rlmy-tabs]'); if(!root)return;
            var buttons=root.querySelectorAll('[data-rlmy-tab]'), cards=root.querySelectorAll('[data-rlmy-bucket]'), empty=root.querySelector('.rlmy-no-tab');
            buttons.forEach(function(button){button.addEventListener('click',function(){
                var bucket=button.getAttribute('data-rlmy-tab'), visible=0;
                buttons.forEach(function(item){item.classList.toggle('is-active',item===button)});
                cards.forEach(function(card){var show=card.getAttribute('data-rlmy-bucket')===bucket;card.hidden=!show;if(show)visible++;});
                if(empty)empty.hidden=visible!==0;
            })});
        })();
        </script>
        <?php
    }

    public static function account_entries(){
        if (is_user_logged_in()) {
            self::account_entries_grouped();
            return;
        }
        if(!is_user_logged_in()){echo '<p>Please log in to view your entries.</p>';return;}

        global $wpdb;
        $uid = get_current_user_id();

        $rows=$wpdb->get_results($wpdb->prepare(
            "SELECT e.*,
                    r.id AS result_id,
                    CASE WHEN r.entry_id = e.id THEN 1 ELSE 0 END AS is_winner,
                    r.selected_at AS winner_selected_at
             FROM {$wpdb->prefix}".\RaffleLB\Core\Contracts::ENTRY_TABLE." e
             LEFT JOIN {$wpdb->prefix}".\RaffleLB\Core\Contracts::RESULT_TABLE." r
                ON r.product_id=e.product_id AND r.entry_id=e.id
             WHERE e.user_id=%d AND e.status='active'
             ORDER BY is_winner DESC, e.created_at DESC, e.id DESC",
            $uid
        ));

        echo '<h3>My RaffleLB Entries</h3>';
        if(!$rows){echo '<p>You do not have any raffle entries yet.</p>';return;}

        // Dismissing here only hides the winning-entry summary card so it stops
        // stacking up over time. The underlying draw result record, and the
        // full entry below (order, status, etc.), are never touched — this
        // reuses the same acknowledgement list as the dashboard winner banner.
        $dismissed_win_ids = self::dismissed_win_ids($uid);
        $winning_rows = array_values(array_filter($rows, static function($row) use ($dismissed_win_ids){
            return !empty($row->is_winner) && !in_array((int)$row->result_id, $dismissed_win_ids, true);
        }));

        if ($winning_rows) {
            $dismiss_nonce = wp_create_nonce('rafflelb_dismiss_winner');
            echo '<div class="rafflelb-winning-cards" style="display:grid;grid-template-columns:1fr;gap:22px;margin:0 0 30px;">';
            $win_number = 0;
            foreach ($winning_rows as $winner) {
                $win_number++;
                echo '<div class="rafflelb-winning-entry-card" data-result-id="'.esc_attr((int)$winner->result_id).'" data-nonce="'.esc_attr($dismiss_nonce).'" style="position:relative;display:block;width:100%;box-sizing:border-box;border:1px solid rgba(202,255,22,.72);border-radius:16px;background:#11150e;padding:22px 24px;margin:0;overflow:hidden;box-shadow:0 10px 28px rgba(0,0,0,.20);">';
                echo '<button type="button" class="rafflelb-winning-entry-dismiss" aria-label="Dismiss" style="all:unset;box-sizing:border-box;position:absolute;top:16px;right:16px;width:28px;height:28px;display:flex;align-items:center;justify-content:center;border:1px solid rgba(202,255,22,.45);border-radius:50%;background:rgba(255,255,255,.06);color:#e3e8dd;font-size:16px;line-height:1;cursor:pointer;">&times;</button>';
                echo '<div class="rafflelb-winning-entry-topline" style="display:flex;align-items:center;justify-content:space-between;gap:12px;padding-bottom:14px;margin-bottom:18px;margin-right:36px;border-bottom:1px solid rgba(202,255,22,.16);">';
                echo '<span class="rafflelb-winning-entry-label" style="display:inline-block;color:#caff16;font-weight:800;font-size:12px;letter-spacing:.04em;">🏆 WINNING ENTRY</span>';
                if (count($winning_rows) > 1) {
                    echo '<span class="rafflelb-winning-entry-count" style="display:inline-block;color:#a7afa0;font-size:10px;font-weight:800;letter-spacing:.1em;white-space:nowrap;">WIN '.esc_html($win_number).' OF '.esc_html(count($winning_rows)).'</span>';
                }
                echo '</div>';
                echo '<div class="rafflelb-winning-entry-body" style="display:flex;align-items:center;gap:18px;min-height:82px;">';
                echo '<div class="rafflelb-winning-entry-icon" style="flex:0 0 auto;font-size:34px;line-height:1;">🏆</div>';
                echo '<div class="rafflelb-winning-entry-copy" style="min-width:0;">';
                echo '<h3 style="margin:0 0 10px!important;color:#fff;">'.esc_html(get_the_title($winner->product_id)).'</h3>';
                echo '<p style="margin:0 0 10px!important;color:#d6dbd1;">Entry <strong style="color:#fff;">#'.esc_html(str_pad((string)$winner->entry_number,3,'0',STR_PAD_LEFT)).'</strong> was selected as the winner.</p>';
                if (!empty($winner->winner_selected_at)) {
                    echo '<small style="display:block;color:#8f9888;margin-top:6px;">Winner selected: '.esc_html($winner->winner_selected_at).'</small>';
                }
                echo '</div>';
                echo '</div>';
                echo '</div>';
            }
            echo '</div>';
            ?>
            <script>
            document.querySelectorAll('.rafflelb-winning-entry-card .rafflelb-winning-entry-dismiss').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var card = btn.closest('.rafflelb-winning-entry-card');
                    if (!card) return;
                    btn.disabled = true;
                    var body = new URLSearchParams();
                    body.append('action', 'rafflelb_dismiss_winner_banner');
                    body.append('nonce', card.getAttribute('data-nonce'));
                    body.append('ids[]', card.getAttribute('data-result-id'));
                    fetch('<?php echo esc_url(admin_url('admin-ajax.php')); ?>', {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: body.toString()
                    }).then(function () {
                        card.style.display = 'none';
                    }).catch(function () {
                        btn.disabled = false;
                    });
                });
            });
            </script>
            <?php
        }

        $unique_raffles = [];
        $active_entry_count = 0;
        $any_active_raffle = false;
        foreach ($rows as $summary_row) {
            $unique_raffles[(int)$summary_row->product_id] = true;
            $row_draw_status = (string) get_post_meta((int)$summary_row->product_id, \RaffleLB\Core\Contracts::META_DRAW_STATUS, true);
            if ($row_draw_status !== 'winner_selected') {
                $active_entry_count++;
                $any_active_raffle = true;
            }
        }

        echo '<div class="rafflelb-entry-summary">';
            echo '<div><span>ACTIVE ENTRIES</span><strong>'.esc_html($active_entry_count).'</strong></div>';
            echo '<div><span>RAFFLES ENTERED</span><strong>'.esc_html(count($unique_raffles)).'</strong></div>';
            echo '<div><span>STATUS</span><strong class="'.($any_active_raffle ? 'is-live' : 'is-complete').'">'.($any_active_raffle ? 'ACTIVE' : 'COMPLETED').'</strong></div>';
        echo '</div>';

        echo '<div class="rafflelb-entry-cards">';

        foreach($rows as $r){
            $product = function_exists('wc_get_product') ? wc_get_product((int)$r->product_id) : false;
            $title = $product ? $product->get_name() : get_the_title($r->product_id);
            $image = $product ? $product->get_image('woocommerce_thumbnail', ['loading'=>'lazy']) : '';
            $product_url = get_permalink((int)$r->product_id);

            $stats = RaffleLB_Draw_Engine::account_stats((int)$r->product_id, true);
            $total = $stats ? (int)$stats['total'] : 0;
            $claimed = $stats ? (int)$stats['claimed'] : 0;
            $percent = $stats ? (int)$stats['percent'] : 0;

            $order = function_exists('wc_get_order') ? wc_get_order((int)$r->order_id) : false;
            $order_status = $order ? self::customer_order_status_label($order) : '';
            $order_url = $order ? $order->get_view_order_url() : '';

            $date_display = $r->created_at;
            $timestamp = strtotime((string)$r->created_at);
            if ($timestamp) {
                $date_display = wp_date(get_option('date_format').' · '.get_option('time_format'), $timestamp);
            }

            $draw_status = (string) get_post_meta($r->product_id, \RaffleLB\Core\Contracts::META_DRAW_STATUS, true);
            $closure = self::public_early_closure((int) $r->product_id);
            $entry_cancelled = is_array($closure) && ($closure['mode'] ?? '') === 'cancel_refund';
            if (!empty($r->is_winner)) {
                $result_label = 'WINNING ENTRY';
                $result_class = 'is-winner';
            } elseif ($draw_status === 'winner_selected') {
                $result_label = 'SELECTION COMPLETE';
                $result_class = 'is-complete';
            } elseif ($entry_cancelled) {
                $result_label = 'RAFFLE CANCELLED';
                $result_class = 'is-cancelled';
            } elseif ($draw_status === 'ready_to_draw') {
                $result_label = 'AWAITING SELECTION';
                $result_class = 'is-pending';
            } else {
                $result_label = 'LIVE';
                $result_class = 'is-live';
            }

            echo '<article class="rafflelb-entry-card'.(!empty($r->is_winner) ? ' is-winning' : '').'">';
                echo '<div class="rafflelb-entry-main">';
                    echo '<a class="rafflelb-entry-thumb" href="'.esc_url($product_url).'">'.wp_kses_post($image).'</a>';
                    echo '<div class="rafflelb-entry-prize">';
                        echo '<span class="rafflelb-entry-kicker">YOUR RAFFLE ENTRY</span>';
                        echo '<h4><a href="'.esc_url($product_url).'">'.esc_html($title).'</a></h4>';
                        echo '<div class="rafflelb-entry-badges">';
                            echo '<span class="rafflelb-entry-number">ENTRY #'.esc_html(str_pad((string)$r->entry_number,3,'0',STR_PAD_LEFT)).'</span>';
                            echo '<span class="rafflelb-entry-result '.esc_attr($result_class).'">'.esc_html($result_label).'</span>';
                        echo '</div>';
                    echo '</div>';
                echo '</div>';

                echo '<div class="rafflelb-entry-details">';
                    echo '<div><span>ORDER</span>';
                    if ($order_url) {
                        echo '<strong><a href="'.esc_url($order_url).'">#'.esc_html($r->order_id).'</a></strong>';
                    } else {
                        echo '<strong>#'.esc_html($r->order_id).'</strong>';
                    }
                    echo '</div>';

                    echo '<div><span>PAYMENT / ORDER STATUS</span><strong>'.esc_html($order_status ?: 'Confirmed').'</strong></div>';
                    echo '<div><span>ENTRY DATE</span><strong>'.esc_html($date_display).'</strong></div>';
                    $entry_status_text = ($draw_status === 'winner_selected' || $draw_status === 'ready_to_draw' || $entry_cancelled)
                        ? ucwords(strtolower($result_label))
                        : 'Eligible';
                    echo '<div><span>ENTRY STATUS</span><strong>'.esc_html($entry_status_text).'</strong></div>';
                echo '</div>';

                if ($total > 0 && $draw_status === 'live' && !$entry_cancelled) {
                    echo '<div class="rafflelb-entry-progress">';
                        echo '<div class="rafflelb-entry-progress-head">';
                            echo '<span>RAFFLE PROGRESS</span>';
                            echo '<strong>'.esc_html($claimed).' / '.esc_html($total).' ENTRIES</strong>';
                        echo '</div>';
                        echo '<div class="rafflelb-entry-progress-track"><i style="width:'.esc_attr($percent).'%"></i></div>';
                    echo '</div>';
                }

                echo '<div class="rafflelb-entry-actions">';
                    echo '<a href="'.esc_url($product_url).'">VIEW RAFFLE</a>';
                    if ($order_url) {
                        echo '<a class="is-secondary" href="'.esc_url($order_url).'">VIEW ORDER</a>';
                    }
                echo '</div>';
            echo '</article>';
        }

        echo '</div>';

        $void_rows = $wpdb->get_results($wpdb->prepare(
            "SELECT h.*
             FROM {$wpdb->prefix}" . \RaffleLB\Core\Contracts::HISTORY_TABLE . " h
             WHERE h.user_id=%d AND h.event_type='void'
             ORDER BY h.event_at DESC, h.id DESC",
            $uid
        ));

        if ($void_rows) {
            echo '<h3 style="margin-top:28px">Voided Entries</h3>';
            echo '<p>These entries are kept for your records but are not eligible to win.</p>';
            echo '<table class="shop_table rafflelb-entries-table rafflelb-void-entries-table"><thead><tr><th>Raffle</th><th>Entry</th><th>Order</th><th>Voided</th><th>Status</th></tr></thead><tbody>';
            foreach ($void_rows as $v) {
                echo '<tr>';
                echo '<td>'.esc_html(get_the_title($v->product_id)).'</td>';
                echo '<td><strong>#'.esc_html(str_pad((string)$v->entry_number,3,'0',STR_PAD_LEFT)).'</strong></td>';
                echo '<td>#'.esc_html($v->order_id).'</td>';
                echo '<td>'.esc_html($v->event_at).'</td>';
                echo '<td><span style="color:#ff8c8c;font-weight:800">VOID — NOT ELIGIBLE</span></td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
        }
    }

    public static function account_body_class($classes) {
        if (function_exists('is_account_page') && is_account_page()) {
            $classes[] = 'rafflelb-account-page';
        }
        return $classes;
    }

    public static function account_styles() {
        if (!function_exists('is_account_page') || !is_account_page()) {
            return;
        }
        ?>
        <style id="rafflelb-account-styles">
        body.rafflelb-account-page{
            background:#0b0c0a;
        }
        .rafflelb-account-page .main-page-wrapper,
        .rafflelb-account-page .site-content{
            background:#0b0c0a;
        }

        /* Keep global RaffleLB header white */
        .rafflelb-account-page .whb-header,
        .rafflelb-account-page .whb-row,
        .rafflelb-account-page .whb-general-header,
        .rafflelb-account-page .whb-header .container{
            background:#fff !important;
        }

        .rafflelb-account-page .site-content{
            padding-top:44px;
            padding-bottom:60px;
        }

        .rafflelb-account-page .woocommerce{
            max-width:1180px;
            margin:0 auto;
            display:grid;
            grid-template-columns:250px minmax(0,1fr);
            gap:30px;
            align-items:start;
        }

        /* Left navigation */
        .rafflelb-account-page .woocommerce-MyAccount-navigation{
            width:100% !important;
            float:none !important;
            margin:0 !important;
            padding:20px;
            border:1px solid #282b26;
            border-radius:18px;
            background:linear-gradient(145deg,#151713,#10110f);
            position:sticky;
            top:20px;
        }
        .rafflelb-account-page .woocommerce-MyAccount-navigation ul{
            margin:0;
            padding:0;
            list-style:none;
        }
        .rafflelb-account-page .woocommerce-MyAccount-navigation li{
            margin:0 0 8px;
            padding:0;
            border:0 !important;
        }
        .rafflelb-account-page .woocommerce-MyAccount-navigation li:last-child{
            margin-bottom:0;
            padding-top:10px;
            border-top:1px solid #292c27 !important;
        }
        .rafflelb-account-page .woocommerce-MyAccount-navigation a{
            display:flex;
            align-items:center;
            min-height:46px;
            padding:0 14px;
            border-radius:11px;
            color:#c5c9bf !important;
            font-size:13px;
            font-weight:700;
            text-decoration:none !important;
            transition:.2s ease;
        }
        .rafflelb-account-page .woocommerce-MyAccount-navigation a:hover{
            background:#1b1e18;
            color:#fff !important;
        }
        .rafflelb-account-page .woocommerce-MyAccount-navigation .is-active a{
            background:#caff16 !important;
            color:#0a0b09 !important;
        }

        /* Main content */
        .rafflelb-account-page .woocommerce-MyAccount-content{
            width:100% !important;
            float:none !important;
            margin:0 !important;
            padding:30px;
            min-height:520px;
            border:1px solid #282b26;
            border-radius:18px;
            background:#121310;
            color:#d8dcd3;
        }
        .rafflelb-account-page .woocommerce-MyAccount-content > p:first-child{
            margin-top:0;
            padding:18px 20px;
            border:1px solid #2a2e27;
            border-radius:13px;
            background:#171915;
            color:#c5c9bf;
        }
        .rafflelb-account-page .woocommerce-MyAccount-content a{
            color:#caff16;
        }
        .rafflelb-account-page .woocommerce-MyAccount-content h2,
        .rafflelb-account-page .woocommerce-MyAccount-content h3,
        .rafflelb-account-page .woocommerce-MyAccount-content h4{
            color:#fff;
        }

        /* Dashboard tiles */
        .rafflelb-account-page .woocommerce-MyAccount-content .wd-my-account-links{
            display:grid !important;
            grid-template-columns:repeat(3,minmax(0,1fr));
            gap:16px;
            margin-top:22px;
        }
        .rafflelb-account-page .woocommerce-MyAccount-content .wd-my-account-links > div{
            width:auto !important;
            max-width:none !important;
            float:none !important;
            margin:0 !important;
            border:1px solid #2a2e27 !important;
            border-radius:15px !important;
            background:#171915 !important;
            overflow:hidden;
            transition:.2s ease;
        }
        .rafflelb-account-page .woocommerce-MyAccount-content .wd-my-account-links > div:hover{
            transform:translateY(-2px);
            border-color:#596236 !important;
        }
        .rafflelb-account-page .woocommerce-MyAccount-content .wd-my-account-links a{
            min-height:150px;
            display:flex !important;
            flex-direction:column;
            align-items:center;
            justify-content:center;
            gap:10px;
            padding:20px !important;
            color:#fff !important;
            font-weight:800;
            text-decoration:none !important;
        }
        .rafflelb-account-page .woocommerce-MyAccount-content .wd-my-account-links a:before{
            color:#caff16 !important;
            opacity:1 !important;
            font-size:40px !important;
        }

        /* Hide old WooCommerce dashboard tiles that no longer belong in menu */
        .rafflelb-account-page .wd-my-account-links .downloads-link,
        .rafflelb-account-page .wd-my-account-links .edit-address-link{
            display:none !important;
        }

        /* Tables: orders and My Entries */
        .rafflelb-account-page table.shop_table{
            width:100%;
            border:1px solid #2a2e27 !important;
            border-radius:14px !important;
            overflow:hidden;
            background:#171915 !important;
            color:#d6d9d1;
        }
        .rafflelb-account-page table.shop_table th{
            padding:14px 16px !important;
            border-color:#2a2e27 !important;
            background:#1c1f19 !important;
            color:#8f9589 !important;
            font-size:11px;
            letter-spacing:.8px;
            text-transform:uppercase;
        }
        .rafflelb-account-page table.shop_table td{
            padding:14px 16px !important;
            border-color:#2a2e27 !important;
            color:#e6e8e2 !important;
        }
        .rafflelb-account-page table.shop_table tr:hover td{
            background:#1a1c18;
        }

        /* Forms */
        .rafflelb-account-page .woocommerce-MyAccount-content label{
            color:#c2c6bc;
            font-size:12px;
            font-weight:700;
        }
        .rafflelb-account-page .woocommerce-MyAccount-content input.input-text,
        .rafflelb-account-page .woocommerce-MyAccount-content input[type="text"],
        .rafflelb-account-page .woocommerce-MyAccount-content input[type="email"],
        .rafflelb-account-page .woocommerce-MyAccount-content input[type="password"]{
            min-height:48px;
            border:1px solid #34382f !important;
            border-radius:10px !important;
            background:#191b17 !important;
            color:#fff !important;
            box-shadow:none !important;
        }
        .rafflelb-account-page .woocommerce-MyAccount-content button.button,
        .rafflelb-account-page .woocommerce-MyAccount-content a.button{
            border:0 !important;
            border-radius:999px !important;
            background:#caff16 !important;
            color:#090a08 !important;
            font-weight:900 !important;
        }

        @media(max-width:900px){
            .rafflelb-account-page .woocommerce{
                display:block;
            }
            .rafflelb-account-page .woocommerce-MyAccount-navigation{
                position:static;
                margin-bottom:20px !important;
            }
            .rafflelb-account-page .woocommerce-MyAccount-navigation ul{
                display:flex;
                gap:8px;
                overflow-x:auto;
                padding-bottom:4px;
            }
            .rafflelb-account-page .woocommerce-MyAccount-navigation li{
                flex:0 0 auto;
                margin:0;
            }
            .rafflelb-account-page .woocommerce-MyAccount-navigation li:last-child{
                padding-top:0;
                border-top:0 !important;
            }
            .rafflelb-account-page .woocommerce-MyAccount-content{
                padding:20px;
            }
            .rafflelb-account-page .woocommerce-MyAccount-content .wd-my-account-links{
                grid-template-columns:1fr 1fr;
            }
        }
        @media(max-width:560px){
            .rafflelb-account-page .woocommerce-MyAccount-content .wd-my-account-links{
                grid-template-columns:1fr;
            }
        }

        /* v0.10.6: logged-out Login / Register readability */
        .rafflelb-account-page .woocommerce > h2,
        .rafflelb-account-page .woocommerce .u-column1 h2,
        .rafflelb-account-page .woocommerce .u-column2 h2,
        .rafflelb-account-page .woocommerce-form-login,
        .rafflelb-account-page .woocommerce-form-register,
        .rafflelb-account-page .woocommerce-form-login p,
        .rafflelb-account-page .woocommerce-form-register p,
        .rafflelb-account-page .woocommerce-form-login label,
        .rafflelb-account-page .woocommerce-form-register label{
            color:#dfe3da !important;
            opacity:1 !important;
        }
        .rafflelb-account-page .woocommerce .u-column1 h2,
        .rafflelb-account-page .woocommerce .u-column2 h2{
            color:#ffffff !important;
        }
        .rafflelb-account-page .woocommerce-form-login input.input-text,
        .rafflelb-account-page .woocommerce-form-register input.input-text{
            color:#ffffff !important;
            -webkit-text-fill-color:#ffffff !important;
            caret-color:#caff16 !important;
            background:#171915 !important;
            border-color:#34382f !important;
            opacity:1 !important;
        }
        .rafflelb-account-page .woocommerce-form-login input::placeholder,
        .rafflelb-account-page .woocommerce-form-register input::placeholder{
            color:#8f9589 !important;
            -webkit-text-fill-color:#8f9589 !important;
            opacity:1 !important;
        }
        .rafflelb-account-page .woocommerce-form-login .woocommerce-LostPassword a,
        .rafflelb-account-page .woocommerce-form-login .lost_password a{
            color:#caff16 !important;
            opacity:1 !important;
        }
        .rafflelb-account-page .woocommerce-form-login button.button,
        .rafflelb-account-page .woocommerce-form-register button.button{
            color:#090a08 !important;
            background:#caff16 !important;
        }

        /* v0.8.1: WoodMart My Account wrapper width/layout correction */
        body.rafflelb-account-page .site-content,
        body.rafflelb-account-page .site-content > .container,
        body.rafflelb-account-page .main-page-wrapper .container {
            width:100% !important;
            max-width:1240px !important;
        }
        body.rafflelb-account-page .woocommerce {
            display:block !important;
            width:100% !important;
            max-width:none !important;
        }
        body.rafflelb-account-page .woocommerce-my-account-wrapper {
            display:grid !important;
            grid-template-columns:250px minmax(0,1fr) !important;
            gap:30px !important;
            width:100% !important;
            max-width:none !important;
            align-items:start !important;
        }
        body.rafflelb-account-page .woocommerce-MyAccount-navigation {
            width:100% !important;
            max-width:none !important;
            flex:none !important;
        }
        body.rafflelb-account-page .woocommerce-MyAccount-content {
            width:100% !important;
            max-width:none !important;
            min-width:0 !important;
            flex:none !important;
        }
        @media(max-width:900px){
            body.rafflelb-account-page .woocommerce-my-account-wrapper{
                display:block !important;
            }
        }


        /* v0.8.2 account readability */
        .rafflelb-account-page .woocommerce-MyAccount-content,
        .rafflelb-account-page .woocommerce-MyAccount-content p,
        .rafflelb-account-page .woocommerce-MyAccount-content span,
        .rafflelb-account-page .woocommerce-MyAccount-content li,
        .rafflelb-account-page .woocommerce-MyAccount-content strong,
        .rafflelb-account-page .woocommerce-MyAccount-content em{
            color:#dfe3da !important;
        }

        .rafflelb-account-page .woocommerce-MyAccount-content a{
            color:#caff16 !important;
        }

        .rafflelb-account-page .woocommerce-MyAccount-content .wd-my-account-links a{
            color:#ffffff !important;
        }

        .rafflelb-account-page .woocommerce-MyAccount-content .wd-my-account-links a:hover{
            color:#caff16 !important;
        }

        .rafflelb-account-page .woocommerce-MyAccount-content table.shop_table,
        .rafflelb-account-page .woocommerce-MyAccount-content table.shop_table thead,
        .rafflelb-account-page .woocommerce-MyAccount-content table.shop_table tbody,
        .rafflelb-account-page .woocommerce-MyAccount-content table.shop_table tr,
        .rafflelb-account-page .woocommerce-MyAccount-content table.shop_table td{
            background:#171915 !important;
        }

        .rafflelb-account-page .woocommerce-MyAccount-content table.shop_table th{
            background:#1d201a !important;
            color:#b6bbaf !important;
        }

        .rafflelb-account-page .woocommerce-MyAccount-content table.shop_table td,
        .rafflelb-account-page .woocommerce-MyAccount-content table.shop_table td *,
        .rafflelb-account-page .woocommerce-MyAccount-content table.shop_table .amount{
            color:#eef0eb !important;
            opacity:1 !important;
        }

        .rafflelb-account-page .woocommerce-MyAccount-content table.shop_table a{
            color:#caff16 !important;
            font-weight:700 !important;
        }

        .rafflelb-account-page .woocommerce-MyAccount-content .woocommerce-orders-table__cell-order-actions a.button{
            color:#090a08 !important;
        }

        /* Make the small "MY ACCOUNT" heading visible */
        .rafflelb-account-page .woocommerce-MyAccount-navigation:before{
            content:"MY ACCOUNT";
            display:block;
            margin:0 0 14px;
            padding:0 4px 12px;
            border-bottom:1px solid #2b2e29;
            color:#ffffff;
            font-size:13px;
            font-weight:800;
            letter-spacing:1px;
        }


        /* v0.8.3 sidebar polish */
        .rafflelb-account-page .page-title,
        .rafflelb-account-page .entry-title,
        .rafflelb-account-page .woocommerce-MyAccount-title,
        .rafflelb-account-page .title-wrapper,
        .rafflelb-account-page .page-title-default,
        .rafflelb-account-page .woodmart-title-container{
            display:none !important;
        }

        .rafflelb-account-page .woocommerce-MyAccount-navigation{
            padding:22px 16px !important;
        }

        .rafflelb-account-page .woocommerce-MyAccount-navigation:before{
            margin:0 4px 16px !important;
            padding:0 2px 13px !important;
            color:#ffffff !important;
            font-size:12px !important;
            font-weight:900 !important;
            letter-spacing:1.4px !important;
        }

        .rafflelb-account-page .woocommerce-MyAccount-navigation li{
            margin:0 0 7px !important;
        }

        .rafflelb-account-page .woocommerce-MyAccount-navigation a{
            min-height:48px !important;
            padding:0 15px !important;
            border:1px solid transparent !important;
            border-radius:11px !important;
            color:#d8dcd3 !important;
            font-size:13px !important;
            font-weight:800 !important;
            letter-spacing:.15px !important;
        }

        .rafflelb-account-page .woocommerce-MyAccount-navigation a:hover{
            background:#1c1f1a !important;
            border-color:#33382f !important;
            color:#caff16 !important;
            transform:translateX(2px);
        }

        .rafflelb-account-page .woocommerce-MyAccount-navigation .is-active a{
            background:#caff16 !important;
            border-color:#caff16 !important;
            color:#090a08 !important;
            box-shadow:0 8px 22px rgba(202,255,22,.10);
        }

        .rafflelb-account-page .woocommerce-MyAccount-navigation li:last-child{
            margin-top:12px !important;
            padding-top:13px !important;
            border-top:1px solid #2d302b !important;
        }

        .rafflelb-account-page .woocommerce-MyAccount-navigation li:last-child a{
            color:#aeb3a8 !important;
        }

        .rafflelb-account-page .woocommerce-MyAccount-navigation li:last-child a:hover{
            color:#ffffff !important;
            background:#1a1c18 !important;
            border-color:#343832 !important;
        }

        /* v0.10.7: clean phone account navigation and entry cards */
        @media(max-width:700px){
            .rafflelb-account-page .site-content{
                padding-top:24px !important;
                padding-bottom:36px !important;
            }
            body.rafflelb-account-page .site-content > .container,
            body.rafflelb-account-page .main-page-wrapper .container{
                width:calc(100% - 28px) !important;
                padding-left:0 !important;
                padding-right:0 !important;
            }
            .rafflelb-account-page .woocommerce-MyAccount-navigation{
                padding:18px 16px !important;
                margin-bottom:18px !important;
            }
            .rafflelb-account-page .woocommerce-MyAccount-navigation ul{
                display:grid !important;
                grid-template-columns:1fr 1fr !important;
                gap:8px !important;
                overflow:visible !important;
                width:100% !important;
                padding:0 !important;
            }
            .rafflelb-account-page .woocommerce-MyAccount-navigation li,
            .rafflelb-account-page .woocommerce-MyAccount-navigation li:last-child{
                width:100% !important;
                margin:0 !important;
                padding:0 !important;
                border:0 !important;
            }
            .rafflelb-account-page .woocommerce-MyAccount-navigation a{
                width:100% !important;
                min-height:44px !important;
                padding:0 11px !important;
                justify-content:center !important;
                text-align:center !important;
                font-size:12px !important;
                white-space:normal !important;
            }
            .rafflelb-account-page .woocommerce-MyAccount-content{
                min-height:0 !important;
                padding:20px 16px !important;
            }
            .rafflelb-account-page .woocommerce-MyAccount-content h3{
                font-size:25px !important;
                line-height:1.15 !important;
            }
            .rafflelb-account-page .woocommerce-MyAccount-content table.rafflelb-entries-table{
                display:block !important;
                border:0 !important;
                background:transparent !important;
                overflow:visible !important;
            }
            .rafflelb-account-page .woocommerce-MyAccount-content table.rafflelb-entries-table thead{
                display:none !important;
            }
            .rafflelb-account-page .woocommerce-MyAccount-content table.rafflelb-entries-table tbody{
                display:grid !important;
                gap:12px !important;
                background:transparent !important;
            }
            .rafflelb-account-page .woocommerce-MyAccount-content table.rafflelb-entries-table tr{
                display:block !important;
                padding:8px 16px !important;
                border:1px solid #2a2e27 !important;
                border-radius:14px !important;
                background:#171915 !important;
            }
            .rafflelb-account-page .woocommerce-MyAccount-content table.rafflelb-entries-table td{
                display:flex !important;
                align-items:center !important;
                justify-content:space-between !important;
                gap:18px !important;
                width:100% !important;
                padding:11px 0 !important;
                border:0 !important;
                border-bottom:1px solid #292d27 !important;
                text-align:right !important;
                overflow-wrap:anywhere !important;
            }
            .rafflelb-account-page .woocommerce-MyAccount-content table.rafflelb-entries-table td:last-child{
                border-bottom:0 !important;
            }
            .rafflelb-account-page .woocommerce-MyAccount-content table.rafflelb-entries-table td:before{
                flex:0 0 74px !important;
                color:#8f9589 !important;
                font-size:10px !important;
                font-weight:800 !important;
                letter-spacing:.12em !important;
                text-align:left !important;
            }
            .rafflelb-account-page .woocommerce-MyAccount-content table.rafflelb-entries-table td:nth-child(1):before{content:"RAFFLE";}
            .rafflelb-account-page .woocommerce-MyAccount-content table.rafflelb-entries-table td:nth-child(2):before{content:"ENTRY";}
            .rafflelb-account-page .woocommerce-MyAccount-content table.rafflelb-entries-table td:nth-child(3):before{content:"ORDER";}
            .rafflelb-account-page .woocommerce-MyAccount-content table.rafflelb-entries-table td:nth-child(4):before{content:"DATE";}
        }

/* =========================================================
   v0.26.3 — FULL-WIDTH DARK RAFFLE SECTION
   ========================================================= */

.rl-live-raffles-shell{
    width:100vw!important;
    max-width:100vw!important;
    margin:0 calc(50% - 50vw)!important;
    padding:42px 24px 58px!important;
    background:
        radial-gradient(circle at 50% 0,rgba(202,255,22,.04),transparent 28%),
        #070807!important;
    border:0!important;
    border-radius:0!important;
    box-shadow:none!important;
}

.rl-live-raffles-shell-inner{
    width:min(1260px,100%)!important;
    margin:0 auto!important;
    padding:0!important;
    border:0!important;
    border-radius:0!important;
    background:transparent!important;
    box-shadow:none!important;
}

.rl-live-now{
    margin:0 0 28px!important;
    font-size:15px!important;
}

.rl-live-raffles-shell .rl-live-raffles-grid{
    width:100%!important;
    display:grid!important;
    grid-template-columns:repeat(var(--rl-live-cols),minmax(0,330px))!important;
    justify-content:center!important;
    gap:24px!important;
    margin:0 auto!important;
}

.rl-live-raffles-shell .rl-live-raffle-card{
    width:100%!important;
    max-width:330px!important;
    border:1px solid rgba(202,255,22,.46)!important;
    border-radius:16px!important;
    background:#0a0c0a!important;
    box-shadow:0 12px 28px rgba(0,0,0,.28)!important;
}

.rl-live-raffles-shell .rl-live-raffle-card:hover{
    transform:translateY(-3px)!important;
    border-color:rgba(202,255,22,.78)!important;
    box-shadow:0 18px 36px rgba(0,0,0,.34),0 0 22px rgba(202,255,22,.05)!important;
}

.rl-live-raffles-actions{
    margin-top:28px!important;
}

.rl-live-raffles-all{
    min-width:270px!important;
    padding:14px 22px!important;
    gap:36px!important;
}

@media(min-width:1200px){
    .rl-live-raffles-shell .rl-live-raffles-grid{
        grid-template-columns:repeat(var(--rl-live-cols),minmax(0,330px))!important;
    }
}

@media(max-width:1024px){
    .rl-live-raffles-shell{
        padding:34px 18px 48px!important;
    }
    .rl-live-raffles-shell .rl-live-raffles-grid{
        grid-template-columns:repeat(min(var(--rl-live-cols),3),minmax(0,300px))!important;
        gap:18px!important;
    }
}

@media(max-width:767px){
    .rl-live-raffles-shell{
        padding:28px 12px 42px!important;
    }
    .rl-live-now{
        margin-bottom:18px!important;
        font-size:12px!important;
    }
    .rl-live-raffles-shell .rl-live-raffles-grid{
        grid-template-columns:repeat(2,minmax(0,1fr))!important;
        gap:10px!important;
    }
    .rl-live-raffles-shell .rl-live-raffle-card{
        max-width:none!important;
    }
    .rl-live-raffles-actions{
        margin-top:22px!important;
    }
}

@media(max-width:390px){
    .rl-live-raffles-shell .rl-live-raffles-grid{
        grid-template-columns:1fr!important;
        width:min(100%,320px)!important;
    }
}

/* =========================================================
   v0.26.4 — TRUE FULL-WIDTH DARK STAGE
   New unique wrapper classes avoid old CSS collisions.
   ========================================================= */
.rl-live-stage-v264{
    position:relative!important;
    left:50%!important;
    width:100vw!important;
    max-width:none!important;
    margin-left:-50vw!important;
    margin-right:-50vw!important;
    padding:40px 24px 52px!important;

    border:0!important;
    border-radius:0!important;

    background:
        radial-gradient(circle at 50% 0,rgba(202,255,22,.045),transparent 30%),
        #070807!important;

    box-shadow:none!important;
    overflow:visible!important;
}

.rl-live-stage-content-v264{
    width:min(1220px,100%)!important;
    max-width:1220px!important;
    margin:0 auto!important;
    padding:0!important;

    border:0!important;
    border-radius:0!important;

    background:transparent!important;
    box-shadow:none!important;
}

.rl-live-stage-v264 .rl-live-now{
    margin:0 0 26px!important;
    color:#fff!important;
    text-align:center!important;
}

.rl-live-stage-v264 .rl-live-raffles-wrap{
    width:100%!important;
    max-width:none!important;
    margin:0!important;
    padding:0!important;
    background:transparent!important;
}

.rl-live-stage-v264 .rl-live-raffles-grid{
    display:grid!important;
    width:100%!important;
    max-width:none!important;
    margin:0 auto!important;

    grid-template-columns:repeat(var(--rl-live-cols),minmax(0,320px))!important;
    justify-content:center!important;
    align-items:stretch!important;
    gap:24px!important;
}

.rl-live-stage-v264 .rl-live-raffle-card{
    width:100%!important;
    max-width:320px!important;
    margin:0!important;

    border:1px solid rgba(202,255,22,.48)!important;
    border-radius:15px!important;

    background:#0a0c0a!important;
    box-shadow:0 12px 28px rgba(0,0,0,.30)!important;
}

.rl-live-stage-v264 .rl-live-raffles-actions{
    margin-top:26px!important;
}

.rl-live-stage-v264 .rl-live-raffles-all{
    min-width:260px!important;
    padding:14px 22px!important;
}

@media(max-width:1024px){
    .rl-live-stage-v264{
        padding:34px 18px 46px!important;
    }

    .rl-live-stage-v264 .rl-live-raffles-grid{
        grid-template-columns:repeat(min(var(--rl-live-cols),3),minmax(0,290px))!important;
        gap:18px!important;
    }
}

@media(max-width:767px){
    .rl-live-stage-v264{
        padding:28px 12px 38px!important;
    }

    .rl-live-stage-v264 .rl-live-now{
        margin-bottom:18px!important;
    }

    .rl-live-stage-v264 .rl-live-raffles-grid{
        grid-template-columns:repeat(2,minmax(0,1fr))!important;
        gap:10px!important;
    }

    .rl-live-stage-v264 .rl-live-raffle-card{
        max-width:none!important;
    }
}

@media(max-width:390px){
    .rl-live-stage-v264 .rl-live-raffles-grid{
        width:min(100%,320px)!important;
        grid-template-columns:1fr!important;
    }
}

/* ==========================================================
   v0.27.1 — HOMEPAGE SECTION SEAM FIX
   Physically overlaps neighboring blocks to eliminate thin
   Elementor/WoodMart background separators.
   ========================================================== */
body.home .rlp270-stage{
    margin-top:-3px!important;
    margin-bottom:-3px!important;
    z-index:2!important;
}

/* Remove spacing from the exact Elementor wrapper that owns shortcode */
body.home .elementor-element:has(.rlp270-stage),
body.home .elementor-widget:has(.rlp270-stage),
body.home .elementor-widget-shortcode:has(.rlp270-stage),
body.home .elementor-element:has(.rlp270-stage) > .elementor-widget-container{
    margin-top:0!important;
    margin-bottom:0!important;
    padding-top:0!important;
    padding-bottom:0!important;
    border-top:0!important;
    border-bottom:0!important;
}

/* Prevent line-box whitespace around shortcode output */
body.home .elementor-widget-shortcode:has(.rlp270-stage){
    line-height:0!important;
}
body.home .elementor-widget-shortcode:has(.rlp270-stage) .rlp270-stage{
    line-height:normal!important;
}

/* v0.27.2 — LIVE RAFFLE CARD PROPORTIONS */
body.home .rlp270-inner{max-width:1280px!important}
body.home .rlp270-grid{
 grid-template-columns:repeat(auto-fit,minmax(340px,380px))!important;
 justify-content:center!important;gap:24px!important
}
body.home .rlp270-card{width:100%!important;max-width:380px!important}
body.home .rlp270-media{
 height:235px!important;min-height:235px!important;padding:10px!important;
 display:flex!important;align-items:center!important;justify-content:center!important;
 overflow:hidden!important;background:#050705!important
}
body.home .rlp270-media img{
 width:100%!important;height:100%!important;max-width:100%!important;max-height:100%!important;
 object-fit:contain!important;object-position:center!important;display:block!important
}
body.home .rlp270-body{padding:18px 20px 20px!important}
body.home .rlp270-body h3{
 min-height:44px!important;margin-bottom:14px!important;line-height:1.25!important;
 display:-webkit-box!important;-webkit-line-clamp:2!important;-webkit-box-orient:vertical!important;overflow:hidden!important
}
body.home .rlp270-stats{padding:12px 0!important;margin-bottom:10px!important}
body.home .rlp270-progress{margin-top:10px!important}
body.home .rlp270-button{margin-top:14px!important}
@media (max-width:767px){
 body.home .rlp270-grid{grid-template-columns:minmax(0,380px)!important;gap:18px!important}
 body.home .rlp270-media{height:220px!important;min-height:220px!important}
}

/* ==========================================================
   v0.27.3 — PREMIUM LIVE CARD UI
   Stronger LIVE/category badges, stats, progress and CTA.
   ========================================================== */

/* LIVE badge */
.rlp270-live{
    top:14px!important;
    left:14px!important;
    min-height:28px!important;
    padding:7px 12px!important;
    border-radius:999px!important;
    background:#c6ff00!important;
    color:#050705!important;
    border:1px solid rgba(255,255,255,.18)!important;
    box-shadow:0 5px 20px rgba(198,255,0,.28)!important;
    font-size:10px!important;
    font-weight:900!important;
    letter-spacing:.7px!important;
    line-height:1!important;
    text-transform:uppercase!important;
}

/* Category badge */
.rlp270-category{
    top:14px!important;
    right:14px!important;
    min-height:28px!important;
    padding:7px 11px!important;
    border-radius:999px!important;
    background:rgba(5,7,5,.92)!important;
    color:#ffffff!important;
    border:1px solid rgba(198,255,0,.45)!important;
    box-shadow:0 5px 18px rgba(0,0,0,.40)!important;
    font-size:9px!important;
    font-weight:850!important;
    letter-spacing:1px!important;
    line-height:1!important;
    text-transform:uppercase!important;
    backdrop-filter:blur(8px)!important;
}

/* Information block */
.rlp270-stats{
    margin-top:2px!important;
    padding:14px 0!important;
    border-top:1px solid rgba(255,255,255,.10)!important;
    border-bottom:1px solid rgba(255,255,255,.10)!important;
    gap:14px!important;
}
.rlp270-stats > *{
    min-width:0!important;
}
.rlp270-stats small,
.rlp270-stats .label{
    color:rgba(255,255,255,.58)!important;
    font-size:8px!important;
    font-weight:850!important;
    letter-spacing:1.5px!important;
    text-transform:uppercase!important;
}
.rlp270-stats strong,
.rlp270-stats .value{
    color:#ffffff!important;
    font-size:13px!important;
    font-weight:850!important;
    line-height:1.2!important;
}

/* Progress / confirmed row */
.rlp270-progress{
    padding:2px 0 0!important;
}
.rlp270-progress,
.rlp270-progress *{
    font-weight:700!important;
}
.rlp270-progress > div:first-child{
    color:rgba(255,255,255,.72)!important;
}
.rlp270-progress strong,
.rlp270-progress b{
    color:#c6ff00!important;
}
.rlp270-track{
    height:6px!important;
    margin-top:9px!important;
    border-radius:999px!important;
    overflow:hidden!important;
    background:rgba(255,255,255,.12)!important;
    box-shadow:inset 0 1px 2px rgba(0,0,0,.45)!important;
}
.rlp270-track > *{
    background:linear-gradient(90deg,#9dcc00,#c6ff00)!important;
    box-shadow:0 0 12px rgba(198,255,0,.32)!important;
}

/* Premium View Raffle CTA */
.rlp270-button{
    min-height:44px!important;
    padding:0 16px!important;
    border:1px solid rgba(198,255,0,.70)!important;
    border-radius:12px!important;
    background:linear-gradient(180deg,rgba(198,255,0,.075),rgba(198,255,0,.018))!important;
    color:#ffffff!important;
    box-shadow:inset 0 1px 0 rgba(255,255,255,.035),0 8px 22px rgba(0,0,0,.20)!important;
    font-size:9px!important;
    font-weight:900!important;
    letter-spacing:1.25px!important;
    text-transform:uppercase!important;
    transition:background .2s ease,border-color .2s ease,color .2s ease,transform .2s ease,box-shadow .2s ease!important;
}
.rlp270-button:hover{
    background:#c6ff00!important;
    border-color:#c6ff00!important;
    color:#050705!important;
    transform:translateY(-1px)!important;
    box-shadow:0 9px 26px rgba(198,255,0,.20)!important;
}
.rlp270-button:hover *{
    color:#050705!important;
}

@media(max-width:767px){
    .rlp270-live,.rlp270-category{top:11px!important}
    .rlp270-live{left:11px!important}
    .rlp270-category{right:11px!important}
}

        </style>
        <?php
    }

    public static function legacy_callback_16373() {
    if (!is_user_logged_in() || !function_exists('is_account_page') || !is_account_page()) return;

    $user = wp_get_current_user();
    $user_id = get_current_user_id();
    $name = trim($user->display_name ?: $user->user_login);
    $initial = strtoupper(function_exists('mb_substr') ? mb_substr($name, 0, 1) : substr($name, 0, 1));

    $points = (float) get_user_meta($user_id, '_rafflelb_ref_points', true);
    $points_display = rtrim(rtrim(number_format($points, 2, '.', ''), '0'), '.');
    if ($points_display === '') $points_display = '0';

    $orders_count = 0;
    if (function_exists('wc_get_orders')) {
        $ids = wc_get_orders([
            'customer_id' => $user_id,
            'limit'       => -1,
            'return'      => 'ids',
            'status'      => array_keys(wc_get_order_statuses()),
        ]);
        $orders_count = is_array($ids) ? count($ids) : 0;
    }

    echo '<section class="rlacct-hero">';
        echo '<div class="rlacct-user">';
            echo '<div class="rlacct-avatar">' . esc_html($initial) . '</div>';
            echo '<div class="rlacct-copy">';
                echo '<span class="rlacct-kicker"><i class="rlacct-brand-mark" aria-hidden="true"><img src="' . esc_url(plugins_url('assets/rafflelb-site-icon.png', __FILE__)) . '" alt=""></i>RAFFLELB ACCOUNT</span>';
                echo '<h2>Welcome back, ' . esc_html($name) . '</h2>';
                echo '<p>Manage your entries, purchases, points and profile.</p>';
            echo '</div>';
        echo '</div>';
        echo '<div class="rlacct-metrics">';
            echo '<div><small>RAFFLE POINTS</small><strong>R ' . esc_html($points_display) . '</strong></div>';
            echo '<div><small>ORDERS</small><strong>' . esc_html($orders_count) . '</strong></div>';
        echo '</div>';
    echo '</section>';
}

    public static function legacy_callback_16412() {
    if (!is_user_logged_in() || !function_exists('is_account_page') || !is_account_page()) return;
    ?>
    <style id="rafflelb-account-premium-safe-v0311">
    /* Keep WoodMart's existing account layout untouched. */
    body.rafflelb-account-page .woocommerce-MyAccount-navigation{
        border:1px solid #293128!important;
        border-radius:16px!important;
        background:linear-gradient(180deg,#101510,#0b0f0b)!important;
        box-shadow:0 20px 50px rgba(0,0,0,.18)!important;
        overflow:hidden!important;
    }

    body.rafflelb-account-page .woocommerce-MyAccount-navigation ul{
        margin:0!important;
        padding:14px!important;
    }

    body.rafflelb-account-page .woocommerce-MyAccount-navigation li{
        margin:3px 0!important;
        border:0!important;
    }

    body.rafflelb-account-page .woocommerce-MyAccount-navigation li a{
        display:flex!important;
        align-items:center!important;
        min-height:44px!important;
        padding:0 14px!important;
        border-radius:10px!important;
        color:#c5cdc1!important;
        font-weight:800!important;
        transition:.18s ease!important;
    }

    body.rafflelb-account-page .woocommerce-MyAccount-navigation li a:hover{
        background:#151b14!important;
        color:#fff!important;
    }

    body.rafflelb-account-page .woocommerce-MyAccount-navigation li.is-active a{
        background:#baff00!important;
        color:#071006!important;
        box-shadow:0 8px 22px rgba(186,255,0,.12)!important;
    }

    body.rafflelb-account-page .woocommerce-MyAccount-content{
        min-width:0!important;
    }

    .rlacct-hero{
        display:flex;
        align-items:center;
        justify-content:space-between;
        gap:22px;
        width:100%;
        margin:0 0 18px;
        padding:22px 24px;
        box-sizing:border-box;
        border:1px solid #2a3428;
        border-top:3px solid #baff00;
        border-radius:16px;
        background:
            radial-gradient(circle at 94% 0,rgba(186,255,0,.09),transparent 34%),
            linear-gradient(145deg,#111711,#0b0f0b);
        box-shadow:0 22px 50px rgba(0,0,0,.18);
    }

    .rlacct-user{
        display:flex;
        align-items:center;
        gap:15px;
        min-width:0;
    }

    .rlacct-avatar{
        display:flex;
        flex:0 0 50px;
        width:50px;
        height:50px;
        align-items:center;
        justify-content:center;
        border-radius:50%;
        background:#baff00;
        color:#071006;
        font-size:20px;
        font-weight:900;
        box-shadow:0 0 0 6px rgba(186,255,0,.06);
    }

    .rlacct-copy{min-width:0}
    .rlacct-copy>span{
        display:block;
        margin-bottom:4px;
        color:#baff00;
        font-size:9px;
        font-weight:900;
        letter-spacing:.16em;
    }
    .rlacct-copy h2{
        margin:0 0 4px!important;
        color:#fff!important;
        font-size:23px!important;
        line-height:1.15!important;
        font-weight:900!important;
    }
    .rlacct-copy p{
        margin:0!important;
        color:#99a395!important;
        font-size:11px!important;
    }

    .rlacct-metrics{
        display:flex;
        gap:8px;
        flex:0 0 auto;
    }
    .rlacct-metrics>div{
        min-width:110px;
        padding:12px 14px;
        border:1px solid #2a3329;
        border-radius:11px;
        background:rgba(6,9,6,.45);
    }
    .rlacct-metrics small{
        display:block;
        margin-bottom:5px;
        color:#7d8879;
        font-size:8px;
        font-weight:900;
        letter-spacing:.11em;
    }
    .rlacct-metrics strong{
        color:#fff;
        font-size:16px;
        font-weight:900;
    }
    .rlacct-metrics>div:first-child strong{color:#baff00}

    /* Existing My Raffles content gets a premium skin only. */
    body.rafflelb-account-page .woocommerce-MyAccount-content h2,
    body.rafflelb-account-page .woocommerce-MyAccount-content h3{
        color:#fff!important;
    }

    body.rafflelb-account-page .rafflelb-entries-table{
        width:100%!important;
        border:1px solid #293128!important;
        border-radius:14px!important;
        overflow:hidden!important;
        background:#0e120e!important;
        box-shadow:0 20px 50px rgba(0,0,0,.14)!important;
    }
    body.rafflelb-account-page .rafflelb-entries-table thead th{
        padding:14px!important;
        background:#171d16!important;
        border-color:#2b3429!important;
        color:#baff00!important;
        font-size:9px!important;
    }
    body.rafflelb-account-page .rafflelb-entries-table tbody td{
        padding:14px!important;
        border-color:#222a21!important;
        color:#d5dcd2!important;
    }
    body.rafflelb-account-page .rafflelb-entries-table tbody td:last-child{
        color:#baff00!important;
        font-weight:900!important;
    }

    @media(max-width:900px){
        .rlacct-hero{
            align-items:flex-start;
            flex-direction:column;
        }
        .rlacct-metrics{
            width:100%;
        }
        .rlacct-metrics>div{
            flex:1 1 0;
            min-width:0;
        }
    }

    @media(max-width:600px){
        .rlacct-hero{
            padding:18px 16px;
        }
        .rlacct-avatar{
            flex-basis:42px;
            width:42px;
            height:42px;
            font-size:16px;
        }
        .rlacct-copy h2{font-size:19px!important}
        .rlacct-copy p{font-size:10px!important}
        .rlacct-metrics{
            display:grid;
            grid-template-columns:1fr 1fr;
        }
    }
    
    /* v0.31.2 — detailed premium raffle entries */
    .rafflelb-entry-summary{
        display:grid;
        grid-template-columns:repeat(3,minmax(0,1fr));
        gap:9px;
        margin:0 0 14px;
    }
    .rafflelb-entry-summary>div{
        padding:13px 15px;
        border:1px solid #293228;
        border-radius:11px;
        background:#0c100c;
    }
    .rafflelb-entry-summary span{
        display:block;
        margin-bottom:5px;
        color:#7e897b;
        font-size:8px;
        font-weight:900;
        letter-spacing:.12em;
    }
    .rafflelb-entry-summary strong{
        color:#fff;
        font-size:17px;
        font-weight:900;
    }
    .rafflelb-entry-summary strong.is-live{color:#baff00}
    .rafflelb-entry-summary strong.is-complete{color:#b8c0b4}

    .rafflelb-entry-cards{
        display:grid;
        gap:13px;
    }
    .rafflelb-entry-card{
        padding:18px;
        border:1px solid #293228;
        border-radius:15px;
        background:
            radial-gradient(circle at 95% 0,rgba(186,255,0,.035),transparent 28%),
            #0d110d;
        box-shadow:0 18px 40px rgba(0,0,0,.13);
    }
    .rafflelb-entry-card.is-winning{
        border-color:rgba(186,255,0,.65);
        box-shadow:0 0 0 1px rgba(186,255,0,.08),0 18px 40px rgba(0,0,0,.16);
    }
    .rafflelb-entry-main{
        display:flex;
        align-items:center;
        gap:15px;
    }
    .rafflelb-entry-thumb{
        display:flex;
        flex:0 0 76px;
        width:76px;
        height:76px;
        align-items:center;
        justify-content:center;
        overflow:hidden;
        border:1px solid #333d31;
        border-radius:11px;
        background:#fff;
    }
    .rafflelb-entry-thumb img{
        width:100%!important;
        height:100%!important;
        object-fit:contain!important;
    }
    .rafflelb-entry-prize{min-width:0}
    .rafflelb-entry-kicker{
        display:block;
        margin-bottom:5px;
        color:#baff00;
        font-size:8px;
        font-weight:900;
        letter-spacing:.14em;
    }
    .rafflelb-entry-prize h4{
        margin:0 0 9px!important;
        font-size:15px!important;
        line-height:1.25!important;
    }
    .rafflelb-entry-prize h4 a{color:#fff!important;text-decoration:none!important}
    .rafflelb-entry-badges{
        display:flex;
        flex-wrap:wrap;
        gap:6px;
    }
    .rafflelb-entry-badges span{
        display:inline-flex;
        min-height:25px;
        align-items:center;
        padding:0 9px;
        border:1px solid #323c30;
        border-radius:999px;
        font-size:8px;
        font-weight:900;
        letter-spacing:.08em;
    }
    .rafflelb-entry-number{
        background:rgba(186,255,0,.06);
        border-color:rgba(186,255,0,.30)!important;
        color:#baff00!important;
    }
    .rafflelb-entry-result{color:#c7cfc3!important}
    .rafflelb-entry-result.is-live{color:#baff00!important}
    .rafflelb-entry-result.is-winner{color:#baff00!important;border-color:#baff00!important}
    .rafflelb-entry-result.is-pending{color:#f2d577!important}
    .rafflelb-entry-result.is-complete{color:#b8c0b4!important}

    .rafflelb-entry-details{
        display:grid;
        grid-template-columns:repeat(4,minmax(0,1fr));
        gap:1px;
        margin-top:15px;
        overflow:hidden;
        border:1px solid #273026;
        border-radius:10px;
        background:#273026;
    }
    .rafflelb-entry-details>div{
        min-width:0;
        padding:11px 12px;
        background:#0a0e0a;
    }
    .rafflelb-entry-details span{
        display:block;
        margin-bottom:4px;
        color:#737f70;
        font-size:7px;
        font-weight:900;
        letter-spacing:.10em;
    }
    .rafflelb-entry-details strong,
    .rafflelb-entry-details strong a{
        color:#e9ede7!important;
        font-size:10px;
        line-height:1.35;
        text-decoration:none!important;
    }

    .rafflelb-entry-progress{
        margin-top:14px;
        padding:12px 13px;
        border:1px solid #283127;
        border-radius:10px;
        background:#0a0e0a;
    }
    .rafflelb-entry-progress-head{
        display:flex;
        justify-content:space-between;
        gap:12px;
        margin-bottom:8px;
    }
    .rafflelb-entry-progress-head span,
    .rafflelb-entry-progress-head strong{
        font-size:8px;
        font-weight:900;
        letter-spacing:.10em;
    }
    .rafflelb-entry-progress-head span{color:#7b8678}
    .rafflelb-entry-progress-head strong{color:#fff}
    .rafflelb-entry-progress-track{
        height:5px;
        overflow:hidden;
        border-radius:999px;
        background:#20271f;
    }
    .rafflelb-entry-progress-track i{
        display:block;
        height:100%;
        border-radius:inherit;
        background:#baff00;
    }

    .rafflelb-entry-actions{
        display:flex;
        gap:8px;
        margin-top:13px;
    }
    .rafflelb-entry-actions a{
        display:inline-flex;
        min-height:34px;
        align-items:center;
        justify-content:center;
        padding:0 13px;
        border:1px solid #baff00;
        border-radius:999px;
        background:#baff00;
        color:#071006!important;
        font-size:8px;
        font-weight:900;
        letter-spacing:.08em;
        text-decoration:none!important;
    }
    .rafflelb-entry-actions a.is-secondary{
        border-color:#303a2e;
        background:transparent;
        color:#d4dbd1!important;
    }

    @media(max-width:700px){
        .rafflelb-entry-summary{grid-template-columns:1fr 1fr}
        .rafflelb-entry-summary>div:last-child{grid-column:1/-1}
        .rafflelb-entry-details{grid-template-columns:1fr 1fr}
    }
    @media(max-width:480px){
        .rafflelb-entry-card{padding:14px}
        /* The ENTRY # / status badges row was wrapping onto two lines
           because it only had the width left over beside the thumbnail
           to work with. Give the badges the card's full width instead
           by stacking the thumbnail above the prize details on narrow
           screens, so both badges comfortably fit on one line. */
        .rafflelb-entry-main{
            flex-direction:column;
            align-items:flex-start;
            gap:10px;
        }
        .rafflelb-entry-thumb{
            flex-basis:auto;
            width:56px;
            height:56px;
        }
        .rafflelb-entry-prize{width:100%}
        .rafflelb-entry-badges{flex-wrap:nowrap}
        .rafflelb-entry-badges span{white-space:nowrap}
        .rafflelb-entry-details{grid-template-columns:1fr}
        .rafflelb-entry-actions{display:grid;grid-template-columns:1fr 1fr}
    }

    
    /* v0.31.3 — every lime-filled account element uses black text */
    body.rafflelb-account-page .woocommerce-MyAccount-navigation li.is-active a,
    body.rafflelb-account-page .woocommerce-MyAccount-navigation li.is-active a *,
    body.rafflelb-account-page .rlacct-avatar,
    body.rafflelb-account-page .rlacct-avatar *,
    body.rafflelb-account-page .rafflelb-entry-actions a:not(.is-secondary),
    body.rafflelb-account-page .rafflelb-entry-actions a:not(.is-secondary) *,
    body.rafflelb-account-page .button.alt,
    body.rafflelb-account-page .button.alt *,
    body.rafflelb-account-page .button.checkout,
    body.rafflelb-account-page .button.checkout *,
    body.rafflelb-account-page a.button:not(.is-secondary),
    body.rafflelb-account-page a.button:not(.is-secondary) *{
        color:#071006!important;
    }

    body.rafflelb-account-page .rlacct-avatar{
        background:#baff00!important;
    }

    body.rafflelb-account-page .rafflelb-entry-actions a:not(.is-secondary){
        background:#baff00!important;
        border-color:#baff00!important;
    }

    body.rafflelb-account-page .woocommerce-MyAccount-navigation li.is-active a{
        background:#baff00!important;
        border-color:#baff00!important;
    }

    
    /* v0.31.4 — force black text on all lime-filled account UI */
    body.rafflelb-account-page .rlacct-avatar,
    body.rafflelb-account-page .rlacct-avatar *,
    body.rafflelb-account-page .woocommerce-MyAccount-navigation li.is-active > a,
    body.rafflelb-account-page .woocommerce-MyAccount-navigation li.is-active > a *,
    body.rafflelb-account-page .rafflelb-entry-actions > a:not(.is-secondary),
    body.rafflelb-account-page .rafflelb-entry-actions > a:not(.is-secondary):link,
    body.rafflelb-account-page .rafflelb-entry-actions > a:not(.is-secondary):visited,
    body.rafflelb-account-page .rafflelb-entry-actions > a:not(.is-secondary):hover,
    body.rafflelb-account-page .rafflelb-entry-actions > a:not(.is-secondary):focus,
    body.rafflelb-account-page .rafflelb-entry-actions > a:not(.is-secondary):active,
    body.rafflelb-account-page .rafflelb-entry-actions > a:not(.is-secondary) *,
    body.rafflelb-account-page a.button.alt,
    body.rafflelb-account-page a.button.alt *,
    body.rafflelb-account-page button.button.alt,
    body.rafflelb-account-page button.button.alt *{
        color:#071006!important;
        -webkit-text-fill-color:#071006!important;
        text-shadow:none!important;
    }

    body.rafflelb-account-page .rafflelb-entry-actions > a:not(.is-secondary){
        background:#baff00!important;
        border-color:#baff00!important;
    }

    body.rafflelb-account-page .rlacct-avatar{
        background:#baff00!important;
    }

    body.rafflelb-account-page .woocommerce-MyAccount-navigation li.is-active > a{
        background:#baff00!important;
        border-color:#baff00!important;
    }

    
    /* v0.31.5 — improve readability of lime account controls */
    body.rafflelb-account-page .rafflelb-entry-actions > a:not(.is-secondary),
    body.rafflelb-account-page .rafflelb-entry-actions > a:not(.is-secondary):link,
    body.rafflelb-account-page .rafflelb-entry-actions > a:not(.is-secondary):visited,
    body.rafflelb-account-page .rafflelb-entry-actions > a:not(.is-secondary):hover,
    body.rafflelb-account-page .rafflelb-entry-actions > a:not(.is-secondary):focus,
    body.rafflelb-account-page .rafflelb-entry-actions > a:not(.is-secondary):active{
        min-height:42px!important;
        padding:0 18px!important;
        background:#baff00!important;
        border-color:#baff00!important;
        color:#050805!important;
        -webkit-text-fill-color:#050805!important;
        font-family:var(--rl-font, "Manrope", sans-serif)!important;
        font-size:13px!important;
        font-weight:800!important;
        line-height:1!important;
        letter-spacing:.03em!important;
        text-shadow:none!important;
        opacity:1!important;
    }

    body.rafflelb-account-page .woocommerce-MyAccount-navigation li.is-active > a{
        color:#050805!important;
        -webkit-text-fill-color:#050805!important;
        font-size:12px!important;
        font-weight:900!important;
        letter-spacing:0!important;
        text-shadow:none!important;
    }

    body.rafflelb-account-page .rlacct-avatar{
        color:#050805!important;
        -webkit-text-fill-color:#050805!important;
        font-size:19px!important;
        font-weight:900!important;
        text-shadow:none!important;
    }

    /* Slightly improve the outlined secondary button too */
    body.rafflelb-account-page .rafflelb-entry-actions > a.is-secondary{
        min-height:42px!important;
        padding:0 18px!important;
        color:#f2f5ef!important;
        -webkit-text-fill-color:#f2f5ef!important;
        font-family:var(--rl-font, "Manrope", sans-serif)!important;
        font-size:13px!important;
        font-weight:800!important;
        letter-spacing:.03em!important;
        opacity:1!important;
    }

    /* Keep lime text on dark surfaces readable */
    body.rafflelb-account-page .rafflelb-entry-number{
        color:#baff00!important;
        -webkit-text-fill-color:#baff00!important;
        font-size:9px!important;
        font-weight:900!important;
    }

    
    /* v0.31.6 — center and sharpen active My Account lime item */
    body.rafflelb-account-page .woocommerce-MyAccount-navigation li.is-active > a,
    body.rafflelb-account-page .woocommerce-MyAccount-navigation li.is-active > a:link,
    body.rafflelb-account-page .woocommerce-MyAccount-navigation li.is-active > a:visited,
    body.rafflelb-account-page .woocommerce-MyAccount-navigation li.is-active > a:hover,
    body.rafflelb-account-page .woocommerce-MyAccount-navigation li.is-active > a:focus,
    body.rafflelb-account-page .woocommerce-MyAccount-navigation li.is-active > a:active{
        display:flex!important;
        align-items:center!important;
        justify-content:flex-start!important;
        min-height:44px!important;
        height:44px!important;
        padding:0 16px!important;
        margin:0!important;
        box-sizing:border-box!important;
        background:#baff00!important;
        border:1px solid #baff00!important;
        border-radius:11px!important;
        color:#050805!important;
        -webkit-text-fill-color:#050805!important;
        font-size:12px!important;
        font-weight:900!important;
        line-height:1!important;
        letter-spacing:0!important;
        text-shadow:none!important;
        white-space:nowrap!important;
        opacity:1!important;
    }

    body.rafflelb-account-page .woocommerce-MyAccount-navigation li.is-active > a *,
    body.rafflelb-account-page .woocommerce-MyAccount-navigation li.is-active > a:before,
    body.rafflelb-account-page .woocommerce-MyAccount-navigation li.is-active > a:after{
        color:#050805!important;
        -webkit-text-fill-color:#050805!important;
        line-height:1!important;
        opacity:1!important;
    }

    
    /* v0.31.7 — final sidebar alignment refinement */
    body.rafflelb-account-page .woocommerce-MyAccount-navigation:before{
        display:block!important;
        width:100%!important;
        margin:4px 0 15px!important;
        padding:0 0 15px!important;
        box-sizing:border-box!important;
        text-align:center!important;
        color:#fff!important;
        font-size:11px!important;
        font-weight:900!important;
        letter-spacing:.12em!important;
        border-bottom:1px solid #262e25!important;
    }

    body.rafflelb-account-page .woocommerce-MyAccount-navigation li.is-active > a,
    body.rafflelb-account-page .woocommerce-MyAccount-navigation li.is-active > a:link,
    body.rafflelb-account-page .woocommerce-MyAccount-navigation li.is-active > a:visited,
    body.rafflelb-account-page .woocommerce-MyAccount-navigation li.is-active > a:hover,
    body.rafflelb-account-page .woocommerce-MyAccount-navigation li.is-active > a:focus,
    body.rafflelb-account-page .woocommerce-MyAccount-navigation li.is-active > a:active{
        display:flex!important;
        align-items:center!important;
        justify-content:center!important;
        text-align:center!important;
        min-height:44px!important;
        height:44px!important;
        padding:0 12px!important;
        margin:0!important;
        box-sizing:border-box!important;
        background:#baff00!important;
        border:1px solid #baff00!important;
        border-radius:11px!important;
        color:#050805!important;
        -webkit-text-fill-color:#050805!important;
        font-size:12px!important;
        font-weight:900!important;
        line-height:1!important;
        letter-spacing:0!important;
        text-shadow:none!important;
        white-space:nowrap!important;
        opacity:1!important;
    }

    </style>
    <?php
}

    public static function legacy_callback_17070() {
    if (!is_user_logged_in() || !function_exists('is_account_page') || !is_account_page()) return;
    ?>
    <style id="rafflelb-my-raffles-final-v03386">
    html body .rlmy,
    html body .rlmy *:not(svg):not(path){
        font-family:var(--rl-font, "Manrope", sans-serif)!important;
    }
    html body .rlmy .rlmy-media{
        position:relative!important;
        display:flex!important;
        flex-direction:column!important;
        align-items:center!important;
        justify-content:flex-start!important;
        height:auto!important;
        overflow:visible!important;
    }
    html body .rlmy .rlmy-media>a{
        display:flex!important;
        flex:0 0 auto!important;
        margin:0!important;
    }
    html body .rlmy .rlmy-media>.rlmy-status{
        position:relative!important;
        inset:auto!important;
        top:auto!important;
        right:auto!important;
        bottom:auto!important;
        left:auto!important;
        transform:none!important;
        z-index:2!important;
        display:flex!important;
        width:auto!important;
        min-width:76px!important;
        max-width:100%!important;
        min-height:28px!important;
        align-items:center!important;
        justify-content:center!important;
        margin:8px auto 0!important;
        padding:5px 13px!important;
        border:0!important;
        border-radius:999px!important;
        background:#baff00!important;
        color:#050705!important;
        -webkit-text-fill-color:#050705!important;
        font-family:var(--rl-font, "Manrope", sans-serif)!important;
        font-size:13px!important;
        line-height:1!important;
        font-weight:800!important;
        letter-spacing:0!important;
        text-align:center!important;
        text-shadow:none!important;
        box-shadow:none!important;
    }
    html body .rlmy .rlmy-card.is-complete .rlmy-media>.rlmy-status{
        background:#737b71!important;
        color:#fff!important;
        -webkit-text-fill-color:#fff!important;
    }
    html body .rlmy .rlmy-status-inline{display:none!important}
    html body .rlmy .rlmy-stats span{
        display:block!important;
        margin:0 0 6px!important;
        color:#c2c9bf!important;
        -webkit-text-fill-color:#c2c9bf!important;
        font-family:var(--rl-font, "Manrope", sans-serif)!important;
        font-size:14px!important;
        line-height:1.25!important;
        font-weight:600!important;
        letter-spacing:0!important;
        text-transform:none!important;
    }
    html body .rlmy .rlmy-stats strong,
    html body .rlmy .rlmy-stats strong *{
        display:inline!important;
        color:#fff!important;
        -webkit-text-fill-color:#fff!important;
        font-family:var(--rl-font, "Manrope", sans-serif)!important;
        font-size:16px!important;
        line-height:1.3!important;
        font-weight:750!important;
        letter-spacing:0!important;
    }
    html body .rlmy .rlmy-ticket-label,
    html body .rlmy .rlmy-side>span{
        font-size:13px!important;
        font-weight:600!important;
    }
    html body .rlmy .rlmy-heading p{font-size:14px!important}
    html body .rlmy .rlmy-tabs button{font-size:13px!important;font-weight:700!important}
    html body .rlmy .rlmy-content h3,
    html body .rlmy .rlmy-content h3 a{font-size:18px!important;line-height:1.3!important;font-weight:750!important}
    html body .rlmy .rlmy-tickets span{font-size:12px!important;font-weight:700!important}
    html body .rlmy .rlmy-progress>div:last-child{font-size:12px!important;font-weight:600!important}
    html body .rlmy .rlmy-side>strong{font-size:14px!important;font-weight:700!important}
    html body .rlmy .rlmy-side>a{font-size:12px!important;font-weight:750!important}
    @media(max-width:760px){
        html body .rlmy .rlmy-card{
            grid-template-columns:92px minmax(0,1fr)!important;
            column-gap:14px!important;
            row-gap:14px!important;
            padding:14px!important;
        }
        html body .rlmy .rlmy-media{
            grid-column:1!important;
            grid-row:1!important;
            align-self:start!important;
        }
        html body .rlmy .rlmy-media>a{width:92px!important;height:100px!important}
        html body .rlmy .rlmy-media>.rlmy-status{
            display:none!important;
        }
        html body .rlmy .rlmy-content{display:contents!important}
        html body .rlmy .rlmy-titleblock{
            grid-column:2!important;
            grid-row:1!important;
            align-self:start!important;
            min-width:0!important;
            padding:4px 0 0!important;
        }
        html body .rlmy .rlmy-content h3{
            margin:0!important;
        }
        html body .rlmy .rlmy-status-inline{
            position:static!important;
            inset:auto!important;
            transform:none!important;
            display:inline-flex!important;
            width:auto!important;
            min-width:72px!important;
            max-width:max-content!important;
            min-height:28px!important;
            align-items:center!important;
            justify-content:center!important;
            margin:10px 0 0!important;
            padding:5px 13px!important;
            border:0!important;
            border-radius:999px!important;
            background:#baff00!important;
            color:#050705!important;
            -webkit-text-fill-color:#050705!important;
            font-family:var(--rl-font, "Manrope", sans-serif)!important;
            font-size:12px!important;
            line-height:1!important;
            font-weight:800!important;
            letter-spacing:0!important;
            text-shadow:none!important;
            box-shadow:none!important;
        }
        html body .rlmy .rlmy-card.is-complete .rlmy-status-inline{
            background:#737b71!important;
            color:#fff!important;
            -webkit-text-fill-color:#fff!important;
        }
        html body .rlmy .rlmy-stats{
            grid-column:1/-1!important;
            grid-row:2!important;
            display:grid!important;
            grid-template-columns:repeat(3,minmax(0,1fr))!important;
            gap:0!important;
            margin:0!important;
            padding:13px 8px!important;
            border:1px solid rgba(255,255,255,.08)!important;
            border-radius:12px!important;
            background:linear-gradient(145deg,rgba(255,255,255,.025),rgba(255,255,255,.008))!important;
        }
        html body .rlmy .rlmy-stats span{font-size:12px!important}
        html body .rlmy .rlmy-stats strong,
        html body .rlmy .rlmy-stats strong *{font-size:14px!important}
        html body .rlmy .rlmy-stats>div{
            position:relative!important;
            box-sizing:border-box!important;
            min-width:0!important;
            padding:0 10px!important;
            text-align:center!important;
        }
        html body .rlmy .rlmy-stats>div>span,
        html body .rlmy .rlmy-stats>div>strong{white-space:nowrap!important}
        html body .rlmy .rlmy-stats>div:not(:last-child):after{
            content:""!important;
            position:absolute!important;
            top:5px!important;
            right:0!important;
            bottom:5px!important;
            width:1px!important;
            background:linear-gradient(180deg,transparent,rgba(186,255,0,.22),transparent)!important;
            pointer-events:none!important;
        }
        html body .rlmy .rlmy-ticket-label{
            grid-column:1/-1!important;
            grid-row:3!important;
            margin:0 0 -7px!important;
        }
        html body .rlmy .rlmy-tickets{grid-column:1/-1!important;grid-row:4!important}
        html body .rlmy .rlmy-progress,
        html body .rlmy .rlmy-winner{grid-column:1/-1!important;grid-row:5!important;margin-top:0!important}
        html body .rlmy .rlmy-fulfillment{grid-column:1/-1!important;grid-row:6!important;margin-top:0!important}
        html body .rlmy .rlmy-side{grid-column:1/-1!important;grid-row:7!important}
        html body .rlmy .rlmy-content h3,
        html body .rlmy .rlmy-content h3 a{font-size:15px!important}
        html body .rlmy .rlmy-ticket-label{font-size:11px!important}
        html body .rlmy .rlmy-tickets span,
        html body .rlmy .rlmy-progress>div:last-child{font-size:10px!important}
    }
    @media(max-width:380px){
        html body .rlmy .rlmy-card{grid-template-columns:84px minmax(0,1fr)!important;column-gap:12px!important;padding:12px!important}
        html body .rlmy .rlmy-media>a{width:84px!important;height:92px!important}
        html body .rlmy .rlmy-stats{padding:12px 5px!important}
        html body .rlmy .rlmy-stats>div{padding:0 6px!important}
        html body .rlmy .rlmy-stats span{font-size:11px!important}
    }
    </style>
    <?php
}

    public static function rafflelb_premium_account_product_image($product, $class = 'rlpa-product-image') {
        $attrs = [
            'loading' => 'lazy',
            'class'   => $class,
            'alt'     => $product ? $product->get_name() : __('Raffle product', 'rafflelb'),
        ];

        if ($product instanceof WC_Product) {
            return $product->get_image('woocommerce_thumbnail', $attrs);
        }

        return function_exists('wc_placeholder_img')
            ? wc_placeholder_img('woocommerce_thumbnail', $attrs)
            : '';
    }

    public static function rafflelb_premium_account_dashboard() {
        if (!is_user_logged_in()) return;

        global $wpdb;
        $user_id = get_current_user_id();
        $entries_table = $wpdb->prefix . RaffleLB_Draw_Engine::ENTRY_TABLE;

        $entry_groups = $wpdb->get_results($wpdb->prepare(
            "SELECT product_id,
                    COUNT(*) AS entry_count,
                    GROUP_CONCAT(entry_number ORDER BY entry_number ASC SEPARATOR ',') AS entry_numbers,
                    MAX(created_at) AS latest_entry
             FROM {$entries_table}
             WHERE user_id=%d AND status='active'
             GROUP BY product_id
             ORDER BY latest_entry DESC",
            $user_id
        ));

        // Cross-check against the permanent draw-results record, not just the
        // product's _rafflelb_draw_status meta - the two should always agree,
        // but a completed draw is never supposed to reappear as "live" here
        // even if that meta were ever out of sync (stale object cache, etc.).
        $drawn_product_ids = [];
        if ($entry_groups) {
            $product_ids = array_map('absint', wp_list_pluck($entry_groups, 'product_id'));
            $results_table = $wpdb->prefix . RaffleLB_Draw_Engine::RESULT_TABLE;
            $placeholders = implode(',', array_fill(0, count($product_ids), '%d'));
            $drawn_product_ids = array_map('absint', $wpdb->get_col($wpdb->prepare(
                "SELECT DISTINCT product_id FROM {$results_table} WHERE product_id IN ({$placeholders})",
                $product_ids
            )));
        }

        $live_groups = [];
        foreach ((array) $entry_groups as $group) {
            $pid = (int) $group->product_id;
            if (in_array($pid, $drawn_product_ids, true)) continue;

            $draw_status = (string) get_post_meta($pid, '_rafflelb_draw_status', true);
            if (!in_array($draw_status, ['ready_to_draw', 'winner_selected'], true)) {
                $live_groups[] = $group;
            }
        }

        $active_entry_count = 0;
        foreach ($live_groups as $group) {
            $active_entry_count += (int) $group->entry_count;
        }

        // v0.33.91: replaces the old "ACCOUNT STATUS: ACTIVE" tile, which
        // always read ACTIVE for any logged-in customer and told the member
        // nothing. Lifetime wins is a real, motivating number this account
        // actually has, and it's on-brand (SHOP - WIN - BE REWARDED).
        $wins_count = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}" . RaffleLB_Draw_Engine::RESULT_TABLE . " WHERE user_id=%d",
            $user_id
        ));

        echo '<section class="rlpa-dashboard" aria-label="RaffleLB account overview">';
        echo '<div class="rlpa-section-heading"><div><span>MEMBER OVERVIEW</span><h3>Your active raffle entries</h3><p>Live raffles you\'ve entered.</p></div>';
        echo '<a class="rlpa-text-link" href="' . esc_url(wc_get_account_endpoint_url('rafflelb-entries')) . '">VIEW ALL</a></div>';

        echo '<div class="rlpa-dashboard-stats">';
        echo '<div><span>YOUR ACTIVE ENTRIES</span><strong>' . esc_html($active_entry_count) . '</strong><small>Across ' . esc_html(count($live_groups)) . ' live raffle' . (count($live_groups) === 1 ? '' : 's') . '</small></div>';
        echo '<div><span>RAFFLES WON</span><strong' . ($wins_count > 0 ? ' class="is-lime"' : '') . '>' . esc_html($wins_count) . '</strong><small>All-time, across every raffle</small></div>';
        echo '</div>';

        if (!$live_groups) {
            echo '<div class="rlpa-empty"><span aria-hidden="true">＋</span><div><strong>No active raffle entries yet</strong><p>When you enter a live raffle, it will appear here with its product image and progress.</p></div></div>';
        } else {
            echo '<div class="rlpa-subscribed-list">';
            foreach (array_slice($live_groups, 0, 3) as $group) {
                $product_id = (int) $group->product_id;
                $product = function_exists('wc_get_product') ? wc_get_product($product_id) : false;
                $title = $product ? $product->get_name() : get_the_title($product_id);
                $url = get_permalink($product_id);
                $numbers = array_filter(array_map('absint', explode(',', (string) $group->entry_numbers)));
                $formatted_numbers = array_map(static function($number) {
                    return '#' . str_pad((string) $number, 3, '0', STR_PAD_LEFT);
                }, $numbers);

                $total = max(0, (int) get_post_meta($product_id, '_rafflelb_total_entries', true));
                $claimed = (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$entries_table} WHERE product_id=%d AND status='active'",
                    $product_id
                ));
                $percent = $total > 0 ? min(100, max(0, (int) round(($claimed / $total) * 100))) : 0;

                echo '<article class="rlpa-subscribed-card">';
                echo '<a class="rlpa-product-thumb" href="' . esc_url($url) . '">' . wp_kses_post(rafflelb_premium_account_product_image($product)) . '</a>';
                echo '<div class="rlpa-subscribed-copy"><span>YOUR LIVE RAFFLE</span><h4><a href="' . esc_url($url) . '">' . esc_html($title) . '</a></h4>';
                echo '<div class="rlpa-entry-number-line"><small>YOUR ' . ((int) $group->entry_count === 1 ? 'ENTRY' : 'ENTRIES') . '</small><strong>' . esc_html(implode(' · ', $formatted_numbers)) . '</strong></div>';
                if ($total > 0) {
                    echo '<div class="rlpa-progress-head"><span>' . esc_html($claimed) . ' of ' . esc_html($total) . ' entries sold</span><strong>' . esc_html($percent) . '%</strong></div>';
                    echo '<div class="rlpa-progress" role="progressbar" aria-label="' . esc_attr($title . ' raffle progress') . '" aria-valuemin="0" aria-valuemax="100" aria-valuenow="' . esc_attr($percent) . '"><i style="width:' . esc_attr($percent) . '%"></i></div>';
                }
                echo '</div><div class="rlpa-entry-count"><strong>' . esc_html((int) $group->entry_count) . '</strong><span>' . ((int) $group->entry_count === 1 ? 'ENTRY' : 'ENTRIES') . '</span></div>';
                echo '</article>';
            }
            echo '</div>';
        }

        $orders = function_exists('wc_get_orders') ? wc_get_orders([
            'customer_id' => $user_id,
            'limit'       => 3,
            'orderby'     => 'date',
            'order'       => 'DESC',
            'status'      => array_keys(wc_get_order_statuses()),
        ]) : [];

        echo '<div class="rlpa-recent-heading"><h3>Recent orders</h3><a class="rlpa-text-link" href="' . esc_url(wc_get_account_endpoint_url('orders')) . '">VIEW ORDERS</a></div>';
        if (!$orders) {
            echo '<div class="rlpa-empty is-compact"><div><strong>No orders yet</strong><p>Your recent RaffleLB orders will appear here.</p></div></div>';
        } else {
            echo '<div class="rlpa-recent-orders">';
            foreach ($orders as $order) {
                $items = $order->get_items('line_item');
                $first_item = $items ? reset($items) : false;
                $product = $first_item instanceof WC_Order_Item_Product ? $first_item->get_product() : false;
                $product_name = $first_item ? $first_item->get_name() : __('RaffleLB order', 'rafflelb');
                echo '<a class="rlpa-recent-order" href="' . esc_url($order->get_view_order_url()) . '">';
                echo '<span class="rlpa-order-thumb">' . wp_kses_post(rafflelb_premium_account_product_image($product, 'rlpa-order-product-image')) . '</span>';
                echo '<span class="rlpa-recent-order-copy"><small>ORDER #' . esc_html($order->get_order_number()) . '</small><strong>' . esc_html($product_name) . '</strong><em>' . esc_html(wc_format_datetime($order->get_date_created())) . '</em></span>';
                echo '<span class="rlpa-order-status status-' . esc_attr($order->get_status()) . '">' . esc_html(self::customer_order_status_label($order)) . '</span>';
                echo '<span class="rlpa-order-total">' . wp_kses_post($order->get_formatted_order_total()) . '</span>';
                echo '<span class="rlpa-order-arrow" aria-hidden="true">›</span></a>';
            }
            echo '</div>';
        }
        echo '</section>';
    }

    public static function rafflelb_account_orders_premium() {
        if (!is_user_logged_in()) return;

        $current_page = empty($_GET['page']) ? 1 : absint($_GET['page']);
        $customer_orders = wc_get_orders(apply_filters('woocommerce_my_account_my_orders_query', [
            'customer' => get_current_user_id(),
            'page'     => $current_page,
            'paginate' => true,
        ]));

        echo '<header class="rlpa-orders-heading"><h2>My Orders</h2><p>Track every purchase, payment, and raffle entry order in one place.</p></header>';

        if (empty($customer_orders->orders)) {
            echo '<div class="rlpa-empty"><div><strong>No orders yet</strong><p>Your RaffleLB purchases will appear here once you place an order.</p></div></div>';
            return;
        }

        echo '<div class="rlpa-orders-list">';
        foreach ($customer_orders->orders as $order) {
            if (!$order instanceof WC_Order) continue;

            $items = $order->get_items('line_item');
            $first_item = $items ? reset($items) : false;
            $product = $first_item instanceof WC_Order_Item_Product ? $first_item->get_product() : false;
            $item_count = count($items);
            $extra_count = max(0, $item_count - 1);
            $item_name = $first_item ? $first_item->get_name() : __('RaffleLB order', 'rafflelb');
            $cancellation = self::order_raffle_cancellation_summary($order);
            $raffle_only_cancelled = self::order_is_raffle_only($order) && $cancellation['raffle_items'] > 0 && $cancellation['cancelled_items'] === $cancellation['raffle_items'];

            echo '<article class="rlpa-order-card' . ($cancellation['cancelled_items'] > 0 ? ' has-raffle-cancellation' : '') . '">';
            echo '<a class="rlpa-order-thumb" href="' . esc_url($order->get_view_order_url()) . '">' . wp_kses_post(rafflelb_premium_account_product_image($product, 'rlpa-order-image')) . '</a>';
            echo '<div class="rlpa-order-body">';
            echo '<div class="rlpa-order-top"><span class="rlpa-order-number">ORDER #' . esc_html($order->get_order_number()) . '</span><span class="rlpa-order-status status-' . esc_attr($order->get_status()) . '">' . esc_html(self::customer_order_status_label($order)) . '</span></div>';
            echo '<h3 class="rlpa-order-title"><a href="' . esc_url($order->get_view_order_url()) . '">' . esc_html($item_name) . '</a></h3>';
            if ($extra_count > 0) {
                echo '<p class="rlpa-order-extra">+' . esc_html($extra_count) . ' more item' . ($extra_count === 1 ? '' : 's') . '</p>';
            }
            echo '<div class="rlpa-order-meta"><span>' . esc_html(wc_format_datetime($order->get_date_created())) . '</span><span aria-hidden="true">&middot;</span><span>' . esc_html($item_count) . ' ' . esc_html(_n('item', 'items', $item_count, 'rafflelb')) . '</span></div>';
            if ($cancellation['cancelled_items'] > 0) {
                if (!$cancellation['all_complete']) {
                    $cancel_text = $raffle_only_cancelled ? 'Raffle cancelled · Raffle Points refund processing' : $cancellation['cancelled_items'] . ' raffle item' . ($cancellation['cancelled_items'] === 1 ? '' : 's') . ' cancelled · refund processing';
                } elseif ($cancellation['points'] > 0) {
                    $cancel_text = $raffle_only_cancelled
                        ? 'Refunded in Raffle Points · ' . $cancellation['points'] . ' Points returned'
                        : $cancellation['cancelled_items'] . ' raffle item' . ($cancellation['cancelled_items'] === 1 ? '' : 's') . ' cancelled · ' . $cancellation['points'] . ' Raffle Points returned';
                } else {
                    $cancel_text = $raffle_only_cancelled ? 'Raffle cancelled' : $cancellation['cancelled_items'] . ' raffle item' . ($cancellation['cancelled_items'] === 1 ? '' : 's') . ' cancelled';
                }
                echo '<div class="rlpa-order-cancel-summary"><span aria-hidden="true">↩</span>' . esc_html($cancel_text) . '</div>';
            }
            echo '<div class="rlpa-order-footer"><span class="rlpa-order-total">' . wp_kses_post($order->get_formatted_order_total()) . '</span><span class="rlpa-order-actions">';
            foreach (wc_get_account_orders_actions($order) as $key => $action) {
                echo '<a href="' . esc_url($action['url']) . '" class="rlpa-order-action' . ($key === 'pay' ? ' is-pay' : '') . '">' . esc_html($action['name']) . '<b aria-hidden="true">&#8594;</b></a>';
            }
            echo '</span></div>';
            echo '</div></article>';
        }
        echo '</div>';

        if ($customer_orders->max_num_pages > 1) {
            echo '<div class="rlpa-orders-pagination">';
            if ($current_page > 1) {
                echo '<a class="rlpa-pagination-link" href="' . esc_url(wc_get_endpoint_url('orders', $current_page - 1)) . '">&larr; Previous</a>';
            }
            if ($current_page < $customer_orders->max_num_pages) {
                echo '<a class="rlpa-pagination-link" href="' . esc_url(wc_get_endpoint_url('orders', $current_page + 1)) . '">Next &rarr;</a>';
            }
            echo '</div>';
        }
    }

    /**
     * Give the Account renderer sole ownership of the View Order endpoint.
     *
     * WooCommerce registers woocommerce_account_view_order(), which renders
     * the native order-details.php template (and its customer-details
     * template).  The custom Account renderer already presents that data, so
     * allowing both endpoint callbacks produces duplicate order details.
     */
    public static function ensure_custom_view_order_endpoint() {
        remove_action('woocommerce_account_view-order_endpoint', 'woocommerce_account_view_order');
        remove_action('woocommerce_account_view-order_endpoint', 'rafflelb_account_view_order_premium');
        add_action('woocommerce_account_view-order_endpoint', [__CLASS__, 'rafflelb_account_view_order_premium'], 10, 1);
    }

    public static function rafflelb_account_view_order_premium($order_id) {
        if (!is_user_logged_in()) return;

        $order = wc_get_order(absint($order_id));
        if (!$order || !current_user_can('view_order', $order->get_id())) {
            echo '<p>' . esc_html__('Invalid order.', 'woocommerce') . ' <a href="' . esc_url(wc_get_page_permalink('myaccount')) . '">' . esc_html__('My account', 'woocommerce') . '</a></p>';
            return;
        }

        echo '<a class="rlpa-back-link" href="' . esc_url(wc_get_account_endpoint_url('orders')) . '">&larr; Back to Orders</a>';

        echo '<header class="rlpa-order-detail-heading">';
        echo '<div><span class="rlpa-order-number">ORDER #' . esc_html($order->get_order_number()) . '</span>';
        echo '<h2>Order details</h2>';
        echo '<p class="rlpa-order-detail-sub">Placed on ' . esc_html(wc_format_datetime($order->get_date_created())) . '</p></div>';
        echo '<span class="rlpa-order-status status-' . esc_attr($order->get_status()) . '">' . esc_html(self::customer_order_status_label($order)) . '</span>';
        echo '</header>';

        $cancellation = self::order_raffle_cancellation_summary($order);
        $raffle_only_cancelled = self::order_is_raffle_only($order) && $cancellation['raffle_items'] > 0 && $cancellation['cancelled_items'] === $cancellation['raffle_items'];
        if ($cancellation['cancelled_items'] > 0) {
            echo '<section class="rlpa-raffle-cancel-notice">';
            echo '<div class="rlpa-raffle-cancel-icon" aria-hidden="true">↩</div><div>';
            echo '<strong>' . esc_html($raffle_only_cancelled ? 'Raffle Cancelled' : 'Raffle item cancelled') . '</strong>';
            if ($raffle_only_cancelled) {
                echo '<p>This raffle was cancelled early.' . ($cancellation['all_complete'] && $cancellation['points'] > 0 ? ' Your eligible entry value was returned to your Raffle Points balance.' : (!$cancellation['all_complete'] ? ' Your Raffle Points refund is being processed.' : '')) . '</p>';
            } else {
                echo '<p>A raffle entry in this order was cancelled. Other purchased items in this order are unchanged.' . ($cancellation['all_complete'] && $cancellation['points'] > 0 ? ' The eligible raffle value was returned to your Raffle Points balance.' : (!$cancellation['all_complete'] ? ' The Raffle Points refund is being processed.' : '')) . '</p>';
            }
            if ($cancellation['public_note'] !== '') echo '<p class="rlpa-raffle-cancel-reason">' . esc_html($cancellation['public_note']) . '</p>';
            echo '<div class="rlpa-raffle-cancel-facts">';
            if ($cancellation['all_complete'] && $cancellation['points'] > 0) echo '<span><small>POINTS RETURNED</small><b>' . esc_html($cancellation['points']) . ' Raffle Points</b></span>';
            $refund_date = self::refund_date_label($cancellation['credited_at']);
            if ($refund_date !== '') echo '<span><small>REFUND DATE</small><b>' . esc_html($refund_date) . '</b></span>';
            echo '</div></div></section>';
        }

        echo '<div class="rlpa-order-items">';
        foreach ($order->get_items('line_item') as $item) {
            if (!$item instanceof WC_Order_Item_Product) continue;

            $product = $item->get_product();
            $url = $product ? get_permalink($product->get_id()) : '';
            $purchase_type = $item->get_meta('Purchase Type');

            echo '<div class="rlpa-order-item">';
            $thumb = wp_kses_post(rafflelb_premium_account_product_image($product, 'rlpa-order-image'));
            echo $url ? '<a class="rlpa-order-thumb" href="' . esc_url($url) . '">' . $thumb . '</a>' : '<span class="rlpa-order-thumb">' . $thumb . '</span>';
            echo '<div class="rlpa-order-item-body">';
            echo '<h3>' . ($url ? '<a href="' . esc_url($url) . '">' . esc_html($item->get_name()) . '</a>' : esc_html($item->get_name())) . '</h3>';
            $meta_bits = [];
            if ($purchase_type) $meta_bits[] = $purchase_type;
            $meta_bits[] = 'Qty ' . $item->get_quantity();
            echo '<p class="rlpa-order-item-meta">' . esc_html(implode(' · ', $meta_bits)) . '</p>';
            $cancel_info = self::cancelled_raffle_item_info($order, $item);
            if ($cancel_info) {
                if ($cancel_info['status'] !== 'complete') {
                    $line_status = 'RAFFLE CANCELLED · REFUND PROCESSING';
                } elseif ($cancel_info['points'] > 0) {
                    $line_status = 'RAFFLE CANCELLED · ' . $cancel_info['points'] . ' RAFFLE POINTS RETURNED';
                } else {
                    $line_status = 'RAFFLE CANCELLED';
                }
                echo '<div class="rlpa-order-item-cancelled">' . esc_html($line_status) . '</div>';
            }
            echo '</div>';
            echo '<div class="rlpa-order-item-total">' . wp_kses_post(apply_filters('woocommerce_order_item_subtotal_html', $order->get_formatted_line_subtotal($item), $item, $order)) . '</div>';
            echo '</div>';
        }
        echo '</div>';

        $totals = $order->get_order_item_totals();
        if ($totals) {
            echo '<div class="rlpa-order-totals">';
            foreach ($totals as $key => $total) {
                echo '<div class="rlpa-order-totals-row' . ($key === 'order_total' ? ' is-grand' : '') . '"><span>' . esc_html($total['label']) . '</span><strong>' . wp_kses_post($total['value']) . '</strong></div>';
            }
            echo '</div>';
        }

        echo '<div class="rlpa-order-address">';
        add_filter('gettext', 'rafflelb_rename_billing_details_label', 10, 3);
        wc_get_template('order/order-details-customer.php', ['order' => $order]);
        remove_filter('gettext', 'rafflelb_rename_billing_details_label', 10);
        echo '</div>';

        if ($order->has_downloadable_item()) {
            echo '<div class="rlpa-order-downloads">';
            wc_get_template('order/order-downloads.php', [
                'downloads'  => $order->get_downloadable_items(),
                'order'      => $order,
                'show_title' => true,
            ]);
            echo '</div>';
        }
    }

    public static function rafflelb_rename_billing_details_label($translated, $original, $domain) {
            if ($domain === 'woocommerce' && $original === 'Billing details') {
                return 'Entry Contact Details';
            }
            return $translated;
        }

    public static function legacy_callback_17961() {
    if (!is_user_logged_in() || !function_exists('is_account_page') || !is_account_page()) return;
    ?>
    <script id="rafflelb-account-stray-table-cleanup-v03389">
    (function(){
        // Native View Order output is suppressed at the endpoint. Do not hide
        // order-detail tables here: extensions may render legitimate content.
        var STRAY_SELECTORS = ['table.woocommerce-orders-table'];

        function isVisible(el) {
            return !!el && window.getComputedStyle(el).display !== 'none';
        }

        function collapseEmptyAncestors(el) {
            var node = el, hops = 0;
            while (node && node.parentElement && hops < 8) {
                var parent = node.parentElement;
                var hasOtherVisibleContent = false;
                for (var i = 0; i < parent.children.length; i++) {
                    var sibling = parent.children[i];
                    if (sibling === node) continue;
                    if (isVisible(sibling)) { hasOtherVisibleContent = true; break; }
                }
                if (hasOtherVisibleContent) {
                    parent.style.setProperty('padding', '0', 'important');
                    parent.style.setProperty('margin', '0', 'important');
                    parent.style.setProperty('min-height', '0', 'important');
                    break;
                }
                parent.style.setProperty('display', 'none', 'important');
                node = parent;
                hops++;
            }
        }

        function run() {
            STRAY_SELECTORS.forEach(function(selector) {
                document.querySelectorAll(selector).forEach(function(table) {
                    table.style.setProperty('display', 'none', 'important');
                    collapseEmptyAncestors(table);
                });
            });
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', run);
        } else {
            run();
        }
        window.addEventListener('load', run);
    })();
    </script>
    <?php
}

    public static function enqueue_my_raffles_styles() {
    if (!is_user_logged_in()
        || !function_exists('is_account_page')
        || !is_account_page()
        || !function_exists('is_wc_endpoint_url')
        || !is_wc_endpoint_url('rafflelb-entries')) return;

    wp_enqueue_style(
        'rafflelb-account-premium',
        plugins_url('assets/account-premium.css', __FILE__),
        [],
        self::VERSION,
        'all'
    );
}

    public static function legacy_callback_18014() {
    if (!is_user_logged_in() || !function_exists('is_account_page') || !is_account_page()) return;
    if (function_exists('wp_style_is') && wp_style_is('rafflelb-account-premium', 'done')) return;
    $url = plugins_url('assets/account-premium.css', __FILE__);
    echo '<link id="rafflelb-account-premium-v0111-fallback-css" rel="stylesheet" href="' . esc_url(add_query_arg('ver', self::VERSION, $url)) . '" media="all">';
}

    public static function legacy_callback_18021() {
    if (!is_user_logged_in() || !function_exists('is_account_page') || !is_account_page()) return;
    ?>
    <script id="rafflelb-account-menu-inline-v0324">
    (function(){
        var links=document.querySelectorAll('.woocommerce-MyAccount-navigation li > a');
        links.forEach(function(link){
            if(link.querySelector('.rlpa-menu-label')) return;
            var label=(link.textContent||'').trim();
            link.textContent='';
            var icon=document.createElement('span');
            icon.className='rlpa-menu-icon';
            icon.setAttribute('aria-hidden','true');
            var text=document.createElement('span');
            text.className='rlpa-menu-label';
            text.textContent=label;
            link.appendChild(icon);
            link.appendChild(text);
        });
    })();
    </script>
    <?php
}

    public static function legacy_callback_18045() {
    if (is_user_logged_in() || !function_exists('is_account_page') || !is_account_page()) return;
    $url = plugins_url('assets/account-login.css', __FILE__);
    echo '<link id="rafflelb-account-login-v016-css" rel="stylesheet" href="' . esc_url(add_query_arg('ver', self::VERSION, $url)) . '" media="all">';
}

    public static function legacy_callback_18052() {
    if (is_user_logged_in() || !function_exists('is_account_page') || !is_account_page()) return;
    $url = plugins_url('assets/account-login.js', __FILE__);
    echo '<script src="' . esc_url(add_query_arg('ver', '0.34.00', $url)) . '"></script>';
}

    public static function legacy_callback_18058($classes) {
    if (!is_user_logged_in() || !function_exists('is_account_page') || !is_account_page()) return $classes;

    $map = [
        'orders'           => 'rlpa-account-orders',
        'view-order'       => 'rlpa-account-order-detail',
        'rafflelb-entries' => 'rlpa-account-raffles',
        'refer-and-earn'   => 'rlpa-account-referrals',
        'edit-address'     => 'rlpa-account-addresses',
        'edit-account'     => 'rlpa-account-details',
    ];
    $matched = false;
    foreach ($map as $endpoint => $class) {
        if (function_exists('is_wc_endpoint_url') && is_wc_endpoint_url($endpoint)) {
            $classes[] = $class;
            $matched = true;
            break;
        }
    }
    if (!$matched) $classes[] = 'rlpa-account-dashboard';
    return $classes;
}

    public static function legacy_callback_18091() {
    if (!is_user_logged_in()) return;
    ?>
    <style id="rafflelb-header-account-dropdown-theme">
    .wd-header-my-account .wd-dropdown,
    .wd-header-my-account [class*="dropdown"]{
        background:#10130e!important;
        border:1px solid rgba(202,255,22,.22)!important;
        box-shadow:0 18px 40px rgba(0,0,0,.35)!important;
    }
    .wd-header-my-account .wd-dropdown *,
    .wd-header-my-account [class*="dropdown"] *{
        background-color:transparent!important;
        color:#d6dbd1!important;
        border-color:rgba(202,255,22,.12)!important;
    }
    .wd-header-my-account .wd-dropdown a:hover,
    .wd-header-my-account .wd-dropdown a:focus,
    .wd-header-my-account [class*="dropdown"] a:hover,
    .wd-header-my-account [class*="dropdown"] a:focus{
        background-color:rgba(202,255,22,.08)!important;
        color:#caff16!important;
    }
    </style>
    <?php
}
}

// Enforce the policy independently of Draw Engine's presentation bridge.
// WooCommerce consults these filters for both My Account and customer cancel requests.
add_action('wp_enqueue_scripts', ['RaffleLB_Account', 'enqueue_my_raffles_styles'], 100);
add_filter('woocommerce_my_account_my_orders_actions', ['RaffleLB_Account', 'remove_customer_cancel_action'], 50, 2);
add_filter('woocommerce_valid_order_statuses_for_cancel', ['RaffleLB_Account', 'customer_cancellable_statuses'], 50, 2);

// Run after all plugins have registered endpoint callbacks, but before the
// account template is rendered.
add_action('wp', ['RaffleLB_Account', 'ensure_custom_view_order_endpoint'], 1);
