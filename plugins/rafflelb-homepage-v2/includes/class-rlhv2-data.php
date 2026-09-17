<?php
/**
 * Read-only data access for the Home V2 shortcode.
 *
 * Every method here only reads data other RaffleLB plugins already own
 * (via their public, documented contracts: RaffleLB\Core\Contracts constants,
 * RaffleLB_Selection_Adapter, RaffleLB_Referral_Points, WooCommerce, and the
 * rafflelb_review post type). Nothing here writes data, alters raffle/entry
 * logic, or duplicates hold/allocation/winner-selection behavior.
 */

if (!defined('ABSPATH')) {
    exit;
}

final class RLHV2_Data {

    public static function woocommerce_available() {
        return class_exists('WooCommerce') && function_exists('wc_get_product');
    }

    /**
     * Curated hero products: reuses the same editorial signal the live
     * homepage relies on (WooCommerce product tag "homepage-hero" plus the
     * shared RaffleLB\Core\Contracts hero-image meta key), read-only.
     */
    public static function hero_items($limit = 3) {
        if (!self::woocommerce_available()) {
            return [];
        }

        $meta_key = class_exists('RaffleLB\\Core\\Contracts')
            ? \RaffleLB\Core\Contracts::META_HERO_IMAGE
            : '_rafflelb_homepage_hero_image_id';

        $ids = get_posts([
            'post_type'      => 'product',
            'post_status'    => 'publish',
            'posts_per_page' => max(1, absint($limit)),
            'fields'         => 'ids',
            'orderby'        => ['menu_order' => 'ASC', 'date' => 'DESC'],
            'tax_query'      => [[
                'taxonomy' => 'product_tag',
                'field'    => 'slug',
                'terms'    => ['homepage-hero'],
            ]],
        ]);

        $items = [];
        foreach ($ids as $pid) {
            $product = wc_get_product($pid);
            if (!$product || !$product->is_visible()) {
                continue;
            }

            $image_id = absint(get_post_meta($pid, $meta_key, true));
            $image    = $image_id ? wp_get_attachment_image_url($image_id, 'large') : '';
            if (!$image) {
                $image = wp_get_attachment_image_url($product->get_image_id(), 'large');
            }
            if (!$image) {
                continue;
            }

            $items[] = [
                'title' => $product->get_name(),
                'url'   => get_permalink($pid),
                'image' => $image,
            ];
        }

        return $items;
    }

    /**
     * Plain ecommerce product cards: image, name, retail price, product URL.
     * Deliberately does NOT surface raffle price, entry counts, or progress
     * — that data belongs only in featured_selections() below, per the
     * Home V2 spec (normal cards must look like normal ecommerce cards).
     */
    public static function featured_products($limit = 6) {
        if (!self::woocommerce_available()) {
            return [];
        }

        $limit = max(1, min(12, absint($limit)));

        $query_args = [
            'post_type'      => 'product',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'orderby'        => 'date',
            'order'          => 'DESC',
            'tax_query'      => [[
                'taxonomy' => 'product_tag',
                'field'    => 'slug',
                'terms'    => ['homepage-featured'],
            ]],
        ];

        $ids = get_posts($query_args);

        // If the site has not tagged anything "homepage-featured" yet, fall
        // back to the most recent published, purchasable products so Home V2
        // never renders an empty storefront section.
        if (!$ids) {
            unset($query_args['tax_query']);
            $query_args['posts_per_page'] = $limit * 2;
            $ids = get_posts($query_args);
        }

        $products = [];
        foreach ($ids as $pid) {
            $product = wc_get_product($pid);
            if (!$product || !$product->is_visible() || !$product->is_purchasable()) {
                continue;
            }

            $price = (float) $product->get_price();
            if ($price <= 0) {
                continue;
            }

            $terms = get_the_terms($pid, 'product_cat');
            $category = '';
            if ($terms && !is_wp_error($terms)) {
                foreach ($terms as $term) {
                    if (strtolower($term->name) !== 'uncategorized') {
                        $category = html_entity_decode($term->name, ENT_QUOTES, get_bloginfo('charset'));
                        break;
                    }
                }
            }

            $has_selection = self::product_has_selection($product);

            $products[] = [
                'id'             => $pid,
                'title'          => $product->get_name(),
                'url'            => get_permalink($pid),
                'image_id'       => $product->get_image_id(),
                'category'       => $category,
                'price'          => $price,
                'has_selection'  => $has_selection,
                'selection_url'  => $has_selection ? self::selection_url($product) : '',
            ];

            if (count($products) >= $limit) {
                break;
            }
        }

        return $products;
    }

    /**
     * True only when the Selection Engine confirms an active, publicly
     * viewable selection exists for this product. Never invents this state.
     */
    public static function product_has_selection($product) {
        if (class_exists('RaffleLB_Selection_Adapter') && method_exists('RaffleLB_Selection_Adapter', 'is_public_raffle_product')) {
            return (bool) RaffleLB_Selection_Adapter::is_public_raffle_product($product);
        }

        // Selection Engine is inactive: fall back to the shared meta key
        // that Core\Contracts documents, without touching Draw Engine logic.
        $pid = is_object($product) && method_exists($product, 'get_id') ? $product->get_id() : absint($product);
        $meta_key = class_exists('RaffleLB\\Core\\Contracts')
            ? \RaffleLB\Core\Contracts::META_ENABLED
            : '_rafflelb_draw_enabled';
        return get_post_meta($pid, $meta_key, true) === 'yes';
    }

