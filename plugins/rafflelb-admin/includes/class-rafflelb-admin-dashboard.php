<?php
defined('ABSPATH') || exit;

final class RaffleLB_Admin_Dashboard {
    const PAGE_SLUG = 'rafflelb-admin-dashboard';
    const CAPABILITY = 'manage_woocommerce';

    public static function init() {
        add_action('admin_menu', array(__CLASS__, 'register_page'), 1);
        add_action('admin_init', array(__CLASS__, 'redirect_dashboard'));
        add_filter('login_redirect', array(__CLASS__, 'login_redirect'), 20, 3);
        add_action('admin_enqueue_scripts', array(__CLASS__, 'assets'));
    }

    public static function register_page() {
        add_dashboard_page(
            __('RaffleLB Operations Dashboard', 'rafflelb-admin'),
            __('RaffleLB Dashboard', 'rafflelb-admin'),
            self::CAPABILITY,
            self::PAGE_SLUG,
            array(__CLASS__, 'render')
        );
    }

    public static function redirect_dashboard() {
        if (!is_admin() || !current_user_can(self::CAPABILITY) || !RaffleLB_Admin_Navigation::is_rafflelb_mode()) return;
        global $pagenow;

        // Redirect only the native Dashboard home. Never redirect our own
        // index.php?page=rafflelb-admin-dashboard request back to itself.
        $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
        if ($pagenow === 'index.php' && $page === '' && !wp_doing_ajax()) {
            wp_safe_redirect(admin_url('index.php?page=' . self::PAGE_SLUG));
            exit;
        }
    }

    public static function login_redirect($redirect_to, $requested_redirect_to, $user) {
        if ($user instanceof WP_User && user_can($user, self::CAPABILITY) && RaffleLB_Admin_Navigation::is_rafflelb_mode($user->ID)) {
            return admin_url('index.php?page=' . self::PAGE_SLUG);
        }
        return $redirect_to;
    }

    public static function assets($hook) {
        $is_dashboard = $hook === 'dashboard_page_' . self::PAGE_SLUG
            || $hook === 'toplevel_page_' . self::PAGE_SLUG
            || (isset($_GET['page']) && sanitize_key(wp_unslash($_GET['page'])) === self::PAGE_SLUG);
        if (!$is_dashboard) return;

        wp_enqueue_style(
            'rafflelb-admin-dashboard',
            RAFFLELB_ADMIN_URL . 'assets/css/dashboard.css',
            array(),
            RAFFLELB_ADMIN_VERSION
        );
        wp_enqueue_script(
            'rafflelb-admin-dashboard',
            RAFFLELB_ADMIN_URL . 'assets/js/dashboard.js',
            array(),
            RAFFLELB_ADMIN_VERSION,
            true
        );
    }

    private static function table_exists($suffix) {
        global $wpdb;
        $table = $wpdb->prefix . $suffix;
        return $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table;
    }

    private static function engine_ready() {
        return class_exists('RaffleLB_Draw_Engine')
            && self::table_exists('rafflelb_entries')
            && self::table_exists('rafflelb_holds')
            && self::table_exists('rafflelb_draw_results');
    }

    private static function urls() {
        return array(
            'dashboard' => admin_url('index.php?page=' . self::PAGE_SLUG),
            'raffles' => admin_url('admin.php?page=rafflelb-raffles'),
            'create_raffle' => admin_url('admin.php?page=rafflelb-raffle-add'),
            'cart_manager' => admin_url('admin.php?page=rafflelb-cart-manager'),
            'entries' => admin_url('admin.php?page=rafflelb-entries'),
            'orders' => admin_url('admin.php?page=wc-orders'),
            'products' => admin_url('edit.php?post_type=product'),
            'customers' => admin_url('users.php'),
            'coupons' => admin_url('edit.php?post_type=shop_coupon'),
            'reports' => admin_url('admin.php?page=wc-reports'),
            'settings' => admin_url('admin.php?page=wc-settings'),
        );
    }

