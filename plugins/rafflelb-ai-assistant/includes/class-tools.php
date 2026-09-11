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
    const RAFFLE_SCAN_BATCH_SIZE = 40;
    const MAX_RAFFLE_SCAN = 800;

    public function definitions() {
        return array(
            array(
                'type'        => 'function',
                'name'        => 'search_products',
                'description' => 'Search the live public WooCommerce catalogue by product name, product type, or category. Use for current products, broad catalogue/category questions, prices, availability, or links.',
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
            default:
                return array('ok' => false, 'error' => 'That tool is not available.');
        }
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

        try {
            $data_store = \WC_Data_Store::load('product');
            if (!is_object($data_store) || !is_callable(array($data_store, 'search_products'))) {
                return $this->unavailable('Product search is currently unavailable.');
            }
            $product_ids = $data_store->search_products($query, '', false, false, $limit);
        } catch (\Throwable $error) {
            return $this->unavailable('Product search is currently unavailable.');
        }

        // WooCommerce's product data store owns product-title/content/SKU
        // search semantics. Variations and non-published statuses are excluded.
        if (!is_array($product_ids)) {
            return $this->unavailable('Product search is currently unavailable.');
        }

        $results = array();
        $seen_product_ids = array();
        foreach ($product_ids as $product_id) {
            $product = wc_get_product(absint($product_id));
            if (!$this->is_public_product($product)) {
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
            $categories = $this->matching_product_categories($query);
            foreach ($categories as $category) {
                $remaining = $limit - count($results);
                if ($remaining < 1) {
                    break;
                }

                try {
                    $category_products = wc_get_products(array(
                        'status'     => 'publish',
                        'visibility' => 'visible',
                        'category'   => array($category->slug),
                        'limit'      => min(self::MAX_RESULTS * 2, $remaining + count($seen_product_ids)),
                        'orderby'    => 'date',
                        'order'      => 'DESC',
                    ));
                } catch (\Throwable $error) {
                    continue;
                }
                if (!is_array($category_products)) {
                    continue;
                }

                $category_has_public_product = false;
                foreach ($category_products as $product) {
                    if (!$this->is_public_product($product)) {
                        continue;
                    }
                    $category_has_public_product = true;
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

                if ($category_has_public_product) {
                    $category_name = sanitize_text_field(wp_strip_all_tags((string) $category->name));
                    if ($category_name !== '' && !in_array($category_name, $matched_categories, true)) {
                        $matched_categories[] = $category_name;
                    }
                }
            }
        }

        return array(
            'ok'                 => true,
            'products'           => $results,
            'matched_categories' => $matched_categories,
            'count'              => count($results),
        );
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

    private function public_product(\WC_Product $product) {
        $product_id = absint($product->get_id());
        $image_id = $product->get_image_id();
        $image_url = $image_id ? wp_get_attachment_image_url($image_id, 'woocommerce_thumbnail') : '';
        $raffle_enabled = null;
        if ($this->core_available()) {
            $raffle_enabled = get_post_meta($product_id, \RaffleLB\Core\Contracts::META_ENABLED, true) === 'yes';
        }

        $excerpt = $product->get_short_description();
        if ($excerpt === '') {
            $excerpt = $product->get_description();
        }

        return array(
            'product_id'          => $product_id,
            'name'                => $product->get_name(),
            'excerpt'             => wp_trim_words(wp_strip_all_tags($excerpt), 32, '…'),
            'display_price'       => $this->plain_price_html($product->get_price_html()),
            'regular_price'       => $this->formatted_money($product->get_regular_price()),
            'sale_price'          => $this->formatted_money($product->get_sale_price()),
            'in_stock'            => (bool) $product->is_in_stock(),
            'public_availability' => sanitize_key($product->get_stock_status()),
            'permalink'           => get_permalink($product_id),
            'image_url'           => is_string($image_url) ? $image_url : '',
            'raffle_enabled'      => $raffle_enabled,
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
