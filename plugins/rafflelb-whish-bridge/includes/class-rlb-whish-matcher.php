<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class RLB_Whish_Matcher {
    public static function normalize_phone( $phone ) {
        $digits = preg_replace( '/\D+/', '', (string) $phone );
        if ( str_starts_with( $digits, '961' ) ) { $digits = substr( $digits, 3 ); }
        if ( str_starts_with( $digits, '0' ) ) { $digits = substr( $digits, 1 ); }
        return $digits;
    }

    private static function event_timestamp( array $event ) {
        if ( ! empty( $event['received_at_ms'] ) && is_numeric( $event['received_at_ms'] ) ) {
            return (int) floor( ( (int) $event['received_at_ms'] ) / 1000 );
        }
        return time();
    }

    private static function currency_decimals( $currency ) {
        return 'LBP' === strtoupper( (string) $currency ) ? 0 : 2;
    }

    private static function normalized_amount( $amount, $currency ) {
        return number_format( (float) $amount, self::currency_decimals( $currency ), '.', '' );
    }

    private static function amount_matches( $order_total, $event_amount, $currency ) {
        return hash_equals(
            self::normalized_amount( $order_total, $currency ),
            self::normalized_amount( $event_amount, $currency )
        );
    }

    private static function order_event_match( $order, array $event, $window_minutes, &$reason = '' ) {
        $reason = '';
        if ( ! $order || 'rlb_whish_bridge' !== $order->get_payment_method() ) {
            $reason = 'wrong_gateway';
            return false;
        }

        $currency = strtoupper( sanitize_text_field( $event['currency'] ?? '' ) );
        $amount = isset( $event['amount'] ) && is_numeric( $event['amount'] ) ? (float) $event['amount'] : 0.0;
        if ( ! $amount || ! in_array( $currency, array( 'USD', 'LBP' ), true ) ) {
            $reason = 'amount_currency';
            return false;
        }

        if ( strtoupper( $order->get_currency() ) !== $currency || ! self::amount_matches( $order->get_total(), $amount, $currency ) ) {
            $reason = 'amount_currency';
            return false;
        }

        $received_ts = self::event_timestamp( $event );
        $started = (int) $order->get_meta( '_rlb_whish_started_at', true );
        if ( ! $started ) { $started = $order->get_date_created() ? $order->get_date_created()->getTimestamp() : 0; }
        if ( ! $started ) {
            $reason = 'missing_start';
            return false;
        }

        // Allow two minutes of clock skew between the Android phone and WordPress.
        if ( $started < ( $received_ts - ( max( 5, (int) $window_minutes ) * MINUTE_IN_SECONDS ) ) || $started > ( $received_ts + 120 ) ) {
            $reason = 'time_window';
            return false;
        }

        $expires = (int) $order->get_meta( '_rlb_whish_expires_at', true );
        if ( $expires && $received_ts > ( $expires + 120 ) ) {
            $reason = 'after_expiry';
            return false;
        }

        // If Whish provides a sender phone, it becomes a strict signal. A known
        // mismatch is never allowed to fall back to amount-only matching.
        $event_phone = self::normalize_phone( $event['sender_phone'] ?? '' );
        if ( $event_phone ) {
            $order_phone = self::normalize_phone( $order->get_meta( '_rlb_whish_sender_phone', true ) );
            if ( ! $order_phone || ! hash_equals( $order_phone, $event_phone ) ) {
                $reason = 'phone_mismatch';
                return false;
            }
        }

        return true;
    }

    private static function acquire_order_lock( $order_id ) {
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

    public static function match_and_process( array $event, $event_row_id ) {
        $settings = get_option( 'woocommerce_rlb_whish_bridge_settings', array() );
        $auto_requested = isset( $settings['auto_match'] ) && 'yes' === $settings['auto_match'];
        $pilot_verified = isset( $settings['pilot_verified'] ) && 'yes' === $settings['pilot_verified'];
        $require_phone_for_auto = ! isset( $settings['auto_require_sender_phone'] ) || 'yes' === $settings['auto_require_sender_phone'];
        $window_minutes = isset( $settings['match_window_minutes'] ) ? max( 5, min( 120, (int) $settings['match_window_minutes'] ) ) : 30;

        if ( empty( $event['amount'] ) || empty( $event['currency'] ) ) {
            RLB_Whish_Storage::update_event( $event_row_id, array( 'status' => 'unparsed', 'note' => 'No amount/currency could be parsed from the notification.' ) );
            return array( 'status' => 'unparsed', 'matched_order_id' => 0 );
        }

        $amount = (float) $event['amount'];
        $currency = strtoupper( sanitize_text_field( $event['currency'] ) );
        if ( $amount <= 0 || ! in_array( $currency, array( 'USD', 'LBP' ), true ) ) {
            RLB_Whish_Storage::update_event( $event_row_id, array( 'status' => 'unparsed', 'note' => 'Parsed amount/currency is not valid for matching.' ) );
            return array( 'status' => 'unparsed', 'matched_order_id' => 0 );
        }

        $orders = wc_get_orders( array(
            'limit'          => 250,
            'status'         => array( 'pending', 'on-hold' ),
            'payment_method' => 'rlb_whish_bridge',
            'orderby'        => 'date',
            'order'          => 'DESC',
            'return'         => 'objects',
        ) );

        $event_id = sanitize_text_field( $event['event_id'] ?? '' );
        $event_phone = self::normalize_phone( $event['sender_phone'] ?? '' );
        $candidates = array();

        foreach ( $orders as $order ) {
            if ( $order->is_paid() || $order->get_meta( '_rlb_whish_event_id', true ) ) { continue; }

            // A pending order already claimed by another detected event cannot be
            // silently re-claimed by a later notification.
            $detected_event = (string) $order->get_meta( '_rlb_whish_detected_event_id', true );
            if ( $detected_event && ( ! $event_id || ! hash_equals( $detected_event, $event_id ) ) ) { continue; }

            $reason = '';
            if ( ! self::order_event_match( $order, $event, $window_minutes, $reason ) ) { continue; }
            $candidates[] = array(
                'order' => $order,
                'phone_match' => (bool) $event_phone,
            );
        }

        if ( ! $candidates ) {
            RLB_Whish_Storage::update_event( $event_row_id, array( 'status' => 'unmatched', 'note' => 'No pending Whish order matched the exact amount, currency, payment window and available sender information.' ) );
            return array( 'status' => 'unmatched', 'matched_order_id' => 0 );
        }

        if ( 1 !== count( $candidates ) ) {
            $ids = implode( ',', array_map( fn( $c ) => $c['order']->get_id(), $candidates ) );
            RLB_Whish_Storage::update_event( $event_row_id, array( 'status' => 'ambiguous', 'note' => 'Multiple pending orders matched safely: ' . $ids ) );
            return array( 'status' => 'ambiguous', 'matched_order_id' => 0, 'candidate_order_ids' => array_map( fn( $c ) => $c['order']->get_id(), $candidates ) );
        }

        $chosen = $candidates[0]['order'];
        $order_id = $chosen->get_id();
        $phone_is_strong = (bool) $event_phone;
        $can_auto = $auto_requested && $pilot_verified && ( ! $require_phone_for_auto || $phone_is_strong );

        if ( $can_auto ) {
            $note = 'Unique safe payment match found and Automatic Confirmation safety gates passed.';
        } elseif ( $auto_requested && ! $pilot_verified ) {
            $note = 'Unique payment detected, but Automatic Confirmation is blocked until a genuine incoming Whish notification is marked verified in gateway settings.';
        } elseif ( $auto_requested && $require_phone_for_auto && ! $phone_is_strong ) {
            $note = 'Unique payment detected, but sender phone was not present; manual verification is required.';
        } else {
            $note = 'Unique payment detected; Automatic Confirmation is disabled.';
        }

        RLB_Whish_Storage::update_event( $event_row_id, array(
            'status' => $can_auto ? 'matched' : 'detected',
            'matched_order_id' => $order_id,
            'note' => $note,
        ) );

        $chosen->update_meta_data( '_rlb_whish_detected_event_id', $event_id );
        $chosen->update_meta_data( '_rlb_whish_detected_at', time() );
        $chosen->delete_meta_data( '_rlb_whish_expired' );
        $chosen->delete_meta_data( '_rlb_whish_review_hold' );
        $chosen->add_order_note( sprintf(
            'Whish payment detected by signed bridge (%s %s), event %s%s.',
            wc_format_decimal( $amount, self::currency_decimals( $currency ) ),
            $currency,
            $event_id,
            $event_phone ? ', sender phone matched' : ''
        ) );
        $chosen->save();

        if ( ! $can_auto ) {
            do_action( 'rafflelb_whish_payment_detected', $order_id, $event );
            return array( 'status' => 'detected', 'matched_order_id' => $order_id );
        }

        return self::confirm_order( $chosen, $event, $event_row_id, 'automatic' );
    }

    public static function confirm_order( $order, array $event, $event_row_id = 0, $mode = 'automatic' ) {
        if ( ! $order ) {
            return array( 'status' => 'invalid_order', 'matched_order_id' => 0 );
        }

        $order_id = $order->get_id();
        $event_id = sanitize_text_field( $event['event_id'] ?? '' );
        if ( ! $event_id ) {
            return array( 'status' => 'invalid_event', 'matched_order_id' => $order_id );
        }

        $lock = self::acquire_order_lock( $order_id );
        if ( ! $lock ) {
            return array( 'status' => 'busy', 'matched_order_id' => $order_id );
        }

        try {
            $order = wc_get_order( $order_id );
            if ( ! $order || 'rlb_whish_bridge' !== $order->get_payment_method() ) {
                return array( 'status' => 'invalid_order', 'matched_order_id' => $order_id );
            }
            if ( $order->is_paid() ) {
                return array( 'status' => 'already_paid', 'matched_order_id' => $order_id );
            }
            if ( $order->get_meta( '_rlb_whish_event_id', true ) ) {
                return array( 'status' => 'already_matched', 'matched_order_id' => $order_id );
            }

            $detected = (string) $order->get_meta( '_rlb_whish_detected_event_id', true );
            if ( $detected && ! hash_equals( $detected, $event_id ) ) {
                return array( 'status' => 'different_event_claimed', 'matched_order_id' => $order_id );
            }

            $settings = get_option( 'woocommerce_rlb_whish_bridge_settings', array() );
            $window_minutes = isset( $settings['match_window_minutes'] ) ? max( 5, min( 120, (int) $settings['match_window_minutes'] ) ) : 30;
            $reason = '';
            if ( ! self::order_event_match( $order, $event, $window_minutes, $reason ) ) {
                if ( $event_row_id ) {
                    RLB_Whish_Storage::update_event( $event_row_id, array( 'status' => 'review_required', 'note' => 'Confirmation blocked by re-check: ' . $reason ) );
                }
                return array( 'status' => 'review_required', 'matched_order_id' => $order_id, 'reason' => $reason );
            }

            $txid = 'WHISH-' . preg_replace( '/[^A-Za-z0-9_-]/', '', $event_id );
            $order->update_meta_data( '_rlb_whish_event_id', $event_id );
            $order->update_meta_data( '_rlb_whish_verified_at', time() );
            $order->update_meta_data( '_rlb_whish_verification_mode', 'manual' === $mode ? 'manual' : 'automatic' );
            $order->delete_meta_data( '_rlb_whish_review_hold' );
            $order->save();

            $order->payment_complete( $txid );
            $order->add_order_note(
                'manual' === $mode
                    ? 'Whish payment confirmed by an administrator after review of the signed bridge event.'
                    : 'Whish payment automatically verified by the signed RaffleLB Android bridge.'
            );
            $order->save();
            if ( function_exists( 'as_unschedule_all_actions' ) ) {
                as_unschedule_all_actions( 'rlb_whish_expire_order', array( $order_id ), 'rafflelb-whish' );
            }
            wp_clear_scheduled_hook( 'rlb_whish_expire_order', array( $order_id ) );

            if ( $event_row_id ) {
                RLB_Whish_Storage::update_event( $event_row_id, array(
                    'status' => 'manual' === $mode ? 'paid_manual' : 'paid_auto',
                    'matched_order_id' => $order_id,
                    'note' => 'manual' === $mode ? 'Order payment completed after administrator confirmation.' : 'Order payment completed automatically.',
                ) );
            }

            do_action( 'rafflelb_whish_payment_verified', $order_id, $event_id, $event );
            return array( 'status' => 'paid', 'matched_order_id' => $order_id );
        } finally {
            delete_option( $lock );
        }
    }
}