    private static function date_bounds() {
        $tz = wp_timezone();
        $range = isset($_GET['rlad_range']) ? sanitize_key(wp_unslash($_GET['rlad_range'])) : 'today';
        $days = array('today' => 0, '7d' => 6, '30d' => 29);
        $days_back = isset($days[$range]) ? $days[$range] : 0;
        $start = (new DateTimeImmutable('today', $tz))->modify('-' . $days_back . ' days')->format('Y-m-d H:i:s');
        $end = (new DateTimeImmutable('tomorrow', $tz))->format('Y-m-d H:i:s');
        return array($start, $end, $range);
    }


    private static function range_metric_labels($range) {
        $sets = array(
            'today' => array(
                'entries' => __('Entries Sold Today', 'rafflelb-admin'),
                'revenue' => __('Revenue Today', 'rafflelb-admin'),
                'orders' => __('Orders Today', 'rafflelb-admin'),
                'customers' => __('New Customers Today', 'rafflelb-admin'),
            ),
            '7d' => array(
                'entries' => __('Entries Sold (7 Days)', 'rafflelb-admin'),
                'revenue' => __('Revenue (7 Days)', 'rafflelb-admin'),
                'orders' => __('Orders (7 Days)', 'rafflelb-admin'),
                'customers' => __('New Customers (7 Days)', 'rafflelb-admin'),
            ),
            '30d' => array(
                'entries' => __('Entries Sold (30 Days)', 'rafflelb-admin'),
                'revenue' => __('Revenue (30 Days)', 'rafflelb-admin'),
                'orders' => __('Orders (30 Days)', 'rafflelb-admin'),
                'customers' => __('New Customers (30 Days)', 'rafflelb-admin'),
            ),
        );
        return isset($sets[$range]) ? $sets[$range] : $sets['today'];
    }

    private static function reservation_rows() {
        $cache_key = 'rlad_active_reservations_v101';
        $rows = get_transient($cache_key);
        $now = current_time('timestamp');

        if (is_array($rows)) {
            // A short cache prevents Cart Manager from scanning WooCommerce
            // sessions on every dashboard refresh. Never display an expired row.
            return array_values(array_filter($rows, function($row) use ($now) {
                return !empty($row['expires']) && strtotime($row['expires'] . ' UTC') >= $now;
            }));
        }

        $rows = array();
        if (class_exists('RLCM') && is_callable(array('RLCM', 'inventory')) && is_callable(array('RLCM', 'title'))) {
            list($groups) = RLCM::inventory();
            foreach ((array) $groups as $group) {
                foreach ((array) $group['holders'] as $holder) {
                    if (empty($holder['hold'])) continue;
                    $user = !empty($holder['user']) ? $holder['user']->display_name : __('Guest', 'rafflelb-admin');
                    $rows[] = array(
                        'title' => RLCM::title($group['info']),
                        'user' => $user,
                        'entries' => (int) $holder['hold']->quantity,
                        'expires' => $holder['hold']->expires_at,
                    );
                    if (count($rows) >= 5) break 2;
                }
            }
        }

        set_transient($cache_key, $rows, 30);
        return $rows;
    }

