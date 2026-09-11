<?php
if (!defined('ABSPATH')) { exit; }

final class RaffleLB_Auth_Phone_Registry {
    public static function table() { global $wpdb; return $wpdb->prefix . 'rafflelb_auth_phones'; }
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
    public static function user_id($phone) {
        global $wpdb;
        return (int) $wpdb->get_var($wpdb->prepare('SELECT user_id FROM ' . self::table() . ' WHERE phone = %s AND user_id > 0', $phone));
    }
    public static function reserve($phone, $token) {
        global $wpdb; $table = self::table(); $now = time();
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
}

