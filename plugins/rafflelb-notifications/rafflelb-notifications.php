<?php
/**
 * Plugin Name: RaffleLB Notifications
 * Description: A notification badge on the account icon, a "Notifications" tab in My Account, and an admin screen to create notifications and control the automatic winner / draw-completed ones. Listens to RaffleLB Draw Engine's rafflelb_draw_completed hook instead of duplicating any draw logic.
 * Version: 1.0.1
 * Author: RaffleLB
 */

if (!defined('ABSPATH')) exit;

final class RaffleLB_Notifications {
    const VERSION = '1.0.1';
    const TABLE = 'rafflelb_notifications';
    const ENDPOINT = 'rafflelb-notifications';

    const OPT_NOTIFY_WINNER        = 'rafflelb_notify_on_winner';
    const OPT_NOTIFY_DRAW_COMPLETE = 'rafflelb_notify_on_draw_complete';

    public static function init() {
        register_activation_hook(__FILE__, [__CLASS__, 'activate']);
        add_action('plugins_loaded', [__CLASS__, 'boot']);
    }

    public static function activate() {
        self::ensure_schema();
        add_rewrite_endpoint(self::ENDPOINT, EP_ROOT | EP_PAGES);
        flush_rewrite_rules();
    }

    private static function ensure_schema() {
        $installed = get_option('rafflelb_notifications_db_version', '');
        if ($installed === self::VERSION) return;

        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $table = $wpdb->prefix . self::TABLE;
        $cc = $wpdb->get_charset_collate();

        dbDelta("CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL,
            type VARCHAR(32) NOT NULL DEFAULT 'announcement',
            title VARCHAR(255) NOT NULL,
            message TEXT NOT NULL,
            link_url VARCHAR(500) NULL,
            product_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            is_read TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            read_at DATETIME NULL,
            PRIMARY KEY (id),
            KEY user_unread (user_id, is_read),
            KEY product_type_user (product_id, type, user_id),
            KEY created_at (created_at)
        ) {$cc};");

        update_option('rafflelb_notifications_db_version', self::VERSION);
    }

    public static function boot() {
        // WordPress does not reliably re-run activation hooks when an active
        // plugin is replaced by an uploaded ZIP, so ensure the schema on
        // every load too (same lesson already applied in Draw Engine).
        self::ensure_schema();

        add_action('init', [__CLASS__, 'register_endpoint']);
        add_filter('query_vars', [__CLASS__, 'query_vars']);
        add_action('admin_menu', [__CLASS__, 'admin_menu']);
        add_action('admin_post_rafflelb_notify_save_settings', [__CLASS__, 'handle_save_settings']);
        add_action('admin_post_rafflelb_notify_send', [__CLASS__, 'handle_send_notification']);
        add_action('admin_post_rafflelb_notify_delete_all', [__CLASS__, 'handle_delete_all_notifications']);
        add_action('rafflelb_draw_completed', [__CLASS__, 'on_draw_completed'], 10, 5);

        if (!class_exists('WooCommerce')) return;

        add_action('woocommerce_account_' . self::ENDPOINT . '_endpoint', [__CLASS__, 'render_account_tab']);
        add_action('wp_footer', [__CLASS__, 'print_badge']);
    }

    public static function register_endpoint() {
        add_rewrite_endpoint(self::ENDPOINT, EP_ROOT | EP_PAGES);
    }

    public static function query_vars($vars) {
        $vars[] = self::ENDPOINT;
        return $vars;
    }

    /* ---------------------------------------------------------------
     * Automatic notifications, triggered by RaffleLB Draw Engine
     * ------------------------------------------------------------- */