    public static function selection_url($product) {
        if (class_exists('RaffleLB_Selection_Adapter') && method_exists('RaffleLB_Selection_Adapter', 'selection_url')) {
            return (string) RaffleLB_Selection_Adapter::selection_url($product);
        }
        $slug = is_object($product) && method_exists($product, 'get_slug') ? $product->get_slug() : '';
        return $slug !== '' ? home_url('/selection/' . rawurlencode($slug) . '/') : home_url('/selection/');
    }

    /**
     * Shop categories: reuses the real WooCommerce product_cat taxonomy.
     */
    public static function shop_categories($limit = 6) {
        if (!taxonomy_exists('product_cat')) {
            return [];
        }

        $terms = get_terms([
            'taxonomy'   => 'product_cat',
            'hide_empty' => true,
            'number'     => max(1, absint($limit)),
            'exclude'    => [],
            'orderby'    => 'count',
            'order'      => 'DESC',
        ]);

        if (is_wp_error($terms) || !$terms) {
            return [];
        }

        $categories = [];
        foreach ($terms as $term) {
            if (strtolower($term->name) === 'uncategorized') {
                continue;
            }
            $thumb_id = get_term_meta($term->term_id, 'thumbnail_id', true);
            $categories[] = [
                'name'  => html_entity_decode($term->name, ENT_QUOTES, get_bloginfo('charset')),
                'url'   => get_term_link($term),
                'image' => $thumb_id ? wp_get_attachment_image_url($thumb_id, 'medium') : '',
                'count' => (int) $term->count,
            ];
            if (count($categories) >= $limit) {
                break;
            }
        }

        return $categories;
    }

    /**
     * Featured Selections: the only section where raffle/selection specific
     * data (entry price, progress, claimed/remaining) is shown, sourced
     * directly from RaffleLB_Selection_Adapter's public read API.
     */
    public static function featured_selections($limit = 3) {
        if (!class_exists('RaffleLB_Selection_Adapter') || !method_exists('RaffleLB_Selection_Adapter', 'raffle_cards')) {
            return [];
        }

        $cards = RaffleLB_Selection_Adapter::raffle_cards(max(1, absint($limit)));
        $selections = [];

        foreach ($cards as $card) {
            if (empty($card) || empty($card['product_id'])) {
                continue;
            }
            $product = self::woocommerce_available() ? wc_get_product((int) $card['product_id']) : false;
            $entry_price = $product ? (float) $product->get_price() : 0.0;

            $selections[] = [
                'name'            => (string) ($card['name'] ?? ''),
                'image'           => (string) ($card['image'] ?? ''),
                'product_url'     => (string) ($card['product_url'] ?? ''),
                'selection_url'   => (string) ($card['selection_url'] ?? ''),
                'entry_price'     => $entry_price,
                'percent_filled'  => (int) ($card['percent_filled'] ?? 0),
                'claimed'         => (int) ($card['eligible_entries'] ?? 0),
                'total'           => (int) ($card['total_allocation'] ?? 0),
            ];
        }

        return $selections;
    }

    /**
     * Verified Results: real completed-selection records from the Selection
     * Engine only. Returns an empty array (never fabricated numbers) when
     * there is nothing to show yet.
     */
    public static function verified_results($limit = 6) {
        if (!class_exists('RaffleLB_Selection_Adapter') || !method_exists('RaffleLB_Selection_Adapter', 'public_results')) {
            return [];
        }
        return RaffleLB_Selection_Adapter::public_results(max(1, absint($limit)));
    }

    public static function verified_results_count() {
        if (!class_exists('RaffleLB_Selection_Adapter') || !method_exists('RaffleLB_Selection_Adapter', 'public_result_count')) {
            return 0;
        }
        return (int) RaffleLB_Selection_Adapter::public_result_count();
    }

    /**
     * Customer reviews: the real rafflelb_review CPT that Draw Engine
     * registers and renders via [rafflelb_community_sections]. Read-only.
     */
    public static function customer_reviews($limit = 3) {
        if (!post_type_exists('rafflelb_review')) {
            return [];
        }

        $posts = get_posts([
            'post_type'      => 'rafflelb_review',
            'post_status'    => 'publish',
            'posts_per_page' => max(1, absint($limit)),
            'orderby'        => 'date',
            'order'          => 'DESC',
        ]);

        $reviews = [];
        foreach ($posts as $post) {
            $rating = (int) get_post_meta($post->ID, '_rafflelb_review_rating', true);
            $reviews[] = [
                'author' => get_the_title($post) ?: __('RaffleLB Customer', 'rafflelb-homepage-v2'),
                'rating' => max(1, min(5, $rating ?: 5)),
                'text'   => wp_strip_all_tags($post->post_content),
            ];
        }

        return $reviews;
    }

    /**
     * Points balance pill markup owned by rafflelb-referral-points.
     */
    public static function points_header_html() {
        if (function_exists('do_shortcode') && shortcode_exists('rafflelb_points_header')) {
            return do_shortcode('[rafflelb_points_header]');
        }
        return '';
    }

    public static function account_points_url() {
        if (function_exists('wc_get_account_endpoint_url')) {
            return wc_get_account_endpoint_url('refer-and-earn');
        }
        return home_url('/my-account/');
    }

    public static function shop_url() {
        return function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : home_url('/shop/');
    }
}
