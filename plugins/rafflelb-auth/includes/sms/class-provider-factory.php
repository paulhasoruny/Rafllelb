<?php
if (!defined('ABSPATH')) { exit; }

final class RaffleLB_Auth_Provider_Factory {
    public static function providers() {
        return apply_filters('rafflelb_auth_sms_providers', array(
            'disabled' => new RaffleLB_Auth_Disabled_Provider(),
            'test' => new RaffleLB_Auth_Test_Provider(),
            'bestsmsbulk' => new RaffleLB_Auth_BestSMSBulk_Provider(),
        ));
    }
    public static function get() {
        $settings = get_option('rafflelb_auth_settings', array());
        $id = !empty($settings['provider']) ? sanitize_key($settings['provider']) : 'disabled';
        $providers = self::providers();
        return isset($providers[$id]) ? $providers[$id] : $providers['disabled'];
    }
}
