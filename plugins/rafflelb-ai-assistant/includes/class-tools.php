<?php

namespace RaffleLB\AI_Assistant;

if (!defined('ABSPATH')) {
    exit;
}

final class Tools {
    const MAX_RESULTS = 8;
    const MAX_QUERY_LENGTH = 120;
    const MAX_CATEGORY_MATCHES = 8;
    const MAX_CATEGORY_CANDIDATES = 80;
    const MAX_PRODUCT_CANDIDATES = 64;
    const MAX_CATEGORY_PRODUCT_CANDIDATES = 80;
    const RAFFLE_SCAN_BATCH_SIZE = 40;
    const MAX_RAFFLE_SCAN = 800;

    public function definitions() {
        $definitions = array(
            array(
                'type'        => 'function',
                'name'        => 'search_products',
                'description' => 'Search the live public WooCommerce catalogue by product name, product type, or category, including compound shopping requests such as "perfume under $150 that I can buy directly and that has a raffle". The tool extracts supported price/direct-buy/active-raffle constraints from the customer request and returns only qualifying public products when those constraints are present. Price fields are explicitly labeled; never treat a raffle entry price as a retail/direct-purchase price.',
                'strict'      => true,
                'parameters'  => array(
                    'type'                 => 'object',
                    'properties'           => array(
                        'query' => array('type' => 'string', 'description' => 'Customer catalogue terms, such as a product name, product type, or WooCommerce category.'),
                        'limit' => array('type' => 'integer', 'description' => 'Number of results, from 1 to 8.'),
                    ),
                    'required'             => array('query', 'limit'),
                    'additionalProperties' => false,
                ),
            ),
            array(
                'type'        => 'function',
                'name'        => 'get_product',
                'description' => 'Get customer-safe public information for one published WooCommerce product.',
                'strict'      => true,
                'parameters'  => array(
                    'type'                 => 'object',
                    'properties'           => array(
                        'product_id' => array('type' => 'integer', 'description' => 'Positive WooCommerce product ID.'),
                    ),
                    'required'             => array('product_id'),
                    'additionalProperties' => false,
                ),
            ),
            array(
                'type'        => 'function',
                'name'        => 'list_active_raffles',
                'description' => 'List public, published raffle products that are currently live and have remaining entries.',
                'strict'      => true,
                'parameters'  => array(
                    'type'                 => 'object',
                    'properties'           => array(
                        'limit' => array('type' => 'integer', 'description' => 'Number of results, from 1 to 8.'),
                    ),
                    'required'             => array('limit'),
                    'additionalProperties' => false,
                ),
            ),
            array(
                'type'        => 'function',
                'name'        => 'get_raffle_status',
                'description' => 'Get customer-safe aggregate progress for one public raffle product. Never returns owners, tickets, orders, or internal controls.',
                'strict'      => true,
                'parameters'  => array(
                    'type'                 => 'object',
                    'properties'           => array(
                        'product_id' => array('type' => 'integer', 'description' => 'Positive WooCommerce product ID.'),
                    ),
                    'required'             => array('product_id'),
                    'additionalProperties' => false,
                ),
            ),
        );

        if ($this->personal_user_id()) {
            $definitions[] = array(
                'type'        => 'function',
                'name'        => 'get_my_raffles',
                'description' => 'Read only the currently authenticated customer\'s own RaffleLB raffle entries and ticket numbers. Use for questions such as "what raffles have I entered?", "what are my ticket numbers?", or "did I win?". Never accepts or looks up another user.',
                'strict'      => true,
                'parameters'  => array(
                    'type'                 => 'object',
                    'properties'           => array(
                        'limit' => array('type' => 'integer', 'description' => 'Number of raffle products to return, from 1 to 8.'),
                    ),
                    'required'             => array('limit'),
                    'additionalProperties' => false,
                ),
            );
            $definitions[] = array(
                'type'        => 'function',
                'name'        => 'get_my_orders',
                'description' => 'Read only the currently authenticated customer\'s own WooCommerce orders and current order status. Use an empty order_number to return recent orders, or supply the customer-visible order number they asked about. Never returns addresses, email, phone, payment credentials, or another customer\'s order.',
                'strict'      => true,
                'parameters'  => array(
                    'type'                 => 'object',
                    'properties'           => array(
                        'order_number' => array('type' => 'string', 'description' => 'Customer-visible order number without or with #, or an empty string for recent orders.'),
                        'limit'        => array('type' => 'integer', 'description' => 'Number of orders to return, from 1 to 8.'),
                    ),
                    'required'             => array('order_number', 'limit'),
                    'additionalProperties' => false,
                ),
            );
            $definitions[] = array(
                'type'        => 'function',
                'name'        => 'get_my_raffle_points',
                'description' => 'Read only the currently authenticated customer\'s own Raffle Points balance and current redemption value/rate. Never changes points and never reads another customer.',
                'strict'      => true,
                'parameters'  => array(
                    'type'                 => 'object',
                    'properties'           => array(),
                    'required'             => array(),
                    'additionalProperties' => false,
                ),
            );
        }

        return $definitions;
    }

    /**
     * Resolve stable public platform questions that should behave identically
     * for signed-out visitors and signed-in customers. Returning an empty
     * string means the message is not one of these deterministic public intents.
     */
    public function direct_public_answer($message) {
        if (!is_string($message)) {
            return '';
        }

        $plain = strtolower(trim(wp_strip_all_tags($message, true)));
        if ($plain === '') {
            return '';
        }

        // Customers may still call this a "draw engine" conversationally,
        // while the public RaffleLB product name is Selection Engine. Keep this
        // answer independent of authentication and AI/tool routing.
        $asks_about_selection_engine = (bool) preg_match(
            '/\b(?:selection\s+engine|draw\s+engine|winner\s+selection\s+engine)\b/u',
            $plain
        );

        if ($asks_about_selection_engine) {
            $url = home_url('/selection-engine/');
            return 'Yes. RaffleLB has a public Selection Engine. We call it the Selection Engine rather than a Draw Engine. It shows the customer-facing selection process, including eligible-entry locking, selection stages, public raffle status, and recorded results. You can view it here: ' . esc_url_raw($url);
        }

        return '';
    }

