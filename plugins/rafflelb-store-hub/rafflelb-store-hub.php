<?php
/**
 * Plugin Name: RaffleLB Store Hub
 * Description: Separate premium Store Hub preview page for RaffleLB. Showcases search, live raffles, categories, brands and budget ranges without replacing the existing Shop.
 * Version: 0.1.16
 * Author: RaffleLB
 */

if (!defined('ABSPATH')) exit;

final class RaffleLB_Store_Hub {
    const VERSION = '0.1.16';
    const PAGE_OPTION = 'rafflelb_store_hub_preview_page_id';
    const PAGE_SLUG = 'store-preview';
    const BRAND_PAGE_OPTION = 'rafflelb_store_hub_brand_page_id';
    const BRAND_PAGE_SLUG = 'brands-preview';

    public static function init() {
        register_activation_hook(__FILE__, [__CLASS__, 'activate']);
        add_action('init', [__CLASS__, 'register_shortcode']);
        add_action('init', [__CLASS__, 'ensure_pages'], 20);
        add_filter('body_class', [__CLASS__, 'body_class']);
        add_action('wp_ajax_rafflelb_store_hub_perfumes', [__CLASS__, 'ajax_perfumes']);
        add_action('wp_ajax_nopriv_rafflelb_store_hub_perfumes', [__CLASS__, 'ajax_perfumes']);
    }

    public static function activate() {
        self::ensure_pages();
        flush_rewrite_rules(false);
    }

    private static function ensure_page($slug, $title, $content, $option) {
        $existing = get_page_by_path($slug, OBJECT, 'page');
        if ($existing instanceof WP_Post) {
            update_option($option, (int) $existing->ID, false);
            return (int) $existing->ID;
        }

        $page_id = wp_insert_post([
            'post_type'    => 'page',
            'post_status'  => 'publish',
            'post_title'   => $title,
            'post_name'    => $slug,
            'post_content' => $content,
            'post_author'  => get_current_user_id() ?: 1,
        ], true);

        if (!is_wp_error($page_id) && $page_id) {
            update_option($option, (int) $page_id, false);
            return (int) $page_id;
        }
        return 0;
    }

    public static function ensure_pages() {
        self::ensure_page(self::PAGE_SLUG, 'Store Preview', '[rafflelb_store_hub]', self::PAGE_OPTION);
        self::ensure_page(self::BRAND_PAGE_SLUG, 'Brands Preview', '[rafflelb_brand_directory]', self::BRAND_PAGE_OPTION);
    }

    public static function register_shortcode() {
        add_shortcode('rafflelb_store_hub', [__CLASS__, 'shortcode']);
        add_shortcode('rafflelb_brand_directory', [__CLASS__, 'brands_shortcode']);
    }

    public static function body_class($classes) {
        $page_id = absint(get_option(self::PAGE_OPTION));
        $brand_page_id = absint(get_option(self::BRAND_PAGE_OPTION));
        if (($page_id && is_page($page_id)) || is_page(self::PAGE_SLUG)) {
            $classes[] = 'rafflelb-store-hub-page';
        }
        if (($brand_page_id && is_page($brand_page_id)) || is_page(self::BRAND_PAGE_SLUG)) {
            $classes[] = 'rafflelb-brand-directory-page';
        }
        return $classes;
    }

    private static function brand_directory_url() {
        $page_id = absint(get_option(self::BRAND_PAGE_OPTION));
        if ($page_id) {
            $url = get_permalink($page_id);
            if ($url) return $url;
        }
        return home_url('/' . self::BRAND_PAGE_SLUG . '/');
    }

    private static function shop_url($args = []) {
        $url = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : home_url('/shop/');
        $args = array_merge(['rl_view' => 'retail'], $args);
        return add_query_arg($args, $url);
    }

    private static function raffles_url() {
        return self::shop_url(['rl_view' => 'raffle']);
    }

    private static function product_buy_price($product) {
        if (!$product instanceof WC_Product) return 0.0;

        // Products that also participate in a raffle may store their direct-purchase
        // price separately. Reuse Draw Engine's proven resolver when it has a valid
        // value, but always fall back to WooCommerce's normal Store price for
        // ordinary retail products. This prevents Store-only Perfume Picks from
        // rendering $0.00 when the Draw Engine has no Buy Now override for them.
        if (class_exists('RaffleLB_Draw_Engine') && method_exists('RaffleLB_Draw_Engine', 'homepage_buy_now_price')) {
            $price = (float) RaffleLB_Draw_Engine::homepage_buy_now_price($product);
            if ($price > 0) return $price;
        }
        return (float) wc_get_price_to_display($product);
    }

    private static function category_term($slug, $name = '') {
        if (!taxonomy_exists('product_cat')) return null;
        $term = get_term_by('slug', $slug, 'product_cat');
        if ((!$term || is_wp_error($term)) && $name !== '') $term = get_term_by('name', $name, 'product_cat');
        return ($term && !is_wp_error($term)) ? $term : null;
    }

    private static function category_image_url($term) {
        if (!$term instanceof WP_Term) return '';
        $thumb_id = absint(get_term_meta($term->term_id, 'thumbnail_id', true));
        if ($thumb_id) {
            $url = wp_get_attachment_image_url($thumb_id, 'large');
            if ($url) return $url;
        }

        $ids = get_posts([
            'post_type'      => 'product',
            'post_status'    => 'publish',
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'tax_query'      => [[
                'taxonomy'         => 'product_cat',
                'field'            => 'term_id',
                'terms'            => [(int) $term->term_id],
                'include_children' => true,
            ]],
        ]);
        if ($ids) {
            $product = wc_get_product($ids[0]);
            if ($product && $product->get_image_id()) {
                $url = wp_get_attachment_image_url($product->get_image_id(), 'large');
                if ($url) return $url;
            }
        }
        return '';
    }

    private static function brand_taxonomies() {
        $preferred = ['product_brand','pa_brands','pa_brand','pwb-brand','yith_product_brand','berocket_brand','brand'];
        $found = [];
        foreach ($preferred as $taxonomy) {
            if (taxonomy_exists($taxonomy) && is_object_in_taxonomy('product', $taxonomy)) $found[] = $taxonomy;
        }
        foreach (get_object_taxonomies('product', 'objects') as $taxonomy => $object) {
            if (in_array($taxonomy, $found, true)) continue;
            $label = strtolower(($object->label ?? '') . ' ' . ($object->labels->singular_name ?? ''));
            if (strpos(strtolower($taxonomy), 'brand') !== false || strpos($label, 'brand') !== false) $found[] = $taxonomy;
        }
        return array_values(array_unique($found));
    }

    private static function brand_terms($limit = 10) {
        foreach (self::brand_taxonomies() as $taxonomy) {
            $terms = get_terms([
                'taxonomy'   => $taxonomy,
                'hide_empty' => true,
                'number'     => max(1, (int) $limit),
                'orderby'    => 'count',
                'order'      => 'DESC',
            ]);
            if (!is_wp_error($terms) && $terms) return [$taxonomy, $terms];
        }
        return ['', []];
    }

    private static function brand_image_url($term) {
        if (!$term instanceof WP_Term) return '';
        foreach (['thumbnail_id','brand_thumbnail_id','brand_image_id','logo_id','image_id','berocket_brand_image_id','berocket_thumbnail_id'] as $key) {
            $id = absint(get_term_meta($term->term_id, $key, true));
            if ($id) {
                $url = wp_get_attachment_image_url($id, 'medium');
                if ($url) return $url;
            }
        }
        foreach (['brand_image','logo','image','thumbnail','berocket_brand_image','berocket_logo'] as $key) {
            $value = get_term_meta($term->term_id, $key, true);
            if (is_numeric($value) && absint($value)) {
                $url = wp_get_attachment_image_url(absint($value), 'medium');
                if ($url) return $url;
            }
            if (is_array($value)) {
                foreach (['id','ID','attachment_id','url'] as $subkey) {
                    if (!isset($value[$subkey])) continue;
                    if (is_numeric($value[$subkey]) && absint($value[$subkey])) {
                        $url = wp_get_attachment_image_url(absint($value[$subkey]), 'medium');
                        if ($url) return $url;
                    }
                    if (is_string($value[$subkey]) && filter_var($value[$subkey], FILTER_VALIDATE_URL)) return esc_url_raw($value[$subkey]);
                }
            }
            if (is_string($value) && filter_var($value, FILTER_VALIDATE_URL)) return esc_url_raw($value);
        }
        return '';
    }

    private static function all_brand_terms() {
        foreach (self::brand_taxonomies() as $taxonomy) {
            $terms = get_terms([
                'taxonomy'   => $taxonomy,
                'hide_empty' => true,
                'orderby'    => 'name',
                'order'      => 'ASC',
                'number'     => 0,
            ]);
            if (!is_wp_error($terms) && $terms) return [$taxonomy, $terms];
        }
        return ['', []];
    }

    private static function hero_products($limit = 6) {
        if (!function_exists('wc_get_product')) return [];
        $ids = get_posts([
            'post_type'      => 'product',
            'post_status'    => 'publish',
            'posts_per_page' => 30,
            'fields'         => 'ids',
            'orderby'        => 'date',
            'order'          => 'DESC',
        ]);
        $products = [];
        foreach ($ids as $pid) {
            $product = wc_get_product($pid);
            if (!$product || !$product->is_visible() || !$product->is_in_stock() || !$product->get_image_id()) continue;
            if (self::product_buy_price($product) <= 0) continue;
            $products[] = $product;
            if (count($products) >= $limit) break;
        }
        return $products;
    }

    private static function featured_products($limit = 6) {
        if (!function_exists('wc_get_product')) return [];
        $ids = get_posts([
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
        ]);

        $products = [];
        foreach ($ids as $pid) {
            $product = wc_get_product($pid);
            if (!$product || !$product->is_visible() || !$product->is_in_stock()) continue;
            if (self::product_buy_price($product) <= 0) continue;
            $products[] = $product;
            if (count($products) >= $limit) break;
        }

        if (count($products) < $limit) {
            $fallback = get_posts([
                'post_type'      => 'product',
                'post_status'    => 'publish',
                'posts_per_page' => 30,
                'fields'         => 'ids',
                'orderby'        => 'date',
                'order'          => 'DESC',
            ]);
            $seen = array_map(static function($p){ return $p->get_id(); }, $products);
            foreach ($fallback as $pid) {
                if (in_array((int) $pid, $seen, true)) continue;
                $product = wc_get_product($pid);
                if (!$product || !$product->is_visible() || !$product->is_in_stock()) continue;
                if (self::product_buy_price($product) <= 0) continue;
                $products[] = $product;
                if (count($products) >= $limit) break;
            }
        }
        return $products;
    }

    private static function live_raffles($limit = 5) {
        if (!class_exists('RaffleLB_Draw_Engine') || !function_exists('wc_get_product')) return [];
        $ids = get_posts([
            'post_type'      => 'product',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'meta_query'     => [[
                'key'     => '_rafflelb_draw_enabled',
                'value'   => 'yes',
                'compare' => '=',
            ]],
        ]);
        $rows = [];
        foreach ($ids as $pid) {
            $product = wc_get_product($pid);
            if (!$product || !$product->is_visible()) continue;
            $draw_pid = method_exists('RaffleLB_Draw_Engine','homepage_draw_id') ? absint(RaffleLB_Draw_Engine::homepage_draw_id($product)) : absint($pid);
            if (!$draw_pid) continue;
            $stats = RaffleLB_Draw_Engine::homepage_stats($draw_pid, true);
            if (!$stats || (string)($stats['status'] ?? '') !== 'live' || absint($stats['available'] ?? 0) < 1) continue;
            if (method_exists('RaffleLB_Draw_Engine','homepage_get_draw_result') && RaffleLB_Draw_Engine::homepage_get_draw_result($draw_pid)) continue;
            $rows[] = [
                'product' => $product,
                'stats'   => $stats,
                'percent' => absint($stats['percent'] ?? 0),
            ];
        }
        usort($rows, static function($a,$b){ return $b['percent'] <=> $a['percent']; });
        return array_slice($rows, 0, max(1, (int) $limit));
    }


