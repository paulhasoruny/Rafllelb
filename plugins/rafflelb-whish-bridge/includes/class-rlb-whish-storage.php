<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class RLB_Whish_Storage {
    const DB_VERSION = '0.1.1';

    public static function table_name() {
        global $wpdb;
        return $wpdb->prefix . 'rlb_whish_events';
    }

    public static function maybe_install() {
        if ( self::DB_VERSION !== (string) get_option( 'rlb_whish_bridge_db_version', '' ) ) {
            self::install();
        }
    }

    public static function install() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $table = self::table_name();
        $charset = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            event_id varchar(80) NOT NULL,
            created_at datetime NOT NULL,
            received_at datetime NULL,
            bridge_id varchar(100) NOT NULL,
            source_package varchar(190) NULL,
            amount decimal(18,4) NULL,
            currency varchar(8) NULL,
            sender_phone varchar(64) NULL,
            fingerprint varchar(128) NULL,
            status varchar(40) NOT NULL,
            matched_order_id bigint(20) unsigned NULL,
            payload longtext NULL,
            note text NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY event_id (event_id),
            KEY status (status),
            KEY matched_order_id (matched_order_id),
            KEY amount_currency (amount,currency),
            KEY fingerprint (fingerprint),
            KEY received_at (received_at)
        ) {$charset};";
        dbDelta( $sql );
        update_option( 'rlb_whish_bridge_db_version', self::DB_VERSION, false );
    }

    public static function get_by_event_id( $event_id ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table_name() . ' WHERE event_id = %s LIMIT 1', $event_id ) );
    }

    public static function insert_event( array $event ) {
        global $wpdb;
        $ok = $wpdb->insert( self::table_name(), $event );
        return $ok ? (int) $wpdb->insert_id : 0;
    }

    public static function update_event( $id, array $data ) {
        global $wpdb;
        return $wpdb->update( self::table_name(), $data, array( 'id' => (int) $id ) );
    }

    public static function recent_fingerprint( $fingerprint, $received_at_gmt, $minutes = 15, $exclude_event_id = '' ) {
        global $wpdb;
        $fingerprint = sanitize_text_field( (string) $fingerprint );
        if ( ! $fingerprint ) { return null; }

        $center = strtotime( $received_at_gmt . ' UTC' );
        if ( ! $center ) { $center = time(); }
        $seconds = max( 60, (int) $minutes * MINUTE_IN_SECONDS );
        $from = gmdate( 'Y-m-d H:i:s', $center - $seconds );
        $to   = gmdate( 'Y-m-d H:i:s', $center + $seconds );

        if ( $exclude_event_id ) {
            return $wpdb->get_row( $wpdb->prepare(
                'SELECT * FROM ' . self::table_name() . ' WHERE fingerprint = %s AND event_id <> %s AND received_at BETWEEN %s AND %s ORDER BY id DESC LIMIT 1',
                $fingerprint,
                $exclude_event_id,
                $from,
                $to
            ) );
        }

        return $wpdb->get_row( $wpdb->prepare(
            'SELECT * FROM ' . self::table_name() . ' WHERE fingerprint = %s AND received_at BETWEEN %s AND %s ORDER BY id DESC LIMIT 1',
            $fingerprint,
            $from,
            $to
        ) );
    }

    public static function recent( $limit = 100 ) {
        global $wpdb;
        $limit = max( 1, min( 500, (int) $limit ) );
        return $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::table_name() . ' ORDER BY id DESC LIMIT %d', $limit ) );
    }
}