    /**
     * Resolve common personal account questions directly from the currently
     * authenticated WordPress customer. Returning an empty string means the
     * message is not one of the supported deterministic personal intents.
     */
    public function direct_personal_answer($message, array $history = array()) {
        if (!Settings::personal_assistance_enabled() || !is_string($message)) {
            return '';
        }

        $plain = strtolower(trim(wp_strip_all_tags($message, true)));
        if ($plain === '') {
            return '';
        }

        // Own raffle / entry / ticket questions. Keep this intentionally
        // narrower than public questions such as "what raffles are active?".
        $mentions_raffle = (bool) preg_match('/\b(?:raffles?|entries|entry|tickets?|ticket\s+numbers?)\b/u', $plain);
        $personal_raffle = (bool) preg_match('/\b(?:my|mine|i|am|registered|entered|joined|participated|tickets?|entries|won|win)\b/u', $plain);
        if ($mentions_raffle && $personal_raffle) {
            $result = $this->execute('get_my_raffles', array('limit' => self::MAX_RESULTS));
            return is_array($result) ? $this->personal_fallback_text('get_my_raffles', $result) : '';
        }

        // Own Raffle Points balance/value questions.
        if (preg_match('/\b(?:my\s+)?(?:raffle\s+)?points?\b/u', $plain)
            && preg_match('/\b(?:my|balance|have|worth|available|points?)\b/u', $plain)) {
            $result = $this->execute('get_my_raffle_points', array());
            return is_array($result) ? $this->personal_fallback_text('get_my_raffle_points', $result) : '';
        }

        // Own order list/status questions. Resolve these deterministically for the
        // signed-in customer so normal account questions do not depend on OpenAI.
        $mentions_order = (bool) preg_match('/\borders?\b/u', $plain);
        $personal_order = (bool) preg_match('/\b(?:my|mine|me|i|am|have|placed|made|bought|purchased|registered|recent|status|where)\b/u', $plain);
        $explicit_personal_order = $mentions_order && $personal_order;

        // Accept natural short follow-ups with optional whitespace/punctuation,
        // e.g. "and orders ?", "what about my orders?", or simply "orders?".
        $contextual_order_followup = (bool) preg_match('/^(?:and\s+)?(?:(?:what|how)\s+about\s+)?(?:my\s+)?orders?\s*[?!.]*$/u', $plain)
            && $this->history_has_personal_account_context($history);
        if ($explicit_personal_order || $contextual_order_followup) {
            $order_number = '';

            // Only treat text as a specific order lookup when a genuine order
            // identifier follows "order". Never let the plural "orders"
            // become order number "s".
            if (preg_match('/\border(?:\s+(?:number|no\.?))?\s*#\s*([a-z0-9-]{1,40})\b/i', $plain, $match)
                || preg_match('/\border(?:\s+(?:number|no\.?))?\s+([0-9][a-z0-9-]{0,39})\b/i', $plain, $match)) {
                $order_number = isset($match[1]) ? (string) $match[1] : '';
            }

            $result = $this->execute('get_my_orders', array(
                'order_number' => $order_number,
                'limit'        => self::MAX_RESULTS,
            ));
            return is_array($result) ? $this->personal_fallback_text('get_my_orders', $result) : '';
        }

        return '';
    }

    /**
     * Detect whether a short follow-up belongs to an already-established
     * signed-in account conversation. Only recent customer-safe text is
     * inspected; no identifiers or private profile data are inferred.
     */
    private function history_has_personal_account_context(array $history) {
        if (!$history) {
            return false;
        }

        $recent = array_slice($history, -4);
        foreach (array_reverse($recent) as $item) {
            if (!is_array($item) || empty($item['content']) || !is_string($item['content'])) {
                continue;
            }
            $text = strtolower(trim(wp_strip_all_tags($item['content'], true)));
            if ($text === '') {
                continue;
            }

            if (preg_match('/\b(?:my|your account|this account|registered to|entered|raffle entries|ticket numbers?|recent orders?|raffle points balance|confirmed raffle entries)\b/u', $text)) {
                return true;
            }
        }

        return false;
    }

    public function execute($name, array $arguments) {
        switch ($name) {
            case 'search_products':
                return $this->search_products($arguments);
            case 'get_product':
                return $this->get_product_tool($arguments);
            case 'list_active_raffles':
                return $this->list_active_raffles($arguments);
            case 'get_raffle_status':
                return $this->get_raffle_status_tool($arguments);
            case 'get_my_raffles':
                return $this->get_my_raffles($arguments);
            case 'get_my_orders':
                return $this->get_my_orders($arguments);
            case 'get_my_raffle_points':
                return $this->get_my_raffle_points();
            default:
                return array('ok' => false, 'error' => 'That tool is not available.');
        }
    }

    private function personal_user_id() {
        if (!Settings::personal_assistance_enabled() || !is_user_logged_in()) {
            return 0;
        }
        $user_id = absint(get_current_user_id());
        return $user_id > 0 ? $user_id : 0;
    }

    private function personal_access_error() {
        $account_url = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('myaccount') : wp_login_url(home_url('/'));
        return array(
            'ok'           => false,
            'auth_required'=> true,
            'error'        => 'Please sign in to RaffleLB to access your own account information.',
            'account_url'  => is_string($account_url) ? $account_url : '',
        );
    }

