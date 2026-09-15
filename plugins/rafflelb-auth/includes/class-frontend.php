<?php
if (!defined('ABSPATH')) { exit; }

final class RaffleLB_Auth_Frontend {
    private static $rendered = false;
    private static $buffering_woo_form = false;
    public static function init() {
        add_action('init', array(__CLASS__, 'register_email_login_route'), 1);
        add_filter('query_vars', array(__CLASS__, 'query_vars'));
        add_filter('redirect_canonical', array(__CLASS__, 'disable_canonical_on_email_login'), 10, 2);
        add_action('send_headers', array(__CLASS__, 'protect_email_login_from_indexing'));
        add_filter('wp_robots', array(__CLASS__, 'email_login_robots'));
        add_shortcode('rafflelb_auth', array(__CLASS__, 'shortcode'));
        add_action('woocommerce_before_customer_login_form', array(__CLASS__, 'woocommerce_form'), -999);
        add_action('woocommerce_after_customer_login_form', array(__CLASS__, 'discard_legacy_woocommerce_form'), 999);
        add_filter('body_class', array(__CLASS__, 'body_class'));
        add_action('wp_enqueue_scripts', array(__CLASS__, 'enqueue'));
    }
    public static function register_email_login_route() {
        add_rewrite_rule('^email-login/?$', 'index.php?pagename=my-account&rafflelb_login=legacy', 'top');

        $stored_version = (string) get_option('rafflelb_auth_rewrite_version', '');
        if ($stored_version !== RAFFLELB_AUTH_VERSION) {
            flush_rewrite_rules(false);
            update_option('rafflelb_auth_rewrite_version', RAFFLELB_AUTH_VERSION, false);
        }
    }
    public static function query_vars($vars) {
        $vars[] = 'rafflelb_login';
        return $vars;
    }
    public static function disable_canonical_on_email_login($redirect_url, $requested_url) {
        if (self::is_email_login_path()) { return false; }
        return $redirect_url;
    }
    public static function protect_email_login_from_indexing() {
        if (!self::is_email_login_path()) { return; }
        if (!headers_sent()) {
            header('X-Robots-Tag: noindex, nofollow, noarchive', true);
        }
    }
    public static function email_login_robots($robots) {
        if (!self::is_email_login_path()) { return $robots; }
        $robots['noindex'] = true;
        $robots['nofollow'] = true;
        $robots['noarchive'] = true;
        unset($robots['index'], $robots['follow']);
        return $robots;
    }
    private static function is_email_login_path() {
        $path = isset($_SERVER['REQUEST_URI']) ? (string) wp_unslash($_SERVER['REQUEST_URI']) : '';
        $path = (string) wp_parse_url($path, PHP_URL_PATH);
        $home_path = (string) wp_parse_url(home_url('/'), PHP_URL_PATH);
        $home_path = '/' . trim($home_path, '/');
        if ($home_path === '/') { $home_path = ''; }
        if ($home_path !== '' && strpos($path, $home_path . '/') === 0) {
            $path = substr($path, strlen($home_path));
        }
        return trim($path, '/') === 'email-login';
    }
    public static function legacy_login_requested() {
        if (is_user_logged_in()) { return false; }
        if (self::is_email_login_path()) { return true; }
        $mode = isset($_GET['rafflelb_login']) ? sanitize_key(wp_unslash($_GET['rafflelb_login'])) : sanitize_key((string) get_query_var('rafflelb_login'));
        return $mode === 'legacy';
    }
    public static function legacy_login_url() {
        return home_url('/email-login/');
    }
    public static function legacy_login_fallback_url() {
        $base = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('myaccount') : home_url('/my-account/');
        return add_query_arg('rafflelb_login', 'legacy', $base);
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
        $legacy = self::legacy_login_requested();
        ob_start(); ?>
        <section class="rl-auth" data-rl-auth>
            <div class="rl-auth__card">
                <div class="rl-auth__mark" aria-hidden="true"><img src="<?php echo esc_url(plugins_url('assets/rafflelb-site-icon.png', RAFFLELB_AUTH_FILE)); ?>" alt=""></div>
                <p class="rl-auth__wordmark" aria-hidden="true">RAFFLE<span>LB</span></p>
                <div class="rl-auth__status" role="status" aria-live="polite" hidden></div>

                <div class="rl-auth__view is-active" data-view="login">
                    <header><p class="rl-auth__eyebrow">RAFFLELB ACCOUNT</p><h1>Welcome back</h1><p><?php echo esc_html($legacy ? 'Use the username or email assigned to this account.' : 'Your next chance starts here.'); ?></p></header>
                    <form data-action="rafflelb_auth_login" novalidate>
                        <input type="hidden" name="login_mode" value="<?php echo esc_attr($legacy ? 'legacy' : 'phone'); ?>">
                        <?php if ($legacy): ?><input type="hidden" name="legacy_token" value="<?php echo esc_attr(wp_create_nonce('rafflelb_auth_legacy')); ?>">
                        <?php endif; ?>
                        <?php if ($legacy): ?>
                        <div class="rl-auth__field rl-auth__login-identifier" data-login-identifier data-mode="legacy">
                            <label for="login_identifier"><?php esc_html_e('Username or email', 'rafflelb-auth'); ?></label>
                            <input id="login_identifier" name="phone" type="text" autocomplete="username" placeholder="Username or email address" required>
                            <div class="rl-auth__identifier-meta"><small><?php esc_html_e('For accounts created without phone registration', 'rafflelb-auth'); ?></small></div>
                        </div>
                        <?php else: ?>
                        <div class="rl-auth__field rl-auth__login-identifier" data-login-identifier data-mode="phone">
                            <label for="login_identifier" data-login-label><?php esc_html_e('Phone number', 'rafflelb-auth'); ?></label>
                            <div class="rl-auth__phone" data-login-control><span aria-hidden="true">+961</span><input id="login_identifier" name="phone" type="tel" inputmode="tel" autocomplete="tel" placeholder="03 123 456" required></div>
                            <div class="rl-auth__identifier-meta"><small><?php esc_html_e('Lebanese mobile number', 'rafflelb-auth'); ?></small></div>
                        </div>
                        <?php endif; ?>
                        <?php self::password_field('login_password', __('Password', 'rafflelb-auth'), 'current-password', 'password'); ?>
                        <div class="rl-auth__options"><label class="rl-auth__check"><input type="checkbox" name="remember" value="1"> <span><?php esc_html_e('Remember me', 'rafflelb-auth'); ?></span></label><?php if (!$legacy): ?><button type="button" class="rl-auth__link" data-go="reset-phone"><?php esc_html_e('Forgot password?', 'rafflelb-auth'); ?></button><?php else: ?><a class="rl-auth__link" href="<?php echo esc_url(wp_lostpassword_url(function_exists('wc_get_page_permalink') ? wc_get_page_permalink('myaccount') : home_url('/my-account/'))); ?>"><?php esc_html_e('Forgot password?', 'rafflelb-auth'); ?></a><?php endif; ?></div>
                        <button class="rl-auth__primary rl-auth__primary--arrow" type="submit"><?php esc_html_e('Log in', 'rafflelb-auth'); ?><span aria-hidden="true">&rarr;</span></button>
                    </form>
                    <?php if (!$legacy): ?>
                    <div class="rl-auth__divider" aria-hidden="true"><span>OR</span></div>
                    <button type="button" class="rl-auth__secondary" data-go="register-phone"><?php esc_html_e('Create account', 'rafflelb-auth'); ?></button>
                    <?php endif; ?>
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
                        <div class="rl-auth__field"><label for="rl-register-first-name">First name</label><input id="rl-register-first-name" name="first_name" type="text" autocomplete="given-name" maxlength="80" data-register-first required></div>
                        <div class="rl-auth__field"><label for="rl-register-last-name">Family name</label><input id="rl-register-last-name" name="last_name" type="text" autocomplete="family-name" maxlength="80" data-register-last required></div>
                        <div class="rl-auth__field"><label for="rl-register-username">Username</label><input id="rl-register-username" name="username" type="text" autocomplete="username" minlength="3" maxlength="60" pattern="[A-Za-z0-9._-]{3,60}" required><small>Choose a unique username using letters, numbers, dots, underscores, or hyphens.</small></div>
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
