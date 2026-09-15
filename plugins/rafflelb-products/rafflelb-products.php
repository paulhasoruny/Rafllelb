<?php
/** Plugin Name: RaffleLB Products
 * Description: Product Studio for WooCommerce and RaffleLB operations.
 * Version: 0.2.15
 * Author: RaffleLB
 * Requires Plugins: woocommerce */
defined('ABSPATH') || exit;

final class RaffleLB_Products {
    const VERSION = '0.2.15';
    const SLUG = 'rafflelb-products';
    const CAPABILITY = 'manage_woocommerce';
    const ENABLED = '_rafflelb_draw_enabled';
    const TOTAL = '_rafflelb_total_entries';
    const BUY = '_rafflelb_buy_now_enabled';
    const RETAIL = '_rafflelb_buy_now_price';
    const ITEM = '_rafflelb_item_type';
    const TAG_TAXONOMY = 'product_tag';
    const TAG_NONCE = 'rafflelb_products_tags';
    const BRAND_NONCE = 'rafflelb_products_brands';

    public static function init() {
        add_action('admin_menu', [__CLASS__, 'menu'], 20);
        add_action('admin_enqueue_scripts', [__CLASS__, 'assets']);
        add_action('admin_post_rafflelb_products_save', [__CLASS__, 'save']);
        add_action('admin_post_rafflelb_products_publish_state', [__CLASS__, 'publish_state']);
        add_action('wp_ajax_rafflelb_products_tag_search', [__CLASS__, 'ajax_tag_search']);
        add_action('wp_ajax_rafflelb_products_brand_search', [__CLASS__, 'ajax_brand_search']);
        add_action('wp_ajax_rafflelb_products_brand_create', [__CLASS__, 'ajax_brand_create']);
        add_action('init', [__CLASS__, 'ensure_brand_taxonomy'], 99);
        add_filter('posts_clauses', [__CLASS__, 'search'], 10, 2);
    }

    public static function menu() {
        add_submenu_page(null, 'Product Studio', 'Product Studio', self::CAPABILITY, self::SLUG, [__CLASS__, 'render']);
    }

    public static function assets($hook) {
        if ($hook !== 'admin_page_' . self::SLUG) return;
        wp_enqueue_style('rafflelb-products', plugin_dir_url(__FILE__) . 'assets/product-studio.css', [], self::VERSION);
        wp_enqueue_media();
        /* Product Tags reuses whichever multi-select enhancer WooCommerce
         * already registers admin-wide rather than adding a second one.
         * Modern WooCommerce ships SelectWoo (a Select2 fork); prefer it and
         * fall back to a bare Select2 registration for older WooCommerce
         * builds. If neither is available the field still works as a plain
         * native multi-select (existing tags can be picked/removed; only the
         * type-to-create convenience is lost). */
        if (wp_script_is('selectWoo', 'registered')) {
            wp_enqueue_script('selectWoo');
        } elseif (wp_script_is('select2', 'registered')) {
            wp_enqueue_script('select2');
        }
        if (wp_style_is('selectWoo', 'registered')) {
            wp_enqueue_style('selectWoo');
        } elseif (wp_style_is('select2', 'registered')) {
            wp_enqueue_style('select2');
        }
    }

    private static function url($a = []) { return add_query_arg(array_merge(['page' => self::SLUG], $a), admin_url('admin.php')); }
    private static function get($k) { return isset($_GET[$k]) ? wp_unslash($_GET[$k]) : ''; }
    private static function ok() { return current_user_can(self::CAPABILITY); }

    public static function search($c, $q) {
        $s = $q->get('rafflelb_products_search');
        if (!is_string($s) || $s === '') return $c;
        global $wpdb;
        $l = '%' . $wpdb->esc_like($s) . '%';
        $c['where'] .= $wpdb->prepare(
            " AND ({$wpdb->posts}.post_title LIKE %s OR EXISTS(SELECT 1 FROM {$wpdb->postmeta} rlp_sku WHERE rlp_sku.post_id={$wpdb->posts}.ID AND rlp_sku.meta_key=%s AND rlp_sku.meta_value LIKE %s))",
            $l, '_sku', $l
        );
        return $c;
    }

    /** Native product_tag search for the Select2 field. Read-only, capability
     *  and nonce checked; returns only id/text pairs, nothing else. */
    public static function ajax_tag_search() {
        check_ajax_referer(self::TAG_NONCE, 'nonce');
        if (!self::ok()) wp_send_json_error([], 403);
        $search = isset($_GET['q']) ? sanitize_text_field(wp_unslash($_GET['q'])) : '';
        $terms = get_terms(['taxonomy' => self::TAG_TAXONOMY, 'hide_empty' => false, 'number' => 20, 'name__like' => $search]);
        $out = [];
        if (!is_wp_error($terms)) {
            foreach ($terms as $t) $out[] = ['id' => $t->term_id, 'text' => $t->name];
        }
        wp_send_json($out);
    }

    /**
     * Find the product brand taxonomy already used by WooCommerce/the site.
     * If no brand taxonomy exists, Product Studio registers a small native
     * product_brand fallback so RaffleLB has one consistent source of truth.
     */
    private static function brand_taxonomy() {
        $preferred = [
            'product_brand',
            'pa_brands',
            'pa_brand',
            'pwb-brand',
            'yith_product_brand',
            'berocket_brand',
            'brand',
        ];
        foreach ($preferred as $taxonomy) {
            if (taxonomy_exists($taxonomy) && is_object_in_taxonomy('product', $taxonomy)) return $taxonomy;
        }
        $taxonomies = get_object_taxonomies('product', 'objects');
        foreach ($taxonomies as $name => $object) {
            $label = isset($object->labels->name) ? strtolower((string) $object->labels->name) : '';
            $singular = isset($object->labels->singular_name) ? strtolower((string) $object->labels->singular_name) : '';
            if (strpos(strtolower($name), 'brand') !== false || strpos($label, 'brand') !== false || strpos($singular, 'brand') !== false) return $name;
        }
        return '';
    }

    /**
     * Fallback only: if WooCommerce or another plugin has not provided a brand
     * taxonomy, register product_brand. This keeps brands native WP terms and
     * makes them immediately usable by the RaffleLB Store's dynamic filter.
     */
    public static function ensure_brand_taxonomy() {
        if (!class_exists('WooCommerce') || self::brand_taxonomy() !== '') return;
        register_taxonomy('product_brand', ['product'], [
            'hierarchical' => false,
            'labels' => [
                'name' => 'Brands',
                'singular_name' => 'Brand',
                'search_items' => 'Search Brands',
                'all_items' => 'All Brands',
                'edit_item' => 'Edit Brand',
                'update_item' => 'Update Brand',
                'add_new_item' => 'Add New Brand',
                'new_item_name' => 'New Brand Name',
                'menu_name' => 'Brands',
            ],
            'public' => false,
            'show_ui' => true,
            'show_admin_column' => true,
            'show_in_rest' => true,
            'query_var' => true,
            'rewrite' => false,
            'capabilities' => [
                'manage_terms' => 'manage_woocommerce',
                'edit_terms' => 'manage_woocommerce',
                'delete_terms' => 'manage_woocommerce',
                'assign_terms' => 'edit_products',
            ],
        ]);
    }

