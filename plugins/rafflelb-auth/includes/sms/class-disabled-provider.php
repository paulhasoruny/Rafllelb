<?php
if (!defined('ABSPATH')) { exit; }

final class RaffleLB_Auth_Disabled_Provider implements RaffleLB_Auth_SMS_Provider_Interface {
    public function get_id() { return 'disabled'; }
    public function get_label() { return __('Disabled', 'rafflelb-auth'); }
    public function is_available() { return false; }
    public function send($phone, $message, $context = array()) {
        return new WP_Error('sms_unavailable', __('SMS delivery is not configured yet.', 'rafflelb-auth'));
    }
}

