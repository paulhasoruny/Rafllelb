<?php
/**
 * Plugin Name: RaffleLB Raffle Manager
 * Description: A dedicated "Raffles" admin section (like WooCommerce Products) for creating, editing, and monitoring every raffle in one place. Reads and writes the same product fields, meta, and database tables as RaffleLB Draw Engine instead of duplicating its logic.
 * Version: 1.4.1
 * Author: RaffleLB
 */

if (!defined('ABSPATH')) exit;

final class RaffleLB_Raffle_Manager {
    const VERSION = '1.4.1';

    // Mirrors RaffleLB Draw Engine's own constants and table names so this
    // plugin reads/writes the exact same product meta and DB tables without
    // depending on load order between the two plugins.
    const META_ENABLED            = '_rafflelb_draw_enabled';
    const META_TOTAL              = '_rafflelb_total_entries';
    const META_DRAW_STATUS        = '_rafflelb_draw_status';
    const META_CLOSED_AT          = '_rafflelb_draw_closed_at';
    const META_WINNER_ENTRY_ID    = '_rafflelb_winner_entry_id';
    const META_WINNER_SELECTED_AT = '_rafflelb_winner_selected_at';
    const META_EARLY_CLOSED       = '_rafflelb_early_closed';
    const META_EARLY_CLOSE_REASON = '_rafflelb_early_close_reason';
    const META_EARLY_CLOSED_BY    = '_rafflelb_early_closed_by';
    const META_EARLY_CLOSE_CLAIMED = '_rafflelb_early_close_claimed';
    const META_BUY_NOW_ENABLED    = '_rafflelb_buy_now_enabled';
    const META_BUY_NOW_PRICE      = '_rafflelb_buy_now_price';

    // Delivery-type override read by RaffleLB Custom Checkout's Cash on
    // Delivery gate (rlcc_item_is_voucher_or_gift_card()). 'tangible' or
    // 'digital'; unset falls back to that plugin's own category-based check.
    const META_ITEM_TYPE = '_rafflelb_item_type';

    const ENTRY_TABLE   = 'rafflelb_entries';
    const RESULT_TABLE  = 'rafflelb_draw_results';
    const HOLD_TABLE    = 'rafflelb_holds';
    const HISTORY_TABLE = 'rafflelb_entry_history';

    const SLUG       = 'rafflelb-raffles';
    const SLUG_ADD   = 'rafflelb-raffle-add';
    const SLUG_RESET = 'rafflelb-raffle-reset';

    public static function init() {
        add_action('plugins_loaded', [__CLASS__, 'boot']);
    }

    public static function boot() {
        if (!class_exists('WooCommerce')) return;

        add_action('admin_menu', [__CLASS__, 'admin_menu']);
        add_action('admin_menu', [__CLASS__, 'hide_legacy_menu'], 999);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
        add_action('admin_notices', [__CLASS__, 'missing_draw_engine_notice']);
        add_action('admin_post_rafflelb_rm_trash_raffle', [__CLASS__, 'handle_trash']);
        add_action('admin_post_rafflelb_rm_reset_winners', [__CLASS__, 'handle_reset_winners']);
        add_action('admin_post_rafflelb_rm_delete_test_orders', [__CLASS__, 'handle_delete_test_orders']);
    }

    public static function missing_draw_engine_notice() {
        if (class_exists('RaffleLB_Draw_Engine')) return;
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (!$screen || strpos((string) $screen->id, 'rafflelb-raffle') === false) return;
        echo '<div class="notice notice-error"><p><strong>RaffleLB Raffle Manager</strong> needs the <strong>RaffleLB Draw Engine</strong> plugin active — it provides the entries, draw, and fulfillment engine this screen manages.</p></div>';
    }

    public static function admin_menu() {
        $list_hook = add_menu_page('Raffles', 'Raffles', 'manage_woocommerce', self::SLUG, [__CLASS__, 'render_list'], 'dashicons-tickets-alt', 56);
        add_submenu_page(self::SLUG, 'All Raffles', 'All Raffles', 'manage_woocommerce', self::SLUG, [__CLASS__, 'render_list']);
        $add_hook = add_submenu_page(self::SLUG, 'Add New Raffle', 'Add New Raffle', 'manage_woocommerce', self::SLUG_ADD, [__CLASS__, 'render_form']);
        add_submenu_page(self::SLUG, 'Reset / Cleanup', 'Reset / Cleanup', 'manage_woocommerce', self::SLUG_RESET, [__CLASS__, 'render_reset_page']);

        // Handled on load- (before any HTML is sent) so a save can safely redirect.
        add_action('load-' . $list_hook, [__CLASS__, 'maybe_handle_save']);
        add_action('load-' . $add_hook, [__CLASS__, 'maybe_handle_save']);
    }

    public static function hide_legacy_menu() {
        remove_submenu_page('woocommerce', 'rafflelb-entries');
    }

    public static function enqueue_assets($hook) {
        if (strpos((string) $hook, 'rafflelb-raffle') === false) return;
        wp_enqueue_media();
    }

    public static function render_list() {
        $action = isset($_GET['action']) ? sanitize_key(wp_unslash($_GET['action'])) : '';
        $id = isset($_GET['id']) ? absint($_GET['id']) : 0;

        if ($action === 'edit' && $id) { self::render_raffle_form($id); return; }
        if ($action === 'view' && $id) { self::render_monitor($id); return; }

        self::render_table();
    }

    public static function render_form() {
        self::render_raffle_form(0);
    }

    /* ---------------------------------------------------------------
     * Shared helpers (mirror the read-only logic in RaffleLB Draw Engine)
     * ------------------------------------------------------------- */

