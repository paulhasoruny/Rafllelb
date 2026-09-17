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
     * The live RaffleLB brand's own hero product-composition artwork,
     * already bundled inside rafflelb-homepage/assets/hero-reference-scene.webp
     * (referenced there via plugins_url(), never modified here). This is a
     * read-only reference to that plugin's already-public static asset — the
     * exact rocky-pedestal / lime-halo / product-collage scene the approved
     * Home V2 preview is built around — so Home V2 can match the preview
     * without compositing a new graphic from arbitrary product photos.
     * Returns '' and lets the caller fall back to hero_items() if that
     * plugin is inactive or the file is missing.
     */
    public static function hero_scene_url() {
        $path = WP_PLUGIN_DIR . '/rafflelb-homepage/assets/hero-reference-scene.webp';
        if (!file_exists($path)) {
            return '';
        }
        return plugins_url('rafflelb-homepage/assets/hero-reference-scene.webp', WP_PLUGIN_DIR . '/index.php');
    }

    /**
     * The RaffleLB brand ticket/"R" mark bundled with rafflelb-referral-points
     * (assets/rafflelb-ticket-icon.webp), the same icon that plugin already
     * uses for the header points pill. Read-only reference, used as the
     * Points section's decorative artwork per "reuse existing RaffleLB
     * logo/points assets only" — no coin illustration is invented.
     */
    public static function points_icon_url() {
        $path = WP_PLUGIN_DIR . '/rafflelb-referral-points/assets/rafflelb-ticket-icon.webp';
        if (!file_exists($path)) {
            return '';
        }
        return plugins_url('rafflelb-referral-points/assets/rafflelb-ticket-icon.webp', WP_PLUGIN_DIR . '/index.php');
    }

    /**
     * Curated hero products: reuses the same editorial signal the live
     * homepage relies on (WooCommerce product tag "homepage-hero" plus the
     * shared RaffleLB\Core\Contracts hero-image meta key), read-only. Used
     * only as a fallback when hero_scene_url() is unavailable.
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

        // No curator has tagged any product "homepage-hero" yet: fall back to
        // the same Featured Products data already being queried, so the hero
        // still shows real, existing product photography instead of nothing.
        if (!$items) {
            foreach (self::featured_products($limit) as $product) {
                if (!$product['image_id']) {
                    continue;
                }
                $image = wp_get_attachment_image_url($product['image_id'], 'large');
                if (!$image) {
                    continue;
                }
                $items[] = [
                    'title' => $product['title'],
                    'url'   => $product['url'],
                    'image' => $image,
                ];
                if (count($items) >= $limit) {
                    break;
                }
            }
        }

        return $items;
    }

    /**
     * The real retail/store price for a product, matching what the live
     * storefront and the live homepage's own Featured Products section
     * display. WooCommerce's own $product->get_price() is NOT safe to use
     * directly here: for a product that also has an active Selection, the
     * WooCommerce price field holds the *raffle entry price* (it's what
     * Draw Engine uses as the cart line-item price for an entry), while the
     * real direct-purchase price lives in a separate "buy now" price meta
     * exposed by Draw Engine's public bridge. For a plain product with no
     * Selection at all, $product->get_price() is genuinely the retail price
     * (nothing overrides it), so that remains the fallback.
     */
    public static function retail_price($product) {
        if (!$product) {
            return 0.0;
        }

        if (class_exists('RaffleLB_Draw_Engine') && method_exists('RaffleLB_Draw_Engine', 'homepage_draw_id') && method_exists('RaffleLB_Draw_Engine', 'homepage_buy_now_price')) {
            $draw_id = RaffleLB_Draw_Engine::homepage_draw_id($product);
            if ($draw_id) {
                $buy_now_price = (float) RaffleLB_Draw_Engine::homepage_buy_now_price($product);
                if ($buy_now_price > 0) {
                    return $buy_now_price;
                }
            }
        }

        return (float) $product->get_price();
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

            $price = self::retail_price($product);
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

        // Top-level categories only (parent = 0), in the site's configured
        // display order — matches how the live homepage's own category grid
        // sources its categories, so Home V2 shows the same curated
        // top-level set (e.g. Perfumes, Electronics) instead of leaf
        // subcategories such as "Men's Perfumes" that rarely have their own
        // thumbnail configured.
        $terms = get_terms([
            'taxonomy'   => 'product_cat',
            'hide_empty' => true,
            'parent'     => 0,
            'orderby'    => 'menu_order',
            'order'      => 'ASC',
        ]);

        if (is_wp_error($terms) || !$terms) {
            return [];
        }

        // The approved preview leads with RaffleLB's six flagship top-level
        // categories in this order. Where the store actually has a category
        // by that name, show the real one (real thumbnail, real product
        // count, real URL) in that position; any store that doesn't have
        // exactly this taxonomy still fills every slot from its own
        // top-level categories, in their configured order.
        $preferred = ['perfumes', 'electronics', 'cosmetics', 'experiences', 'vouchers & gift cards', 'home appliances'];

        $by_name = [];
        $rest = [];
        foreach ($terms as $term) {
            if (strtolower($term->name) === 'uncategorized') {
                continue;
            }
            $key = strtolower(html_entity_decode($term->name, ENT_QUOTES, get_bloginfo('charset')));
            if (in_array($key, $preferred, true) && !isset($by_name[$key])) {
                $by_name[$key] = $term;
            } else {
                $rest[] = $term;
            }
        }

        $ordered = [];
        foreach ($preferred as $key) {
            if (isset($by_name[$key])) {
                $ordered[] = $by_name[$key];
            }
        }
        $ordered = array_merge($ordered, $rest);

        $categories = [];
        foreach ($ordered as $term) {
            $thumb_id = get_term_meta($term->term_id, 'thumbnail_id', true);
            $categories[] = [
                'name'  => html_entity_decode($term->name, ENT_QUOTES, get_bloginfo('charset')),
                'url'   => get_term_link($term),
                'image' => $thumb_id ? wp_get_attachment_image_url($thumb_id, 'large') : '',
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

            $claimed = (int) ($card['eligible_entries'] ?? 0);
            $total   = (int) ($card['total_allocation'] ?? 0);

            $selections[] = [
                'name'            => (string) ($card['name'] ?? ''),
                'image'           => (string) ($card['image'] ?? ''),
                'product_url'     => (string) ($card['product_url'] ?? ''),
                'selection_url'   => (string) ($card['selection_url'] ?? ''),
                'entry_price'     => $entry_price,
                'percent_filled'  => (int) ($card['percent_filled'] ?? 0),
                'claimed'         => $claimed,
                'total'           => $total,
                'remaining'       => max(0, $total - $claimed),
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
     * Non-numeric trust statements only (no invented counts like "10,000+
     * happy customers" — there is no safely-reusable, read-only source for
     * that on this store). Paired in the template with the one real number
     * above (verified_results_count()).
     */
    public static function verified_results_badges() {
        return [
            ['label' => 'Transparent & Verifiable', 'sub' => 'Every result is recorded and published.'],
            ['label' => 'Fair Selection Process',    'sub' => 'The same rules apply to every entry.'],
        ];
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
                'author'   => get_the_title($post) ?: __('RaffleLB Customer', 'rafflelb-homepage-v2'),
                'rating'   => max(1, min(5, $rating ?: 5)),
                'text'     => wp_strip_all_tags($post->post_content),
                'verified' => get_post_meta($post->ID, '_rafflelb_review_verified', true) ? true : false,
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

    /**
     * Anchor link into the existing customer review system rendered by
     * Draw Engine's [rafflelb_community_sections] (id="community-reviews"),
     * which already includes the live review submission form. Home V2 does
     * not rebuild that form — it only links to the existing one.
     */
    public static function share_review_url() {
        return home_url('/#community-reviews');
    }
}