    private static function dashboard_data() {
        global $wpdb;
        $data = array('kpis' => array_fill_keys(array('active_raffles', 'entries_today', 'revenue_today', 'active_reservations', 'orders_today', 'new_customers'), null), 'raffles' => array(), 'reservations' => array(), 'winners' => array(), 'orders' => array(), 'alerts' => array());
        list($start, $end) = self::date_bounds();

        if (self::engine_ready()) {
            $entries = $wpdb->prefix . 'rafflelb_entries';
            $holds = $wpdb->prefix . 'rafflelb_holds';
            $results = $wpdb->prefix . 'rafflelb_draw_results';
            $raffle_ids = get_posts(array('post_type' => 'product', 'post_status' => array('publish', 'private', 'draft'), 'posts_per_page' => 100, 'fields' => 'ids', 'meta_key' => '_rafflelb_draw_enabled', 'meta_value' => 'yes'));
            $active = array();
            foreach ((array) $raffle_ids as $id) {
                $status = (string) get_post_meta($id, '_rafflelb_draw_status', true);
                if ($status !== 'winner_selected') $active[] = (int) $id;
            }
            $data['kpis']['active_raffles'] = count($active);
            $data['kpis']['entries_today'] = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$entries} WHERE status='active' AND created_at >= %s AND created_at < %s", $start, $end));
            $data['kpis']['active_reservations'] = (int) $wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(quantity), 0) FROM {$holds} WHERE expires_at >= %s", current_time('mysql')));