    private function get_my_raffles(array $arguments) {
        $user_id = $this->personal_user_id();
        if (!$user_id) {
            return $this->personal_access_error();
        }
        $limit = $this->validated_limit($arguments['limit'] ?? 0);
        if (!$limit) {
            return array('ok' => false, 'error' => 'A valid result limit is required.');
        }
        if (!$this->core_available()) {
            return $this->unavailable('Your raffle entries are currently unavailable.');
        }

        global $wpdb;
        $entries_exists = \RaffleLB\Core\Database::exists('entries');
        if (is_wp_error($entries_exists) || !$entries_exists) {
            return $this->unavailable('Your raffle entries are currently unavailable.');
        }
        $entries = \RaffleLB\Core\Database::table('entries');
        $expected_entries = $wpdb->prefix . \RaffleLB\Core\Contracts::ENTRY_TABLE;
        if (!is_string($entries) || $entries !== $expected_entries) {
            return $this->unavailable('Your raffle entries are currently unavailable.');
        }

        // Entry history is useful on its own. Winner-result metadata is an
        // optional enhancement here so a temporary/migration issue with the
        // results table cannot make a customer's confirmed entries disappear.
        $results_available = false;
        $results = '';
        $results_exists = \RaffleLB\Core\Database::exists('results');
        if (!is_wp_error($results_exists) && $results_exists) {
            $candidate_results = \RaffleLB\Core\Database::table('results');
            $expected_results = $wpdb->prefix . \RaffleLB\Core\Contracts::RESULT_TABLE;
            if (is_string($candidate_results) && $candidate_results === $expected_results) {
                $results = $candidate_results;
                $results_available = true;
            }
        }

        $previous_suppression = $wpdb->suppress_errors(true);
        $groups = $wpdb->get_results($wpdb->prepare(
            "SELECT product_id, COUNT(*) AS entry_count, MAX(created_at) AS latest_entry\n             FROM {$entries}\n             WHERE user_id=%d AND status=%s\n             GROUP BY product_id\n             ORDER BY latest_entry DESC\n             LIMIT %d",
            $user_id,
            'active',
            $limit
        ));
        $total_raffles = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT product_id) FROM {$entries} WHERE user_id=%d AND status=%s",
            $user_id,
            'active'
        ));
        $total_entries = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$entries} WHERE user_id=%d AND status=%s",
            $user_id,
            'active'
        ));
        $wins_count = 0;
        if ($results_available) {
            $wins_count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$results} WHERE user_id=%d",
                $user_id
            ));
        }
        $database_error = (string) $wpdb->last_error;
        $wpdb->suppress_errors($previous_suppression);
        if ($database_error !== '' || !is_array($groups)) {
            return $this->unavailable('Your raffle entries are currently unavailable.');
        }

        $raffles = array();
        foreach ($groups as $group) {
            $product_id = absint($group->product_id);
            if (!$product_id) {
                continue;
            }

            $previous_suppression = $wpdb->suppress_errors(true);
            $numbers = $wpdb->get_col($wpdb->prepare(
                "SELECT entry_number FROM {$entries}\n                 WHERE user_id=%d AND product_id=%d AND status=%s\n                 ORDER BY entry_number ASC\n                 LIMIT 100",
                $user_id,
                $product_id,
                'active'
            ));
            $result = null;
            if ($results_available) {
                $result = $wpdb->get_row($wpdb->prepare(
                    "SELECT entry_number, user_id, selected_at, fulfillment_status, contacted_at, claimed_at, fulfilled_at\n                     FROM {$results} WHERE product_id=%d LIMIT 1",
                    $product_id
                ));
            }
            $database_error = (string) $wpdb->last_error;
            $wpdb->suppress_errors($previous_suppression);
            if ($database_error !== '') {
                return $this->unavailable('Your raffle entries are currently unavailable.');
            }

            $product = function_exists('wc_get_product') ? wc_get_product($product_id) : false;
            $name = $product instanceof \WC_Product ? $product->get_name() : get_the_title($product_id);
            if (!is_string($name) || trim($name) === '') {
                $name = 'Raffle #' . $product_id;
            }
            $permalink = get_permalink($product_id);
            $entry_numbers = array();
            foreach ((array) $numbers as $number) {
                $number = absint($number);
                if ($number > 0) {
                    $entry_numbers[] = '#' . str_pad((string) $number, 3, '0', STR_PAD_LEFT);
                }
            }

            $customer_status = 'active';
            $public_status = null;
            $remaining_entries = null;
            if ($result) {
                $public_status = 'completed';
                $customer_status = absint($result->user_id) === $user_id ? 'won' : 'past';
            } elseif ($product instanceof \WC_Product && $this->is_public_product($product)) {
                $status = $this->raffle_status($product);
                if (!empty($status['ok'])) {
                    $public_status = isset($status['public_status']) ? (string) $status['public_status'] : null;
                    $remaining_entries = isset($status['remaining_entries']) ? (int) $status['remaining_entries'] : null;
                    if ($public_status === 'closed') {
                        $customer_status = 'awaiting_selection';
                    }
                }
            } else {
                $stored_status = (string) get_post_meta($product_id, \RaffleLB\Core\Contracts::META_DRAW_STATUS, true);
                if ($stored_status === 'winner_selected') {
                    $public_status = 'completed';
                    $customer_status = 'past';
                } elseif ($stored_status === 'ready_to_draw') {
                    $public_status = 'closed';
                    $customer_status = 'awaiting_selection';
                }
            }

            $item = array(
                'product_id'              => $product_id,
                'name'                    => sanitize_text_field(wp_strip_all_tags($name)),
                'customer_status'         => $customer_status,
                'raffle_public_status'    => $public_status,
                'entry_count'             => max(0, (int) $group->entry_count),
                'entry_numbers'           => $entry_numbers,
                'entry_numbers_truncated' => max(0, (int) $group->entry_count) > count($entry_numbers),
                'remaining_entries'       => $remaining_entries,
                'latest_entry_at'         => !empty($group->latest_entry) ? mysql2date('c', (string) $group->latest_entry, false) : null,
                'permalink'               => is_string($permalink) ? $permalink : '',
            );

            if ($result && absint($result->user_id) === $user_id) {
                $item['winning_entry_number'] = '#' . str_pad((string) absint($result->entry_number), 3, '0', STR_PAD_LEFT);
                $item['winner_selected_at'] = !empty($result->selected_at) ? mysql2date('c', (string) $result->selected_at, false) : null;
                $allowed_fulfillment = array('pending', 'contacted', 'claimed', 'fulfilled');
                $fulfillment = sanitize_key((string) $result->fulfillment_status);
                $item['fulfillment_status'] = in_array($fulfillment, $allowed_fulfillment, true) ? $fulfillment : 'pending';
            }
            $raffles[] = $item;
        }

        $account_url = function_exists('wc_get_account_endpoint_url') ? wc_get_account_endpoint_url('rafflelb-entries') : '';
        return array(
            'ok'                    => true,
            'total_raffles_entered' => max(0, (int) $total_raffles),
            'total_active_records'  => max(0, (int) $total_entries),
            'lifetime_wins'         => max(0, (int) $wins_count),
            'raffles'               => $raffles,
            'my_raffles_url'        => is_string($account_url) ? $account_url : '',
        );
    }

    private function get_my_orders(array $arguments) {
        $user_id = $this->personal_user_id();
        if (!$user_id) {
            return $this->personal_access_error();
        }
        if (!function_exists('wc_get_orders') || !function_exists('wc_get_order_statuses')) {
            return $this->unavailable('Your order information is currently unavailable.');
        }
        $limit = $this->validated_limit($arguments['limit'] ?? 0);
        if (!$limit) {
            return array('ok' => false, 'error' => 'A valid result limit is required.');
        }
        $requested = isset($arguments['order_number']) && is_string($arguments['order_number'])
            ? sanitize_text_field($arguments['order_number']) : '';
        $requested = ltrim(trim($this->truncate($requested, 40)), '#');
        $query_limit = $requested !== '' ? 50 : $limit;

        try {
            $orders = wc_get_orders(array(
                'customer_id' => $user_id,
                'limit'       => $query_limit,
                'orderby'     => 'date',
                'order'       => 'DESC',
                'status'      => array_keys(wc_get_order_statuses()),
            ));
        } catch (\Throwable $error) {
            return $this->unavailable('Your order information is currently unavailable.');
        }
        if (!is_array($orders)) {
            return $this->unavailable('Your order information is currently unavailable.');
        }

        if ($requested !== '') {
            $orders = array_values(array_filter($orders, static function($order) use ($requested) {
                if (!$order instanceof \WC_Order) {
                    return false;
                }
                return ltrim((string) $order->get_order_number(), '#') === $requested;
            }));
        }
        $orders = array_slice($orders, 0, $limit);

        $out = array();
        foreach ($orders as $order) {
            if (!$order instanceof \WC_Order || absint($order->get_customer_id()) !== $user_id) {
                continue;
            }
            $items_out = array();
            $kinds = array();
            foreach ($order->get_items('line_item') as $item) {
                if (!$item instanceof \WC_Order_Item_Product) {
                    continue;
                }
                $mode = (string) $item->get_meta('_rafflelb_purchase_mode', true) === 'buy_now' ? 'direct_purchase' : 'raffle_entry';
                $kinds[$mode] = true;
                if (count($items_out) < 6) {
                    $items_out[] = array(
                        'name'          => sanitize_text_field(wp_strip_all_tags((string) $item->get_name())),
                        'quantity'      => max(1, (int) $item->get_quantity()),
                        'purchase_mode' => $mode,
                    );
                }
            }
            $order_kind = isset($kinds['direct_purchase'], $kinds['raffle_entry']) ? 'mixed'
                : (isset($kinds['direct_purchase']) ? 'direct_purchase' : 'raffle_entries');
            $status = sanitize_key((string) $order->get_status());
            $status_label = function_exists('wc_get_order_status_name') ? wc_get_order_status_name($status) : ucfirst($status);
            if ($status === 'processing' && $order_kind === 'raffle_entries') {
                $status_label = 'Paid';
            }
            $created = $order->get_date_created();
            $out[] = array(
                'order_number' => (string) $order->get_order_number(),
                'status'       => $status,
                'status_label' => sanitize_text_field(wp_strip_all_tags((string) $status_label)),
                'order_kind'   => $order_kind,
                'created_at'   => $created ? $created->date('c') : null,
                'total'        => $this->plain_price_html($order->get_formatted_order_total()),
                'items'        => $items_out,
                'items_truncated' => count($order->get_items('line_item')) > count($items_out),
                'view_url'     => $order->get_view_order_url(),
            );
        }

        $orders_url = function_exists('wc_get_account_endpoint_url') ? wc_get_account_endpoint_url('orders') : '';
        return array(
            'ok'             => true,
            'requested_order'=> $requested,
            'orders'         => $out,
            'orders_url'     => is_string($orders_url) ? $orders_url : '',
            'tracking_note'  => 'This tool reports the WooCommerce order status only. It does not provide live courier/GPS tracking.',
        );
    }

    private function get_my_raffle_points() {
        $user_id = $this->personal_user_id();
        if (!$user_id) {
            return $this->personal_access_error();
        }

        $balance = 0;
        if (class_exists('RaffleLB_Referral_Points') && is_callable(array('RaffleLB_Referral_Points', 'points'))) {
            $balance = max(0, (int) \RaffleLB_Referral_Points::points($user_id));
        } else {
            $balance = max(0, (int) get_user_meta($user_id, '_rafflelb_ref_points', true));
        }

        $points_per_dollar = 1.0;
        $spend_points_per_dollar = 10.0;
        if (class_exists('RaffleLB_Referral_Points') && is_callable(array('RaffleLB_Referral_Points', 'settings'))) {
            $point_settings = \RaffleLB_Referral_Points::settings();
            if (is_array($point_settings)) {
                $points_per_dollar = max(0, (float) ($point_settings['points_per_dollar'] ?? 1));
                $spend_points_per_dollar = max(0.01, (float) ($point_settings['spend_points_per_dollar'] ?? 10));
            }
        } else {
            $point_settings = get_option('rafflelb_referral_settings', array());
            if (is_array($point_settings)) {
                $points_per_dollar = max(0, (float) ($point_settings['points_per_dollar'] ?? 1));
                $spend_points_per_dollar = max(0.01, (float) ($point_settings['spend_points_per_dollar'] ?? 10));
            }
        }

        $refer_url = function_exists('wc_get_account_endpoint_url') ? wc_get_account_endpoint_url('refer-and-earn') : '';
        return array(
            'ok'                         => true,
            'balance_points'             => $balance,
            'checkout_value'             => $this->formatted_money($balance / $spend_points_per_dollar),
            'redemption_rate'            => $spend_points_per_dollar . ' points = $1',
            'referral_earn_rate'          => $points_per_dollar . ' point(s) per $1 of qualifying referred-friend spend',
            'full_payment_only'           => true,
            'split_payment_with_cash'     => false,
            'refer_and_earn_url'          => is_string($refer_url) ? $refer_url : '',
        );
    }

    /**
     * Safe, deterministic text for a customer-scoped read-only tool result.
     * Used only as a resilience fallback if the AI provider fails after the
     * server has already completed the authenticated lookup.
     */
    public function personal_fallback_text($name, array $result) {
        if (!in_array($name, array('get_my_raffles', 'get_my_orders', 'get_my_raffle_points'), true)) {
            return '';
        }

        if (empty($result['ok'])) {
            $error = isset($result['error']) && is_string($result['error']) ? trim($result['error']) : '';
            return $error !== '' ? $error : 'Your account information is currently unavailable. Please try again shortly.';
        }

        if ($name === 'get_my_raffles') {
            $raffles = isset($result['raffles']) && is_array($result['raffles']) ? $result['raffles'] : array();
            if (!$raffles) {
                return 'You do not currently have any confirmed raffle entries on this account. Your entries will appear in My Account after you enter a raffle.';
            }

            $lines = array('Here are the raffles registered to your account:');
            foreach (array_slice($raffles, 0, self::MAX_RESULTS) as $raffle) {
                if (!is_array($raffle)) {
                    continue;
                }
                $name_text = isset($raffle['name']) ? sanitize_text_field((string) $raffle['name']) : 'Raffle';
                $entry_count = isset($raffle['entry_count']) ? max(0, (int) $raffle['entry_count']) : 0;
                $numbers = isset($raffle['entry_numbers']) && is_array($raffle['entry_numbers']) ? array_values(array_filter(array_map('sanitize_text_field', $raffle['entry_numbers']))) : array();
                $status = isset($raffle['customer_status']) ? sanitize_key((string) $raffle['customer_status']) : 'active';
                $status_label = $status === 'won' ? 'Won' : ($status === 'awaiting_selection' ? 'Awaiting selection' : ($status === 'past' ? 'Completed' : 'Active'));
                $line = '- **' . $name_text . '** — ' . $status_label . '; ' . $entry_count . ' entr' . ($entry_count === 1 ? 'y' : 'ies');
                if ($numbers) {
                    $line .= '; tickets: ' . implode(', ', $numbers);
                    if (!empty($raffle['entry_numbers_truncated'])) {
                        $line .= ' (showing the first ' . count($numbers) . ')';
                    }
                }
                if ($status === 'won' && !empty($raffle['winning_entry_number'])) {
                    $line .= '; winning ticket: ' . sanitize_text_field((string) $raffle['winning_entry_number']);
                }
                $lines[] = $line . '.';
            }
            if (!empty($result['my_raffles_url']) && is_string($result['my_raffles_url'])) {
                $lines[] = 'You can also review them in [My Raffles](' . esc_url_raw($result['my_raffles_url']) . ').';
            }
            return implode("\n", $lines);
        }

        if ($name === 'get_my_orders') {
            $orders = isset($result['orders']) && is_array($result['orders']) ? $result['orders'] : array();
            if (!$orders) {
                return !empty($result['requested_order'])
                    ? 'I could not find that order in your signed-in account.'
                    : 'You do not currently have any orders on this account.';
            }
            $lines = array(count($orders) === 1 ? 'Here is your order:' : 'Here are your recent orders:');
            foreach (array_slice($orders, 0, self::MAX_RESULTS) as $order) {
                if (!is_array($order)) {
                    continue;
                }
                $number = isset($order['order_number']) ? sanitize_text_field((string) $order['order_number']) : '';
                $status = isset($order['status_label']) ? sanitize_text_field((string) $order['status_label']) : 'Unknown';
                $total = isset($order['total']) ? sanitize_text_field((string) $order['total']) : '';
                $line = '- **Order #' . $number . '** — ' . $status;
                if ($total !== '') {
                    $line .= '; total: ' . $total;
                }
                $lines[] = $line . '.';
            }
            $lines[] = 'This is the WooCommerce order status, not live courier/GPS tracking.';
            return implode("\n", $lines);
        }

        $balance = isset($result['balance_points']) ? max(0, (int) $result['balance_points']) : 0;
        $value = isset($result['checkout_value']) ? sanitize_text_field((string) $result['checkout_value']) : '';
        $rate = isset($result['redemption_rate']) ? sanitize_text_field((string) $result['redemption_rate']) : '';
        $text = 'Your current Raffle Points balance is **' . $balance . ' points**';
        if ($value !== '') {
            $text .= ', currently worth **' . $value . '** at checkout';
        }
        $text .= '.';
        if ($rate !== '') {
            $text .= ' Current rate: ' . $rate . '.';
        }
        $text .= ' Raffle Points must cover the full eligible payment amount; they cannot be combined with cash.';
        return $text;
    }

    private function search_products(array $arguments) {
        if (!$this->woocommerce_available() || !class_exists('WC_Data_Store')) {
            return $this->unavailable('Product search is currently unavailable.');
        }

        $query = isset($arguments['query']) && is_string($arguments['query']) ? sanitize_text_field($arguments['query']) : '';
        $query = $this->truncate(trim($query), self::MAX_QUERY_LENGTH);
        $limit = $this->validated_limit($arguments['limit'] ?? 0);
        if ($query === '' || !$limit) {
            return array('ok' => false, 'error' => 'A valid search query and limit are required.');
        }

        $intent = $this->catalog_search_intent($query);
        $search_term = $intent['search_term'] !== '' ? $intent['search_term'] : $query;

        try {
            $data_store = \WC_Data_Store::load('product');
            if (!is_object($data_store) || !is_callable(array($data_store, 'search_products'))) {
                return $this->unavailable('Product search is currently unavailable.');
            }
            // Search a wider candidate set because server-side shopping constraints
            // (for example direct price <= $150 + active raffle) are applied after
            // WooCommerce's textual relevance search.
            $candidate_limit = max($limit, min(self::MAX_PRODUCT_CANDIDATES, $limit * 8));
            $product_ids = $data_store->search_products($search_term, '', false, false, $candidate_limit);
        } catch (\Throwable $error) {
            return $this->unavailable('Product search is currently unavailable.');
        }

        if (!is_array($product_ids)) {
            return $this->unavailable('Product search is currently unavailable.');
        }

        $results = array();
        $seen_product_ids = array();
        foreach ($product_ids as $product_id) {
            $product = wc_get_product(absint($product_id));
            if (!$this->is_public_product($product) || !$this->catalog_product_matches_intent($product, $intent)) {
                continue;
            }
            $public_product_id = absint($product->get_id());
            if (isset($seen_product_ids[$public_product_id])) {
                continue;
            }
            $seen_product_ids[$public_product_id] = true;
            $results[] = $this->public_product($product);
            if (count($results) >= $limit) {
                break;
            }
        }

        $matched_categories = array();
        if (count($results) < $limit) {
            $categories = $this->matching_product_categories($search_term);
            foreach ($categories as $category) {
                if (count($results) >= $limit) {
                    break;
                }

                try {
                    // Inspect a bounded but sufficiently broad set from each matching
                    // category so a qualifying item is not missed merely because newer
                    // products fail the requested price/mode constraints.
                    $category_products = wc_get_products(array(
                        'status'     => 'publish',
                        'visibility' => 'visible',
                        'category'   => array($category->slug),
                        'limit'      => self::MAX_CATEGORY_PRODUCT_CANDIDATES,
                        'orderby'    => 'date',
                        'order'      => 'DESC',
                    ));
                } catch (\Throwable $error) {
                    continue;
                }
                if (!is_array($category_products)) {
                    continue;
                }

                $category_has_qualifying_product = false;
                foreach ($category_products as $product) {
                    if (!$this->is_public_product($product) || !$this->catalog_product_matches_intent($product, $intent)) {
                        continue;
                    }
                    $category_has_qualifying_product = true;
                    $product_id = absint($product->get_id());
                    if (isset($seen_product_ids[$product_id])) {
                        continue;
                    }
                    $seen_product_ids[$product_id] = true;
                    $results[] = $this->public_product($product);
                    if (count($results) >= $limit) {
                        break;
                    }
                }

                if ($category_has_qualifying_product) {
                    $category_name = sanitize_text_field(wp_strip_all_tags((string) $category->name));
                    if ($category_name !== '' && !in_array($category_name, $matched_categories, true)) {
                        $matched_categories[] = $category_name;
                    }
                }
            }
        }

        return array(
            'ok'                 => true,
            'search_term'        => $search_term,
            'applied_filters'    => array(
                'minimum_direct_purchase_price' => $intent['minimum_direct_purchase_price'],
                'maximum_direct_purchase_price' => $intent['maximum_direct_purchase_price'],
                'require_direct_purchase'        => (bool) $intent['require_direct_purchase'],
                'require_active_raffle'          => (bool) $intent['require_active_raffle'],
            ),
            'products'           => $results,
            'matched_categories' => $matched_categories,
            'count'              => count($results),
        );
    }

    private function catalog_search_intent($query) {
        $plain = function_exists('remove_accents') ? remove_accents((string) $query) : (string) $query;
        $plain = strtolower($plain);

        $maximum_price = null;
        $minimum_price = null;

        if (preg_match('/(?:under|below|less\\s+than|up\\s+to|no\\s+more\\s+than|max(?:imum)?(?:\\s+price)?(?:\\s+of)?)\\s*\\$?\\s*([0-9]+(?:\\.[0-9]+)?)/i', $plain, $match)) {
            $maximum_price = (float) $match[1];
        }
        if (preg_match('/(?:over|above|more\\s+than|at\\s+least|min(?:imum)?(?:\\s+price)?(?:\\s+of)?)\\s*\\$?\\s*([0-9]+(?:\\.[0-9]+)?)/i', $plain, $match)) {
            $minimum_price = (float) $match[1];
        }

        $require_direct_purchase = (bool) preg_match('/\\b(?:buy\\s+direct(?:ly)?|purchase\\s+direct(?:ly)?|direct\\s+purchase|directly|buy\\s+now|retail|store\\s+purchase)\\b/i', $plain);
        $require_active_raffle = (bool) preg_match('/\\b(?:active\\s+)?raffles?\\b/i', $plain);

        return array(
            'search_term'                   => $this->catalog_core_search_term($query),
            'minimum_direct_purchase_price' => $minimum_price,
            'maximum_direct_purchase_price' => $maximum_price,
            'require_direct_purchase'        => $require_direct_purchase,
            'require_active_raffle'          => $require_active_raffle,
        );
    }

    private function catalog_core_search_term($query) {
        $stop_tokens = array(
            'a', 'an', 'and', 'any', 'are', 'available', 'also', 'at', 'below', 'buy',
            'can', 'catalog', 'catalogue', 'category', 'could', 'current', 'direct',
            'directly', 'do', 'does', 'entry', 'for', 'from', 'has', 'have', 'i', 'in',
            'item', 'kind', 'less', 'live', 'looking', 'max', 'maximum', 'me', 'min',
            'minimum', 'more', 'need', 'no', 'normally', 'of', 'on', 'or', 'over',
            'please', 'price', 'priced', 'product', 'purchase', 'raffle', 'recommend',
            'recommendation', 'retail', 'sell', 'selling', 'show', 'store', 'than', 'that',
            'the', 'there', 'to', 'type', 'under', 'up', 'want', 'what', 'which', 'with',
            'you', 'your',
        );

        $tokens = array_values(array_filter($this->normalized_catalog_tokens($query), function ($token) use ($stop_tokens) {
            if ($token === '' || strlen($token) < 2 || in_array($token, $stop_tokens, true)) {
                return false;
            }
            return !preg_match('/^[0-9]+(?:\\.[0-9]+)?$/', $token);
        }));

        return implode(' ', array_slice(array_values(array_unique($tokens)), 0, 6));
    }

    private function catalog_product_matches_intent(\WC_Product $product, array $intent) {
        $state = $this->product_commerce_state($product, false);

        if (!empty($intent['require_direct_purchase']) && !$state['direct_purchase_available']) {
            return false;
        }

        $minimum_price = $intent['minimum_direct_purchase_price'];
        $maximum_price = $intent['maximum_direct_purchase_price'];
        if ($minimum_price !== null || $maximum_price !== null) {
            if (!$state['direct_purchase_available'] || $state['direct_purchase_price'] === null) {
                return false;
            }
            $direct_price = (float) $state['direct_purchase_price'];
            if ($minimum_price !== null && $direct_price < (float) $minimum_price) {
                return false;
            }
            if ($maximum_price !== null && $direct_price > (float) $maximum_price) {
                return false;
            }
        }

        if (!empty($intent['require_active_raffle'])) {
            if (!$state['raffle_enabled']) {
                return false;
            }
            $live_state = $this->product_commerce_state($product, true);
            if (!$live_state['raffle_entry_available']) {
                return false;
            }
        }

        return true;
    }

    private function matching_product_categories($query) {
        if (!function_exists('get_terms') || !function_exists('taxonomy_exists') || !taxonomy_exists('product_cat')) {
            return array();
        }

        $tokens = $this->catalog_query_tokens($query);
        $broad_query = !$tokens && $this->is_broad_catalog_query($query);
        if (!$tokens && !$broad_query) {
            return array();
        }

        $candidate_terms = array();
        if ($broad_query) {
            $terms = get_terms(array(
                'taxonomy'   => 'product_cat',
                'hide_empty' => false,
                'number'     => self::MAX_CATEGORY_CANDIDATES,
                'orderby'    => 'count',
                'order'      => 'DESC',
            ));
            if (!is_wp_error($terms) && is_array($terms)) {
                $candidate_terms = $terms;
            }
        } else {
            foreach ($tokens as $token) {
                $terms = get_terms(array(
                    'taxonomy'   => 'product_cat',
                    'hide_empty' => false,
                    'search'     => $token,
                    'number'     => 50,
                    'orderby'    => 'name',
                    'order'      => 'ASC',
                ));
                if (is_wp_error($terms) || !is_array($terms)) {
                    continue;
                }
                foreach ($terms as $term) {
                    if (!isset($term->term_id, $term->name, $term->slug)) {
                        continue;
                    }
                    $candidate_terms[(int) $term->term_id] = $term;
                    if (count($candidate_terms) >= self::MAX_CATEGORY_CANDIDATES) {
                        break 2;
                    }
                }
            }
        }

        $matches = array();
        foreach ($candidate_terms as $term) {
            if (!isset($term->name, $term->slug)) {
                continue;
            }
            $score = $broad_query ? 1 : $this->category_match_score($tokens, $term->name, $term->slug);
            if ($score < 1) {
                continue;
            }
            $matches[] = array('score' => $score, 'term' => $term);
        }

        usort($matches, function ($left, $right) {
            if ($left['score'] === $right['score']) {
                return strcasecmp((string) $left['term']->name, (string) $right['term']->name);
            }
            return $right['score'] <=> $left['score'];
        });

        return array_map(function ($match) {
            return $match['term'];
        }, array_slice($matches, 0, self::MAX_CATEGORY_MATCHES));
    }

    private function category_match_score(array $query_tokens, $name, $slug) {
        $term_tokens = array_values(array_unique(array_merge(
            $this->normalized_catalog_tokens($name),
            $this->normalized_catalog_tokens($slug)
        )));
        if (!$term_tokens || array_diff($query_tokens, $term_tokens)) {
            return 0;
        }

        $query_phrase = implode(' ', $query_tokens);
        $name_phrase = implode(' ', $this->normalized_catalog_tokens($name));
        if ($query_phrase === $name_phrase) {
            return 400;
        }
        if ($query_phrase !== '' && strpos($name_phrase, $query_phrase) !== false) {
            return 300;
        }
        return 200 + count($query_tokens);
    }

    private function catalog_query_tokens($query) {
        $stop_tokens = array(
            'a', 'an', 'and', 'any', 'are', 'available', 'catalog', 'catalogue',
            'category', 'do', 'for', 'have', 'in', 'item', 'kind', 'me', 'of', 'on',
            'or', 'please', 'product', 'sell', 'selling', 'show', 'the', 'there',
            'type', 'what', 'with', 'you',
        );
        $tokens = array_values(array_filter($this->normalized_catalog_tokens($query), function ($token) use ($stop_tokens) {
            return strlen($token) > 1 && !in_array($token, $stop_tokens, true);
        }));
        return array_slice(array_values(array_unique($tokens)), 0, 6);
    }

    private function is_broad_catalog_query($query) {
        $tokens = $this->normalized_catalog_tokens($query);
        return (bool) array_intersect($tokens, array('catalog', 'catalogue', 'category', 'product', 'type', 'sell'));
    }

    private function normalized_catalog_tokens($value) {
        $value = function_exists('remove_accents') ? remove_accents((string) $value) : (string) $value;
        $value = strtolower($value);
        $value = preg_replace("/['’]s\\b/u", '', $value);
        $tokens = preg_split('/[^a-z0-9]+/', $value, -1, PREG_SPLIT_NO_EMPTY);
        if (!is_array($tokens)) {
            return array();
        }
        return array_map(array($this, 'singular_catalog_token'), $tokens);
    }

    private function singular_catalog_token($token) {
        $length = strlen($token);
        if ($length > 4 && substr($token, -3) === 'ies') {
            return substr($token, 0, -3) . 'y';
        }
        if ($length > 4 && preg_match('/(?:ches|shes|sses|xes)$/', $token)) {
            return substr($token, 0, -2);
        }
        if ($length > 3 && substr($token, -1) === 's' && substr($token, -2) !== 'ss') {
            return substr($token, 0, -1);
        }
        return $token;
    }

    private function get_product_tool(array $arguments) {
        $product_id = $this->validated_product_id($arguments['product_id'] ?? 0);
        if (!$product_id) {
            return array('ok' => false, 'error' => 'A valid product ID is required.');
        }
        if (!$this->woocommerce_available()) {
            return $this->unavailable('Product information is currently unavailable.');
        }

        $product = wc_get_product($product_id);
        if (!$this->is_public_product($product)) {
            return array('ok' => false, 'error' => 'No public product was found.');
        }
        return array('ok' => true, 'product' => $this->public_product($product));
    }

    private function list_active_raffles(array $arguments) {
        $limit = $this->validated_limit($arguments['limit'] ?? 0);
        if (!$limit) {
            return array('ok' => false, 'error' => 'A valid limit is required.');
        }
        if (!$this->woocommerce_available()) {
            return $this->unavailable('Raffle product information is currently unavailable.');
        }
        if (!$this->core_available()) {
            return $this->unavailable('Live raffle data is currently unavailable.');
        }

        $enabled_key = \RaffleLB\Core\Contracts::META_ENABLED;
        $raffles = array();
        $page = 1;
        $scanned = 0;

        while ($scanned < self::MAX_RAFFLE_SCAN && count($raffles) < $limit) {
            $batch_limit = min(self::RAFFLE_SCAN_BATCH_SIZE, self::MAX_RAFFLE_SCAN - $scanned);
            $query = new \WP_Query(array(
                'post_type'              => 'product',
                'post_status'            => 'publish',
                'posts_per_page'         => $batch_limit,
                'paged'                  => $page,
                'fields'                 => 'ids',
                'orderby'                => array('date' => 'DESC', 'ID' => 'DESC'),
                'no_found_rows'          => true,
                'update_post_term_cache' => false,
                'meta_query'             => array(array(
                    'key'     => $enabled_key,
                    'value'   => 'yes',
                    'compare' => '=',
                )),
            ));

            $candidate_ids = is_array($query->posts) ? $query->posts : array();
            if (!$candidate_ids) {
                break;
            }

            foreach ($candidate_ids as $product_id) {
                $scanned++;
                $product = wc_get_product(absint($product_id));
                if (!$this->is_public_product($product)) {
                    continue;
                }
                $status = $this->raffle_status($product);
                if (empty($status['ok']) || $status['public_status'] !== 'live' || $status['remaining_entries'] < 1) {
                    continue;
                }
                $raffles[] = $status;
                if (count($raffles) >= $limit || $scanned >= self::MAX_RAFFLE_SCAN) {
                    break;
                }
            }

            if (count($candidate_ids) < $batch_limit) {
                break;
            }
            $page++;
        }

        return array('ok' => true, 'raffles' => $raffles, 'count' => count($raffles));
    }

    private function get_raffle_status_tool(array $arguments) {
        $product_id = $this->validated_product_id($arguments['product_id'] ?? 0);
        if (!$product_id) {
            return array('ok' => false, 'error' => 'A valid product ID is required.');
        }
        if (!$this->woocommerce_available()) {
            return $this->unavailable('Raffle product information is currently unavailable.');
        }
        if (!$this->core_available()) {
            return $this->unavailable('Live raffle data is currently unavailable.');
        }

        $product = wc_get_product($product_id);
        if (!$this->is_public_product($product)) {
            return array('ok' => false, 'error' => 'No public raffle product was found.');
        }
        if (get_post_meta($product_id, \RaffleLB\Core\Contracts::META_ENABLED, true) !== 'yes') {
            return array('ok' => true, 'product_id' => $product_id, 'name' => $product->get_name(), 'raffle_enabled' => false, 'permalink' => get_permalink($product_id));
        }

        return $this->raffle_status($product);
    }

    private function raffle_status(\WC_Product $product) {
        global $wpdb;

        $product_id = absint($product->get_id());
        $exists = \RaffleLB\Core\Database::exists('entries');
        if (is_wp_error($exists) || !$exists) {
            return $this->unavailable('Live raffle data is currently unavailable.');
        }

        $table = \RaffleLB\Core\Database::table('entries');
        $expected_table = $wpdb->prefix . \RaffleLB\Core\Contracts::ENTRY_TABLE;
        if (is_wp_error($table) || !is_string($table) || $table !== $expected_table) {
            return $this->unavailable('Live raffle data is currently unavailable.');
        }

        $previous_suppression = $wpdb->suppress_errors(true);
        $claimed = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE product_id = %d AND status = %s",
            $product_id,
            'active'
        ));
        $database_error = (string) $wpdb->last_error;
        $wpdb->suppress_errors($previous_suppression);
        if ($database_error !== '' || $claimed === null) {
            return $this->unavailable('Live raffle data is currently unavailable.');
        }

        $total = absint(get_post_meta($product_id, \RaffleLB\Core\Contracts::META_TOTAL, true));
        $claimed = max(0, (int) $claimed);
        $remaining = max(0, $total - $claimed);
        $stored_status = (string) get_post_meta($product_id, \RaffleLB\Core\Contracts::META_DRAW_STATUS, true);
        $public_status = $this->public_raffle_status($stored_status, $total, $claimed);

        return array(
            'ok'                => true,
            'product_id'        => $product_id,
            'name'              => $product->get_name(),
            'raffle_enabled'    => true,
            'public_status'     => $public_status,
            'total_entries'     => $total,
            'claimed_entries'   => $claimed,
            'remaining_entries' => $remaining,
            'percent_filled'    => $total > 0 ? round(min(100, ($claimed / $total) * 100), 1) : 0,
            'permalink'         => get_permalink($product_id),
        );
    }

    private function public_raffle_status($stored_status, $total, $claimed) {
        if ($stored_status === 'winner_selected') {
            return 'completed';
        }
        if ($stored_status === 'ready_to_draw') {
            return 'closed';
        }
        if ($total < 1) {
            return 'unavailable';
        }
        if ($claimed >= $total) {
            return 'closed';
        }
        if ($stored_status === '' || $stored_status === 'live') {
            return 'live';
        }
        return 'unavailable';
    }

    private function product_commerce_state(\WC_Product $product, $include_raffle_status = true) {
        $product_id = absint($product->get_id());
        $raffle_enabled = false;
        $raffle_entry_price = null;
        $raffle_entry_available = false;
        $raffle_public_status = null;
        $raffle_remaining_entries = null;

        // A store-only product uses WooCommerce's normal price as the direct
        // purchase price. Current stock status is part of actual availability.
        $direct_purchase_price = $product->get_price();
        $direct_regular_price = $product->get_regular_price();
        $direct_sale_price = $product->get_sale_price();
        $direct_purchase_available = $product->is_in_stock() && $direct_purchase_price !== '';
        $purchase_mode = 'store_only';

        if ($this->core_available()) {
            $raffle_enabled = get_post_meta($product_id, \RaffleLB\Core\Contracts::META_ENABLED, true) === 'yes';
            if ($raffle_enabled) {
                // On raffle-enabled products WooCommerce's native product price
                // is the raffle-entry fee. Direct purchase has its own RaffleLB
                // authoritative meta contract.
                $raffle_entry_price = $product->get_price();
                $buy_now_enabled = get_post_meta($product_id, \RaffleLB\Core\Contracts::META_BUY_NOW_ENABLED, true) === 'yes';
                $buy_now_price_raw = get_post_meta($product_id, \RaffleLB\Core\Contracts::META_BUY_NOW_PRICE, true);
                $buy_now_price = is_numeric($buy_now_price_raw) ? (float) $buy_now_price_raw : 0.0;

                $direct_purchase_available = $product->is_in_stock() && $buy_now_enabled && $buy_now_price > 0;
                $direct_purchase_price = $buy_now_enabled && $buy_now_price > 0 ? $buy_now_price : null;
                $direct_regular_price = null;
                $direct_sale_price = null;
                $purchase_mode = ($buy_now_enabled && $buy_now_price > 0) ? 'store_and_raffle' : 'raffle_only';

                if ($include_raffle_status) {
                    $status = $this->raffle_status($product);
                    if (!empty($status['ok'])) {
                        $raffle_public_status = isset($status['public_status']) ? (string) $status['public_status'] : null;
                        $raffle_remaining_entries = isset($status['remaining_entries']) ? (int) $status['remaining_entries'] : null;
                        $raffle_entry_available = $raffle_public_status === 'live' && $raffle_remaining_entries !== null && $raffle_remaining_entries > 0;
                    }
                }
            }
        }

        return array(
            'purchase_mode'             => $purchase_mode,
            'direct_purchase_available' => (bool) $direct_purchase_available,
            'direct_purchase_price'     => $direct_purchase_price,
            'direct_regular_price'      => $direct_regular_price,
            'direct_sale_price'         => $direct_sale_price,
            'raffle_enabled'            => (bool) $raffle_enabled,
            'raffle_entry_available'    => (bool) $raffle_entry_available,
            'raffle_entry_price'        => $raffle_entry_price,
            'raffle_public_status'      => $raffle_public_status,
            'raffle_remaining_entries'  => $raffle_remaining_entries,
        );
    }

    private function public_product(\WC_Product $product) {
        $product_id = absint($product->get_id());
        $image_id = $product->get_image_id();
        $image_url = $image_id ? wp_get_attachment_image_url($image_id, 'woocommerce_thumbnail') : '';
        $state = $this->product_commerce_state($product, true);

        $excerpt = $product->get_short_description();
        if ($excerpt === '') {
            $excerpt = $product->get_description();
        }

        return array(
            'product_id'                => $product_id,
            'name'                      => $product->get_name(),
            'excerpt'                   => wp_trim_words(wp_strip_all_tags($excerpt), 32, '…'),
            'purchase_mode'             => $state['purchase_mode'],
            'direct_purchase_available' => $state['direct_purchase_available'],
            'direct_purchase_price'     => $this->formatted_money($state['direct_purchase_price']),
            'direct_regular_price'      => $this->formatted_money($state['direct_regular_price']),
            'direct_sale_price'         => $this->formatted_money($state['direct_sale_price']),
            'raffle_enabled'            => $state['raffle_enabled'],
            'raffle_entry_available'    => $state['raffle_entry_available'],
            'raffle_entry_price'        => $state['raffle_enabled'] ? $this->formatted_money($state['raffle_entry_price']) : null,
            'raffle_public_status'      => $state['raffle_public_status'],
            'raffle_remaining_entries'  => $state['raffle_remaining_entries'],
            'in_stock'                  => (bool) $product->is_in_stock(),
            'public_availability'       => sanitize_key($product->get_stock_status()),
            'permalink'                 => get_permalink($product_id),
            'image_url'                 => is_string($image_url) ? $image_url : '',
        );
    }

    private function is_public_product($product) {
        return $product instanceof \WC_Product
            && !($product instanceof \WC_Product_Variation)
            && $product->get_type() !== 'variation'
            && $product->get_status() === 'publish'
            && $product->is_visible();
    }

    private function plain_price_html($html) {
        return html_entity_decode(wp_strip_all_tags((string) $html), ENT_QUOTES, get_bloginfo('charset') ?: 'UTF-8');
    }

    private function formatted_money($value) {
        if ($value === '' || $value === null || !function_exists('wc_price')) {
            return null;
        }
        return $this->plain_price_html(wc_price((float) $value));
    }

    private function validated_product_id($value) {
        if (!is_int($value) && !(is_string($value) && ctype_digit($value))) {
            return 0;
        }
        return absint($value);
    }

    private function validated_limit($value) {
        if (!is_int($value) && !(is_string($value) && ctype_digit($value))) {
            return 0;
        }
        $value = (int) $value;
        return $value > 0 ? min(self::MAX_RESULTS, $value) : 0;
    }

    private function woocommerce_available() {
        return class_exists('WooCommerce') && function_exists('wc_get_product') && function_exists('wc_get_products');
    }

    private function core_available() {
        return class_exists('RaffleLB\\Core\\Contracts') && class_exists('RaffleLB\\Core\\Database');
    }

    private function unavailable($message) {
        return array('ok' => false, 'error' => $message);
    }

    private function truncate($value, $length) {
        return function_exists('mb_substr') ? mb_substr((string) $value, 0, $length) : substr((string) $value, 0, $length);
    }
}