    public static function on_draw_completed($product_id, $winner_user_id, $winner_entry_id, $result_id, $method) {
        global $wpdb;
        $product_id = absint($product_id);
        $winner_user_id = absint($winner_user_id);
        if (!$product_id) return;

        $title = get_the_title($product_id);
        $link = function_exists('wc_get_account_endpoint_url')
            ? wc_get_account_endpoint_url('rafflelb-entries')
            : home_url('/my-account/rafflelb-entries/');

        if ($winner_user_id && self::option_enabled(self::OPT_NOTIFY_WINNER)
            && !self::already_notified($product_id, 'winner', $winner_user_id)) {
            self::insert_notification(
                $winner_user_id,
                'winner',
                'You won a raffle!',
                sprintf('Your entry was selected as the winner for "%s". Congratulations!', $title),
                $link,
                $product_id
            );
        }

        if (self::option_enabled(self::OPT_NOTIFY_DRAW_COMPLETE)) {
            $entries_table = $wpdb->prefix . 'rafflelb_entries';
            $user_ids = $wpdb->get_col($wpdb->prepare(
                "SELECT DISTINCT user_id FROM {$entries_table} WHERE product_id=%d AND status='active' AND user_id>0",
                $product_id
            ));
            foreach ($user_ids as $uid) {
                $uid = absint($uid);
                if (!$uid || $uid === $winner_user_id) continue;
                if (self::already_notified($product_id, 'draw_completed', $uid)) continue;
                self::insert_notification(
                    $uid,
                    'draw_completed',
                    'Draw completed',
                    sprintf('The draw for "%s" has been completed. See the results in your account.', $title),
                    $link,
                    $product_id
                );
            }
        }
    }

    private static function option_enabled($key) {
        return get_option($key, 'yes') === 'yes';
    }