            foreach (array_slice($active, 0, 8) as $id) {
                $total = absint(get_post_meta($id, '_rafflelb_total_entries', true));
                $sold = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$entries} WHERE product_id=%d AND status='active'", $id));
                $reserved = (int) $wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(quantity), 0) FROM {$holds} WHERE product_id=%d AND expires_at >= %s", $id, current_time('mysql')));
                $status = (string) get_post_meta($id, '_rafflelb_draw_status', true);
                $status = $status === 'ready_to_draw' ? 'Ready to draw' : 'Live';
                $data['raffles'][] = array('id' => $id, 'title' => get_the_title($id), 'image' => get_the_post_thumbnail_url($id, 'thumbnail'), 'sold' => $sold, 'total' => $total, 'reserved' => $reserved, 'status' => $status);
                if ($total > 0 && $sold < $total && ($sold / $total) >= .9) $data['alerts'][] = array('label' => get_the_title($id) . ' is over 90% filled', 'url' => add_query_arg(array('page' => 'rafflelb-raffles', 'action' => 'view', 'id' => $id), admin_url('admin.php')), 'type' => 'Fill level');
                if ($status === 'Ready to draw') $data['alerts'][] = array('label' => get_the_title($id) . ' is ready for winner selection', 'url' => add_query_arg(array('page' => 'rafflelb-raffles', 'action' => 'view', 'id' => $id), admin_url('admin.php')), 'type' => 'Draw');
            }
            $winner_rows = $wpdb->get_results("SELECT product_id, user_id, order_id, selected_at FROM {$results} ORDER BY selected_at DESC LIMIT 5");
            foreach ((array) $winner_rows as $row) {
                $user = $row->user_id ? get_user_by('id', $row->user_id) : false;
                $order = function_exists('wc_get_order') ? wc_get_order($row->order_id) : false;
                $name = $user ? $user->display_name : ($order ? trim($order->get_formatted_billing_full_name()) : __('Guest', 'rafflelb-admin'));
                $data['winners'][] = array('title' => get_the_title($row->product_id), 'winner' => $name ?: __('Guest', 'rafflelb-admin'), 'date' => $row->selected_at);
            }
            if ($data['kpis']['active_reservations'] > 0) $data['alerts'][] = array('label' => sprintf(__('%d active cart reservation(s) need monitoring', 'rafflelb-admin'), $data['kpis']['active_reservations']), 'url' => self::urls()['cart_manager'], 'type' => 'Reservations');
        }

        if (function_exists('wc_get_orders')) {
            $orders = wc_get_orders(array('limit' => 5, 'orderby' => 'date', 'order' => 'DESC', 'return' => 'objects', 'type' => 'shop_order'));
            foreach ($orders as $order) $data['orders'][] = $order;

            $range_orders = wc_get_orders(array(
                'limit' => -1,
                'return' => 'objects',
                'type' => 'shop_order',
                'date_created' => '>=' . $start,
            ));
            $data['kpis']['orders_today'] = count($range_orders);

            // Revenue should reflect paid orders, not pending/failed/cancelled orders.
            $paid_statuses = function_exists('wc_get_is_paid_statuses') ? wc_get_is_paid_statuses() : array('processing', 'completed');
            $paid_orders = wc_get_orders(array(
                'limit' => -1,
                'return' => 'objects',
                'type' => 'shop_order',
                'date_created' => '>=' . $start,
                'status' => $paid_statuses,
            ));
            $data['kpis']['revenue_today'] = array_sum(array_map(function($order) {
                return max(0, (float) $order->get_total() - (float) $order->get_total_refunded());
            }, $paid_orders));

            $pending = wc_get_orders(array('limit' => 1, 'return' => 'ids', 'type' => 'shop_order', 'status' => array('wc-pending', 'wc-on-hold')));
            if ($pending) $data['alerts'][] = array('label' => __('Pending or on-hold payments require review', 'rafflelb-admin'), 'url' => self::urls()['orders'], 'type' => 'Payments');
        }
        $data['kpis']['new_customers'] = count(get_users(array('role__in' => array('customer'), 'date_query' => array(array('after' => $start, 'before' => $end, 'inclusive' => true)), 'fields' => 'ID')));

        // Add the requested Raffle Requests attention alert when that CPT exists.
        if (post_type_exists('raffle_request')) {
            $counts = wp_count_posts('raffle_request');
            $pending_requests = isset($counts->pending) ? (int) $counts->pending : 0;
            if ($pending_requests > 0) {
                $data['alerts'][] = array(
                    'label' => sprintf(_n('%d raffle request is awaiting review', '%d raffle requests are awaiting review', $pending_requests, 'rafflelb-admin'), $pending_requests),
                    'url' => admin_url('edit.php?post_type=raffle_request&post_status=pending'),
                    'type' => 'Requests',
                );
            }
        }

        $data['reservations'] = self::reservation_rows();
        return $data;
    }

    private static function metric($label, $value, $kind = 'number') {
        $display = $value === null ? '&mdash;' : ($kind === 'money' && function_exists('wc_price') ? wp_kses_post(wc_price($value)) : esc_html(number_format_i18n($value)));
        echo '<article class="rlad-kpi"><span>' . esc_html($label) . '</span><strong>' . $display . '</strong></article>';
    }

    public static function render() {
        if (!current_user_can(self::CAPABILITY)) {
            wp_die(esc_html__('You do not have permission to view this page.', 'rafflelb-admin'));
        }

        $data = self::dashboard_data();
        $urls = self::urls();
        $range = isset($_GET['rlad_range']) ? sanitize_key(wp_unslash($_GET['rlad_range'])) : 'today';
        if (!in_array($range, array('today', '7d', '30d'), true)) {
            $range = 'today';
        }
        $metric_labels = self::range_metric_labels($range);

        echo '<div class="wrap rlad">';
        echo '<header class="rlad-header"><div><p class="rlad-eyebrow">RAFFLELB / OPERATIONS</p><h1>' . esc_html__('Operations Dashboard', 'rafflelb-admin') . '</h1><p>' . esc_html(wp_date(get_option('date_format'))) . '</p></div>';
        echo '<div class="rlad-actions"><form method="get"><input type="hidden" name="page" value="' . esc_attr(self::PAGE_SLUG) . '"><select class="rlad-date" name="rlad_range" onchange="this.form.submit()"><option value="today"' . selected($range, 'today', false) . '>' . esc_html__('Today', 'rafflelb-admin') . '</option><option value="7d"' . selected($range, '7d', false) . '>' . esc_html__('Last 7 days', 'rafflelb-admin') . '</option><option value="30d"' . selected($range, '30d', false) . '>' . esc_html__('Last 30 days', 'rafflelb-admin') . '</option></select></form>';
        echo '<a class="rlad-button secondary" href="' . esc_url(home_url('/')) . '" target="_blank" rel="noopener">' . esc_html__('View Website', 'rafflelb-admin') . '</a>';
        echo '<a class="rlad-button primary" href="' . esc_url($urls['create_raffle']) . '">' . esc_html__('Create Raffle', 'rafflelb-admin') . '</a></div></header>';

        echo '<section class="rlad-panel rlad-overview rlad-collapsible" data-rlad-section="overview">';
        self::collapsible_head(__('Overview', 'rafflelb-admin'));
        echo '<div class="rlad-collapse-body"><div class="rlad-kpis">';
        self::metric(__('Active Raffles', 'rafflelb-admin'), $data['kpis']['active_raffles']);
        self::metric($metric_labels['entries'], $data['kpis']['entries_today']);
        self::metric($metric_labels['revenue'], $data['kpis']['revenue_today'], 'money');
        self::metric(__('Active Reservations', 'rafflelb-admin'), $data['kpis']['active_reservations']);
        self::metric($metric_labels['orders'], $data['kpis']['orders_today']);
        self::metric($metric_labels['customers'], $data['kpis']['new_customers']);
        echo '</div></div></section>';

        self::section(
            __('Needs Attention', 'rafflelb-admin'),
            $data['alerts'],
            function($item) {
                echo '<a class="rlad-alert" href="' . esc_url($item['url']) . '"><span>' . esc_html($item['type']) . '</span><strong>' . esc_html($item['label']) . '</strong><b>→</b></a>';
            },
            __('No operational alerts right now.', 'rafflelb-admin')
        );

        echo '<section class="rlad-panel rlad-collapsible" data-rlad-section="active-raffles">';
        self::collapsible_head(__('Active Raffles', 'rafflelb-admin'), $urls['raffles']);
        echo '<div class="rlad-collapse-body">';
        if (!$data['raffles']) {
            echo '<p class="rlad-empty">' . esc_html__('No active raffle data is available.', 'rafflelb-admin') . '</p>';
        } else {
            echo '<div class="rlad-table-wrap"><table class="rlad-table"><thead><tr><th>Raffle</th><th>Entries</th><th>Reserved</th><th>Status</th><th></th></tr></thead><tbody>';
            foreach ($data['raffles'] as $r) {
                $pct = $r['total'] ? min(100, round($r['sold'] / $r['total'] * 100)) : 0;
                $manage = add_query_arg(array('page' => 'rafflelb-raffles', 'action' => 'view', 'id' => $r['id']), admin_url('admin.php'));
                echo '<tr><td class="rlad-raffle">' . ($r['image'] ? '<img src="' . esc_url($r['image']) . '" alt="">' : '<i></i>') . '<strong>' . esc_html($r['title']) . '</strong></td><td><strong>' . esc_html($r['sold'] . ' / ' . $r['total']) . '</strong><span class="rlad-progress"><i style="width:' . esc_attr($pct) . '%"></i></span></td><td>' . esc_html($r['reserved']) . '</td><td><em>' . esc_html($r['status']) . '</em></td><td><a href="' . esc_url($manage) . '">Manage</a></td></tr>';
            }
            echo '</tbody></table></div>';
        }
        echo '</div></section>';

        echo '<div class="rlad-grid">';
        self::panel_reservations($data['reservations'], $urls['cart_manager']);
        self::panel_orders($data['orders'], $urls['orders']);
        self::panel_winners($data['winners'], $urls['raffles']);
        echo '</div></div>';
    }

    private static function collapsible_head($title, $url = '') {
        echo '<div class="rlad-panel-head"><h2>' . esc_html($title) . '</h2><div class="rlad-panel-actions">';
        if ($url !== '') {
            echo '<a href="' . esc_url($url) . '">' . esc_html__('View all', 'rafflelb-admin') . '</a>';
        }
        echo '<button type="button" class="rlad-collapse-toggle" aria-expanded="true" title="' . esc_attr__('Collapse section', 'rafflelb-admin') . '"><span class="dashicons dashicons-arrow-up-alt2" aria-hidden="true"></span><span class="screen-reader-text">' . esc_html__('Toggle section', 'rafflelb-admin') . '</span></button>';
        echo '</div></div>';
    }

    private static function section($title, $items, $render, $empty) {
        echo '<section class="rlad-attention rlad-collapsible" data-rlad-section="attention">';
        self::collapsible_head($title);
        echo '<div class="rlad-collapse-body"><div class="rlad-alerts">';
        if ($items) {
            foreach ($items as $item) {
                call_user_func($render, $item);
            }
        } else {
            echo '<p class="rlad-empty">' . esc_html($empty) . '</p>';
        }
        echo '</div></div></section>';
    }

    private static function panel_reservations($rows, $url) {
        echo '<section class="rlad-panel rlad-collapsible" data-rlad-section="active-reservations">';
        self::collapsible_head(__('Active Cart Reservations', 'rafflelb-admin'), $url);
        echo '<div class="rlad-collapse-body">';
        if (!$rows) {
            echo '<p class="rlad-empty">' . esc_html__('No active holds available.', 'rafflelb-admin') . '</p>';
        } else {
            echo '<div class="rlad-table-wrap"><table class="rlad-table"><thead><tr><th>Product</th><th>Holder</th><th>Entries</th><th>Expires</th></tr></thead><tbody>';
            foreach ($rows as $row) {
                echo '<tr><td>' . esc_html($row['title']) . '</td><td>' . esc_html($row['user']) . '</td><td>' . esc_html($row['entries']) . '</td><td>' . esc_html(human_time_diff(current_time('timestamp'), strtotime($row['expires'] . ' UTC'))) . '</td></tr>';
            }
            echo '</tbody></table></div>';
        }
        echo '</div></section>';
    }

    private static function panel_orders($orders, $url) {
        echo '<section class="rlad-panel rlad-collapsible" data-rlad-section="recent-orders">';
        self::collapsible_head(__('Recent Orders', 'rafflelb-admin'), $url);
        echo '<div class="rlad-collapse-body">';
        if (!$orders) {
            echo '<p class="rlad-empty">' . esc_html__('WooCommerce orders are unavailable.', 'rafflelb-admin') . '</p>';
        } else {
            echo '<div class="rlad-table-wrap"><table class="rlad-table"><thead><tr><th>Order</th><th>Customer</th><th>Amount</th><th>Status</th></tr></thead><tbody>';
            foreach ($orders as $order) {
                echo '<tr><td><a href="' . esc_url($order->get_edit_order_url()) . '">#' . esc_html($order->get_order_number()) . '</a></td><td>' . esc_html(trim($order->get_formatted_billing_full_name()) ?: __('Guest', 'rafflelb-admin')) . '</td><td>' . wp_kses_post($order->get_formatted_order_total()) . '</td><td><em>' . esc_html(wc_get_order_status_name($order->get_status())) . '</em></td></tr>';
            }
            echo '</tbody></table></div>';
        }
        echo '</div></section>';
    }

    private static function panel_winners($rows, $url) {
        echo '<section class="rlad-panel rlad-collapsible" data-rlad-section="recent-winners">';
        self::collapsible_head(__('Recent Winners', 'rafflelb-admin'), $url);
        echo '<div class="rlad-collapse-body">';
        if (!$rows) {
            echo '<p class="rlad-empty">' . esc_html__('No completed draws yet.', 'rafflelb-admin') . '</p>';
        } else {
            echo '<div class="rlad-table-wrap"><table class="rlad-table"><thead><tr><th>Raffle</th><th>Winner</th><th>Draw date</th></tr></thead><tbody>';
            foreach ($rows as $row) {
                echo '<tr><td>' . esc_html($row['title']) . '</td><td>' . esc_html($row['winner']) . '</td><td>' . esc_html(wp_date(get_option('date_format'), strtotime($row['date'] . ' UTC'))) . '</td></tr>';
            }
            echo '</tbody></table></div>';
        }
        echo '</div></section>';
    }

}
