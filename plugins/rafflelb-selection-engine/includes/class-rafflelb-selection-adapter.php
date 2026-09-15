<?php

if (!defined('ABSPATH')) {
    exit;
}

final class RaffleLB_Selection_Adapter {
    const SNAPSHOT_CACHE_TTL = 20;
    const META_ENABLED = '_rafflelb_draw_enabled';
    const META_TOTAL = '_rafflelb_total_entries';
    const META_STATUS = '_rafflelb_draw_status';
    const META_CLOSED_AT = '_rafflelb_draw_closed_at';
    const META_SELECTED_AT = '_rafflelb_winner_selected_at';

    public static function available() {
        return class_exists('WooCommerce')
            && class_exists('RaffleLB_Draw_Engine')
            && method_exists('RaffleLB_Draw_Engine', 'homepage_get_draw_result')
            && method_exists('RaffleLB_Draw_Engine', 'homepage_winner_masked_name');
    }

    public static function is_raffle_product($product_or_id) {
        $product = self::resolve_product($product_or_id);
        if (!$product instanceof WC_Product) {
            return false;
        }
        return get_post_meta($product->get_id(), self::META_ENABLED, true) === 'yes';
    }

    private static function resolve_product($product_or_id) {
        $product = $product_or_id instanceof WC_Product
            ? $product_or_id
            : (function_exists('wc_get_product') ? wc_get_product(absint($product_or_id)) : false);
        if ($product instanceof WC_Product && $product->is_type('variation')) {
            $product = wc_get_product($product->get_parent_id());
        }
        return $product;
    }

    public static function is_public_raffle_product($product_or_id) {
        $product = self::resolve_product($product_or_id);
        if (!$product instanceof WC_Product || !self::is_raffle_product($product)) {
            return false;
        }

        $post = get_post($product->get_id());
        if (!$post instanceof WP_Post || $post->post_status !== 'publish' || (string) $post->post_password !== '') {
            return false;
        }
        if (function_exists('is_post_publicly_viewable') && !is_post_publicly_viewable($post)) {
            return false;
        }
        if (method_exists($product, 'get_catalog_visibility') && $product->get_catalog_visibility() === 'hidden') {
            return false;
        }
        if (method_exists($product, 'is_visible') && !$product->is_visible()) {
            return false;
        }
        return true;
    }

    public static function product_by_slug($slug) {
        if (!function_exists('wc_get_product')) {
            return false;
        }
        $post = get_page_by_path(sanitize_title($slug), OBJECT, 'product');
        if (!$post) {
            return false;
        }
        $product = wc_get_product($post->ID);
        return ($product && self::is_public_raffle_product($product)) ? $product : false;
    }

    public static function selection_url($product_or_id) {
        $product = $product_or_id instanceof WC_Product ? $product_or_id : (function_exists('wc_get_product') ? wc_get_product(absint($product_or_id)) : false);
        if (!$product instanceof WC_Product) {
            return home_url('/selection/');
        }
        return home_url('/selection/' . $product->get_slug() . '/');
    }