    private static function already_notified($product_id, $type, $user_id) {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE;
        return (bool) $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table} WHERE product_id=%d AND type=%s AND user_id=%d LIMIT 1",
            $product_id, $type, $user_id
        ));
    }

    private static function insert_notification($user_id, $type, $title, $message, $link_url = '', $product_id = 0) {
        global $wpdb;
        return $wpdb->insert(
            $wpdb->prefix . self::TABLE,
            [
                'user_id'    => absint($user_id),
                'type'       => sanitize_key($type),
                'title'      => $title,
                'message'    => $message,
                'link_url'   => $link_url,
                'product_id' => absint($product_id),
                'is_read'    => 0,
                'created_at' => current_time('mysql'),
            ],
            ['%d', '%s', '%s', '%s', '%s', '%d', '%d', '%s']
        );
    }

    private static function unread_count($user_id) {
        global $wpdb;
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}" . self::TABLE . " WHERE user_id=%d AND is_read=0",
            $user_id
        ));
    }

    private static function type_label($type) {
        $labels = ['winner' => 'Winner', 'draw_completed' => 'Draw Completed', 'announcement' => 'Announcement'];
        return $labels[$type] ?? ucfirst($type);
    }

    private static function type_icon($type) {
        $icons = ['winner' => '🏆', 'draw_completed' => '🎲', 'announcement' => '📢'];
        return $icons[$type] ?? '🔔';
    }

    /* ---------------------------------------------------------------
     * My Account tab
     * ------------------------------------------------------------- */

    public static function render_account_tab() {
        if (!is_user_logged_in()) {
            echo '<p>Please log in to view your notifications.</p>';
            return;
        }

        global $wpdb;
        $table = $wpdb->prefix . self::TABLE;
        $uid = get_current_user_id();

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} WHERE user_id=%d ORDER BY created_at DESC, id DESC LIMIT 100",
            $uid
        ));

        echo '<h3>Notifications</h3>';

        if (!$rows) {
            echo '<p>You do not have any notifications yet.</p>';
            return;
        }

        echo '<div class="rafflelb-notify-list">';
        foreach ($rows as $row) {
            $unread = empty($row->is_read);
            echo '<div class="rafflelb-notify-item' . ($unread ? ' is-unread' : '') . '">';
            echo '<div class="rafflelb-notify-item-icon">' . self::type_icon($row->type) . '</div>';
            echo '<div class="rafflelb-notify-item-body">';
            echo '<div class="rafflelb-notify-item-title">' . esc_html($row->title) . ($unread ? ' <span class="rafflelb-notify-dot"></span>' : '') . '</div>';
            echo '<p class="rafflelb-notify-item-message">' . esc_html($row->message) . '</p>';
            echo '<small class="rafflelb-notify-item-date">' . esc_html($row->created_at) . '</small>';
            if (!empty($row->link_url)) {
                echo ' <a class="rafflelb-notify-item-link" href="' . esc_url($row->link_url) . '">View &rarr;</a>';
            }
            echo '</div></div>';
        }
        echo '</div>';

        self::print_account_tab_styles();

        // Everything just rendered above is now considered seen. The unread
        // styling/dot for this pageview was already computed from the rows
        // fetched before this update, so the visit itself still reads clearly.
        $wpdb->query($wpdb->prepare(
            "UPDATE {$table} SET is_read=1, read_at=%s WHERE user_id=%d AND is_read=0",
            current_time('mysql'), $uid
        ));
    }

    private static function print_account_tab_styles() {
        echo '<style>
        .rafflelb-notify-list{display:flex;flex-direction:column;gap:14px;margin:0 0 24px}
        .rafflelb-notify-item{display:flex;gap:14px;padding:16px 18px;border:1px solid rgba(202,255,22,.18);border-radius:12px;background:#11150e}
        .rafflelb-notify-item.is-unread{border-color:rgba(202,255,22,.6);background:#141a10}
        .rafflelb-notify-item-icon{flex:0 0 auto;font-size:24px;line-height:1}
        .rafflelb-notify-item-body{min-width:0}
        .rafflelb-notify-item-title{color:#fff;font-weight:800;font-size:14px;margin-bottom:4px}
        .rafflelb-notify-dot{display:inline-block;width:7px;height:7px;border-radius:50%;background:#caff16;margin-left:4px;vertical-align:middle}
        .rafflelb-notify-item-message{margin:0 0 6px;color:#d6dbd1;font-size:13px;line-height:1.5}
        .rafflelb-notify-item-date{color:#8f9887;font-size:11px}
        .rafflelb-notify-item-link{margin-left:8px;color:#caff16;font-weight:700;font-size:12px;text-decoration:none}
        </style>';
    }

    /* ---------------------------------------------------------------
     * Account-icon badge (site-wide)
     * ------------------------------------------------------------- */

    public static function print_badge() {
        if (!is_user_logged_in() || is_admin()) return;

        $count = self::unread_count(get_current_user_id());
        if ($count < 1) return;

        $label = $count > 9 ? '9+' : (string) $count;
        ?>
        <style>
        .wd-header-my-account,.wd-tools-element.wd-header-my-account{position:relative!important}
        .rafflelb-notify-badge{position:absolute;top:-4px;right:-4px;min-width:17px;height:17px;padding:0 4px;display:flex;align-items:center;justify-content:center;border-radius:999px;background:#caff16;color:#0b0d08;font-size:10px;font-weight:800;line-height:1;font-family:Inter,Arial,sans-serif;box-shadow:0 0 0 2px #10130e;pointer-events:none;z-index:5}
        </style>
        <script>
        (function(){
            function placeRaffleNotifyBadge(){
                if(document.querySelector('.rafflelb-notify-badge')) return;
                var account = document.querySelector('.wd-header-my-account') || document.querySelector('.wd-tools-element.wd-header-my-account');
                if(!account) return;
                var badge = document.createElement('span');
                badge.className = 'rafflelb-notify-badge';
                badge.textContent = <?php echo wp_json_encode($label); ?>;
                account.appendChild(badge);
            }
            if(document.readyState === 'loading'){
                document.addEventListener('DOMContentLoaded', placeRaffleNotifyBadge);
            } else {
                placeRaffleNotifyBadge();
            }
            setTimeout(placeRaffleNotifyBadge, 400);
            setTimeout(placeRaffleNotifyBadge, 1200);
        })();
        </script>
        <?php
    }

    /* ---------------------------------------------------------------
     * Admin: settings + send notification + recent log
     * ------------------------------------------------------------- */

    public static function admin_menu() {
        add_submenu_page('woocommerce', 'RaffleLB Notifications', 'Notifications', 'manage_woocommerce', 'rafflelb-notifications-admin', [__CLASS__, 'render_admin_page']);
    }

    public static function handle_save_settings() {
        if (!current_user_can('manage_woocommerce')) {
            wp_die('You are not allowed to manage notification settings.');
        }
        check_admin_referer('rafflelb_notify_save_settings');

        update_option(self::OPT_NOTIFY_WINNER, !empty($_POST['notify_winner']) ? 'yes' : 'no');
        update_option(self::OPT_NOTIFY_DRAW_COMPLETE, !empty($_POST['notify_draw_complete']) ? 'yes' : 'no');

        wp_safe_redirect(add_query_arg('rlbn', 'settings_saved', admin_url('admin.php?page=rafflelb-notifications-admin')));
        exit;
    }

    public static function handle_send_notification() {
        if (!current_user_can('manage_woocommerce')) {
            wp_die('You are not allowed to send notifications.');
        }
        check_admin_referer('rafflelb_notify_send');

        $redirect = admin_url('admin.php?page=rafflelb-notifications-admin');

        $title = sanitize_text_field(wp_unslash($_POST['notify_title'] ?? ''));
        $message = sanitize_textarea_field(wp_unslash($_POST['notify_message'] ?? ''));
        $link = isset($_POST['notify_link']) ? esc_url_raw(wp_unslash($_POST['notify_link'])) : '';
        $target = isset($_POST['notify_target']) ? sanitize_key(wp_unslash($_POST['notify_target'])) : 'all';
        $target_user_input = isset($_POST['notify_target_user']) ? sanitize_text_field(wp_unslash($_POST['notify_target_user'])) : '';

        if ($title === '' || $message === '') {
            wp_safe_redirect(add_query_arg('rlbn', 'send_missing_fields', $redirect));
            exit;
        }

        if ($target === 'specific') {
            $user = false;
            if (is_email($target_user_input)) {
                $user = get_user_by('email', $target_user_input);
            } elseif (ctype_digit($target_user_input)) {
                $user = get_user_by('id', (int) $target_user_input);
            } else {
                $user = get_user_by('login', $target_user_input);
            }
            if (!$user) {
                wp_safe_redirect(add_query_arg('rlbn', 'send_user_not_found', $redirect));
                exit;
            }
            $user_ids = [$user->ID];
        } else {
            $user_ids = get_users(['fields' => 'ID']);
        }

        $sent = 0;
        foreach ($user_ids as $uid) {
            if (self::insert_notification(absint($uid), 'announcement', $title, $message, $link, 0)) {
                $sent++;
            }
        }

        wp_safe_redirect(add_query_arg(['rlbn' => 'sent', 'rlbn_count' => $sent], $redirect));
        exit;
    }

    public static function handle_delete_all_notifications() {
        if (!current_user_can('manage_woocommerce')) {
            wp_die('You are not allowed to delete notifications.');
        }
        check_admin_referer('rafflelb_notify_delete_all');

        global $wpdb;
        $table = $wpdb->prefix . self::TABLE;

        // Delete every stored notification for every user. Because the account
        // list and unread badge are both read directly from this table, the
        // deletion takes effect for all users immediately on their next page load.
        $deleted = $wpdb->query("DELETE FROM {$table}");
        if ($deleted === false) {
            $deleted = 0;
            $result = 'delete_failed';
        } else {
            $result = 'deleted_all';
        }

        wp_safe_redirect(add_query_arg([
            'rlbn' => $result,
            'rlbn_count' => absint($deleted),
        ], admin_url('admin.php?page=rafflelb-notifications-admin')));
        exit;
    }

    public static function render_admin_page() {
        if (!current_user_can('manage_woocommerce')) return;

        $notify_winner = self::option_enabled(self::OPT_NOTIFY_WINNER);
        $notify_draw_complete = self::option_enabled(self::OPT_NOTIFY_DRAW_COMPLETE);

        echo '<div class="wrap"><h1>RaffleLB Notifications</h1>';
        echo '<style>
        .rlbn-card{background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:18px 20px;margin:16px 0;max-width:820px}
        .rlbn-danger{border-color:#d63638;background:#fffafa}
        .rlbn-danger h2{color:#b32d2e}
        .rlbn-danger-note{margin:8px 0 16px;color:#50575e}
        .rlbn-delete-all{background:#d63638!important;border-color:#d63638!important;color:#fff!important}
        .rlbn-delete-all:hover,.rlbn-delete-all:focus{background:#b32d2e!important;border-color:#b32d2e!important;color:#fff!important}
        </style>';

        self::render_admin_notices();

        echo '<div class="rlbn-card"><h2>Automatic Notifications</h2>';
        echo '<p class="description">Fires whenever a draw is completed (Secure Random Draw or Record Chosen Winner), on the RaffleLB Draw Engine\'s existing draw-completed event.</p>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" name="action" value="rafflelb_notify_save_settings">';
        wp_nonce_field('rafflelb_notify_save_settings');
        echo '<p><label><input type="checkbox" name="notify_winner" value="1" ' . checked($notify_winner, true, false) . '> Notify the winner when a draw is completed</label></p>';
        echo '<p><label><input type="checkbox" name="notify_draw_complete" value="1" ' . checked($notify_draw_complete, true, false) . '> Notify every other entrant that the draw is complete</label></p>';
        submit_button('Save Settings');
        echo '</form></div>';

        echo '<div class="rlbn-card"><h2>Send a Notification</h2>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" name="action" value="rafflelb_notify_send">';
        wp_nonce_field('rafflelb_notify_send');
        echo '<table class="form-table"><tbody>';
        echo '<tr><th><label for="notify_title">Title</label></th><td><input type="text" id="notify_title" name="notify_title" class="large-text" required></td></tr>';
        echo '<tr><th><label for="notify_message">Message</label></th><td><textarea id="notify_message" name="notify_message" rows="4" class="large-text" required></textarea></td></tr>';
        echo '<tr><th><label for="notify_link">Link (optional)</label></th><td><input type="url" id="notify_link" name="notify_link" class="large-text" placeholder="https://..."></td></tr>';
        echo '<tr><th>Send to</th><td>';
        echo '<p><label><input type="radio" name="notify_target" value="all" checked> All registered users</label></p>';
        echo '<p><label><input type="radio" name="notify_target" value="specific"> A specific user</label> ';
        echo '<input type="text" name="notify_target_user" placeholder="Email, username, or user ID" class="regular-text"></p>';
        echo '</td></tr>';
        echo '</tbody></table>';
        submit_button('Send Notification');
        echo '</form></div>';

        echo '<div class="rlbn-card rlbn-danger"><h2>Delete All Notifications</h2>';
        echo '<p class="rlbn-danger-note"><strong>This removes every notification for every user.</strong> It clears both read and unread notifications, so notification badges will also disappear for all users on their next page load.</p>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" onsubmit="return confirm(\'Delete ALL notifications for ALL users? This cannot be undone.\');">';
        echo '<input type="hidden" name="action" value="rafflelb_notify_delete_all">';
        wp_nonce_field('rafflelb_notify_delete_all');
        submit_button('Delete All Notifications', 'delete rlbn-delete-all', 'submit', false);
        echo '</form></div>';

        global $wpdb;
        $rows = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}" . self::TABLE . " ORDER BY id DESC LIMIT 50");

        echo '<div class="rlbn-card"><h2>Recent Notifications</h2>';
        if (!$rows) {
            echo '<p>No notifications sent yet.</p>';
        } else {
            echo '<table class="widefat striped"><thead><tr><th>Recipient</th><th>Type</th><th>Title</th><th>Sent</th><th>Read</th></tr></thead><tbody>';
            foreach ($rows as $row) {
                $user = get_user_by('id', $row->user_id);
                echo '<tr>';
                echo '<td>' . ($user ? esc_html($user->user_email) : '#' . esc_html($row->user_id)) . '</td>';
                echo '<td>' . esc_html(self::type_label($row->type)) . '</td>';
                echo '<td>' . esc_html($row->title) . '</td>';
                echo '<td>' . esc_html($row->created_at) . '</td>';
                echo '<td>' . (!empty($row->is_read) ? 'Yes' : 'No') . '</td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
        }
        echo '</div>';

        echo '</div>';
    }

    private static function render_admin_notices() {
        $notice = isset($_GET['rlbn']) ? sanitize_key(wp_unslash($_GET['rlbn'])) : '';
        if ($notice === 'sent') {
            $count = isset($_GET['rlbn_count']) ? absint($_GET['rlbn_count']) : 0;
            echo '<div class="notice notice-success is-dismissible"><p>Notification sent to ' . esc_html($count) . ' user' . ($count === 1 ? '' : 's') . '.</p></div>';
            return;
        }

        if ($notice === 'deleted_all') {
            $count = isset($_GET['rlbn_count']) ? absint($_GET['rlbn_count']) : 0;
            echo '<div class="notice notice-success is-dismissible"><p>All notifications were deleted. Removed ' . esc_html($count) . ' notification' . ($count === 1 ? '' : 's') . ' across all users.</p></div>';
            return;
        }

        $messages = [
            'settings_saved'      => ['success', 'Settings saved.'],
            'send_missing_fields' => ['error', 'Title and message are required.'],
            'send_user_not_found' => ['error', 'Could not find a user matching that email, username, or ID.'],
            'delete_failed'       => ['error', 'Could not delete notifications. Please try again.'],
        ];
        if ($notice && isset($messages[$notice])) {
            [$type, $text] = $messages[$notice];
            echo '<div class="notice notice-' . esc_attr($type) . ' is-dismissible"><p>' . esc_html($text) . '</p></div>';
        }
    }
}

RaffleLB_Notifications::init();
