<?php
if (!defined('ABSPATH')) { exit; }

final class RaffleLB_Auth_Phone_Registry {
    public static function table() { global $wpdb; return $wpdb->prefix . 'rafflelb_auth_phones'; }

    public static function init() {
        // Keep the phone registry in sync when a WordPress/WooCommerce user is removed.
        add_action('delete_user', array(__CLASS__, 'delete_for_user'), 10, 1);
        add_action('wpmu_delete_user', array(__CLASS__, 'delete_for_user'), 10, 1);
    }

    public static function install() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset = $wpdb->get_charset_collate();
        $table = self::table();
        dbDelta("CREATE TABLE {$table} (
            phone varchar(24) NOT NULL,
            user_id bigint(20) unsigned NOT NULL DEFAULT 0,
            reservation_token varchar(64) NOT NULL DEFAULT '',
            reservation_expires bigint(20) unsigned NOT NULL DEFAULT 0,
            created_at datetime NOT NULL,
            PRIMARY KEY  (phone),
            KEY user_id (user_id)
        ) {$charset};");
    }

    /**
     * Return the live owner of a phone number.
     *
     * Older plugin versions could leave a registry row behind when a customer
     * was deleted in WordPress. Treat that row as orphaned, remove it, and make
     * the phone immediately reusable for a new registration.
     */
    public static function user_id($phone) {
        global $wpdb;
        $table = self::table();
        $user_id = (int) $wpdb->get_var($wpdb->prepare("SELECT user_id FROM {$table} WHERE phone = %s AND user_id > 0", $phone));
        if (!$user_id) { return 0; }

        if (!get_userdata($user_id)) {
            $wpdb->delete($table, array('phone' => $phone, 'user_id' => $user_id), array('%s', '%d'));
            return 0;
        }

        return $user_id;
    }

    public static function reserve($phone, $token) {
        global $wpdb; $table = self::table(); $now = time();

        // Calling user_id() also self-heals an orphaned row left by a deleted user.
        self::user_id($phone);

        $wpdb->query($wpdb->prepare("DELETE FROM {$table} WHERE phone=%s AND user_id=0 AND reservation_expires < %d", $phone, $now));
        $ok = $wpdb->query($wpdb->prepare("INSERT IGNORE INTO {$table} (phone,user_id,reservation_token,reservation_expires,created_at) VALUES (%s,0,%s,%d,%s)", $phone, $token, $now + 120, current_time('mysql', true)));
        if ($ok === 1) { return true; }
        $owner = $wpdb->get_row($wpdb->prepare("SELECT user_id,reservation_token FROM {$table} WHERE phone=%s", $phone));
        return $owner && (int) $owner->user_id === 0 && hash_equals((string) $owner->reservation_token, (string) $token);
    }

    public static function commit($phone, $token, $user_id) {
        global $wpdb;
        return 1 === $wpdb->update(self::table(), array('user_id' => absint($user_id), 'reservation_token' => '', 'reservation_expires' => 0), array('phone' => $phone, 'user_id' => 0, 'reservation_token' => $token), array('%d','%s','%d'), array('%s','%d','%s'));
    }

    public static function release($phone, $token) {
        global $wpdb;
        $wpdb->delete(self::table(), array('phone' => $phone, 'user_id' => 0, 'reservation_token' => $token), array('%s','%d','%s'));
    }

    public static function delete_for_user($user_id) {
        global $wpdb;
        $user_id = absint($user_id);
        if (!$user_id) { return; }
        $wpdb->delete(self::table(), array('user_id' => $user_id), array('%d'));
    }
}