    /** Create a brand directly from Product Studio and return the term id/name. */
    public static function ajax_brand_create() {
        check_ajax_referer(self::BRAND_NONCE, 'nonce');
        if (!self::ok()) wp_send_json_error(['message' => 'You do not have permission to manage brands.'], 403);

        $taxonomy = self::brand_taxonomy();
        if ($taxonomy === '') wp_send_json_error(['message' => 'Brand taxonomy is unavailable.'], 400);

        $name = isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '';
        $name = trim($name);
        if ($name === '') wp_send_json_error(['message' => 'Enter a brand name.'], 400);

        $existing = term_exists($name, $taxonomy);
        if ($existing) {
            $term_id = is_array($existing) ? (int) $existing['term_id'] : (int) $existing;
            $term = get_term($term_id, $taxonomy);
            if ($term && !is_wp_error($term)) {
                wp_send_json_success(['id' => $term->term_id, 'text' => $term->name, 'existing' => true]);
            }
        }

        $created = wp_insert_term($name, $taxonomy);
        if (is_wp_error($created)) wp_send_json_error(['message' => $created->get_error_message()], 400);

        $term = get_term((int) $created['term_id'], $taxonomy);
        if (!$term || is_wp_error($term)) wp_send_json_error(['message' => 'Brand was created but could not be loaded.'], 500);

        wp_send_json_success(['id' => $term->term_id, 'text' => $term->name, 'existing' => false]);
    }

    /** Brand search for the Product Studio SelectWoo field. */
    public static function ajax_brand_search() {
        check_ajax_referer(self::BRAND_NONCE, 'nonce');
        if (!self::ok()) wp_send_json_error([], 403);
        $taxonomy = self::brand_taxonomy();
        if ($taxonomy === '') wp_send_json([]);
        $search = isset($_GET['q']) ? sanitize_text_field(wp_unslash($_GET['q'])) : '';
        $terms = get_terms(['taxonomy' => $taxonomy, 'hide_empty' => false, 'number' => 30, 'name__like' => $search]);
        $out = [];
        if (!is_wp_error($terms)) {
            foreach ($terms as $t) $out[] = ['id' => $t->term_id, 'text' => $t->name];
        }
        wp_send_json($out);
    }

    private static function profile($p) {
        $id = $p->get_id();
        $raffle = get_post_meta($id, self::ENABLED, true) === 'yes';
        $buy = $raffle && get_post_meta($id, self::BUY, true) === 'yes' && (float) get_post_meta($id, self::RETAIL, true) > 0;
        return [
            'raffle' => $raffle,
            'buy' => $buy,
            'mode' => $raffle ? ($buy ? 'both' : 'raffle') : 'store',
            'entry' => (float) $p->get_price(),
            'retail' => $buy ? (float) get_post_meta($id, self::RETAIL, true) : (float) $p->get_price(),
        ];
    }

    private static function stats($id) {
        if (!class_exists('RaffleLB_Draw_Engine') || !method_exists('RaffleLB_Draw_Engine', 'shop_bridge_stats')) return false;
        $s = RaffleLB_Draw_Engine::shop_bridge_stats(absint($id), false);
        foreach (['total', 'claimed', 'left', 'available', 'percent', 'status'] as $k) {
            if (!is_array($s) || !array_key_exists($k, $s)) return false;
        }
        return $s;
    }