    private static function count_active_entries($product_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'rafflelb_entries';
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE product_id=%d AND status='active'",
            $product_id
        ));
    }

    private static function active_entry_signature($product_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'rafflelb_entries';
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT COUNT(*) AS entry_count,
                    COALESCE(MAX(entry_number),0) AS max_number,
                    COALESCE(SUM(entry_number),0) AS number_sum
             FROM {$table} WHERE product_id=%d AND status='active'",
            absint($product_id)
        ));
        if (!$row) return '0:0:0';
        return implode(':', [(string)$row->entry_count, (string)$row->max_number, (string)$row->number_sum]);
    }

    private static function raw_result($product_id) {
        if (!self::available()) {
            return null;
        }
        return RaffleLB_Draw_Engine::homepage_get_draw_result($product_id);
    }

    private static function public_status($product_id, $result, $stats = false, $early_closure = null) {
        if ($result) {
            return 'complete';
        }
        if (is_array($early_closure) && ($early_closure['mode'] ?? '') === 'cancel_refund') {
            return 'cancelled';
        }
        if (is_array($stats) && isset($stats['status'])) {
            if ((string) $stats['status'] === 'winner_selected') {
                return 'complete';
            }
            if ((string) $stats['status'] === 'ready_to_draw') {
                return 'awaiting';
            }
        }
        $raw = (string) get_post_meta($product_id, self::META_STATUS, true);
        if ($raw === 'winner_selected') {
            return 'complete';
        }
        if ($raw === 'ready_to_draw') {
            return 'awaiting';
        }
        return 'live';
    }

    private static function status_label($status) {
        if ($status === 'complete') {
            return __('Selection Complete', 'rafflelb-selection-engine');
        }
        if ($status === 'awaiting') {
            return __('Awaiting Selection', 'rafflelb-selection-engine');
        }
        if ($status === 'cancelled') {
            return __('Raffle Cancelled', 'rafflelb-selection-engine');
        }
        return __('Raffle Open', 'rafflelb-selection-engine');
    }

    public static function locked_pool_page($product_id, $page = 1, $per_page = 60, $entry_number = 0) {
        if (!class_exists('RaffleLB_Draw_Engine')
            || !method_exists('RaffleLB_Draw_Engine', 'selection_bridge_locked_pool')) {
            return [];
        }

        $raw = RaffleLB_Draw_Engine::selection_bridge_locked_pool(
            absint($product_id),
            max(1, absint($page)),
            min(100, max(1, absint($per_page))),
            absint($entry_number)
        );
        if (!is_array($raw) || empty($raw['revision']) || !isset($raw['entries'])) {
            return [];
        }

        // Enforce the privacy contract again at the consumer boundary. Unknown
        // fields are discarded even if a future Draw Engine adds them.
        $entries = [];
        foreach ((array) $raw['entries'] as $row) {
            if (!is_array($row) || empty($row['entry'])) continue;
            $entries[] = [
                'entry' => sanitize_text_field((string) $row['entry']),
                'participant' => sanitize_text_field((string) ($row['participant'] ?? 'Participant ***')),
                'winning' => !empty($row['winning']),
            ];
        }
        return [
            'revision' => sanitize_text_field((string) $raw['revision']),
            'locked_at' => sanitize_text_field((string) ($raw['locked_at'] ?? '')),
            'total_locked' => absint($raw['total_locked'] ?? 0),
            'entries' => $entries,
        ];
    }

    private static function public_early_closure($product_id) {
        if (!class_exists('RaffleLB_Draw_Engine')
            || !method_exists('RaffleLB_Draw_Engine', 'selection_bridge_public_closure')) {
            return null;
        }

        $raw = RaffleLB_Draw_Engine::selection_bridge_public_closure(absint($product_id));
        if (!is_array($raw) || empty($raw['closed_early'])) return null;
        $note = sanitize_textarea_field((string) ($raw['note'] ?? ''));
        if ($note === '') return null;
        $raw_mode = sanitize_key((string) ($raw['mode'] ?? ''));
        $raw_refund_status = sanitize_key((string) ($raw['refund_status'] ?? ''));
        // Draw Engine 0.34.18.44 exposed refund status before it exposed the
        // explicit closure mode. Treat that legacy refund projection as a
        // cancellation so an upgrade cannot temporarily mislabel old closes.
        $mode = $raw_mode === 'cancel_refund'
            ? 'cancel_refund'
            : (($raw_mode === '' && in_array($raw_refund_status, ['processing', 'complete'], true)) ? 'cancel_refund' : 'selection');
        $refund_status = 'not_applicable';
        if ($mode === 'cancel_refund') {
            $refund_status = $raw_refund_status === 'complete' ? 'complete' : 'processing';
        }

        // Only the public-safe fields in the bridge contract are retained.
        return [
            'closed_early'  => true,
            'mode'          => $mode,
            'note'          => $note,
            'refund_status' => $refund_status,
        ];
    }

    private static function live_participant_name($order_id) {
        $order = function_exists('wc_get_order') ? wc_get_order(absint($order_id)) : false;
        if (!$order) return 'Participant ***';

        $first = trim(wp_strip_all_tags((string) $order->get_billing_first_name()));
        $last = trim(wp_strip_all_tags((string) $order->get_billing_last_name()));
        if ($first === '' || $last === '') return 'Participant ***';

        $first_parts = preg_split('/\s+/u', $first, -1, PREG_SPLIT_NO_EMPTY);
        $first = $first_parts ? (string) $first_parts[0] : '';
        if ($first === '') return 'Participant ***';

        $first = function_exists('mb_convert_case')
            ? mb_convert_case($first, MB_CASE_TITLE, 'UTF-8')
            : ucwords(strtolower($first));
        if (function_exists('grapheme_substr')) {
            $initial = grapheme_substr($last, 0, 1);
        } elseif (function_exists('mb_substr')) {
            $initial = mb_substr($last, 0, 1, 'UTF-8');
        } else {
            $initial = substr($last, 0, 1);
        }
        $initial = function_exists('mb_strtoupper')
            ? mb_strtoupper((string)$initial, 'UTF-8')
            : strtoupper((string)$initial);
        return $initial !== '' ? $first . ' ' . $initial . '***' : 'Participant ***';
    }

    private static function is_live_entry_state($product_id) {
        if (self::raw_result($product_id)) return false;
        $status = (string) get_post_meta($product_id, self::META_STATUS, true);
        return $status === '' || $status === 'live';
    }

    /**
     * Mutable, read-only public projection used only while the raffle is open.
     * Internal ownership is needed solely for server-side masking and is never
     * returned from this method.
     */
    public static function live_pool_page($product_id, $page = 1, $per_page = 60, $entry_number = 0) {
        $product_id = absint($product_id);
        if (!$product_id || !self::is_live_entry_state($product_id)) return [];

        global $wpdb;
        $table = $wpdb->prefix . 'rafflelb_entries';
        $page = max(1, absint($page));
        $per_page = min(100, max(1, absint($per_page)));
        $entry_number = absint($entry_number);
        $total = self::count_active_entries($product_id);

        if ($entry_number > 0) {
            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT entry_number, order_id FROM {$table}
                 WHERE product_id=%d AND status='active' AND entry_number=%d
                 ORDER BY entry_number ASC LIMIT 1",
                $product_id, $entry_number
            ));
        } else {
            $offset = ($page - 1) * $per_page;
            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT entry_number, order_id FROM {$table}
                 WHERE product_id=%d AND status='active'
                 ORDER BY entry_number ASC LIMIT %d OFFSET %d",
                $product_id, $per_page, $offset
            ));
        }

        // Closure may occur between the first state check and the query. Fail
        // closed so a mutable projection is never served for a locked raffle.
        if (!self::is_live_entry_state($product_id)) return [];

        $entries = [];
        foreach ((array) $rows as $row) {
            $entries[] = [
                'entry' => '#' . str_pad((string) absint($row->entry_number), 3, '0', STR_PAD_LEFT),
                'participant' => self::live_participant_name($row->order_id),
            ];
        }
        if (!self::is_live_entry_state($product_id)) return [];
        return [
            'mode' => 'live',
            'total_entries' => $total,
            'entries' => $entries,
        ];
    }

    private static function public_result($result) {
        if (!$result) {
            return null;
        }
        $selected_at = !empty($result->selected_at) ? (string) $result->selected_at : '';
        $winner = RaffleLB_Draw_Engine::homepage_winner_masked_name($result);
        return [
            'entry'            => '#' . str_pad((string) absint($result->entry_number), 3, '0', STR_PAD_LEFT),
            'winner'           => sanitize_text_field((string) $winner),
            'selected_at'      => $selected_at,
            'selected_display' => self::format_datetime($selected_at),
        ];
    }

    private static function format_datetime($mysql_datetime) {
        if (!$mysql_datetime) {
            return '';
        }
        return mysql2date(get_option('date_format') . ' · ' . get_option('time_format'), $mysql_datetime);
    }

    private static function process_events($product_id, $status, $closed_at, $selected_at) {
        global $wpdb;
        $table = $wpdb->prefix . 'rafflelb_entries';
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT MIN(created_at) AS first_entry_at, MAX(created_at) AS latest_entry_at FROM {$table} WHERE product_id=%d AND status='active'",
            $product_id
        ));
        $events = [];
        if ($row && !empty($row->first_entry_at)) {
            $events[] = ['time' => self::format_datetime($row->first_entry_at), 'label' => __('First eligible entry recorded', 'rafflelb-selection-engine'), 'type' => 'entry'];
        }
        if ($row && !empty($row->latest_entry_at) && $row->latest_entry_at !== $row->first_entry_at) {
            $events[] = ['time' => self::format_datetime($row->latest_entry_at), 'label' => __('Latest eligible entry recorded', 'rafflelb-selection-engine'), 'type' => 'entry'];
        }
        if ($status !== 'live' && $closed_at) {
            $events[] = ['time' => self::format_datetime($closed_at), 'label' => __('Entries locked', 'rafflelb-selection-engine'), 'type' => 'locked'];
        }
        if ($status === 'cancelled' && $closed_at) {
            $events[] = ['time' => self::format_datetime($closed_at), 'label' => __('Raffle cancelled early — no selection will take place', 'rafflelb-selection-engine'), 'type' => 'cancelled'];
        }
        if ($status === 'complete' && $selected_at) {
            $events[] = ['time' => self::format_datetime($selected_at), 'label' => __('Selection complete — result recorded', 'rafflelb-selection-engine'), 'type' => 'complete'];
        }
        return $events;
    }

    public static function snapshot($product_id) {
        $product_id = absint($product_id);
        $product = function_exists('wc_get_product') ? wc_get_product($product_id) : false;
        if (!$product instanceof WC_Product || !self::is_public_raffle_product($product)) {
            return [];
        }

        $cache_key = 'rlse_public_snapshot_0213_' . $product_id;
        $found = false;
        $cached = wp_cache_get($cache_key, 'rafflelb-selection-engine', false, $found);
        // Live pools are mutable, and awaiting raffles can become complete at any
        // moment. Only a completed full public snapshot is safe to reuse.
        if ($found && is_array($cached) && ($cached['status'] ?? '') === 'complete') {
            return $cached;
        }
        $cached = get_transient($cache_key);
        if (is_array($cached) && ($cached['status'] ?? '') === 'complete') {
            wp_cache_set($cache_key, $cached, 'rafflelb-selection-engine', self::SNAPSHOT_CACHE_TTL);
            return $cached;
        }

        $result = self::raw_result($product_id);
        $draw_id = method_exists('RaffleLB_Draw_Engine', 'shop_bridge_draw_id')
            ? RaffleLB_Draw_Engine::shop_bridge_draw_id($product)
            : $product_id;
        $stats = $draw_id && method_exists('RaffleLB_Draw_Engine', 'shop_bridge_stats')
            ? RaffleLB_Draw_Engine::shop_bridge_stats($draw_id, false)
            : false;
        $early_closure = self::public_early_closure($product_id);
        $status = self::public_status($product_id, $result, $stats, $early_closure);
        $accepting_entries = is_array($stats)
            && $status === 'live'
            && (string) $stats['status'] === 'live'
            && absint($stats['available']) > 0;
        $total = absint(get_post_meta($product_id, self::META_TOTAL, true));
        $claimed = self::count_active_entries($product_id);
        $percent = $total > 0 ? min(100, (int) round(($claimed / $total) * 100)) : 0;
        $closed_at = (string) get_post_meta($product_id, self::META_CLOSED_AT, true);
        $selected_at = $result && !empty($result->selected_at)
            ? (string) $result->selected_at
            : (string) get_post_meta($product_id, self::META_SELECTED_AT, true);
        $live_pool = $status === 'live' ? self::live_pool_page($product_id, 1, 60) : [];
        $live_pool_available = $status === 'live' && isset($live_pool['entries']);
        $locked_pool = $status === 'live' ? [] : self::locked_pool_page($product_id, 1, 60);
        $locked_pool_available = !empty($locked_pool['revision']);
        if ($live_pool_available) {
            $claimed = absint($live_pool['total_entries']);
            $percent = $total > 0 ? min(100, (int) round(($claimed / $total) * 100)) : 0;
        } elseif ($locked_pool_available) {
            $claimed = absint($locked_pool['total_locked']);
            $percent = $total > 0 ? min(100, (int) round(($claimed / $total) * 100)) : 0;
        }
        $entries = [];
        $visible_pool = $status === 'live' && $live_pool_available ? $live_pool : $locked_pool;
        if (($status === 'live' && $live_pool_available) || $locked_pool_available) {
            foreach (array_slice((array) $visible_pool['entries'], 0, 36) as $row) {
                $entries[] = absint(ltrim((string) $row['entry'], '#'));
            }
        }

        $snapshot = [
            'product_id'       => $product_id,
            'slug'             => $product->get_slug(),
            'name'             => $product->get_name(),
            'image'            => $product->get_image_id() ? (string) wp_get_attachment_image_url($product->get_image_id(), 'large') : '',
            'product_url'      => get_permalink($product_id),
            'selection_url'    => self::selection_url($product),
            'status'           => $status,
            'status_label'     => self::status_label($status),
            'accepting_entries' => $accepting_entries,
            'eligible_entries' => $claimed,
            'total_allocation' => $total,
            'percent_filled'   => $percent,
            'closed_at'        => $closed_at,
            'closed_display'   => self::format_datetime($closed_at),
            'selected_at'      => $selected_at,
            'selected_display' => self::format_datetime($selected_at),
            'early_closure'    => $early_closure,
            'entry_numbers'    => $entries,
            'remaining_hidden' => 0,
            'live_pool_available' => $live_pool_available,
            'live_pool'        => $live_pool_available ? $live_pool : null,
            'locked_pool_available' => $locked_pool_available,
            'locked_pool'      => $locked_pool_available ? $locked_pool : null,
            'result'           => self::public_result($result),
            'events'           => self::process_events($product_id, $status, $closed_at, $selected_at),
            'revision'         => md5(implode('|', [$status, $accepting_entries ? 1 : 0, is_array($stats) ? absint($stats['available']) : 0, $claimed, $closed_at, $selected_at, $result ? absint($result->entry_number) : 0, $early_closure ? $early_closure['note'] : '', $early_closure ? $early_closure['mode'] : '', $early_closure ? $early_closure['refund_status'] : '', $locked_pool_available ? $locked_pool['revision'] : 'live-' . self::active_entry_signature($product_id)])),
        ];
        if ($status === 'complete') {
            set_transient($cache_key, $snapshot, self::SNAPSHOT_CACHE_TTL);
            wp_cache_set($cache_key, $snapshot, 'rafflelb-selection-engine', self::SNAPSHOT_CACHE_TTL);
        }
        return $snapshot;
    }

    public static function raffle_cards($limit = 6, $exclude_id = 0) {
        $limit = max(1, absint($limit));
        $query = new WP_Query([
            'post_type'              => 'product',
            'post_status'            => 'publish',
            'posts_per_page'         => -1,
            'post__not_in'           => $exclude_id ? [absint($exclude_id)] : [],
            'meta_key'               => self::META_ENABLED,
            'meta_value'             => 'yes',
            'orderby'                => 'date',
            'order'                  => 'DESC',
            'no_found_rows'          => true,
            'update_post_term_cache' => false,
        ]);
        $cards = [];
        foreach ($query->posts as $post) {
            if (!self::is_public_raffle_product($post->ID)) {
                continue;
            }
            $snapshot = self::snapshot($post->ID);
            if ($snapshot && !empty($snapshot['accepting_entries'])) {
                $cards[] = $snapshot;
            }
            if (count($cards) >= $limit) {
                break;
            }
        }
        return $cards;
    }

    /**
     * Read-only public result records for shared Homepage/Winners rendering.
     * Only fields already intended for public display leave this adapter.
     */
    public static function public_results($limit = 0) {
        if (!self::available()) {
            return [];
        }

        global $wpdb;
        $table = $wpdb->prefix . 'rafflelb_draw_results';
        $limit = absint($limit);
        $sql = "SELECT product_id, entry_number, order_id, user_id, selected_at FROM {$table} ORDER BY selected_at DESC, id DESC";
        if ($limit) {
            // Leave room for records whose products are no longer publicly visible.
            $sql = $wpdb->prepare($sql . ' LIMIT %d', max(25, $limit * 5));
        }

        $items = [];
        foreach ((array) $wpdb->get_results($sql) as $row) {
            $product = function_exists('wc_get_product') ? wc_get_product(absint($row->product_id)) : false;
            if (!$product instanceof WC_Product || !self::is_public_raffle_product($product)) {
                continue;
            }

            $terms = wp_get_post_terms($product->get_id(), 'product_cat');
            $category_slug = 'other';
            $category_name = __('Other', 'rafflelb-selection-engine');
            if (!is_wp_error($terms) && $terms) {
                $term = reset($terms);
                $category_slug = sanitize_title($term->slug ?: $term->name);
                $category_name = sanitize_text_field($term->name);
            }

            $winner = sanitize_text_field((string) RaffleLB_Draw_Engine::homepage_winner_masked_name($row));
            $selected_at = !empty($row->selected_at) ? (string) $row->selected_at : '';
            $items[] = [
                'product_id'       => $product->get_id(),
                'name'             => $product->get_name(),
                'image'            => $product->get_image_id() ? (string) wp_get_attachment_image_url($product->get_image_id(), 'large') : '',
                'product_url'      => get_permalink($product->get_id()),
                'selection_url'    => self::selection_url($product),
                'entry'            => '#' . str_pad((string) absint($row->entry_number), 3, '0', STR_PAD_LEFT),
                'winner'           => $winner,
                'selected_at'      => $selected_at,
                'selected_display' => self::format_datetime($selected_at),
                'timestamp'        => strtotime($selected_at) ?: 0,
                'category_slug'    => $category_slug,
                'category_name'    => $category_name,
            ];

            if ($limit && count($items) >= $limit) {
                break;
            }
        }
        return $items;
    }

    public static function public_result_count() {
        if (!self::available()) {
            return 0;
        }
        global $wpdb;
        $table = $wpdb->prefix . 'rafflelb_draw_results';
        return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
    }

    public static function previous_results($limit = 4, $exclude_id = 0) {
        if (!self::available()) {
            return [];
        }
        global $wpdb;
        $table = $wpdb->prefix . 'rafflelb_draw_results';
        $limit = max(1, absint($limit));
        $query_limit = min(50, $limit * 3);
        if ($exclude_id) {
            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT product_id, entry_number, order_id, user_id, selected_at FROM {$table} WHERE product_id<>%d ORDER BY selected_at DESC, id DESC LIMIT %d",
                absint($exclude_id),
                $query_limit
            ));
        } else {
            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT product_id, entry_number, order_id, user_id, selected_at FROM {$table} ORDER BY selected_at DESC, id DESC LIMIT %d",
                $query_limit
            ));
        }
        $items = [];
        foreach ((array) $rows as $row) {
            $product = function_exists('wc_get_product') ? wc_get_product(absint($row->product_id)) : false;
            if (!$product instanceof WC_Product || !self::is_public_raffle_product($product)) {
                continue;
            }
            $items[] = [
                'name'      => $product->get_name(),
                'image'     => $product->get_image_id() ? (string) wp_get_attachment_image_url($product->get_image_id(), 'thumbnail') : '',
                'entry'     => '#' . str_pad((string) absint($row->entry_number), 3, '0', STR_PAD_LEFT),
                'date'      => self::format_datetime((string) $row->selected_at),
                'winner'    => sanitize_text_field((string) RaffleLB_Draw_Engine::homepage_winner_masked_name($row)),
                'url'       => self::selection_url($product),
            ];
            if (count($items) >= $limit) {
                break;
            }
        }
        return $items;
    }
}
