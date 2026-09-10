<?php
defined('ABSPATH') || exit;

final class RLCM {
    const SLUG = 'rafflelb-cart-manager';
    private static $locks = array();
    private static $supported_handler = false;

    public static function session_handler($handler) {
        if ($handler === 'Automattic\\WooCommerce\\StoreApi\\SessionHandler') {
            // Woo's final Cart-Token handler cannot be extended. Lock before its init/read.
            $utils = 'Automattic\\WooCommerce\\StoreApi\\Utilities\\CartTokenUtils';
            $token = isset($_SERVER['HTTP_CART_TOKEN']) && is_string($_SERVER['HTTP_CART_TOKEN']) ? wp_unslash($_SERVER['HTTP_CART_TOKEN']) : '';
            if (is_callable(array($utils, 'validate_cart_token')) && $utils::validate_cart_token($token)) {
                $payload = $utils::get_cart_token_payload($token);
                if (!empty($payload['user_id'])) self::lock($payload['user_id']);
            }
            return $handler;
        }
        if ($handler === 'RLCM_Session_Handler') return $handler;
        if ($handler !== 'WC_Session_Handler') {
            update_option('rlcm_custom_session_seen', (string) $handler, false);
            return $handler;
        }
        require_once __DIR__ . '/session.php';
        self::$supported_handler = true;
        return 'RLCM_Session_Handler';
    }

