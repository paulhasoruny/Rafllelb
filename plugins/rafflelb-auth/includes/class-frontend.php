<?php
if (!defined('ABSPATH')) { exit; }

final class RaffleLB_Auth_Frontend {
    private static $rendered = false;
    private static $buffering_woo_form = false;
    public static function init() {
        add_shortcode('rafflelb_auth', array(__CLASS__, 'shortcode'));
        add_action('woocommerce_before_customer_login_form', array(__CLASS__, 'woocommerce_form'), -999);
        add_action('woocommerce_after_customer_login_form', array(__CLASS__, 'discard_legacy_woocommerce_form'), 999);
        add_filter('body_class', array(__CLASS__, 'body_class'));
        add_action('wp_enqueue_scripts', array(__CLASS__, 'enqueue'));
    }
    public static function is_auth_page() {
        if (is_user_logged_in()) { return false; }
        if (function_exists('is_account_page') && is_account_page()) { return true; }
        global $post; return $post && has_shortcode($post->post_content, 'rafflelb_auth');
    }
    public static function body_class($classes) { if (self::is_auth_page()) { $classes[] = 'rafflelb-auth-active'; } return $classes; }
    public static function enqueue() {
        if (!self::is_auth_page()) { return; }
        wp_enqueue_style('rafflelb-auth', plugins_url('assets/auth.css', RAFFLELB_AUTH_FILE), array(), RAFFLELB_AUTH_VERSION);
        wp_enqueue_script('rafflelb-auth', plugins_url('assets/auth.js', RAFFLELB_AUTH_FILE), array(), RAFFLELB_AUTH_VERSION, true);
        wp_localize_script('rafflelb-auth', 'RaffleLBAuth', array(
            'ajaxUrl' => admin_url('admin-ajax.php'), 'nonce' => wp_create_nonce('rafflelb_auth_frontend'),
            'cooldown' => absint(wp_parse_args(get_option('rafflelb_auth_settings', array()), RaffleLB_Auth_Admin::defaults())['resend_cooldown']),
        ));
    }
    public static function woocommerce_form() {
        echo self::shortcode();
        // Auth owns the logged-out My Account UI. Removing the native guest form
        // from the final markup also gives Account 0.1.6's enhancer nothing to
        // wrap or mutate, while authenticated account screens remain untouched.
        ob_start();
        self::$buffering_woo_form = true;
    }
    public static function discard_legacy_woocommerce_form() {
        if (self::$buffering_woo_form) {
            ob_end_clean();
            self::$buffering_woo_form = false;
        }
    }
    public static function shortcode() {
        if (is_user_logged_in() || self::$rendered) { return ''; }
        self::$rendered = true;
        ob_start(); ?>
        <section class="rl-auth" data-rl-auth>
            <div class="rl-auth__card">
                <div class="rl-auth__mark" aria-hidden="true"><img src="<?php echo esc_url(plugins_url('assets/rafflelb-site-icon.png', RAFFLELB_AUTH_FILE)); ?>" alt=""></div>
                <p class="rl-auth__wordmark" aria-hidden="true">RAFFLE<span>LB</span></p>
                <div class="rl-auth__status" role="status" aria-live="polite" hidden></div>

                <div class="rl-auth__view is-active" data-view="login">
                    <header><p class="rl-auth__eyebrow">RAFFLELB ACCOUNT</p><h1>Welcome back</h1><p>Your next chance starts here.</p></header>
                    <form data-action="rafflelb_auth_login" novalidate>
                        <input type="hidden" name="login_mode" value="phone" data-login-mode-input>
                        <div class="rl-auth__field rl-auth__login-identifier" data-login-identifier data-mode="phone">
                            <label for="login_identifier" data-login-label><?php esc_html_e('Phone number', 'rafflelb-auth'); ?></label>
                            <div class="rl-auth__phone" data-login-control><span aria-hidden="true">+961</span><input id="login_identifier" name="phone" type="tel" inputmode="tel" autocomplete="tel" placeholder="03 123 456" required></div>
                            <div class="rl-auth__identifier-meta"><small data-login-help><?php esc_html_e('Lebanese mobile number', 'rafflelb-auth'); ?></small><button type="button" class="rl-auth__link rl-auth__mode-toggle" data-login-mode-toggle aria-pressed="false"><?php esc_html_e('Use email instead', 'rafflelb-auth'); ?></button></div>
                        </div>
                        <?php self::password_field('login_password', __('Password', 'rafflelb-auth'), 'current-password', 'password'); ?>
                        <div class="rl-auth__options"><label class="rl-auth__check"><input type="checkbox" name="remember" value="1"> <span><?php esc_html_e('Remember me', 'rafflelb-auth'); ?></span></label><button type="button" class="rl-auth__link" data-go="reset-phone"><?php esc_html_e('Forgot password?', 'rafflelb-auth'); ?></button></div>
                        <button class="rl-auth__primary rl-auth__primary--arrow" type="submit"><?php esc_html_e('Log in', 'rafflelb-auth'); ?><span aria-hidden="true">&rarr;</span></button>
                    </form>
                    <div class="rl-auth__divider" aria-hidden="true"><span>OR</span></div>
                    <button type="button" class="rl-auth__secondary" data-go="register-phone"><?php esc_html_e('Create account', 'rafflelb-auth'); ?></button>
                </div>

                <div class="rl-auth__view" data-view="register-phone">
                    <?php self::heading('Create your account', 'Start with your Lebanese mobile number.'); ?>
                    <form data-action="rafflelb_auth_register_send" data-next="register-code" novalidate><?php self::phone_field('register_phone'); ?><button class="rl-auth__primary" type="submit">Send code</button></form>
                    <?php self::back('login', 'Back to login', true); ?>
                </div>
                <div class="rl-auth__view" data-view="register-code">
                    <?php self::heading('Verify your number', 'We sent a 6-digit code to <strong data-phone-label></strong>.'); ?>
                    <form data-action="rafflelb_auth_register_verify" data-next="register-details" novalidate><?php self::otp_field('register_code'); ?><button class="rl-auth__primary" type="submit">Verify phone</button></form>
                    <p class="rl-auth__resend"><button type="button" class="rl-auth__link" data-resend="register" disabled>Resend code in <span data-countdown>01:00</span></button></p>
                    <?php self::back('register-phone', 'Use a different number'); ?>
                </div>
                <div class="rl-auth__view" data-view="register-details">
                    <?php self::heading('Complete your account', 'Your phone is verified. Add your account details.'); ?>
                    <form data-action="rafflelb_auth_register_create" novalidate>
                        <div class="rl-auth__field"><label for="rl-register-email">Email address</label><input id="rl-register-email" name="email" type="email" autocomplete="email" required></div>
                        <?php self::password_field('register_password', 'Password', 'new-password', 'password'); ?>
                        <?php self::password_field('register_confirm', 'Confirm password', 'new-password', 'confirm_password'); ?>
                        <button class="rl-auth__primary" type="submit">Create account</button>
                    </form>
                </div>

                <div class="rl-auth__view" data-view="reset-phone">
                    <?php self::heading('Reset password', 'Verify the phone number connected to your account.'); ?>
                    <form data-action="rafflelb_auth_reset_send" data-next="reset-code" novalidate><?php self::phone_field('reset_phone'); ?><button class="rl-auth__primary" type="submit">Send code</button></form>
                    <?php self::back('login', 'Back to login', true); ?>
                </div>
                <div class="rl-auth__view" data-view="reset-code">
                    <?php self::heading('Enter verification code', 'If an account matches, a code was sent to <strong data-phone-label></strong>.'); ?>
                    <form data-action="rafflelb_auth_reset_verify" data-next="reset-password" novalidate><?php self::otp_field('reset_code'); ?><button class="rl-auth__primary" type="submit">Verify</button></form>
                    <p class="rl-auth__resend"><button type="button" class="rl-auth__link" data-resend="reset" disabled>Resend code in <span data-countdown>01:00</span></button></p>
                    <?php self::back('reset-phone', 'Use a different number'); ?>
                </div>
                <div class="rl-auth__view" data-view="reset-password">
                    <?php self::heading('Choose a new password', 'Use at least 8 characters.'); ?>
                    <form data-action="rafflelb_auth_reset_finish" data-next="login" novalidate>
                        <?php self::password_field('reset_password', 'New password', 'new-password', 'password'); ?>
                        <?php self::password_field('reset_confirm', 'Confirm new password', 'new-password', 'confirm_password'); ?>
                        <button class="rl-auth__primary" type="submit">Update password</button>
                    </form>
                </div>
            </div>
        </section>
        <?php return ob_get_clean();
    }
    private static function heading($title, $subtitle) { echo '<header><p class="rl-auth__eyebrow">SECURE ACCESS</p><h1>' . esc_html($title) . '</h1><p>' . wp_kses($subtitle, array('strong' => array('data-phone-label' => true))) . '</p></header>'; }
    private static function phone_field($id) { ?><div class="rl-auth__field"><label for="<?php echo esc_attr($id); ?>">Phone number</label><div class="rl-auth__phone"><span aria-hidden="true">+961</span><input id="<?php echo esc_attr($id); ?>" name="phone" type="tel" inputmode="tel" autocomplete="tel" placeholder="03 123 456" required></div><small>Lebanese mobile number</small></div><?php }
    private static function otp_field($id) { ?><div class="rl-auth__field"><label for="<?php echo esc_attr($id); ?>">6-digit code</label><input class="rl-auth__otp" id="<?php echo esc_attr($id); ?>" name="code" type="text" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" placeholder="••••••" required></div><?php }
    private static function password_field($id, $label, $autocomplete, $name) { ?><div class="rl-auth__field"><label for="<?php echo esc_attr($id); ?>"><?php echo esc_html($label); ?></label><div class="rl-auth__password"><input id="<?php echo esc_attr($id); ?>" name="<?php echo esc_attr($name); ?>" type="password" autocomplete="<?php echo esc_attr($autocomplete); ?>" required><button type="button" class="rl-auth__eye" aria-label="Show password" aria-pressed="false" data-password-toggle><svg data-eye-open viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path fill="currentColor" d="M12 5c-5.4 0-9.8 3.6-11.5 7 1.7 3.4 6.1 7 11.5 7s9.8-3.6 11.5-7C21.8 8.6 17.4 5 12 5zm0 11.5A4.5 4.5 0 1 1 12 7.5a4.5 4.5 0 0 1 0 9zm0-2.2a2.3 2.3 0 1 0 0-4.6 2.3 2.3 0 0 0 0 4.6z"/></svg><svg data-eye-closed viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" hidden><path fill="currentColor" d="M2.4 3.9 3.9 2.4l17.7 17.7-1.5 1.5-3.1-3.1c-1.5.6-3.2 1-5 1-5.4 0-9.8-3.6-11.5-7 .8-1.6 2-3 3.5-4.2L2.4 3.9zM12 7.5c.5 0 1 .1 1.4.2l-1.6 1.6a2.3 2.3 0 0 0-2.5 2.5L7.7 13.2A4.5 4.5 0 0 1 12 7.5zm0-2.5c5.4 0 9.8 3.6 11.5 7a13 13 0 0 1-3.1 3.9l-1.4-1.4A10.9 10.9 0 0 0 21.5 12C19.8 8.6 15.4 5 12 5c-1 0-2 .1-2.9.4L7.6 3.9C8.9 3.4 10.4 3 12 3v2.5z"/></svg></button></div></div><?php }
    private static function back($view, $label, $cta = false) { $class = $cta ? 'rl-auth__back rl-auth__back--cta' : 'rl-auth__back'; echo '<button type="button" class="' . esc_attr($class) . '" data-go="' . esc_attr($view) . '">← ' . esc_html($label) . '</button>'; }
}