    /**
     * Returns the active raffle products nearest to capacity for the homepage.
     *
     * The Draw Engine remains the single source of truth for eligibility and live
     * statistics. Candidate discovery is narrowed to raffle-enabled products first
     * so the homepage does not run live-stat queries for the whole WooCommerce
     * catalog.
     */
    private static function almost_filled_raffles() {
        if (!function_exists('wc_get_product') || !class_exists('RaffleLB_Draw_Engine')) return [];

        // Draw Engine's public constants are the established raffle contract.
        // Query only products that can actually own a raffle, then let the bridge
        // validate live/completion state below. This preserves Store + Raffle while
        // avoiding a full published-catalog scan on every uncached homepage render.
        $ids = get_posts([
            'post_type'      => 'product',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'orderby'        => 'date',
            'order'          => 'DESC',
            'no_found_rows'  => true,
            'meta_query'     => [
                [
                    'key'   => RaffleLB_Draw_Engine::META_ENABLED,
                    'value' => 'yes',
                ],
                [
                    'key'     => RaffleLB_Draw_Engine::META_TOTAL,
                    'value'   => 0,
                    'compare' => '>',
                    'type'    => 'NUMERIC',
                ],
            ],
        ]);

        $raffles = [];
        foreach ($ids as $pid) {
            $product = wc_get_product($pid);
            if (!$product || !$product->is_visible()) continue;

            $draw_pid = absint(RaffleLB_Draw_Engine::homepage_draw_id($product));
            if (!$draw_pid) continue;

            $stats = RaffleLB_Draw_Engine::homepage_stats($draw_pid, true);
            if (!$stats || (string) $stats['status'] !== 'live') continue;

            $capacity = absint($stats['total']);
            $claimed  = absint($stats['claimed']);
            $left     = absint($stats['available']);
            if ($capacity < 1 || $left < 1 || $claimed < 1 || RaffleLB_Draw_Engine::homepage_get_draw_result($draw_pid)) continue;

            // homepage_stats() supplies this authoritative value from the same
            // claimed/capacity data used by the existing live-raffle section.
            $percent = max(0, min(100, (float) $stats['percent']));

            // Almost Filled is activity-driven: only raffles with at least one claimed
            // entry participate in the closest-to-full ranking.
            $raffles[] = [
                'product'  => $product,
                'capacity' => $capacity,
                'claimed'  => $claimed,
                'left'     => $left,
                'percent'  => $percent,
            ];
        }

        usort($raffles, static function ($a, $b) {
            return $b['percent'] <=> $a['percent'];
        });

        return array_slice($raffles, 0, 6);
    }