    private static function claimed($pid) {
        global $wpdb;
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}" . self::ENTRY_TABLE . " WHERE product_id=%d AND status='active'",
            $pid
        ));
    }

    private static function draw_status($pid) {
        $status = (string) get_post_meta($pid, self::META_DRAW_STATUS, true);
        if (in_array($status, ['ready_to_draw', 'winner_selected'], true)) return $status;

        $total = absint(get_post_meta($pid, self::META_TOTAL, true));
        if ($total > 0 && self::claimed($pid) >= $total) {
            update_post_meta($pid, self::META_DRAW_STATUS, 'ready_to_draw');
            return 'ready_to_draw';
        }
        return 'live';
    }

    private static function customer_name($user_id, $order) {
        if ($user_id) {
            $user = get_user_by('id', $user_id);
            if ($user) return $user->display_name;
        }
        if ($order) {
            $name = trim($order->get_formatted_billing_full_name());
            if ($name !== '') return $name;
        }
        return 'Guest';
    }

    private static function status_badge($status) {
        $map = [
            'live'             => ['LIVE', '#3c6e00', '#eaffcf'],
            'ready_to_draw'    => ['READY TO DRAW', '#7a4b00', '#fff2d6'],
            'winner_selected'  => ['WINNER SELECTED', '#10130d', '#e8ffb0'],
        ];
        [$label, $fg, $bg] = $map[$status] ?? ['UNKNOWN', '#555', '#eee'];
        return '<span class="rlbrm-badge" style="color:' . esc_attr($fg) . ';background:' . esc_attr($bg) . '">' . esc_html($label) . '</span>';
    }

    private static function fulfillment_badge($status) {
        $labels = ['pending' => 'Pending', 'contacted' => 'Contacted', 'claimed' => 'Claimed', 'fulfilled' => 'Fulfilled'];
        $label = $labels[$status] ?? 'Pending';
        $bg = $status === 'fulfilled' ? '#e8ffb0' : '#f0f0f1';
        return '<span class="rlbrm-badge" style="background:' . esc_attr($bg) . ';color:#222">' . esc_html($label) . '</span>';
    }

    private static function print_styles() {
        echo '<style>
        .rlbrm-card{background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:18px 20px;margin:16px 0;box-shadow:0 1px 3px rgba(0,0,0,.04)}
        .rlbrm-border-amber{border-left:5px solid #dba617}
        .rlbrm-border-lime{border-left:5px solid #9cff00}
        .rlbrm-border-dark{border-left:5px solid #10130d}
        .rlbrm-badge{display:inline-block;padding:3px 10px;border-radius:999px;font-size:11px;font-weight:700;letter-spacing:.03em;text-transform:uppercase}
        .rlbrm-bar{display:block;width:120px;height:6px;background:#eee;border-radius:99px;overflow:hidden;margin-bottom:4px}
        .rlbrm-bar span{display:block;height:100%;background:#9cff00}
        .rlbrm-image-field .rlbrm-image-preview img{display:block;max-width:150px;max-height:150px;padding:6px;border:1px solid #dcdcde;background:#fff;margin-bottom:8px;border-radius:4px}
        .rlbrm-tag-picker{margin-top:10px;max-width:640px}
        .rlbrm-tag-picker-label{display:block;margin-bottom:6px;color:#646970;font-size:12px}
        .rlbrm-tag-chip{margin:0 4px 4px 0}
        .rlbrm-stats{display:flex;flex-wrap:wrap;gap:28px;align-items:center}
        .rlbrm-stats > div{min-width:110px}
        </style>';
    }

    /* ---------------------------------------------------------------
     * List / table view
     * ------------------------------------------------------------- */

    private static function render_table() {
        $status_filter = isset($_GET['rm_status']) ? sanitize_key(wp_unslash($_GET['rm_status'])) : '';
        $search = isset($_GET['rm_s']) ? sanitize_text_field(wp_unslash($_GET['rm_s'])) : '';
        $paged = max(1, isset($_GET['rm_paged']) ? absint($_GET['rm_paged']) : 1);
        $per_page = 20;

        $args = [
            'post_type'      => 'product',
            'post_status'    => ['publish', 'private', 'draft'],
            'posts_per_page' => -1,
            'meta_key'       => self::META_ENABLED,
            'meta_value'     => 'yes',
            'orderby'        => 'date',
            'order'          => 'DESC',
            'fields'         => 'ids',
        ];
        if ($search !== '') $args['s'] = $search;
        $ids = get_posts($args);

        global $wpdb;
        $rows = [];
        $counts = ['live' => 0, 'ready_to_draw' => 0, 'winner_selected' => 0];
        foreach ($ids as $pid) {
            $status = self::draw_status($pid);
            $counts[$status] = ($counts[$status] ?? 0) + 1;
            if ($status_filter !== '' && $status !== $status_filter) continue;

            $row = [
                'id'     => $pid,
                'status' => $status,
                'total'  => absint(get_post_meta($pid, self::META_TOTAL, true)),
                'claimed'=> self::claimed($pid),
            ];

            if ($status === 'winner_selected') {
                $result = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}" . self::RESULT_TABLE . " WHERE product_id=%d LIMIT 1", $pid));
                if ($result) {
                    $order = wc_get_order(absint($result->order_id));
                    $row['winner'] = self::customer_name(absint($result->user_id), $order);
                    $row['fulfillment'] = !empty($result->fulfillment_status) ? $result->fulfillment_status : 'pending';
                }
            }

            $rows[] = $row;
        }

        $total_rows = count($rows);
        $total_pages = max(1, (int) ceil($total_rows / $per_page));
        $paged = min($paged, $total_pages);
        $page_rows = array_slice($rows, ($paged - 1) * $per_page, $per_page);

        $base_url = admin_url('admin.php?page=' . self::SLUG);

        echo '<div class="wrap rafflelb-rm">';
        self::print_styles();

        $notice = isset($_GET['rlbrm']) ? sanitize_key(wp_unslash($_GET['rlbrm'])) : '';
        if ($notice === 'saved') echo '<div class="notice notice-success is-dismissible"><p>Raffle saved.</p></div>';
        if ($notice === 'trashed') echo '<div class="notice notice-success is-dismissible"><p>Raffle moved to trash.</p></div>';

        echo '<h1 class="wp-heading-inline">Raffles</h1> ';
        echo '<a href="' . esc_url(admin_url('admin.php?page=' . self::SLUG_ADD)) . '" class="page-title-action">Add New Raffle</a>';
        echo '<hr class="wp-header-end">';

        $tabs = [
            ''                => 'All ' . count($ids),
            'live'            => 'Live ' . $counts['live'],
            'ready_to_draw'   => 'Ready to Draw ' . $counts['ready_to_draw'],
            'winner_selected' => 'Winner Selected ' . $counts['winner_selected'],
        ];
        $links = [];
        foreach ($tabs as $key => $label) {
            $url = $key === '' ? $base_url : add_query_arg('rm_status', $key, $base_url);
            $class = $status_filter === $key ? ' class="current"' : '';
            $links[] = '<a href="' . esc_url($url) . '"' . $class . '>' . esc_html($label) . '</a>';
        }
        echo '<ul class="subsubsub"><li>' . implode(' | </li><li>', $links) . '</li></ul><div style="clear:both"></div>';

        echo '<form method="get" style="margin:12px 0;display:flex;gap:8px;align-items:center">';
        echo '<input type="hidden" name="page" value="' . esc_attr(self::SLUG) . '">';
        if ($status_filter !== '') echo '<input type="hidden" name="rm_status" value="' . esc_attr($status_filter) . '">';
        echo '<input type="search" name="rm_s" value="' . esc_attr($search) . '" placeholder="Search raffles…">';
        echo '<button type="submit" class="button">Search</button>';
        if ($search !== '') {
            $clear = $status_filter !== '' ? add_query_arg('rm_status', $status_filter, $base_url) : $base_url;
            echo '<a class="button" href="' . esc_url($clear) . '">Clear</a>';
        }
        echo '</form>';

        if (!$page_rows) {
            if ($search !== '' || $status_filter !== '') {
                echo '<p>No raffles match this search/filter.</p>';
            } else {
                echo '<p>No raffles yet. <a href="' . esc_url(admin_url('admin.php?page=' . self::SLUG_ADD)) . '">Create your first raffle.</a></p>';
            }
        } else {
            echo '<table class="widefat striped rlbrm-table"><thead><tr><th style="width:56px"></th><th>Raffle</th><th>Raffle Price</th><th>Retail Price</th><th>Entries</th><th>Status</th><th>Winner</th><th>Fulfillment</th><th>Actions</th></tr></thead><tbody>';
            foreach ($page_rows as $row) {
                $pid = $row['id'];
                $view_url = add_query_arg(['page' => self::SLUG, 'action' => 'view', 'id' => $pid], admin_url('admin.php'));
                $edit_url = add_query_arg(['page' => self::SLUG, 'action' => 'edit', 'id' => $pid], admin_url('admin.php'));
                $pct = $row['total'] > 0 ? min(100, round(($row['claimed'] / $row['total']) * 100)) : 0;
                $retail_price = get_post_meta($pid, self::META_BUY_NOW_PRICE, true);

                echo '<tr>';
                echo '<td>' . get_the_post_thumbnail($pid, [48, 48], ['style' => 'border-radius:6px']) . '</td>';
                echo '<td><strong><a href="' . esc_url($view_url) . '">' . esc_html(get_the_title($pid)) . '</a></strong></td>';
                echo '<td>' . wp_kses_post(wc_price(get_post_meta($pid, '_regular_price', true))) . '</td>';
                echo '<td>' . ($retail_price !== '' && (float) $retail_price > 0 ? wp_kses_post(wc_price($retail_price)) : '—') . '</td>';
                echo '<td><span class="rlbrm-bar"><span style="width:' . esc_attr($pct) . '%"></span></span>' . esc_html($row['claimed']) . ' / ' . esc_html($row['total']) . '</td>';
                echo '<td>' . self::status_badge($row['status']) . '</td>';
                echo '<td>' . (isset($row['winner']) ? esc_html($row['winner']) : '—') . '</td>';
                echo '<td>' . (isset($row['fulfillment']) ? self::fulfillment_badge($row['fulfillment']) : '—') . '</td>';
                echo '<td><a class="button button-small" href="' . esc_url($view_url) . '">Monitor</a> <a class="button button-small" href="' . esc_url($edit_url) . '">Edit</a></td>';
                echo '</tr>';
            }
            echo '</tbody></table>';

            if ($total_pages > 1) {
                echo '<div class="tablenav"><div class="tablenav-pages">';
                for ($p = 1; $p <= $total_pages; $p++) {
                    $url = add_query_arg(array_filter(['rm_status' => $status_filter, 'rm_s' => $search, 'rm_paged' => $p]), $base_url);
                    echo $p === $paged
                        ? '<strong style="padding:4px 9px;border:1px solid #111;border-radius:4px;margin-right:4px">' . esc_html($p) . '</strong>'
                        : '<a class="button" style="margin-right:4px" href="' . esc_url($url) . '">' . esc_html($p) . '</a>';
                }
                echo '</div></div>';
            }
        }

        echo '</div>';
    }

    /* ---------------------------------------------------------------
     * Add / Edit form
     * ------------------------------------------------------------- */

    public static function maybe_handle_save() {
        if (empty($_POST['rafflelb_rm_save'])) return;
        check_admin_referer('rafflelb_rm_save_raffle');

        $id = isset($_POST['raffle_id']) ? absint($_POST['raffle_id']) : 0;
        $result = self::save_raffle($id);

        if (is_wp_error($result)) {
            $back = $id
                ? admin_url('admin.php?page=' . self::SLUG . '&action=edit&id=' . $id)
                : admin_url('admin.php?page=' . self::SLUG_ADD);
            wp_safe_redirect(add_query_arg('rlbrm_error', rawurlencode($result->get_error_message()), $back));
            exit;
        }

        wp_safe_redirect(admin_url('admin.php?page=' . self::SLUG . '&action=view&id=' . $result . '&rlbrm=saved'));
        exit;
    }

    private static function save_raffle($id = 0) {
        if (!current_user_can('manage_woocommerce')) {
            return new WP_Error('cap', 'You are not allowed to manage raffles.');
        }

        $title = isset($_POST['raffle_title']) ? sanitize_text_field(wp_unslash($_POST['raffle_title'])) : '';
        if ($title === '') return new WP_Error('title', 'A raffle title is required.');

        $price = isset($_POST['raffle_price']) ? wc_format_decimal(wp_unslash($_POST['raffle_price'])) : '';
        if ($price === '' || (float) $price <= 0) return new WP_Error('price', 'A raffle price greater than 0 is required.');

        $total = isset($_POST['raffle_total_entries']) ? absint(wp_unslash($_POST['raffle_total_entries'])) : 0;
        if ($total < 1) return new WP_Error('total', 'Total raffle entries must be at least 1.');

        $description = isset($_POST['raffle_description']) ? wp_kses_post(wp_unslash($_POST['raffle_description'])) : '';
        $status = (isset($_POST['raffle_status']) && $_POST['raffle_status'] === 'draft') ? 'draft' : 'publish';
        $category = isset($_POST['raffle_category']) ? absint(wp_unslash($_POST['raffle_category'])) : 0;
        $buy_now_enabled = !empty($_POST['raffle_buy_now_enabled']);
        $buy_now_price = isset($_POST['raffle_buy_now_price']) ? wc_format_decimal(wp_unslash($_POST['raffle_buy_now_price'])) : '';
        $featured_id = isset($_POST['raffle_image']) ? absint(wp_unslash($_POST['raffle_image'])) : 0;
        $tags_raw = isset($_POST['raffle_tags']) ? sanitize_text_field(wp_unslash($_POST['raffle_tags'])) : '';
        $tag_names = array_values(array_filter(array_map('trim', explode(',', $tags_raw)), 'strlen'));
        $item_type = (isset($_POST['raffle_item_type']) && $_POST['raffle_item_type'] === 'digital') ? 'digital' : 'tangible';

        if ($id) {
            if (!current_user_can('edit_post', $id)) return new WP_Error('cap', 'You are not allowed to edit this raffle.');
            $product = wc_get_product($id);
            if (!$product) return new WP_Error('missing', 'Raffle product not found.');
            if (!$product->is_type('simple')) return new WP_Error('type', 'This product is not a simple product and cannot be saved from this screen.');
        } else {
            $product = new WC_Product_Simple();
        }

        $product->set_name($title);
        $product->set_description($description);
        $product->set_status($status);
        $product->set_regular_price($price);
        $product->set_price($price);
        $product->set_virtual(true);
        $product->set_manage_stock(true);
        $product->set_backorders('no');
        $product->set_sold_individually(false);
        $product->set_stock_quantity($total);
        $product->set_stock_status($total > 0 ? 'instock' : 'outofstock');
        $product->set_image_id(($featured_id && wp_attachment_is_image($featured_id)) ? $featured_id : 0);

        $product_id = $product->save();
        if (!$product_id) return new WP_Error('save', 'The raffle could not be saved.');

        wp_set_object_terms($product_id, $category ? [$category] : [], 'product_cat');
        wp_set_object_terms($product_id, $tag_names, 'product_tag');

        update_post_meta($product_id, self::META_ENABLED, 'yes');
        update_post_meta($product_id, self::META_TOTAL, $total);
        update_post_meta($product_id, self::META_ITEM_TYPE, $item_type);
        update_post_meta($product_id, self::META_BUY_NOW_ENABLED, $buy_now_enabled ? 'yes' : 'no');
        if ($buy_now_enabled && $buy_now_price !== '' && (float) $buy_now_price > 0) {
            update_post_meta($product_id, self::META_BUY_NOW_PRICE, $buy_now_price);
        } else {
            delete_post_meta($product_id, self::META_BUY_NOW_PRICE);
        }

        return $product_id;
    }

    private static function render_raffle_form($id = 0) {
        $editing = $id > 0;
        $product = null;

        echo '<div class="wrap rafflelb-rm">';
        self::print_styles();
        echo '<h1 class="wp-heading-inline">' . ($editing ? 'Edit Raffle' : 'Add New Raffle') . '</h1> ';
        echo '<a href="' . esc_url(admin_url('admin.php?page=' . self::SLUG)) . '" class="page-title-action">Back to All Raffles</a>';
        echo '<hr class="wp-header-end">';

        if ($editing) {
            $product = wc_get_product($id);
            if (!$product) {
                echo '<div class="notice notice-error"><p>Raffle not found.</p></div></div>';
                return;
            }
            if (!$product->is_type('simple')) {
                echo '<div class="notice notice-error"><p>This raffle is not a simple product and can\'t be edited here. <a href="' . esc_url(get_edit_post_link($id)) . '">Edit it in the standard WooCommerce screen instead.</a></p></div></div>';
                return;
            }
        }

        $error = isset($_GET['rlbrm_error']) ? sanitize_text_field(wp_unslash($_GET['rlbrm_error'])) : '';
        if ($error !== '') echo '<div class="notice notice-error"><p>' . esc_html($error) . '</p></div>';

        if ($editing && self::claimed($id) > 0) {
            echo '<div class="notice notice-warning"><p>This raffle already has paid entries. Changing the raffle price or total entries here does not affect entries already sold.</p></div>';
        }

        $title = $editing ? $product->get_name() : '';
        $description = $editing ? $product->get_description() : '';
        $price = $editing ? get_post_meta($id, '_regular_price', true) : '';
        $total = $editing ? absint(get_post_meta($id, self::META_TOTAL, true)) : '';
        $buy_now_enabled = $editing && get_post_meta($id, self::META_BUY_NOW_ENABLED, true) === 'yes';
        $buy_now_price = $editing ? get_post_meta($id, self::META_BUY_NOW_PRICE, true) : '';
        $featured_id = $editing ? absint($product->get_image_id()) : 0;
        $post_status = $editing ? $product->get_status() : 'publish';
        $current_cats = $editing ? wp_get_post_terms($id, 'product_cat', ['fields' => 'ids']) : [];
        $current_cat = !empty($current_cats) ? (int) $current_cats[0] : 0;
        $current_tags = $editing ? implode(', ', wp_get_post_terms($id, 'product_tag', ['fields' => 'names'])) : '';
        $item_type = $editing ? get_post_meta($id, self::META_ITEM_TYPE, true) : 'tangible';
        if (!in_array($item_type, ['tangible', 'digital'], true)) $item_type = 'tangible';

        $form_action = $editing
            ? admin_url('admin.php?page=' . self::SLUG . '&action=edit&id=' . $id)
            : admin_url('admin.php?page=' . self::SLUG_ADD);

        echo '<form method="post" action="' . esc_url($form_action) . '" enctype="multipart/form-data">';
        wp_nonce_field('rafflelb_rm_save_raffle');
        echo '<input type="hidden" name="rafflelb_rm_save" value="1">';
        echo '<input type="hidden" name="raffle_id" value="' . esc_attr($id) . '">';

        echo '<div class="rlbrm-card"><table class="form-table"><tbody>';

        echo '<tr><th><label for="raffle_title">Title <span style="color:#b42318">*</span></label></th><td><input type="text" id="raffle_title" name="raffle_title" value="' . esc_attr($title) . '" class="large-text" required></td></tr>';

        echo '<tr><th><label for="raffle_description">Description</label></th><td><textarea id="raffle_description" name="raffle_description" rows="6" class="large-text">' . esc_textarea($description) . '</textarea></td></tr>';

        echo '<tr><th>Image</th><td>' . self::image_uploader_field('raffle_image', $featured_id, 'Choose Image') . '</td></tr>';

        echo '<tr><th><label for="raffle_category">Category</label></th><td>';
        wp_dropdown_categories([
            'taxonomy'          => 'product_cat',
            'name'              => 'raffle_category',
            'id'                => 'raffle_category',
            'hierarchical'      => true,
            'show_option_none'  => '— Select category —',
            'option_none_value' => '0',
            'selected'          => $current_cat,
            'hide_empty'        => false,
        ]);
        echo '</td></tr>';

        echo '<tr><th><label for="raffle_tags">Tags</label></th><td>';
        echo '<input type="text" id="raffle_tags" name="raffle_tags" value="' . esc_attr($current_tags) . '" class="large-text">';
        echo '<p class="description">Comma-separated. Click an existing tag below to add or remove it.</p>';
        echo self::tag_picker();
        echo '</td></tr>';

        echo '<tr><th><label for="raffle_price">Raffle Price ($) <span style="color:#b42318">*</span></label></th><td><input type="number" step="0.01" min="0.01" id="raffle_price" name="raffle_price" value="' . esc_attr($price) . '" class="regular-text" required><p class="description">This sets the WooCommerce Regular Price — the price of one raffle entry.</p></td></tr>';

        echo '<tr><th><label for="raffle_total_entries">Total Raffle Entries <span style="color:#b42318">*</span></label></th><td><input type="number" step="1" min="1" id="raffle_total_entries" name="raffle_total_entries" value="' . esc_attr($total) . '" class="regular-text" required><p class="description">Also sets stock/availability, same as the WooCommerce product screen.</p></td></tr>';

        echo '<tr><th>Buy It Now</th><td>';
        echo '<label><input type="checkbox" name="raffle_buy_now_enabled" value="1" ' . checked($buy_now_enabled, true, false) . '> Enable direct purchase (Buy It Now)</label><br><br>';
        echo '<label for="raffle_buy_now_price">Retail Price ($)</label><br>';
        echo '<input type="number" step="0.01" min="0" id="raffle_buy_now_price" name="raffle_buy_now_price" value="' . esc_attr($buy_now_price) . '" class="regular-text">';
        echo '<p class="description">The price of the item if bought directly instead of raffled. Also shown as the "Retail Price" on the storefront when Buy It Now is enabled.</p>';
        echo '</td></tr>';

        echo '<tr><th>Delivery Type</th><td>';
        echo '<label><input type="radio" name="raffle_item_type" value="tangible" ' . checked($item_type, 'tangible', false) . '> Tangible / physical item</label><br>';
        echo '<label><input type="radio" name="raffle_item_type" value="digital" ' . checked($item_type, 'digital', false) . '> Digital (voucher / gift card)</label>';
        echo '<p class="description">Physical items are eligible for Cash on Delivery at checkout. Digital items are not, and are fulfilled by WhatsApp/email instead.</p>';
        echo '</td></tr>';

        echo '<tr><th><label for="raffle_status">Status</label></th><td><select id="raffle_status" name="raffle_status">';
        echo '<option value="publish" ' . selected($post_status, 'publish', false) . '>Published</option>';
        echo '<option value="draft" ' . selected($post_status, 'draft', false) . '>Draft</option>';
        echo '</select></td></tr>';

        echo '</tbody></table></div>';

        submit_button($editing ? 'Update Raffle' : 'Create Raffle');
        echo '</form>';

        self::print_image_uploader_script();
        echo '</div>';
    }

    private static function image_uploader_field($name, $attachment_id, $button_label) {
        $url = $attachment_id ? wp_get_attachment_image_url($attachment_id, 'medium') : '';
        $html = '<div class="rlbrm-image-field" data-field="' . esc_attr($name) . '">';
        $html .= '<input type="hidden" class="rlbrm-image-id" name="' . esc_attr($name) . '" value="' . esc_attr($attachment_id) . '">';
        $html .= '<span class="rlbrm-image-preview">' . ($url ? '<img src="' . esc_url($url) . '" alt="">' : '') . '</span>';
        $html .= '<div><button type="button" class="button rlbrm-image-upload">' . esc_html($attachment_id ? 'Change Image' : $button_label) . '</button> ';
        $html .= '<button type="button" class="button rlbrm-image-remove" style="' . ($attachment_id ? '' : 'display:none') . '">Remove</button></div>';
        $html .= '</div>';
        return $html;
    }

    private static function tag_picker() {
        $terms = get_terms(['taxonomy' => 'product_tag', 'hide_empty' => false, 'orderby' => 'name']);
        if (is_wp_error($terms) || !$terms) return '';

        $html = '<div class="rlbrm-tag-picker"><span class="rlbrm-tag-picker-label">Existing tags:</span> ';
        foreach ($terms as $term) {
            $html .= '<button type="button" class="button button-small rlbrm-tag-chip" data-tag="' . esc_attr($term->name) . '">' . esc_html($term->name) . '</button> ';
        }
        $html .= '</div>';
        return $html;
    }

    private static function print_image_uploader_script() {
        ?>
        <script>
        jQuery(function($){
            var rlbrmFrame;
            $(document).on('click', '.rlbrm-image-upload', function(e){
                e.preventDefault();
                var $field = $(this).closest('.rlbrm-image-field');
                rlbrmFrame = wp.media({ title: 'Choose Image', button: { text: 'Use Image' }, library: { type: 'image' }, multiple: false });
                rlbrmFrame.off('select').on('select', function(){
                    var a = rlbrmFrame.state().get('selection').first().toJSON();
                    var url = (a.sizes && a.sizes.medium) ? a.sizes.medium.url : a.url;
                    $field.find('.rlbrm-image-id').val(a.id);
                    $field.find('.rlbrm-image-preview').html('<img src="'+url+'" alt="">');
                    $field.find('.rlbrm-image-upload').text('Change Image');
                    $field.find('.rlbrm-image-remove').show();
                });
                rlbrmFrame.open();
            });
            $(document).on('click', '.rlbrm-image-remove', function(e){
                e.preventDefault();
                var $field = $(this).closest('.rlbrm-image-field');
                $field.find('.rlbrm-image-id').val('');
                $field.find('.rlbrm-image-preview').empty();
                $(this).hide();
            });

            function rlbrmGetTags(){
                return ($('#raffle_tags').val() || '').split(',').map(function(s){ return s.trim(); }).filter(function(s){ return s.length; });
            }
            function rlbrmHighlightTagChips(){
                var tags = rlbrmGetTags().map(function(s){ return s.toLowerCase(); });
                $('.rlbrm-tag-chip').each(function(){
                    var t = String($(this).data('tag')).toLowerCase();
                    $(this).toggleClass('button-primary', tags.indexOf(t) !== -1);
                });
            }
            rlbrmHighlightTagChips();
            $(document).on('input', '#raffle_tags', rlbrmHighlightTagChips);
            $(document).on('click', '.rlbrm-tag-chip', function(e){
                e.preventDefault();
                var tag = String($(this).data('tag'));
                var tags = rlbrmGetTags();
                var idx = tags.map(function(s){ return s.toLowerCase(); }).indexOf(tag.toLowerCase());
                if (idx === -1) { tags.push(tag); } else { tags.splice(idx, 1); }
                $('#raffle_tags').val(tags.join(', '));
                rlbrmHighlightTagChips();
            });
        });
        </script>
        <?php
    }

    /* ---------------------------------------------------------------
     * Monitor (single raffle: stats, draw controls, entries, orders)
     * ------------------------------------------------------------- */

    private static function render_monitor($id) {
        $product = wc_get_product($id);

        echo '<div class="wrap rafflelb-rm">';
        self::print_styles();

        if (!$product || get_post_meta($id, self::META_ENABLED, true) !== 'yes') {
            echo '<h1>Raffle not found</h1><p><a href="' . esc_url(admin_url('admin.php?page=' . self::SLUG)) . '">Back to All Raffles</a></p></div>';
            return;
        }

        $view_url = add_query_arg(['page' => self::SLUG, 'action' => 'view', 'id' => $id], admin_url('admin.php'));
        $edit_url = add_query_arg(['page' => self::SLUG, 'action' => 'edit', 'id' => $id], admin_url('admin.php'));

        echo '<h1 class="wp-heading-inline">' . esc_html(get_the_title($id)) . '</h1> ';
        echo '<a href="' . esc_url($edit_url) . '" class="page-title-action">Edit Raffle</a> ';
        echo '<a href="' . esc_url(get_permalink($id)) . '" class="page-title-action" target="_blank" rel="noopener">View on Site</a> ';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="display:inline" onsubmit="return confirm(\'Move this raffle to trash?\');">';
        echo '<input type="hidden" name="action" value="rafflelb_rm_trash_raffle">';
        echo '<input type="hidden" name="raffle_id" value="' . esc_attr($id) . '">';
        wp_nonce_field('rafflelb_rm_trash_' . $id);
        echo '<button type="submit" class="page-title-action" style="color:#b42318">Move to Trash</button>';
        echo '</form>';
        echo '<hr class="wp-header-end">';
        echo '<p><a href="' . esc_url(admin_url('admin.php?page=' . self::SLUG)) . '">&larr; Back to All Raffles</a></p>';

        self::render_notices();

        $status = self::draw_status($id);
        $total = absint(get_post_meta($id, self::META_TOTAL, true));
        $claimed = self::claimed($id);
        $price = get_post_meta($id, '_regular_price', true);
        $buy_now_enabled = get_post_meta($id, self::META_BUY_NOW_ENABLED, true) === 'yes';
        $pct = $total > 0 ? min(100, round(($claimed / $total) * 100)) : 0;
        $revenue = $claimed * (float) $price;

        echo '<div class="rlbrm-card"><div class="rlbrm-stats">';
        echo '<div>' . get_the_post_thumbnail($id, [90, 90], ['style' => 'border-radius:8px']) . '</div>';
        echo '<div><strong>Status</strong><br>' . self::status_badge($status) . '</div>';
        echo '<div><strong>Entries</strong><br><span class="rlbrm-bar" style="width:160px"><span style="width:' . esc_attr($pct) . '%"></span></span>' . esc_html($claimed) . ' / ' . esc_html($total) . '</div>';
        echo '<div><strong>Raffle Price</strong><br>' . wp_kses_post(wc_price($price)) . '</div>';
        echo '<div><strong>Entry Revenue</strong><br>' . wp_kses_post(wc_price($revenue)) . '</div>';
        if ($buy_now_enabled) {
            echo '<div><strong>Retail Price</strong><br>' . wp_kses_post(wc_price(get_post_meta($id, self::META_BUY_NOW_PRICE, true))) . '</div>';
        }
        $item_type = get_post_meta($id, self::META_ITEM_TYPE, true);
        $item_type = in_array($item_type, ['tangible', 'digital'], true) ? $item_type : 'tangible';
        echo '<div><strong>Delivery Type</strong><br>' . ($item_type === 'digital' ? 'Digital' : 'Tangible (COD eligible)') . '</div>';
        echo '</div></div>';

        if ($status === 'live') {
            self::render_early_close_section($id, $total, $claimed, $view_url);
        } elseif ($status === 'ready_to_draw') {
            self::render_draw_section($id, $view_url);
        } elseif ($status === 'winner_selected') {
            self::render_winner_section($id, $view_url);
        }

        self::render_entries_section($id);
        self::render_orders_section($id, $view_url);

        echo '</div>';
    }

    private static function render_notices() {
        $draw_notice = isset($_GET['rafflelb_draw']) ? sanitize_key(wp_unslash($_GET['rafflelb_draw'])) : '';
        $messages = [
            'success'                => ['success', 'Winner selected and permanently recorded.'],
            'manual_success'         => ['success', 'Manual/external winner recorded and permanently audited.'],
            'early_closed'           => ['success', 'Raffle closed early. New entries are now blocked and the current paid-entry pool is frozen for winner selection.'],
            'early_reason_required'  => ['error', 'Closing a raffle early requires an admin reason.'],
            'early_not_available'    => ['error', 'This raffle cannot be closed early right now.'],
            'already_selected'       => ['warning', 'A winner has already been selected for this raffle.'],
            'manual_reason_required' => ['error', 'Manual winner selection requires a reason or external draw reference.'],
            'manual_entry_invalid'   => ['error', 'The chosen entry is not an active paid entry for this raffle.'],
            'not_ready'              => ['error', 'This raffle is not Ready to Draw.'],
            'pool_mismatch'          => ['error', 'Winner selection was blocked because the eligible paid-entry pool is inconsistent.'],
            'rng_error'              => ['error', 'Secure random selection could not be completed. No winner was recorded.'],
            'save_error'             => ['error', 'The result could not be saved. No winner was recorded.'],
            'invalid'                => ['error', 'Invalid raffle.'],
            'email_sent'             => ['success', 'Winner email sent successfully.'],
            'email_failed'           => ['error', 'Winner email could not be sent.'],
            'email_result_missing'   => ['error', 'Winner result could not be found.'],
            'fulfillment_saved'      => ['success', 'Prize fulfillment status saved.'],
            'fulfillment_missing'    => ['error', 'Prize fulfillment record could not be found.'],
        ];
        if ($draw_notice && isset($messages[$draw_notice])) {
            [$type, $text] = $messages[$draw_notice];
            echo '<div class="notice notice-' . esc_attr($type) . ' is-dismissible"><p>' . esc_html($text) . '</p></div>';
        }

        $order_notice = isset($_GET['rafflelb_order_action']) ? sanitize_key(wp_unslash($_GET['rafflelb_order_action'])) : '';
        $order_messages = [
            'success'            => ['success', 'Entry order updated.'],
            'invalid'            => ['error', 'Invalid order action.'],
            'reason_required'    => ['error', 'A cancellation/refund reason is required.'],
            'order_missing'      => ['error', 'The WooCommerce order could not be found.'],
            'no_active_entries'  => ['warning', 'This order has no active raffle entries to cancel or refund.'],
            'winner_locked'      => ['error', 'Action blocked: this order is attached to a raffle with a selected winner.'],
        ];
        if ($order_notice && isset($order_messages[$order_notice])) {
            [$type, $text] = $order_messages[$order_notice];
            echo '<div class="notice notice-' . esc_attr($type) . ' is-dismissible"><p>' . esc_html($text) . '</p></div>';
        }
    }

    private static function render_early_close_section($id, $total, $claimed, $view_url) {
        if ($total < 1 || $claimed < 1 || $claimed >= $total) return;

        echo '<div class="rlbrm-card rlbrm-border-amber">';
        echo '<h2>Close Raffle Early</h2>';
        echo '<p class="description">Closing early immediately blocks new entries and freezes the current paid-entry pool for winner selection. A reason is required and recorded in the audit trail.</p>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" onsubmit="return confirm(\'Close this raffle early now? New entries will be blocked immediately.\');">';
        echo '<input type="hidden" name="action" value="rafflelb_close_raffle_early">';
        echo '<input type="hidden" name="product_id" value="' . esc_attr($id) . '">';
        echo '<input type="hidden" name="rafflelb_redirect_to" value="' . esc_attr($view_url) . '">';
        wp_nonce_field('rafflelb_close_raffle_early_' . $id);
        echo '<textarea name="close_reason" rows="2" required placeholder="Required reason for early closure" class="large-text"></textarea>';
        echo '<p><button type="submit" class="button">Close Raffle Early</button></p>';
        echo '</form></div>';
    }

    private static function render_draw_section($id, $view_url) {
        global $wpdb;
        $eligible = $wpdb->get_results($wpdb->prepare(
            "SELECT id, entry_number, order_id, user_id FROM {$wpdb->prefix}" . self::ENTRY_TABLE . " WHERE product_id=%d AND status='active' ORDER BY entry_number ASC",
            $id
        ));
        $early_closed = get_post_meta($id, self::META_EARLY_CLOSED, true) === 'yes';
        $early_reason = (string) get_post_meta($id, self::META_EARLY_CLOSE_REASON, true);

        echo '<div class="rlbrm-card rlbrm-border-lime">';
        echo '<h2>Ready to Draw</h2>';
        if ($early_closed && $early_reason !== '') echo '<p><small><strong>Early-close reason:</strong> ' . esc_html($early_reason) . '</small></p>';

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="margin-bottom:16px" onsubmit="return confirm(\'Run the secure random draw now? This result is permanent.\');">';
        echo '<input type="hidden" name="action" value="rafflelb_select_winner">';
        echo '<input type="hidden" name="product_id" value="' . esc_attr($id) . '">';
        echo '<input type="hidden" name="rafflelb_redirect_to" value="' . esc_attr($view_url) . '">';
        wp_nonce_field('rafflelb_select_winner_' . $id);
        echo '<button type="submit" class="button button-primary">Secure Random Draw</button>';
        echo '</form>';

        echo '<details><summary style="cursor:pointer;font-weight:600">Record Chosen Winner (manual/external)</summary>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="margin-top:12px" onsubmit="return confirm(\'Record this chosen entry as the winner? This is permanent.\');">';
        echo '<input type="hidden" name="action" value="rafflelb_choose_winner">';
        echo '<input type="hidden" name="product_id" value="' . esc_attr($id) . '">';
        echo '<input type="hidden" name="rafflelb_redirect_to" value="' . esc_attr($view_url) . '">';
        wp_nonce_field('rafflelb_choose_winner_' . $id);
        echo '<p><label><strong>Choose eligible winning entry</strong><br><select name="entry_number" required class="regular-text"><option value="">Select paid entry…</option>';
        foreach ($eligible as $row) {
            $order = wc_get_order(absint($row->order_id));
            $name = self::customer_name(absint($row->user_id), $order);
            $option = '#' . str_pad((string) absint($row->entry_number), 3, '0', STR_PAD_LEFT) . ' — ' . $name . ' — Order #' . absint($row->order_id);
            echo '<option value="' . esc_attr($row->entry_number) . '">' . esc_html($option) . '</option>';
        }
        echo '</select></label></p>';
        echo '<p><label><strong>Reason / external draw reference</strong><br><textarea name="selection_note" rows="2" required class="large-text"></textarea></label></p>';
        echo '<button type="submit" class="button">Record Chosen Winner</button>';
        echo '</form></details>';
        echo '</div>';
    }

    private static function render_winner_section($id, $view_url) {
        global $wpdb;
        $result = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}" . self::RESULT_TABLE . " WHERE product_id=%d LIMIT 1", $id));
        if (!$result) return;

        $order = wc_get_order(absint($result->order_id));
        $name = self::customer_name(absint($result->user_id), $order);
        $method_label = (!empty($result->selection_method) && $result->selection_method === 'manual') ? 'Manual / External' : 'Secure Random';
        $fulfillment_status = !empty($result->fulfillment_status) ? $result->fulfillment_status : 'pending';
        $is_fulfilled = $fulfillment_status === 'fulfilled';

        echo '<div class="rlbrm-card rlbrm-border-dark">';
        echo '<h2>Winner</h2>';
        echo '<table class="widefat striped"><tbody>';
        echo '<tr><th>Winning Entry</th><td><strong>#' . esc_html(str_pad((string) $result->entry_number, 3, '0', STR_PAD_LEFT)) . '</strong></td></tr>';
        echo '<tr><th>Customer</th><td>' . esc_html($name) . '</td></tr>';
        echo '<tr><th>Order</th><td>' . ($order ? '<a href="' . esc_url($order->get_edit_order_url()) . '">#' . esc_html($result->order_id) . '</a>' : '#' . esc_html($result->order_id)) . '</td></tr>';
        echo '<tr><th>Method</th><td>' . esc_html($method_label) . '</td></tr>';
        echo '<tr><th>Selected At</th><td>' . esc_html($result->selected_at) . '</td></tr>';
        echo '<tr><th>Audit Hash</th><td><code title="' . esc_attr($result->audit_hash) . '">' . esc_html(substr($result->audit_hash, 0, 16)) . '…</code></td></tr>';
        echo '</tbody></table>';

        echo '<h3 style="margin-top:20px">Notification</h3>';
        echo !empty($result->winner_email_sent_at)
            ? '<p><strong style="color:#4d7600">Email sent</strong> — ' . esc_html($result->winner_email_sent_at) . '</p>'
            : '<p><strong>Not sent</strong></p>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" name="action" value="rafflelb_send_winner_email">';
        echo '<input type="hidden" name="result_id" value="' . esc_attr($result->id) . '">';
        echo '<input type="hidden" name="rafflelb_redirect_to" value="' . esc_attr($view_url) . '">';
        wp_nonce_field('rafflelb_send_winner_email_' . $result->id);
        echo '<button type="submit" class="button">' . (!empty($result->winner_email_sent_at) ? 'Resend Email' : 'Send Winner Email') . '</button>';
        echo '</form>';

        echo '<h3 style="margin-top:20px">Prize Fulfillment</h3>';
        $lock_id = 'rlbrm-fulfillment-lock-' . absint($result->id);
        if ($is_fulfilled) {
            echo '<p><strong style="color:#4d7600">🔒 Prize Fulfilled</strong>';
            if (!empty($result->fulfilled_at)) echo '<br><small>Fulfilled: ' . esc_html($result->fulfilled_at) . '</small>';
            echo '</p>';
            if (!empty($result->fulfillment_note)) echo '<p><small>' . esc_html($result->fulfillment_note) . '</small></p>';
            echo '<div class="rlbrm-fulfillment-lock"><label><input type="checkbox" id="' . esc_attr($lock_id) . '" onchange="this.closest(\'.rlbrm-fulfillment-lock\').nextElementSibling.querySelectorAll(\'select,textarea,button[type=submit]\').forEach(el=>{el.disabled=!this.checked})"> Edit anyway</label></div>';
        }

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="margin-top:8px">';
        echo '<input type="hidden" name="action" value="rafflelb_update_fulfillment">';
        echo '<input type="hidden" name="result_id" value="' . esc_attr($result->id) . '">';
        echo '<input type="hidden" name="rafflelb_redirect_to" value="' . esc_attr($view_url) . '">';
        wp_nonce_field('rafflelb_update_fulfillment_' . $result->id);
        $disabled = $is_fulfilled ? ' disabled' : '';
        echo '<select name="fulfillment_status" class="regular-text"' . $disabled . '>';
        foreach (['pending' => 'Pending', 'contacted' => 'Winner Contacted', 'claimed' => 'Prize Claimed', 'fulfilled' => 'Prize Fulfilled'] as $key => $label) {
            echo '<option value="' . esc_attr($key) . '" ' . selected($fulfillment_status, $key, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select>';
        echo '<textarea name="fulfillment_note" rows="2" placeholder="Admin fulfillment note" class="large-text" style="margin-top:6px"' . $disabled . '>' . esc_textarea((string) $result->fulfillment_note) . '</textarea>';
        echo '<p><button type="submit" class="button button-secondary"' . $disabled . '>Save Status</button></p>';
        if (!$is_fulfilled) {
            if (!empty($result->contacted_at)) echo '<p><small>Contacted: ' . esc_html($result->contacted_at) . '</small></p>';
            if (!empty($result->claimed_at)) echo '<p><small>Claimed: ' . esc_html($result->claimed_at) . '</small></p>';
        }
        echo '</form>';
        echo '</div>';
    }

    private static function render_entries_section($id) {
        global $wpdb;
        $search = isset($_GET['rme_s']) ? sanitize_text_field(wp_unslash($_GET['rme_s'])) : '';
        $paged = max(1, isset($_GET['rme_paged']) ? absint($_GET['rme_paged']) : 1);
        $per_page = 20;

        $all = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}" . self::ENTRY_TABLE . " WHERE product_id=%d ORDER BY entry_number ASC",
            $id
        ));

        $rows = [];
        foreach ($all as $entry) {
            $order = wc_get_order(absint($entry->order_id));
            $name = self::customer_name(absint($entry->user_id), $order);
            if ($search !== '') {
                $haystack = strtolower($name . ' #' . $entry->order_id . ' #' . str_pad((string) $entry->entry_number, 3, '0', STR_PAD_LEFT));
                if (strpos($haystack, strtolower($search)) === false) continue;
            }
            $rows[] = ['entry' => $entry, 'name' => $name, 'order' => $order];
        }

        $total = count($rows);
        $total_pages = max(1, (int) ceil($total / $per_page));
        $paged = min($paged, $total_pages);
        $page_rows = array_slice($rows, ($paged - 1) * $per_page, $per_page);

        $base_url = add_query_arg(['page' => self::SLUG, 'action' => 'view', 'id' => $id], admin_url('admin.php'));

        echo '<div class="rlbrm-card">';
        echo '<h2>Entries (' . esc_html(count($all)) . ')</h2>';
        echo '<form method="get" style="display:flex;gap:8px;margin-bottom:12px">';
        echo '<input type="hidden" name="page" value="' . esc_attr(self::SLUG) . '"><input type="hidden" name="action" value="view"><input type="hidden" name="id" value="' . esc_attr($id) . '">';
        echo '<input type="search" name="rme_s" value="' . esc_attr($search) . '" placeholder="Search buyer or order #">';
        echo '<button type="submit" class="button">Search</button>';
        if ($search !== '') echo '<a class="button" href="' . esc_url($base_url) . '">Clear</a>';
        echo '</form>';

        if (!$page_rows) {
            echo '<p>No entries' . ($search !== '' ? ' match this search.' : ' yet.') . '</p>';
        } else {
            echo '<table class="widefat striped"><thead><tr><th>Entry #</th><th>Buyer</th><th>Order</th><th>Status</th><th>Date</th></tr></thead><tbody>';
            foreach ($page_rows as $r) {
                $entry = $r['entry'];
                $status_label = $entry->status === 'active' ? 'Confirmed' : 'Void';
                $status_color = $entry->status === 'active' ? '#3c6e00' : '#8a2a1c';
                echo '<tr>';
                echo '<td>#' . esc_html(str_pad((string) $entry->entry_number, 3, '0', STR_PAD_LEFT)) . '</td>';
                echo '<td>' . esc_html($r['name']) . '</td>';
                echo '<td>' . ($r['order'] ? '<a href="' . esc_url($r['order']->get_edit_order_url()) . '">#' . esc_html($entry->order_id) . '</a>' : '#' . esc_html($entry->order_id)) . '</td>';
                echo '<td><strong style="color:' . esc_attr($status_color) . '">' . esc_html($status_label) . '</strong></td>';
                echo '<td>' . esc_html($entry->created_at) . '</td>';
                echo '</tr>';
            }
            echo '</tbody></table>';

            if ($total_pages > 1) {
                echo '<div class="tablenav"><div class="tablenav-pages">';
                for ($p = 1; $p <= $total_pages; $p++) {
                    $url = add_query_arg(array_filter(['rme_s' => $search, 'rme_paged' => $p]), $base_url);
                    echo $p === $paged
                        ? '<strong style="padding:4px 9px;border:1px solid #111;border-radius:4px;margin-right:4px">' . esc_html($p) . '</strong>'
                        : '<a class="button" style="margin-right:4px" href="' . esc_url($url) . '">' . esc_html($p) . '</a>';
                }
                echo '</div></div>';
            }
        }
        echo '</div>';
    }

    private static function render_orders_section($id, $view_url) {
        global $wpdb;
        $order_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT order_id FROM {$wpdb->prefix}" . self::ENTRY_TABLE . " WHERE product_id=%d AND status='active' AND order_id>0 ORDER BY order_id DESC",
            $id
        ));
        if (!$order_ids) return;

        $winner_locked = self::draw_status($id) === 'winner_selected';

        echo '<div class="rlbrm-card rlbrm-border-lime">';
        echo '<h2>Orders</h2>';
        echo '<p class="description">Cancelling or refunding an order here voids its active entries for this raffle (RaffleLB VOID protection). A reason is required.</p>';
        echo '<table class="widefat striped"><thead><tr><th>Order</th><th>Customer</th><th>Entries</th><th>Order Status</th><th>Total</th><th>Action</th></tr></thead><tbody>';

        foreach ($order_ids as $order_id) {
            $order = wc_get_order(absint($order_id));
            if (!$order) continue;

            $summary = $wpdb->get_row($wpdb->prepare(
                "SELECT MIN(entry_number) first_entry, MAX(entry_number) last_entry, COUNT(*) qty FROM {$wpdb->prefix}" . self::ENTRY_TABLE . " WHERE product_id=%d AND order_id=%d AND status='active'",
                $id, $order_id
            ));
            $qty = $summary ? absint($summary->qty) : 0;
            $first = '#' . str_pad((string) absint($summary->first_entry ?? 0), 3, '0', STR_PAD_LEFT);
            $last = '#' . str_pad((string) absint($summary->last_entry ?? 0), 3, '0', STR_PAD_LEFT);
            $range = $qty > 1 ? ($first . '–' . $last) : $first;
            $name = self::customer_name($order->get_user_id(), $order);

            echo '<tr>';
            echo '<td><a href="' . esc_url($order->get_edit_order_url()) . '">#' . esc_html($order_id) . '</a></td>';
            echo '<td>' . esc_html($name) . '</td>';
            echo '<td>' . esc_html($qty) . ' (' . esc_html($range) . ')</td>';
            echo '<td>' . esc_html(wc_get_order_status_name($order->get_status())) . '</td>';
            echo '<td>' . wp_kses_post($order->get_formatted_order_total()) . '</td>';
            echo '<td style="min-width:280px">';
            if ($winner_locked) {
                echo '<strong style="color:#b42318">Locked — winner selected</strong>';
            } elseif ($order->has_status(['cancelled', 'refunded', 'failed'])) {
                echo '<em>No action available.</em>';
            } else {
                echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
                echo '<input type="hidden" name="action" value="rafflelb_admin_order_action">';
                echo '<input type="hidden" name="order_id" value="' . esc_attr($order_id) . '">';
                echo '<input type="hidden" name="rafflelb_redirect_to" value="' . esc_attr($view_url) . '">';
                wp_nonce_field('rafflelb_admin_order_action_' . $order_id);
                echo '<select name="raffle_order_action" required style="width:100%;max-width:260px;margin-bottom:6px"><option value="">Choose action…</option><option value="cancel">Cancel Entry Order</option><option value="refund">Mark Order Refunded</option></select>';
                echo '<textarea name="reason" rows="2" required placeholder="Required reason" style="display:block;width:100%;max-width:260px;margin-bottom:6px"></textarea>';
                echo '<button type="submit" class="button button-secondary" onclick="return confirm(\'Apply this order action? Active entries will be voided.\');">Apply Action</button>';
                echo '</form>';
            }
            echo '</td></tr>';
        }
        echo '</tbody></table></div>';
    }

    /* ---------------------------------------------------------------
     * Delete
     * ------------------------------------------------------------- */

    public static function handle_trash() {
        if (!current_user_can('manage_woocommerce')) {
            wp_die('You are not allowed to delete raffles.');
        }
        $id = isset($_POST['raffle_id']) ? absint($_POST['raffle_id']) : 0;
        if (!$id) {
            wp_safe_redirect(admin_url('admin.php?page=' . self::SLUG));
            exit;
        }
        check_admin_referer('rafflelb_rm_trash_' . $id);
        wp_trash_post($id);
        wp_safe_redirect(admin_url('admin.php?page=' . self::SLUG . '&rlbrm=trashed'));
        exit;
    }

    /* ---------------------------------------------------------------
     * Reset / Cleanup (danger zone: wipe completed-draw test data)
     * ------------------------------------------------------------- */

    public static function render_reset_page() {
        if (!current_user_can('manage_woocommerce')) return;

        global $wpdb;
        $results_table = $wpdb->prefix . self::RESULT_TABLE;
        $results = $wpdb->get_results("SELECT * FROM {$results_table} ORDER BY selected_at DESC, id DESC");

        echo '<div class="wrap rafflelb-rm">';
        self::print_styles();
        echo '<h1>Reset / Cleanup</h1>';

        $notice = isset($_GET['rlbrm_reset']) ? sanitize_key(wp_unslash($_GET['rlbrm_reset'])) : '';
        if ($notice === 'done') {
            $count = isset($_GET['rlbrm_count']) ? absint($_GET['rlbrm_count']) : 0;
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($count) . ' raffle' . ($count === 1 ? '' : 's') . ' reset. Entries, winner record, and related notifications were permanently deleted; the product' . ($count === 1 ? ' is' : 's are') . ' back to a fresh, unentered raffle.</p></div>';
        } elseif ($notice === 'confirm_mismatch') {
            echo '<div class="notice notice-error is-dismissible"><p>Nothing was reset: the confirmation checkbox must be checked and the typed confirmation text must exactly match.</p></div>';
        } elseif ($notice === 'none_selected') {
            echo '<div class="notice notice-error is-dismissible"><p>Nothing was reset: no raffles were selected.</p></div>';
        }

        $order_notice = isset($_GET['rlbrm_orders']) ? sanitize_key(wp_unslash($_GET['rlbrm_orders'])) : '';
        if ($order_notice === 'done') {
            $orders_deleted = isset($_GET['rlbrm_orders_deleted']) ? absint($_GET['rlbrm_orders_deleted']) : 0;
            $entries_deleted = isset($_GET['rlbrm_entries_deleted']) ? absint($_GET['rlbrm_entries_deleted']) : 0;
            $history_deleted = isset($_GET['rlbrm_history_deleted']) ? absint($_GET['rlbrm_history_deleted']) : 0;
            $results_deleted = isset($_GET['rlbrm_results_deleted']) ? absint($_GET['rlbrm_results_deleted']) : 0;
            echo '<div class="notice notice-success is-dismissible"><p><strong>Test-order cleanup complete.</strong> Permanently deleted ' . esc_html($orders_deleted) . ' WooCommerce order' . ($orders_deleted === 1 ? '' : 's') . ', ' . esc_html($entries_deleted) . ' raffle entr' . ($entries_deleted === 1 ? 'y' : 'ies') . ', ' . esc_html($history_deleted) . ' entry-history row' . ($history_deleted === 1 ? '' : 's') . ', and ' . esc_html($results_deleted) . ' draw-result row' . ($results_deleted === 1 ? '' : 's') . '.</p></div>';
        } elseif ($order_notice === 'confirm_mismatch') {
            echo '<div class="notice notice-error is-dismissible"><p>No orders were deleted: the confirmation checkbox must be checked and the typed confirmation text must exactly match.</p></div>';
        } elseif ($order_notice === 'failed') {
            echo '<div class="notice notice-error is-dismissible"><p>The test-order cleanup could not be completed. No additional cleanup should be attempted until the WooCommerce order system is available.</p></div>';
        }

        echo '<div class="rlbrm-card"><p class="description">This tool is for clearing out test raffles. Selecting a raffle below and confirming will permanently delete its winner record, every paid entry, its entry history, and any related notifications, then reset the product back to a fresh, unentered raffle (full stock, no winner, no early-close flags). WooCommerce orders themselves are never deleted or modified by the raffle-reset tool below.</p></div>';

        // Separate whole-site test-order cleanup. Kept on this page so all destructive
        // maintenance tools live in one clearly marked place.
        $order_count = 0;
        if (function_exists('wc_get_orders')) {
            $count_query = wc_get_orders([
                'limit'    => 1,
                'paginate' => true,
                'return'   => 'ids',
                'type'     => 'shop_order',
                'status'   => array_keys(wc_get_order_statuses()),
            ]);
            if (is_object($count_query) && isset($count_query->total)) {
                $order_count = absint($count_query->total);
            }
        }

        echo '<div class="rlbrm-card rlbrm-border-amber">';
        echo '<h2 style="color:#b42318">&#9888; Danger Zone — Delete All Test Orders &amp; Related Entries</h2>';
        echo '<p><strong>Current WooCommerce orders: ' . esc_html($order_count) . '</strong></p>';
        echo '<p class="description"><strong>This is a whole-site test reset and cannot be undone.</strong> It permanently deletes every WooCommerce customer order, removes RaffleLB paid entries and entry-history rows tied to those orders, removes draw results whose winning order is being deleted, clears the affected winner/draw flags, and recalculates the affected raffle stock. WooCommerce stock reduced by deleted test orders is restored before each order is removed. It does <em>not</em> delete products, customers, coupons, points, or general notifications.</p>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" name="action" value="rafflelb_rm_delete_test_orders">';
        wp_nonce_field('rafflelb_rm_delete_test_orders');
        echo '<p><label><input type="checkbox" name="confirm_delete_orders" value="1" required> I understand this permanently deletes <strong>ALL WooCommerce orders for ALL users</strong> and their related RaffleLB entry data.</label></p>';
        echo '<p><label>Type <code>DELETE ALL TEST ORDERS</code> to confirm:<br><input type="text" name="confirm_orders_text" class="regular-text" autocomplete="off" required></label></p>';
        echo '<p><button type="submit" class="button" style="background:#b42318;border-color:#b42318;color:#fff" onclick="return confirm(\'Permanently delete ALL WooCommerce orders and their related RaffleLB entries? This cannot be undone.\');">Delete All Test Orders &amp; Related Entries</button></p>';
        echo '</form>';
        echo '</div>';

        if (!$results) {
            echo '<div class="rlbrm-card"><p>No completed draws to reset.</p></div></div>';
            return;
        }

        echo '<div class="rlbrm-card rlbrm-border-dark">';
        echo '<h2 style="color:#b42318">&#9888; Danger Zone — Reset Completed Raffles</h2>';
        echo '<p class="description"><strong>This cannot be undone.</strong> Double-check the list below before confirming.</p>';

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" name="action" value="rafflelb_rm_reset_winners">';
        wp_nonce_field('rafflelb_rm_reset_winners');

        echo '<table class="widefat striped" style="margin-bottom:16px"><thead><tr><th style="width:32px"><input type="checkbox" id="rlbrm-select-all"></th><th>Raffle</th><th>Winner</th><th>Entries</th><th>Selected At</th><th>Fulfillment</th></tr></thead><tbody>';
        foreach ($results as $result) {
            $pid = absint($result->product_id);
            $order = wc_get_order(absint($result->order_id));
            $winner_name = self::customer_name(absint($result->user_id), $order);
            $entry_count = self::claimed($pid);
            $fulfillment = !empty($result->fulfillment_status) ? $result->fulfillment_status : 'pending';

            echo '<tr>';
            echo '<td><input type="checkbox" name="reset_product_ids[]" value="' . esc_attr($pid) . '" class="rlbrm-reset-checkbox"></td>';
            echo '<td><strong>' . esc_html(get_the_title($pid)) . '</strong></td>';
            echo '<td>' . esc_html($winner_name) . '</td>';
            echo '<td>' . esc_html($entry_count) . '</td>';
            echo '<td>' . esc_html($result->selected_at) . '</td>';
            echo '<td>' . self::fulfillment_badge($fulfillment) . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';

        echo '<p><label><input type="checkbox" name="confirm_understand" value="1" required> I understand this permanently deletes all entries, the winner record, and related notifications for the selected raffle(s), and cannot be undone.</label></p>';
        echo '<p><label>Type <code>RESET</code> to confirm:<br><input type="text" name="confirm_text" class="regular-text" autocomplete="off" required></label></p>';
        echo '<p><button type="submit" class="button" style="background:#b42318;border-color:#b42318;color:#fff" onclick="return confirm(\'This permanently deletes the selected raffle data and cannot be undone. Continue?\');">Reset Selected Raffles</button></p>';
        echo '</form>';
        echo '</div>';

        ?>
        <script>
        document.getElementById('rlbrm-select-all').addEventListener('change', function () {
            var checked = this.checked;
            document.querySelectorAll('.rlbrm-reset-checkbox').forEach(function (cb) { cb.checked = checked; });
        });
        </script>
        <?php

        echo '</div>';
    }

    public static function handle_delete_test_orders() {
        if (!current_user_can('manage_woocommerce')) {
            wp_die('You are not allowed to delete test orders.');
        }
        check_admin_referer('rafflelb_rm_delete_test_orders');

        $redirect = admin_url('admin.php?page=' . self::SLUG_RESET);
        $confirmed = !empty($_POST['confirm_delete_orders']);
        $confirm_text = isset($_POST['confirm_orders_text']) ? trim(wp_unslash($_POST['confirm_orders_text'])) : '';

        if (!$confirmed || $confirm_text !== 'DELETE ALL TEST ORDERS') {
            wp_safe_redirect(add_query_arg('rlbrm_orders', 'confirm_mismatch', $redirect));
            exit;
        }

        if (!function_exists('wc_get_orders') || !function_exists('wc_get_order')) {
            wp_safe_redirect(add_query_arg('rlbrm_orders', 'failed', $redirect));
            exit;
        }

        global $wpdb;

        $entries_table = $wpdb->prefix . self::ENTRY_TABLE;
        $history_table = $wpdb->prefix . self::HISTORY_TABLE;
        $results_table = $wpdb->prefix . self::RESULT_TABLE;
        $notifications_table = $wpdb->prefix . 'rafflelb_notifications';

        $orders_deleted = 0;
        $entries_deleted = 0;
        $history_deleted = 0;
        $results_deleted = 0;
        $affected_products = [];
        $winner_products = [];
        $failed_order_ids = [];
        $notifications_exist = ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $notifications_table)) === $notifications_table);

        // Always query from the first page. Deleting each batch shrinks the result
        // set, so this remains safe for both legacy CPT storage and WooCommerce HPOS.
        do {
            $query_args = [
                'limit'   => 100,
                'return'  => 'ids',
                'type'    => 'shop_order',
                'status'  => array_keys(wc_get_order_statuses()),
                'orderby' => 'ID',
                'order'   => 'ASC',
            ];
            if ($failed_order_ids) {
                $query_args['exclude'] = array_values(array_unique($failed_order_ids));
            }
            $order_ids = wc_get_orders($query_args);

            $order_ids = array_values(array_unique(array_filter(array_map('absint', (array) $order_ids))));
            if (!$order_ids) break;

            foreach ($order_ids as $order_id) {
                // Capture every raffle product touched by this order before deleting
                // the order-specific data, so stock and draw flags can be repaired.
                $entry_product_ids = $wpdb->get_col($wpdb->prepare(
                    "SELECT DISTINCT product_id FROM {$entries_table} WHERE order_id=%d",
                    $order_id
                ));
                foreach ((array) $entry_product_ids as $pid) {
                    $pid = absint($pid);
                    if ($pid) $affected_products[$pid] = true;
                }

                $result_product_ids = $wpdb->get_col($wpdb->prepare(
                    "SELECT DISTINCT product_id FROM {$results_table} WHERE order_id=%d",
                    $order_id
                ));
                foreach ((array) $result_product_ids as $pid) {
                    $pid = absint($pid);
                    if ($pid) {
                        $affected_products[$pid] = true;
                        $winner_products[$pid] = true;
                    }
                }

                $deleted = $wpdb->query($wpdb->prepare(
                    "DELETE FROM {$entries_table} WHERE order_id=%d",
                    $order_id
                ));
                if ($deleted !== false) $entries_deleted += absint($deleted);

                $deleted = $wpdb->query($wpdb->prepare(
                    "DELETE FROM {$history_table} WHERE order_id=%d",
                    $order_id
                ));
                if ($deleted !== false) $history_deleted += absint($deleted);

                $deleted = $wpdb->query($wpdb->prepare(
                    "DELETE FROM {$results_table} WHERE order_id=%d",
                    $order_id
                ));
                if ($deleted !== false) $results_deleted += absint($deleted);

                $order = wc_get_order($order_id);
                if ($order) {
                    // Restore normal WooCommerce stock first (including direct-purchase
                    // test orders) before permanently removing the order record.
                    if (function_exists('wc_maybe_increase_stock_levels')) {
                        wc_maybe_increase_stock_levels($order_id);
                    }

                    $deleted_order = $order->delete(true);
                    if ($deleted_order !== false && !wc_get_order($order_id)) {
                        $orders_deleted++;
                    } else {
                        // Do not let a single storage/plugin failure trap this cleanup
                        // in an endless first-page loop. Leave the failed order intact.
                        $failed_order_ids[] = $order_id;
                    }
                } else {
                    $failed_order_ids[] = $order_id;
                }
            }
        } while (true);

        // Repair raffle state after the linked orders/entries have gone. Remaining
        // active entries (if any non-order data exists) are respected.
        foreach (array_keys($affected_products) as $pid) {
            $pid = absint($pid);
            if (!$pid) continue;

            // Removing the test orders/entries makes this raffle live/fresh again,
            // even if it had only reached ready-to-draw or was closed early without
            // a permanent winner result yet.
            delete_post_meta($pid, self::META_DRAW_STATUS);
            delete_post_meta($pid, self::META_CLOSED_AT);
            delete_post_meta($pid, self::META_WINNER_ENTRY_ID);
            delete_post_meta($pid, self::META_WINNER_SELECTED_AT);
            delete_post_meta($pid, self::META_EARLY_CLOSED);
            delete_post_meta($pid, self::META_EARLY_CLOSE_REASON);
            delete_post_meta($pid, self::META_EARLY_CLOSED_BY);
            delete_post_meta($pid, self::META_EARLY_CLOSE_CLAIMED);

            if (!empty($winner_products[$pid]) && $notifications_exist) {
                // Winner/draw-complete notifications are no longer valid once that
                // test draw result is removed. General/manual notifications stay.
                $wpdb->query($wpdb->prepare(
                    "DELETE FROM {$notifications_table} WHERE product_id=%d AND type IN ('winner','draw_completed')",
                    $pid
                ));
            }

            $total = absint(get_post_meta($pid, self::META_TOTAL, true));
            if ($total > 0) {
                $active = (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$entries_table} WHERE product_id=%d AND status='active'",
                    $pid
                ));
                $remaining = max(0, $total - $active);

                $product = wc_get_product($pid);
                if ($product && $product->managing_stock()) {
                    $product->set_stock_quantity($remaining);
                    $product->set_stock_status($remaining > 0 ? 'instock' : 'outofstock');
                    $product->save();
                }
            }
        }

        if (function_exists('wc_delete_shop_order_transients')) {
            wc_delete_shop_order_transients();
        }

        wp_safe_redirect(add_query_arg([
            'rlbrm_orders'          => 'done',
            'rlbrm_orders_deleted'  => $orders_deleted,
            'rlbrm_entries_deleted' => $entries_deleted,
            'rlbrm_history_deleted' => $history_deleted,
            'rlbrm_results_deleted' => $results_deleted,
        ], $redirect));
        exit;
    }

    public static function handle_reset_winners() {
        if (!current_user_can('manage_woocommerce')) {
            wp_die('You are not allowed to reset raffles.');
        }
        check_admin_referer('rafflelb_rm_reset_winners');

        $redirect = admin_url('admin.php?page=' . self::SLUG_RESET);

        $confirm_understand = !empty($_POST['confirm_understand']);
        $confirm_text = isset($_POST['confirm_text']) ? trim(wp_unslash($_POST['confirm_text'])) : '';
        if (!$confirm_understand || $confirm_text !== 'RESET') {
            wp_safe_redirect(add_query_arg('rlbrm_reset', 'confirm_mismatch', $redirect));
            exit;
        }

        $product_ids = isset($_POST['reset_product_ids']) ? array_map('absint', (array) wp_unslash($_POST['reset_product_ids'])) : [];
        $product_ids = array_values(array_unique(array_filter($product_ids)));
        if (!$product_ids) {
            wp_safe_redirect(add_query_arg('rlbrm_reset', 'none_selected', $redirect));
            exit;
        }

        global $wpdb;
        $results_table = $wpdb->prefix . self::RESULT_TABLE;
        $entries_table = $wpdb->prefix . self::ENTRY_TABLE;
        $history_table = $wpdb->prefix . self::HISTORY_TABLE;
        $holds_table = $wpdb->prefix . self::HOLD_TABLE;
        $notifications_table = $wpdb->prefix . 'rafflelb_notifications';
        $notifications_exist = class_exists('RaffleLB_Notifications');

        $reset_count = 0;
        foreach ($product_ids as $pid) {
            // Only touch raffles that actually have a permanent draw result -
            // this tool resets completed draws, not merely sold-out ones.
            $has_result = (bool) $wpdb->get_var($wpdb->prepare("SELECT id FROM {$results_table} WHERE product_id=%d LIMIT 1", $pid));
            if (!$has_result) continue;

            $wpdb->delete($results_table, ['product_id' => $pid], ['%d']);
            $wpdb->delete($entries_table, ['product_id' => $pid], ['%d']);
            $wpdb->delete($history_table, ['product_id' => $pid], ['%d']);
            $wpdb->delete($holds_table, ['product_id' => $pid], ['%d']);

            if ($notifications_exist) {
                $wpdb->query($wpdb->prepare(
                    "DELETE FROM {$notifications_table} WHERE product_id=%d AND type IN ('winner','draw_completed')",
                    $pid
                ));
            }

            delete_post_meta($pid, self::META_DRAW_STATUS);
            delete_post_meta($pid, self::META_CLOSED_AT);
            delete_post_meta($pid, self::META_WINNER_ENTRY_ID);
            delete_post_meta($pid, self::META_WINNER_SELECTED_AT);
            delete_post_meta($pid, self::META_EARLY_CLOSED);
            delete_post_meta($pid, self::META_EARLY_CLOSE_REASON);
            delete_post_meta($pid, self::META_EARLY_CLOSED_BY);
            delete_post_meta($pid, self::META_EARLY_CLOSE_CLAIMED);

            $product = wc_get_product($pid);
            if ($product) {
                $total = absint(get_post_meta($pid, self::META_TOTAL, true));
                $product->set_stock_quantity($total);
                $product->set_stock_status($total > 0 ? 'instock' : 'outofstock');
                $product->save();
            }

            $reset_count++;
        }

        wp_safe_redirect(add_query_arg(['rlbrm_reset' => 'done', 'rlbrm_count' => $reset_count], $redirect));
        exit;
    }
}

RaffleLB_Raffle_Manager::init();