    /** Read-only operational safety check. No entry, hold, result, or status write occurs here. */
    private static function locked($id, $s = false) {
        global $wpdb;
        $e = $wpdb->prefix . 'rafflelb_entries';
        $h = $wpdb->prefix . 'rafflelb_holds';
        $r = $wpdb->prefix . 'rafflelb_draw_results';
        $entries = $s ? (int) $s['claimed'] : 0;
        if (!$entries && $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $e)) === $e) {
            $entries = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$e} WHERE product_id=%d", $id));
        }
        $holds = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $h)) === $h
            && (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$h} WHERE product_id=%d AND expires_at >= %s", $id, current_time('mysql'))) > 0;
        $result = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $r)) === $r
            && (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$r} WHERE product_id=%d", $id)) > 0;
        $status = get_post_meta($id, '_rafflelb_draw_status', true);
        return $entries || $holds || $result || in_array($status, ['ready_to_draw', 'winner_selected'], true);
    }

    private static function label($m) { return ['store' => 'STORE ONLY', 'raffle' => 'RAFFLE ONLY', 'both' => 'STORE + RAFFLE'][$m]; }
    private static function edit($id) { return self::url(['action' => 'edit', 'product_id' => absint($id)]); }

    /** Raffle Operations status pill: [label, modifier]. Purely presentational —
     *  the status string itself still comes only from Draw Engine's bridge. */
    private static function opstatus($status) {
        $map = [
            'live' => ['LIVE', 'live'],
            'ready_to_draw' => ['READY TO DRAW', 'ready'],
            'winner_selected' => ['WINNER SELECTED', 'winner'],
            'closed' => ['CLOSED', 'closed'],
            'fulfilled' => ['FULFILLED', 'fulfilled'],
        ];
        if (isset($map[$status])) return $map[$status];
        return [strtoupper(str_replace('_', ' ', $status)), 'default'];
    }

    /** Publishing pill: [label, modifier]. Reads the existing WooCommerce/post
     *  status only — no new publishing state is introduced. */
    private static function pubpill($status) {
        $s = strtolower($status);
        $labels = ['publish' => 'Published', 'draft' => 'Draft', 'private' => 'Private'];
        $mod = isset($labels[$s]) ? $s : 'default';
        return [$labels[$s] ?? ucfirst($status), $mod];
    }

    public static function render() {
        if (!self::ok()) wp_die('You do not have permission to manage products.', '', ['response' => 403]);
        if (!class_exists('WooCommerce')) {
            echo '<div class="wrap rafflelb-products"><div class="rlp-empty"><h1>Product Studio</h1><p>WooCommerce must be active.</p></div></div>';
            return;
        }
        $a = sanitize_key(self::get('action'));
        if ($a === 'view') { wp_safe_redirect(self::edit(absint(self::get('product_id')))); exit; }
        if ($a === 'edit' || $a === 'new') { self::editor($a === 'new' ? 0 : absint(self::get('product_id'))); return; }
        self::listing();
    }

    private static function query($m) {
        if ($m === 'both') return ['relation' => 'AND', ['key' => self::ENABLED, 'value' => 'yes'], ['key' => self::BUY, 'value' => 'yes'], ['key' => self::RETAIL, 'value' => 0, 'compare' => '>', 'type' => 'NUMERIC']];
        if ($m === 'raffle') return ['relation' => 'AND', ['key' => self::ENABLED, 'value' => 'yes'], ['relation' => 'OR', ['key' => self::BUY, 'compare' => 'NOT EXISTS'], ['key' => self::BUY, 'value' => 'yes', 'compare' => '!='], ['key' => self::RETAIL, 'compare' => 'NOT EXISTS'], ['key' => self::RETAIL, 'value' => 0, 'compare' => '<=', 'type' => 'NUMERIC']]];
        if ($m === 'store') return ['relation' => 'OR', ['key' => self::ENABLED, 'value' => 'yes', 'compare' => '!='], ['key' => self::ENABLED, 'compare' => 'NOT EXISTS']];
        return [];
    }

    private static function counts($s) {
        $base = ['post_type' => 'product', 'post_status' => ['publish', 'draft', 'private'], 'posts_per_page' => 1, 'fields' => 'ids'];
        if ($s !== '') $base['rafflelb_products_search'] = $s;
        $out = [];
        foreach (['all', 'store', 'raffle', 'both', 'stock'] as $mode) {
            $a = $base;
            if (in_array($mode, ['store', 'raffle', 'both'], true)) $a['meta_query'] = self::query($mode);
            if ($mode === 'stock') $a['meta_query'] = [['key' => '_stock_status', 'value' => 'outofstock']];
            $out[$mode] = (int) (new WP_Query($a))->found_posts;
        }
        return $out;
    }

    private static function listing() {
        $f = sanitize_key(self::get('filter'));
        if (!in_array($f, ['all', 'store', 'raffle', 'both', 'stock'], true)) $f = 'all';
        $s = sanitize_text_field(self::get('s'));
        $args = ['post_type' => 'product', 'post_status' => ['publish', 'draft', 'private'], 'posts_per_page' => 25, 'paged' => max(1, absint(self::get('paged'))), 'orderby' => 'date', 'order' => 'DESC'];
        if ($s !== '') $args['rafflelb_products_search'] = $s;
        if (in_array($f, ['store', 'raffle', 'both'], true)) $args['meta_query'] = self::query($f);
        if ($f === 'stock') $args['meta_query'] = [['key' => '_stock_status', 'value' => 'outofstock']];
        $q = new WP_Query($args);
        $counts = self::counts($s);

        echo '<div class="wrap rafflelb-products"><main class="rlp-shell">'
            . '<header class="rlp-header"><div><span class="rlp-kicker">RAFFLELB OPERATIONS</span><h1>Product Studio <em>' . esc_html($counts['all']) . '</em></h1><p>Manage simple store and raffle products from one workspace.</p></div>'
            . '<div class="rlp-header-actions"><a class="rlp-button" href="' . esc_url(self::url(['action' => 'new'])) . '">+ Create Product</a><a class="rlp-button rlp-button--quiet" href="' . esc_url(admin_url('edit.php?post_type=product')) . '">Advanced WooCommerce Editor ↗</a></div></header>';
        self::notice();
        echo '<nav class="rlp-tabs">';
        foreach (['all' => 'All', 'store' => 'Store Only', 'raffle' => 'Raffle Only', 'both' => 'Store + Raffle', 'stock' => 'Out of Stock'] as $k => $v) {
            echo '<a class="' . ($f === $k ? 'is-active' : '') . '" href="' . esc_url(self::url(['filter' => $k, 's' => $s])) . '">' . esc_html($v) . ' <b>' . esc_html($counts[$k]) . '</b></a>';
        }
        echo '</nav>'
            . '<form class="rlp-toolbar"><input type="hidden" name="page" value="' . esc_attr(self::SLUG) . '"><input type="hidden" name="filter" value="' . esc_attr($f) . '"><input type="search" name="s" value="' . esc_attr($s) . '" placeholder="Search product name or SKU"><button class="rlp-button">Search</button></form>'
            . '<div class="rlp-table-wrap"><table class="rlp-table"><colgroup>'
            . '<col class="rlp-col-product"><col class="rlp-col-mode"><col class="rlp-col-store"><col class="rlp-col-ops"><col class="rlp-col-actions">'
            . '</colgroup><thead><tr><th>Product</th><th>Mode</th><th>Store</th><th>Raffle operations</th><th>Actions</th></tr></thead><tbody>';
        if (!$q->posts) echo '<tr><td colspan="5"><div class="rlp-empty">No products found</div></td></tr>';
        foreach ($q->posts as $post) {
            if ($p = wc_get_product($post)) self::row($p);
        }
        echo '</tbody></table></div>';
        if ($q->max_num_pages > 1) {
            echo '<div class="rlp-pagination">' . wp_kses_post(paginate_links(['base' => add_query_arg('paged', '%#%', self::url(['filter' => $f, 's' => $s])), 'format' => '', 'current' => $args['paged'], 'total' => $q->max_num_pages, 'type' => 'list'])) . '</div>';
        }
        echo '</main></div>';
    }

    private static function row($p) {
        $id = $p->get_id();
        $x = self::profile($p);
        $s = $x['raffle'] ? self::stats($id) : false;

        /* WP_Query primes the term cache for every taxonomy on the queried post
         * type (product_tag included) in one batched query, so get_the_terms()
         * here is a cache read, not a new query per row. */
        $tag_terms = get_the_terms($id, self::TAG_TAXONOMY);
        $tag_names = ($tag_terms && !is_wp_error($tag_terms)) ? wp_list_pluck($tag_terms, 'name') : [];
        $tag_html = '';
        if ($tag_names) {
            $shown = array_slice($tag_names, 0, 3);
            $extra = count($tag_names) - count($shown);
            $tag_html = '<div class="rlp-tag-row">';
            foreach ($shown as $tn) $tag_html .= '<span class="rlp-tag-pill">' . esc_html($tn) . '</span>';
            if ($extra > 0) $tag_html .= '<span class="rlp-tag-pill rlp-tag-pill--more">+' . esc_html($extra) . '</span>';
            $tag_html .= '</div>';
        }

        echo '<tr><td><div class="rlp-product"><span class="rlp-thumb">' . $p->get_image('thumbnail') . '</span>'
            . '<div class="rlp-product-main"><strong class="rlp-product-name" title="' . esc_attr($p->get_name()) . '">' . esc_html($p->get_name()) . '</strong>'
            . '<small class="rlp-product-sku">SKU ' . esc_html($p->get_sku() ?: '—') . '</small>' . $tag_html . '</div></div></td>';

        echo '<td><span class="rlp-mode rlp-mode--' . esc_attr($x['mode']) . '">' . self::label($x['mode']) . '</span></td>';

        if ($x['mode'] === 'raffle') {
            echo '<td><div class="rlp-cell"><strong class="rlp-cell-value rlp-cell-value--muted">—</strong><small class="rlp-cell-sub">Direct purchase disabled</small></div></td>';
        } else {
            $in_stock = $p->is_in_stock();
            echo '<td><div class="rlp-cell"><strong class="rlp-cell-value">' . wp_kses_post(wc_price($x['retail'])) . '</strong>'
                . '<small class="rlp-cell-sub rlp-cell-sub--' . ($in_stock ? 'in' : 'out') . '"><i class="rlp-dot" aria-hidden="true"></i>' . ($in_stock ? 'In Stock' : 'Out of Stock') . '</small></div></td>';
        }

        echo '<td>';
        if ($s) {
            [$op_label, $op_mod] = self::opstatus($s['status']);
            echo '<div class="rlp-cell rlp-cell--ops"><span class="rlp-cell-label">Entry price</span><strong class="rlp-cell-value">' . wp_kses_post(wc_price($x['entry'])) . '</strong>'
                . '<small class="rlp-cell-sub">' . esc_html($s['claimed']) . ' claimed · ' . esc_html($s['total']) . ' total</small>'
                . '<span class="rlp-pill rlp-pill--' . esc_attr($op_mod) . '">' . esc_html($op_label) . '</span></div>';
        } elseif ($x['raffle']) {
            echo '<div class="rlp-cell rlp-cell--ops"><small class="rlp-cell-sub">— Raffle data unavailable</small></div>';
        } else {
            echo '<div class="rlp-cell rlp-cell--ops"><small class="rlp-cell-sub">— Raffle disabled</small></div>';
        }
        echo '</td>';



        echo '<td><div class="rlp-actions"><a class="rlp-action rlp-action--primary" href="' . esc_url(self::edit($id)) . '">Manage →</a>'
            . ($p->is_visible() ? '<a class="rlp-action rlp-action--secondary" target="_blank" rel="noopener" href="' . esc_url(get_permalink($id)) . '">View</a>' : '')
            . '<a class="rlp-action rlp-action--tertiary" href="' . esc_url(get_edit_post_link($id, 'raw')) . '">Advanced Edit</a></div></td></tr>';
    }

    private static function section($t, $body) { echo '<section class="rlp-card rlp-form-card"><h2>' . esc_html($t) . '</h2>' . $body . '</section>'; }

    private static function notice() {
        $n = sanitize_key(self::get('rlp'));
        if ($n) {
            $messages = [
                'created' => 'Product created successfully.',
                'updated' => 'Product updated successfully.',
                'drafted' => 'Product moved to Draft.',
                'published' => 'Product published.',
            ];
            echo '<div class="rlp-notice">' . esc_html($messages[$n] ?? 'Product updated successfully.') . '</div>';
        }
        $e = rawurldecode((string) self::get('rlp_error'));
        if ($e) echo '<div class="rlp-notice rlp-notice--error">' . esc_html($e) . '</div>';
    }

    private static function editor($id) {
        $new = $id === 0;
        $p = $new ? new WC_Product_Simple() : wc_get_product($id);
        if (!$p) { echo '<div class="wrap rafflelb-products"><div class="rlp-empty">Product not found</div></div>'; return; }
        if ($new && !current_user_can('edit_products')) wp_die('You cannot create products.');
        if (!$new && !current_user_can('edit_post', $id)) wp_die('You cannot edit this product.');

        $advanced = !$new && !$p->is_type('simple');
        $x = self::profile($p);
        $s = $x['raffle'] ? self::stats($id) : false;
        $lock = $x['raffle'] && (!$s || self::locked($id, $s));
        $item = get_post_meta($id, self::ITEM, true) === 'digital' ? 'digital' : 'tangible';
        $cats = wp_get_post_terms($id, 'product_cat', ['fields' => 'ids']);
        $d = $lock ? ' disabled' : '';
        $store_price = $x['mode'] === 'both' ? (string) get_post_meta($id, self::RETAIL, true) : ($x['raffle'] ? '' : $p->get_regular_price());
        $status = $new ? 'draft' : $p->get_status();

        $stock_controls = $x['raffle']
            ? '<label>WooCommerce Stock<input readonly value="' . esc_attr($p->get_stock_quantity()) . '"></label><p class="rlp-help">Managed by RaffleLB raffle availability.</p>'
                . (!$lock ? '<label>Store Stock Quantity<input type="number" min="0" step="1" name="conversion_stock_qty" value=""></label><label>Store Stock Status<select name="conversion_stock_status"><option value="instock">In stock</option><option value="outofstock">Out of stock</option></select></label><label><input type="checkbox" name="conversion_manage_stock" value="1"> Manage Store stock quantity</label><label><input type="checkbox" name="confirm_store_inventory" value="1"> I confirm Store inventory is configured</label><p class="rlp-help">Raffle availability is not reused as store inventory.</p>' : '')
            : '<label>Stock Quantity<input type="number" min="0" step="1" name="stock_qty" value="' . esc_attr($p->managing_stock() ? $p->get_stock_quantity() : '') . '"></label><label>Stock Status<select name="stock_status"><option value="instock" ' . selected($p->get_stock_status(), 'instock', false) . '>In stock</option><option value="outofstock" ' . selected($p->get_stock_status(), 'outofstock', false) . '>Out of stock</option></select></label>';

        $lock_badge = '<span class="rlp-locked-badge">🔒 Locked</span>';
        $locked_mode = '<div class="rlp-locked-value"><span>' . esc_html(self::label($x['mode'])) . '</span>' . $lock_badge . '</div>';
        $locked_store = $x['mode'] === 'both'
            ? '<div class="rlp-locked-grid"><div><small>Direct Purchase / Retail Price</small><strong>' . wp_kses_post(wc_price((float) get_post_meta($id, self::RETAIL, true))) . '</strong>' . $lock_badge . '</div><div><small>WooCommerce Stock</small><strong>' . esc_html($p->get_stock_quantity()) . '</strong><em>Managed by RaffleLB raffle availability</em>' . $lock_badge . '</div></div>'
            : '<div class="rlp-locked-grid"><div><small>Direct Purchase</small><strong>Disabled</strong>' . $lock_badge . '</div><div><small>WooCommerce Stock</small><strong>' . esc_html($p->get_stock_quantity()) . '</strong><em>Managed by RaffleLB raffle availability</em>' . $lock_badge . '</div></div>';
        $locked_raffle = '<div class="rlp-locked-grid"><div><small>Entry Price</small><strong>' . wp_kses_post(wc_price((float) $p->get_price())) . '</strong>' . $lock_badge . '</div><div><small>Raffle Capacity</small><strong>' . esc_html(get_post_meta($id, self::TOTAL, true)) . '</strong>' . $lock_badge . '</div><div><small>Delivery Type</small><strong>' . esc_html($item === 'digital' ? 'Digital / voucher / gift card' : 'Tangible / physical item') . '</strong>' . $lock_badge . '</div></div>';

        echo '<div class="wrap rafflelb-products"><main class="rlp-shell rlp-editor"><a class="rlp-back" href="' . esc_url(self::url()) . '">← Back to Products</a>';
        self::notice();
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('rafflelb_products_save', 'rafflelb_products_nonce');
        echo '<input type="hidden" name="action" value="rafflelb_products_save"><input type="hidden" name="product_id" value="' . esc_attr($id) . '">'
            . '<header class="rlp-detail-head"><div class="rlp-detail-image">' . $p->get_image('medium') . '</div><div><span class="rlp-kicker">' . ($new ? 'NEW SIMPLE PRODUCT' : 'PRODUCT STUDIO') . '</span><h1>' . esc_html($new ? 'Create Product' : $p->get_name()) . '</h1><p>SKU ' . esc_html($p->get_sku() ?: '—') . ' · ' . esc_html(ucfirst($status)) . '</p></div>'
            . '<div class="rlp-detail-actions"><button class="rlp-button" ' . disabled($advanced, true, false) . '>Save Changes</button>' . (!$new && $p->is_visible() ? '<a class="rlp-button rlp-button--quiet" target="_blank" href="' . esc_url(get_permalink($id)) . '">View on Site ↗</a>' : '') . (!$new ? '<a class="rlp-button rlp-button--quiet" href="' . esc_url(get_edit_post_link($id, 'raw')) . '">Advanced WooCommerce Editor ↗</a>' : '') . '</div></header>';

        echo '<style>.rafflelb-products .rlp-locked-value{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:13px 14px;border:1px solid var(--line);border-radius:8px;background:#0d100c;color:#fff;font-size:13px;font-weight:800;letter-spacing:.04em}.rafflelb-products .rlp-locked-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px}.rafflelb-products .rlp-locked-grid>div{min-height:78px;padding:13px;border:1px solid var(--line);border-radius:8px;background:#0d100c}.rafflelb-products .rlp-locked-grid small{display:block;margin-bottom:7px}.rafflelb-products .rlp-locked-grid strong{display:block;font-size:14px}.rafflelb-products .rlp-locked-grid em{display:block;margin-top:6px;color:var(--muted);font-size:11px;font-style:normal}.rafflelb-products .rlp-locked-badge{display:inline-flex;align-items:center;margin-top:8px;padding:3px 6px;border:1px solid #3b4734;border-radius:999px;color:#b9c4b1;font-size:10px;font-weight:700;letter-spacing:0;text-transform:none}.rafflelb-products .rlp-lock{display:flex;align-items:center;gap:8px}.rafflelb-products .rlp-lock>span{color:var(--lime);font-size:13px}@media(max-width:900px){.rafflelb-products .rlp-locked-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:782px){.rafflelb-products .rlp-locked-grid{grid-template-columns:1fr}}</style>';
        if ($advanced) echo '<div class="rlp-notice rlp-notice--error">Advanced product type — use WooCommerce Editor. Product Studio supports simple products only.</div>';
        if ($lock) echo '<div class="rlp-lock"><span aria-hidden="true">🔒</span> Locked because this raffle already has operational activity. Manage the raffle from Raffle Operations.</div>';

        echo '<div class="rlp-editor-grid"><div class="rlp-editor-main">';
        self::section('Basic Information', '<label>Product Name<input required name="name" value="' . esc_attr($p->get_name()) . '"></label><label>SKU<input name="sku" value="' . esc_attr($p->get_sku()) . '"></label><label>Short Description<textarea name="short_description" rows="4">' . esc_textarea($p->get_short_description()) . '</textarea></label><label>Full Description<textarea name="description" rows="8">' . esc_textarea($p->get_description()) . '</textarea></label>');
        self::section('Selling Mode', $lock ? $locked_mode : '<div class="rlp-mode-options"><label><input type="radio" name="mode" value="store" ' . checked($x['mode'], 'store', false) . $d . '> <strong>Store Only</strong><small>Standard WooCommerce purchase</small></label><label><input type="radio" name="mode" value="raffle" ' . checked($x['mode'], 'raffle', false) . $d . '> <strong>Raffle Only</strong><small>Entries only</small></label><label><input type="radio" name="mode" value="both" ' . checked($x['mode'], 'both', false) . $d . '> <strong>Store + Raffle</strong><small>Entry and retail paths</small></label></div>');
        self::section('Store Settings', $lock ? $locked_store : '<div class="rlp-store-fields"><label>Regular Price<input type="number" min="0" step="0.01" name="regular_price" value="' . esc_attr($store_price) . '"></label><label>Sale Price<input type="number" min="0" step="0.01" name="sale_price" value="' . esc_attr($x['raffle'] ? '' : $p->get_sale_price()) . '"></label><label>Direct Purchase / Retail Price<input type="number" min="0.01" step="0.01" name="retail" value="' . esc_attr(get_post_meta($id, self::RETAIL, true)) . '"' . $d . '></label>' . $stock_controls . '<p class="rlp-help">Store Only inventory remains unchanged unless a stock quantity is intentionally supplied. Raffle products cannot edit WooCommerce stock independently.</p><label class="rlp-store-confirm"><input type="checkbox" name="confirm_store_price" value="1"> I confirm this is the Store Only selling price.</label></div>');
        self::section('Raffle Settings', $lock ? $locked_raffle : '<div class="rlp-store-fields"><label>Entry Price<input type="number" min="0.01" step="0.01" name="entry" value="' . esc_attr($p->get_price()) . '"' . $d . '></label><label>Raffle Capacity<input type="number" min="1" step="1" name="capacity" value="' . esc_attr(get_post_meta($id, self::TOTAL, true)) . '"' . $d . '></label><label>Delivery Type<select name="item"' . $d . '><option value="tangible" ' . selected($item, 'tangible', false) . '>Tangible / physical item</option><option value="digital" ' . selected($item, 'digital', false) . '>Digital / voucher / gift card</option></select></label><p class="rlp-help">Entry price is the current WooCommerce price. Capacity uses the existing Draw Engine contract.</p></div>');
        self::section('Images', '<input class="rlp-media-value" type="hidden" name="image" value="' . esc_attr($p->get_image_id()) . '"><input class="rlp-gallery-value" type="hidden" name="gallery" value="' . esc_attr(implode(',', $p->get_gallery_image_ids())) . '"><div class="rlp-media-actions"><button class="rlp-button rlp-media-featured" type="button">Choose featured image</button><button class="rlp-button rlp-button--quiet rlp-media-gallery" type="button">Select gallery images</button></div><div class="rlp-gallery-preview"></div>');
        echo '</div><aside class="rlp-sidebar">';
        self::section('Publish', '<label>Publish State<select name="status"><option value="draft" ' . selected($status, 'draft', false) . '>Draft</option><option value="publish" ' . selected($status, 'publish', false) . '>Published</option>' . (!$new ? '<option value="private" ' . selected($status, 'private', false) . '>Private</option>' : '') . '</select></label><label>Catalog Visibility<select name="visibility">' . self::opts(['visible' => 'Shop and search', 'catalog' => 'Shop only', 'search' => 'Search only', 'hidden' => 'Hidden'], $p->get_catalog_visibility()) . '</select></label>');

        $cat_html = '<div class="rlp-categories">';
        $terms = get_terms(['taxonomy' => 'product_cat', 'hide_empty' => false]);
        foreach (is_wp_error($terms) ? [] : $terms as $t) {
            $cat_html .= '<label><input type="checkbox" name="categories[]" value="' . esc_attr($t->term_id) . '" ' . checked(in_array($t->term_id, $cats, true), true, false) . '> ' . esc_html($t->name) . '</label>';
        }
        $cat_html .= '</div>';
        self::section('Categories', $cat_html);

        $brand_taxonomy = self::brand_taxonomy();
        $brand_options = '';
        if ($brand_taxonomy !== '') {
            $existing_brands = wp_get_post_terms($id, $brand_taxonomy, ['fields' => 'all']);
            if (is_wp_error($existing_brands)) $existing_brands = [];
            foreach ($existing_brands as $t) {
                $brand_options .= '<option value="' . esc_attr($t->term_id) . '" selected>' . esc_html($t->name) . '</option>';
                break; // Product Studio intentionally keeps one primary brand per product.
            }
            self::section('Brand', '<div class="rlp-brand-field"><select class="rlp-brand-select" name="brand" data-placeholder="Search brands…" style="width:100%"><option value=""></option>' . $brand_options . '</select><button type="button" class="button rlp-brand-add-toggle">+ Add New Brand</button></div><div class="rlp-brand-create" hidden><input type="text" class="rlp-brand-new-name" placeholder="New brand name" autocomplete="off"><button type="button" class="button button-primary rlp-brand-create-btn">Add Brand</button><button type="button" class="button rlp-brand-cancel-btn">Cancel</button><span class="rlp-brand-create-status" aria-live="polite"></span></div><p class="rlp-help">Choose an existing brand, or click <strong>+ Add New Brand</strong>. The new brand is created immediately and selected for this product.</p>');
        } else {
            self::section('Brand', '<p class="rlp-help">Brand taxonomy is unavailable. Reload Product Studio once WooCommerce is active.</p>');
        }

        $existing_tags = wp_get_post_terms($id, self::TAG_TAXONOMY, ['fields' => 'all']);
        if (is_wp_error($existing_tags)) $existing_tags = [];
        $tag_options = '';
        foreach ($existing_tags as $t) $tag_options .= '<option value="' . esc_attr($t->term_id) . '" selected>' . esc_html($t->name) . '</option>';
        self::section('Product Tags', '<select class="rlp-tag-select" name="tags[]" multiple="multiple" data-placeholder="Search or add a tag…" style="width:100%">' . $tag_options . '</select><p class="rlp-help">Search existing tags, or type a new tag name and press Enter to create it.</p>');

        self::section('Product Summary', '<dl><div><dt>Type</dt><dd>' . esc_html($p->get_type()) . '</dd></div><div><dt>Raffle state</dt><dd>' . esc_html($s ? strtoupper(str_replace('_', ' ', $s['status'])) : ($x['raffle'] ? 'Unavailable' : 'Not a raffle')) . '</dd></div><div><dt>Entries / capacity</dt><dd>' . esc_html($s ? $s['claimed'] . ' / ' . $s['total'] : '—') . '</dd></div></dl>');
        echo '</aside></div></form></main></div>';
        self::media($p->get_image_id(), $p->get_gallery_image_ids());
        self::tag_assets();
        self::brand_assets();
    }

    private static function opts($a, $selected) {
        $o = '';
        foreach ($a as $v => $l) $o .= '<option value="' . esc_attr($v) . '" ' . selected($selected, $v, false) . '>' . esc_html($l) . '</option>';
        return $o;
    }

    private static function media($image, $gallery) { ?><script>jQuery(function($){var f=<?php echo wp_json_encode(absint($image)); ?>,g=<?php echo wp_json_encode(array_map('absint', (array) $gallery)); ?>,pv=$('.rlp-gallery-preview');function loadImage(id,img){var attachment=wp.media.attachment(id);attachment.fetch().always(function(){var sizes=attachment.get('sizes')||{},src=sizes.thumbnail?sizes.thumbnail.url:attachment.get('url');if(src)img.attr('src',src);});}function draw(){var h='';if(f)h+='<div class="rlp-media-item"><small>Featured</small><img data-image-id="'+f+'" alt=""><button type="button" class="rlp-remove-featured">Remove</button></div>';g.forEach(function(i,n){h+='<div class="rlp-media-item" data-id="'+i+'"><small>Gallery</small><img data-image-id="'+i+'" alt=""><button type="button" class="rlp-gallery-up" '+(n?'':'disabled')+'>↑</button><button type="button" class="rlp-gallery-down" '+(n===g.length-1?'disabled':'')+'>↓</button><button type="button" class="rlp-gallery-remove">Remove</button></div>'});pv.html(h);pv.find('img').each(function(){loadImage(+$(this).data('image-id'),$(this));});$('.rlp-media-value').val(f);$('.rlp-gallery-value').val(g.join(','));}draw();$('.rlp-media-featured').click(function(){var x=wp.media({title:f?'Replace featured image':'Choose featured image',multiple:false,library:{type:'image'}});x.on('select',function(){f=x.state().get('selection').first().id;draw()});x.open()});$('.rlp-media-gallery').click(function(){var x=wp.media({title:'Select gallery images',multiple:true,library:{type:'image'}});x.on('select',function(){g=x.state().get('selection').map(function(a){return a.id});draw()});x.open()});pv.on('click','.rlp-remove-featured',function(){f=0;draw()}).on('click','.rlp-gallery-remove',function(){var i=g.indexOf(+$(this).closest('[data-id]').data('id'));if(i>-1)g.splice(i,1);draw()}).on('click','.rlp-gallery-up,.rlp-gallery-down',function(){var i=g.indexOf(+$(this).closest('[data-id]').data('id')),j=$(this).hasClass('rlp-gallery-up')?i-1:i+1;if(i<0||j<0||j>=g.length)return;var t=g[i];g[i]=g[j];g[j]=t;draw()})});</script><?php }

    /** Product Tags Select2: search via ajax_tag_search(), and tags:true lets
     *  a typed name that doesn't match anything become a new option locally.
     *  Nothing is created until the form is actually saved. */
    private static function tag_assets() {
        $nonce = wp_create_nonce(self::TAG_NONCE);
        $ajax_url = admin_url('admin-ajax.php');
        ?><script>jQuery(function($){var $select=$('.rlp-tag-select');if(!$select.length)return;var enhancer=null;if(typeof $.fn.selectWoo==='function'){enhancer='selectWoo';}else if(typeof $.fn.select2==='function'){enhancer='select2';}if(!enhancer)return;$select[enhancer]({width:'100%',tags:true,tokenSeparators:[',','\n'],placeholder:$select.data('placeholder')||'Search or add a tag…',ajax:{url:<?php echo wp_json_encode($ajax_url); ?>,dataType:'json',delay:250,data:function(params){return {action:'rafflelb_products_tag_search',nonce:<?php echo wp_json_encode($nonce); ?>,q:params.term||''};},processResults:function(data){return {results:(data||[]).map(function(t){return {id:t.id,text:t.text};})};}},createTag:function(params){var term=$.trim(params.term);if(term==='')return null;return {id:term,text:term,newTag:true};}});});</script><?php
    }

    /** Single Brand SelectWoo field. Existing terms are searched by AJAX;
     *  tags:true lets an administrator type a new brand, but the term is only
     *  created when the product form is saved. */
    private static function brand_assets() {
        $taxonomy = self::brand_taxonomy();
        if ($taxonomy === '') return;
        $nonce = wp_create_nonce(self::BRAND_NONCE);
        $ajax_url = admin_url('admin-ajax.php');
        ?><script>jQuery(function($){
            var $select=$('.rlp-brand-select');
            if(!$select.length)return;
            var enhancer=null;
            if(typeof $.fn.selectWoo==='function'){enhancer='selectWoo';}
            else if(typeof $.fn.select2==='function'){enhancer='select2';}
            if(enhancer){
                $select[enhancer]({
                    width:'100%',
                    allowClear:true,
                    placeholder:$select.data('placeholder')||'Search brands…',
                    ajax:{
                        url:<?php echo wp_json_encode($ajax_url); ?>,
                        dataType:'json',
                        delay:250,
                        data:function(params){return {action:'rafflelb_products_brand_search',nonce:<?php echo wp_json_encode($nonce); ?>,q:params.term||''};},
                        processResults:function(data){return {results:(data||[]).map(function(t){return {id:t.id,text:t.text};})};}
                    }
                });
            }

            var $box=$('.rlp-brand-create'),$name=$('.rlp-brand-new-name'),$status=$('.rlp-brand-create-status');
            $('.rlp-brand-add-toggle').on('click',function(){
                $box.prop('hidden',false);
                $status.text('');
                setTimeout(function(){$name.trigger('focus');},0);
            });
            $('.rlp-brand-cancel-btn').on('click',function(){
                $box.prop('hidden',true);$name.val('');$status.text('');
            });
            function createBrand(){
                var name=$.trim($name.val()||'');
                if(!name){$status.text('Enter a brand name.');$name.trigger('focus');return;}
                var $btn=$('.rlp-brand-create-btn');
                $btn.prop('disabled',true);$status.text('Adding…');
                $.ajax({
                    url:<?php echo wp_json_encode($ajax_url); ?>,
                    method:'POST',dataType:'json',
                    data:{action:'rafflelb_products_brand_create',nonce:<?php echo wp_json_encode($nonce); ?>,name:name}
                }).done(function(resp){
                    if(!resp||!resp.success||!resp.data){$status.text((resp&&resp.data&&resp.data.message)?resp.data.message:'Could not add brand.');return;}
                    var d=resp.data;
                    var option=new Option(d.text,d.id,true,true);
                    $select.empty().append(option).trigger('change');
                    $name.val('');
                    $status.text(d.existing?'Brand already existed and is now selected.':'Brand added and selected.');
                    setTimeout(function(){$box.prop('hidden',true);$status.text('');},900);
                }).fail(function(xhr){
                    var msg='Could not add brand.';
                    if(xhr.responseJSON&&xhr.responseJSON.data&&xhr.responseJSON.data.message)msg=xhr.responseJSON.data.message;
                    $status.text(msg);
                }).always(function(){$btn.prop('disabled',false);});
            }
            $('.rlp-brand-create-btn').on('click',createBrand);
            $name.on('keydown',function(e){if(e.key==='Enter'){e.preventDefault();createBrand();}});
        });</script><?php
    }

    public static function publish_state() {
        if (!self::ok()) wp_die('You do not have permission to change product publishing state.', '', ['response' => 403]);

        $id = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;
        $state = isset($_POST['publish_state']) ? sanitize_key(wp_unslash($_POST['publish_state'])) : '';
        if (!$id || get_post_type($id) !== 'product' || !in_array($state, ['draft', 'publish'], true)) {
            wp_safe_redirect(self::url());
            exit;
        }
        check_admin_referer('rafflelb_products_publish_state_' . $id . '_' . $state);
        if (!current_user_can('edit_post', $id)) wp_die('You cannot edit this product.', '', ['response' => 403]);
        if ($state === 'publish' && !current_user_can('publish_products')) wp_die('You cannot publish products.', '', ['response' => 403]);

        $updated = wp_update_post(['ID' => $id, 'post_status' => $state], true);
        if (is_wp_error($updated)) wp_die(esc_html($updated->get_error_message()));

        $fallback = self::url();
        $redirect = isset($_POST['redirect_to']) ? wp_validate_redirect(wp_unslash($_POST['redirect_to']), $fallback) : $fallback;
        wp_safe_redirect(add_query_arg('rlp', $state === 'draft' ? 'drafted' : 'published', $redirect));
        exit;
    }

    public static function save() {
        if (!self::ok()) wp_die('You do not have permission to save products.', '', ['response' => 403]);
        check_admin_referer('rafflelb_products_save', 'rafflelb_products_nonce');
        $id = isset($_POST['product_id']) ? absint(wp_unslash($_POST['product_id'])) : 0;
        $new = !$id;
        if ($new && !current_user_can('edit_products')) wp_die('You cannot create products.', '', ['response' => 403]);
        $p = $new ? new WC_Product_Simple() : wc_get_product($id);
        if (!$p || (!$new && !current_user_can('edit_post', $id))) self::error($id, 'Product could not be loaded.');
        if (!$new && !$p->is_type('simple')) self::error($id, 'Advanced product types must be edited in WooCommerce.');

        $name = isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '';
        $sku = isset($_POST['sku']) ? wc_clean(wp_unslash($_POST['sku'])) : '';
        $mode = isset($_POST['mode']) ? sanitize_key(wp_unslash($_POST['mode'])) : 'store';
        $reg = isset($_POST['regular_price']) ? wc_format_decimal(wp_unslash($_POST['regular_price'])) : '';
        $sale = isset($_POST['sale_price']) ? wc_format_decimal(wp_unslash($_POST['sale_price'])) : '';
        $entry = isset($_POST['entry']) ? wc_format_decimal(wp_unslash($_POST['entry'])) : '';
        $retail = isset($_POST['retail']) ? wc_format_decimal(wp_unslash($_POST['retail'])) : '';
        $cap = isset($_POST['capacity']) ? absint(wp_unslash($_POST['capacity'])) : 0;
        $stock = (isset($_POST['stock_qty']) && wp_unslash($_POST['stock_qty']) !== '') ? wc_stock_amount(wp_unslash($_POST['stock_qty'])) : null;
        $conversion_stock = (isset($_POST['conversion_stock_qty']) && wp_unslash($_POST['conversion_stock_qty']) !== '') ? wc_stock_amount(wp_unslash($_POST['conversion_stock_qty'])) : null;
        $old = $new ? ['raffle' => false, 'mode' => 'store'] : self::profile($p);
        $old_item = !$new && get_post_meta($id, self::ITEM, true) === 'digital' ? 'digital' : 'tangible';
        $stats = $new ? false : self::stats($id);
        $lock = $old['raffle'] && (!$stats || self::locked($id, $stats));
        $buy_enabled = !$new && get_post_meta($id, self::BUY, true) === 'yes';
        if ($lock) { $mode = $old['mode']; $entry = (string) $p->get_price(); $cap = absint(get_post_meta($id, self::TOTAL, true)); $retail = (string) get_post_meta($id, self::RETAIL, true); $item = $old_item; }

        if ($name === '') self::error($id, 'A product name is required.');
        if (!in_array($mode, ['store', 'raffle', 'both'], true)) self::error($id, 'Choose a valid selling mode.');
        $sku_id = $sku === '' ? 0 : wc_get_product_id_by_sku($sku);
        if ($sku_id && $sku_id !== $id) self::error($id, 'SKU must be unique.');
        foreach ([$reg, $sale, $entry, $retail] as $v) if ($v !== '' && (float) $v < 0) self::error($id, 'Prices cannot be negative.');
        if ($stock !== null && $stock < 0 || $conversion_stock !== null && $conversion_stock < 0) self::error($id, 'Stock cannot be negative.');
        $conversion = !$lock && !$new && $old['raffle'] && $mode === 'store';
        $conversion_manage = !empty($_POST['conversion_manage_stock']);
        if ($conversion && ((float) $reg <= 0 || empty($_POST['confirm_store_price']) || empty($_POST['confirm_store_inventory']) || ($conversion_manage && $conversion_stock === null))) self::error($id, 'Enter and confirm a valid Store Only price and explicit Store inventory; raffle availability is not reused.');
        if ($mode === 'both' && (float) $retail <= 0) self::error($id, 'Store + Raffle requires a positive Direct Purchase / Retail Price.');
        if (in_array($mode, ['raffle', 'both'], true) && ((float) $entry <= 0 || $cap < 1)) self::error($id, 'Raffle products require a positive Entry Price and Raffle Capacity.');

        $status = isset($_POST['status']) ? sanitize_key(wp_unslash($_POST['status'])) : 'draft';
        if (!in_array($status, ['draft', 'publish', 'private'], true)) $status = 'draft';
        if ($status === 'publish' && !current_user_can('publish_products')) wp_die('You cannot publish products.', '', ['response' => 403]);
        if ($status === 'private' && !current_user_can('edit_private_products')) wp_die('You cannot make products private.', '', ['response' => 403]);
        $vis = isset($_POST['visibility']) ? sanitize_key(wp_unslash($_POST['visibility'])) : 'visible';
        if (!in_array($vis, ['visible', 'catalog', 'search', 'hidden'], true)) $vis = 'visible';
        if (!$lock) $item = isset($_POST['item']) && wp_unslash($_POST['item']) === 'digital' ? 'digital' : 'tangible';
        if ($conversion) $item = $old_item;
        $image = isset($_POST['image']) ? absint(wp_unslash($_POST['image'])) : 0;
        $gallery = isset($_POST['gallery']) ? array_values(array_filter(array_map('absint', explode(',', sanitize_text_field(wp_unslash($_POST['gallery'])))), function ($attachment) { return $attachment && wp_attachment_is_image($attachment); })) : [];
        $cats = isset($_POST['categories']) ? array_map('absint', (array) wp_unslash($_POST['categories'])) : [];

        /* Brand: one primary term in the detected product brand taxonomy.
         * Numeric input attaches an existing term; a typed name is sanitized
         * and created by wp_set_object_terms() on save. */
        $brand_taxonomy = self::brand_taxonomy();
        $brand = '';
        if ($brand_taxonomy !== '' && isset($_POST['brand'])) {
            $raw_brand = trim((string) wp_unslash($_POST['brand']));
            if ($raw_brand !== '') {
                $existing_brand = ctype_digit($raw_brand) ? get_term((int) $raw_brand, $brand_taxonomy) : null;
                $brand = ($existing_brand && !is_wp_error($existing_brand)) ? (int) $raw_brand : sanitize_text_field($raw_brand);
            }
        }

        /* Product Tags: native product_tag only. Numeric values that resolve to
         * a real term are attached by id; anything else is a plain name and
         * wp_set_object_terms() creates that term natively if it doesn't
         * already exist — the same mechanism the core tag metabox uses. The
         * call is scoped to product_tag alone, so categories and every other
         * taxonomy on this product are untouched. */
        $tags_raw = isset($_POST['tags']) ? (array) wp_unslash($_POST['tags']) : [];
        $tags = [];
        foreach ($tags_raw as $raw_tag) {
            $raw_tag = trim((string) $raw_tag);
            if ($raw_tag === '') continue;
            $existing_term = ctype_digit($raw_tag) ? get_term((int) $raw_tag, self::TAG_TAXONOMY) : null;
            $tags[] = ($existing_term && !is_wp_error($existing_term)) ? (int) $raw_tag : sanitize_text_field($raw_tag);
            if (count($tags) >= 50) break;
        }

        $p->set_name($name);
        $p->set_sku($sku);
        $p->set_short_description(isset($_POST['short_description']) ? wp_kses_post(wp_unslash($_POST['short_description'])) : '');
        $p->set_description(isset($_POST['description']) ? wp_kses_post(wp_unslash($_POST['description'])) : '');
        $p->set_status($status);
        $p->set_catalog_visibility($vis);
        $p->set_image_id($image && wp_attachment_is_image($image) ? $image : 0);
        $p->set_gallery_image_ids($gallery);
        if (!$lock && $mode === 'store') {
            $p->set_regular_price($reg);
            $p->set_sale_price($sale);
            $p->set_price($sale !== '' ? $sale : $reg);
            if ($conversion) {
                $p->set_virtual($old_item === 'digital');
                $p->set_manage_stock($conversion_manage);
                if ($conversion_manage) $p->set_stock_quantity($conversion_stock);
                if (isset($_POST['conversion_stock_status']) && in_array(wp_unslash($_POST['conversion_stock_status']), ['instock', 'outofstock'], true)) $p->set_stock_status(wp_unslash($_POST['conversion_stock_status']));
            } else {
                if ($stock !== null) { $p->set_manage_stock(true); $p->set_stock_quantity($stock); }
                if (isset($_POST['stock_status']) && in_array(wp_unslash($_POST['stock_status']), ['instock', 'outofstock'], true)) $p->set_stock_status(wp_unslash($_POST['stock_status']));
            }
        } elseif (!$lock) {
            $p->set_regular_price($entry);
            $p->set_sale_price('');
            $p->set_price($entry);
            $p->set_virtual(true);
            $p->set_manage_stock(true);
            $p->set_backorders('no');
            $p->set_sold_individually(false);
            $p->set_stock_quantity($cap);
            $p->set_stock_status($cap > 0 ? 'instock' : 'outofstock');
        }
        $saved = $p->save();
        if (!$saved) self::error($id, 'Product could not be saved.');
        wp_set_object_terms($saved, $cats, 'product_cat');
        if ($brand_taxonomy !== '') {
            $brand_result = wp_set_object_terms($saved, $brand === '' ? [] : [$brand], $brand_taxonomy);
            if (is_wp_error($brand_result)) self::error($saved, 'Brand could not be saved.');
        }
        $tags_result = wp_set_object_terms($saved, $tags, self::TAG_TAXONOMY);
        if (is_wp_error($tags_result)) self::error($saved, 'Product Tags could not be saved.');

        if (!$lock && $mode === 'store') {
            update_post_meta($saved, self::ENABLED, 'no');
            update_post_meta($saved, self::BUY, 'no');
            delete_post_meta($saved, self::RETAIL);
        } elseif (!$lock) {
            update_post_meta($saved, self::ENABLED, 'yes');
            update_post_meta($saved, self::TOTAL, $cap);
            update_post_meta($saved, self::ITEM, $item);
            update_post_meta($saved, self::BUY, $mode === 'both' ? 'yes' : 'no');
            if ($mode === 'both') update_post_meta($saved, self::RETAIL, $retail); else delete_post_meta($saved, self::RETAIL);
        }
        wp_safe_redirect(add_query_arg('rlp', $new ? 'created' : 'saved', self::edit($saved)));
        exit;
    }

    private static function error($id, $m) {
        wp_safe_redirect(add_query_arg('rlp_error', rawurlencode($m), $id ? self::edit($id) : self::url(['action' => 'new'])));
        exit;
    }
}
RaffleLB_Products::init();
add_action('admin_footer', function () { ?>
<style>.rafflelb-products .rlp-mode-hidden{display:none!important}</style>
<script>document.addEventListener('DOMContentLoaded',function(){var root=document.querySelector('.rafflelb-products .rlp-editor');if(!root)return;var mode=function(){var x=root.querySelector('input[name="mode"]:checked');return x?x.value:'store'},card=function(text){return Array.prototype.find.call(root.querySelectorAll('.rlp-card'),function(x){return x.querySelector('h2')&&x.querySelector('h2').textContent.trim()===text})},hide=function(el,on){if(el)el.classList.toggle('rlp-mode-hidden',!!on)},fields=function(name){return root.querySelectorAll('[name="'+name+'"]')};function label(name){Array.prototype.forEach.call(fields(name),function(x){hide(x.closest('label'),false)})}function update(){var m=mode(),store=card('Store Settings'),raffle=card('Raffle Settings'),conversion=store&&store.querySelector('[name="conversion_stock_qty"]');hide(raffle,m==='store');['regular_price','sale_price','retail','stock_qty','stock_status','conversion_stock_qty','conversion_stock_status','conversion_manage_stock','confirm_store_inventory','confirm_store_price'].forEach(label);if(m==='store'){Array.prototype.forEach.call(fields('retail'),function(x){hide(x.closest('label'),true)});if(!conversion){Array.prototype.forEach.call(fields('confirm_store_price'),function(x){hide(x.closest('label'),true)})}}else{['regular_price','sale_price','retail','stock_qty','stock_status','conversion_stock_qty','conversion_stock_status','conversion_manage_stock','confirm_store_inventory','confirm_store_price'].forEach(function(n){Array.prototype.forEach.call(fields(n),function(x){hide(x.closest('label'),true)})});hide(raffle,false);if(m==='both')Array.prototype.forEach.call(fields('retail'),function(x){hide(x.closest('label'),false)})}}Array.prototype.forEach.call(root.querySelectorAll('input[name="mode"]'),function(x){x.addEventListener('change',update)});update()});</script>
<?php });
