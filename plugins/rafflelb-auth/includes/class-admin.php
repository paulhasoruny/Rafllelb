<?php
if (!defined('ABSPATH')) { exit; }

final class RaffleLB_Auth_Admin {
    public static function defaults() { return array('provider' => 'disabled', 'otp_expiry' => 300, 'resend_cooldown' => 60, 'max_attempts' => 5, 'phone_hourly_limit' => 5, 'ip_hourly_limit' => 20, 'test_mode' => 0, 'bsb_api_key' => '', 'bsb_api_secret' => '', 'bsb_secured_key' => '', 'bsb_sender_id' => ''); }
    public static function init() { add_action('admin_menu', array(__CLASS__, 'menu')); add_action('admin_init', array(__CLASS__, 'settings')); }
    public static function menu() { add_options_page(__('RaffleLB Authentication', 'rafflelb-auth'), __('RaffleLB Authentication', 'rafflelb-auth'), 'manage_options', 'rafflelb-auth', array(__CLASS__, 'page')); }
    public static function settings() {
        register_setting('rafflelb_auth', 'rafflelb_auth_settings', array('sanitize_callback' => array(__CLASS__, 'sanitize'), 'default' => self::defaults()));
    }
    public static function sanitize($input) {
        $defaults = self::defaults(); $existing = wp_parse_args(get_option('rafflelb_auth_settings', array()), $defaults); $out = $defaults;
        $providers = RaffleLB_Auth_Provider_Factory::providers(); $provider = isset($input['provider']) ? sanitize_key($input['provider']) : 'disabled';
        $out['provider'] = isset($providers[$provider]) ? $provider : 'disabled';
        foreach (array('otp_expiry','resend_cooldown','max_attempts','phone_hourly_limit','ip_hourly_limit') as $key) { $out[$key] = max(1, absint(isset($input[$key]) ? $input[$key] : $defaults[$key])); }
        $out['test_mode'] = (!self::is_production() && !empty($input['test_mode'])) ? 1 : 0;
        if ($out['provider'] === 'test' && !$out['test_mode']) { $out['provider'] = 'disabled'; }
        foreach (array('bsb_api_key','bsb_api_secret','bsb_secured_key') as $key) {
            $replacement = isset($input[$key]) && is_scalar($input[$key]) ? trim((string) wp_unslash($input[$key])) : '';
            $out[$key] = $replacement !== '' ? $replacement : (string) $existing[$key];
        }
        $sender_id = isset($input['bsb_sender_id']) && is_scalar($input['bsb_sender_id']) ? sanitize_text_field(wp_unslash($input['bsb_sender_id'])) : '';
        $out['bsb_sender_id'] = $sender_id !== '' ? $sender_id : (string) $existing['bsb_sender_id'];
        return $out;
    }
    public static function is_production() { return function_exists('wp_get_environment_type') && wp_get_environment_type() === 'production'; }
    public static function test_mode_enabled() { $s = wp_parse_args(get_option('rafflelb_auth_settings', array()), self::defaults()); return !self::is_production() && !empty($s['test_mode']); }
    public static function page() {
        if (!current_user_can('manage_options')) { return; }
        $s = wp_parse_args(get_option('rafflelb_auth_settings', array()), self::defaults()); $providers = RaffleLB_Auth_Provider_Factory::providers(); ?>
        <div class="wrap"><h1>RaffleLB → Authentication</h1><p>Auth-owned SMS and verification settings. Best SMS Bulk / PROXIREACH uses the provider's Send SMS (Text HTTP) API.</p>
        <div class="notice notice-info inline" style="padding:12px 14px"><p style="margin:0 0 8px"><strong>Username / email account login</strong></p><p style="margin:0 0 8px">Use this short private link for accounts you create manually without verified phone registration. Normal RaffleLB customer login remains phone-only. The older My Account query URL remains supported as a fallback.</p><input type="text" readonly class="regular-text code" style="width:min(100%,760px)" value="<?php echo esc_attr(RaffleLB_Auth_Frontend::legacy_login_url()); ?>" onclick="this.select();"></div>
        <?php if (self::is_production()): ?><div class="notice notice-warning inline"><p><strong>Development test mode is locked off because this environment is production.</strong></p></div><?php endif; ?>
        <form method="post" action="options.php"><?php settings_fields('rafflelb_auth'); ?><table class="form-table" role="presentation">
        <tr><th><label for="rl-provider">SMS Provider</label></th><td><select id="rl-provider" name="rafflelb_auth_settings[provider]"><?php foreach ($providers as $id => $provider) { echo '<option value="'.esc_attr($id).'" '.selected($s['provider'],$id,false).'>'.esc_html($provider->get_label()).'</option>'; } ?></select><p class="description">Provider status: <strong><?php echo esc_html(RaffleLB_Auth_Provider_Factory::get()->is_available() ? 'Available' : 'Unavailable'); ?></strong></p></td></tr>
        <?php if ($s['provider'] === 'bestsmsbulk'): ?>
        <?php self::credential_row('bsb_api_key', 'API Key', 'RAFFLELB_BSB_API_KEY', $s, 'password'); ?>
        <?php self::credential_row('bsb_api_secret', 'API Secret', 'RAFFLELB_BSB_API_SECRET', $s, 'password'); ?>
        <?php self::credential_row('bsb_sender_id', 'Sender ID', 'RAFFLELB_BSB_SENDER_ID', $s, 'text'); ?>
        <?php endif; ?>
        <?php foreach (array('otp_expiry'=>'OTP expiry (seconds)','resend_cooldown'=>'Resend cooldown (seconds)','max_attempts'=>'Max verification attempts','phone_hourly_limit'=>'Max sends per phone/hour','ip_hourly_limit'=>'Max sends per IP/hour') as $key=>$label): ?><tr><th><label for="rl-<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></label></th><td><input id="rl-<?php echo esc_attr($key); ?>" type="number" min="1" name="rafflelb_auth_settings[<?php echo esc_attr($key); ?>]" value="<?php echo esc_attr($s[$key]); ?>"></td></tr><?php endforeach; ?>
        <tr><th>Test mode</th><td><label><input type="checkbox" name="rafflelb_auth_settings[test_mode]" value="1" <?php checked($s['test_mode']); disabled(self::is_production()); ?>> <strong>NOT FOR PRODUCTION</strong> — expose recent encrypted test codes to administrators only.</label></td></tr>
        </table><?php submit_button(); ?></form>
        <?php if (self::test_mode_enabled()): ?><h2>Recent development codes</h2><table class="widefat striped"><thead><tr><th>Phone</th><th>Code</th><th>Created (UTC)</th></tr></thead><tbody><?php
        $index = get_transient('rl_auth_test_index'); $shown = false;
        foreach (is_array($index) ? $index : array() as $phone => $key) { $plain = RaffleLB_Auth_Test_Provider::decrypt(get_transient($key)); $data = $plain ? json_decode($plain, true) : null; if (!$data) continue; $shown=true; echo '<tr><td>'.esc_html($phone).'</td><td><code>'.esc_html($data['code']).'</code></td><td>'.esc_html(gmdate('Y-m-d H:i:s', $data['created'])).'</td></tr>'; }
        if (!$shown) echo '<tr><td colspan="3">No active test codes.</td></tr>'; ?></tbody></table><?php endif; ?></div><?php
    }

    private static function credential_row($key, $label, $constant_name, $settings, $type) {
        $constant_configured = defined($constant_name) && is_scalar(constant($constant_name)) && trim((string) constant($constant_name)) !== '';
        $stored = !$constant_configured && !empty($settings[$key]); ?>
        <tr><th><label for="rl-<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></label></th><td>
        <input id="rl-<?php echo esc_attr($key); ?>" type="<?php echo esc_attr($type); ?>" name="rafflelb_auth_settings[<?php echo esc_attr($key); ?>]" value="" autocomplete="new-password" <?php disabled($constant_configured); ?>>
        <p class="description"><?php
        if ($constant_configured) { echo 'Configured via wp-config.php'; }
        elseif ($stored) { echo 'Stored — leave blank to keep existing value'; }
        else { echo 'Not configured'; }
        ?></p></td></tr><?php
    }
}