    public static function lock($key) {
        if ((string) $key === '') return;
        global $wpdb;
        $name = 'rlcm_' . substr(hash('sha256', DB_NAME . ':' . $wpdb->prefix . ':' . $key), 0, 50);
        if (isset(self::$locks[$name])) return;
        if ((string) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 8)', $name)) !== '1') {
            wp_die('This cart is busy. Please retry in a moment.', 'Cart busy', array('response' => 409));
        }
        self::$locks[$name] = true;
        // Run after WooCommerce saves its session at shutdown priority 20.
        add_action('shutdown', array(__CLASS__, 'unlock'), PHP_INT_MAX);
    }
    public static function unlock() {
        global $wpdb;
        foreach (self::$locks as $name => $unused) $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $name));
        self::$locks = array();
    }

    public static function decode($value) {
        if (!is_string($value)) return $value;
        if (!is_serialized($value)) return $value;
        return @unserialize(trim($value), array('allowed_classes' => false));
    }
    public static function data($raw) {
        $data = self::decode($raw);
        if (!is_array($data)) return array();
        return $data;
    }
    public static function field($data, $key, $default = null) {
        return isset($data[$key]) ? self::decode($data[$key]) : $default;
    }
    public static function cart($data) {
        $cart = self::field($data, 'cart', array());
        return is_array($cart) ? $cart : array();
    }
    public static function item($item) {
        if (!is_array($item) || empty($item['product_id']) || empty($item['quantity'])) return false;
        $pid = absint($item['product_id']);
        $variation = !empty($item['variation_id']) ? absint($item['variation_id']) : 0;
        $product = wc_get_product($variation ?: $pid);
        $draw = 0;
        if ($product) {
            if (get_post_meta($product->get_id(), '_rafflelb_draw_enabled', true) === 'yes') $draw = $product->get_id();
            elseif ($product->is_type('variation') && get_post_meta($product->get_parent_id(), '_rafflelb_draw_enabled', true) === 'yes') $draw = $product->get_parent_id();
        }
        $raffle = $draw && (!isset($item['_rafflelb_purchase_mode']) || $item['_rafflelb_purchase_mode'] !== 'buy_now');
        return array('id' => $raffle ? $draw : ($variation ?: $pid), 'raffle' => (bool) $raffle, 'product' => $product, 'qty' => max(0, (float) $item['quantity']));
    }
    public static function fingerprint($row) {
        return hash_hmac('sha256', $row->session_key . '|' . serialize(self::cart(self::data($row->session_value))) . '|' . self::field(self::data($row->session_value), 'rafflelb_hold_token', '') . '|' . self::field(self::data($row->session_value), 'rafflelb_hold_started_at', ''), wp_salt('nonce'));
    }
    public static function compatibility() {
        global $wpdb;
        if (!class_exists('WooCommerce') || !class_exists('RaffleLB_Draw_Engine')) return 'WooCommerce and RaffleLB Draw Engine must be active.';
        foreach (array('sync_hold_from_cart', 'release_current_hold') as $method) {
            if (!is_callable(array('RaffleLB_Draw_Engine', $method))) return 'The installed draw engine does not provide the required reservation functions.';
        }
        if (RaffleLB_Draw_Engine::HOLD_TABLE !== 'rafflelb_holds' || RaffleLB_Draw_Engine::HOLD_MINUTES !== 15 || RaffleLB_Draw_Engine::VERSION !== '0.34.18') return 'This draw engine version needs a compatibility review before cart removal is enabled.';
        $engine_file = (new ReflectionClass('RaffleLB_Draw_Engine'))->getFileName();
        $contract = json_decode(file_get_contents(__DIR__ . '/engine-contract.json'), true);
        if (!$engine_file || !is_readable($engine_file) || !$contract) return 'The draw engine compatibility contract could not be checked.';
        $source = file($engine_file);
        foreach ($contract as $method => $hash) {
            if (!method_exists('RaffleLB_Draw_Engine', $method)) return 'A required draw engine method has changed. Cart removal is disabled.';
            $reflection = new ReflectionMethod('RaffleLB_Draw_Engine', $method);
            $body = implode('', array_slice($source, $reflection->getStartLine() - 1, $reflection->getEndLine() - $reflection->getStartLine() + 1));
            if (!hash_equals($hash, hash('sha256', str_replace("\r", '', $body)))) return 'The draw engine reservation code has changed. Cart removal is disabled until its integration is reviewed.';
        }
        if (apply_filters('woocommerce_session_handler', 'WC_Session_Handler') !== 'RLCM_Session_Handler') return 'A custom session handler is active. Cart removal is disabled.';
        if (get_option('rlcm_custom_session_seen', '')) return 'An unsupported session handler was detected. Cart removal is disabled; review that integration before reactivating Cart Manager.';
        if (time() - (int) get_option('rlcm_activated_at', time()) < 120) return 'Cart removal becomes available two minutes after activation, allowing existing requests to finish.';
        foreach (array($wpdb->prefix . 'woocommerce_sessions', $wpdb->prefix . 'rafflelb_holds', $wpdb->usermeta) as $table) {
            $engine = $wpdb->get_var($wpdb->prepare('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s', $table));
            if (strtoupper((string) $engine) !== 'INNODB') return 'Cart removal requires InnoDB storage for WooCommerce sessions, raffle holds, and user metadata.';
        }
        return '';
    }

    public static function menu() {
        global $menu, $submenu;
        $parent = '';
        $parent_index = null;

        foreach ((array) $menu as $index => $entry) {
            $label = isset($entry[0]) ? strtolower(trim(wp_strip_all_tags($entry[0]))) : '';
            $slug  = isset($entry[2]) ? (string) $entry[2] : '';
            if ($slug === 'rafflelb' || $label === 'rafflelb' || $label === 'rafflelb cart manager') {
                $parent = $slug ?: 'rafflelb';
                $parent_index = $index;
                break;
            }
        }

        if (!$parent) {
            $parent = 'rafflelb';
            add_menu_page('RaffleLB Cart Manager', 'RaffleLB Cart Manager', 'manage_woocommerce', $parent, array(__CLASS__, 'page'), 'dashicons-cart', 57);
        } elseif ($parent_index !== null && isset($menu[$parent_index][0])) {
            // Keep the existing RaffleLB parent page/callback, but use the requested admin label.
            $menu[$parent_index][0] = 'RaffleLB Cart Manager';
        }

        add_submenu_page($parent, 'Cart Manager', 'Cart Manager', 'manage_woocommerce', self::SLUG, array(__CLASS__, 'page'));

        // WordPress mirrors the parent menu as the first submenu item. Rename that
        // existing parent/dashboard entry to "Settings" without changing its callback.
        if (!empty($submenu[$parent])) {
            foreach ($submenu[$parent] as &$item) {
                if (isset($item[2]) && (string) $item[2] === (string) $parent) {
                    $item[0] = 'Settings';
                    if (isset($item[3])) $item[3] = 'Settings';
                    break;
                }
            }
            unset($item);
        }
    }
    public static function assets($hook) {
        if (strpos($hook, self::SLUG) === false && $hook !== 'toplevel_page_rafflelb') return;
        wp_enqueue_style('rlcm', plugins_url('admin.css', __FILE__), array(), '0.1.3');
        wp_enqueue_script('rlcm', plugins_url('admin.js', __FILE__), array(), '0.1.3', true);
    }

    public static function inventory() {
        global $wpdb;
        $groups = array(); $last = 0; $tokens = array();
        $holds = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}rafflelb_holds WHERE expires_at >= %s", current_time('mysql')));
        $index = array();
        foreach ((array) $holds as $hold) $index[$hold->hold_token][(int) $hold->product_id] = $hold;
        // Keyset batches avoid loading every serialized session into memory at once.
        do {
            $rows = $wpdb->get_results($wpdb->prepare("SELECT session_id, session_key, session_value, session_expiry FROM {$wpdb->prefix}woocommerce_sessions WHERE session_id > %d AND session_expiry > %d ORDER BY session_id LIMIT 250", $last, time()));
            foreach ((array) $rows as $row) {
                $last = (int) $row->session_id;
                $data = self::data($row->session_value); $cart = self::cart($data);
                $token = (string) self::field($data, 'rafflelb_hold_token', '');
                $per = array();
                foreach ($cart as $line) {
                    $info = self::item($line); if (!$info) continue;
                    $gid = $info['id'] . ($info['raffle'] ? ':raffle' : ':store');
                    if (!isset($per[$gid])) $per[$gid] = array('info' => $info, 'qty' => 0);
                    $per[$gid]['qty'] += $info['qty'];
                }
                $user = ctype_digit((string) $row->session_key) ? get_user_by('id', (int) $row->session_key) : false;
                foreach ($per as $gid => $entry) {
                    $info = $entry['info']; $hold = $info['raffle'] && isset($index[$token][$info['id']]) ? $index[$token][$info['id']] : null;
                    // Raffle products are useful here only while they have a real, active
                    // Draw Engine hold. WooCommerce can retain saved carts long after the
                    // 15-minute raffle reservation expires, so hide those stale cart rows.
                    if ($info['raffle'] && !$hold) continue;
                    if ($hold) $tokens[$token . ':' . $info['id']] = true;
                    $holder = array('row' => $row, 'user' => $user, 'qty' => $entry['qty'], 'hold' => $hold, 'activity' => (int) self::field($data, 'rlcm_last_activity', 0));
                    if (!isset($groups[$gid])) $groups[$gid] = array('info' => $info, 'holders' => array());
                    $groups[$gid]['holders'][] = $holder;
                }
            }
        } while (count((array) $rows) === 250);
        $orphans = 0;
        foreach ((array) $holds as $hold) if (!isset($tokens[$hold->hold_token . ':' . $hold->product_id])) {
            ++$orphans; $gid = $hold->product_id . ':raffle';
            if (!isset($groups[$gid])) $groups[$gid] = array('info' => array('id' => (int) $hold->product_id, 'raffle' => true), 'holders' => array());
            $groups[$gid]['holders'][] = array('row' => null, 'user' => false, 'qty' => 0, 'hold' => $hold, 'activity' => 0);
        }
        uasort($groups, function ($a, $b) { return strcasecmp(self::title($a['info']), self::title($b['info'])); });
        return array($groups, $orphans);
    }
    public static function title($info) {
        $p = wc_get_product($info['id']);
        return $p ? $p->get_name() : 'Unavailable product #' . $info['id'];
    }
    public static function timer($hold) {
        if (!$hold) return 'No active hold';
        $seconds = max(0, strtotime($hold->expires_at . ' UTC') - (int) current_time('timestamp'));
        return '<span data-remaining="' . esc_attr($seconds) . '">' . esc_html(sprintf('%02d:%02d', floor($seconds / 60), $seconds % 60)) . '</span>';
    }
    public static function page() {
        if (!current_user_can('manage_woocommerce')) wp_die('You cannot manage carts.', '', array('response' => 403));
        echo '<div class="wrap rlcm"><h1>Cart Manager</h1><p>Active raffle reservations and saved store carts. Raffle products show only carts that currently hold an active 15-minute reservation.</p>';
        $problem = self::compatibility();
        if ($problem) echo '<div class="notice notice-warning"><p>' . esc_html($problem) . '</p></div>';
        if (isset($_GET['removed'])) echo '<div class="notice notice-success"><p>Item removed. The saved cart and raffle holds have been updated.</p></div>';
        if (!class_exists('WooCommerce') || !class_exists('RaffleLB_Draw_Engine')) { echo '</div>'; return; }
        list($groups, $orphans) = self::inventory();
        echo '<div class="rlcm-toolbar"><label>Find a product <input type="search" id="rlcm-search" placeholder="Product name or ID"></label><a class="button" href="' . esc_url(admin_url('admin.php?page=' . self::SLUG)) . '">Refresh carts</a></div>';
        echo '<p class="description">Snapshot: ' . esc_html(wp_date('Y-m-d H:i:s')) . '. Countdown updates locally; Refresh carts reloads quantities. Expired or stale raffle carts without an active hold are hidden. Last activity is recorded from activation onward. Store cart quantities are not stock reservations.</p>';
        if ($orphans) echo '<div class="notice notice-warning"><p>' . esc_html($orphans) . ' active hold(s) have no matching saved cart. They remain under the draw engine’s normal expiry; removal is unavailable.</p></div>';
        if (!$groups) echo '<div class="rlcm-empty">No active raffle reservations or saved store carts found.</div>';
        foreach ($groups as $gid => $group) {
            $info = $group['info']; $users = array(); $guests = 0; $unmatched = 0; $qty = 0; $reserved = 0; $seen = array(); $soonest = null;
            foreach ($group['holders'] as $holder) {
                if (!$holder['row']) ++$unmatched; elseif ($holder['user']) $users[$holder['user']->ID] = true; else ++$guests;
                $qty += $holder['qty'];
                if ($holder['hold'] && !isset($seen[$holder['hold']->id])) {
                    $seen[$holder['hold']->id] = true; $reserved += $holder['hold']->quantity;
                    if (!$soonest || $holder['hold']->expires_at < $soonest->expires_at) $soonest = $holder['hold'];
                }
            }
            $active_carts = count($users) + $guests;
            $cart_label = $active_carts === 1 ? 'active cart' : 'active carts';
            $quantity_summary = $info['raffle']
                ? esc_html($active_carts) . ' ' . $cart_label . '<br>' . esc_html($reserved) . ' reserved'
                : esc_html($qty) . ' in carts<br>Stock not reserved';
            echo '<details class="rlcm-product" data-search="' . esc_attr(strtolower(self::title($info) . ' ' . $info['id'])) . '"><summary><span><strong>' . esc_html(self::title($info)) . '</strong><small>#' . esc_html($info['id']) . ' · ' . ($info['raffle'] ? 'Raffle entries' : 'Store / direct purchase') . '</small></span><span>' . count($users) . ' users · ' . $guests . ' guests' . ($unmatched ? '<br>' . $unmatched . ' unmatched holds' : '') . '</span><span>' . $quantity_summary . '</span><span>Next expiry<br>' . ($info['raffle'] ? self::timer($soonest) : 'Not timed') . '</span></summary>';
            echo '<div class="rlcm-table"><table class="widefat striped"><thead><tr><th>Cart holder</th><th>Quantity / held</th><th>Last activity</th><th>Time remaining</th><th>Action</th></tr></thead><tbody>';
            foreach ($group['holders'] as $holder) {
                $row = $holder['row']; $u = $holder['user'];
                if (!$row) {
                    echo '<tr><td>Unmatched hold<br><small>No active saved session</small></td><td>— / ' . esc_html($holder['hold']->quantity) . '</td><td>Unknown</td><td>' . self::timer($holder['hold']) . '</td><td>Normal expiry only</td></tr>';
                    continue;
                }
                $name = $u ? $u->display_name . ' — ' . $u->user_email : 'Guest #' . strtoupper(substr(hash_hmac('sha256', $row->session_key, wp_salt('auth')), 0, 10));
                echo '<tr><td>' . esc_html($name) . '<br><small>' . ($u ? 'User #' . (int) $u->ID : 'Anonymous session') . '</small></td><td>' . esc_html($holder['qty']) . ' / ' . ($info['raffle'] ? esc_html($holder['hold'] ? $holder['hold']->quantity : 0) : '—') . '</td><td>' . ($holder['activity'] ? esc_html(human_time_diff($holder['activity']) . ' ago') : 'Not yet recorded') . '</td><td>' . ($info['raffle'] ? self::timer($holder['hold']) : 'Not timed') . '</td><td>';
                echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="rlcm-remove">';
                foreach (array('action' => 'rlcm_remove', 'session_id' => $row->session_id, 'product_id' => $info['id'], 'mode' => $info['raffle'] ? 'raffle' : 'store', 'fingerprint' => self::fingerprint($row)) as $key => $value) echo '<input type="hidden" name="' . esc_attr($key) . '" value="' . esc_attr($value) . '">';
                wp_nonce_field('rlcm_remove_' . $row->session_id, '_rlcm_nonce');
                echo '<button class="button rlcm-danger" ' . disabled((bool) $problem, true, false) . '>Remove Now</button></form></td></tr>';
            }
            echo '</tbody></table></div></details>';
        }
        echo '<p id="rlcm-no-match" hidden>No products match your search.</p></div>';
    }

    public static function handle_remove() {
        if (!current_user_can('manage_woocommerce')) wp_die('You cannot manage carts.', '', array('response' => 403));
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') wp_die('POST required.', '', array('response' => 405));
        $sid = isset($_POST['session_id']) ? absint($_POST['session_id']) : 0;
        check_admin_referer('rlcm_remove_' . $sid, '_rlcm_nonce');
        $problem = self::compatibility(); if ($problem) wp_die(esc_html($problem));
        $pid = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;
        $mode = isset($_POST['mode']) && $_POST['mode'] === 'raffle' ? 'raffle' : 'store';
        $fingerprint = isset($_POST['fingerprint']) && is_string($_POST['fingerprint']) ? wp_unslash($_POST['fingerprint']) : '';
        try { self::remove($sid, $pid, $mode, $fingerprint); }
        catch (Throwable $e) { wp_die(esc_html($e->getMessage()), 'Cart was not changed', array('response' => 409, 'back_link' => true)); }
        wp_safe_redirect(admin_url('admin.php?page=' . self::SLUG . '&removed=1')); exit;
    }

    public static function remove($sid, $pid, $mode, $fingerprint) {
        global $wpdb;
        $table = $wpdb->prefix . 'woocommerce_sessions';
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE session_id = %d", $sid));
        if (!$row) throw new RuntimeException('This saved cart no longer exists. Refresh carts.');
        self::lock($row->session_key);
        if (false === $wpdb->query('START TRANSACTION')) throw new RuntimeException('Could not start a safe cart update. Nothing was changed.');
        $old_session = WC()->session; $old_cart = WC()->cart; $uid = 0; $token = '';
        try {
            $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE session_id = %d FOR UPDATE", $sid));
            if (!$row || $row->session_expiry <= time() || !hash_equals(self::fingerprint($row), $fingerprint)) throw new RuntimeException('This cart changed or expired. Refresh carts and try again.');
            if ($old_session && (string) $old_session->get_customer_id() === (string) $row->session_key) throw new RuntimeException('Use the storefront to edit your own current cart.');
            $data = self::data($row->session_value); $cart = self::cart($data); $removed = array();
            foreach ($cart as $key => $line) {
                $info = self::item($line);
                if ($info && $info['id'] === $pid && $info['raffle'] === ($mode === 'raffle')) { $removed[$key] = true; unset($cart[$key]); }
            }
            if (!$removed) throw new RuntimeException('The selected product is no longer in this cart.');
            $token = (string) self::field($data, 'rafflelb_hold_token', '');
            if ($mode === 'raffle') {
                $started = (int) self::field($data, 'rafflelb_hold_started_at', 0);
                if (!$token || !$started || (int) current_time('timestamp') >= $started + 900) throw new RuntimeException('This raffle reservation has expired or is missing. Refresh carts; normal expiry will clear the shopper’s raffle items on their next request.');
                $before = $wpdb->get_results($wpdb->prepare("SELECT product_id, quantity, expires_at FROM {$wpdb->prefix}rafflelb_holds WHERE hold_token = %s FOR UPDATE", $token), ARRAY_A);
                // Refuse shared/cloned tokens rather than releasing another saved cart's holds.
                $duplicates = $wpdb->get_results($wpdb->prepare("SELECT session_id, session_value FROM {$table} WHERE session_id <> %d AND session_expiry > %d AND session_value LIKE %s", $sid, time(), '%' . $wpdb->esc_like($token) . '%'));
                foreach ((array) $duplicates as $other) if (self::field(self::data($other->session_value), 'rafflelb_hold_token', '') === $token) throw new RuntimeException('This hold is shared by multiple sessions. Wait for normal expiry; manual removal is disabled.');
                $hydrated = array();
                foreach ($cart as $key => $line) {
                    $info = self::item($line);
                    if (!$info || !$info['product']) throw new RuntimeException('Another cart item is unavailable. The cart cannot be safely rebuilt.');
                    $line['data'] = $info['product']; $hydrated[$key] = $line;
                }
                require_once __DIR__ . '/session.php';
                WC()->session = new RLCM_Remote_Session($data);
                WC()->cart = new RLCM_Remote_Cart($hydrated);
                $wpdb->last_error = '';
                RaffleLB_Draw_Engine::sync_hold_from_cart();
                if ($wpdb->last_error) throw new RuntimeException('The draw engine could not update reservations. No changes were saved.');
                $new_started = WC()->session->get('rafflelb_hold_started_at');
                if ($new_started) $data['rafflelb_hold_started_at'] = $new_started; else unset($data['rafflelb_hold_started_at']);
                $after = $wpdb->get_results($wpdb->prepare("SELECT product_id, quantity, expires_at FROM {$wpdb->prefix}rafflelb_holds WHERE hold_token = %s", $token), ARRAY_A);
                $expected = array_values(array_filter($before, function ($hold) use ($pid) { return (int) $hold['product_id'] !== $pid; }));
                $sort = function (&$values) { usort($values, function ($a, $b) { return (int) $a['product_id'] <=> (int) $b['product_id']; }); };
                $sort($expected); $sort($after);
                if ($expected != $after) throw new RuntimeException('Other reservations changed during removal. Nothing was saved; refresh and retry.');
            }
            WC()->session = $old_session; WC()->cart = $old_cart;
            $data['cart'] = maybe_serialize($cart);
            $undo = self::field($data, 'removed_cart_contents', array());
            foreach ((array) $undo as $key => $line) {
                $info = self::item($line);
                if (isset($removed[$key]) || ($info && $info['id'] === $pid && $info['raffle'] === ($mode === 'raffle'))) unset($undo[$key]);
            }
            $data['removed_cart_contents'] = maybe_serialize($undo);
            // Force WooCommerce to recalculate totals/shipping on the next cart load.
            unset($data['cart_totals'], $data['coupon_discount_totals'], $data['coupon_discount_tax_totals']);
            foreach (array_keys($data) as $key) if (strpos($key, 'shipping_for_package_') === 0) unset($data[$key]);
            $uid = ctype_digit((string) $row->session_key) && get_user_by('id', (int) $row->session_key) ? (int) $row->session_key : 0;
            if ($uid) {
                $meta_key = '_woocommerce_persistent_cart_' . get_current_blog_id();
                $meta = $wpdb->get_results($wpdb->prepare("SELECT umeta_id, meta_value FROM {$wpdb->usermeta} WHERE user_id = %d AND meta_key = %s FOR UPDATE", $uid, $meta_key));
                foreach ($meta as $m) {
                    $saved = self::data($m->meta_value);
                    if (!empty($saved['cart']) && is_array($saved['cart'])) foreach ($saved['cart'] as $key => $line) {
                        $info = self::item($line);
                        if ($info && $info['id'] === $pid && $info['raffle'] === ($mode === 'raffle')) unset($saved['cart'][$key]);
                    }
                    if (false === $wpdb->update($wpdb->usermeta, array('meta_value' => maybe_serialize($saved)), array('umeta_id' => $m->umeta_id), array('%s'), array('%d'))) throw new RuntimeException('Could not update the saved account cart.');
                }
            }
            if (false === $wpdb->update($table, array('session_value' => maybe_serialize($data)), array('session_id' => $sid), array('%s'), array('%d'))) throw new RuntimeException('Could not save the cart.');
            if (false === $wpdb->query('COMMIT')) throw new RuntimeException('Could not commit the cart update.');
            self::clear_cache($row->session_key, $uid);
        } catch (Throwable $e) {
            $wpdb->query('ROLLBACK'); throw $e;
        } finally { WC()->session = $old_session; WC()->cart = $old_cart; }
    }
    private static function clear_cache($key, $uid) {
        wp_cache_delete(WC_Cache_Helper::get_cache_prefix(WC_SESSION_CACHE_GROUP) . $key, WC_SESSION_CACHE_GROUP);
        if ($uid) wp_cache_delete($uid, 'user_meta');
    }
}