    public static function almost_filled_shortcode() {
        $raffles = self::almost_filled_raffles();
        if (!$raffles) return '';

        $shop_url = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : home_url('/shop/');
        $raffle_shop_url = add_query_arg('rl_view', 'raffle', $shop_url) . '#rl-shop-controls';

        ob_start();
        ?>
        <section class="rl-almost-filled" aria-labelledby="rl-almost-filled-title">
            <div class="rl-almost-filled-inner">
                <div class="rl-almost-filled-head">
                    <div class="rl-almost-filled-heading-copy">
                        <span class="rl-almost-filled-kicker">LIVE STATUS</span>
                        <h2 id="rl-almost-filled-title">ALMOST <span>FILLED</span></h2>
                        <p>Raffles closest to reaching full capacity.</p>
                    </div>
                    <a class="rl-almost-filled-view" href="<?php echo esc_url($raffle_shop_url); ?>"><span>VIEW ALL RAFFLES</span><svg class="rl-almost-filled-view-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h13"></path><path d="m14 7 5 5-5 5"></path></svg></a>
                </div>

                <div class="rl-almost-filled-grid">
                    <?php foreach ($raffles as $raffle):
                        /** @var WC_Product $product */
                        $product  = $raffle['product'];
                        $pid      = $product->get_id();
                        $title    = $product->get_name();
                        $url      = get_permalink($pid);
                        $image_id = $product->get_image_id();
                        $category = '';
                        $terms    = get_the_terms($pid, 'product_cat');
                        if ($terms && !is_wp_error($terms)) {
                            foreach ($terms as $term) {
                                if (strtolower($term->name) !== 'uncategorized') {
                                    $category = html_entity_decode($term->name, ENT_QUOTES, get_bloginfo('charset'));
                                    break;
                                }
                            }
                        }
                        $entry_value = (float) wc_get_price_to_display($product);
                        $entry_price = html_entity_decode(get_woocommerce_currency_symbol(), ENT_QUOTES | ENT_HTML5, 'UTF-8') . number_format($entry_value, 2, '.', '');
                        $percent     = $raffle['percent'];
                        $selection_url = '';
                        if (class_exists('RaffleLB_Selection_Adapter') && method_exists('RaffleLB_Selection_Adapter', 'selection_url')) {
                            $selection_url = (string) RaffleLB_Selection_Adapter::selection_url($product);
                        }
                        if ($selection_url === '') {
                            $slug = $product->get_slug();
                            $selection_url = $slug !== '' ? home_url('/selection/' . rawurlencode($slug) . '/') : '';
                        }
                    ?>
                        <article class="rl-almost-filled-card">
                            <a class="rl-almost-filled-media" href="<?php echo esc_url($url); ?>" aria-label="<?php echo esc_attr($title); ?>">
                                <?php if ($image_id): ?>
                                    <?php echo wp_get_attachment_image($image_id, 'woocommerce_thumbnail', false, ['class' => 'rl-almost-filled-image', 'loading' => 'lazy', 'alt' => $title]); ?>
                                <?php else: ?>
                                    <span class="rl-almost-filled-placeholder" aria-hidden="true">R</span>
                                <?php endif; ?>
                            </a>
                            <div class="rl-almost-filled-body">
                                <span class="rl-almost-filled-badge"><i aria-hidden="true"></i> ALMOST FILLED</span>
                                <h3><a href="<?php echo esc_url($url); ?>" title="<?php echo esc_attr($title); ?>"><?php echo esc_html($title); ?></a></h3>
                                <?php if ($category): ?><p class="rl-almost-filled-category"><?php echo esc_html($category); ?></p><?php endif; ?>
                                <p class="rl-almost-filled-price"><strong><?php echo esc_html($entry_price); ?></strong><span>/ entry</span></p>
                                <div class="rl-almost-filled-progress" aria-label="<?php echo esc_attr(number_format($percent, 0)); ?>% filled">
                                    <div class="rl-almost-filled-progress-top"><span>FILL PROGRESS</span><strong><?php echo esc_html(number_format($percent, 0)); ?>%</strong></div>
                                    <div class="rl-almost-filled-track"><span style="width:<?php echo esc_attr($percent); ?>%"></span></div>
                                    <div class="rl-almost-filled-stats"><span><strong><?php echo esc_html($raffle['claimed']); ?></strong> CLAIMED</span><span><strong><?php echo esc_html($raffle['left']); ?></strong> LEFT</span></div>
                                </div>
                                <a class="rl-almost-filled-enter" href="<?php echo esc_url($url); ?>">ENTER RAFFLE</a>
                                <?php if ($selection_url !== ''): ?>
                                    <a class="rl-almost-filled-selection" href="<?php echo esc_url($selection_url); ?>"><span>VIEW SELECTION STATUS</span><b aria-hidden="true">→</b></a>
                                <?php endif; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
        <style>
        .rl-almost-filled{--rl-af-lime:#baff00;--rl-af-bg:#090d09;--rl-af-line:#294129;width:100%;padding:28px clamp(16px,2.2vw,42px) 34px;border-top:1px solid rgba(186,255,0,.08);background:var(--rl-af-bg);color:#f5f7f2;font-family:var(--rl-font)!important;box-sizing:border-box}
        .rl-almost-filled *{box-sizing:border-box;font-family:inherit!important;text-shadow:none!important}.rl-almost-filled a{text-decoration:none!important}.rl-almost-filled a:focus-visible{outline:3px solid var(--rl-af-lime);outline-offset:3px}
        .rl-almost-filled-inner{max-width:1840px;margin:0 auto}.rl-almost-filled-head{display:flex;align-items:center;justify-content:space-between;gap:24px;margin:0 0 16px}.rl-almost-filled-kicker{display:block;margin:0 0 6px;color:var(--rl-af-lime);font-size:9px;font-weight:900;letter-spacing:.16em;line-height:1;text-transform:uppercase}.rl-almost-filled-head h2{margin:0!important;color:#f5f7f2!important;font-size:clamp(25px,2vw,32px)!important;line-height:1.08!important;font-weight:800!important;letter-spacing:-.025em!important}.rl-almost-filled-head h2 span{color:var(--rl-af-lime)}.rl-almost-filled-head p{margin:7px 0 0!important;color:#879183!important;font-size:12px!important;line-height:1.4!important}.rl-almost-filled-view{display:inline-flex;align-items:center;justify-content:center;gap:10px;min-height:42px;padding:10px 15px;border:1px solid var(--rl-af-lime);border-radius:10px;background:transparent;color:#f5f7f2!important;font-size:11px;font-weight:800;letter-spacing:.06em;white-space:nowrap;transition:background .18s ease,color .18s ease}.rl-almost-filled-view-icon{width:18px;height:18px;flex:none;color:var(--rl-af-lime);display:block}.rl-almost-filled-view:hover{background:var(--rl-af-lime);color:#091000!important}.rl-almost-filled-view:hover .rl-almost-filled-view-icon{color:#091000}
        .rl-almost-filled-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:16px}.rl-almost-filled-card{display:flex;flex-direction:column;min-width:0;overflow:hidden;border:1px solid var(--rl-af-line);border-radius:11px;background:#030503;transition:border-color .18s ease,transform .18s ease}.rl-almost-filled-card:hover{border-color:#668544;transform:translateY(-2px)}.rl-almost-filled-media{display:flex;align-items:center;justify-content:center;width:100%;height:auto;aspect-ratio:1.15;min-width:0;padding:14px;background:#000;border-bottom:1px solid #1d291d}.rl-almost-filled-image{display:block!important;width:100%!important;height:100%!important;max-height:none;object-fit:contain!important;object-position:center!important}.rl-almost-filled-placeholder{color:#84917c;font-size:30px;font-weight:800}.rl-almost-filled-body{display:flex;flex:1;flex-direction:column;min-width:0;padding:13px 14px 14px}.rl-almost-filled-badge{display:inline-flex;align-items:center;align-self:flex-start;gap:5px;margin:0 0 8px;padding:4px 7px;border:1px solid #759b00;border-radius:999px;color:var(--rl-af-lime);font-size:9px;font-weight:700;letter-spacing:.06em;line-height:1}.rl-almost-filled-badge i{width:5px;height:5px;border-radius:50%;background:currentColor;box-shadow:0 0 7px currentColor}.rl-almost-filled-body h3{margin:0!important;font-size:16px!important;font-weight:700!important;line-height:1.28!important;letter-spacing:0!important;overflow-wrap:anywhere}.rl-almost-filled-body h3 a{color:#fff!important}.rl-almost-filled-category{min-height:17px;margin:4px 0 0!important;color:#929b8d!important;font-size:12px!important;line-height:1.4!important;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.rl-almost-filled-price{display:flex;align-items:baseline;gap:5px;margin:8px 0 10px!important;line-height:1}.rl-almost-filled-price strong{color:var(--rl-af-lime);font-size:20px;font-weight:800;letter-spacing:-.02em}.rl-almost-filled-price span{color:#879080;font-size:10px;font-weight:500}.rl-almost-filled-progress{margin-top:auto}.rl-almost-filled-progress-top,.rl-almost-filled-stats{display:flex;justify-content:space-between;gap:10px}.rl-almost-filled-progress-top{margin-bottom:6px;color:#899382;font-size:10px;font-weight:700;letter-spacing:.05em}.rl-almost-filled-progress-top strong{color:#dbe3d5;font-size:11px;letter-spacing:0}.rl-almost-filled-track{height:5px;overflow:hidden;border-radius:999px;background:#253025}.rl-almost-filled-track span{display:block;height:100%;border-radius:inherit;background:var(--rl-af-lime)}.rl-almost-filled-stats{margin-top:7px;color:#949d8f;font-size:10px;font-weight:600;letter-spacing:.03em}.rl-almost-filled-stats strong{color:#e7ece3;font-weight:700}.rl-almost-filled-enter{display:flex;align-items:center;justify-content:center;min-height:36px;margin-top:11px;padding:8px;border-radius:6px;background:var(--rl-af-lime);color:#091000!important;font-size:11px;font-weight:800;letter-spacing:.05em}.rl-almost-filled-enter:hover{background:#ceff4a}
        .rl-almost-filled-selection{display:flex;align-items:center;justify-content:center;gap:7px;min-height:34px;margin-top:7px;padding:7px 10px;border:1px solid #50672a;border-radius:6px;background:#080d08;color:var(--rl-af-lime)!important;font-size:10px;font-weight:800;letter-spacing:.045em;text-align:center}.rl-almost-filled-selection b{font-size:14px;font-weight:500;line-height:1}.rl-almost-filled-selection:hover{border-color:var(--rl-af-lime);background:#0d140a;color:#d3ff63!important}
        /* 0.1.20: keep the 0.1.19 desktop geometry, but match Featured Products typography weight and scale. */
        @media(min-width:768px){
        .rl-almost-filled-card{display:grid;grid-template-columns:34% minmax(0,1fr);min-height:218px}
        .rl-almost-filled-media{grid-column:1;width:auto;height:100%;min-height:218px;aspect-ratio:auto;padding:14px;border-right:1px solid #1d291d;border-bottom:0}
        .rl-almost-filled-image{max-height:188px}
        .rl-almost-filled-body{grid-column:2;padding:14px 15px 13px}
        .rl-almost-filled-badge{margin-bottom:8px;padding:4px 8px;font-size:10px;font-weight:700}
        .rl-almost-filled-body h3{font-size:18px!important;font-weight:700!important;line-height:1.28!important;letter-spacing:0!important}
        .rl-almost-filled-category{display:none}
        .rl-almost-filled-price{margin:8px 0 9px!important}
        .rl-almost-filled-price strong{font-size:24px;font-weight:800}
        .rl-almost-filled-price span{font-size:11px;font-weight:500}
        .rl-almost-filled-progress-top{margin-bottom:6px;font-size:11px;font-weight:700}
        .rl-almost-filled-progress-top span{display:none}
        .rl-almost-filled-progress-top strong{font-size:12px}
        .rl-almost-filled-track{height:5px}
        .rl-almost-filled-stats{justify-content:flex-start;gap:0;margin-top:7px;font-size:11px;font-weight:600;letter-spacing:.02em}
        .rl-almost-filled-stats span+span:before{content:'•';margin:0 8px;color:#667060}
        .rl-almost-filled-enter{align-self:stretch;min-height:38px;width:100%;margin-top:10px;padding:8px 12px;font-size:13px;font-weight:700;letter-spacing:.04em}
        .rl-almost-filled-selection{align-self:stretch;width:100%;min-height:36px;margin-top:7px;padding:8px 12px;font-size:11px;font-weight:700;letter-spacing:.035em}
        }
        @media(max-width:1100px) and (min-width:768px){.rl-almost-filled-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
        @media(max-width:767px){.rl-almost-filled{padding:22px 12px 30px}.rl-almost-filled-head{align-items:flex-end;gap:14px;margin-bottom:14px}.rl-almost-filled-kicker{margin-bottom:5px;font-size:8px}.rl-almost-filled-head h2{font-size:25px!important}.rl-almost-filled-head p{margin-top:5px!important;font-size:12px!important;line-height:1.4!important}.rl-almost-filled-view{min-height:38px;padding:7px 10px;border-radius:12px;font-size:10px;letter-spacing:.035em;gap:8px}.rl-almost-filled-view span{font-size:10px}.rl-almost-filled-view-icon{width:18px;height:18px}.rl-almost-filled-grid{grid-template-columns:1fr;gap:9px}.rl-almost-filled-card{display:grid;grid-template-columns:29% minmax(0,1fr);min-height:166px;border-radius:9px}.rl-almost-filled-media{grid-column:1;width:auto;height:auto;min-height:0;aspect-ratio:auto;padding:0;border-right:1px solid #1d291d;border-bottom:0}.rl-almost-filled-image{max-height:194px}.rl-almost-filled-body{grid-column:2;padding:11px 12px}.rl-almost-filled-badge{margin-bottom:7px;padding:4px 7px;font-size:9px}.rl-almost-filled-body h3{font-size:16px!important;line-height:1.24!important}.rl-almost-filled-category{display:none}.rl-almost-filled-price{margin:7px 0 8px!important}.rl-almost-filled-price strong{font-size:20px}.rl-almost-filled-price span{font-size:10px}.rl-almost-filled-progress-top{margin-bottom:5px;font-size:9px}.rl-almost-filled-progress-top span{display:none}.rl-almost-filled-progress-top strong{font-size:12px}.rl-almost-filled-track{height:5px}.rl-almost-filled-stats{justify-content:flex-start;gap:0;margin-top:6px;font-size:10px;letter-spacing:.02em}.rl-almost-filled-stats span+span:before{content:'•';margin:0 7px;color:#667060}.rl-almost-filled-enter{align-self:flex-start;min-height:34px;width:auto;margin-top:9px;padding:8px 14px;font-size:10px;letter-spacing:.045em}}
        @media(max-width:767px){.rl-almost-filled-selection{align-self:flex-start;min-height:34px;width:auto;margin-top:7px;padding:8px 14px;font-size:10px;letter-spacing:.04em}.rl-almost-filled-selection b{font-size:15px}}
        @media(prefers-reduced-motion:reduce){.rl-almost-filled-card{transition:none}.rl-almost-filled-card:hover{transform:none}}
        </style>
        <?php
        return ob_get_clean();
    }

    private static function selection_url($product) {
        if (!$product instanceof WC_Product) return '';
        if (class_exists('RaffleLB_Selection_Adapter') && method_exists('RaffleLB_Selection_Adapter', 'selection_url')) {
            $url = (string) RaffleLB_Selection_Adapter::selection_url($product);
            if ($url !== '') return $url;
        }
        $slug = (string) get_post_field('post_name', $product->get_id());
        return $slug !== '' ? home_url('/selection/' . rawurlencode($slug) . '/') : '';
    }

    private static function money($amount) {
        return html_entity_decode(get_woocommerce_currency_symbol(), ENT_QUOTES | ENT_HTML5, 'UTF-8') . number_format((float)$amount, 2, '.', ',');
    }

    private static function render_store_card($product, $compact = false) {
        if (!$product instanceof WC_Product) return;
        $title = $product->get_name();
        $url   = get_permalink($product->get_id());
        $price = self::product_buy_price($product);
        $image_id = $product->get_image_id();
        ?>
        <article class="rlsh-product-card<?php echo $compact ? ' is-compact' : ''; ?>">
            <a class="rlsh-product-media" href="<?php echo esc_url($url); ?>" aria-label="<?php echo esc_attr($title); ?>">
                <?php if ($image_id): ?>
                    <?php echo wp_get_attachment_image($image_id, 'woocommerce_thumbnail', false, ['class'=>'rlsh-product-img','loading'=>'lazy','alt'=>$title]); ?>
                <?php else: ?><div class="rlsh-product-noimg">R</div><?php endif; ?>
            </a>
            <div class="rlsh-product-body">
                <h3><a data-rlsh-fit-title href="<?php echo esc_url($url); ?>" title="<?php echo esc_attr($title); ?>"><?php echo esc_html($title); ?></a></h3>
                <div class="rlsh-product-price"><?php echo esc_html(self::money($price)); ?></div>
                <a class="rlsh-buy" href="<?php echo esc_url($url); ?>"><span>BUY NOW</span><span aria-hidden="true">▢</span></a>
            </div>
        </article>
        <?php
    }

    private static function render_raffle_card($row) {
        $product = $row['product'] ?? null;
        $stats = $row['stats'] ?? [];
        if (!$product instanceof WC_Product) return;
        $title = $product->get_name();
        $url = get_permalink($product->get_id());
        $image_id = $product->get_image_id();
        $price = (float) wc_get_price_to_display($product);
        $claimed = absint($stats['claimed'] ?? 0);
        $available = absint($stats['available'] ?? 0);
        $total = absint($stats['total'] ?? 0);
        $percent = absint($stats['percent'] ?? 0);
        $selection = self::selection_url($product);
        ?>
        <article class="rlsh-raffle-card">
            <div class="rlsh-raffle-badge"><i></i> LIVE</div>
            <a class="rlsh-raffle-media" href="<?php echo esc_url($url); ?>">
                <?php if ($image_id): echo wp_get_attachment_image($image_id, 'woocommerce_thumbnail', false, ['loading'=>'lazy','alt'=>$title]); else: ?><span>R</span><?php endif; ?>
            </a>
            <div class="rlsh-raffle-body">
                <h3><a data-rlsh-fit-title href="<?php echo esc_url($url); ?>" title="<?php echo esc_attr($title); ?>"><?php echo esc_html($title); ?></a></h3>
                <div class="rlsh-raffle-price"><strong><?php echo esc_html(self::money($price)); ?></strong><span>/ ENTRY</span></div>
                <div class="rlsh-raffle-track"><span style="width:<?php echo esc_attr(min(100,$percent)); ?>%"></span></div>
                <div class="rlsh-raffle-stats"><span><?php echo esc_html($claimed); ?> CLAIMED</span><span><?php echo esc_html($available); ?> LEFT</span></div>
                <a class="rlsh-enter" href="<?php echo esc_url($url); ?>">ENTER RAFFLE</a>
                <?php if ($selection): ?><a class="rlsh-selection" href="<?php echo esc_url($selection); ?>">VIEW SELECTION STATUS <b>→</b></a><?php endif; ?>
            </div>
        </article>
        <?php
    }

    private static function render_raffle_slide($row, $index = 0) {
        $product = $row['product'] ?? null;
        $stats = $row['stats'] ?? [];
        if (!$product instanceof WC_Product) return;
        $title = $product->get_name();
        $url = get_permalink($product->get_id());
        $image_id = $product->get_image_id();
        $price = (float) wc_get_price_to_display($product);
        $claimed = absint($stats['claimed'] ?? 0);
        $available = absint($stats['available'] ?? 0);
        $total = absint($stats['total'] ?? 0);
        $percent = absint($stats['percent'] ?? 0);
        $selection = self::selection_url($product);
        ?>
        <?php
        $short = trim(wp_strip_all_tags($product->get_short_description()));
        $short = $short ? wp_trim_words($short, 16, '…') : '';
        if ($total > 0 && $percent >= 75) $status_badge = 'LIMITED ENTRIES';
        elseif ($total > 0 && $percent >= 40) $status_badge = 'FILLING FAST';
        else $status_badge = 'OPEN NOW';
        ?>
        <article class="rlsh-raffle-slide<?php echo $index === 0 ? ' is-active' : ''; ?>" data-rlsh-raffle-slide aria-hidden="<?php echo $index === 0 ? 'false' : 'true'; ?>">
            <div class="rlsh-raffle-feature-copy">
                <div class="rlsh-raffle-feature-badges">
                    <span class="rlsh-raffle-feature-badge"><i></i> LIVE RAFFLE</span>
                    <span class="rlsh-raffle-feature-status"><?php echo esc_html($status_badge); ?></span>
                </div>
                <h3><a href="<?php echo esc_url($url); ?>"><?php echo esc_html($title); ?></a></h3>
                <?php if ($short): ?><p class="rlsh-raffle-feature-desc"><?php echo esc_html($short); ?></p><?php endif; ?>
                <div class="rlsh-raffle-feature-pricebox"><span>ENTRY PRICE</span><strong><?php echo esc_html(self::money($price)); ?></strong><small>per entry</small></div>
                <div class="rlsh-raffle-feature-info">
                    <span class="rlsh-raffle-feature-info-item"><i class="rlsh-raffle-feature-info-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="3.2"></circle><path d="M5 20c0-3.5 3-6 7-6s7 2.5 7 6"></path></svg></i><strong><?php echo esc_html($claimed); ?></strong><span>CLAIMED</span></span>
                    <span class="rlsh-raffle-feature-info-item"><i class="rlsh-raffle-feature-info-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="8"></circle><path d="M12 8v4l3 2"></path></svg></i><strong><?php echo esc_html($available); ?></strong><span>LEFT</span></span>
                    <?php if ($total): ?><span class="rlsh-raffle-feature-info-item"><i class="rlsh-raffle-feature-info-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 4.5 4 8.5l8 4 8-4-8-4Z"></path><path d="M4 12.5l8 4 8-4"></path><path d="M4 16.5l8 4 8-4"></path></svg></i><strong><?php echo esc_html($total); ?></strong><span>TOTAL</span></span><?php endif; ?>
                    <span class="rlsh-raffle-feature-info-item is-filled"><i class="rlsh-raffle-feature-info-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="8"></circle><path d="M12 4v8h8"></path></svg></i><strong><?php echo esc_html($percent); ?>%</strong><span>FILLED</span></span>
                </div>
                <div class="rlsh-raffle-feature-progress">
                    <div class="rlsh-raffle-feature-track"><span style="width:<?php echo esc_attr(min(100,$percent)); ?>%"></span></div>
                    <strong><?php echo esc_html($percent); ?>%</strong>
                </div>
                <div class="rlsh-raffle-feature-actions">
                    <a class="rlsh-enter" href="<?php echo esc_url($url); ?>">ENTER RAFFLE <b>→</b></a>
                    <?php if ($selection): ?><a class="rlsh-selection" href="<?php echo esc_url($selection); ?>">VIEW SELECTION STATUS <b>→</b></a><?php endif; ?>
                </div>
            </div>
            <a class="rlsh-raffle-feature-visual" href="<?php echo esc_url($url); ?>" aria-label="<?php echo esc_attr($title); ?>">
                <span class="rlsh-raffle-feature-geo" aria-hidden="true"></span>
                <span class="rlsh-raffle-feature-glow" aria-hidden="true"></span>
                <span class="rlsh-raffle-feature-platform" aria-hidden="true"></span>
                <?php if ($image_id): echo wp_get_attachment_image($image_id, 'large', false, ['loading'=>$index===0?'eager':'lazy','alt'=>$title,'class'=>'rlsh-raffle-feature-img']); else: ?><span class="rlsh-product-noimg">R</span><?php endif; ?>
            </a>
        </article>
        <?php
    }

    private static function perfume_pool_ids() {
        $term = self::category_term('perfumes', 'Perfumes');
        if (!$term) return [];

        $term_ids = [(int) $term->term_id];
        $children = get_term_children((int) $term->term_id, 'product_cat');
        if (!is_wp_error($children) && $children) {
            foreach ($children as $child_id) $term_ids[] = (int) $child_id;
        }
        $term_ids = array_values(array_unique(array_filter(array_map('absint', $term_ids))));

        return get_posts([
            'post_type'              => 'product',
            'post_status'            => 'publish',
            'posts_per_page'         => 300,
            'fields'                 => 'ids',
            'orderby'                => 'rand',
            'ignore_sticky_posts'    => true,
            'no_found_rows'          => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
            'tax_query'              => [[
                'taxonomy' => 'product_cat',
                'field'    => 'term_id',
                'terms'    => $term_ids,
                'operator' => 'IN',
            ]],
            'meta_query'             => [[
                'key'     => '_stock_status',
                'value'   => 'instock',
                'compare' => '=',
            ]],
        ]);
    }

    public static function ajax_perfumes() {
        if (!function_exists('wc_get_product')) wp_send_json_success(['html'=>'']);
        nocache_headers();
        $ids = self::perfume_pool_ids();
        $products = [];
        foreach ($ids as $pid) {
            $product = wc_get_product($pid);
            if (!$product || !$product->is_visible() || !$product->is_purchasable() || !$product->is_in_stock()) continue;

            // Store-only is presentation only. Perfumes may also have a raffle elsewhere.
            $buy_price = (float) wc_get_price_to_display($product);
            if ($buy_price <= 0) continue;

            $products[] = $product;
            if (count($products) >= 6) break;
        }
        ob_start();
        foreach ($products as $product) self::render_store_card($product, true);
        wp_send_json_success(['html'=>ob_get_clean()]);
    }

    public static function brands_shortcode() {
        if (!function_exists('wc_get_product')) {
            return '<div style="padding:30px">WooCommerce is required for the RaffleLB brand directory.</div>';
        }

        [$brand_taxonomy, $brands] = self::all_brand_terms();
        ob_start();
        ?>
        <div class="rlbd">
            <section class="rlbd-top">
                <h1>SHOP BY <span>BRAND</span></h1>
                <a href="<?php echo esc_url(self::shop_url(['rl_open_filters'=>'1'])); ?>">VIEW ALL PRODUCTS <b>→</b></a>
            </section>

            <?php if ($brands): ?>
            <section class="rlbd-grid" aria-label="RaffleLB brands">
                <?php foreach ($brands as $brand):
                    $logo = self::brand_image_url($brand);
                    $url  = self::shop_url(['rl_brand'=>$brand->slug,'rl_open_filters'=>'1']);
                ?>
                    <a class="rlbd-card" href="<?php echo esc_url($url); ?>" title="<?php echo esc_attr($brand->name); ?>">
                        <div class="rlbd-logo">
                            <?php if ($logo): ?>
                                <img src="<?php echo esc_url($logo); ?>" alt="<?php echo esc_attr($brand->name); ?>" loading="lazy">
                            <?php else: ?>
                                <strong><?php echo esc_html($brand->name); ?></strong>
                            <?php endif; ?>
                        </div>
                        <div class="rlbd-meta"><span><?php echo esc_html($brand->name); ?></span><b>→</b></div>
                    </a>
                <?php endforeach; ?>
            </section>
            <?php else: ?>
                <div class="rlbd-empty">No brands are available yet.</div>
            <?php endif; ?>
        </div>
        <style id="rafflelb-brand-directory-v012">
        body.rafflelb-brand-directory-page .page-title,body.rafflelb-brand-directory-page .entry-title,body.rafflelb-brand-directory-page .wd-page-title{display:none!important}
        body.rafflelb-brand-directory-page .site-content,body.rafflelb-brand-directory-page .main-page-wrapper,body.rafflelb-brand-directory-page .main-page-wrapper>.container,body.rafflelb-brand-directory-page .site-content>.container{max-width:none!important;width:100%!important;padding-left:0!important;padding-right:0!important}
        .rlbd{--lime:#baff00;--bg:#050805;--panel:#0b100b;--line:#2b3828;position:relative;left:50%;width:100vw;margin-left:-50vw;padding:42px clamp(16px,3vw,56px) 64px;background:var(--bg);color:#f7f9f4;font-family:var(--rl-font,"Manrope",-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif)!important;box-sizing:border-box}
        .rlbd *{box-sizing:border-box;font-family:inherit!important}
        .rlbd-top{max-width:1840px;margin:0 auto 28px;display:flex;align-items:center;justify-content:space-between;gap:18px}
        .rlbd-top h1{margin:0!important;color:#fff!important;font-size:42px!important;font-weight:800!important;line-height:1!important;letter-spacing:-.035em}.rlbd-top h1 span{color:var(--lime)}
        .rlbd-top>a{min-height:46px;padding:0 20px;display:inline-flex;align-items:center;gap:18px;border:1px solid var(--lime);border-radius:11px;color:#fff!important;font-size:10px;font-weight:800;letter-spacing:.08em;text-decoration:none!important}.rlbd-top>a b{color:var(--lime);font-size:16px}
        .rlbd-grid{max-width:1840px;margin:0 auto;display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:14px}
        .rlbd-card{min-width:0;overflow:hidden;border:1px solid var(--line);border-radius:13px;background:linear-gradient(180deg,#080c08,#0b100b);color:#fff!important;text-decoration:none!important;transition:transform .18s ease,border-color .18s ease,box-shadow .18s ease}
        .rlbd-card:hover{transform:translateY(-2px);border-color:rgba(186,255,0,.7);box-shadow:0 12px 28px rgba(0,0,0,.22)}
        .rlbd-logo{height:150px;padding:24px;display:flex;align-items:center;justify-content:center;background:#050705;border-bottom:1px solid #1c271c}
        .rlbd-logo img{display:block;max-width:86%;max-height:82px;width:auto;height:auto;object-fit:contain}
        .rlbd-logo strong{font-size:20px;font-weight:800;line-height:1.1;text-align:center}
        .rlbd-meta{min-height:50px;padding:0 15px;display:flex;align-items:center;justify-content:space-between;gap:12px;font-size:13px;font-weight:700}.rlbd-meta b{color:var(--lime);font-size:16px}
        .rlbd-empty{max-width:1840px;margin:auto;padding:30px;border:1px solid var(--line);border-radius:12px;color:#aeb8aa}
        @media(max-width:1300px){.rlbd-grid{grid-template-columns:repeat(4,minmax(0,1fr))}}
        @media(max-width:900px){.rlbd{padding-top:28px}.rlbd-grid{grid-template-columns:repeat(3,minmax(0,1fr))}.rlbd-top h1{font-size:34px!important}}
        @media(max-width:600px){.rlbd{padding:24px 12px 46px}.rlbd-top{align-items:flex-start}.rlbd-top h1{font-size:28px!important}.rlbd-top>a{min-height:38px;padding:0 12px;font-size:8px}.rlbd-grid{grid-template-columns:repeat(2,minmax(0,1fr));gap:9px}.rlbd-logo{height:108px;padding:15px}.rlbd-logo img{max-height:60px}.rlbd-logo strong{font-size:15px}.rlbd-meta{min-height:44px;padding:0 11px;font-size:11px}}
        </style>
        <?php
        return ob_get_clean();
    }


    /** Keep the Store Hub Almost Filled block fresh when raffle state changes. */
    private static function purge_store_hub_cache() {
        static $purged = false;
        if ($purged) return;
        $purged = true;
        $page_id = absint(get_option(self::PAGE_OPTION));
        if ($page_id && function_exists('rocket_clean_post')) rocket_clean_post($page_id);
        if (function_exists('rocket_clean_domain')) rocket_clean_domain();
    }

    public static function raffle_order_cache_purge($order_id) {
        if (!function_exists('wc_get_order') || !class_exists('RaffleLB_Draw_Engine')) return;
        $order = wc_get_order($order_id);
        if (!$order) return;
        foreach ($order->get_items('line_item') as $item) {
            if (!$item instanceof WC_Order_Item_Product) continue;
            $mode = sanitize_key((string) $item->get_meta(RaffleLB_Draw_Engine::ITEM_MODE_META, true));
            if ($mode === 'buy_now') continue;
            $product = $item->get_product();
            if (!$product instanceof WC_Product) continue;
            if (!RaffleLB_Draw_Engine::homepage_draw_id($product)) continue;
            self::purge_store_hub_cache();
            return;
        }
    }

    public static function raffle_meta_cache_purge($meta_id, $object_id, $meta_key, $meta_value = null) {
        if (!class_exists('RaffleLB_Draw_Engine')) return;
        $object_id = absint($object_id);
        if (!$object_id || get_post_type($object_id) !== 'product') return;
        $watched = [
            RaffleLB_Draw_Engine::META_ENABLED,
            RaffleLB_Draw_Engine::META_TOTAL,
            RaffleLB_Draw_Engine::META_DRAW_STATUS,
            RaffleLB_Draw_Engine::META_EARLY_CLOSED,
        ];
        if (!in_array((string) $meta_key, $watched, true)) return;
        self::purge_store_hub_cache();
    }

    public static function shortcode() {
        if (!function_exists('wc_get_product') || !taxonomy_exists('product_cat')) {
            return '<div style="padding:30px">WooCommerce is required for the RaffleLB Store Hub.</div>';
        }

        $categories = [
            ['perfumes','Perfumes'],
            ['cosmetics','Cosmetics'],
            ['electronics','Electronics'],
            ['home-appliances','Home Appliances'],
            ['vouchers-gift-cards','Vouchers & Gift Cards'],
            ['experiences','Experiences'],
        ];
        $category_rows = [];
        foreach ($categories as $pair) {
            $term = self::category_term($pair[0], $pair[1]);
            if (!$term) continue;
            $link = get_term_link($term);
            if (is_wp_error($link)) continue;
            $category_rows[] = [
                'term'  => $term,
                'url'   => add_query_arg(['rl_view'=>'retail','rl_open_filters'=>'1'], $link),
                'image' => self::category_image_url($term),
            ];
        }

        [$brand_taxonomy, $brands] = self::brand_terms(10);
        $raffles = self::live_raffles(5);
        $ajax_url = admin_url('admin-ajax.php');

        ob_start();
        ?>
        <div class="rlsh" data-rlsh-root>
            <section class="rlsh-search-wrap" aria-label="Search the RaffleLB Store">
                <form class="rlsh-search" data-rlsh-search action="<?php echo esc_url(function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : home_url('/shop/')); ?>" method="get">
                    <input type="hidden" name="rl_search_scope" value="all">
                    <span class="rlsh-search-icon" aria-hidden="true"></span>
                    <input class="rlsh-search-input" data-rlsh-search-input type="search" name="rl_search" autocomplete="off" spellcheck="false" placeholder="Search products, raffles, brands & categories..." aria-label="Search products, raffles, brands and categories" aria-expanded="false" aria-controls="rlsh-search-results">
                    <button class="rlsh-search-submit" type="submit">SEARCH <b>→</b></button>
                    <div id="rlsh-search-results" class="rlsh-search-results" data-rlsh-search-results hidden></div>
                </form>
            </section>

            <section class="rlsh-section rlsh-raffles">
                <div class="rlsh-head rlsh-head-featured"><div class="rlsh-head-copy"><span class="rlsh-kicker">LIVE NOW</span><h2>LIVE <span>RAFFLES</span></h2><p>Explore active selections available now.</p></div><a href="<?php echo esc_url(self::raffles_url()); ?>">VIEW ALL RAFFLES <b>→</b></a></div>
                <?php if ($raffles): ?>
                <div class="rlsh-raffle-showcase" data-rlsh-raffle-carousel>
                    <div class="rlsh-raffle-stage">
                        <?php foreach ($raffles as $i => $row) self::render_raffle_slide($row, $i); ?>
                    </div>
                    <?php if (count($raffles) > 1): ?>
                    <div class="rlsh-raffle-controls" aria-label="Live raffle carousel controls">
                        <button type="button" data-rlsh-raffle-prev aria-label="Previous raffle">‹</button>
                        <div class="rlsh-raffle-dots">
                            <?php foreach ($raffles as $i => $row): ?><button type="button" class="<?php echo $i===0?'is-active':''; ?>" data-rlsh-raffle-dot="<?php echo esc_attr($i); ?>" aria-label="Show raffle <?php echo esc_attr($i+1); ?>"></button><?php endforeach; ?>
                        </div>
                        <button type="button" data-rlsh-raffle-next aria-label="Next raffle">›</button>
                    </div>
                    <?php endif; ?>
                </div>
                <?php else: ?><div class="rlsh-empty">No live raffles right now.</div><?php endif; ?>
            </section>


            <section class="rlsh-section rlsh-categories">
                <div class="rlsh-head rlsh-head-featured"><div class="rlsh-head-copy"><span class="rlsh-kicker">EXPLORE THE STORE</span><h2>SHOP BY <span>CATEGORY</span></h2><p>Browse RaffleLB by category.</p></div><a href="<?php echo esc_url(self::shop_url(['rl_open_filters'=>'1'])); ?>">VIEW ALL CATEGORIES <b>→</b></a></div>
                <div class="rlsh-category-grid">
                    <?php foreach ($category_rows as $row): $term=$row['term']; ?>
                    <a class="rlsh-category-card" href="<?php echo esc_url($row['url']); ?>">
                        <div class="rlsh-category-image"><?php if ($row['image']): ?><img src="<?php echo esc_url($row['image']); ?>" alt="<?php echo esc_attr($term->name); ?>" loading="lazy"><?php endif; ?></div>
                        <strong><?php echo esc_html($term->name); ?></strong><span>→</span>
                    </a>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="rlsh-section rlsh-brands">
                <div class="rlsh-head rlsh-head-featured">
                    <div class="rlsh-head-copy"><span class="rlsh-kicker">EXPLORE BY BRAND</span><h2>SHOP BY <span>BRAND</span></h2><p>Find products from the brands you know.</p></div>
                    <a href="<?php echo esc_url(self::brand_directory_url()); ?>">VIEW ALL BRANDS <b>→</b></a>
                </div>
                <?php if ($brands): ?><div class="rlsh-brand-grid">
                    <?php foreach ($brands as $brand): $logo=self::brand_image_url($brand); $url=self::shop_url(['rl_brand'=>$brand->slug,'rl_open_filters'=>'1']); ?>
                    <a class="rlsh-brand-card" href="<?php echo esc_url($url); ?>">
                        <?php if ($logo): ?><img src="<?php echo esc_url($logo); ?>" alt="<?php echo esc_attr($brand->name); ?>" loading="lazy"><?php else: ?><strong><?php echo esc_html($brand->name); ?></strong><?php endif; ?>
                    </a>
                    <?php endforeach; ?>
                </div><?php else: ?><div class="rlsh-empty">Brands will appear here automatically when available.</div><?php endif; ?>
            </section>

            <section class="rlsh-section rlsh-budget">
                <div class="rlsh-head rlsh-head-featured">
                    <div class="rlsh-head-copy"><span class="rlsh-kicker">FIND YOUR RANGE</span><h2>SHOP BY <span>BUDGET</span></h2><p>Browse products by price.</p></div>
                    <a href="<?php echo esc_url(self::shop_url(['rl_open_filters'=>'1'])); ?>">BROWSE BY BUDGET <b>→</b></a>
                </div>
                <div class="rlsh-budget-grid">
                    <a href="<?php echo esc_url(self::shop_url(['max_price'=>'25','rl_open_filters'=>'1'])); ?>"><span class="rlsh-budget-left"><i class="rlsh-budget-icon" aria-hidden="true">$</i><strong>Under $25</strong></span><b>→</b></a>
                    <a href="<?php echo esc_url(self::shop_url(['min_price'=>'25','max_price'=>'50','rl_open_filters'=>'1'])); ?>"><span class="rlsh-budget-left"><i class="rlsh-budget-icon" aria-hidden="true">$</i><strong>$25 – $50</strong></span><b>→</b></a>
                    <a href="<?php echo esc_url(self::shop_url(['min_price'=>'50','max_price'=>'100','rl_open_filters'=>'1'])); ?>"><span class="rlsh-budget-left"><i class="rlsh-budget-icon" aria-hidden="true">$</i><strong>$50 – $100</strong></span><b>→</b></a>
                    <a href="<?php echo esc_url(self::shop_url(['min_price'=>'100','max_price'=>'250','rl_open_filters'=>'1'])); ?>"><span class="rlsh-budget-left"><i class="rlsh-budget-icon" aria-hidden="true">$</i><strong>$100 – $250</strong></span><b>→</b></a>
                    <a href="<?php echo esc_url(self::shop_url(['min_price'=>'250','rl_open_filters'=>'1'])); ?>"><span class="rlsh-budget-left"><i class="rlsh-budget-icon" aria-hidden="true">$</i><strong>$250+</strong></span><b>→</b></a>
                </div>
            </section>

            <?php echo self::almost_filled_shortcode(); ?>

        </div>

        <style id="rafflelb-store-hub-v0113">
        body.rafflelb-store-hub-page{background:#050805!important}
        body.rafflelb-store-hub-page .page-title,body.rafflelb-store-hub-page .entry-title,body.rafflelb-store-hub-page .wd-page-title{display:none!important}
        body.rafflelb-store-hub-page .website-wrapper,body.rafflelb-store-hub-page #page,body.rafflelb-store-hub-page .main-page-wrapper,body.rafflelb-store-hub-page .site-content,body.rafflelb-store-hub-page .content-layout-wrapper,body.rafflelb-store-hub-page .wd-content-layout,body.rafflelb-store-hub-page .page-wrapper,body.rafflelb-store-hub-page .main-page-wrapper>.container,body.rafflelb-store-hub-page .site-content>.container,body.rafflelb-store-hub-page article.page,body.rafflelb-store-hub-page .entry-content{max-width:none!important;width:100%!important;margin-top:0!important;margin-bottom:0!important;padding-top:0!important;padding-bottom:0!important;padding-left:0!important;padding-right:0!important;background:#050805!important}
        body.rafflelb-store-hub-page footer,body.rafflelb-store-hub-page .footer-container,body.rafflelb-store-hub-page .site-footer,body.rafflelb-store-hub-page .wd-footer{margin-top:0!important;background-color:#050805!important}
        .rlsh{--lime:#baff00;--bg:#050805;--panel:#0b100b;--panel2:#101610;--line:#2b3828;--muted:#aeb8aa;position:relative;left:50%;width:100vw;margin-left:-50vw;background:var(--bg);color:#f7f9f4;font-family:var(--rl-font,"Manrope",-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif)!important;overflow:hidden;border-bottom:1px solid rgba(186,255,0,.08)}
        .rlsh *{box-sizing:border-box;font-family:inherit!important;text-shadow:none!important}.rlsh a{text-decoration:none!important}.rlsh img{max-width:100%;height:auto}.rlsh-section{padding:34px clamp(18px,3vw,54px);border-top:1px solid rgba(186,255,0,.08)}
        .rlsh-search-wrap{position:relative;z-index:25;padding:18px clamp(18px,3vw,54px) 8px;background:var(--bg)}
        .rlsh-search{position:relative;max-width:1840px;margin:0 auto;display:flex;align-items:center;min-height:58px;border:1px solid #2c382b;border-radius:11px;background:linear-gradient(145deg,#0b100b,#050805);box-shadow:0 12px 30px rgba(0,0,0,.18)}
        .rlsh-search:focus-within{border-color:rgba(186,255,0,.7);box-shadow:0 0 0 3px rgba(186,255,0,.06)}
        .rlsh-search-icon{position:relative;width:18px;height:18px;flex:0 0 18px;margin-left:18px;margin-right:12px;border:2px solid #7f887b;border-radius:50%}.rlsh-search-icon:after{content:"";position:absolute;width:7px;height:2px;right:-5px;bottom:-2px;border-radius:2px;background:#7f887b;transform:rotate(45deg)}
        .rlsh-search-input{min-width:0;flex:1 1 auto;height:56px!important;margin:0!important;padding:0 14px 0 0!important;border:0!important;outline:0!important;background:transparent!important;box-shadow:none!important;color:#f7f9f4!important;font-family:inherit!important;font-size:14px!important;font-weight:650!important}.rlsh-search-input::placeholder{color:#687166!important;opacity:1}
        .rlsh-search-submit{height:44px;margin-right:7px;padding:0 18px;border:1px solid #7da200;border-radius:7px;background:rgba(186,255,0,.07);color:#fff;font-family:inherit;font-size:10px;font-weight:900;letter-spacing:.06em;cursor:pointer}.rlsh-search-submit b{margin-left:10px;color:var(--lime);font-size:16px}.rlsh-search-submit:hover{background:var(--lime);color:#061000}.rlsh-search-submit:hover b{color:#061000}
        .rlsh-search-results{position:absolute;left:0;right:0;top:calc(100% + 6px);overflow:hidden;border:1px solid #2a3429;border-radius:11px;background:#070a07;box-shadow:0 22px 60px rgba(0,0,0,.58)}
        .rlsh-search-status{padding:16px;color:#818a7d;font-size:12px;font-weight:650;text-align:center}.rlsh-search-list{max-height:420px;overflow:auto;padding:7px}.rlsh-search-item{display:grid;grid-template-columns:54px minmax(0,1fr) auto;gap:11px;align-items:center;min-height:68px;padding:7px 10px;border-radius:8px;color:#fff!important}.rlsh-search-item:hover{background:#111610}.rlsh-search-thumb{width:54px;height:54px;border:1px solid #192019;border-radius:7px;overflow:hidden;background:#000}.rlsh-search-thumb img{width:100%!important;height:100%!important;object-fit:contain!important}.rlsh-search-copy{min-width:0}.rlsh-search-type{display:block;margin-bottom:3px;color:var(--lime);font-size:8px;font-weight:900;letter-spacing:.08em}.rlsh-search-name{display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:#f5f7f2;font-size:13px;font-weight:800}.rlsh-search-cat{display:block;margin-top:3px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:#687065;font-size:9px;font-weight:650}.rlsh-search-price{max-width:230px;color:#cbd0c8;font-size:10px;font-weight:800;text-align:right;white-space:nowrap}.rlsh-search-footer{display:flex;justify-content:space-between;align-items:center;gap:12px;padding:10px 12px;border-top:1px solid #1e251d;background:#090d09}.rlsh-search-footer span{color:#727a70;font-size:10px;font-weight:700}.rlsh-search-footer a{min-height:32px;padding:0 12px;display:inline-flex;align-items:center;border:1px solid rgba(186,255,0,.34);border-radius:6px;color:var(--lime)!important;font-size:9px;font-weight:900;letter-spacing:.05em}
                .rlsh-head{display:flex;align-items:end;justify-content:space-between;gap:20px;max-width:1840px;margin:0 auto 18px}.rlsh-head h2{margin:0!important;color:#fff!important;font-size:clamp(24px,2.3vw,36px)!important;line-height:1!important;font-weight:900!important;letter-spacing:-.03em!important}.rlsh-head h2 span{color:var(--lime)}.rlsh-head>a{display:inline-flex;align-items:center;gap:16px;min-height:42px;padding:0 18px;border:1px solid #7da200;border-radius:8px;color:#fff!important;font-size:10px;font-weight:800;letter-spacing:.055em;white-space:nowrap}.rlsh-head>a b{color:var(--lime);font-size:18px}
        .rlsh-brands,.rlsh-budget{padding-top:27px;padding-bottom:29px}.rlsh-head-featured{align-items:center;margin-bottom:14px}.rlsh-head-copy{min-width:0}.rlsh-kicker{display:block;margin:0 0 6px;color:var(--lime);font-size:9px;font-weight:900;letter-spacing:.16em;line-height:1;text-transform:uppercase}.rlsh-head-featured h2{font-size:clamp(25px,2vw,32px)!important}.rlsh-head-copy p{margin:7px 0 0!important;color:#879183!important;font-size:12px!important;line-height:1.35!important;font-weight:550!important}
        .rlsh-category-grid{max-width:1840px;margin:auto;display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:14px}.rlsh-category-card{position:relative;min-height:220px;border:1px solid var(--line);border-radius:10px;overflow:hidden;background:#080b08;color:#fff!important}.rlsh-category-image{position:absolute;inset:0}.rlsh-category-image:after{content:"";position:absolute;inset:35% 0 0;background:linear-gradient(transparent,#020402 88%)}.rlsh-category-image img{width:100%;height:100%!important;object-fit:cover}.rlsh-category-card strong{position:absolute;left:15px;bottom:13px;z-index:2;font-size:16px}.rlsh-category-card>span{position:absolute;right:15px;bottom:11px;z-index:2;color:var(--lime);font-size:22px}
        .rlsh-brand-grid{max-width:1840px;margin:auto;display:grid;grid-template-columns:repeat(10,minmax(0,1fr));gap:10px}.rlsh-brand-card{height:102px;padding:13px;display:flex;align-items:center;justify-content:center;border:1px solid var(--line);border-radius:9px;background:linear-gradient(145deg,#080c08,#060906);color:#fff!important;text-align:center}.rlsh-brand-card img{max-width:86%;max-height:54px;object-fit:contain;filter:brightness(1.08);transition:transform .18s ease}.rlsh-brand-card:hover img{transform:scale(1.035)}.rlsh-brand-card strong{font-size:14px;line-height:1.1}
        .rlsh-budget-grid{max-width:1840px;margin:auto;display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:12px}.rlsh-budget-grid a{min-height:72px;padding:0 18px;display:flex;align-items:center;justify-content:space-between;border:1px solid var(--line);border-radius:9px;background:linear-gradient(90deg,#0d130d,#071007);color:#fff!important}.rlsh-budget-left{display:flex;align-items:center;gap:11px;min-width:0}.rlsh-budget-icon{width:28px;height:28px;flex:0 0 28px;display:inline-flex;align-items:center;justify-content:center;border:1px solid rgba(186,255,0,.34);border-radius:50%;background:rgba(186,255,0,.04);color:var(--lime);font-style:normal;font-size:12px;font-weight:900}.rlsh-budget-grid strong{font-size:15px}.rlsh-budget-grid b{color:var(--lime);font-size:20px}
        .rlsh-product-grid{max-width:1840px;margin:auto;display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:14px}.rlsh-product-card{display:flex;flex-direction:column;min-width:0;border:1px solid var(--line);border-radius:10px;overflow:hidden;background:#0b100b}.rlsh-product-media{height:210px;padding:18px;display:flex;align-items:center;justify-content:center;background:#020402;border-bottom:1px solid #222c22}.rlsh-product-img{width:100%!important;height:100%!important;object-fit:contain!important}.rlsh-product-noimg{font-size:42px;color:var(--lime);font-weight:900}.rlsh-product-body{display:flex;flex:1;flex-direction:column;padding:13px}.rlsh-product-body h3{height:24px;margin:0 0 12px!important;font-size:16px!important;line-height:1.3!important;font-weight:800!important;overflow:hidden;white-space:nowrap}.rlsh-product-body h3 a{display:block;color:#fff!important;white-space:nowrap}.rlsh-product-price{margin:auto 0 12px;text-align:center;color:#fff;font-size:22px;font-weight:900}.rlsh-buy{min-height:40px;display:flex;align-items:center;justify-content:center;gap:8px;border-radius:6px;background:var(--lime);color:#061000!important;font-size:11px;font-weight:900}
        .rlsh-empty{max-width:1840px;margin:auto;padding:25px;border:1px solid var(--line);border-radius:9px;color:#aeb8aa;text-align:center}.rlsh-skeleton{min-height:330px;border:1px solid var(--line);border-radius:10px;background:#0b100b;overflow:hidden}.rlsh-skeleton span{display:block;height:62%;background:linear-gradient(90deg,#020402,#111811,#020402);background-size:200% 100%;animation:rlshPulse 1.1s linear infinite}.rlsh-skeleton i,.rlsh-skeleton b{display:block;height:13px;margin:14px;border-radius:7px;background:#1d281d}.rlsh-skeleton b{height:40px;margin-top:30px}@keyframes rlshPulse{to{background-position:-200% 0}}
        .rlsh a:hover{border-color:var(--lime)}.rlsh-category-card:hover img,.rlsh-product-card:hover img{transform:scale(1.025)}.rlsh-category-card img,.rlsh-product-card img{transition:transform .2s ease}@media(max-width:1500px){.rlsh-category-grid{grid-template-columns:repeat(3,1fr)}.rlsh-brand-grid{grid-template-columns:repeat(5,1fr)}.rlsh-product-grid{grid-template-columns:repeat(3,1fr)}}@media(max-width:900px){.rlsh-category-grid{grid-template-columns:repeat(2,1fr)}.rlsh-brand-grid{grid-template-columns:repeat(2,1fr)}.rlsh-budget-grid{grid-template-columns:repeat(2,1fr)}.rlsh-product-grid{grid-template-columns:repeat(2,1fr)}.rlsh-head{align-items:flex-start}.rlsh-head>a{min-height:38px;padding:0 12px}}@media(max-width:600px){.rlsh-search-wrap{padding:12px 12px 4px}.rlsh-search{min-height:52px}.rlsh-search-icon{margin-left:13px;margin-right:9px}.rlsh-search-input{height:50px!important;font-size:13px!important}.rlsh-search-submit{width:44px;height:38px;padding:0;margin-right:6px;font-size:0}.rlsh-search-submit b{margin:0;font-size:18px}.rlsh-search-item{grid-template-columns:48px minmax(0,1fr);gap:9px}.rlsh-search-thumb{width:48px;height:48px}.rlsh-search-price{grid-column:2;max-width:none;text-align:left;white-space:normal;margin-top:-4px}.rlsh-search-list{max-height:330px}.rlsh-section{padding:28px 12px}.rlsh-head{gap:12px}.rlsh-head h2{font-size:26px!important}.rlsh-head-featured{margin-bottom:12px}.rlsh-kicker{font-size:8px;margin-bottom:5px}.rlsh-head-copy p{font-size:11px!important;margin-top:5px!important}.rlsh-head>a{font-size:8px;gap:8px}.rlsh-category-card{min-height:160px}.rlsh-category-card strong{font-size:13px}.rlsh-brand-card{height:84px}.rlsh-budget-grid{grid-template-columns:1fr 1fr;gap:8px}.rlsh-budget-grid a{min-height:62px;padding:0 11px}.rlsh-budget-left{gap:7px}.rlsh-budget-icon{width:24px;height:24px;flex-basis:24px;font-size:10px}.rlsh-budget-grid strong{font-size:13px}.rlsh-product-grid{gap:9px}.rlsh-product-media{height:155px;padding:10px}.rlsh-product-body{padding:10px}.rlsh-product-body h3{height:38px;font-size:14px!important;white-space:normal}.rlsh-product-body h3 a{white-space:normal;display:-webkit-box;-webkit-box-orient:vertical;-webkit-line-clamp:2;overflow:hidden}.rlsh-product-price{font-size:20px}.rlsh-buy{font-size:10px}}/* 0.1.13 premium UI pass — align Store Hub with RaffleLB homepage typography and surfaces. */
        .rlsh{--bg:#090d09;--panel:#101610;--panel2:#0b100b;--line:#2b342b;--muted:#b8c1b5;-webkit-font-smoothing:antialiased;text-rendering:optimizeLegibility}
        .rlsh-section{padding:38px clamp(18px,2.2vw,42px);border-top:1px solid rgba(186,255,0,.075)}
        .rlsh-search-wrap{padding:20px clamp(18px,2.2vw,42px) 10px;background:var(--bg)}
        .rlsh-search{max-width:1840px;min-height:60px;border-color:var(--line);border-radius:14px;background:linear-gradient(145deg,#101610,#090d09);box-shadow:0 16px 38px rgba(0,0,0,.22),inset 0 1px 0 rgba(255,255,255,.018)}
        .rlsh-search:focus-within{border-color:#61704d;box-shadow:0 0 0 3px rgba(186,255,0,.045),0 16px 38px rgba(0,0,0,.22)}
        .rlsh-search-input{font-size:14px!important;font-weight:500!important;letter-spacing:0!important}.rlsh-search-input::placeholder{color:#7d8779!important}
        .rlsh-search-submit{height:44px;border-color:#91b500;border-radius:10px;background:#080e0a;color:#f5f7f2;font-size:11px;font-weight:700;letter-spacing:.05em;box-shadow:0 0 24px #baff0012,inset 0 1px 0 #baff0012}
        .rlsh-head{align-items:flex-end;margin:0 auto 22px}.rlsh-head-featured{align-items:flex-end;margin-bottom:22px}.rlsh-head-copy{max-width:780px}
        .rlsh-kicker{display:flex;align-items:center;gap:8px;margin-bottom:10px;color:var(--lime);font-size:11px;font-weight:700;letter-spacing:.14em;line-height:1.2}.rlsh-kicker:before{content:"";width:6px;height:6px;flex:0 0 6px;border-radius:50%;background:var(--lime);box-shadow:0 0 12px rgba(186,255,0,.28)}
        .rlsh-head h2,.rlsh-head-featured h2{font-size:clamp(26px,2.6vw,38px)!important;line-height:var(--rl-line-tight,1.08)!important;font-weight:var(--rl-weight-heavy,800)!important;letter-spacing:var(--rl-tracking-tight,-.025em)!important}
        .rlsh-head-copy p{max-width:740px;margin:10px 0 0!important;color:#b8c1b5!important;font-size:14px!important;line-height:1.6!important;font-weight:400!important}
        .rlsh-head>a{min-height:44px;min-width:190px;padding:11px 20px;border-color:#91b500;border-radius:10px;background:#080e0a;box-shadow:0 0 24px #baff0014,inset 0 1px 0 #baff0014;color:#f5f7f2!important;font-size:11px;font-weight:700;letter-spacing:.05em;transition:transform .18s ease,border-color .18s ease,background .18s ease}.rlsh-head>a:hover{transform:translateY(-1px);background:#0d150d;border-color:var(--lime)}
        .rlsh-category-grid{gap:16px}.rlsh-category-card{min-height:230px;border-color:var(--line);border-radius:14px;background:#101610;box-shadow:0 18px 42px rgba(0,0,0,.16);transition:transform .2s ease,border-color .2s ease,box-shadow .2s ease}.rlsh-category-card:hover{transform:translateY(-3px);border-color:#61704d;box-shadow:0 22px 48px rgba(0,0,0,.22)}.rlsh-category-image:after{inset:30% 0 0;background:linear-gradient(transparent,rgba(2,4,2,.96) 85%)}.rlsh-category-card strong{left:17px;bottom:16px;font-size:15px;line-height:1.25;font-weight:700}.rlsh-category-card>span{right:15px;bottom:13px;width:30px;height:30px;display:flex;align-items:center;justify-content:center;border:1px solid rgba(186,255,0,.28);border-radius:50%;background:rgba(186,255,0,.035);font-size:17px}
        .rlsh-brands,.rlsh-budget{padding-top:34px;padding-bottom:36px}.rlsh-brand-grid{gap:12px}.rlsh-brand-card{height:108px;padding:15px;border-color:var(--line);border-radius:14px;background:linear-gradient(145deg,#101610,#0b100b);box-shadow:inset 0 1px 0 rgba(255,255,255,.018),0 12px 30px rgba(0,0,0,.12);transition:transform .18s ease,border-color .18s ease,background .18s ease}.rlsh-brand-card:hover{transform:translateY(-2px);border-color:#61704d;background:linear-gradient(145deg,#121a12,#0c120c)}.rlsh-brand-card img{max-width:82%;max-height:52px;filter:none}.rlsh-brand-card strong{color:#f5f7f2;font-size:13px;line-height:1.25;font-weight:700}
        .rlsh-budget-grid{gap:14px}.rlsh-budget-grid a{min-height:80px;padding:0 20px;border-color:var(--line);border-radius:14px;background:linear-gradient(145deg,#101610,#0b100b);box-shadow:inset 0 1px 0 rgba(255,255,255,.018),0 12px 30px rgba(0,0,0,.11);transition:transform .18s ease,border-color .18s ease,background .18s ease}.rlsh-budget-grid a:hover{transform:translateY(-2px);border-color:#61704d;background:linear-gradient(145deg,#121a12,#0c120c)}.rlsh-budget-icon{width:32px;height:32px;flex-basis:32px;border-color:rgba(186,255,0,.28);background:rgba(186,255,0,.045);font-size:12px}.rlsh-budget-grid strong{font-size:14px;font-weight:700}.rlsh-budget-grid b{font-size:18px;font-weight:500}
        .rlsh .rl-almost-filled{padding:34px clamp(18px,2.2vw,42px) 38px;border-top:1px solid rgba(186,255,0,.075);background:#090d09}.rlsh .rl-almost-filled-head{margin-bottom:22px}.rlsh .rl-almost-filled-kicker{display:flex;align-items:center;gap:8px;margin-bottom:10px;font-size:11px;font-weight:700;letter-spacing:.14em}.rlsh .rl-almost-filled-kicker:before{content:"";width:6px;height:6px;flex:0 0 6px;border-radius:50%;background:var(--lime);box-shadow:0 0 12px rgba(186,255,0,.28)}.rlsh .rl-almost-filled-head h2{font-size:clamp(26px,2.6vw,38px)!important;line-height:var(--rl-line-tight,1.08)!important;font-weight:var(--rl-weight-heavy,800)!important;letter-spacing:var(--rl-tracking-tight,-.025em)!important}.rlsh .rl-almost-filled-head p{margin-top:10px!important;color:#b8c1b5!important;font-size:14px!important;line-height:1.6!important;font-weight:400!important}.rlsh .rl-almost-filled-view{min-height:44px;padding:11px 20px;border-color:#91b500;border-radius:10px;background:#080e0a;box-shadow:0 0 24px #baff0014,inset 0 1px 0 #baff0014;font-size:11px;font-weight:700;letter-spacing:.05em}.rlsh .rl-almost-filled-card{border-color:#2b342b;border-radius:14px;background:#101610;box-shadow:0 16px 38px rgba(0,0,0,.14)}.rlsh .rl-almost-filled-card:hover{border-color:#61704d;transform:translateY(-2px)}.rlsh .rl-almost-filled-media{background:#000;border-color:#2b342b}.rlsh .rl-almost-filled-body h3{font-weight:700!important}.rlsh .rl-almost-filled-enter{border-radius:8px;font-weight:700}.rlsh .rl-almost-filled-selection{border-radius:8px;font-weight:700}@media(max-width:600px){.rlsh-section{padding:30px 16px}.rlsh-search-wrap{padding:14px 16px 6px}.rlsh-head-featured{margin-bottom:16px}.rlsh-kicker{font-size:9px;margin-bottom:7px}.rlsh-head h2,.rlsh-head-featured h2{font-size:27px!important}.rlsh-head-copy p{font-size:12px!important;line-height:1.5!important;margin-top:7px!important}.rlsh-head>a{min-width:0;min-height:40px;padding:8px 11px;font-size:9px}.rlsh-category-card{border-radius:12px}.rlsh-brand-card{height:88px;border-radius:12px}.rlsh-budget-grid a{min-height:66px;border-radius:12px}.rlsh .rl-almost-filled{padding:28px 12px 32px}.rlsh .rl-almost-filled-kicker{font-size:9px}.rlsh .rl-almost-filled-head h2{font-size:27px!important}.rlsh .rl-almost-filled-head p{font-size:12px!important;line-height:1.5!important}}@media(prefers-reduced-motion:reduce){.rlsh-skeleton span{animation:none}.rlsh img{transition:none!important}}

        /* 0.1.16 — Live Raffles rebuilt as a compact luxury campaign banner (consolidated; replaces every prior Live Raffles layer). */
        .rlsh-raffles{padding-top:30px;padding-bottom:34px}
        .rlsh-raffle-showcase{position:relative;max-width:1840px;margin:auto;border:1px solid rgba(186,255,0,.22);border-radius:18px;overflow:hidden;background:linear-gradient(120deg,#0b100b 0%,#090d09 54%,#101610 100%);box-shadow:0 22px 56px rgba(0,0,0,.3),inset 0 1px 0 rgba(255,255,255,.02)}
        .rlsh-raffle-stage{position:relative;height:472px}
        .rlsh-raffle-slide{position:absolute;inset:0;display:grid;grid-template-columns:minmax(0,48%) minmax(0,52%);opacity:0;visibility:hidden;pointer-events:none;transition:opacity .25s ease,visibility .25s ease}
        .rlsh-raffle-slide.is-active{opacity:1;visibility:visible;pointer-events:auto}

        .rlsh-raffle-feature-copy{position:relative;z-index:3;display:flex;flex-direction:column;justify-content:center;min-width:0;padding:36px 40px;background:linear-gradient(100deg,rgba(3,6,3,.99) 0%,rgba(4,8,4,.96) 66%,rgba(4,8,4,.8) 100%)}
        .rlsh-raffle-feature-badges{display:flex;align-items:center;gap:9px;flex-wrap:wrap;margin-bottom:15px}
        .rlsh-raffle-feature-badge{align-self:flex-start;display:inline-flex;align-items:center;margin:0;padding:6px 12px;border-radius:999px;background:linear-gradient(180deg,#ff3347,#f0152f);box-shadow:0 0 18px rgba(255,40,62,.2);color:#fff;font-size:9px;font-weight:800;letter-spacing:.06em}
        .rlsh-raffle-feature-badge i{display:inline-block;width:5px;height:5px;margin-right:6px;border-radius:50%;background:#fff}
        .rlsh-raffle-feature-status{display:inline-flex;align-items:center;min-height:26px;padding:6px 12px;border:1px solid rgba(186,255,0,.32);border-radius:999px;background:rgba(186,255,0,.03);color:var(--lime);font-size:9px;font-weight:700;letter-spacing:.05em}
        .rlsh-raffle-feature-copy h3{display:-webkit-box;-webkit-box-orient:vertical;-webkit-line-clamp:2;overflow:hidden;max-width:520px;margin:0 0 8px!important;color:#fff!important;font-size:clamp(25px,2.3vw,38px)!important;line-height:1.16!important;font-weight:800!important;letter-spacing:-.02em!important}
        .rlsh-raffle-feature-copy h3 a{color:#fff!important}
        .rlsh-raffle-feature-desc{display:-webkit-box;-webkit-box-orient:vertical;-webkit-line-clamp:1;overflow:hidden;max-width:480px;margin:0 0 16px!important;color:#b8c1b5!important;font-size:13px!important;line-height:1.5!important;font-weight:400!important}

        .rlsh-raffle-feature-pricebox{display:grid;grid-template-columns:auto auto;grid-template-areas:'label label' 'price unit';align-content:center;column-gap:8px;width:fit-content;min-width:230px;max-width:280px;margin:0 0 16px;padding:11px 18px;border:1px solid #2b382b;border-radius:12px;background:linear-gradient(145deg,rgba(16,25,16,.94),rgba(7,12,7,.96));box-shadow:inset 0 1px 0 rgba(255,255,255,.02)}
        .rlsh-raffle-feature-pricebox>span{grid-area:label;margin-bottom:2px;color:#c0c9bd;font-size:10px;font-weight:600;letter-spacing:.04em}
        .rlsh-raffle-feature-pricebox>strong{grid-area:price;color:var(--lime);font-size:29px;line-height:1;font-weight:800;letter-spacing:-.02em}
        .rlsh-raffle-feature-pricebox>small{grid-area:unit;align-self:end;padding-bottom:2px;color:#d3d9d1;font-size:10px;font-weight:500}

        .rlsh-raffle-feature-info{display:flex;flex-wrap:wrap;align-items:center;margin:0 0 18px;color:#dfe4dc;font-size:13px;font-weight:600}
        .rlsh-raffle-feature-info-item{display:inline-flex;align-items:center;gap:6px;padding:3px 0}
        .rlsh-raffle-feature-info-item+.rlsh-raffle-feature-info-item{margin-left:14px;padding-left:14px;border-left:1px solid rgba(255,255,255,.14)}
        .rlsh-raffle-feature-info-icon{display:flex;flex:0 0 auto;align-items:center;justify-content:center;width:14px;height:14px;color:#8b9787}
        .rlsh-raffle-feature-info-icon svg{width:100%;height:100%;display:block}
        .rlsh-raffle-feature-info-item.is-filled .rlsh-raffle-feature-info-icon{color:var(--lime)}
        .rlsh-raffle-feature-info strong{color:#f7faf5;font-weight:800}
        .rlsh-raffle-feature-info-item.is-filled strong{color:var(--lime)}
        .rlsh-raffle-feature-info span{margin-left:4px;color:#8b9787;font-size:10px;font-weight:700;letter-spacing:.05em}

        .rlsh-raffle-feature-progress{display:flex;align-items:center;gap:12px;max-width:460px;margin:0 0 20px}
        .rlsh-raffle-feature-track{flex:1 1 auto;height:9px;min-width:0;border-radius:99px;background:rgba(255,255,255,.06);overflow:hidden}
        .rlsh-raffle-feature-track span{position:relative;display:block;height:100%;border-radius:inherit;background:linear-gradient(90deg,#96e000,#d0ff00);box-shadow:0 0 12px rgba(186,255,0,.4)}
        .rlsh-raffle-feature-progress>strong{flex:0 0 auto;color:#f5f7f2;font-size:13px;font-weight:800}

        .rlsh-raffle-feature-actions{display:flex;align-items:center;gap:14px;margin-top:2px}
        .rlsh-raffle-feature-actions .rlsh-enter,.rlsh-raffle-feature-actions .rlsh-selection{display:inline-flex;align-items:center;justify-content:center;gap:8px;min-width:230px;min-height:46px;padding:0 22px;border-radius:10px;font-size:12px;font-weight:700;letter-spacing:.03em;white-space:nowrap}
        .rlsh-raffle-feature-actions .rlsh-enter{background:var(--lime);color:#071000!important;box-shadow:0 0 20px rgba(186,255,0,.12)}
        .rlsh-raffle-feature-actions .rlsh-selection{min-width:270px;border:1px solid #50672a;background:rgba(3,7,3,.55);color:#f5f7f2!important}
        .rlsh-raffle-feature-actions .rlsh-enter b,.rlsh-raffle-feature-actions .rlsh-selection b{margin-left:0;font-weight:500}

        .rlsh-raffle-feature-visual{position:relative;isolation:isolate;display:flex;align-items:center;justify-content:center;min-width:0;overflow:hidden;background:radial-gradient(120% 100% at 50% 18%,rgba(255,255,255,.03),transparent 55%),radial-gradient(85% 75% at 50% 50%,rgba(30,60,20,.5),transparent 68%),linear-gradient(165deg,#0c130c 0%,#070c07 50%,#030503 100%)}
        .rlsh-raffle-feature-geo{position:absolute;inset:0;z-index:0;pointer-events:none;opacity:.7;background:linear-gradient(115deg,transparent 42%,rgba(186,255,0,.07) 46%,transparent 50%),linear-gradient(65deg,transparent 60%,rgba(186,255,0,.06) 64%,transparent 69%)}
        .rlsh-raffle-feature-visual:after{content:'';position:absolute;inset:0;z-index:1;pointer-events:none;background:radial-gradient(closest-side,transparent 62%,rgba(2,4,2,.62) 100%)}
        .rlsh-raffle-feature-glow{position:absolute;left:50%;top:44%;width:62%;max-width:440px;aspect-ratio:1;transform:translate(-50%,-50%);border-radius:50%;border:1px solid rgba(186,255,0,.22);box-shadow:0 0 70px rgba(186,255,0,.14),0 0 160px rgba(186,255,0,.08);z-index:2;pointer-events:none}
        .rlsh-raffle-feature-platform{position:absolute;left:50%;bottom:7%;width:42%;max-width:280px;height:18px;transform:translateX(-50%);z-index:3;pointer-events:none}
        .rlsh-raffle-feature-platform:before{content:'';position:absolute;inset:0;border-radius:50%;border:1.5px solid rgba(186,255,0,.6);box-shadow:0 0 24px rgba(186,255,0,.22)}
        .rlsh-raffle-feature-platform:after{content:'';position:absolute;left:8%;right:8%;top:35%;bottom:-160%;border-radius:50%;background:radial-gradient(ellipse at center,rgba(186,255,0,.16),transparent 72%);filter:blur(2px)}
        .rlsh-raffle-feature-visual img.rlsh-raffle-feature-img{position:relative;z-index:4;width:auto!important;height:64%!important;max-width:72%!important;object-fit:contain!important;filter:drop-shadow(0 26px 24px rgba(0,0,0,.55))}

        .rlsh-raffle-controls{position:absolute;inset:0;display:block;pointer-events:none;z-index:6}
        .rlsh-raffle-controls>button{position:absolute;top:50%;width:38px;height:38px;padding:0;transform:translateY(-50%);border:1px solid #4f5f49;border-radius:50%;background:rgba(4,7,4,.7);backdrop-filter:blur(6px);color:#fff;font-size:19px;line-height:1;pointer-events:auto;transition:border-color .18s ease,background .18s ease}
        .rlsh-raffle-controls>button:hover{border-color:var(--lime);background:rgba(8,14,8,.9)}
        .rlsh-raffle-controls>[data-rlsh-raffle-prev]{left:16px}
        .rlsh-raffle-controls>[data-rlsh-raffle-next]{right:16px}
        .rlsh-raffle-dots{position:absolute;right:64px;bottom:20px;z-index:7;display:flex;align-items:center;gap:6px;pointer-events:auto}
        .rlsh-raffle-dots button{width:16px;height:3px;padding:0;border:0;border-radius:99px;background:#54604e}
        .rlsh-raffle-dots button.is-active{width:28px;background:var(--lime);box-shadow:0 0 10px rgba(186,255,0,.25)}

        @media(max-width:1400px){
        .rlsh-raffle-stage{height:440px}
        .rlsh-raffle-feature-copy{padding:30px 34px}
        .rlsh-raffle-feature-copy h3{font-size:clamp(22px,2vw,30px)!important}
        .rlsh-raffle-feature-pricebox>strong{font-size:26px}
        }
        @media(max-width:900px){
        .rlsh-raffle-stage{height:720px}
        .rlsh-raffle-slide{grid-template-columns:1fr;grid-template-rows:290px 1fr}
        .rlsh-raffle-feature-visual{grid-row:1}
        .rlsh-raffle-feature-copy{grid-row:2;padding:26px 28px}
        .rlsh-raffle-feature-copy h3{max-width:none;font-size:clamp(24px,3.4vw,30px)!important}
        .rlsh-raffle-feature-desc{max-width:none}
        .rlsh-raffle-controls{height:290px}
        .rlsh-raffle-controls>button{top:145px}
        .rlsh-raffle-dots{top:254px;bottom:auto}
        }
        @media(max-width:600px){
        .rlsh-raffles{padding-top:24px;padding-bottom:28px}
        .rlsh-raffle-showcase{border-radius:14px}
        .rlsh-raffle-stage{height:660px}
        .rlsh-raffle-slide{grid-template-rows:225px 1fr}
        .rlsh-raffle-feature-copy{padding:20px 16px 24px}
        .rlsh-raffle-feature-badge,.rlsh-raffle-feature-status{padding:5px 10px;font-size:8px}
        .rlsh-raffle-feature-copy h3{font-size:22px!important;margin-bottom:6px!important}
        .rlsh-raffle-feature-desc{margin-bottom:12px!important;font-size:12px!important}
        .rlsh-raffle-feature-pricebox{min-width:0;max-width:none;width:100%;padding:10px 16px;margin-bottom:12px}
        .rlsh-raffle-feature-pricebox>strong{font-size:25px}
        .rlsh-raffle-feature-info{font-size:12px;margin-bottom:14px}
        .rlsh-raffle-feature-info-item+.rlsh-raffle-feature-info-item{margin-left:10px;padding-left:10px}
        .rlsh-raffle-feature-progress{max-width:none;margin-bottom:16px}
        .rlsh-raffle-feature-actions{flex-wrap:wrap;gap:9px}
        .rlsh-raffle-feature-actions .rlsh-enter,.rlsh-raffle-feature-actions .rlsh-selection{flex:1 1 100%;min-width:0;min-height:44px}
        .rlsh-raffle-controls{height:225px}
        .rlsh-raffle-controls>button{top:112px;width:34px;height:34px;font-size:17px}
        .rlsh-raffle-controls>[data-rlsh-raffle-prev]{left:10px}
        .rlsh-raffle-controls>[data-rlsh-raffle-next]{right:10px}
        .rlsh-raffle-dots{right:auto;left:50%;top:195px;bottom:auto;transform:translateX(-50%)}
        }
        </style>

        <script id="rafflelb-store-hub-v0113-js">
        (function(){
            var root=document.querySelector('[data-rlsh-root]'); if(!root)return;
            function fitTitle(el){
                if(!el)return;
                el.style.fontSize='';
                if(window.matchMedia('(max-width:600px)').matches)return;
                var size=parseFloat(window.getComputedStyle(el).fontSize)||16, min=11;
                while(el.scrollWidth>el.clientWidth && size>min){size-=.5;el.style.fontSize=size+'px';}
            }
            function fitAll(){root.querySelectorAll('[data-rlsh-fit-title]').forEach(fitTitle);}
            var timer; window.addEventListener('resize',function(){clearTimeout(timer);timer=setTimeout(fitAll,120);});
            fitAll();
            var raffleCarousel=root.querySelector('[data-rlsh-raffle-carousel]');
            if(raffleCarousel){
                var raffleSlides=Array.prototype.slice.call(raffleCarousel.querySelectorAll('[data-rlsh-raffle-slide]'));
                var raffleDots=Array.prototype.slice.call(raffleCarousel.querySelectorAll('[data-rlsh-raffle-dot]'));
                var raffleIndex=0;
                function showRaffle(i){
                    if(!raffleSlides.length)return;
                    raffleIndex=(i+raffleSlides.length)%raffleSlides.length;
                    raffleSlides.forEach(function(slide,n){var active=n===raffleIndex;slide.classList.toggle('is-active',active);slide.setAttribute('aria-hidden',active?'false':'true');});
                    raffleDots.forEach(function(dot,n){dot.classList.toggle('is-active',n===raffleIndex);});
                }
                var prev=raffleCarousel.querySelector('[data-rlsh-raffle-prev]'), next=raffleCarousel.querySelector('[data-rlsh-raffle-next]');
                if(prev)prev.addEventListener('click',function(){showRaffle(raffleIndex-1);});
                if(next)next.addEventListener('click',function(){showRaffle(raffleIndex+1);});
                raffleDots.forEach(function(dot){dot.addEventListener('click',function(){showRaffle(parseInt(dot.getAttribute('data-rlsh-raffle-dot'),10)||0);});});
            }
            var searchForm=root.querySelector('[data-rlsh-search]'), searchInput=root.querySelector('[data-rlsh-search-input]'), searchResults=root.querySelector('[data-rlsh-search-results]');
            if(searchForm&&searchInput&&searchResults){
                var searchTimer=null, searchController=null;
                function esc(v){var d=document.createElement('div');d.textContent=v==null?'':String(v);return d.innerHTML;}
                function hideSearch(){searchResults.hidden=true;searchInput.setAttribute('aria-expanded','false');}
                function showSearch(html){searchResults.innerHTML=html;searchResults.hidden=false;searchInput.setAttribute('aria-expanded','true');}
                function searchNow(){
                    var q=searchInput.value.trim();
                    if(q.length<2){hideSearch();return;}
                    if(searchController)searchController.abort();
                    searchController=('AbortController' in window)?new AbortController():null;
                    showSearch('<div class="rlsh-search-status">Searching…</div>');
                    var fd=new FormData();fd.append('action','rafflelb_store_search');fd.append('q',q);fd.append('mode','both');fd.append('scope','all');
                    fetch(<?php echo wp_json_encode($ajax_url); ?>,{method:'POST',credentials:'same-origin',body:fd,cache:'no-store',signal:searchController?searchController.signal:undefined})
                    .then(function(r){return r.json();}).then(function(resp){
                        if(!resp||!resp.success||!resp.data){showSearch('<div class="rlsh-search-status">Press Enter to search the Store.</div>');return;}
                        var data=resp.data, items=data.items||[];
                        if(!items.length){showSearch('<div class="rlsh-search-status">No instant matches. Press Enter to search all products.</div>');return;}
                        var html='<div class="rlsh-search-list">';
                        items.forEach(function(it){html+='<a class="rlsh-search-item" href="'+esc(it.url)+'"><span class="rlsh-search-thumb">'+(it.image?'<img src="'+esc(it.image)+'" alt="">':'')+'</span><span class="rlsh-search-copy"><span class="rlsh-search-type">'+esc(it.type||'STORE')+'</span><span class="rlsh-search-name">'+esc(it.title||'')+'</span><span class="rlsh-search-cat">'+esc(it.category||'')+'</span></span><span class="rlsh-search-price">'+esc(it.price||'')+'</span></a>';});
                        html+='</div><div class="rlsh-search-footer"><span>'+items.length+' quick matches</span><a href="'+esc(data.view_all||searchForm.action)+'">VIEW ALL RESULTS →</a></div>';
                        showSearch(html);
                    }).catch(function(err){if(err&&err.name==='AbortError')return;showSearch('<div class="rlsh-search-status">Press Enter to search the Store.</div>');});
                }
                searchInput.addEventListener('input',function(){clearTimeout(searchTimer);searchTimer=setTimeout(searchNow,180);});
                searchInput.addEventListener('focus',function(){if(searchInput.value.trim().length>=2)searchNow();});
                document.addEventListener('click',function(e){if(!searchForm.contains(e.target))hideSearch();});
                searchForm.addEventListener('submit',function(e){if(!searchInput.value.trim()){searchInput.focus();e.preventDefault();}});
            }

                        var grid=root.querySelector('[data-rlsh-perfumes]');
            if(grid){
                var data=new FormData();data.append('action','rafflelb_store_hub_perfumes');
                fetch(<?php echo wp_json_encode($ajax_url); ?>,{method:'POST',credentials:'same-origin',body:data,cache:'no-store'})
                .then(function(r){return r.json();}).then(function(resp){
                    if(resp&&resp.success&&resp.data&&resp.data.html){grid.innerHTML=resp.data.html;grid.setAttribute('aria-busy','false');fitAll();}
                    else{grid.innerHTML='<div class="rlsh-empty">No perfumes available right now.</div>';grid.setAttribute('aria-busy','false');}
                }).catch(function(){grid.innerHTML='<div class="rlsh-empty">No perfumes available right now.</div>';grid.setAttribute('aria-busy','false');});
            }
        })();
        </script>
        <?php
        return ob_get_clean();
    }
}

RaffleLB_Store_Hub::init();

// Keep Store Hub Almost Filled fresh after raffle entry allocation/voiding.
foreach ([
    'woocommerce_order_status_processing',
    'woocommerce_order_status_completed',
    'woocommerce_order_status_cancelled',
    'woocommerce_order_status_refunded',
    'woocommerce_order_status_failed',
] as $rafflelb_store_hub_order_hook) {
    add_action($rafflelb_store_hub_order_hook, ['RaffleLB_Store_Hub', 'raffle_order_cache_purge'], 100, 1);
}
unset($rafflelb_store_hub_order_hook);
add_action('added_post_meta', ['RaffleLB_Store_Hub', 'raffle_meta_cache_purge'], 100, 4);
add_action('updated_post_meta', ['RaffleLB_Store_Hub', 'raffle_meta_cache_purge'], 100, 4);
add_action('deleted_post_meta', ['RaffleLB_Store_Hub', 'raffle_meta_cache_purge'], 100, 4);

