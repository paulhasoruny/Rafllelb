<?php
/**
 * Plugin Name: RaffleLB Shop
 * Description: Existing RaffleLB catalog and product presentation with reversible Draw Engine delegation.
 * Version: 0.2.58
 * Author: RaffleLB
 * Requires PHP: 7.4
 */
if (!defined('ABSPATH')) { exit; }
require_once plugin_dir_path(__FILE__) . 'includes/class-rafflelb-store-only-renderer.php';
final class RaffleLB_Shop {
    const VERSION = '0.2.58';
    private static $selection_entry_form_context = false;
    private static $public_banners_rendered = false;
    public static function ready() {
        return class_exists('RaffleLB\\Core\\Contracts')
            && version_compare(\RaffleLB\Core\Contracts::VERSION, '0.1.0', '>=')
            && defined('RaffleLB_Draw_Engine::SHOP_BRIDGE_VERSION')
            && RaffleLB_Draw_Engine::SHOP_BRIDGE_VERSION === '1';
    }

    private static function account_required() {
        if (class_exists('RaffleLB\\Core\\Access')) {
            return \RaffleLB\Core\Access::purchase_requires_account() && !\RaffleLB\Core\Access::can_purchase();
        }
        return !is_user_logged_in();
    }

    private static function account_login_url($return_url = '') {
        if (class_exists('RaffleLB\\Core\\Access')) {
            return \RaffleLB\Core\Access::login_url($return_url);
        }
        return wp_login_url($return_url ?: home_url('/'));
    }
    private static function current_shop_return_url() {
        $fallback = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : home_url('/shop/');

        // WoodMart can redraw archive cards over AJAX. In that case use the
        // browser page referer rather than the admin-ajax request URL.
        if (function_exists('wp_doing_ajax') && wp_doing_ajax()) {
            $referer = wp_get_referer();
            if ($referer) {
                return wp_validate_redirect($referer, $fallback);
            }
        }

        if (!empty($_SERVER['REQUEST_URI'])) {
            $relative = (string) wp_unslash($_SERVER['REQUEST_URI']);
            $candidate = home_url($relative);
            return wp_validate_redirect($candidate, $fallback);
        }

        return $fallback;
    }

    private static function selection_status_url($product) {
        $product = $product instanceof WC_Product ? $product : wc_get_product(absint($product));
        if (!$product) return '';
        if (class_exists('RaffleLB_Selection_Adapter') && method_exists('RaffleLB_Selection_Adapter', 'selection_url')) {
            return (string) RaffleLB_Selection_Adapter::selection_url($product);
        }
        $slug = $product->get_slug();
        return $slug !== '' ? home_url('/selection/' . rawurlencode($slug) . '/') : '';
    }

    public static function shop_footer_widget_repair() {
        if (is_admin()) return;
        if (!function_exists('is_shop') || !is_shop()) return;

        global $wp_registered_sidebars;

        $wanted = ['Footer Column 1', 'Footer Column 2', 'Footer Column 3'];
        $sidebar_ids = [];

        if (is_array($wp_registered_sidebars)) {
            foreach ($wanted as $name) {
                foreach ($wp_registered_sidebars as $id => $sidebar) {
                    if (!empty($sidebar['name']) && trim((string) $sidebar['name']) === $name) {
                        $sidebar_ids[] = $id;
                        break;
                    }
                }
            }
        }

        if (empty($sidebar_ids)) return;

        ob_start();
        echo '<div id="rafflelb-shop-footer-repair" class="rl-shop-footer-repair" hidden>';
        echo '<div class="container">';
        echo '<div class="rl-shop-footer-grid">';

        foreach ($sidebar_ids as $sidebar_id) {
            echo '<div class="rl-shop-footer-col">';
            dynamic_sidebar($sidebar_id);
            echo '</div>';
        }

        echo '</div>';
        echo '</div>';
        echo '</div>';
        $markup = ob_get_clean();

        echo $markup;
        ?>
        <style id="rafflelb-shop-footer-repair-css">
            body.rafflelb-raffle-shop .wd-footer.footer-container.rl-footer-repaired{
                display:block!important;
                visibility:visible!important;
                opacity:1!important;
                background:#070807!important;
                color:#ffffff!important;
                border-top:1px solid rgba(255,255,255,.07)!important;
                padding:48px 0 34px!important;
            }
            body.rafflelb-raffle-shop .wd-footer.footer-container.rl-footer-repaired .container{
                width:min(1220px,calc(100% - 40px))!important;
                max-width:1220px!important;
                margin:0 auto!important;
                padding:0!important;
            }
            body.rafflelb-raffle-shop .rl-shop-footer-grid{
                display:grid!important;
                grid-template-columns:1.35fr 1fr 1fr!important;
                gap:56px!important;
                align-items:start!important;
            }
            body.rafflelb-raffle-shop .rl-shop-footer-col{
                min-width:0!important;
            }
            body.rafflelb-raffle-shop .wd-footer.footer-container.rl-footer-repaired,
            body.rafflelb-raffle-shop .wd-footer.footer-container.rl-footer-repaired p,
            body.rafflelb-raffle-shop .wd-footer.footer-container.rl-footer-repaired li,
            body.rafflelb-raffle-shop .wd-footer.footer-container.rl-footer-repaired span,
            body.rafflelb-raffle-shop .wd-footer.footer-container.rl-footer-repaired a{
                color:rgba(255,255,255,.76)!important;
            }
            body.rafflelb-raffle-shop .wd-footer.footer-container.rl-footer-repaired h1,
            body.rafflelb-raffle-shop .wd-footer.footer-container.rl-footer-repaired h2,
            body.rafflelb-raffle-shop .wd-footer.footer-container.rl-footer-repaired h3,
            body.rafflelb-raffle-shop .wd-footer.footer-container.rl-footer-repaired h4,
            body.rafflelb-raffle-shop .wd-footer.footer-container.rl-footer-repaired .widget-title{
                color:#ffffff!important;
            }
            body.rafflelb-raffle-shop .wd-footer.footer-container.rl-footer-repaired a:hover{
                color:#caff16!important;
            }
            @media(max-width:767px){
                body.rafflelb-raffle-shop .wd-footer.footer-container.rl-footer-repaired{
                    padding:34px 0 24px!important;
                }
                body.rafflelb-raffle-shop .wd-footer.footer-container.rl-footer-repaired .container{
                    width:min(100% - 28px,1220px)!important;
                }
                body.rafflelb-raffle-shop .rl-shop-footer-grid{
                    grid-template-columns:1fr!important;
                    gap:26px!important;
                }
            }
        </style>
        <script>
        (function(){
            var staging = document.getElementById('rafflelb-shop-footer-repair');
            var footer = document.querySelector('footer.wd-footer.footer-container');
            if (!staging || !footer) return;

            var hasRealContent = footer.textContent.trim().length > 0 || footer.children.length > 0;
            if (!hasRealContent) {
                footer.innerHTML = staging.innerHTML;
                footer.classList.add('rl-footer-repaired');
            }
            staging.remove();
        })();
        </script>
        <?php
    }

    public static function raffle_category_legacy_redirect() {
        if (is_admin()) return;

        $path = isset($_SERVER['REQUEST_URI']) ? wp_parse_url(wp_unslash($_SERVER['REQUEST_URI']), PHP_URL_PATH) : '';
        $path = is_string($path) ? untrailingslashit($path) : '';

        if ($path === '/product-category/vouchers-gift-cards') {
            wp_safe_redirect(home_url('/product-category/vouchers-and-gift-cards/'), 301);
            exit;
        }
    }

    public static function raffles_marketplace_shortcode($atts = []) {
        if (!function_exists('wc_get_product')) return '';

        $atts = shortcode_atts(['limit' => 24], $atts, 'rafflelb_raffles_marketplace');
        $limit = max(1, min(60, absint($atts['limit'])));

        $ids = get_posts([
            'post_type'      => 'product',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'orderby'        => 'date',
            'order'          => 'DESC',
            'meta_query'     => [[
                'key'     => \RaffleLB\Core\Contracts::META_ENABLED,
                'value'   => 'yes',
                'compare' => '=',
            ]],
        ]);

        $live = [];
        $categories = [];
        foreach ($ids as $pid) {
            $product = wc_get_product($pid);
            if (!$product || !$product->is_visible()) continue;
            $stats = RaffleLB_Draw_Engine::shop_bridge_stats(RaffleLB_Draw_Engine::shop_bridge_draw_id($product), false);
            if (!$stats || (string)$stats['status'] !== 'live' || absint($stats['available']) < 1) continue;

            $terms = get_the_terms($pid, 'product_cat');
            $slugs = [];
            $category_name = '';
            if ($terms && !is_wp_error($terms)) {
                foreach ($terms as $term) {
                    if (strtolower($term->name) === 'uncategorized') continue;
                    $slugs[] = sanitize_title($term->slug);
                    if ($category_name === '') $category_name = html_entity_decode($term->name, ENT_QUOTES, get_bloginfo('charset'));
                    $categories[$term->slug] = html_entity_decode($term->name, ENT_QUOTES, get_bloginfo('charset'));
                }
            }
            $live[] = [$product, $stats, $slugs, $category_name];
            if (count($live) >= $limit) break;
        }

        natcasesort($categories);
        ob_start(); ?>
        <section id="live-raffles" class="rlrm3140">
          <div class="rlrm3140-inner">
            <div class="rlrm3140-head">
              <div>
                <div class="rlrm3140-kicker"><i></i> RAFFLES AVAILABLE NOW</div>
                <h2>LIVE <span>RAFFLES</span></h2>
                <p>Choose a prize, select your entries and track everything from your RaffleLB account.</p>
              </div>
              <div class="rlrm3140-count"><strong><?php echo esc_html(count($live)); ?></strong><span>LIVE NOW</span></div>
            </div>

            <?php if ($categories): ?>
            <div class="rlrm3140-filters" aria-label="Filter raffles by category">
              <button class="is-active" type="button" data-filter="all">ALL RAFFLES</button>
              <?php foreach ($categories as $slug => $name): ?>
                <button type="button" data-filter="<?php echo esc_attr(sanitize_title($slug)); ?>"><?php echo esc_html($name); ?></button>
              <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <?php if (!$live): ?>
              <div class="rlrm3140-empty"><strong>No live raffles right now.</strong><span>New raffles will appear here automatically as soon as they open.</span></div>
            <?php else: ?>
              <div class="rlrm3140-grid">
              <?php foreach ($live as $row):
                [$product,$stats,$slugs,$category] = $row;
                $pid=$product->get_id(); $title=$product->get_name(); $url=get_permalink($pid);
                $image_id=$product->get_image_id(); $entry=(float)$product->get_price();
                $available=absint($stats['available']); $total=absint($stats['total']); $claimed=absint($stats['claimed']);
                $percent=$total ? min(100, round(($claimed/$total)*100)) : 0;
              ?>
                <article class="rlrm3140-card" data-cats="<?php echo esc_attr(implode(' ', $slugs)); ?>">
                  <a class="rlrm3140-media" href="<?php echo esc_url($url); ?>">
                    <?php if ($category): ?><span class="rlrm3140-cat"><?php echo esc_html($category); ?></span><?php endif; ?>
                    <div class="rlrm3140-glow"></div>
                    <?php if ($image_id) echo wp_get_attachment_image($image_id,'medium_large',false,['class'=>'rlrm3140-img','loading'=>'lazy','alt'=>$title]); else echo '<div class="rlrm3140-noimg">RAFFLE</div>'; ?>
                  </a>
                  <div class="rlrm3140-body">
                    <h3><a href="<?php echo esc_url($url); ?>"><?php echo esc_html($title); ?></a></h3>
                    <div class="rlrm3140-price"><small>ENTRY FROM</small><strong><?php echo wp_kses_post(wc_price($entry)); ?></strong></div>
                    <div class="rlrm3140-progress"><span style="width:<?php echo esc_attr($percent); ?>%"></span></div>
                    <div class="rlrm3140-meta"><span><?php echo esc_html($claimed); ?> / <?php echo esc_html($total); ?> claimed</span><strong><?php echo esc_html($available); ?> left</strong></div>
                    <a class="rlrm3140-enter" href="<?php echo esc_url($url); ?>">ENTER RAFFLE →</a>
                  </div>
                </article>
              <?php endforeach; ?>
              </div>
              <div class="rlrm3140-nomatch">No live raffles in this category right now.</div>
            <?php endif; ?>
          </div>
        </section>
        <style>
        .rlrm3140{--lime:#baff00;position:relative;width:100vw;max-width:100vw;margin-left:calc(50% - 50vw);margin-right:calc(50% - 50vw);padding:64px 28px 72px;background:#070907;color:#fff;box-sizing:border-box;overflow:hidden}.rlrm3140 *{box-sizing:border-box}.rlrm3140 a{text-decoration:none!important}.rlrm3140-inner{max-width:1640px;margin:auto}.rlrm3140-head{display:flex;justify-content:space-between;align-items:flex-end;gap:30px;margin-bottom:30px}.rlrm3140-kicker{display:flex;align-items:center;gap:9px;margin-bottom:10px;color:var(--lime);font-size:12px;font-weight:900;letter-spacing:.13em}.rlrm3140-kicker i{width:8px;height:8px;border-radius:50%;background:var(--lime);box-shadow:0 0 12px rgba(186,255,0,.55)}.rlrm3140 h2{margin:0!important;color:#fff!important;font-size:42px!important;line-height:1!important;font-weight:900!important;letter-spacing:-.035em!important}.rlrm3140 h2 span{color:var(--lime)}.rlrm3140-head p{margin:12px 0 0!important;color:#b8c0b5!important;font-size:16px!important;line-height:1.6!important}.rlrm3140-count{min-width:106px;padding:14px 16px;border:1px solid #293228;border-radius:12px;text-align:center}.rlrm3140-count strong{display:block;color:var(--lime);font-size:28px;line-height:1}.rlrm3140-count span{display:block;margin-top:6px;color:#9da697;font-size:10px;font-weight:900;letter-spacing:.12em}.rlrm3140-filters{display:flex;gap:10px;overflow-x:auto;padding:0 0 22px;scrollbar-width:none}.rlrm3140-filters::-webkit-scrollbar{display:none}.rlrm3140-filters button{flex:0 0 auto;border:1px solid #2b332a;border-radius:999px;background:#0d110d;color:#d0d6ce;padding:12px 18px;font:800 11px/1 inherit;letter-spacing:.06em;cursor:pointer}.rlrm3140-filters button:hover,.rlrm3140-filters button.is-active{border-color:var(--lime);background:var(--lime);color:#050705}.rlrm3140-grid{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:14px}.rlrm3140-card{overflow:hidden;border:1px solid #273026;border-radius:16px;background:#0d110d;transition:.2s ease;display:flex;flex-direction:column;height:100%}.rlrm3140-card:hover{transform:translateY(-3px);border-color:#465242}.rlrm3140-card.is-hidden{display:none}.rlrm3140-media{position:relative;display:flex!important;align-items:center;justify-content:center;height:210px;overflow:hidden;border-bottom:1px solid #222a21;background:#070a07}.rlrm3140-glow{position:absolute;width:74%;height:62%;left:50%;bottom:0;top:auto;transform:translateX(-50%);border-radius:50%;background:radial-gradient(circle,rgba(186,255,0,.07),transparent 70%)}.rlrm3140-img{position:relative!important;z-index:2;display:block!important;width:100%!important;height:100%!important;max-width:none!important;max-height:none!important;object-fit:cover!important;object-position:center!important;padding:0!important;transform:scale(1.16);filter:drop-shadow(0 12px 17px rgba(0,0,0,.35));transition:transform .2s ease}.rlrm3140-card:hover .rlrm3140-img{transform:scale(1.20)}.rlrm3140-cat{position:absolute;z-index:4;left:11px;top:11px;padding:7px 9px;border:1px solid rgba(255,255,255,.14);border-radius:999px;background:rgba(4,6,4,.82);color:#e2e7df;font-size:9px;font-weight:800;text-transform:uppercase}.rlrm3140-noimg{height:100%;display:flex;align-items:center;justify-content:center;color:var(--lime);font-size:18px;font-weight:900}.rlrm3140-body{padding:14px;display:flex;flex-direction:column;flex:1}.rlrm3140-body h3{min-height:34px!important;margin:0 0 12px!important;font-family:Inter,"Segoe UI",Arial,sans-serif!important;font-size:13px!important;line-height:1.28!important;font-weight:700!important;letter-spacing:-.01em!important;text-align:center!important;white-space:normal!important;overflow:visible!important;text-overflow:clip!important}.rlrm3140-body h3 a{display:block!important;color:#fff!important;text-align:center!important;white-space:normal!important;overflow:visible!important;text-overflow:clip!important;word-break:normal!important;overflow-wrap:anywhere!important}.rlrm3140-price{display:flex;align-items:flex-end;justify-content:space-between;gap:10px;margin-bottom:14px}.rlrm3140-price small{color:#9ca597!important;font-size:9px!important;font-weight:900!important;letter-spacing:.11em}.rlrm3140-price strong,.rlrm3140-price strong *,.rlrm3140-price .woocommerce-Price-amount,.rlrm3140-price .woocommerce-Price-currencySymbol{color:var(--lime)!important;font-size:22px!important;font-weight:900!important;line-height:1!important}.rlrm3140-progress{height:6px;overflow:hidden;border-radius:99px;background:#242c24}.rlrm3140-progress span{display:block;height:100%;border-radius:inherit;background:var(--lime)}.rlrm3140-meta{display:flex;justify-content:space-between;gap:8px;margin-top:10px;margin-bottom:14px;color:#b8c0b5;font-size:12px!important;line-height:1.25!important;font-weight:600!important}.rlrm3140-meta strong{color:#fff!important;font-size:12.5px!important;font-weight:800!important}.rlrm3140-enter{display:flex!important;align-items:center;justify-content:center;height:44px;margin-top:auto;padding-top:0;border-radius:9px;background:var(--lime);color:#050705!important;font-size:10px;font-weight:900;letter-spacing:.08em}.rlrm3140-empty,.rlrm3140-nomatch{padding:34px;border:1px solid #273026;border-radius:14px;background:#0d110d;text-align:center;color:#a9b1a6;font-size:14px}.rlrm3140-empty strong{display:block;color:#fff;margin-bottom:6px;font-size:17px}.rlrm3140-nomatch{display:none}.rlrm3140-nomatch.is-visible{display:block}
        @media(max-width:1450px){.rlrm3140-grid{grid-template-columns:repeat(5,minmax(0,1fr))}.rlrm3140-media{height:210px}}
        @media(max-width:1250px){.rlrm3140-grid{grid-template-columns:repeat(4,minmax(0,1fr))}}
        @media(max-width:900px){.rlrm3140-grid{grid-template-columns:repeat(3,minmax(0,1fr))}.rlrm3140-media{height:200px}}
        @media(max-width:767px){.rlrm3140{padding:42px 12px 50px}.rlrm3140-head{align-items:flex-start;margin-bottom:24px}.rlrm3140 h2{font-size:31px!important}.rlrm3140-head p{font-size:13px!important;line-height:1.55!important}.rlrm3140-kicker{font-size:10px}.rlrm3140-count{min-width:76px;padding:10px}.rlrm3140-count strong{font-size:22px}.rlrm3140-count span{font-size:8px}.rlrm3140-filters{gap:7px;padding-bottom:16px}.rlrm3140-filters button{padding:10px 14px;font-size:9px}.rlrm3140-grid{grid-template-columns:repeat(2,minmax(0,1fr));gap:9px}.rlrm3140-media{height:138px;min-height:138px}.rlrm3140-img{object-fit:contain!important;object-position:center!important;padding:0!important;transform:scale(1.55)!important}.rlrm3140-card:hover .rlrm3140-img{transform:scale(1.55)!important}.rlrm3140-cat{font-size:7.5px;padding:5px 7px}.rlrm3140-body{padding:10px 9px 11px;display:flex;flex-direction:column;flex:1}.rlrm3140-body h3{min-height:var(--rl-banner-height,46px)!important;margin-bottom:8px!important;font-size:11.5px!important;line-height:1.32!important;white-space:normal!important;overflow:visible!important;text-overflow:clip!important}.rlrm3140-body h3 a{display:block!important;white-space:normal!important;overflow:visible!important;text-overflow:clip!important;word-break:normal!important;overflow-wrap:anywhere!important}.rlrm3140-price{margin-bottom:9px}.rlrm3140-price small{font-size:8px!important}.rlrm3140-price strong,.rlrm3140-price strong *,.rlrm3140-price .woocommerce-Price-amount,.rlrm3140-price .woocommerce-Price-currencySymbol{font-size:18px!important}.rlrm3140-meta{font-size:10px!important;margin-top:8px;margin-bottom:12px}.rlrm3140-meta strong{font-size:10.5px!important}.rlrm3140-enter{height:38px;margin-top:auto;font-size:8.5px}}
        @media(max-width:420px){.rlrm3140{padding-left:9px;padding-right:9px}.rlrm3140-media{height:125px;min-height:125px}.rlrm3140-img{transform:scale(1.42)!important}.rlrm3140-card:hover .rlrm3140-img{transform:scale(1.42)!important}.rlrm3140-body h3{font-size:10.5px!important;line-height:1.3!important;white-space:normal!important;overflow:visible!important;text-overflow:clip!important}.rlrm3140-meta{font-size:9px!important;margin-bottom:11px}.rlrm3140-meta strong{font-size:9.5px!important}.rlrm3140-filters button{padding:9px 12px;font-size:8.5px}}
        </style>
        <script>
        document.addEventListener('click',function(e){var b=e.target.closest('.rlrm3140-filters button');if(!b)return;var s=b.closest('.rlrm3140');if(!s)return;s.querySelectorAll('.rlrm3140-filters button').forEach(function(x){x.classList.toggle('is-active',x===b)});var f=b.getAttribute('data-filter'),shown=0;s.querySelectorAll('.rlrm3140-card').forEach(function(c){var ok=f==='all'||(' '+c.getAttribute('data-cats')+' ').indexOf(' '+f+' ')!==-1;c.classList.toggle('is-hidden',!ok);if(ok)shown++});var n=s.querySelector('.rlrm3140-nomatch');if(n)n.classList.toggle('is-visible',shown===0)});
        </script>
        <?php return ob_get_clean();
    }

    private static function current_product($candidate = null) {
        if ($candidate instanceof WC_Product) return $candidate;
        $queried_id = function_exists('get_queried_object_id') ? (int) get_queried_object_id() : 0;
        if ($queried_id > 0 && get_post_type($queried_id) === 'product') {
            $resolved = wc_get_product($queried_id);
            if ($resolved instanceof WC_Product) return $resolved;
        }
        global $product;
        return $product instanceof WC_Product ? $product : null;
    }

    public static function is_raffle_product($product = null) {
        $product = self::current_product($product);
        return $product instanceof WC_Product
            && get_post_meta($product->get_id(), \RaffleLB\Core\Contracts::META_ENABLED, true) === 'yes';
    }

    public static function cart_button_text($text) {
        global $product;
        if (self::is_raffle_product($product)) {
            return self::account_required() ? 'LOGIN / REGISTER TO ENTER' : 'ENTER RAFFLE';
        }
        return $text;
    }

    public static function raffle_product_body_class($classes) {
        if (function_exists('is_product') && is_product()) {
            $product = self::current_product();
            if (!$product instanceof WC_Product) return $classes;
            /* Shared product shell. Both modes render the same outer layout,
               so both carry the mode-neutral shell class. The mode class below
               scopes purchase components only. */
            $classes[] = 'rafflelb-product-page';
            if (self::is_raffle_product($product)) {
                $classes[] = 'rafflelb-raffle-product';
            } else {
                /* Store Only remains outside the raffle contract. */
                $classes[] = 'rafflelb-store-product';
            }
        }
        return $classes;
    }

    public static function raffle_product_price_html($price_html, $product) {
        if (!$product instanceof WC_Product) return $price_html;

        $is_single = function_exists('is_product') && is_product();
        $is_shop   = function_exists('is_shop') && is_shop();
        $is_cat    = function_exists('is_product_category') && is_product_category();
        if (!$is_single && !$is_shop && !$is_cat) {
            return $price_html;
        }

        $product_mode = self::shop_product_mode($product);
        $is_raffle = $product_mode !== 'retail';
        $retail = self::is_raffle_product($product) && in_array($product_mode, ['both', 'retail'], true) ? RaffleLB_Draw_Engine::shop_bridge_buy_now_price($product) : 0;
        $entry  = (float) wc_get_price_to_display($product);

        /* On the single raffle product page the two purchase routes are already
           presented in dedicated panels. */
        if ($is_single) {
            return $is_raffle ? '' : $price_html;
        }

        $mode = self::shop_view_mode();

        if ($mode === 'retail') {
            if ($retail > 0) {
                return '<div class="rl-shop-prices is-retail-only"><div class="rl-shop-price-main"><small>RETAIL PRICE</small><strong>' . wp_kses_post(wc_price($retail)) . '</strong></div></div>';
            }
            /* Genuine Store Only products share the exact Store-mode card
             * presentation, while retaining their native WooCommerce price. */
            return '<div class="rl-shop-prices is-retail-only"><div class="rl-shop-price-main"><small>RETAIL PRICE</small><strong>' . wp_kses_post(wc_price($product->get_price())) . '</strong></div></div>';
        }

        if ($mode === 'raffle') {
            if (!$is_raffle) return '';
            return '<div class="rl-shop-prices is-raffle-only"><div class="rl-shop-price-raffle"><small>RAFFLE ENTRY</small><span class="rl-shop-entry-value"><strong>' . wp_kses_post(wc_price($entry)) . '</strong><em>/ entry</em></span></div></div>';
        }

        if (!$is_raffle) {
            /* Genuine Store Only card in the default ALL PRODUCTS view: show
               its real WooCommerce price in the same solo price-box shape a
               Raffle Only card uses below, instead of the raw theme markup,
               so the price row lines up with every other card. */
            $retail_display = $retail > 0 ? $retail : (float) $product->get_price();
            return '<div class="rl-shop-prices is-retail-only"><div class="rl-shop-price-main"><small>RETAIL PRICE</small><strong>' . wp_kses_post(wc_price($retail_display)) . '</strong></div></div>';
        }
        if ($retail <= 0) {
            /* Genuine Raffle Only card in the default ALL PRODUCTS view: use
               the same solo raffle-price markup as rl_view=raffle, not the
               generic dual-price container the CSS treats as the Store +
               Raffle grid. $entry is the authoritative WooCommerce price. */
            return '<div class="rl-shop-prices is-raffle-only"><div class="rl-shop-price-raffle"><small>RAFFLE ENTRY</small><span class="rl-shop-entry-value"><strong>' . wp_kses_post(wc_price($entry)) . '</strong><em>/ entry</em></span></div></div>';
        }

        return '<div class="rl-shop-prices is-dual-price">'
            . '<div class="rl-shop-price-main"><small>RETAIL PRICE</small><strong>' . wp_kses_post(wc_price($retail)) . '</strong></div>'
            . '<div class="rl-shop-price-raffle"><small>RAFFLE ENTRY</small><span class="rl-shop-entry-value"><strong>' . wp_kses_post(wc_price($entry)) . '</strong><em>/ entry</em></span></div>'
            . '</div>';
    }

    public static function raffle_product_availability_text($text, $product) {
        if (self::$selection_entry_form_context && self::is_raffle_product($product)) {
            return '';
        }
        if (function_exists('is_product') && is_product() && self::is_raffle_product($product)) {
            return '';
        }
        return $text;
    }

    public static function is_selection_entry_form_context() {
        return self::$selection_entry_form_context;
    }

    public static function raffle_quantity_label() {
        if (self::$selection_entry_form_context) return;
        $product = self::current_product();
        if (!self::is_raffle_product($product)) return;
        echo '<div class="rl-entry-label">SELECT NUMBER OF ENTRIES</div>';
    }

    /**
     * Shared public entry component for non-product raffle surfaces.
     *
     * The form itself is WooCommerce's native single-product form. Draw Engine's
     * existing hooks therefore remain the sole owners of quantity limits,
     * capacity validation, cart metadata, reservations, checkout and paid-entry
     * generation. This method only supplies Shop-owned presentation and account
     * gating around that native form.
     */
    public static function selection_entry_form($product, $return_url = '') {
        $product = self::current_product($product);
        if (!$product instanceof WC_Product || !self::is_raffle_product($product)) return '';
        if (!function_exists('woocommerce_template_single_add_to_cart')) return '';

        $draw_id = RaffleLB_Draw_Engine::shop_bridge_draw_id($product);
        $stats = $draw_id ? RaffleLB_Draw_Engine::shop_bridge_stats($draw_id, true) : false;
        if (!$stats || (string) $stats['status'] !== 'live' || absint($stats['available']) < 1) return '';

        $return_url = $return_url ?: get_permalink($product->get_id());
        $entry_price = (float) wc_get_price_to_display($product);

        ob_start();
        echo '<section class="rlse-entry-panel" data-rlse-entry-panel aria-labelledby="rlse-entry-title">';
        echo '<div class="rlse-entry-panel-copy"><span class="rlse-entry-open-state"><i aria-hidden="true"></i>RAFFLE OPEN</span><h2 id="rlse-entry-title">Enter This Raffle</h2></div>';
        echo '<div class="rlse-entry-panel-action">';
        echo '<div class="rlse-entry-panel-price"><small>ENTRY PRICE</small><strong>' . wp_kses_post(wc_price($entry_price)) . '</strong><span>per entry</span></div>';

        $account_required = self::account_required();
        if ($account_required) {
            echo '<a class="button alt rlse-entry-login" href="' . esc_url(self::account_login_url($return_url)) . '">LOGIN / REGISTER TO ENTER</a>';
        }

        if (!$account_required) {
            $had_product = array_key_exists('product', $GLOBALS);
            $previous_product = $had_product ? $GLOBALS['product'] : null;
            $previous_context = self::$selection_entry_form_context;
            $GLOBALS['product'] = $product;
            self::$selection_entry_form_context = true;
            echo '<div class="rlse-native-entry-form">';
            try {
                woocommerce_template_single_add_to_cart();
            } finally {
                self::$selection_entry_form_context = $previous_context;
                if ($had_product) {
                    $GLOBALS['product'] = $previous_product;
                } else {
                    unset($GLOBALS['product']);
                }
            }
            echo '</div>';
        }

        echo '</div></section>';
        return ob_get_clean();
    }

    /*
     * The product structure is mounted from wp_head before body parsing begins.
     * This legacy bridge remains callable by Draw Engine, but must not schedule
     * a second footer/DOMContentLoaded reconstruction.
     */
    public static function product_details_relocator() {
        return;
    }

    /*
     * True once wp_head printed the render-blocking layout stylesheet, so the
     * later wp_enqueue_scripts copy can be dropped instead of duplicated.
     */
    private static $early_style_printed = false;

    public static function early_style_printed() {
        return self::$early_style_printed;
    }

    /* Store Only uses a Shop-owned WooCommerce template, not browser-side DOM relocation. */
    public static function store_only_template_part($template, $slug, $name) {
        if (!function_exists('is_product') || !is_product() || $slug !== 'content' || $name !== 'single-product') return $template;
        $product = self::current_product();
        if (!$product instanceof WC_Product || self::is_raffle_product($product)) return $template;
        $store_template = plugin_dir_path(__FILE__) . 'templates/content-single-product-store.php';
        return file_exists($store_template) ? $store_template : $template;
    }

    public static function store_only_product_head() {
        if (!function_exists('is_product') || !is_product()) return;
        $product = self::current_product();
        if (!$product instanceof WC_Product || self::is_raffle_product($product)) return;
        /* Store Only consumes the shared RaffleLB product stylesheet, which is
         * now the only single-product presentation source for both modes. The
         * former parallel store-product.css copy of the shell is removed. */
        $css_url = plugins_url('assets/single-product.css', __FILE__) . '?ver=' . rawurlencode(self::VERSION);
        self::$early_style_printed = true;
        echo '<link rel="stylesheet" id="rafflelb-store-only-shared-product-css" href="' . esc_url($css_url) . '" media="all" data-no-optimize="1" data-noptimize="1" data-no-defer="1" data-no-minify="1" data-wpr-nooptimize="1">';
    }

    /*
     * Shared RaffleLB product shell.
     *
     * This is the single server-side source of the outer product layout:
     * summary container, breadcrumb row, .rl-product-layout grid, both
     * columns, gallery, Product Information, title and meta row. It emits the
     * exact wrapper markup the raffle bootstrap composes on raffle pages, so
     * both modes resolve to the same structure and the same shared CSS. The
     * caller supplies only the purchase area for its mode.
     */
    public static function render_product_shell($product, callable $purchase_area) {
        if (!$product instanceof WC_Product) return;

        $classes = function_exists('wc_get_product_class') ? wc_get_product_class('', $product) : ['product'];

        echo '<div id="product-' . esc_attr($product->get_id()) . '" class="' . esc_attr(implode(' ', $classes)) . '">';
        /* The -wrap / -inner pair mirrors the theme chain the raffle bootstrap
           mounts into, so both modes present the same wrapper structure. */
        echo '<div class="product-image-summary-wrap">';
        echo '<div class="product-image-summary rl-product-layout-ready">';
        echo '<div class="product-image-summary-inner">';

        echo '<div class="rl-product-breadcrumbs">';
        if (function_exists('woocommerce_breadcrumb')) woocommerce_breadcrumb();
        echo '</div>';

        echo '<div class="rl-product-layout">';

        echo '<div class="rl-product-left">';
        woocommerce_show_product_images();
        self::product_details_panel();
        echo '</div>';

        echo '<div class="rl-product-right">';
        echo '<div class="summary entry-summary">';
        echo '<h1 class="product_title entry-title">' . esc_html($product->get_name()) . '</h1>';
        echo '<div class="rl-product-meta-row">';
        echo '<span class="rl-stock-badge' . ($product->is_in_stock() ? ' is-in-stock' : ' is-out-of-stock') . '"><i aria-hidden="true"></i>' . esc_html($product->is_in_stock() ? 'IN STOCK' : 'OUT OF STOCK') . '</span>';
        echo '<span class="rl-product-classification">' . wp_kses_post(wc_get_product_category_list($product->get_id(), ', ')) . '</span>';
        echo '</div>';
        echo '</div>';

        $purchase_area($product);

        echo '</div>';

        echo '</div></div></div></div></div>';
    }

    public static function raffle_price_visibility_css() {
        if (!function_exists('is_product') || !is_product() || !self::is_raffle_product()) return;
        echo '<style id="rafflelb-raffle-price-visibility">body.rafflelb-raffle-product .rl-raffle-secondary-intro p b,body.rafflelb-raffle-product .rl-raffle-secondary-intro p b *,body.rafflelb-raffle-product .rl-raffle-secondary-intro p b .woocommerce-Price-amount,body.rafflelb-raffle-product .rl-raffle-secondary-intro p b .woocommerce-Price-currencySymbol{color:#baff00!important;opacity:1!important;visibility:visible!important;text-shadow:none!important}</style>';
    }

    public static function store_only_add_to_cart_text($text) {
        return function_exists('is_product') && is_product() && !self::is_raffle_product() ? 'BUY NOW →' : $text;
    }

    public static function product_layout_bootstrap() {
        if (!function_exists('is_product') || !is_product()) return;
        $product = self::current_product();
        if (!$product instanceof WC_Product || !self::is_raffle_product($product)) return;

        /*
         * The layout stylesheet is printed here, ahead of every theme and
         * optimiser stylesheet, so the raffle presentation is already applied
         * at first paint. Relying on the wp_enqueue_scripts copy alone let
         * WP Rocket move it behind an async loader, which is what produced the
         * WoodMart flash: the bootstrap re-parented the nodes while the plugin
         * CSS had not arrived, so the theme's own product styling painted for
         * a frame.
         */
        $css_url = plugins_url('assets/single-product.css', __FILE__) . '?ver=' . rawurlencode(self::VERSION);
        self::$early_style_printed = true;
        ?>
        <link rel="stylesheet" id="rafflelb-single-product-early-css" href="<?php echo esc_url($css_url); ?>" media="all" data-no-optimize="1" data-noptimize="1" data-no-defer="1" data-no-minify="1" data-wpr-nooptimize="1">
        <style id="rafflelb-product-first-paint" data-no-optimize="1" data-noptimize="1" data-no-minify="1" data-wpr-nooptimize="1">
        html,body.rafflelb-raffle-product,html,body.rafflelb-store-product{background:#080b09!important}
        body.single-product.rafflelb-raffle-product .main-page-wrapper,body.single-product.rafflelb-store-product .main-page-wrapper,
        body.single-product.rafflelb-raffle-product .site-content,body.single-product.rafflelb-store-product .site-content,
        body.single-product.rafflelb-raffle-product .product-image-summary-wrap,body.single-product.rafflelb-store-product .product-image-summary-wrap,
        body.single-product.rafflelb-raffle-product .product-image-summary,body.single-product.rafflelb-store-product .product-image-summary{background:#080b09!important}
        /*
         * Nothing inside the product region paints until the layout is mounted
         * AND the layout stylesheet has actually applied. The guard covers the
         * whole region rather than the summary alone, because the tabs wrapper
         * and the raffle details section paint in WoodMart's styling too.
         *
         * The reveal keyframe is a pure-CSS failsafe: even if the bootstrap
         * script never runs, the region becomes visible on its own. It is
         * therefore deliberately declared without !important, so the animation
         * is able to win the cascade.
         */
        @keyframes rafflelb-product-reveal{to{visibility:visible;opacity:1}}
        html.rl-product-guard body.single-product.rafflelb-raffle-product .product-image-summary-wrap,
        html.rl-product-guard body.single-product.rafflelb-raffle-product .product-image-summary,
        html.rl-product-guard body.single-product.rafflelb-raffle-product .product-tabs-wrapper,
        html.rl-product-guard body.single-product.rafflelb-raffle-product .rl-raffle-details-section,
        html.rl-product-guard body.single-product.rafflelb-raffle-product div.product.type-product{
            visibility:hidden;opacity:0;
            animation:rafflelb-product-reveal 0s linear 2.5s forwards
        }
        body.single-product.rafflelb-raffle-product .product-image-summary,
        body.single-product.rafflelb-raffle-product .rl-raffle-details-section{transition:opacity .16s ease-out}
        </style>
        <script data-no-optimize="1" data-noptimize="1" data-no-defer="1" data-no-delay="1" data-no-minify="1" data-wpr-nooptimize="1">
        (function(){
            window.RaffleLBProductBootstrap = true;

            var doc = document.documentElement;
            var REVEAL_DEADLINE = 2400;
            var revealed = false;
            var mounted = false;
            var observer = null;
            var poll = null;
            var probe = null;
            var media = window.matchMedia ? window.matchMedia('(max-width: 900px)') : null;

            /* Hidden first, released by reveal() or by the CSS failsafe. */
            doc.className += ' rl-product-guard';
            var deadline = window.setTimeout(reveal, REVEAL_DEADLINE);

            function reveal() {
                if (revealed) return;
                revealed = true;
                window.clearTimeout(deadline);
                if (poll) { window.clearInterval(poll); poll = null; }
                if (observer) { observer.disconnect(); observer = null; }
                doc.className = doc.className.replace(/(^|\s)rl-product-guard(?=\s|$)/g, ' ');
                if (document.body) document.body.classList.add('rl-product-mounted');
            }

            /*
             * Reading the rules back is the only optimiser-agnostic way to know
             * they are live: WP Rocket may inline, defer or rewrite the link,
             * so the <link> element itself proves nothing.
             *
             * The raffle card is checked first because it is real, rendered
             * markup that Remove Unused CSS always keeps. The injected probe is
             * the backup for the case where that card is styled by something
             * else on the page.
             */
            function stylesheetApplied() {
                if (!document.body) return false;
                var card = document.querySelector('.rafflelb-raffle-product .rl-raffle-option-card');
                if (card && window.getComputedStyle(card).borderTopLeftRadius === '16px') return true;
                if (!probe) {
                    probe = document.createElement('div');
                    probe.className = 'rl-css-probe';
                    document.body.appendChild(probe);
                }
                return window.getComputedStyle(probe).width === '7px';
            }

            function node(root, selector) { return root && root.querySelector(selector); }
            function move(parent, child) {
                if (parent && child && child.parentElement !== parent) parent.appendChild(child);
            }

            function mount() {
                var root = document.querySelector('.rafflelb-raffle-product');
                var host = node(root, '.product-image-summary-inner') || node(root, '.product-image-summary');
                var panel = node(root, '.rl-product-details-panel');
                var summary = node(root, '.summary');
                var gallery = node(root, '.woocommerce-product-gallery, .product-images, .wd-product-gallery');
                var galleryColumn = gallery && (gallery.closest('.product-images, .wd-product-gallery, .product-gallery') || gallery.parentElement);
                var raffle = node(root, '.rl-raffle-option-card');
                /*
                 * A raffle entry card exists only while the draw can still be
                 * entered. Ready-to-draw and winner-selected products
                 * intentionally omit it, but they still need the exact same
                 * two-column Shop composition. Treat the card as an optional
                 * action panel; gallery, summary and Product Details are the
                 * structural requirements.
                 */
                if (!root || !host || !panel || !summary || !galleryColumn) return false;

                var layout = node(root, '.rl-product-layout');
                var breadcrumbs = node(host, '.woocommerce-breadcrumb, .breadcrumbs, .woodmart-breadcrumbs');
                var breadcrumbRow = node(host, '.rl-product-breadcrumbs');
                var left = node(root, '.rl-product-left');
                var right = node(root, '.rl-product-right');

                if (!layout) {
                    layout = document.createElement('div');
                    layout.className = 'rl-product-layout';
                    breadcrumbRow = document.createElement('div');
                    breadcrumbRow.className = 'rl-product-breadcrumbs';
                    host.insertBefore(breadcrumbRow, host.firstChild);
                    host.insertBefore(layout, breadcrumbRow.nextSibling);
                }
                if (breadcrumbs && breadcrumbRow && breadcrumbs.parentElement !== breadcrumbRow) breadcrumbRow.appendChild(breadcrumbs);

                /*
                 * Completed/ready-to-draw states intentionally have no live
                 * raffle entry card. Give those states a narrower gallery
                 * column so the result/title side becomes the visual focus.
                 */
                layout.classList.toggle('rl-product-layout-no-entry', !raffle);

                var mobile = media && media.matches;
                if (mobile) {
                    /* Real mobile DOM order: gallery, summary, raffle, details. */
                    move(layout, galleryColumn);
                    move(layout, summary);
                    if (raffle) move(layout, raffle);
                    move(layout, panel);
                    if (left && !left.childNodes.length) left.remove();
                    if (right && !right.childNodes.length) right.remove();
                    layout.dataset.rlMode = 'mobile';
                } else {
                    if (!left) {
                        left = document.createElement('div');
                        left.className = 'rl-product-left';
                        layout.appendChild(left);
                    }
                    if (!right) {
                        right = document.createElement('div');
                        right.className = 'rl-product-right';
                        layout.appendChild(right);
                    }
                    move(left, galleryColumn);
                    move(left, panel);
                    move(right, summary);
                    if (raffle) move(right, raffle);
                    layout.dataset.rlMode = 'desktop';
                }

                panel.classList.add('rl-product-details-mounted');
                var button = node(raffle, '.single_add_to_cart_button');
                if (button && !node(button, '.rl-button-icon')) {
                    button.insertAdjacentHTML('afterbegin', '<svg class="rl-button-icon" aria-hidden="true" viewBox="0 0 24 24"><path d="M4 7h16v10H4zM8 7V5h8v2m-7 5h.01M15 12h.01"/></svg>');
                }
                var productArea = host.closest('.product-image-summary');
                if (productArea) productArea.classList.add('rl-product-layout-ready');
                mounted = true;
                return true;
            }

            /*
             * The page is only released once the nodes are in their final
             * position and the stylesheet that positions them is live. Either
             * half on its own is exactly the frame the user was seeing.
             */
            function attempt() {
                if (revealed) return;
                try {
                    if (mount() && stylesheetApplied()) reveal();
                } catch (e) {
                    reveal();
                }
            }

            function onViewportChange() {
                if (mounted) { try { mount(); } catch (e) {} }
            }
            if (media) {
                if (media.addEventListener) media.addEventListener('change', onViewportChange);
                else if (media.addListener) media.addListener(onViewportChange);
            }

            observer = new MutationObserver(attempt);
            observer.observe(document.documentElement, { childList:true, subtree:true });
            /* Stylesheet arrival is not a DOM mutation, so it needs polling. */
            poll = window.setInterval(attempt, 50);
            document.addEventListener('DOMContentLoaded', attempt);
            window.addEventListener('load', reveal);
            attempt();
        })();
        </script>
        <?php
    }

    public static function product_details_panel() {
        global $product;

        if (!$product instanceof WC_Product) return;

        $brand = '';
        $brand_candidates = ['pa_brands', 'pa_brand', 'brand'];

        foreach ($brand_candidates as $taxonomy) {
            if (taxonomy_exists($taxonomy)) {
                $terms = wc_get_product_terms($product->get_id(), $taxonomy, ['fields' => 'names']);
                if (!empty($terms) && !is_wp_error($terms)) {
                    $brand = implode(', ', $terms);
                    break;
                }
            }
        }

        $type = '';
        $type_candidates = ['pa_product-type', 'pa_product_type', 'pa_type', 'product-type'];

        foreach ($type_candidates as $taxonomy) {
            if (taxonomy_exists($taxonomy)) {
                $terms = wc_get_product_terms($product->get_id(), $taxonomy, ['fields' => 'names']);
                if (!empty($terms) && !is_wp_error($terms)) {
                    $type = implode(', ', $terms);
                    break;
                }
            }
        }

        $size = '';
        $size_candidates = ['pa_size', 'pa_volume', 'pa_capacity'];

        foreach ($size_candidates as $taxonomy) {
            if (taxonomy_exists($taxonomy)) {
                $terms = wc_get_product_terms($product->get_id(), $taxonomy, ['fields' => 'names']);
                if (!empty($terms) && !is_wp_error($terms)) {
                    $size = implode(', ', $terms);
                    break;
                }
            }
        }

        $description = trim((string) $product->get_description());
        $specs = [];
        $seen_labels = [];
        foreach (['Brand' => $brand, 'Type' => $type, 'Size' => $size] as $label => $value) {
            if ($value !== '') { $specs[] = [$label, $value]; $seen_labels[strtolower($label)] = true; }
        }

        /* Product descriptions in the existing catalog may contain genuine
           line-based specifications. Convert only recognised generic labels to
           rows; any other text stays visible below so no source data is lost. */
        $line_source = preg_replace('/<\/?p[^>]*>|<br\s*\/?>/i', "\n", $description);
        $lines = preg_split('/\r\n|\r|\n/', wp_strip_all_tags((string) $line_source));
        $labels = ['Operating System', 'Connectivity', 'Processor', 'Storage', 'Camera', 'Battery', 'Warranty', 'Color', 'Ram', 'Size', 'Brand', 'Type', 'Material', 'Dimensions', 'Weight'];
        $leftover = [];
        foreach ($lines as $line) {
            $line = trim(preg_replace('/\s+/', ' ', (string) $line));
            if ($line === '') continue;
            $matched = false;
            foreach ($labels as $label) {
                if (preg_match('/^' . preg_quote($label, '/') . '\s*:?\s+(.+)$/i', $line, $match)) {
                    $key = strtolower($label);
                    if (!isset($seen_labels[$key])) { $specs[] = [$label, trim($match[1])]; $seen_labels[$key] = true; }
                    $matched = true;
                    break;
                }
            }
            if (!$matched) $leftover[] = $line;
        }

        echo '<section class="rl-product-details-panel">';
            echo '<div class="rl-product-details-head">';
                echo '<span>PRODUCT INFORMATION</span>';
                echo '<strong>Product details</strong>';
            echo '</div>';

            echo '<div class="rl-product-retail-notes">';
                echo '<span>✓ Authentic product</span>';
                if (self::is_raffle_product($product) && RaffleLB_Draw_Engine::shop_bridge_buy_now_enabled($product) && !RaffleLB_Draw_Engine::shop_bridge_is_draw_closed($product)) {
                    echo '<span>✓ Standard direct-purchase option available</span>';
                }
            echo '</div>';

            if ($specs) {
                echo '<div class="rl-product-details-body">';
                echo '<div class="rl-product-specs">';
                foreach ($specs as $spec) {
                    echo '<div class="rl-product-spec-row">';
                        echo '<span>' . esc_html($spec[0]) . '</span>';
                        echo '<strong>' . esc_html($spec[1]) . '</strong>';
                    echo '</div>';
                }
                echo '</div>';
                $features = [];
                $feature_map = [
                    'processor' => ['chip', 'Processor'],
                    'camera' => ['camera', 'Camera'],
                    'connectivity' => ['link', 'Connectivity'],
                    'battery' => ['battery', 'Battery'],
                    'size' => ['device', 'Size'],
                    'warranty' => ['shield', 'Warranty'],
                ];
                foreach ($specs as $spec) {
                    $key = strtolower($spec[0]);
                    if (isset($feature_map[$key])) $features[] = [$feature_map[$key][0], $feature_map[$key][1], $spec[1]];
                    if (count($features) === 3) break;
                }
                if ($features) {
                    echo '<div class="rl-product-features">';
                    foreach (array_slice($features, 0, 3) as $feature) {
                        $icon_paths = [
                            'chip' => 'M9 3h6m-6 18h6M3 9v6m18-6v6M7 7h10v10H7zM10 10h4v4h-4z',
                            'camera' => 'M4 7h4l1.5-2h5L16 7h4v12H4V7Zm8 3a3 3 0 1 0 0 6 3 3 0 0 0 0-6Z',
                            'link' => 'M9 15 15 9m-7 9-2 2a3 3 0 0 1-4-4l3-3a3 3 0 0 1 4 0m7-2 2-2a3 3 0 0 0-4-4l-3 3a3 3 0 0 0 0 4',
                            'battery' => 'M4 7h15v10H4V7Zm15 3h2v4h-2',
                            'shield' => 'M12 3 4 6v5c0 5.2 3.4 8.5 8 10 4.6-1.5 8-4.8 8-10V6l-8-3Zm-3 9 2 2 4-4',
                            'device' => 'M6 3h12v18H6zM10 18h4',
                        ];
                        $icon_path = $icon_paths[$feature[0]] ?? $icon_paths['device'];
                        echo '<div class="rl-product-feature"><span class="rl-feature-icon rl-feature-' . esc_attr($feature[0]) . '" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="' . esc_attr($icon_path) . '"/></svg></span><span><b>' . esc_html($feature[1]) . '</b><small>' . esc_html($feature[2]) . '</small></span></div>';
                    }
                    echo '</div>';
                }
                echo '</div>';
            }

            if ($leftover) {
                echo '<div class="rl-product-description">';
                    echo wp_kses_post(wpautop(implode("\n", $leftover)));
                echo '</div>';
            }
        echo '</section>';
    }

    public static function raffle_option_open() {
        global $product;
        if (!self::is_raffle_product($product)) return;
        if (RaffleLB_Draw_Engine::shop_bridge_is_draw_closed($product)) return;

        echo '<section class="rl-raffle-option-card" aria-label="Enter the raffle">';
        echo '<div class="rl-raffle-option-head">';
        echo '<span class="rl-raffle-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M8 3h8v4a4 4 0 0 1-8 0V3Zm0 2H4v2a5 5 0 0 0 5 5m7-7h4v2a5 5 0 0 1-5 5m-3 0v5m-3 3h6M8 20h8"/></svg></span>';
        echo '<div class="rl-raffle-heading"><strong>Try your chance to win</strong><p>Enter the raffle for a chance to win this product.</p></div>';
        echo '<span class="rl-raffle-live"><i aria-hidden="true"></i>LIVE NOW</span>';
        echo '</div>';
    }

    public static function raffle_option_close() {
        global $product;
        if (!self::is_raffle_product($product)) return;
        if (RaffleLB_Draw_Engine::shop_bridge_is_draw_closed($product)) return;

        echo '</section>';
    }

    public static function raffle_secondary_intro() {
        global $product;
        if (!self::is_raffle_product($product)) return;
        if (RaffleLB_Draw_Engine::shop_bridge_is_draw_closed($product)) return;

        echo '<div class="rl-raffle-secondary-intro">';
            if ((float) $product->get_price() > 0) {
                echo '<p>Get a chance to win this product for <b>' . wp_kses_post(wc_price((float) $product->get_price())) . ' per entry</b>.</p>';
            }
        echo '</div>';
    }

    public static function buy_now_panel() {
        global $product;
        if (!self::is_raffle_product($product)) return;
        if (RaffleLB_Draw_Engine::shop_bridge_is_draw_closed($product)) return;
        $enabled = get_post_meta($product->get_id(), '_rafflelb_buy_now_enabled', true) === 'yes';
        $price = (float) get_post_meta($product->get_id(), '_rafflelb_buy_now_price', true);
        if (!$enabled) return;
        if ($price <= 0) return;

        $action = get_permalink($product->get_id());

        echo '<section class="rl-buy-now-panel" aria-label="Buy this product directly">';
            echo '<span class="rl-buy-now-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M3 3h2l2.1 10.2a2 2 0 0 0 2 1.6h7.7a2 2 0 0 0 2-1.6L19 8H7M9 21a1 1 0 1 0 0-2 1 1 0 0 0 0 2Zm8 0a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z"/></svg></span>';
            echo '<div class="rl-buy-now-copy">';
                echo '<strong>Buy Direct</strong>';
                echo '<p>Purchase this item directly at the listed retail price. This option is a standard product purchase and does not include a raffle entry.</p>';
            echo '</div>';

            echo '<div class="rl-buy-now-action">';
                echo '<div class="rl-buy-now-price-wrap">';
                    echo '<span class="rl-buy-now-price-label">RETAIL PRICE</span>';
                    echo '<div class="rl-buy-now-price">' . wp_kses_post(wc_price($price)) . '</div>';
                echo '</div>';
                if (self::account_required()) {
                    $login_url = self::account_login_url($action);
                    echo '<div class="rl-buy-now-form">';
                    echo '<a class="button alt rl-buy-now-button" href="' . esc_url($login_url) . '"><svg class="rl-button-icon" aria-hidden="true" viewBox="0 0 24 24"><path d="M3 3h2l2.1 10.2a2 2 0 0 0 2 1.6h7.7a2 2 0 0 0 2-1.6L19 8H7M9 21a1 1 0 1 0 0-2 1 1 0 0 0 0 2Zm8 0a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z"/></svg><span>LOGIN / REGISTER TO BUY</span><b aria-hidden="true">→</b></a>';
                    echo '</div>';
                } else {
                    echo '<form class="rl-buy-now-form" method="post" action="' . esc_url($action) . '">';
                        echo '<input type="hidden" name="add-to-cart" value="' . esc_attr($product->get_id()) . '">';
                        echo '<input type="hidden" name="quantity" value="1">';
                        echo '<input type="hidden" name="rafflelb_purchase_mode" value="buy_now">';
                        echo '<button type="submit" class="button alt rl-buy-now-button"><svg class="rl-button-icon" aria-hidden="true" viewBox="0 0 24 24"><path d="M3 3h2l2.1 10.2a2 2 0 0 0 2 1.6h7.7a2 2 0 0 0 2-1.6L19 8H7M9 21a1 1 0 1 0 0-2 1 1 0 0 0 0 2Zm8 0a1 1 0 1 0 0-2 1 1 0 1 0 0 2Z"/></svg><span>BUY NOW</span><b aria-hidden="true">→</b></button>';
                    echo '</form>';
                }
            echo '</div>';
        echo '</section>';
    }

    public static function raffle_entry_trust() {
        if (self::$selection_entry_form_context) return;
        global $product;
        if (!self::is_raffle_product($product)) return;
        echo '<div class="rl-entry-trust" aria-label="Raffle entry assurances">';
        echo '<span><svg aria-hidden="true" viewBox="0 0 24 24"><path d="M12 3 4 6v5c0 5.2 3.4 8.5 8 10 4.6-1.5 8-4.8 8-10V6l-8-3Zm-3 9 2 2 4-4"/></svg><b>Secure entry</b><small>Your information is safe</small></span>';
        echo '<span><svg aria-hidden="true" viewBox="0 0 24 24"><path d="M20 11a8 8 0 1 1-3-6.2M8 12l2.5 2.5L21 4"/></svg><b>Verified selection</b><small>100% transparent</small></span>';
        echo '<span><svg aria-hidden="true" viewBox="0 0 24 24"><ellipse cx="12" cy="5" rx="7" ry="3"/><path d="M5 5v7c0 1.7 3.1 3 7 3s7-1.3 7-3V5m-14 7v7c0 1.7 3.1 3 7 3s7-1.3 7-3v-7"/></svg><b>Entries saved to my raffles</b><small>Track your entries anytime</small></span>';
        echo '</div>';
    }

    public static function raffle_remove_native_short_description() {
        if (!function_exists('is_product') || !is_product()) return;

        global $product;
        if (!$product instanceof WC_Product) {
            $product = wc_get_product(get_queried_object_id());
        }

        if (!self::is_raffle_product($product)) return;

        // WooCommerce normally prints the native short description at priority 20.
        // Our custom lime-border short description is printed separately.
        remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_excerpt', 20);
    }

    private static function raffle_header_badge($product) {
        $fallback = [
            'class' => $product->is_in_stock() ? 'is-in-stock' : 'is-out-of-stock',
            'label' => $product->is_in_stock() ? 'IN STOCK' : 'OUT OF STOCK',
        ];
        $draw_id = RaffleLB_Draw_Engine::shop_bridge_draw_id($product);
        $stats = $draw_id ? RaffleLB_Draw_Engine::shop_bridge_stats($draw_id, false) : false;
        if (!$stats) return $fallback;

        $status = (string) $stats['status'];
        $has_result = method_exists('RaffleLB_Draw_Engine', 'homepage_get_draw_result')
            && RaffleLB_Draw_Engine::homepage_get_draw_result($draw_id);
        if ($has_result || $status === 'winner_selected') {
            return ['class' => 'is-selection-complete', 'label' => 'SELECTION COMPLETE'];
        }
        if ($status !== 'live') {
            return ['class' => 'is-raffle-closed', 'label' => 'RAFFLE CLOSED'];
        }
        return $fallback;
    }

    public static function raffle_short_description() {
        global $product;
        if (!self::is_raffle_product($product)) return;

        $classes = [];
        $categories = wc_get_product_category_list($product->get_id(), ', ');
        if ($categories) $classes[] = wp_strip_all_tags($categories);
        foreach (['pa_brands', 'pa_brand', 'brand', 'pa_product-type', 'pa_product_type', 'pa_type'] as $taxonomy) {
            if (!taxonomy_exists($taxonomy)) continue;
            $terms = wc_get_product_terms($product->get_id(), $taxonomy, ['fields' => 'names']);
            if (!empty($terms) && !is_wp_error($terms)) { $classes[] = implode(', ', $terms); break; }
        }
        $badge = self::raffle_header_badge($product);
        echo '<div class="rl-product-meta-row"><span class="rl-stock-badge ' . esc_attr($badge['class']) . '"><i aria-hidden="true"></i>' . esc_html($badge['label']) . '</span>';
        if ($classes) echo '<span class="rl-product-classification">' . esc_html(implode(' / ', array_filter($classes))) . '</span>';
        echo '</div>';
        $short = trim((string) $product->get_short_description());
        if ($short === '') return;

        echo '<div class="rl-raffle-short-description">';
        echo wp_kses_post(wpautop(do_shortcode($short)));
        echo '</div>';
    }

    public static function raffle_product_tabs($tabs) {
        if (!function_exists('is_product') || !is_product()) return $tabs;
        global $product;
        if (!self::is_raffle_product($product)) return $tabs;

        // Raffle products use the dedicated RaffleLB details section below.
        // Remove the standard WooCommerce tab strip entirely.
        unset($tabs['description'], $tabs['reviews'], $tabs['additional_information']);
        return $tabs;
    }

    public static function raffle_details_section() {
        global $product;
        if (!self::is_raffle_product($product)) return;

        $title = $product->get_name();
        ?>
        <section class="rl-raffle-details-section" aria-label="Raffle details">
            <div class="rl-raffle-details-inner">
                <div class="rl-raffle-details-heading">
                    <span>RAFFLE DETAILS</span>
                </div>

                <div class="rl-raffle-details-grid">
                    <div class="rl-raffle-detail-col">
                        <h3>PRIZE</h3>
                        <p><strong><?php echo esc_html($title); ?></strong></p>
                        <ul class="rl-raffle-detail-list" role="list">
                            <li>Condition: Brand New</li>
                            <li>Authenticity: 100% Authentic</li>
                        </ul>
                    </div>

                    <div class="rl-raffle-detail-col">
                        <h3>HOW IT WORKS</h3>
                        <ul class="rl-raffle-detail-list" role="list">
                            <li>Choose the number of entries you want.</li>
                            <li>Complete your order.</li>
                            <li>Your unique raffle entries are assigned automatically.</li>
                            <li>Follow the raffle from My Raffles.</li>
                        </ul>
                    </div>

                    <div class="rl-raffle-detail-col">
                        <h3>WINNER</h3>
                        <ul class="rl-raffle-detail-list" role="list">
                            <li>The winner is selected according to the RaffleLB selection process once the raffle closes.</li>
                            <li>The verified result is published on the Winners page.</li>
                        </ul>
                    </div>

                    <div class="rl-raffle-detail-col">
                        <h3>IMPORTANT</h3>
                        <ul class="rl-raffle-detail-list" role="list">
                            <li>Review the raffle details and selection terms before entering.</li>
                            <li>Entries are recorded to your RaffleLB account after a successful order.</li>
                        </ul>
                    </div>
                </div>
            </div>
        </section>
        <?php
    }

    public static function raffle_archive_body_class($classes) {
        $is_shop_archive = function_exists('is_shop') && is_shop();
        $is_cat_archive  = function_exists('is_product_category') && is_product_category();

        if ($is_shop_archive || $is_cat_archive) {
            $classes[] = 'rafflelb-raffle-archive';
            $classes[] = 'rl-shop-view-' . self::shop_view_mode();
            if ($is_shop_archive) {
                $classes[] = 'rafflelb-raffle-shop';
            }
            if ($is_cat_archive) {
                $classes[] = 'rafflelb-category-active';
            }

            // Mark empty raffle archives so WoodMart's fallback search
            // can be hidden without affecting header search.
            global $wp_query;
            if ($wp_query instanceof WP_Query && (int) $wp_query->post_count === 0) {
                $classes[] = 'rafflelb-empty-raffle-archive';
            }
        }
        return $classes;
    }

    public static function raffle_archive_product_class($classes, $class = '', $post_id = 0) {
        $is_shop_archive = function_exists('is_shop') && is_shop();
        $is_cat_archive  = function_exists('is_product_category') && is_product_category();
        if (!$is_shop_archive && !$is_cat_archive) return $classes;
        $post_id = absint($post_id);
        if (!$post_id || get_post_type($post_id) !== 'product') return $classes;

        $mode = self::shop_view_mode();
        $product_mode = self::shop_product_mode($post_id);
        if (self::shop_mode_includes_product($mode, $product_mode)) {
            $classes[] = 'rl-raffle-card';
            $classes[] = 'rl-shop-card-' . $product_mode;
        }
        return $classes;
    }

    public static function raffle_archive_hero() {
        $is_shop_archive = function_exists('is_shop') && is_shop();
        $is_cat_archive  = function_exists('is_product_category') && is_product_category();
        if (!$is_shop_archive && !$is_cat_archive) return;

        static $rendered = false;
        if ($rendered) return;
        $rendered = true;

        $term = $is_cat_archive ? get_queried_object() : null;
        $title = ($term && !empty($term->name)) ? $term->name : 'STORE';
        $description = ($term && !empty($term->description))
            ? wp_strip_all_tags($term->description)
            : 'Shop premium products directly, with selected items also available through RaffleLB raffles.';

        echo '<section class="rl-shop-hero">';
            echo '<div class="rl-shop-hero-main">';
                echo '<div class="rl-shop-title-row">';
                    echo '<div class="rl-shop-heading">';
                        echo '<div class="rl-shop-title-lockup"><h1>' . esc_html($title) . '</h1><i class="rl-shop-title-rule" aria-hidden="true"></i></div>';
                        echo '<p>' . esc_html($description) . '</p>';
                    echo '</div>';
                    echo '<div class="rl-shop-assurances" aria-label="Store assurances">';
                        echo '<span>Direct purchase</span>';
                        echo '<span>Secure checkout</span>';
                        echo '<span>Raffle option on selected items</span>';
                    echo '</div>';
                echo '</div>';
            echo '</div>';

        echo '</section>';
    }

    public static function shop_query_is_catalog($query = null) {
        if ($query instanceof WP_Query) {
            return $query->is_post_type_archive('product') || $query->is_tax('product_cat');
        }

        $is_shop_archive = function_exists('is_shop') && is_shop();
        $is_cat_archive  = function_exists('is_product_category') && is_product_category();
        return $is_shop_archive || $is_cat_archive;
    }

    public static function shop_view_mode() {
        $mode = isset($_GET['rl_view']) ? sanitize_key(wp_unslash($_GET['rl_view'])) : 'both';
        return in_array($mode, ['both', 'retail', 'raffle'], true) ? $mode : 'both';
    }

    /**
     * Shopping Mode is intentionally a document navigation, never an AJAX
     * catalog refresh. Print this in the head so its capturing listener is
     * registered before WoodMart's delegated Shop handlers.
     */
    public static function shop_mode_navigation_guard() {
        if (!self::shop_query_is_catalog()) return;
        ?>
        <script id="rafflelb-shop-mode-navigation-guard">
        (function(){
            document.addEventListener('click', function(event){
                var link = event.target && event.target.closest ? event.target.closest('.rl-shop-view-mode') : null;
                if (!link || !link.href) return;

                // Preserve the browser's normal new-tab/window behavior for
                // explicitly modified clicks. A standard activation below is
                // always one full GET navigation.
                if (event.button && event.button !== 0) return;
                if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;

                event.preventDefault();
                event.stopPropagation();
                event.stopImmediatePropagation();

                try {
                    // Mode switching is read-only catalogue navigation. The
                    // generated mode URLs contain none of these keys; deleting
                    // them defensively ensures a stale/mutated link can never
                    // become a WooCommerce cart request.
                    var destination = new URL(link.href, window.location.href);
                    ['add-to-cart', 'rafflelb_purchase_mode', 'quantity'].forEach(function(key){
                        destination.searchParams.delete(key);
                    });

                    try {
                        window.sessionStorage.setItem('rafflelb_shop_return_v03324', JSON.stringify({
                            openFilters: false,
                            ts: Date.now()
                        }));
                    } catch (ignore) {}

                    window.location.assign(destination.href);
                } catch (ignore) {
                    // The href is emitted by WordPress. This fallback retains
                    // the same full-page browser navigation if URL parsing is
                    // unavailable in an older browser.
                    window.location.assign(link.href);
                }
            }, true);
        })();
        </script>
        <?php
    }

    /** Customer-safe cancellation state exposed by Draw Engine. */
    private static function shop_public_early_closure($product_id) {
        $product_id = absint($product_id);
        if (!$product_id || !class_exists('RaffleLB_Draw_Engine') || !method_exists('RaffleLB_Draw_Engine', 'selection_bridge_public_closure')) return null;
        $closure = RaffleLB_Draw_Engine::selection_bridge_public_closure($product_id);
        return is_array($closure) && !empty($closure['closed_early']) ? $closure : null;
    }

    private static function shop_raffle_cancelled($product_id) {
        $closure = self::shop_public_early_closure($product_id);
        return is_array($closure) && sanitize_key((string) ($closure['mode'] ?? '')) === 'cancel_refund';
    }

    /**
     * Give The SEO Framework a useful generated description for WooCommerce
     * products when no manual TSF description was saved for that product.
     *
     * TSF already generates a good branded product title from the WooCommerce
     * product name, so RaffleLB only replaces the generated description source.
     * A hand-written `_genesis_description` always wins and is left untouched.
     *
     * The wording is mode-aware so a future Store Only or Raffle Only item does
     * not advertise a purchase/Selection route that the customer cannot use.
     */
    public static function tsf_product_description_excerpt($excerpt, $args = null) {
        if (!function_exists('wc_get_product')) return $excerpt;

        $product_id = 0;
        if (is_array($args) && !empty($args['id'])) {
            $product_id = absint($args['id']);
        }
        if (!$product_id && function_exists('get_queried_object_id')) {
            $product_id = absint(get_queried_object_id());
        }
        if (!$product_id && function_exists('get_the_ID')) {
            $product_id = absint(get_the_ID());
        }

        if (!$product_id || get_post_type($product_id) !== 'product') return $excerpt;

        /* Preserve every manually authored TSF product description. */
        $manual = trim((string) get_post_meta($product_id, '_genesis_description', true));
        if ($manual !== '') return $excerpt;

        $product = wc_get_product($product_id);
        if (!$product instanceof WC_Product) return $excerpt;

        $name = trim(wp_strip_all_tags((string) $product->get_name()));
        if ($name === '') return $excerpt;

        /* If Core is unavailable, fall back to a neutral store description. */
        $mode = class_exists('RaffleLB\\Core\\Contracts') ? self::shop_product_mode($product) : 'retail';

        if ($mode === 'both') {
            return sprintf(
                'Shop %s at RaffleLB. Buy directly or join the live Selection for a chance to win.',
                $name
            );
        }

        if ($mode === 'raffle') {
            return sprintf(
                'Discover %s at RaffleLB. Join the live Selection, track your entries, and follow the result transparently.',
                $name
            );
        }

        return sprintf(
            'Shop %s at RaffleLB. View product details, availability, and direct purchase options.',
            $name
        );
    }

    /* Product type is distinct from the overlapping customer shopping views. */
    /**
     * Keep public ecommerce schema aligned with the real direct-purchase route.
     *
     * Raffle products intentionally use WooCommerce's native product price as
     * the Selection entry price. That value must never be exposed to Google as
     * though it were the retail selling price of the prize. Store + Raffle (or
     * a cancelled raffle that remains directly purchasable) therefore exports
     * the configured Buy Now price, while Raffle Only removes the Merchant
     * Offer entirely and leaves the Product entity itself indexable.
     */
    private static function structured_data_retail_price($product) {
        if (!$product instanceof WC_Product || !self::is_raffle_product($product)) return 0.0;

        $mode = self::shop_product_mode($product);
        if (!in_array($mode, ['both', 'retail'], true)) return 0.0;

        $retail = (float) RaffleLB_Draw_Engine::shop_bridge_buy_now_price($product);
        return $retail > 0 ? $retail : 0.0;
    }

    /**
     * Replace WooCommerce's Selection-entry Offer with the real Buy Now offer.
     * Shipping/returns/seller fields added by WooCommerce or another integration
     * are preserved; only price-bearing fields are normalised to the retail
     * amount so Merchant listings cannot advertise an entry fee as item price.
     */
    public static function structured_data_product_offer($offer, $product) {
        if (!is_array($offer) || !$product instanceof WC_Product) return $offer;

        $retail = self::structured_data_retail_price($product);
        if ($retail <= 0) return $offer;

        $tax_display = get_option('woocommerce_tax_display_shop');
        if (function_exists('wc_tax_enabled') && wc_tax_enabled()) {
            $retail = $tax_display === 'incl'
                ? (float) wc_get_price_including_tax($product, ['price' => $retail])
                : (float) wc_get_price_excluding_tax($product, ['price' => $retail]);
        }

        $price = wc_format_decimal($retail, wc_get_price_decimals());
        $currency = get_woocommerce_currency();
        $valid_through = gmdate('Y-12-31', time() + (defined('YEAR_IN_SECONDS') ? YEAR_IN_SECONDS : 31536000));

        $price_spec = [
            '@type'         => 'UnitPriceSpecification',
            'price'         => $price,
            'priceCurrency' => $currency,
            'validThrough'  => $valid_through,
        ];

        if (function_exists('wc_tax_enabled') && wc_tax_enabled()) {
            $price_spec['valueAddedTaxIncluded'] = $tax_display === 'incl';
        }

        /* A raffle entry can carry sale/range semantics that have no meaning
         * for the independently configured direct-purchase price. */
        $offer['@type'] = 'Offer';
        unset($offer['lowPrice'], $offer['highPrice'], $offer['offerCount']);
        $offer['priceSpecification'] = [$price_spec];
        $offer['price'] = $price;
        $offer['priceCurrency'] = $currency;
        $offer['priceValidUntil'] = $valid_through;

        return self::structured_data_direct_purchase_policies($offer, $product);
    }

    /**
     * Add RaffleLB's current physical-delivery and direct-purchase return policy
     * to Google/WooCommerce Merchant Offer markup. Existing schema supplied by
     * WooCommerce, a shipping integration, or another trusted plugin always wins.
     *
     * Current public policy:
     * - Delivery within Lebanon only.
     * - Flat delivery fee: USD 4.50.
     * - Typical delivery: 1–3 days.
     * - Direct-purchase returns: 7 days from delivery, subject to the published
     *   Shipping & Returns conditions.
     *
     * Clearly digital products are intentionally excluded because vouchers
     * and digital codes can have different fulfilment/return rules. RaffleLB
     * raffle-enabled physical products may be WooCommerce-virtual for entry-flow
     * reasons, so the virtual flag alone must not suppress delivery schema.
     */
    /**
     * RaffleLB raffle-enabled products can be marked virtual in WooCommerce for
     * entry/checkout behaviour even when the prize is a physical item that is
     * delivered after a direct Buy Now purchase. Do not use is_virtual() as the
     * Merchant-schema shipping signal for those products.
     *
     * Only clearly digital fulfilment is excluded here: downloadable products
     * and products assigned to RaffleLB's vouchers/gift-cards catalog category.
     */
    private static function structured_data_is_digital_product($product) {
        if (!$product instanceof WC_Product) return false;
        if ($product->is_downloadable()) return true;

        $product_id = $product->get_id();
        if (!$product_id) return false;

        $digital_slugs = [
            'vouchers-and-gift-cards',
            'vouchers-gift-cards',
            'gift-cards',
            'gift-card',
            'digital-vouchers',
            'digital-codes',
        ];

        foreach ($digital_slugs as $slug) {
            if (has_term($slug, 'product_cat', $product_id)) return true;
        }

        return false;
    }

    private static function structured_data_direct_purchase_policies($offer, $product) {
        if (!is_array($offer) || !$product instanceof WC_Product) return $offer;

        $is_direct_purchase = true;
        if (self::is_raffle_product($product)) {
            $mode = self::shop_product_mode($product);
            $is_direct_purchase = in_array($mode, ['both', 'retail'], true)
                && self::structured_data_retail_price($product) > 0;
        } else {
            $is_direct_purchase = $product->is_purchasable() && (float) $product->get_price() > 0;
        }

        if (!$is_direct_purchase || self::structured_data_is_digital_product($product)) {
            return $offer;
        }

        $currency = get_woocommerce_currency();
        $shipping_rate = (float) apply_filters('rafflelb_shop_schema_shipping_rate', 4.50, $product);
        $delivery_min = max(0, absint(apply_filters('rafflelb_shop_schema_delivery_min_days', 1, $product)));
        $delivery_max = max($delivery_min, absint(apply_filters('rafflelb_shop_schema_delivery_max_days', 3, $product)));
        $return_days = max(1, absint(apply_filters('rafflelb_shop_schema_return_days', 7, $product)));
        $country = strtoupper(sanitize_text_field((string) apply_filters('rafflelb_shop_schema_country', 'LB', $product)));
        if (!preg_match('/^[A-Z]{2}$/', $country)) $country = 'LB';

        if (empty($offer['shippingDetails'])) {
            $offer['shippingDetails'] = [
                '@type' => 'OfferShippingDetails',
                'shippingDestination' => [
                    '@type' => 'DefinedRegion',
                    'addressCountry' => $country,
                ],
                'shippingRate' => [
                    '@type' => 'MonetaryAmount',
                    'value' => wc_format_decimal(max(0, $shipping_rate), 2),
                    'currency' => $currency,
                ],
                'deliveryTime' => [
                    '@type' => 'ShippingDeliveryTime',
                    'transitTime' => [
                        '@type' => 'QuantitativeValue',
                        'minValue' => $delivery_min,
                        'maxValue' => $delivery_max,
                        'unitCode' => 'DAY',
                    ],
                ],
            ];
        }

        if (empty($offer['hasMerchantReturnPolicy'])) {
            $offer['hasMerchantReturnPolicy'] = [
                '@type' => 'MerchantReturnPolicy',
                'applicableCountry' => $country,
                'returnPolicyCategory' => 'https://schema.org/MerchantReturnFiniteReturnWindow',
                'merchantReturnDays' => $return_days,
            ];
        }

        return $offer;
    }

    /**
     * Ensure the same shipping/returns enrichment also applies to any future
     * Store Only WooCommerce product whose native price does not need RaffleLB's
     * retail-price substitution.
     */
    public static function structured_data_product_offer_policies($offer, $product) {
        return self::structured_data_direct_purchase_policies($offer, $product);
    }

    /**
     * Raffle Only pages remain indexable as ordinary web pages, but they must
     * not be emitted as Google Product/Merchant rich-result data. This avoids
     * both a false retail Offer and an invalid Product snippet with no genuine
     * purchase offer. Store Only and Store + Raffle keep WooCommerce Product
     * structured data.
     */
    public static function structured_data_types_for_page($types) {
        if (!is_array($types) || !function_exists('is_product') || !is_product()) return $types;

        $product = self::current_product();
        if (!$product instanceof WC_Product || !self::is_raffle_product($product)) return $types;

        $mode = self::shop_product_mode($product);
        if (in_array($mode, ['raffle', 'cancelled'], true)) {
            $types = array_values(array_diff($types, ['product', 'review']));
        }

        return $types;
    }

    /**
     * Defense in depth for any consumer that reads WooCommerce's generated
     * Product data directly instead of using its normal page-type output list.
     * Raffle Only must never carry a Merchant Offer based on an entry fee.
     */
    public static function structured_data_product($markup, $product) {
        if (!is_array($markup) || !$product instanceof WC_Product || !self::is_raffle_product($product)) {
            return $markup;
        }

        $mode = self::shop_product_mode($product);
        if (in_array($mode, ['raffle', 'cancelled'], true)) {
            unset($markup['offers']);
        }

        return $markup;
    }

    private static function shop_product_mode($product) {
        $product_id = $product instanceof WC_Product ? $product->get_id() : absint($product);
        if (!$product_id) return '';

        $raffle_enabled = get_post_meta($product_id, \RaffleLB\Core\Contracts::META_ENABLED, true) === 'yes';
        if (!$raffle_enabled) return 'retail';

        $buy_now_enabled = get_post_meta($product_id, \RaffleLB\Core\Contracts::META_BUY_NOW_ENABLED, true) === 'yes';
        $buy_now_price = (float) get_post_meta($product_id, \RaffleLB\Core\Contracts::META_BUY_NOW_PRICE, true);

        /* A cancelled raffle is no longer a raffle-shopping route. If the same
         * product genuinely supports direct purchase, keep only that retail
         * route; otherwise remove it from customer catalogue views entirely. */
        if (self::shop_raffle_cancelled($product_id)) {
            return $buy_now_enabled && $buy_now_price > 0 ? 'retail' : 'cancelled';
        }

        return $buy_now_enabled && $buy_now_price > 0 ? 'both' : 'raffle';
    }

    private static function shop_mode_includes_product($mode, $product_mode) {
        if ($mode === 'retail') return $product_mode === 'retail' || $product_mode === 'both';
        if ($mode === 'raffle') return $product_mode === 'raffle' || $product_mode === 'both';
        /* 'both' is the default Shopping Mode and is now the complete
           catalogue: Store Only, Raffle Only and Store + Raffle are all
           visible, so category/shop views never appear falsely empty. */
        return $product_mode !== 'cancelled';
    }

    private static function shop_mode_meta_query($mode) {
        if ($mode === 'retail') {
            /* Store mode is applied by shop_store_catalog_clauses(). A nested
             * WP_Meta_Query here creates several self-joins on postmeta and
             * is prohibitively expensive on a live catalogue. */
            return [];
        }

        if ($mode === 'raffle') {
            return [
                'key'     => \RaffleLB\Core\Contracts::META_ENABLED,
                'value'   => 'yes',
                'compare' => '=',
            ];
        }

        /* 'both' is the default ALL PRODUCTS view: Store Only, Raffle Only
         * and Store + Raffle are all eligible, so no classification
         * restriction is applied here at all (only the winner-selected
         * exclusion the caller adds). This also makes the default view's
         * query simpler than either dedicated mode's. */
        return [];
    }

    public static function shop_mode_url($mode) {
        $mode = in_array($mode, ['both', 'retail', 'raffle'], true) ? $mode : 'both';
        $request_uri = isset($_SERVER['REQUEST_URI']) ? wp_unslash($_SERVER['REQUEST_URI']) : '/shop/';
        $url = home_url($request_uri);
        $url = remove_query_arg(['rl_view', 'rl_search_scope', 'min_price', 'max_price', 'paged', 'product-page', 'orderby'], $url);
        if ($mode !== 'both') {
            $url = add_query_arg('rl_view', $mode, $url);
        }
        return $url . '#rl-shop-controls';
    }

    public static function shop_has_native_price_filter($query = null) {
        if (isset($_GET['min_price']) || isset($_GET['max_price'])) {
            return true;
        }
        if ($query instanceof WP_Query) {
            return $query->get('min_price') !== '' || $query->get('max_price') !== '';
        }
        return false;
    }

    public static function shop_native_price_value($key, $query = null, $fallback = null) {
        if (isset($_GET[$key])) {
            return max(0, (float) wc_format_decimal(wp_unslash($_GET[$key])));
        }
        if ($query instanceof WP_Query) {
            $value = $query->get($key);
            if ($value !== '' && $value !== null) {
                return max(0, (float) wc_format_decimal($value));
            }
        }
        return $fallback;
    }

    public static function shop_catalog_scope($query) {
        if (!$query instanceof WP_Query || !$query->is_main_query()) return;
        if (is_admin() && !(function_exists('wp_doing_ajax') && wp_doing_ajax())) return;
        if (!self::shop_query_is_catalog($query)) return;

        $mode = self::shop_view_mode();
        $query->set('rafflelb_cancelled_catalog_scope', $mode);
        $meta_query = $query->get('meta_query');
        if (!is_array($meta_query)) $meta_query = [];

        if ($mode === 'retail') {
            $query->set('rafflelb_store_catalog_scope', true);
        } else {
            $restriction = self::shop_mode_meta_query($mode);
            if (!empty($restriction)) {
                $meta_query[] = $restriction;
            }
            /* Keep winner-selected raffles out of catalogue browsing, while a
             * genuine Store Only product stays visible even if legacy draw meta
             * happens to exist on it. Applies to both the 'raffle' mode and the
             * default 'both' (all products) view. */
            $meta_query[] = [
                'relation' => 'OR',
                [
                    'key'     => \RaffleLB\Core\Contracts::META_ENABLED,
                    'compare' => 'NOT EXISTS',
                ],
                [
                    'key'     => \RaffleLB\Core\Contracts::META_ENABLED,
                    'value'   => 'yes',
                    'compare' => '!=',
                ],
                [
                    'key'     => \RaffleLB\Core\Contracts::META_DRAW_STATUS,
                    'compare' => 'NOT EXISTS',
                ],
                [
                    'key'     => \RaffleLB\Core\Contracts::META_DRAW_STATUS,
                    'value'   => 'winner_selected',
                    'compare' => '!=',
                ],
            ];
        }

        $query->set('meta_query', $meta_query);

        $orderby = isset($_GET['orderby']) ? sanitize_key(wp_unslash($_GET['orderby'])) : sanitize_key((string)$query->get('orderby'));
        if (($mode === 'both' || $mode === 'retail') && ($orderby === 'rl_retail_asc' || $orderby === 'rl_retail_desc')) {
            $query->set('rafflelb_effective_retail_order', $orderby === 'rl_retail_asc' ? 'ASC' : 'DESC');
        }
    }

    /**
     * Store mode is Store Only plus Store & Raffle. Correlated subqueries keep
     * each meta lookup constrained by post_id and meta_key, avoiding the large
     * multi-alias WP_Meta_Query generated by the former OR tree.
     */
    public static function shop_store_catalog_clauses($clauses, $query) {
        if (!$query instanceof WP_Query || !$query->is_main_query()) return $clauses;
        if (!self::shop_query_is_catalog($query) || !$query->get('rafflelb_store_catalog_scope')) return $clauses;
        if (strpos((string) ($clauses['where'] ?? ''), 'rafflelb_store_catalog_scope') !== false) return $clauses;

        global $wpdb;
        $enabled = \RaffleLB\Core\Contracts::META_ENABLED;
        $buy_enabled = \RaffleLB\Core\Contracts::META_BUY_NOW_ENABLED;
        $buy_price = \RaffleLB\Core\Contracts::META_BUY_NOW_PRICE;
        $draw_status = \RaffleLB\Core\Contracts::META_DRAW_STATUS;
        $id = "{$wpdb->posts}.ID";

        $is_raffle = $wpdb->prepare(
            "EXISTS (SELECT 1 FROM {$wpdb->postmeta} rl_store_draw WHERE rl_store_draw.post_id = {$id} AND rl_store_draw.meta_key = %s AND rl_store_draw.meta_value = 'yes')",
            $enabled
        );
        $is_buyable_raffle = $wpdb->prepare(
            "EXISTS (SELECT 1 FROM {$wpdb->postmeta} rl_store_buy_enabled WHERE rl_store_buy_enabled.post_id = {$id} AND rl_store_buy_enabled.meta_key = %s AND rl_store_buy_enabled.meta_value = 'yes')
             AND EXISTS (SELECT 1 FROM {$wpdb->postmeta} rl_store_buy_price WHERE rl_store_buy_price.post_id = {$id} AND rl_store_buy_price.meta_key = %s AND CAST(rl_store_buy_price.meta_value AS DECIMAL(18,4)) > 0)",
            $buy_enabled,
            $buy_price
        );
        $winner_selected = $wpdb->prepare(
            "EXISTS (SELECT 1 FROM {$wpdb->postmeta} rl_store_winner WHERE rl_store_winner.post_id = {$id} AND rl_store_winner.meta_key = %s AND rl_store_winner.meta_value = 'winner_selected')",
            $draw_status
        );

        $clauses['where'] .= " AND /* rafflelb_store_catalog_scope */ ((NOT {$is_raffle}) OR ({$is_buyable_raffle})) AND ((NOT {$is_raffle}) OR NOT {$winner_selected})";
        return $clauses;
    }

    /**
     * Cancelled raffles disappear from RAFFLE ONLY. In the default combined
     * catalogue they remain only when a genuine direct-purchase route exists,
     * in which case the card is rendered as Store Only.
     */
    public static function shop_cancelled_catalog_clauses($clauses, $query) {
        if (!$query instanceof WP_Query || !$query->is_main_query()) return $clauses;
        if (!self::shop_query_is_catalog($query)) return $clauses;
        $mode = sanitize_key((string) $query->get('rafflelb_cancelled_catalog_scope'));
        if (!in_array($mode, ['both', 'raffle'], true)) return $clauses;
        if (strpos((string) ($clauses['where'] ?? ''), 'rafflelb_cancelled_catalog_scope') !== false) return $clauses;

        global $wpdb;
        $id = "{$wpdb->posts}.ID";
        $cancelled = "(EXISTS (SELECT 1 FROM {$wpdb->postmeta} rl_cancel_closed WHERE rl_cancel_closed.post_id = {$id} AND rl_cancel_closed.meta_key = '_rafflelb_early_closed' AND rl_cancel_closed.meta_value = 'yes')
"
            . " AND EXISTS (SELECT 1 FROM {$wpdb->postmeta} rl_cancel_mode WHERE rl_cancel_mode.post_id = {$id} AND rl_cancel_mode.meta_key = '_rafflelb_early_close_mode' AND rl_cancel_mode.meta_value = 'cancel_refund'))";

        if ($mode === 'raffle') {
            $clauses['where'] .= " AND /* rafflelb_cancelled_catalog_scope */ NOT {$cancelled}";
            return $clauses;
        }

        $buy_enabled = \RaffleLB\Core\Contracts::META_BUY_NOW_ENABLED;
        $buy_price = \RaffleLB\Core\Contracts::META_BUY_NOW_PRICE;
        $buyable = $wpdb->prepare(
            "EXISTS (SELECT 1 FROM {$wpdb->postmeta} rl_cancel_buy_enabled WHERE rl_cancel_buy_enabled.post_id = {$id} AND rl_cancel_buy_enabled.meta_key = %s AND rl_cancel_buy_enabled.meta_value = 'yes')
"
            . " AND EXISTS (SELECT 1 FROM {$wpdb->postmeta} rl_cancel_buy_price WHERE rl_cancel_buy_price.post_id = {$id} AND rl_cancel_buy_price.meta_key = %s AND CAST(rl_cancel_buy_price.meta_value AS DECIMAL(18,4)) > 0)",
            $buy_enabled,
            $buy_price
        );
        $clauses['where'] .= " AND /* rafflelb_cancelled_catalog_scope */ (NOT {$cancelled} OR ({$buyable}))";
        return $clauses;
    }

    public static function shop_effective_retail_sort($clauses, $query) {
        if (!$query instanceof WP_Query || !self::shop_query_is_catalog($query)) return $clauses;
        $direction = $query->get('rafflelb_effective_retail_order');
        if ($direction !== 'ASC' && $direction !== 'DESC') return $clauses;
        global $wpdb;
        $lookup = $wpdb->wc_product_meta_lookup;
        $clauses['join'] .= $wpdb->prepare(" LEFT JOIN {$wpdb->postmeta} rl_sort_price ON rl_sort_price.post_id = {$wpdb->posts}.ID AND rl_sort_price.meta_key = %s ", \RaffleLB\Core\Contracts::META_BUY_NOW_PRICE);
        $clauses['join'] .= $wpdb->prepare(" LEFT JOIN {$wpdb->postmeta} rl_sort_draw ON rl_sort_draw.post_id = {$wpdb->posts}.ID AND rl_sort_draw.meta_key = %s ", \RaffleLB\Core\Contracts::META_ENABLED);
        $clauses['join'] .= $wpdb->prepare(" LEFT JOIN {$wpdb->postmeta} rl_sort_enabled ON rl_sort_enabled.post_id = {$wpdb->posts}.ID AND rl_sort_enabled.meta_key = %s ", \RaffleLB\Core\Contracts::META_BUY_NOW_ENABLED);
        $clauses['join'] .= " INNER JOIN {$lookup} rl_sort_store ON rl_sort_store.product_id = {$wpdb->posts}.ID ";
        $retail = "CAST(COALESCE(rl_sort_price.meta_value, '0') AS DECIMAL(18,4))";
        $effective = "CASE WHEN rl_sort_draw.meta_value = 'yes' AND rl_sort_enabled.meta_value = 'yes' AND {$retail} > 0 THEN {$retail} ELSE rl_sort_store.min_price END";
        $clauses['orderby'] = "{$effective} {$direction}";
        return $clauses;
    }

    public static function shop_premium_toolbar() {
        if (!self::shop_query_is_catalog()) return;

        $mode = self::shop_view_mode();
        $filter_count = 0;
        $has_category_filter = function_exists('is_product_category') && is_product_category();
        $has_brand_filter = self::shop_brand_slug() !== '';
        $has_min_price = isset($_GET['min_price']) && wc_format_decimal(wp_unslash($_GET['min_price'])) !== '';
        $has_max_price = isset($_GET['max_price']) && wc_format_decimal(wp_unslash($_GET['max_price'])) !== '';
        if ($has_category_filter) $filter_count++;
        if ($has_brand_filter) $filter_count++;
        if ($has_min_price) $filter_count++;
        if ($has_max_price) $filter_count++;

        $clear_url = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : home_url('/shop/');
        if ($mode !== 'both') {
            $clear_url = add_query_arg('rl_view', $mode, $clear_url);
        }
        if (isset($_GET['orderby']) && sanitize_key(wp_unslash($_GET['orderby']))) {
            $clear_url = add_query_arg('orderby', sanitize_key(wp_unslash($_GET['orderby'])), $clear_url);
        }
        $clear_url .= '#rl-shop-controls';

        $modes = [
            'both'   => 'STORE & RAFFLE',
            'retail' => 'STORE ONLY',
            'raffle' => 'RAFFLE ONLY',
        ];

        echo '<section id="rl-shop-controls" class="rl-shop-toolbar rl-shop-toolbar-clean" aria-label="Store controls">';
            echo '<div class="rl-shop-toolbar-spacer">';
                if (is_user_logged_in()) {
                    $points = (float) get_user_meta(get_current_user_id(), '_rafflelb_ref_points', true);
                    $points_display = rtrim(rtrim(number_format($points, 2, '.', ''), '0'), '.');
                    if ($points_display === '') $points_display = '0';
                    $account_url = function_exists('wc_get_account_endpoint_url') ? wc_get_account_endpoint_url('dashboard') : home_url('/my-account/');
                    $icon = plugins_url('assets/rafflelb-site-icon.png', __FILE__);

                    echo '<a class="rl-shop-points-badge" href="' . esc_url($account_url) . '">';
                        echo '<span class="rl-shop-points-icon"><img src="' . esc_url($icon) . '" alt=""></span>';
                        echo '<span class="rl-shop-points-label">Raffle Points</span>';
                        echo '<strong class="rl-shop-points-value">' . esc_html($points_display) . '</strong>';
                    echo '</a>';
                }
            echo '</div>';
            if (is_user_logged_in()) {
                echo '<span class="rl-shop-toolbar-divider" aria-hidden="true"></span>';
            }
            echo '<div class="rl-shop-mode-block">';
                echo '<span class="rl-shop-mode-eyebrow">SHOPPING MODE</span>';
                echo '<nav class="rl-shop-view-modes" aria-label="Choose your way to shop">';
                    foreach ($modes as $mode_key => $label) {
                        $active = $mode === $mode_key ? ' is-active' : '';
                        echo '<a class="rl-shop-view-mode' . esc_attr($active) . '" href="' . esc_url(self::shop_mode_url($mode_key)) . '"' . ($mode === $mode_key ? ' aria-current="page"' : '') . '>' . esc_html($label) . '</a>';
                    }
                echo '</nav>';
            echo '</div>';
            echo '<span class="rl-shop-toolbar-divider" aria-hidden="true"></span>';
            echo '<div class="rl-shop-toolbar-actions">';
                if ($filter_count) {
                    echo '<a class="rl-shop-clear-filters" href="' . esc_url($clear_url) . '">CLEAR FILTERS</a>';
                }
                echo '<button type="button" class="rl-shop-filter-toggle" aria-expanded="false"><span class="rl-shop-filter-icon" aria-hidden="true"></span><span>FILTERS' . ($filter_count ? ' <b>' . esc_html($filter_count) . '</b>' : '') . '</span></button>';
                echo '<div class="rl-shop-sort">';
                    echo '<span class="rl-shop-sort-label">SORT</span>';
                    if (function_exists('woocommerce_catalog_ordering')) {
                        woocommerce_catalog_ordering();
                    }
                echo '</div>';
            echo '</div>';
        echo '</section>';
    }

    /**
     * v0.2.41 — one-time discovery hint for the Store Filters control.
     * Rendered as a body-level fixed "portal" so no WoodMart/theme toolbar
     * overflow rule can clip the message. The hint follows the real Filters
     * button on resize/scroll and disappears when Filters is opened.
     */
    public static function shop_filter_hint_assets() {
        if (!self::shop_query_is_catalog()) return;
        ?>
        <style id="rafflelb-shop-filter-hint-v0241" data-no-optimize="1" data-noptimize="1" data-no-minify="1" data-wpr-nooptimize="1">
            #rl-filter-discovery-hint{
                position:absolute!important;
                left:0;
                top:0;
                display:flex!important;
                align-items:center!important;
                justify-content:center!important;
                gap:9px!important;
                width:max-content!important;
                max-width:calc(100vw - 24px)!important;
                min-height:40px!important;
                padding:8px 13px!important;
                border:1px solid rgba(186,255,0,.72)!important;
                border-radius:999px!important;
                background:#071008!important;
                color:#f7ffed!important;
                box-shadow:0 10px 30px rgba(0,0,0,.55),0 0 20px rgba(186,255,0,.20),inset 0 0 0 1px rgba(186,255,0,.06)!important;
                opacity:0;
                visibility:hidden;
                pointer-events:none!important;
                transform:scale(.96)!important;
                transform-origin:center bottom!important;
                transition:opacity .2s ease,transform .25s cubic-bezier(.2,.8,.2,1),visibility .2s ease!important;
                z-index:2147483000!important;
                box-sizing:border-box!important;
                white-space:nowrap!important;
                font-family:var(--rl-font,"Manrope",Arial,sans-serif)!important;
                font-size:12px!important;
                line-height:1.2!important;
                font-weight:800!important;
                letter-spacing:.01em!important;
                text-transform:none!important;
            }
            #rl-filter-discovery-hint.is-visible{
                opacity:1!important;
                visibility:visible!important;
                transform:scale(1)!important;
                animation:rlFilterPortalGlow 1.55s ease-in-out .28s infinite!important;
            }
            #rl-filter-discovery-hint.is-below{
                transform-origin:center top!important;
            }
            #rl-filter-discovery-hint .rl-filter-discovery-copy{
                display:block!important;
                color:#f7ffed!important;
                font:inherit!important;
                line-height:inherit!important;
                letter-spacing:inherit!important;
                text-transform:none!important;
            }
            #rl-filter-discovery-hint .rl-filter-discovery-copy-mobile{display:none!important}
            #rl-filter-discovery-hint .rl-filter-discovery-arrow{
                display:inline-flex!important;
                align-items:center!important;
                justify-content:center!important;
                width:26px!important;
                height:26px!important;
                flex:0 0 26px!important;
                border-radius:50%!important;
                background:#baff00!important;
                color:#061004!important;
                animation:rlFilterPortalIcon 1.35s ease-in-out infinite!important;
            }
            #rl-filter-discovery-hint .rl-filter-discovery-arrow svg{
                display:block!important;
                width:14px!important;
                height:14px!important;
                stroke:currentColor!important;
                fill:none!important;
                stroke-width:2!important;
                stroke-linecap:round!important;
                stroke-linejoin:round!important;
            }
            #rl-filter-discovery-hint:after{
                content:"";
                position:absolute!important;
                left:var(--rl-hint-arrow-x,50%)!important;
                bottom:-6px!important;
                width:11px!important;
                height:11px!important;
                margin-left:-6px!important;
                border-right:1px solid rgba(186,255,0,.72)!important;
                border-bottom:1px solid rgba(186,255,0,.72)!important;
                background:#071008!important;
                transform:rotate(45deg)!important;
            }
            #rl-filter-discovery-hint.is-below:after{
                top:-6px!important;
                bottom:auto!important;
                border:0!important;
                border-left:1px solid rgba(186,255,0,.72)!important;
                border-top:1px solid rgba(186,255,0,.72)!important;
            }
            @keyframes rlFilterPortalGlow{
                0%,100%{box-shadow:0 10px 30px rgba(0,0,0,.55),0 0 12px rgba(186,255,0,.14),inset 0 0 0 1px rgba(186,255,0,.06)}
                50%{box-shadow:0 10px 30px rgba(0,0,0,.55),0 0 27px rgba(186,255,0,.34),inset 0 0 0 1px rgba(186,255,0,.14)}
            }
            @keyframes rlFilterPortalIcon{
                0%,100%{transform:scale(1);box-shadow:0 0 0 0 rgba(186,255,0,.18)}
                50%{transform:scale(1.06);box-shadow:0 0 0 5px rgba(186,255,0,0)}
            }
            @media(max-width:767px){
                #rl-filter-discovery-hint{
                    max-width:calc(100vw - 20px)!important;
                    min-height:38px!important;
                    padding:8px 11px!important;
                    gap:8px!important;
                    white-space:nowrap!important;
                    text-align:center!important;
                    font-size:11px!important;
                }
                #rl-filter-discovery-hint .rl-filter-discovery-copy-desktop{display:none!important}
                #rl-filter-discovery-hint .rl-filter-discovery-copy-mobile{display:block!important}
                #rl-filter-discovery-hint .rl-filter-discovery-arrow{
                    width:23px!important;
                    height:23px!important;
                    flex-basis:23px!important;
                }
                #rl-filter-discovery-hint .rl-filter-discovery-arrow svg{width:13px!important;height:13px!important}
            }
            @media(max-width:390px){
                #rl-filter-discovery-hint{font-size:10.5px!important;padding:7px 10px!important}
            }
            @media(prefers-reduced-motion:reduce){
                #rl-filter-discovery-hint.is-visible,
                #rl-filter-discovery-hint .rl-filter-discovery-arrow{animation:none!important}
            }
        </style>
        <script id="rafflelb-shop-filter-hint-js-v0252" data-no-optimize="1" data-noptimize="1" data-no-minify="1" data-wpr-nooptimize="1">
        (function RaffleLBFilterHint(){
            'use strict';
            var revealTimer = null;
            var autoHideTimer = null;
            var hint = null;
            var dismissedThisView = false;
            function filterButton(){
                return document.querySelector('#rl-shop-controls .rl-shop-toolbar-actions > .rl-shop-filter-toggle:not(.rl-shop-sort-trigger)') ||
                       document.querySelector('#rl-shop-controls .rl-shop-filter-toggle:not(.rl-shop-sort-trigger)');
            }
            function ensureHint(){
                if (hint && document.body.contains(hint)) return hint;
                hint = document.createElement('div');
                hint.id = 'rl-filter-discovery-hint';
                hint.setAttribute('role','status');
                hint.setAttribute('aria-live','polite');
                hint.innerHTML = '<span class="rl-filter-discovery-copy rl-filter-discovery-copy-desktop">Open Filters for Brands &amp; Categories</span><span class="rl-filter-discovery-copy rl-filter-discovery-copy-mobile">Brands &amp; Categories are in Filters</span><span class="rl-filter-discovery-arrow" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M4 7h10"></path><circle cx="17" cy="7" r="2"></circle><path d="M20 17H10"></path><circle cx="7" cy="17" r="2"></circle><path d="M4 12h4"></path><path d="M12 12h8"></path></svg></span>';
                document.body.appendChild(hint);
                return hint;
            }
            function positionHint(){
                var btn = filterButton();
                var el = ensureHint();
                if (!btn || !el) return false;

                var r = btn.getBoundingClientRect();
                var viewportW = Math.max(document.documentElement.clientWidth || 0, window.innerWidth || 0);
                var viewportH = Math.max(document.documentElement.clientHeight || 0, window.innerHeight || 0);
                var scrollX = window.pageXOffset || document.documentElement.scrollLeft || 0;
                var scrollY = window.pageYOffset || document.documentElement.scrollTop || 0;
                var margin = 10;

                /* Body-level absolute positioning: calculate once in document space so
                   the hint scrolls naturally with the Filters button and never jitters. */
                el.style.left = (scrollX + margin) + 'px';
                el.style.top = (scrollY + margin) + 'px';
                var hintW = Math.min(el.offsetWidth || 260, Math.max(120, viewportW - (margin * 2)));
                var hintH = el.offsetHeight || 40;
                var centerViewport = r.left + (r.width / 2);
                var leftViewport = centerViewport - (hintW / 2);
                leftViewport = Math.max(margin, Math.min(viewportW - hintW - margin, leftViewport));

                var roomAbove = r.top - margin;
                var roomBelow = viewportH - r.bottom - margin;
                var placeBelow = roomAbove < (hintH + 14) && roomBelow > roomAbove;
                var topViewport = placeBelow ? (r.bottom + 12) : (r.top - hintH - 12);
                topViewport = Math.max(margin, Math.min(viewportH - hintH - margin, topViewport));

                el.classList.toggle('is-below', placeBelow);
                el.style.left = Math.round(scrollX + leftViewport) + 'px';
                el.style.top = Math.round(scrollY + topViewport) + 'px';

                /* Keep the pointer aimed at the real Filters button. */
                var arrowX = centerViewport - leftViewport;
                arrowX = Math.max(18, Math.min(hintW - 18, arrowX));
                el.style.setProperty('--rl-hint-arrow-x', Math.round(arrowX) + 'px');
                return true;
            }
            function hideForThisView(){
                dismissedThisView = true;
                if (revealTimer) { clearTimeout(revealTimer); revealTimer = null; }
                if (autoHideTimer) { clearTimeout(autoHideTimer); autoHideTimer = null; }
                if (hint) hint.classList.remove('is-visible');
            }
            function reveal(){
                if (dismissedThisView || !filterButton()) return;
                ensureHint();
                if (revealTimer) clearTimeout(revealTimer);
                if (autoHideTimer) clearTimeout(autoHideTimer);
                revealTimer = setTimeout(function(){
                    if (dismissedThisView || !positionHint()) return;
                    hint.classList.add('is-visible');
                    autoHideTimer = setTimeout(hideForThisView, 12000);
                }, 650);
            }
            function onFilterClick(e){
                var target = e.target && e.target.closest ? e.target.closest('#rl-shop-controls .rl-shop-filter-toggle:not(.rl-shop-sort-trigger)') : null;
                if (!target) return;
                hideForThisView();
            }
            function reposition(){
                if (hint && hint.classList.contains('is-visible')) positionHint();
            }

            document.addEventListener('click', onFilterClick, true);
            window.addEventListener('resize', reposition, {passive:true});
            window.addEventListener('orientationchange', function(){ setTimeout(reposition, 120); }, {passive:true});
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', reveal, {once:true});
            } else {
                reveal();
            }
            window.addEventListener('pageshow', function(e){
                /* A fresh load creates a new script context automatically. On BFCache
                   return, treat the restored Shop as a new visit so the hint can show again. */
                if (e && e.persisted) {
                    dismissedThisView = false;
                    reveal();
                }
            });
        })();
        </script>
        <?php
    }

    public static function shop_orderby_options($options) {
        if (!is_array($options)) $options = [];
        if (!self::shop_query_is_catalog()) return $options;

        /* Raffle discovery belongs in the public Store, not Raffle Manager.
         * Keep normal WooCommerce sorting everywhere else and expose raffle-
         * specific choices only while the shopper is in RAFFLE ONLY mode. */
        if (self::shop_view_mode() === 'raffle') {
            return [
                'menu_order' => 'Recommended',
                'rl_closest' => 'Closest to full',
                'date'       => 'Newest raffles',
                'price'      => 'Entry price: low to high',
                'price-desc' => 'Entry price: high to low',
            ];
        }

        return $options;
    }

    public static function shop_catalog_ordering_args($args, $orderby = '', $order = '') {
        if (!is_array($args) || !self::shop_query_is_catalog() || self::shop_view_mode() !== 'raffle') return $args;
        $requested = sanitize_key((string) $orderby);
        if ($requested === '' && isset($_GET['orderby'])) {
            $requested = sanitize_key(wp_unslash($_GET['orderby']));
        }
        if ($requested === 'rl_closest') {
            /* Give WooCommerce a valid base ordering. posts_clauses below
             * replaces it with the authoritative raffle-capacity ordering. */
            $args['orderby'] = 'date';
            $args['order'] = 'DESC';
        }
        return $args;
    }

    public static function shop_raffle_closest_sort($clauses, $query) {
        if (!$query instanceof WP_Query || !self::shop_query_is_catalog($query)) return $clauses;
        if (self::shop_view_mode() !== 'raffle') return $clauses;

        $requested = isset($_GET['orderby']) ? sanitize_key(wp_unslash($_GET['orderby'])) : '';
        if ($requested !== 'rl_closest') return $clauses;
        if (strpos((string) ($clauses['join'] ?? ''), 'rl_raffle_sort_total') !== false) return $clauses;

        global $wpdb;
        $entry_table = $wpdb->prefix . 'rafflelb_entries';
        $total_key = '_rafflelb_total_entries';

        $clauses['join'] .= $wpdb->prepare(
            " LEFT JOIN {$wpdb->postmeta} rl_raffle_sort_total ON rl_raffle_sort_total.post_id = {$wpdb->posts}.ID AND rl_raffle_sort_total.meta_key = %s ",
            $total_key
        );
        $clauses['join'] .= " LEFT JOIN (SELECT product_id, COUNT(*) AS claimed FROM {$entry_table} WHERE status = 'active' GROUP BY product_id) rl_raffle_sort_entries ON rl_raffle_sort_entries.product_id = {$wpdb->posts}.ID ";

        $total = "CAST(COALESCE(rl_raffle_sort_total.meta_value, '0') AS UNSIGNED)";
        $claimed = "COALESCE(rl_raffle_sort_entries.claimed, 0)";
        $remaining = "GREATEST({$total} - {$claimed}, 0)";
        $fill_ratio = "CASE WHEN {$total} > 0 THEN ({$claimed} / {$total}) ELSE 0 END";

        /* Fewest confirmed entries left first. Fill percentage breaks ties so
         * a nearly-complete larger raffle wins over a less-complete one, then
         * newest product provides deterministic ordering. */
        $clauses['orderby'] = "CASE WHEN {$total} > 0 THEN {$remaining} ELSE 2147483647 END ASC, {$fill_ratio} DESC, {$wpdb->posts}.post_date DESC";
        return $clauses;
    }

    public static function shop_native_price_filter_sql($sql, $meta_query_sql, $tax_query_sql) {
        if (!self::shop_query_is_catalog() || self::shop_view_mode() === 'raffle') return $sql;

        global $wpdb;
        $meta_join = is_array($meta_query_sql) && isset($meta_query_sql['join']) ? $meta_query_sql['join'] : '';
        $meta_where = is_array($meta_query_sql) && isset($meta_query_sql['where']) ? $meta_query_sql['where'] : '';
        $tax_join = is_array($tax_query_sql) && isset($tax_query_sql['join']) ? $tax_query_sql['join'] : '';
        $tax_where = is_array($tax_query_sql) && isset($tax_query_sql['where']) ? $tax_query_sql['where'] : '';
        $search = class_exists('WC_Query') ? WC_Query::get_main_search_query_sql() : '';
        $search_where = $search ? ' AND ' . $search : '';

        $post_types = array_map('esc_sql', (array) apply_filters('woocommerce_price_filter_post_type', ['product']));
        if (!$post_types) $post_types = ['product'];
        $post_types_sql = "'" . implode("','", $post_types) . "'";
        $retail_key = esc_sql(\RaffleLB\Core\Contracts::META_BUY_NOW_PRICE);

        $lookup_table = $wpdb->wc_product_meta_lookup;
        $retail_value = "CAST(COALESCE(rl_retail_price.meta_value, '0') AS DECIMAL(18,4))";
        $is_both = "rl_price_draw.meta_value = 'yes' AND rl_price_enabled.meta_value = 'yes' AND {$retail_value} > 0";
        $minimum_value = "CASE WHEN {$is_both} THEN {$retail_value} ELSE rl_store_price.min_price END";
        $maximum_value = "CASE WHEN {$is_both} THEN {$retail_value} ELSE rl_store_price.max_price END";

        return "SELECT MIN({$minimum_value}) AS min_price, "
            . "MAX({$maximum_value}) AS max_price "
            . "FROM {$wpdb->posts} "
            . "LEFT JOIN {$wpdb->postmeta} rl_retail_price ON {$wpdb->posts}.ID = rl_retail_price.post_id AND rl_retail_price.meta_key = '{$retail_key}' "
            . "LEFT JOIN {$wpdb->postmeta} rl_price_draw ON {$wpdb->posts}.ID = rl_price_draw.post_id AND rl_price_draw.meta_key = '" . esc_sql(\RaffleLB\Core\Contracts::META_ENABLED) . "' "
            . "LEFT JOIN {$wpdb->postmeta} rl_price_enabled ON {$wpdb->posts}.ID = rl_price_enabled.post_id AND rl_price_enabled.meta_key = '" . esc_sql(\RaffleLB\Core\Contracts::META_BUY_NOW_ENABLED) . "' "
            . "INNER JOIN {$lookup_table} rl_store_price ON {$wpdb->posts}.ID = rl_store_price.product_id "
            . $tax_join . $meta_join
            . " WHERE {$wpdb->posts}.post_type IN ({$post_types_sql}) "
            . "AND {$wpdb->posts}.post_status = 'publish' "
            . $tax_where . $meta_where . $search_where;
    }

    public static function shop_disable_core_price_filtering($enabled, $query) {
        if ($query instanceof WP_Query && $query->is_main_query() && self::shop_query_is_catalog($query) && self::shop_view_mode() !== 'raffle' && self::shop_has_native_price_filter($query)) {
            // WC core would otherwise compare min_price/max_price against its
            // lookup table, which contains raffle entry fees for these items.
            return false;
        }
        return $enabled;
    }

    public static function shop_native_price_filter_clauses($clauses, $query) {
        if (!$query instanceof WP_Query || !$query->is_main_query()) return $clauses;
        if (!self::shop_query_is_catalog($query) || self::shop_view_mode() === 'raffle' || !self::shop_has_native_price_filter($query)) return $clauses;
        if (!isset($clauses['where']) || strpos($clauses['where'], 'rafflelb_retail_price_filter') !== false) return $clauses;

        global $wpdb;
        $min = self::shop_native_price_value('min_price', $query, 0);
        $max = self::shop_native_price_value('max_price', $query, null);
        $price_conditions = [];
        $retail_value = "CAST(COALESCE(rl_retail_filter.meta_value, '0') AS DECIMAL(18,4))";
        $is_both = "rl_filter_draw.meta_value = 'yes' AND rl_filter_enabled.meta_value = 'yes' AND {$retail_value} > 0";
        $effective_price = "CASE WHEN {$is_both} THEN {$retail_value} ELSE rl_store_filter.min_price END";
        if ($min !== null && $min > 0) {
            $price_conditions[] = $wpdb->prepare("{$effective_price} >= %f", $min);
        }
        if ($max !== null && $max > 0) {
            $price_conditions[] = $wpdb->prepare("{$effective_price} <= %f", $max);
        }
        if (!$price_conditions) return $clauses;

        $lookup_table = $wpdb->wc_product_meta_lookup;
        $clauses['join'] .= $wpdb->prepare(
            " LEFT JOIN {$wpdb->postmeta} rl_retail_filter ON rl_retail_filter.post_id = {$wpdb->posts}.ID AND rl_retail_filter.meta_key = %s ",
            \RaffleLB\Core\Contracts::META_BUY_NOW_PRICE
        );
        $clauses['join'] .= $wpdb->prepare(" LEFT JOIN {$wpdb->postmeta} rl_filter_draw ON rl_filter_draw.post_id = {$wpdb->posts}.ID AND rl_filter_draw.meta_key = %s ", \RaffleLB\Core\Contracts::META_ENABLED);
        $clauses['join'] .= $wpdb->prepare(" LEFT JOIN {$wpdb->postmeta} rl_filter_enabled ON rl_filter_enabled.post_id = {$wpdb->posts}.ID AND rl_filter_enabled.meta_key = %s ", \RaffleLB\Core\Contracts::META_BUY_NOW_ENABLED);
        $clauses['join'] .= " INNER JOIN {$lookup_table} rl_store_filter ON rl_store_filter.product_id = {$wpdb->posts}.ID ";
        $clauses['where'] .= ' AND ' . implode(' AND ', $price_conditions) . ' /* rafflelb_retail_price_filter */';
        return $clauses;
    }

    public static function raffle_archive_progress() {
        $is_shop_archive = function_exists('is_shop') && is_shop();
        $is_cat_archive  = function_exists('is_product_category') && is_product_category();
        if (!$is_shop_archive && !$is_cat_archive) return;
        if (self::shop_view_mode() === 'retail') return;
        global $product;
        if (!$product instanceof WC_Product) return;

        $product_mode = self::shop_product_mode($product);
        if ($product_mode === 'cancelled') return;
        if ($product_mode === 'retail') {
            /* Genuine Store Only products and cancelled raffles that still
               support direct purchase share the same direct-purchase card. */
            if (self::shop_view_mode() === 'both') {
                echo '<div class="rl-shop-store-status" aria-label="Direct purchase">'
                    . '<span class="rl-shop-store-status-badge">STORE ONLY</span>'
                    . '<span class="rl-shop-store-status-note">DIRECT PURCHASE</span>'
                    . '</div>';
            }
            return;
        }

        $stats = RaffleLB_Draw_Engine::shop_bridge_stats($product->get_id(), false);
        if (!$stats) return;

        $status    = (string) $stats['status'];
        $claimed   = absint($stats['claimed']);
        $total     = max(1, absint($stats['total']));
        $available = max(0, absint($stats['available']));
        $percent   = max(0, min(100, absint($stats['percent'])));
        $entry     = (float) wc_get_price_to_display($product);

        if ($status === 'live' && $available > 0) {
            echo '<div class="rl-shop-raffle-box" aria-label="Raffle option">';
                echo '<div class="rl-shop-raffle-line">';
                    echo '<span class="rl-shop-raffle-live"><i></i> RAFFLE AVAILABLE</span>';
                    echo '<strong>' . wp_kses_post(wc_price($entry)) . ' <small>/ ENTRY</small></strong>';
                echo '</div>';
                echo '<div class="rl-shop-raffle-progress"><span style="width:' . esc_attr($percent) . '%"></span></div>';
                echo '<div class="rl-shop-raffle-meta"><span>' . esc_html($claimed) . ' claimed</span><b>' . esc_html($available) . ' left of ' . esc_html($total) . '</b></div>';
            echo '</div>';
        } elseif (in_array($status, ['ready_to_draw','winner_selected'], true)) {
            echo '<div class="rl-shop-raffle-box is-closed"><div class="rl-shop-raffle-line"><span>RAFFLE CLOSED</span><strong>DIRECT SHOPPING VIEW</strong></div></div>';
        }
    }

    public static function raffle_archive_view_button() {
        $is_shop_archive = function_exists('is_shop') && is_shop();
        $is_cat_archive  = function_exists('is_product_category') && is_product_category();
        if (!$is_shop_archive && !$is_cat_archive) return;
        global $product;
        if (!$product instanceof WC_Product) return;

        $mode = self::shop_view_mode();
        $product_mode = self::shop_product_mode($product);
        if (!self::shop_mode_includes_product($mode, $product_mode)) return;

        $is_raffle = $product_mode !== 'retail';
        $has_retail = $product_mode === 'retail' || $product_mode === 'both';

        $pid = $product->get_id();
        $url = get_permalink($pid);
        $stats = $is_raffle ? RaffleLB_Draw_Engine::shop_bridge_stats($pid, false) : null;
        $raffle_live = $stats && (string)$stats['status'] === 'live' && absint($stats['available']) > 0;
        $can_buy = $has_retail && !$product->is_type('variable') && $product->is_in_stock()
            && ($product_mode === 'retail' || !RaffleLB_Draw_Engine::shop_bridge_is_draw_closed($product));

        /*
         * The dedicated STORE ONLY / RAFFLE ONLY views show one purchase
         * route for every card, chosen by the view itself, unchanged below.
         * The default ALL PRODUCTS view instead reflects each product's own
         * real capability, so a genuine Store Only or Raffle Only card never
         * gets a control for the route it doesn't support.
         */
        $show_buy    = $mode !== 'raffle' && ($mode !== 'both' || $has_retail);
        $show_raffle = $mode !== 'retail' && ($mode !== 'both' || $is_raffle);

        /* Card action layout: dedicated views keep their existing single-mode
           class name unchanged. The default view uses the same 'retail' /
           'raffle' full-width class a card would get in the dedicated view
           whenever it only has one real route, so a solo Buy Now or Enter
           Raffle button spans the action row exactly like it already does
           there; a genuine Store + Raffle card keeps the two-column split. */
        $layout = $mode;
        if ($mode === 'both') {
            $layout = ($show_buy && $show_raffle) ? 'both' : ($show_buy ? 'retail' : 'raffle');
        }

        $showed_buy_action = false;

        echo '<div class="rl-shop-card-actions rl-shop-actions-' . esc_attr($layout) . '">';

            if ($show_buy) {
                if ($can_buy) {
                    if (self::account_required()) {
                        echo '<a class="rl-shop-buy" href="' . esc_url(self::account_login_url(self::current_shop_return_url())) . '"><span class="rl-shop-buy-label">LOGIN TO BUY</span><span class="rl-shop-buy-bag" aria-hidden="true"><svg viewBox="0 0 24 24" focusable="false"><path d="M6.5 8V6.5a5.5 5.5 0 0 1 11 0V8M4.5 8h15l1 13h-17l1-13Z"/></svg></span></a>';
                    } else {
                        echo '<form class="rl-shop-buy-form" method="post" action="' . esc_url($url) . '">';
                            echo '<input type="hidden" name="add-to-cart" value="' . esc_attr($pid) . '">';
                            echo '<input type="hidden" name="quantity" value="1">';
                            if (self::is_raffle_product($product) && $has_retail) echo '<input type="hidden" name="rafflelb_purchase_mode" value="buy_now">';
                            echo '<button type="submit" class="rl-shop-buy"><span class="rl-shop-buy-label">BUY NOW</span><span class="rl-shop-buy-bag" aria-hidden="true"><svg viewBox="0 0 24 24" focusable="false"><path d="M6.5 8V6.5a5.5 5.5 0 0 1 11 0V8M4.5 8h15l1 13h-17l1-13Z"/></svg></span></button>';
                        echo '</form>';
                    }
                } else {
                    echo '<a class="rl-shop-buy" href="' . esc_url($url) . '">VIEW PRODUCT <span>→</span></a>';
                }
                $showed_buy_action = true;
            }

            if ($show_raffle) {
                if ($showed_buy_action) {
                    echo '<span class="rl-shop-card-actions-or" aria-hidden="true">OR</span>';
                }
                if ($raffle_live) {
                    if (self::account_required()) {
                        echo '<a class="rl-shop-enter" href="' . esc_url(self::account_login_url(self::current_shop_return_url())) . '"><span class="rl-shop-enter-label">LOGIN TO ENTER</span><span class="rl-shop-enter-price">ACCOUNT REQUIRED</span></a>';
                    } else {
                        echo '<a class="rl-shop-enter" href="' . esc_url($url) . '#raffle-entry"><span class="rl-shop-enter-label">ENTER RAFFLE</span><span class="rl-shop-enter-price">FROM ' . wp_kses_post(wc_price((float)wc_get_price_to_display($product))) . '</span></a>';
                    }
                } else {
                    echo '<a class="rl-shop-enter is-muted" href="' . esc_url($url) . '">RAFFLE DETAILS <span>VIEW</span></a>';
                }

                // Dedicated RAFFLE ONLY mode also exposes the public status
                // page directly beneath the primary raffle action. The link is
                // public/read-only and does not depend on login state.
                if ($mode === 'raffle') {
                    $selection_url = self::selection_status_url($product);
                    if ($selection_url !== '') {
                        echo '<a class="rl-shop-selection-status" href="' . esc_url($selection_url) . '"><span>VIEW SELECTION STATUS</span><b aria-hidden="true">→</b></a>';
                    }
                }
            }

        echo '</div>';
    }

    public static function shop_enqueue_inter_font() {
        // Retained as a no-op for hook compatibility. Typography is supplied
        // by RaffleLB Design System via var(--rl-font).
    }

    public static function raffle_archive_styles() {
        $is_shop_archive = function_exists('is_shop') && is_shop();
        $is_cat_archive  = function_exists('is_product_category') && is_product_category();
        if (!$is_shop_archive && !$is_cat_archive) return;
        ?>
        <style id="rafflelb-premium-retail-shop-v03311">
        body.rafflelb-raffle-archive,
        body.rafflelb-raffle-archive .main-page-wrapper,
        body.rafflelb-raffle-archive .site-content,
        body.rafflelb-raffle-archive .wd-page-content,
        body.rafflelb-raffle-archive .shop-content-area,
        body.rafflelb-raffle-archive .products-footer{background:#060806!important;color:#f5f7f2!important}
        body.rafflelb-raffle-archive .main-page-wrapper{padding-top:26px!important}
        body.rafflelb-raffle-shop .main-page-wrapper{padding-top:20px!important}
        body.rafflelb-raffle-archive .container,
        body.rafflelb-raffle-archive .wd-content-layout{max-width:1380px!important}
        body.rafflelb-raffle-shop .woocommerce-breadcrumb,
        body.rafflelb-raffle-shop .breadcrumbs,
        body.rafflelb-raffle-shop .yoast-breadcrumb{display:none!important}

        /* Premium retail hero: compact, full-width and clearly e-commerce first. */
        .rl-shop-hero{position:relative;overflow:hidden;margin:0 0 12px;padding:31px 36px 24px;border:1px solid #252c24;border-radius:20px;background:radial-gradient(circle at 88% 0%,rgba(186,255,0,.085),transparent 27%),linear-gradient(135deg,#101510 0%,#090c09 68%,#070907 100%);box-shadow:0 22px 60px rgba(0,0,0,.18)}
        .rl-shop-hero:after{content:"";position:absolute;right:-72px;top:-132px;width:330px;height:330px;border:1px solid rgba(186,255,0,.065);border-radius:50%;pointer-events:none}
        .rl-shop-hero-main{position:relative;z-index:1}
        .rl-shop-eyebrow{display:flex;align-items:center;gap:9px;margin-bottom:9px;color:#baff00;font-size:9px;font-weight:900;letter-spacing:.18em}.rl-shop-eyebrow span{width:6px;height:6px;border-radius:50%;background:#baff00;box-shadow:0 0 12px rgba(186,255,0,.45)}
        .rl-shop-title-row{display:flex;align-items:flex-end;justify-content:space-between;gap:28px}.rl-shop-title-row h1{margin:0 0 8px!important;color:#fff!important;font-size:clamp(40px,4.2vw,56px)!important;line-height:.96!important;font-weight:900!important;letter-spacing:-.045em!important;text-transform:uppercase!important}.rl-shop-title-row p{max-width:650px;margin:0!important;color:#aab4a6!important;font-size:13px!important;line-height:1.55!important}
        .rl-shop-assurances{display:flex;flex-wrap:wrap;justify-content:flex-end;gap:6px;max-width:430px}.rl-shop-assurances span{display:inline-flex;align-items:center;min-height:28px;padding:0 9px;border:1px solid #2b332a;border-radius:999px;background:rgba(7,10,7,.72);color:#cbd3c7;font-size:7px;font-weight:900;letter-spacing:.075em;white-space:nowrap}
        .rl-shop-categories{position:relative;z-index:1;display:flex;gap:7px;margin-top:21px;padding-bottom:1px;overflow-x:auto;scrollbar-width:none}.rl-shop-categories::-webkit-scrollbar{display:none}.rl-shop-category{flex:0 0 auto;display:inline-flex!important;align-items:center;justify-content:center;min-height:36px;padding:0 13px;border:1px solid #30382f;border-radius:999px;background:#090c09;color:#c8d0c4!important;font-size:8px;font-weight:900;letter-spacing:.085em;text-decoration:none!important;transition:.18s ease}.rl-shop-category:hover,.rl-shop-category.is-active{border-color:#baff00;background:#baff00;color:#050705!important}

        /* Hide WoodMart's duplicate archive toolbar. We replace only its presentation;
           filtering and AJAX widgets remain native and untouched below. */
        body.rafflelb-raffle-archive .shop-loop-head,
        body.rafflelb-raffle-archive .wd-shop-tools{display:none!important}
        body.rafflelb-raffle-archive .wd-show-sidebar-btn,
        body.rafflelb-raffle-archive .wd-sidebar-opener,
        body.rafflelb-raffle-archive .wd-products-per-page,
        body.rafflelb-raffle-archive .products-per-page,
        body.rafflelb-raffle-archive .wd-show-products{display:none!important}

        /* RaffleLB presentation layer for native WooCommerce sorting + WoodMart filters. */
        .rl-shop-toolbar{display:flex;align-items:center;justify-content:space-between;gap:18px;margin:0 0 14px;padding:10px 12px 10px 16px;border:1px solid #222a21;border-radius:14px;background:#0a0e0a;box-shadow:0 12px 34px rgba(0,0,0,.12)}
        /* The reported stray edge/line poking past this box's rounded
           border turned out to be a browser sub-pixel rounding artifact
           at certain zoom levels (confirmed: visible at 125%, gone at
           150%, and present on both mobile and desktop) - not a specific
           child's width being wrong. Rather than chase whichever value
           happens to round badly at any given zoom, clip anything that
           overflows this box by even a fraction of a pixel so it can
           never become visible regardless of how the math rounds.
           #rl-shop-controls (an ID selector) is used so this wins
           regardless of the many class-only rules elsewhere in this
           file that also target .rl-shop-toolbar. */
        #rl-shop-controls.rl-shop-toolbar{
            overflow:hidden!important;
            /* The select was right up against (or slightly past) the
               overflow:hidden edge with no breathing room, so clipping
               left it flush against the border instead of leaving a
               visible gap. Extra right padding restores that gap. */
            padding-right:26px!important;
        }
        /* Sidesteps the sort control's rendering quirks entirely: hide it
           whenever a category filter is active (a real category archive
           page, e.g. /product-category/electronics/), show it only on
           the plain shop view with no category chosen. No media query -
           applies identically on desktop and mobile since it's just
           removing the element from the layout. */
        body.rafflelb-category-active #rl-shop-controls .rl-shop-sort{
            display:none!important;
        }
        /* The select never had box-sizing:border-box, so its own padding
           and border were adding ON TOP of its declared width instead of
           being included in it - the actual cause of it running wider
           than intended and clipping its own right corner square instead
           of rounded. Also removes WoodMart's default double up/down sort
           arrows (from its own wd-woo-shop-el-order-by.css) in favor of a
           single clean chevron matching the site's other dropdowns. */
        #rl-shop-controls .rl-shop-sort .woocommerce-ordering select{
            box-sizing:border-box!important;
            max-width:100%!important;
            -webkit-appearance:none!important;
            appearance:none!important;
            background-image:url("data:image/svg+xml;charset=UTF-8,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 20 20' fill='%23baff00'%3E%3Cpath d='M5.5 7.5L10 12l4.5-4.5' stroke='%23baff00' stroke-width='1.6' fill='none' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E")!important;
            background-repeat:no-repeat!important;
            background-position:right 12px center!important;
            background-size:14px!important;
            padding-right:32px!important;
        }
        .rl-shop-product-count{display:flex;align-items:baseline;gap:7px;min-width:0}.rl-shop-product-count strong{color:#fff;font-size:18px;line-height:1;font-weight:900;letter-spacing:-.035em}.rl-shop-product-count span{color:#7f8b7b;font-size:8px;font-weight:900;letter-spacing:.12em}
        .rl-shop-toolbar-actions{display:flex;align-items:center;justify-content:flex-end;gap:9px;min-width:0}
        .rl-shop-filter-toggle{display:inline-flex!important;align-items:center!important;justify-content:center!important;gap:9px!important;min-height:42px!important;margin:0!important;padding:0 15px!important;border:1px solid #354033!important;border-radius:9px!important;background:#101510!important;color:#eef3eb!important;font-size:9px!important;font-weight:900!important;letter-spacing:.08em!important;text-transform:uppercase!important;box-shadow:none!important;cursor:pointer!important;transition:.18s ease!important}.rl-shop-filter-toggle:hover,.rl-shop-filter-toggle[aria-expanded="true"]{border-color:#baff00!important;color:#baff00!important}.rl-shop-filter-toggle b{display:inline-flex;align-items:center;justify-content:center;min-width:18px;height:18px;margin-left:3px;padding:0 5px;border-radius:99px;background:#baff00;color:#050705;font-size:8px}.rl-shop-filter-icon{position:relative;width:14px;height:12px;display:inline-block;border-top:1.5px solid currentColor;border-bottom:1.5px solid currentColor}.rl-shop-filter-icon:after{content:"";position:absolute;left:3px;right:3px;top:4px;border-top:1.5px solid currentColor}
        .rl-shop-sort{display:flex;align-items:center;gap:8px}.rl-shop-sort-label{color:#73806f;font-size:7px;font-weight:900;letter-spacing:.12em}.rl-shop-sort .woocommerce-ordering{width:220px!important;max-width:100%!important;margin:0!important;float:none!important}.rl-shop-sort .woocommerce-ordering select{width:100%!important;height:42px!important;margin:0!important;padding:0 38px 0 12px!important;border:1px solid #2c352b!important;border-radius:9px!important;background-color:#080b08!important;color:#eef2eb!important;font-size:10px!important;font-weight:800!important;box-shadow:none!important}.rl-shop-sort .woocommerce-ordering select option{background:#fff!important;color:#111!important}

        /* Shop filters are collapsed by default and opened by the RaffleLB Filters button.
           The actual widgets, links, query args and WoodMart AJAX behavior remain native. */
        body.rafflelb-raffle-archive .filters-area,
        body.rafflelb-raffle-archive .wd-filters-area,
        body.rafflelb-raffle-archive .shop-filters,
        body.rafflelb-raffle-archive .wd-shop-filters{display:none!important;margin:0 0 16px!important;padding:18px!important;border:1px solid #252e24!important;border-radius:15px!important;background:linear-gradient(145deg,#0e130e,#090d09)!important;box-shadow:0 16px 42px rgba(0,0,0,.14)!important}
        body.rafflelb-raffle-archive.rl-shop-filters-open .filters-area,
        body.rafflelb-raffle-archive.rl-shop-filters-open .wd-filters-area,
        body.rafflelb-raffle-archive.rl-shop-filters-open .shop-filters,
        body.rafflelb-raffle-archive.rl-shop-filters-open .wd-shop-filters{display:block!important}
        body.rafflelb-raffle-archive .filters-area .filters-inner-area,
        body.rafflelb-raffle-archive .wd-filters-area .filters-inner-area,
        body.rafflelb-raffle-archive .shop-filters .widget-area{display:grid!important;grid-template-columns:repeat(2,minmax(0,1fr))!important;gap:22px!important;align-items:start!important}
        body.rafflelb-raffle-archive .filters-area .widget,
        body.rafflelb-raffle-archive .wd-filters-area .widget,
        body.rafflelb-raffle-archive .shop-filters .widget,
        body.rafflelb-raffle-archive .wd-shop-filters .widget{min-width:0!important;margin:0!important;padding:0!important;border:0!important;background:transparent!important;color:#cbd4c7!important}
        body.rafflelb-raffle-archive .filters-area .woodmart-woocommerce-sort-by,
        body.rafflelb-raffle-archive .filters-area .widget_woodmart_sorting,
        body.rafflelb-raffle-archive .wd-filters-area .woodmart-woocommerce-sort-by,
        body.rafflelb-raffle-archive .shop-filters .woodmart-woocommerce-sort-by,
        body.rafflelb-raffle-archive .filters-area [class*="woocommerce-sort-by"],
        body.rafflelb-raffle-archive .wd-filters-area [class*="woocommerce-sort-by"],
        body.rafflelb-raffle-archive .shop-filters [class*="woocommerce-sort-by"]{display:none!important}
        body.rafflelb-raffle-archive .filters-area .widget-title,
        body.rafflelb-raffle-archive .wd-filters-area .widget-title,
        body.rafflelb-raffle-archive .shop-filters .widget-title,
        body.rafflelb-raffle-archive .wd-shop-filters .widget-title{margin:0 0 11px!important;color:#f4f7f2!important;font-size:9px!important;line-height:1.2!important;font-weight:900!important;letter-spacing:.105em!important;text-transform:uppercase!important}
        body.rafflelb-raffle-archive .filters-area ul,
        body.rafflelb-raffle-archive .wd-filters-area ul,
        body.rafflelb-raffle-archive .shop-filters ul{margin:0!important;padding:0!important;list-style:none!important}
        body.rafflelb-raffle-archive .filters-area li,
        body.rafflelb-raffle-archive .wd-filters-area li,
        body.rafflelb-raffle-archive .shop-filters li{margin:0!important;padding:3px 0!important;color:#929d8f!important;font-size:11px!important}
        body.rafflelb-raffle-archive .filters-area a,
        body.rafflelb-raffle-archive .wd-filters-area a,
        body.rafflelb-raffle-archive .shop-filters a{color:#aeb8aa!important;text-decoration:none!important}
        body.rafflelb-raffle-archive .filters-area a:hover,
        body.rafflelb-raffle-archive .filters-area .chosen a,
        body.rafflelb-raffle-archive .wd-filters-area a:hover,
        body.rafflelb-raffle-archive .wd-filters-area .chosen a,
        body.rafflelb-raffle-archive .shop-filters a:hover,
        body.rafflelb-raffle-archive .shop-filters .chosen a{color:#baff00!important}

        /* Category filter as premium compact chips. */
        body.rafflelb-raffle-archive .wd-product-category-filter ul,
        body.rafflelb-raffle-archive .widget_product_categories ul,
        body.rafflelb-raffle-archive .filters-area .product-categories{display:flex!important;flex-wrap:wrap!important;gap:7px!important}
        body.rafflelb-raffle-archive .wd-product-category-filter li,
        body.rafflelb-raffle-archive .widget_product_categories li{padding:0!important}
        body.rafflelb-raffle-archive .wd-product-category-filter a,
        body.rafflelb-raffle-archive .widget_product_categories a{display:inline-flex!important;align-items:center!important;min-height:34px!important;padding:0 11px!important;border:1px solid #2a3329!important;border-radius:999px!important;background:#090d09!important;color:#bdc6b9!important;font-size:9px!important;font-weight:850!important}.wd-product-category-filter a:hover,.widget_product_categories a:hover{border-color:#baff00!important;color:#baff00!important}

        /* Native WooCommerce slider + WoodMart price-link widget, both mapped to retail value by PHP above. */
        body.rafflelb-raffle-archive .widget_price_filter .price_slider_wrapper{padding-top:3px!important}
        body.rafflelb-raffle-archive .widget_price_filter .price_slider{height:5px!important;margin:10px 7px 18px!important;border:0!important;border-radius:999px!important;background:#252d24!important}
        body.rafflelb-raffle-archive .widget_price_filter .ui-slider-range{background:#baff00!important;border-radius:999px!important}
        body.rafflelb-raffle-archive .widget_price_filter .ui-slider-handle{top:50%!important;width:15px!important;height:15px!important;margin-top:-7px!important;border:2px solid #baff00!important;border-radius:50%!important;background:#070907!important;box-shadow:0 0 0 4px rgba(186,255,0,.08)!important}
        body.rafflelb-raffle-archive .widget_price_filter .price_slider_amount{display:flex!important;align-items:center!important;justify-content:space-between!important;gap:10px!important}
        body.rafflelb-raffle-archive .widget_price_filter .price_label{color:#9da897!important;font-size:10px!important;font-weight:800!important}
        body.rafflelb-raffle-archive .widget_price_filter .button{min-height:34px!important;padding:0 12px!important;border:1px solid #baff00!important;border-radius:8px!important;background:#baff00!important;color:#050705!important;font-size:8px!important;font-weight:900!important;letter-spacing:.07em!important;text-transform:uppercase!important}
        body.rafflelb-raffle-archive .woodmart-price-filter ul{display:flex!important;flex-wrap:wrap!important;gap:7px!important}
        body.rafflelb-raffle-archive .woodmart-price-filter li{padding:0!important}
        body.rafflelb-raffle-archive .woodmart-price-filter a{display:inline-flex!important;align-items:center!important;min-height:34px!important;padding:0 11px!important;border:1px solid #2a3329!important;border-radius:999px!important;background:#090d09!important;color:#bdc6b9!important;font-size:9px!important;font-weight:850!important}.woodmart-price-filter a:hover,.woodmart-price-filter .chosen a{border-color:#baff00!important;color:#baff00!important}

        /* Active filter chips. */
        body.rafflelb-raffle-archive .widget_layered_nav_filters ul,
        body.rafflelb-raffle-archive .wd-active-filters{display:flex!important;flex-wrap:wrap!important;gap:7px!important}
        body.rafflelb-raffle-archive .widget_layered_nav_filters a,
        body.rafflelb-raffle-archive .wd-active-filters a{display:inline-flex!important;min-height:31px!important;align-items:center!important;margin:0!important;padding:0 10px!important;border:1px solid #30392f!important;border-radius:999px!important;background:#0d120d!important;color:#d0d8cc!important;font-size:8px!important;font-weight:900!important;letter-spacing:.04em!important}
        body.rafflelb-raffle-archive .widget_layered_nav_filters li:hover,
        body.rafflelb-raffle-archive .wd-active-filters a:hover{border-color:#baff00!important;color:#baff00!important}

        /* Product grid + premium dual-purpose commerce cards. */
        body.rafflelb-raffle-archive .products{display:grid!important;grid-template-columns:repeat(3,minmax(0,1fr))!important;gap:20px!important;margin:0 0 44px!important}.products>.product,.products>.product-grid-item{min-width:0!important;width:auto!important;max-width:none!important;margin:0!important;padding:0!important}
        body.rafflelb-raffle-archive .rl-raffle-card .product-wrapper{position:relative;height:100%!important;display:flex!important;flex-direction:column!important;overflow:hidden!important;padding:10px 10px 14px!important;border:1px solid #252e24!important;border-radius:18px!important;background:linear-gradient(145deg,#0f140f,#090c09)!important;box-shadow:0 20px 55px rgba(0,0,0,.20)!important;transition:transform .22s ease,border-color .22s ease,box-shadow .22s ease!important}.rl-raffle-card .product-wrapper:hover{transform:translateY(-4px)!important;border-color:#3f4a3d!important;box-shadow:0 28px 65px rgba(0,0,0,.30)!important}
        .rl-raffle-card .product-element-top{position:relative!important;overflow:hidden!important;border:1px solid #20281f!important;border-radius:12px!important;background:#050705!important}.rl-raffle-card .product-element-top img{display:block!important;width:100%!important;aspect-ratio:16/9!important;height:auto!important;object-fit:contain!important;object-position:center!important;transform:none!important;transition:transform .28s ease!important}.rl-raffle-card .product-wrapper:hover .product-element-top img{transform:none!important}
        .rl-raffle-card .product-information{display:flex!important;flex-direction:column!important;flex:1 1 auto!important;padding:14px 7px 2px!important;background:transparent!important;text-align:left!important}.rl-raffle-card .wd-entities-title,.rl-raffle-card .product-title,.rl-raffle-card h3{min-height:44px!important;margin:0 0 7px!important;color:#f5f7f3!important;font-size:17px!important;line-height:1.32!important;font-weight:800!important;letter-spacing:-.018em!important;text-align:left!important}.rl-raffle-card .wd-entities-title a,.rl-raffle-card .product-title a,.rl-raffle-card h3 a{color:#f5f7f3!important}.rl-raffle-card .wd-product-cats,.rl-raffle-card .product-categories{margin:0 0 8px!important;color:#768073!important;font-size:9px!important;font-weight:800!important;letter-spacing:.09em!important;text-transform:uppercase!important;text-align:left!important}.rl-raffle-card .wd-product-cats a{color:#768073!important}
        .rl-raffle-card .price{display:block!important;min-height:0!important;margin:7px 0 11px!important}.rl-shop-prices{display:grid;grid-template-columns:1.15fr .85fr;gap:8px}.rl-shop-prices>div{min-width:0;padding:10px 11px;border:1px solid #252e24;border-radius:10px;background:#090d09}.rl-shop-prices small{display:block;margin-bottom:5px;color:#7f8a7b;font-size:7.5px!important;font-weight:900!important;letter-spacing:.11em!important}.rl-shop-prices strong,.rl-shop-prices strong *{color:#fff!important;font-size:22px!important;line-height:1!important;font-weight:900!important;letter-spacing:-.03em!important}.rl-shop-price-raffle{border-color:rgba(186,255,0,.25)!important}.rl-shop-price-raffle strong,.rl-shop-price-raffle strong *{color:#baff00!important;font-size:17px!important}.rl-shop-price-raffle em{margin-left:3px;color:#8f998c;font-size:8px;font-style:normal;font-weight:800;white-space:nowrap}
        .rl-shop-raffle-box{margin:0 0 11px;padding:10px 11px;border:1px solid rgba(186,255,0,.18);border-radius:10px;background:rgba(186,255,0,.025)}.rl-shop-raffle-line,.rl-shop-raffle-meta{display:flex;align-items:center;justify-content:space-between;gap:10px}.rl-shop-raffle-live{display:flex;align-items:center;gap:6px;color:#dce5d8;font-size:8px;font-weight:900;letter-spacing:.08em}.rl-shop-raffle-live i{width:6px;height:6px;border-radius:50%;background:#baff00;box-shadow:0 0 10px rgba(186,255,0,.5)}.rl-shop-raffle-line strong,.rl-shop-raffle-line strong *{color:#baff00!important;font-size:12px!important;font-weight:900!important}.rl-shop-raffle-line strong small{color:#91a08d!important;font-size:7px!important}.rl-shop-raffle-progress{height:5px;margin:8px 0 7px;overflow:hidden;border-radius:99px;background:#242c23}.rl-shop-raffle-progress span{display:block;height:100%;border-radius:inherit;background:#baff00}.rl-shop-raffle-meta{color:#818c7e;font-size:8px;font-weight:800;text-transform:uppercase}.rl-shop-raffle-meta b{color:#c8d1c4;font-weight:900}.rl-shop-raffle-box.is-closed{border-color:#2a3129}.rl-shop-raffle-box.is-closed .rl-shop-raffle-line span,.rl-shop-raffle-box.is-closed .rl-shop-raffle-line strong{color:#899286!important;font-size:8px!important}.rl-shop-store-status{display:flex;align-items:center;justify-content:space-between;gap:10px;margin:0 0 11px;padding:10px 11px;border:1px solid #2a3129;border-radius:10px;background:#0b0f0a}.rl-shop-store-status-badge{color:#c7d0c3;font-size:8px;font-weight:900;letter-spacing:.08em;text-transform:uppercase}.rl-shop-store-status-note{color:#818c7e;font-size:8px;font-weight:800;text-transform:uppercase;text-align:right}
        .rl-shop-card-actions{display:grid;grid-template-columns:1.12fr .88fr;gap:8px;margin-top:auto}.rl-shop-card-actions-or{display:none}.rl-shop-buy-form{margin:0!important}.rl-shop-buy,.rl-shop-enter{display:flex!important;width:100%!important;height:44px!important;align-items:center!important;justify-content:space-between!important;gap:9px!important;margin:0!important;padding:0 13px!important;border-radius:9px!important;text-decoration:none!important;font-size:9px!important;font-weight:900!important;letter-spacing:.07em!important}.rl-shop-buy{border:1px solid #baff00!important;background:#baff00!important;color:#050705!important}.rl-shop-buy span{font-size:15px}.rl-shop-enter{border:1px solid #354033!important;background:#0b0f0b!important;color:#eef3eb!important}.rl-shop-enter span{color:#baff00!important;font-size:8px!important;font-weight:900!important;letter-spacing:0!important}.rl-shop-enter.is-muted span{color:#8c9688!important}.rl-shop-buy:hover{background:#c8ff2a!important;color:#050705!important}.rl-shop-enter:hover{border-color:#baff00!important;color:#fff!important}
        /* Desktop-only: reveal the "OR" between Buy Now / Enter Raffle
           (mobile already shows it via its own 767px rule further down).
           min-width:768px keeps this from ever touching the mobile
           stacked layout. */
        @media(min-width:768px){
            .rl-shop-card-actions{grid-template-columns:1fr auto 1fr!important}
            .rl-shop-card-actions-or{
                display:flex!important;
                align-items:center!important;
                justify-content:center!important;
                padding:0 1px!important;
                color:#6d766a!important;
                font-family:var(--rl-font,"Manrope",sans-serif)!important;
                font-size:9px!important;
                font-weight:800!important;
                letter-spacing:.1em!important;
            }
        }
        .rl-raffle-card .star-rating,.rl-raffle-card .wd-buttons,.rl-raffle-card .wd-add-btn,.rl-raffle-card .add-to-cart-loop,.rl-raffle-card .quick-view,.rl-raffle-card .wd-compare-btn,.rl-raffle-card .wd-wishlist-btn{display:none!important}
        body.rafflelb-raffle-archive .woocommerce-pagination{margin:10px 0 52px!important}body.rafflelb-raffle-archive .woocommerce-pagination a,body.rafflelb-raffle-archive .woocommerce-pagination span{border-color:#2a3329!important;background:#0f140f!important;color:#dbe2d7!important}body.rafflelb-raffle-archive .woocommerce-pagination .current,body.rafflelb-raffle-archive .woocommerce-pagination a:hover{background:#baff00!important;color:#050705!important}

        @media(max-width:1180px){.rl-shop-title-row{align-items:flex-start;flex-direction:column}.rl-shop-assurances{justify-content:flex-start;max-width:none}body.rafflelb-raffle-archive .products{grid-template-columns:repeat(2,minmax(0,1fr))!important}}
        @media(max-width:767px){body.rafflelb-raffle-shop .main-page-wrapper{padding-top:10px!important}.rl-shop-hero{margin:0 0 10px;padding:24px 16px 18px;border-radius:15px}.rl-shop-title-row h1{font-size:38px!important}.rl-shop-title-row p{font-size:12px!important;line-height:1.5!important}.rl-shop-assurances{display:none}.rl-shop-categories{margin-top:17px}.rl-shop-category{min-height:34px;padding:0 11px;font-size:7.5px}.rl-shop-toolbar{align-items:stretch;flex-direction:column;gap:9px;padding:10px;margin-bottom:10px}.rl-shop-product-count{padding:4px 3px 0}.rl-shop-toolbar-actions{display:grid;grid-template-columns:auto minmax(0,1fr);width:100%;gap:8px}.rl-shop-filter-toggle{min-width:112px!important}.rl-shop-sort{min-width:0}.rl-shop-sort-label{display:none}.rl-shop-sort .woocommerce-ordering{width:100%!important}.rl-shop-sort .woocommerce-ordering select{font-size:10px!important}body.rafflelb-raffle-archive .filters-area,body.rafflelb-raffle-archive .wd-filters-area,body.rafflelb-raffle-archive .shop-filters,body.rafflelb-raffle-archive .wd-shop-filters{padding:14px!important;border-radius:13px!important}body.rafflelb-raffle-archive .filters-area .filters-inner-area,body.rafflelb-raffle-archive .wd-filters-area .filters-inner-area,body.rafflelb-raffle-archive .shop-filters .widget-area{grid-template-columns:1fr!important;gap:16px!important}.rl-shop-prices{grid-template-columns:1fr 1fr}.rl-shop-prices strong,.rl-shop-prices strong *{font-size:18px!important}.rl-shop-price-raffle strong,.rl-shop-price-raffle strong *{font-size:15px!important}.rl-shop-card-actions{grid-template-columns:1fr 1fr}body.rafflelb-raffle-archive .products{grid-template-columns:1fr!important;gap:14px!important}.rl-raffle-card .wd-entities-title,.rl-raffle-card .product-title,.rl-raffle-card h3{min-height:0!important;font-size:16px!important}}


        /* v0.33.4 readability pass: the first premium layout was too condensed on real screens. */
        .rl-shop-eyebrow{font-size:11px!important;letter-spacing:.16em!important}
        .rl-shop-title-row p{font-size:15px!important;line-height:1.6!important;color:#bcc5b8!important}
        .rl-shop-assurances span{min-height:32px!important;padding:0 11px!important;font-size:9px!important;letter-spacing:.065em!important}
        .rl-shop-category{min-height:40px!important;padding:0 15px!important;font-size:10px!important;letter-spacing:.07em!important}
        .rl-shop-product-count strong{font-size:24px!important}.rl-shop-product-count span{font-size:10px!important;color:#9aa596!important}
        .rl-shop-filter-toggle{min-height:46px!important;padding:0 17px!important;font-size:11px!important}.rl-shop-filter-toggle b{min-width:20px!important;height:20px!important;font-size:9px!important}
        .rl-shop-sort-label{font-size:9px!important}.rl-shop-sort .woocommerce-ordering{width:240px!important}.rl-shop-sort .woocommerce-ordering select{height:46px!important;font-size:13px!important;padding-left:14px!important}
        body.rafflelb-raffle-archive .filters-area .widget-title,
        body.rafflelb-raffle-archive .wd-filters-area .widget-title,
        body.rafflelb-raffle-archive .shop-filters .widget-title,
        body.rafflelb-raffle-archive .wd-shop-filters .widget-title{font-size:12px!important;letter-spacing:.09em!important;margin-bottom:14px!important}
        body.rafflelb-raffle-archive .filters-area li,
        body.rafflelb-raffle-archive .wd-filters-area li,
        body.rafflelb-raffle-archive .shop-filters li{font-size:13px!important;line-height:1.45!important;padding:5px 0!important}
        body.rafflelb-raffle-archive .filters-area a,
        body.rafflelb-raffle-archive .wd-filters-area a,
        body.rafflelb-raffle-archive .shop-filters a{font-size:13px!important}
        body.rafflelb-raffle-archive .filters-area .product-categories a,
        body.rafflelb-raffle-archive .wd-filters-area .product-categories a,
        body.rafflelb-raffle-archive .shop-filters .product-categories a{min-height:38px!important;padding:0 13px!important;font-size:11px!important}
        .rl-raffle-card .product-information{padding:17px 9px 4px!important}
        .rl-raffle-card .wd-entities-title,.rl-raffle-card .product-title,.rl-raffle-card h3{min-height:52px!important;font-size:20px!important;line-height:1.28!important;font-weight:850!important}
        .rl-raffle-card .wd-product-cats,.rl-raffle-card .product-categories{font-size:11px!important;line-height:1.35!important}
        .rl-shop-prices>div{padding:13px 14px!important}.rl-shop-prices small{font-size:10px!important;margin-bottom:7px!important}.rl-shop-prices strong,.rl-shop-prices strong *{font-size:27px!important}.rl-shop-price-raffle strong,.rl-shop-price-raffle strong *{font-size:22px!important}.rl-shop-price-raffle em{font-size:10px!important}
        .rl-shop-raffle-box{padding:12px 13px!important}.rl-shop-raffle-live{font-size:10px!important}.rl-shop-raffle-line strong,.rl-shop-raffle-line strong *{font-size:15px!important}.rl-shop-raffle-line strong small{font-size:9px!important}.rl-shop-raffle-meta{font-size:10px!important}.rl-shop-store-status{padding:12px 13px!important}.rl-shop-store-status-badge,.rl-shop-store-status-note{font-size:10px!important}
        .rl-shop-buy,.rl-shop-enter{height:48px!important;padding:0 14px!important;font-size:11px!important}.rl-shop-enter span{font-size:9px!important}

        /* Exact retail price inputs layered on top of WooCommerce's native min_price/max_price query args. */
        .rl-price-widget-enhanced>ul,
        .rl-price-widget-enhanced>.woodmart-price-filter,
        .rl-price-widget-enhanced>.wd-price-filter,
        .rl-price-widget-enhanced>.price_slider_wrapper,
        .rl-price-widget-enhanced>form:not(.rl-retail-price-form){display:none!important}
        .rl-retail-price-form{margin:0!important;padding:0!important}
        .rl-price-input-grid{display:grid;grid-template-columns:minmax(0,1fr) auto minmax(0,1fr);align-items:end;gap:10px}
        .rl-price-field{display:block;min-width:0;margin:0!important}.rl-price-field>span{display:block;margin:0 0 7px;color:#98a394;font-size:10px;font-weight:900;letter-spacing:.10em;text-transform:uppercase}
        .rl-price-input-wrap{display:flex;align-items:center;min-height:46px;border:1px solid #303a2f;border-radius:10px;background:#080b08;overflow:hidden;transition:border-color .18s ease,box-shadow .18s ease}.rl-price-input-wrap:focus-within{border-color:#baff00;box-shadow:0 0 0 3px rgba(186,255,0,.08)}
        .rl-price-currency{display:flex;align-items:center;justify-content:center;align-self:stretch;min-width:34px;border-right:1px solid #273026;color:#baff00;font-size:13px;font-weight:900}
        .rl-retail-price-form input[type=number]{width:100%!important;min-width:0!important;height:44px!important;margin:0!important;padding:0 11px!important;border:0!important;background:transparent!important;color:#fff!important;font-size:15px!important;font-weight:850!important;box-shadow:none!important;outline:0!important;-moz-appearance:textfield}.rl-retail-price-form input[type=number]::-webkit-outer-spin-button,.rl-retail-price-form input[type=number]::-webkit-inner-spin-button{-webkit-appearance:none;margin:0}
        .rl-price-separator{padding-bottom:14px;color:#6e796a;font-size:15px;font-weight:900}
        .rl-price-actions{display:flex;align-items:center;gap:8px;margin-top:12px}.rl-price-apply,.rl-price-clear{display:inline-flex!important;min-height:40px!important;align-items:center!important;justify-content:center!important;margin:0!important;padding:0 15px!important;border-radius:9px!important;font-size:10px!important;font-weight:900!important;letter-spacing:.07em!important;text-transform:uppercase!important;text-decoration:none!important;cursor:pointer!important}.rl-price-apply{border:1px solid #baff00!important;background:#baff00!important;color:#050705!important}.rl-price-clear{border:1px solid #303a2f!important;background:#0a0e0a!important;color:#c6cec2!important}.rl-price-clear[hidden]{display:none!important}
        .rl-price-help{margin:10px 0 0;color:#7f8a7b;font-size:11px;line-height:1.45}

        @media(max-width:767px){.rl-shop-title-row p{font-size:14px!important}.rl-shop-category{min-height:38px!important;font-size:9px!important}.rl-shop-product-count strong{font-size:22px!important}.rl-shop-product-count span{font-size:9px!important}.rl-shop-filter-toggle{min-height:44px!important;font-size:10px!important}.rl-shop-sort .woocommerce-ordering select{height:44px!important;font-size:12px!important}.rl-raffle-card .wd-entities-title,.rl-raffle-card .product-title,.rl-raffle-card h3{font-size:18px!important}.rl-shop-prices small{font-size:9px!important}.rl-shop-prices strong,.rl-shop-prices strong *{font-size:22px!important}.rl-shop-price-raffle strong,.rl-shop-price-raffle strong *{font-size:18px!important}.rl-shop-buy,.rl-shop-enter{font-size:10px!important}.rl-price-input-grid{grid-template-columns:1fr 1fr}.rl-price-separator{display:none}.rl-price-field>span{font-size:9px!important}}


        /* v0.33.5 Shop polish: readable categories + compact exact retail-price controls. */
        .rl-shop-category{min-height:42px!important;padding:0 17px!important;font-size:12px!important;line-height:1!important;letter-spacing:.055em!important}
        body.rafflelb-raffle-archive .filters-area .widget-title,
        body.rafflelb-raffle-archive .wd-filters-area .widget-title,
        body.rafflelb-raffle-archive .shop-filters .widget-title,
        body.rafflelb-raffle-archive .wd-shop-filters .widget-title{font-size:14px!important;letter-spacing:.075em!important;margin-bottom:16px!important}
        body.rafflelb-raffle-archive .wd-product-category-filter a,
        body.rafflelb-raffle-archive .widget_product_categories a,
        body.rafflelb-raffle-archive .filters-area .product-categories a,
        body.rafflelb-raffle-archive .wd-filters-area .product-categories a,
        body.rafflelb-raffle-archive .shop-filters .product-categories a{min-height:42px!important;padding:0 16px!important;font-size:13px!important;line-height:1.2!important;font-weight:850!important;letter-spacing:.015em!important}
        .rl-shop-sort .woocommerce-ordering{width:270px!important}.rl-shop-sort .woocommerce-ordering select{font-size:14px!important;font-weight:850!important}

        /* Compact price range: two true numeric fields, one action row, no oversized fake boxes. */
        .rl-retail-price-form{max-width:560px!important}
        .rl-price-row{display:grid!important;grid-template-columns:minmax(125px,1fr) 30px minmax(125px,1fr) auto auto!important;align-items:end!important;gap:9px!important}
        .rl-price-field{display:block!important;min-width:0!important;margin:0!important}
        .rl-price-label{display:block!important;margin:0 0 7px!important;color:#9aa596!important;font-size:11px!important;line-height:1!important;font-weight:900!important;letter-spacing:.10em!important;text-transform:uppercase!important}
        .rl-price-input-wrap{display:flex!important;align-items:center!important;height:44px!important;min-height:44px!important;margin:0!important;border:1px solid #303a2f!important;border-radius:9px!important;background:#080b08!important;overflow:hidden!important;color:inherit!important;text-transform:none!important;letter-spacing:0!important;transition:border-color .18s ease,box-shadow .18s ease!important}
        .rl-price-input-wrap:focus-within{border-color:#baff00!important;box-shadow:0 0 0 3px rgba(186,255,0,.08)!important}
        .rl-price-currency{display:flex!important;align-items:center!important;justify-content:center!important;align-self:stretch!important;flex:0 0 36px!important;min-width:36px!important;margin:0!important;border-right:1px solid #273026!important;color:#baff00!important;font-size:15px!important;line-height:1!important;font-style:normal!important;font-weight:900!important;text-transform:none!important;letter-spacing:0!important}
        .rl-retail-price-form input[type=number]{display:block!important;width:100%!important;min-width:0!important;height:42px!important;margin:0!important;padding:0 12px!important;border:0!important;background:transparent!important;color:#fff!important;font-size:16px!important;line-height:42px!important;font-weight:850!important;text-align:left!important;box-shadow:none!important;outline:0!important}
        .rl-retail-price-form input[type=number]::placeholder{color:#687266!important;opacity:1!important}
        .rl-price-to{display:flex!important;height:44px!important;align-items:center!important;justify-content:center!important;margin:0!important;color:#758071!important;font-size:10px!important;font-weight:900!important;letter-spacing:.08em!important}
        .rl-price-apply,.rl-price-clear{height:44px!important;min-height:44px!important;margin:0!important;padding:0 16px!important;border-radius:9px!important;font-size:11px!important;line-height:1!important;font-weight:900!important;letter-spacing:.06em!important;white-space:nowrap!important}
        .rl-price-apply{border:1px solid #baff00!important;background:#baff00!important;color:#050705!important}.rl-price-apply:hover{background:#c9ff31!important}
        .rl-price-clear{border:1px solid #303a2f!important;background:#0a0e0a!important;color:#bdc7b9!important}.rl-price-clear:hover{border-color:#536050!important;color:#fff!important}
        .rl-price-clear[hidden]{display:none!important}
        .rl-price-input-grid,.rl-price-separator,.rl-price-actions,.rl-price-help{display:none!important}

        @media(max-width:767px){
            .rl-shop-category{min-height:40px!important;padding:0 14px!important;font-size:11px!important}
            body.rafflelb-raffle-archive .wd-product-category-filter a,
            body.rafflelb-raffle-archive .widget_product_categories a,
            body.rafflelb-raffle-archive .filters-area .product-categories a,
            body.rafflelb-raffle-archive .wd-filters-area .product-categories a,
            body.rafflelb-raffle-archive .shop-filters .product-categories a{min-height:40px!important;padding:0 13px!important;font-size:12px!important}
            .rl-price-row{grid-template-columns:1fr 24px 1fr!important;gap:7px!important}
            .rl-price-apply,.rl-price-clear{grid-column:auto!important;margin-top:2px!important}
            .rl-price-apply{grid-column:1 / 3!important}.rl-price-clear{grid-column:3!important}
            .rl-price-label{font-size:10px!important}.rl-price-currency{flex-basis:32px!important;min-width:32px!important;font-size:14px!important}.rl-retail-price-form input[type=number]{font-size:15px!important}
        }



        /* v0.33.8 — scalable category filter for large catalogues. Keep the
           native WooCommerce/WoodMart category links, but show only five in
           compact mode and reveal a searchable grid when MORE is opened. */
        body.rafflelb-raffle-archive .rl-category-widget-enhanced .rl-cat-search-wrap{
            display:none!important;
            position:relative!important;
            margin:0 0 14px!important;
        }
        body.rafflelb-raffle-archive .rl-category-widget-enhanced.rl-cat-expanded .rl-cat-search-wrap{
            display:block!important;
        }
        body.rafflelb-raffle-archive .rl-cat-search{
            display:block!important;
            width:100%!important;
            height:44px!important;
            margin:0!important;
            padding:0 42px 0 14px!important;
            border:1px solid #303a2f!important;
            border-radius:10px!important;
            background:#080b08!important;
            color:#fff!important;
            font-family:var(--rl-font,"Manrope",sans-serif)!important;
            font-size:14px!important;
            font-weight:650!important;
            line-height:44px!important;
            outline:0!important;
            box-shadow:none!important;
        }
        body.rafflelb-raffle-archive .rl-cat-search::placeholder{color:#687266!important;opacity:1!important}
        body.rafflelb-raffle-archive .rl-cat-search:focus{
            border-color:#baff00!important;
            box-shadow:0 0 0 3px rgba(186,255,0,.08)!important;
        }
        body.rafflelb-raffle-archive .rl-cat-search-icon{
            position:absolute!important;
            top:50%!important;
            right:14px!important;
            width:15px!important;
            height:15px!important;
            margin-top:-8px!important;
            border:1.6px solid #8d9989!important;
            border-radius:50%!important;
            pointer-events:none!important;
        }
        body.rafflelb-raffle-archive .rl-cat-search-icon:after{
            content:""!important;
            position:absolute!important;
            right:-5px!important;
            bottom:-3px!important;
            width:7px!important;
            border-top:1.6px solid #8d9989!important;
            transform:rotate(45deg)!important;
            transform-origin:left center!important;
        }
        body.rafflelb-raffle-archive .rl-category-list{
            display:flex!important;
            flex-wrap:wrap!important;
            gap:10px!important;
            align-items:center!important;
            margin:0!important;
            padding:0!important;
        }
        body.rafflelb-raffle-archive .rl-category-list > .rl-cat-item{
            display:block!important;
            flex:0 0 auto!important;
            margin:0!important;
            padding:0!important;
        }
        body.rafflelb-raffle-archive .rl-category-widget-enhanced:not(.rl-cat-expanded) .rl-category-list > .rl-cat-extra{
            display:none!important;
        }
        body.rafflelb-raffle-archive .rl-category-list > .rl-cat-more-item{
            display:block!important;
            flex:0 0 auto!important;
            margin:0!important;
            padding:0!important;
        }
        body.rafflelb-raffle-archive .rl-cat-more{
            display:inline-flex!important;
            align-items:center!important;
            justify-content:center!important;
            min-height:42px!important;
            margin:0!important;
            padding:0 16px!important;
            border:1px solid rgba(186,255,0,.46)!important;
            border-radius:999px!important;
            background:rgba(186,255,0,.055)!important;
            color:#baff00!important;
            font-size:13px!important;
            line-height:1!important;
            font-weight:800!important;
            letter-spacing:.015em!important;
            text-transform:none!important;
            box-shadow:none!important;
            cursor:pointer!important;
            transition:border-color .18s ease,background .18s ease,color .18s ease!important;
        }
        body.rafflelb-raffle-archive .rl-cat-more:hover,
        body.rafflelb-raffle-archive .rl-cat-more:focus{
            border-color:#baff00!important;
            background:#baff00!important;
            color:#050705!important;
            outline:0!important;
        }
        body.rafflelb-raffle-archive .rl-category-widget-enhanced.rl-cat-expanded .rl-category-list{
            display:grid!important;
            grid-template-columns:repeat(3,minmax(0,1fr))!important;
            gap:9px!important;
            align-items:stretch!important;
        }
        body.rafflelb-raffle-archive .rl-category-widget-enhanced.rl-cat-expanded .rl-category-list > .rl-cat-item{
            display:block!important;
            min-width:0!important;
        }
        body.rafflelb-raffle-archive .rl-category-widget-enhanced.rl-cat-expanded .rl-category-list > .rl-cat-item > a{
            display:flex!important;
            width:100%!important;
            min-height:44px!important;
            justify-content:flex-start!important;
            padding:0 14px!important;
            overflow:hidden!important;
            text-overflow:ellipsis!important;
            white-space:nowrap!important;
        }
        body.rafflelb-raffle-archive .rl-category-widget-enhanced.rl-cat-expanded .rl-category-list > .rl-cat-more-item{
            grid-column:1 / -1!important;
            margin-top:3px!important;
        }
        body.rafflelb-raffle-archive .rl-category-widget-enhanced.rl-cat-expanded .rl-cat-more{
            min-width:112px!important;
            border-color:#303a2f!important;
            background:#0a0e0a!important;
            color:#c8d0c4!important;
        }
        body.rafflelb-raffle-archive .rl-category-widget-enhanced .rl-cat-item.rl-parent-current > a{
            border-color:#baff00!important;
            background:rgba(186,255,0,.075)!important;
            color:#fff!important;
        }
        body.rafflelb-raffle-archive .rl-category-widget-enhanced .rl-cat-search-hidden{display:none!important}
        body.rafflelb-raffle-archive .rl-cat-empty{
            display:none;
            margin:12px 0 0!important;
            color:#7f8a7b!important;
            font-size:13px!important;
            line-height:1.45!important;
        }
        body.rafflelb-raffle-archive .rl-cat-empty.is-visible{display:block!important}
        @media(max-width:980px){
            body.rafflelb-raffle-archive .rl-category-widget-enhanced.rl-cat-expanded .rl-category-list{
                grid-template-columns:repeat(2,minmax(0,1fr))!important;
            }
        }
        @media(max-width:560px){
            body.rafflelb-raffle-archive .rl-category-widget-enhanced.rl-cat-expanded .rl-category-list{
                grid-template-columns:1fr!important;
            }
            body.rafflelb-raffle-archive .rl-category-widget-enhanced.rl-cat-expanded .rl-category-list > .rl-cat-more-item{
                grid-column:1!important;
            }
            body.rafflelb-raffle-archive .rl-cat-more{min-height:40px!important;font-size:12px!important}
            body.rafflelb-raffle-archive .rl-cat-search{font-size:16px!important}
        }

        /* v0.33.7 — Inter typography only for Shop filter/sort controls. */
        body.rafflelb-raffle-archive .rl-shop-filter-toggle,
        body.rafflelb-raffle-archive .rl-shop-filter-toggle *,
        body.rafflelb-raffle-archive .rl-shop-sort,
        body.rafflelb-raffle-archive .rl-shop-sort *,
        body.rafflelb-raffle-archive .filters-area,
        body.rafflelb-raffle-archive .filters-area *,
        body.rafflelb-raffle-archive .wd-filters-area,
        body.rafflelb-raffle-archive .wd-filters-area *,
        body.rafflelb-raffle-archive .shop-filters,
        body.rafflelb-raffle-archive .shop-filters *,
        body.rafflelb-raffle-archive .wd-shop-filters,
        body.rafflelb-raffle-archive .wd-shop-filters *{
            font-family:var(--rl-font,"Manrope",sans-serif)!important;
            -webkit-font-smoothing:antialiased;
            text-rendering:optimizeLegibility;
        }

        /* v0.33.9 — professional retail typography system.
           Inter is now the catalogue UI typeface, with calmer weights, larger
           readable labels and less aggressive tracking than the legacy arcade
           display treatment. This is presentation only. */
        body.rafflelb-raffle-archive .rl-shop-hero,
        body.rafflelb-raffle-archive .rl-shop-hero *,
        body.rafflelb-raffle-archive .rl-shop-toolbar,
        body.rafflelb-raffle-archive .rl-shop-toolbar *,
        body.rafflelb-raffle-archive .rl-raffle-card .product-information,
        body.rafflelb-raffle-archive .rl-raffle-card .product-information *{
            font-family:var(--rl-font,"Manrope",sans-serif)!important;
            -webkit-font-smoothing:antialiased!important;
            text-rendering:optimizeLegibility!important;
        }

        /* Hero hierarchy */
        body.rafflelb-raffle-archive .rl-shop-eyebrow{
            margin-bottom:10px!important;
            font-size:11px!important;
            line-height:1.2!important;
            font-weight:750!important;
            letter-spacing:.12em!important;
        }
        body.rafflelb-raffle-archive .rl-shop-title-row h1{
            margin-bottom:10px!important;
            font-family:var(--rl-font,"Manrope",sans-serif)!important;
            font-size:clamp(44px,4.1vw,58px)!important;
            line-height:.98!important;
            font-weight:800!important;
            letter-spacing:-.045em!important;
            text-transform:uppercase!important;
        }
        body.rafflelb-raffle-archive .rl-shop-title-row p{
            max-width:720px!important;
            color:#b7c0b3!important;
            font-size:15px!important;
            line-height:1.55!important;
            font-weight:450!important;
            letter-spacing:-.005em!important;
        }
        body.rafflelb-raffle-archive .rl-shop-assurances span{
            min-height:32px!important;
            padding:0 12px!important;
            font-size:10px!important;
            line-height:1!important;
            font-weight:650!important;
            letter-spacing:.045em!important;
        }

        /* Results / toolbar */
        body.rafflelb-raffle-archive .rl-shop-product-count strong{
            font-size:22px!important;
            font-weight:750!important;
            letter-spacing:-.035em!important;
        }
        body.rafflelb-raffle-archive .rl-shop-product-count span{
            color:#98a494!important;
            font-size:10px!important;
            font-weight:650!important;
            letter-spacing:.08em!important;
        }
        body.rafflelb-raffle-archive .rl-shop-filter-toggle{
            min-height:44px!important;
            padding:0 17px!important;
            border-radius:10px!important;
            font-size:12px!important;
            font-weight:700!important;
            letter-spacing:.025em!important;
        }
        body.rafflelb-raffle-archive .rl-shop-filter-toggle b{
            font-size:10px!important;
            font-weight:750!important;
        }
        body.rafflelb-raffle-archive .rl-shop-sort-label{
            color:#8f9a8c!important;
            font-size:10px!important;
            font-weight:650!important;
            letter-spacing:.055em!important;
        }
        body.rafflelb-raffle-archive .rl-shop-sort .woocommerce-ordering{width:270px!important}
        body.rafflelb-raffle-archive .rl-shop-sort .woocommerce-ordering select{
            height:44px!important;
            padding-left:14px!important;
            font-size:14px!important;
            line-height:1.2!important;
            font-weight:600!important;
            letter-spacing:-.01em!important;
        }

        /* Filter-panel hierarchy */
        body.rafflelb-raffle-archive .filters-area .widget-title,
        body.rafflelb-raffle-archive .wd-filters-area .widget-title,
        body.rafflelb-raffle-archive .shop-filters .widget-title,
        body.rafflelb-raffle-archive .wd-shop-filters .widget-title{
            margin:0 0 17px!important;
            color:#f4f7f2!important;
            font-family:var(--rl-font,"Manrope",sans-serif)!important;
            font-size:15px!important;
            line-height:1.25!important;
            font-weight:700!important;
            letter-spacing:-.01em!important;
            text-transform:none!important;
        }
        body.rafflelb-raffle-archive .wd-product-category-filter a,
        body.rafflelb-raffle-archive .widget_product_categories a,
        body.rafflelb-raffle-archive .filters-area .product-categories a,
        body.rafflelb-raffle-archive .wd-filters-area .product-categories a,
        body.rafflelb-raffle-archive .shop-filters .product-categories a,
        body.rafflelb-raffle-archive .rl-category-list > .rl-cat-item > a{
            min-height:44px!important;
            padding:0 17px!important;
            border-radius:12px!important;
            color:#d8ded5!important;
            font-size:14px!important;
            line-height:1.2!important;
            font-weight:600!important;
            letter-spacing:-.01em!important;
            text-transform:none!important;
        }
        body.rafflelb-raffle-archive .rl-cat-more{
            min-height:44px!important;
            padding:0 17px!important;
            border-radius:12px!important;
            font-size:14px!important;
            line-height:1.2!important;
            font-weight:650!important;
            letter-spacing:-.005em!important;
        }
        body.rafflelb-raffle-archive .rl-cat-search{
            height:46px!important;
            border-radius:11px!important;
            font-size:14px!important;
            line-height:46px!important;
            font-weight:500!important;
            letter-spacing:-.01em!important;
        }

        /* Retail price filter typography */
        body.rafflelb-raffle-archive .rl-retail-price-form .rl-price-label{
            color:#9da89a!important;
            font-size:11px!important;
            line-height:1.2!important;
            font-weight:650!important;
            letter-spacing:.06em!important;
        }
        body.rafflelb-raffle-archive .rl-retail-price-form input[type=number]{
            color:#f5f7f3!important;
            font-size:15px!important;
            line-height:1!important;
            font-weight:600!important;
            letter-spacing:-.015em!important;
        }
        body.rafflelb-raffle-archive .rl-price-currency{
            font-size:14px!important;
            font-weight:700!important;
        }
        body.rafflelb-raffle-archive .rl-price-sep,
        body.rafflelb-raffle-archive .rl-price-separator{
            color:#7f8a7c!important;
            font-size:11px!important;
            font-weight:600!important;
        }
        body.rafflelb-raffle-archive .rl-price-apply,
        body.rafflelb-raffle-archive .rl-price-clear{
            font-size:12px!important;
            font-weight:700!important;
            letter-spacing:.02em!important;
        }

        /* Product-card hierarchy */
        body.rafflelb-raffle-archive .rl-raffle-card .wd-entities-title,
        body.rafflelb-raffle-archive .rl-raffle-card .product-title,
        body.rafflelb-raffle-archive .rl-raffle-card h3{
            min-height:48px!important;
            margin-bottom:8px!important;
            font-size:18px!important;
            line-height:1.32!important;
            font-weight:700!important;
            letter-spacing:-.025em!important;
        }
        body.rafflelb-raffle-archive .rl-raffle-card .wd-product-cats,
        body.rafflelb-raffle-archive .rl-raffle-card .product-categories{
            color:#929e8e!important;
            font-size:11px!important;
            line-height:1.35!important;
            font-weight:600!important;
            letter-spacing:.045em!important;
        }
        body.rafflelb-raffle-archive .rl-shop-prices small{
            color:#909b8d!important;
            font-size:10px!important;
            line-height:1.2!important;
            font-weight:650!important;
            letter-spacing:.045em!important;
        }
        body.rafflelb-raffle-archive .rl-shop-prices strong,
        body.rafflelb-raffle-archive .rl-shop-prices strong *{
            font-size:27px!important;
            font-weight:750!important;
            letter-spacing:-.04em!important;
        }
        body.rafflelb-raffle-archive .rl-shop-price-raffle strong,
        body.rafflelb-raffle-archive .rl-shop-price-raffle strong *{font-size:21px!important}
        body.rafflelb-raffle-archive .rl-shop-price-raffle em{
            font-size:10px!important;
            font-weight:600!important;
        }
        body.rafflelb-raffle-archive .rl-shop-buy,
        body.rafflelb-raffle-archive .rl-shop-enter{
            height:48px!important;
            font-size:12px!important;
            font-weight:700!important;
            letter-spacing:.02em!important;
        }
        body.rafflelb-raffle-archive .rl-shop-enter span{
            font-size:10px!important;
            font-weight:650!important;
        }

        /* v0.33.10 — category chip sizing fix.
           WoodMart may assign fixed/flex widths to category list items. The chip
           must size itself from the rendered Inter label so text can never escape
           the border in compact mode. */
        body.rafflelb-raffle-archive .rl-category-widget-enhanced:not(.rl-cat-expanded) .rl-category-list > .rl-cat-item{
            flex:0 0 auto!important;
            width:auto!important;
            min-width:max-content!important;
            max-width:none!important;
            overflow:visible!important;
        }
        body.rafflelb-raffle-archive .rl-category-widget-enhanced:not(.rl-cat-expanded) .rl-category-list > .rl-cat-item > a{
            display:inline-flex!important;
            flex:0 0 auto!important;
            width:auto!important;
            min-width:max-content!important;
            max-width:none!important;
            box-sizing:border-box!important;
            align-items:center!important;
            justify-content:center!important;
            padding:0 18px!important;
            white-space:nowrap!important;
            overflow:visible!important;
            text-overflow:clip!important;
        }
        body.rafflelb-raffle-archive .rl-category-widget-enhanced:not(.rl-cat-expanded) .rl-category-list > .rl-cat-item > a > *{
            flex:0 0 auto!important;
            width:auto!important;
            max-width:none!important;
            white-space:nowrap!important;
            overflow:visible!important;
        }
        body.rafflelb-raffle-archive .rl-category-list > .rl-cat-more-item{
            width:auto!important;
            min-width:max-content!important;
            max-width:none!important;
            overflow:visible!important;
        }
        body.rafflelb-raffle-archive .rl-cat-more{
            box-sizing:border-box!important;
            white-space:nowrap!important;
        }


        /* v0.33.11 — cleaner retail header + toolbar.
           Removes the product-result counter and gives the Shop/category title
           a calmer editorial lockup instead of the oversized arcade-style heading. */
        body.rafflelb-raffle-archive .rl-shop-toolbar{
            justify-content:flex-end!important;
            min-height:62px!important;
            padding:9px 12px!important;
        }
        body.rafflelb-raffle-archive .rl-shop-toolbar-actions{
            width:100%!important;
            justify-content:flex-end!important;
        }
        body.rafflelb-raffle-archive .rl-shop-product-count{display:none!important}

        body.rafflelb-raffle-archive .rl-shop-hero{
            padding:34px 38px 30px!important;
        }
        body.rafflelb-raffle-archive .rl-shop-eyebrow{
            display:flex!important;
            align-items:center!important;
            gap:8px!important;
            margin:0 0 18px!important;
            color:#8f9a8c!important;
            font-size:10px!important;
            line-height:1!important;
            font-weight:600!important;
            letter-spacing:.085em!important;
        }
        body.rafflelb-raffle-archive .rl-shop-eyebrow span{
            width:6px!important;
            height:6px!important;
            flex:0 0 6px!important;
            background:#baff00!important;
            box-shadow:0 0 12px rgba(186,255,0,.35)!important;
        }
        body.rafflelb-raffle-archive .rl-shop-eyebrow strong{
            color:#eef3eb!important;
            font:inherit!important;
            font-weight:700!important;
            letter-spacing:.075em!important;
        }
        body.rafflelb-raffle-archive .rl-shop-eyebrow em{
            color:#778273!important;
            font:inherit!important;
            font-style:normal!important;
            font-weight:600!important;
            letter-spacing:.075em!important;
        }
        body.rafflelb-raffle-archive .rl-shop-eyebrow em:before{
            content:"/";
            margin-right:8px;
            color:#4f5b4d;
        }
        body.rafflelb-raffle-archive .rl-shop-heading{min-width:0!important}
        body.rafflelb-raffle-archive .rl-shop-title-lockup{
            display:flex!important;
            align-items:center!important;
            gap:16px!important;
            margin:0 0 12px!important;
        }
        body.rafflelb-raffle-archive .rl-shop-title-rule{
            display:block!important;
            width:3px!important;
            height:42px!important;
            flex:0 0 3px!important;
            border-radius:999px!important;
            background:#baff00!important;
            box-shadow:0 0 18px rgba(186,255,0,.18)!important;
        }
        body.rafflelb-raffle-archive .rl-shop-title-row h1{
            margin:0!important;
            color:#f6f8f4!important;
            font-family:var(--rl-font,"Manrope",sans-serif)!important;
            font-size:clamp(40px,3.2vw,50px)!important;
            line-height:1.03!important;
            font-weight:650!important;
            letter-spacing:-.042em!important;
            text-transform:none!important;
        }
        body.rafflelb-raffle-archive .rl-shop-title-row p{
            max-width:680px!important;
            margin:0 0 0 19px!important;
            color:#aeb8aa!important;
            font-size:15px!important;
            line-height:1.58!important;
            font-weight:400!important;
            letter-spacing:-.008em!important;
        }
        body.rafflelb-raffle-archive .rl-shop-assurances{
            align-self:center!important;
        }
        body.rafflelb-raffle-archive .rl-shop-assurances span{
            min-height:34px!important;
            padding:0 13px!important;
            color:#c6cec2!important;
            font-size:10px!important;
            font-weight:600!important;
            letter-spacing:.025em!important;
            text-transform:none!important;
        }

        @media(max-width:767px){
            body.rafflelb-raffle-archive .rl-shop-hero{padding:25px 18px 22px!important}
            body.rafflelb-raffle-archive .rl-shop-eyebrow{margin-bottom:15px!important}
            body.rafflelb-raffle-archive .rl-shop-title-lockup{gap:12px!important;margin-bottom:10px!important}
            body.rafflelb-raffle-archive .rl-shop-title-rule{height:34px!important}
            body.rafflelb-raffle-archive .rl-shop-title-row h1{font-size:36px!important;font-weight:650!important}
            body.rafflelb-raffle-archive .rl-shop-title-row p{margin-left:15px!important;font-size:14px!important}
            body.rafflelb-raffle-archive .rl-shop-toolbar{min-height:56px!important;padding:8px!important}
            body.rafflelb-raffle-archive .rl-shop-title-row p{font-size:14px!important}
            body.rafflelb-raffle-archive .rl-shop-toolbar-actions{grid-template-columns:120px minmax(0,1fr)!important}
            body.rafflelb-raffle-archive .rl-shop-sort .woocommerce-ordering{width:100%!important}
            body.rafflelb-raffle-archive .rl-shop-filter-toggle{font-size:11px!important}
            body.rafflelb-raffle-archive .filters-area .widget-title,
            body.rafflelb-raffle-archive .wd-filters-area .widget-title,
            body.rafflelb-raffle-archive .shop-filters .widget-title,
            body.rafflelb-raffle-archive .wd-shop-filters .widget-title{font-size:14px!important}
            body.rafflelb-raffle-archive .wd-product-category-filter a,
            body.rafflelb-raffle-archive .widget_product_categories a,
            body.rafflelb-raffle-archive .filters-area .product-categories a,
            body.rafflelb-raffle-archive .wd-filters-area .product-categories a,
            body.rafflelb-raffle-archive .shop-filters .product-categories a,
            body.rafflelb-raffle-archive .rl-category-list > .rl-cat-item > a,
            body.rafflelb-raffle-archive .rl-cat-more{font-size:13px!important}
            body.rafflelb-raffle-archive .rl-raffle-card .wd-entities-title,
            body.rafflelb-raffle-archive .rl-raffle-card .product-title,
            body.rafflelb-raffle-archive .rl-raffle-card h3{font-size:17px!important}
        }
        /* v0.33.12 — clear professional Store header and readable catalogue UI.
           Removes the small RAFFLELB / STORE eyebrow, renames the main Shop archive
           to STORE, replaces the vertical title rule with an editorial underline,
           and raises small catalogue text to readable retail sizes. */
        body.rafflelb-raffle-archive .rl-shop-eyebrow{display:none!important}
        body.rafflelb-raffle-archive .rl-shop-hero{
            padding:36px 40px 32px!important;
        }
        body.rafflelb-raffle-archive .rl-shop-title-row{
            align-items:center!important;
            gap:38px!important;
        }
        body.rafflelb-raffle-archive .rl-shop-heading{
            flex:1 1 auto!important;
            min-width:0!important;
        }
        body.rafflelb-raffle-archive .rl-shop-title-lockup{
            display:inline-flex!important;
            flex-direction:column!important;
            align-items:flex-start!important;
            gap:12px!important;
            margin:0 0 16px!important;
        }
        body.rafflelb-raffle-archive .rl-shop-title-row h1{
            margin:0!important;
            color:#f7faf5!important;
            font-family:var(--rl-font,"Manrope",sans-serif)!important;
            font-size:clamp(52px,4.3vw,68px)!important;
            line-height:.95!important;
            font-weight:720!important;
            letter-spacing:-.045em!important;
            text-transform:uppercase!important;
        }
        body.rafflelb-raffle-archive .rl-shop-title-rule{
            display:block!important;
            width:68px!important;
            height:4px!important;
            flex:0 0 4px!important;
            border-radius:999px!important;
            background:#baff00!important;
            box-shadow:none!important;
        }
        body.rafflelb-raffle-archive .rl-shop-title-row p{
            max-width:760px!important;
            margin:0!important;
            color:#bcc5b8!important;
            font-size:16px!important;
            line-height:1.6!important;
            font-weight:450!important;
            letter-spacing:-.008em!important;
        }
        body.rafflelb-raffle-archive .rl-shop-assurances{
            align-self:center!important;
            gap:8px!important;
            max-width:460px!important;
        }
        body.rafflelb-raffle-archive .rl-shop-assurances span{
            min-height:38px!important;
            padding:0 15px!important;
            color:#d7ddd4!important;
            font-size:12px!important;
            line-height:1.15!important;
            font-weight:600!important;
            letter-spacing:.01em!important;
            text-transform:none!important;
        }

        /* Readability floor for all compact Shop controls and product metadata. */
        body.rafflelb-raffle-archive .rl-shop-filter-toggle{
            font-size:13px!important;
            letter-spacing:0!important;
        }
        body.rafflelb-raffle-archive .rl-shop-filter-toggle b{font-size:12px!important}
        body.rafflelb-raffle-archive .rl-shop-clear-filters{
            display:inline-flex!important;
            align-items:center!important;
            justify-content:center!important;
            min-height:46px!important;
            padding:0 16px!important;
            border:1px solid #3a4338!important;
            border-radius:10px!important;
            background:#0b0f0b!important;
            color:#dce3d9!important;
            font-family:var(--rl-font,"Manrope",sans-serif)!important;
            font-size:13px!important;
            line-height:1!important;
            font-weight:700!important;
            letter-spacing:0!important;
            text-decoration:none!important;
            white-space:nowrap!important;
            transition:border-color .18s ease,color .18s ease,background .18s ease!important;
        }
        body.rafflelb-raffle-archive .rl-shop-clear-filters:hover,
        body.rafflelb-raffle-archive .rl-shop-clear-filters:focus{
            border-color:#baff00!important;
            color:#baff00!important;
            background:#101510!important;
        }
        body.rafflelb-raffle-archive .rl-shop-sort-label{
            font-size:12px!important;
            font-weight:600!important;
            letter-spacing:.02em!important;
        }
        body.rafflelb-raffle-archive .rl-shop-sort .woocommerce-ordering select{
            font-size:14px!important;
            font-weight:600!important;
        }
        body.rafflelb-raffle-archive .filters-area .widget-title,
        body.rafflelb-raffle-archive .wd-filters-area .widget-title,
        body.rafflelb-raffle-archive .shop-filters .widget-title,
        body.rafflelb-raffle-archive .wd-shop-filters .widget-title{
            font-size:16px!important;
            font-weight:700!important;
        }
        body.rafflelb-raffle-archive .rl-retail-price-form .rl-price-label{
            font-size:12px!important;
            letter-spacing:.025em!important;
        }
        body.rafflelb-raffle-archive .rl-price-sep,
        body.rafflelb-raffle-archive .rl-price-separator{
            font-size:12px!important;
        }
        body.rafflelb-raffle-archive .rl-price-apply,
        body.rafflelb-raffle-archive .rl-price-clear{
            font-size:13px!important;
            letter-spacing:0!important;
        }
        body.rafflelb-raffle-archive .rl-raffle-card .wd-product-cats,
        body.rafflelb-raffle-archive .rl-raffle-card .product-categories{
            font-size:12px!important;
            letter-spacing:.025em!important;
        }
        body.rafflelb-raffle-archive .rl-shop-prices small{
            font-size:12px!important;
            letter-spacing:.025em!important;
        }
        body.rafflelb-raffle-archive .rl-shop-price-raffle em{
            font-size:12px!important;
        }
        body.rafflelb-raffle-archive .rl-shop-raffle-live,
        body.rafflelb-raffle-archive .rl-shop-raffle-meta,
        body.rafflelb-raffle-archive .rl-shop-raffle-box.is-closed .rl-shop-raffle-line span,
        body.rafflelb-raffle-archive .rl-shop-raffle-box.is-closed .rl-shop-raffle-line strong{
            font-size:12px!important;
            letter-spacing:.02em!important;
        }
        body.rafflelb-raffle-archive .rl-shop-raffle-line strong small{
            font-size:12px!important;
        }
        body.rafflelb-raffle-archive .rl-shop-buy,
        body.rafflelb-raffle-archive .rl-shop-enter{
            font-size:13px!important;
            letter-spacing:0!important;
        }
        body.rafflelb-raffle-archive .rl-shop-enter span{
            font-size:12px!important;
        }

        @media(max-width:767px){
            body.rafflelb-raffle-archive .rl-shop-toolbar-actions:has(.rl-shop-clear-filters){
                display:grid!important;
                grid-template-columns:1fr 1fr!important;
            }
            body.rafflelb-raffle-archive .rl-shop-toolbar-actions:has(.rl-shop-clear-filters) .rl-shop-sort{
                grid-column:1/-1!important;
            }
            body.rafflelb-raffle-archive .rl-shop-clear-filters{
                min-height:44px!important;
                padding:0 12px!important;
                font-size:12px!important;
            }
        }
        @media(max-width:767px){
            body.rafflelb-raffle-archive .rl-shop-hero{padding:28px 20px 24px!important}
            body.rafflelb-raffle-archive .rl-shop-title-row{gap:18px!important}
            body.rafflelb-raffle-archive .rl-shop-title-lockup{gap:10px!important;margin-bottom:14px!important}
            body.rafflelb-raffle-archive .rl-shop-title-row h1{font-size:44px!important;font-weight:720!important}
            body.rafflelb-raffle-archive .rl-shop-title-rule{width:56px!important;height:4px!important}
            body.rafflelb-raffle-archive .rl-shop-title-row p{margin:0!important;font-size:15px!important;line-height:1.55!important}
            body.rafflelb-raffle-archive .rl-shop-filter-toggle{font-size:13px!important}
            body.rafflelb-raffle-archive .filters-area .widget-title,
            body.rafflelb-raffle-archive .wd-filters-area .widget-title,
            body.rafflelb-raffle-archive .shop-filters .widget-title,
            body.rafflelb-raffle-archive .wd-shop-filters .widget-title{font-size:16px!important}
            body.rafflelb-raffle-archive .wd-product-category-filter a,
            body.rafflelb-raffle-archive .widget_product_categories a,
            body.rafflelb-raffle-archive .filters-area .product-categories a,
            body.rafflelb-raffle-archive .wd-filters-area .product-categories a,
            body.rafflelb-raffle-archive .shop-filters .product-categories a,
            body.rafflelb-raffle-archive .rl-category-list > .rl-cat-item > a,
            body.rafflelb-raffle-archive .rl-cat-more{font-size:14px!important}
            body.rafflelb-raffle-archive .rl-shop-prices small,
            body.rafflelb-raffle-archive .rl-shop-price-raffle em,
            body.rafflelb-raffle-archive .rl-shop-raffle-live,
            body.rafflelb-raffle-archive .rl-shop-raffle-meta,
            body.rafflelb-raffle-archive .rl-shop-buy,
            body.rafflelb-raffle-archive .rl-shop-enter,
            body.rafflelb-raffle-archive .rl-shop-enter span{font-size:12px!important}
        }
        /* v0.33.13 — professional Store hero, collision-safe filters and
           contextual nested WooCommerce categories. Presentation only. */
        body.rafflelb-raffle-archive .rl-shop-hero,
        body.rafflelb-raffle-archive .rl-shop-hero *{
            font-family:var(--rl-font,"Manrope",sans-serif)!important;
            -webkit-font-smoothing:antialiased!important;
            text-rendering:optimizeLegibility!important;
        }
        body.rafflelb-raffle-archive .rl-shop-hero{
            padding:34px 40px 30px!important;
            border-radius:18px!important;
        }
        body.rafflelb-raffle-archive .rl-shop-title-row{
            align-items:center!important;
            gap:40px!important;
        }
        body.rafflelb-raffle-archive .rl-shop-title-lockup{
            display:block!important;
            margin:0 0 12px!important;
        }
        body.rafflelb-raffle-archive .rl-shop-title-row h1{
            margin:0!important;
            color:#f7f9f5!important;
            font-family:var(--rl-font,"Manrope",sans-serif)!important;
            font-size:clamp(42px,4vw,54px)!important;
            line-height:1.04!important;
            font-weight:700!important;
            letter-spacing:-.025em!important;
            text-transform:none!important;
        }
        body.rafflelb-raffle-archive .rl-shop-title-row h1::first-letter{text-transform:uppercase!important}
        body.rafflelb-raffle-archive .rl-shop-title-rule{display:none!important}
        body.rafflelb-raffle-archive .rl-shop-title-row p{
            max-width:760px!important;
            margin:0!important;
            color:#b9c2b6!important;
            font-family:var(--rl-font,"Manrope",sans-serif)!important;
            font-size:16px!important;
            line-height:1.55!important;
            font-weight:400!important;
            letter-spacing:0!important;
        }
        body.rafflelb-raffle-archive .rl-shop-assurances{
            display:grid!important;
            grid-template-columns:max-content max-content!important;
            justify-content:end!important;
            align-items:center!important;
            gap:12px 24px!important;
            max-width:520px!important;
        }
        body.rafflelb-raffle-archive .rl-shop-assurances span{
            display:inline-flex!important;
            align-items:center!important;
            min-height:28px!important;
            padding:0!important;
            border:0!important;
            border-radius:0!important;
            background:transparent!important;
            color:#e1e6df!important;
            font-family:var(--rl-font,"Manrope",sans-serif)!important;
            font-size:13px!important;
            line-height:1.3!important;
            font-weight:600!important;
            letter-spacing:0!important;
            text-transform:none!important;
            white-space:nowrap!important;
        }
        body.rafflelb-raffle-archive .rl-shop-assurances span:before{
            content:""!important;
            display:inline-block!important;
            flex:0 0 8px!important;
            width:8px!important;
            height:8px!important;
            margin-right:9px!important;
            border-radius:50%!important;
            background:#baff00!important;
            box-shadow:0 0 0 4px rgba(186,255,0,.08)!important;
        }
        body.rafflelb-raffle-archive .rl-shop-assurances span:last-child{
            grid-column:1 / -1!important;
            justify-self:end!important;
        }

        /* Prevent category search from ever crossing into the price column. */
        body.rafflelb-raffle-archive .filters-area .filters-inner-area,
        body.rafflelb-raffle-archive .wd-filters-area .filters-inner-area,
        body.rafflelb-raffle-archive .shop-filters .widget-area{
            grid-template-columns:minmax(0,1fr) minmax(0,1fr)!important;
            column-gap:34px!important;
            row-gap:24px!important;
        }
        body.rafflelb-raffle-archive .filters-area .widget,
        body.rafflelb-raffle-archive .wd-filters-area .widget,
        body.rafflelb-raffle-archive .shop-filters .widget,
        body.rafflelb-raffle-archive .wd-shop-filters .widget,
        body.rafflelb-raffle-archive .rl-category-widget-enhanced,
        body.rafflelb-raffle-archive .rl-price-widget-enhanced{
            width:100%!important;
            min-width:0!important;
            max-width:100%!important;
            box-sizing:border-box!important;
        }
        body.rafflelb-raffle-archive .rl-cat-search-wrap,
        body.rafflelb-raffle-archive .rl-cat-search{
            width:100%!important;
            min-width:0!important;
            max-width:100%!important;
            box-sizing:border-box!important;
        }
        body.rafflelb-raffle-archive .rl-category-widget-enhanced ul.children{
            display:none!important;
        }

        /* v0.33.20 — the Store retail-price control is persistent and independent
           of WoodMart's native price widget, which may disappear on leaf archives. */
        body.rafflelb-raffle-archive .rl-native-price-source{display:none!important}
        body.rafflelb-raffle-archive .rl-synth-price-widget{
            display:block!important;
            grid-column:auto!important;
        }

        /* v0.33.16 — parent categories and child categories are deliberately
           separated. The native Category widget is parent-only; after a
           parent is selected, its direct children appear in a dedicated,
           full-width Subcategory section below the main filter row. */
        body.rafflelb-raffle-archive .rl-subcategory-widget{
            grid-column:1 / -1!important;
            width:100%!important;
            min-width:0!important;
            max-width:100%!important;
            margin:0!important;
            padding:20px 0 0!important;
            border-top:1px solid #252e24!important;
            box-sizing:border-box!important;
        }
        body.rafflelb-raffle-archive .rl-subcategory-header{
            display:flex!important;
            align-items:baseline!important;
            justify-content:flex-start!important;
            flex-wrap:wrap!important;
            gap:8px 12px!important;
            margin:0 0 13px!important;
        }
        body.rafflelb-raffle-archive .rl-subcategory-heading{
            margin:0!important;
            color:#f4f7f2!important;
            font-family:var(--rl-font,"Manrope",sans-serif)!important;
            font-size:16px!important;
            line-height:1.3!important;
            font-weight:700!important;
            letter-spacing:-.01em!important;
        }
        body.rafflelb-raffle-archive .rl-subcategory-context{
            color:#899486!important;
            font-family:var(--rl-font,"Manrope",sans-serif)!important;
            font-size:13px!important;
            line-height:1.35!important;
            font-weight:600!important;
        }
        body.rafflelb-raffle-archive .rl-subcategory-grid{
            display:grid!important;
            grid-template-columns:repeat(4,minmax(0,1fr))!important;
            gap:10px!important;
        }
        body.rafflelb-raffle-archive .rl-subcategory-link{
            display:flex!important;
            min-width:0!important;
            min-height:46px!important;
            align-items:center!important;
            justify-content:flex-start!important;
            padding:0 15px!important;
            border:1px solid #303a2f!important;
            border-radius:11px!important;
            background:#090d09!important;
            color:#dce2d9!important;
            font-family:var(--rl-font,"Manrope",sans-serif)!important;
            font-size:14px!important;
            line-height:1.25!important;
            font-weight:650!important;
            letter-spacing:-.005em!important;
            text-decoration:none!important;
            overflow:hidden!important;
            text-overflow:ellipsis!important;
            white-space:nowrap!important;
            transition:border-color .18s ease,background .18s ease,color .18s ease!important;
        }
        body.rafflelb-raffle-archive .rl-subcategory-link:hover,
        body.rafflelb-raffle-archive .rl-subcategory-link.is-active{
            border-color:#baff00!important;
            background:rgba(186,255,0,.08)!important;
            color:#fff!important;
        }
        body.rafflelb-raffle-archive .rl-subcategory-link.rl-subcategory-all{
            color:#f4f7f2!important;
            font-weight:700!important;
        }

        /* v0.33.17 — scalable child-category filter. Only Store-backed child
           branches are rendered. More than 15 children collapse behind a
           searchable MORE control, matching the parent-category interaction. */
        body.rafflelb-raffle-archive .rl-subcategory-widget:not(.rl-subcategory-expanded) .rl-subcategory-extra{
            display:none!important;
        }
        body.rafflelb-raffle-archive .rl-subcategory-search-wrap{
            display:none!important;
            position:relative!important;
            width:100%!important;
            max-width:620px!important;
            margin:0 0 14px!important;
        }
        body.rafflelb-raffle-archive .rl-subcategory-widget.rl-subcategory-expanded .rl-subcategory-search-wrap{
            display:block!important;
        }
        body.rafflelb-raffle-archive .rl-subcategory-more{
            display:inline-flex!important;
            align-items:center!important;
            justify-content:center!important;
            min-height:42px!important;
            margin:14px 0 0!important;
            padding:0 16px!important;
            border:1px solid rgba(186,255,0,.46)!important;
            border-radius:999px!important;
            background:rgba(186,255,0,.055)!important;
            color:#baff00!important;
            font-family:var(--rl-font,"Manrope",sans-serif)!important;
            font-size:13px!important;
            line-height:1!important;
            font-weight:750!important;
            letter-spacing:0!important;
            text-transform:none!important;
            box-shadow:none!important;
            cursor:pointer!important;
        }
        body.rafflelb-raffle-archive .rl-subcategory-more:hover,
        body.rafflelb-raffle-archive .rl-subcategory-more:focus{
            border-color:#baff00!important;
            background:#baff00!important;
            color:#050705!important;
            outline:0!important;
        }
        body.rafflelb-raffle-archive .rl-subcategory-search-hidden{display:none!important}
        body.rafflelb-raffle-archive .rl-subcategory-empty{
            display:none!important;
            margin:12px 0 0!important;
            color:#8f998c!important;
            font-family:var(--rl-font,"Manrope",sans-serif)!important;
            font-size:14px!important;
            line-height:1.45!important;
        }
        body.rafflelb-raffle-archive .rl-subcategory-empty.is-visible{display:block!important}

        @media(max-width:980px){
            body.rafflelb-raffle-archive .rl-shop-title-row{align-items:flex-start!important;flex-direction:column!important;gap:24px!important}
            body.rafflelb-raffle-archive .rl-shop-assurances{justify-content:start!important;grid-template-columns:max-content max-content!important}
            body.rafflelb-raffle-archive .rl-shop-assurances span:last-child{justify-self:start!important}
        }
        @media(max-width:767px){
            body.rafflelb-raffle-archive .rl-shop-hero{padding:28px 22px 26px!important}
            body.rafflelb-raffle-archive .rl-shop-title-row h1{font-size:40px!important;line-height:1.06!important}
            body.rafflelb-raffle-archive .rl-shop-title-row p{font-size:16px!important;line-height:1.5!important}
            body.rafflelb-raffle-archive .rl-shop-assurances{display:flex!important;justify-content:flex-start!important;gap:12px 18px!important}
            body.rafflelb-raffle-archive .rl-shop-assurances span{font-size:13px!important;white-space:normal!important}
            body.rafflelb-raffle-archive .filters-area .filters-inner-area,
            body.rafflelb-raffle-archive .wd-filters-area .filters-inner-area,
            body.rafflelb-raffle-archive .shop-filters .widget-area{grid-template-columns:1fr!important;gap:24px!important}
            body.rafflelb-raffle-archive .rl-subcategory-grid{grid-template-columns:repeat(2,minmax(0,1fr))!important}
            body.rafflelb-raffle-archive .rl-subcategory-link{font-size:15px!important}
        }
        @media(max-width:520px){
            body.rafflelb-raffle-archive .rl-subcategory-grid{grid-template-columns:1fr!important}
        }

        /* v0.33.22 — concise professional Store / Raffle view labels.
           The three readable toolbar choices alter the catalogue and card
           presentation without changing the underlying purchase or draw logic. */
        body.rafflelb-raffle-archive .rl-shop-toolbar{
            align-items:center!important;
            justify-content:space-between!important;
            gap:18px!important;
            min-height:66px!important;
            padding:10px 12px!important;
        }
        body.rafflelb-raffle-archive .rl-shop-mode-block{
            display:flex!important;
            flex:1 1 auto!important;
            min-width:0!important;
            align-items:center!important;
            gap:18px!important;
        }
        body.rafflelb-raffle-archive .rl-shop-mode-copy{
            display:flex!important;
            flex:0 0 auto!important;
            min-width:190px!important;
            flex-direction:column!important;
            gap:3px!important;
        }
        body.rafflelb-raffle-archive .rl-shop-mode-copy strong{
            margin:0!important;
            color:#f4f7f2!important;
            font-family:var(--rl-font,"Manrope",sans-serif)!important;
            font-size:17px!important;
            line-height:1.25!important;
            font-weight:700!important;
            letter-spacing:-.01em!important;
        }
        body.rafflelb-raffle-archive .rl-shop-mode-copy span{
            color:rgba(235,241,232,.70)!important;
            font-family:var(--rl-font,"Manrope",sans-serif)!important;
            font-size:14px!important;
            line-height:1.35!important;
            font-weight:450!important;
            letter-spacing:0!important;
        }
        body.rafflelb-raffle-archive .rl-shop-view-modes{
            display:flex!important;
            flex:1 1 auto!important;
            min-width:0!important;
            align-items:center!important;
            gap:8px!important;
        }
        body.rafflelb-raffle-archive .rl-shop-view-mode{
            display:inline-flex!important;
            min-height:46px!important;
            align-items:center!important;
            justify-content:center!important;
            padding:0 17px!important;
            border:1px solid rgba(186,255,0,.72)!important;
            border-radius:10px!important;
            background:rgba(186,255,0,.10)!important;
            color:#dfff91!important;
            font-family:var(--rl-font,"Manrope",sans-serif)!important;
            font-size:14px!important;
            line-height:1.2!important;
            font-weight:700!important;
            letter-spacing:0!important;
            text-align:center!important;
            text-decoration:none!important;
            white-space:nowrap!important;
            box-shadow:inset 0 0 0 1px rgba(186,255,0,.05)!important;
            transition:border-color .18s ease,background .18s ease,color .18s ease,box-shadow .18s ease,transform .18s ease!important;
        }
        body.rafflelb-raffle-archive .rl-shop-view-mode:hover,
        body.rafflelb-raffle-archive .rl-shop-view-mode:focus{
            border-color:#baff00!important;
            background:rgba(186,255,0,.18)!important;
            color:#f5ffd7!important;
            box-shadow:0 0 0 2px rgba(186,255,0,.10)!important;
            outline:0!important;
        }
        body.rafflelb-raffle-archive .rl-shop-view-mode.is-active{
            border-color:#baff00!important;
            background:#baff00!important;
            color:#071006!important;
            box-shadow:0 0 0 1px rgba(186,255,0,.18),0 6px 18px rgba(186,255,0,.10)!important;
        }
        body.rafflelb-raffle-archive .rl-shop-toolbar-actions{
            flex:0 0 auto!important;
            width:auto!important;
            justify-content:flex-end!important;
        }
        body.rafflelb-raffle-archive .rl-shop-prices.is-retail-only,
        body.rafflelb-raffle-archive .rl-shop-prices.is-raffle-only{
            grid-template-columns:1fr!important;
        }
        body.rafflelb-raffle-archive .rl-shop-prices.is-raffle-only .rl-shop-price-raffle{
            width:100%!important;
        }
        body.rafflelb-raffle-archive .rl-shop-actions-retail,
        body.rafflelb-raffle-archive .rl-shop-actions-raffle{
            grid-template-columns:1fr!important;
        }
        body.rafflelb-raffle-archive .rl-shop-prices.is-raffle-only .rl-shop-price-raffle strong,
        body.rafflelb-raffle-archive .rl-shop-prices.is-raffle-only .rl-shop-price-raffle strong *{
            font-size:27px!important;
        }
        body.rafflelb-raffle-archive.rl-shop-view-raffle .rl-shop-actions-raffle .rl-shop-enter:not(.is-muted){
            border-color:#baff00!important;
            background:#baff00!important;
            color:#050705!important;
        }
        body.rafflelb-raffle-archive.rl-shop-view-raffle .rl-shop-actions-raffle .rl-shop-enter:not(.is-muted) span{
            color:#050705!important;
        }

        @media(max-width:1180px){
            body.rafflelb-raffle-archive .rl-shop-toolbar{
                align-items:stretch!important;
                flex-direction:column!important;
            }
            body.rafflelb-raffle-archive .rl-shop-mode-block{
                width:100%!important;
                align-items:flex-start!important;
                flex-direction:column!important;
                gap:10px!important;
            }
            body.rafflelb-raffle-archive .rl-shop-mode-copy{
                min-width:0!important;
                width:100%!important;
            }
            body.rafflelb-raffle-archive .rl-shop-view-modes{
                display:grid!important;
                grid-template-columns:repeat(3,minmax(0,1fr))!important;
                width:100%!important;
            }
            body.rafflelb-raffle-archive .rl-shop-view-mode{
                width:100%!important;
                white-space:normal!important;
                padding:8px 12px!important;
            }
            body.rafflelb-raffle-archive .rl-shop-toolbar-actions{
                width:100%!important;
            }
        }
        /* v0.33.27 — stronger, clearer shopping-mode typography. */
        body.rafflelb-raffle-archive .rl-shop-mode-copy{
            min-width:220px!important;
            gap:5px!important;
        }
        body.rafflelb-raffle-archive .rl-shop-mode-copy strong{
            color:#ffffff!important;
            font-family:var(--rl-font,"Manrope",sans-serif)!important;
            font-size:19px!important;
            line-height:1.2!important;
            font-weight:700!important;
            letter-spacing:-.015em!important;
            text-rendering:optimizeLegibility!important;
            -webkit-font-smoothing:antialiased!important;
        }
        body.rafflelb-raffle-archive .rl-shop-mode-copy span{
            color:rgba(244,247,242,.82)!important;
            font-family:var(--rl-font,"Manrope",sans-serif)!important;
            font-size:15.5px!important;
            line-height:1.35!important;
            font-weight:500!important;
            letter-spacing:0!important;
            text-rendering:optimizeLegibility!important;
            -webkit-font-smoothing:antialiased!important;
        }

        /* v0.33.28 — three-zone desktop toolbar: shopping guidance on the left,
           Store/Raffle mode buttons visually centered, Filters/Sort on the right. */
        @media(min-width:1181px){
            body.rafflelb-raffle-archive .rl-shop-toolbar{
                display:grid!important;
                grid-template-columns:minmax(240px,1fr) auto minmax(360px,1fr)!important;
                align-items:center!important;
                column-gap:24px!important;
            }
            body.rafflelb-raffle-archive .rl-shop-mode-block{
                display:contents!important;
            }
            body.rafflelb-raffle-archive .rl-shop-mode-copy{
                grid-column:1!important;
                justify-self:start!important;
                width:auto!important;
                min-width:0!important;
            }
            body.rafflelb-raffle-archive .rl-shop-view-modes{
                grid-column:2!important;
                justify-self:center!important;
                flex:0 0 auto!important;
                width:auto!important;
            }
            body.rafflelb-raffle-archive .rl-shop-toolbar-actions{
                grid-column:3!important;
                justify-self:end!important;
                width:auto!important;
            }
        }

        /* v0.33.29 — true-centered Store mode controls with collision-safe actions.
           On wide desktop the mode group is centered against the whole toolbar,
           not merely the leftover space. Clear Filters / Filters / Sort are kept
           inside a capped right zone so they can never cover RAFFLE ONLY. */
        @media(min-width:1450px){
            body.rafflelb-raffle-archive .rl-shop-toolbar{
                display:grid!important;
                /* The actions column holds Clear Filters (min-width:112px)
                   + Filters (min-width:110px) + the sort select
                   (min-width:260px) + two 8px gaps - about 498px of rigid
                   content. minmax(0,1fr) let this column shrink smaller
                   than that at some window widths, and the select (unable
                   to compress below its own min-width) would overflow past
                   the column/toolbar border. Guaranteeing it can't shrink
                   below what its content actually needs fixes that. */
                grid-template-columns:minmax(0,1fr) max-content minmax(540px,1fr)!important;
                align-items:center!important;
                column-gap:22px!important;
            }
            body.rafflelb-raffle-archive .rl-shop-mode-block{
                display:contents!important;
            }
            body.rafflelb-raffle-archive .rl-shop-mode-copy{
                grid-column:1!important;
                justify-self:start!important;
                min-width:0!important;
                width:auto!important;
            }
            body.rafflelb-raffle-archive .rl-shop-view-modes{
                grid-column:2!important;
                justify-self:center!important;
                width:max-content!important;
                max-width:none!important;
                flex:0 0 auto!important;
                gap:8px!important;
                position:relative!important;
                z-index:2!important;
            }
            body.rafflelb-raffle-archive .rl-shop-toolbar-actions{
                grid-column:3!important;
                justify-self:end!important;
                display:flex!important;
                flex-wrap:nowrap!important;
                align-items:center!important;
                gap:8px!important;
                min-width:0!important;
                width:auto!important;
                max-width:100%!important;
                position:relative!important;
                z-index:3!important;
            }
            body.rafflelb-raffle-archive .rl-shop-sort-label{display:none!important}
            body.rafflelb-raffle-archive .rl-shop-sort{min-width:0!important;gap:0!important}
            body.rafflelb-raffle-archive .rl-shop-sort .woocommerce-ordering{
                width:260px!important;
                min-width:260px!important;
            }
            body.rafflelb-raffle-archive .rl-shop-clear-filters{
                min-width:112px!important;
                padding-left:13px!important;
                padding-right:13px!important;
            }
            body.rafflelb-raffle-archive .rl-shop-filter-toggle{
                min-width:110px!important;
                padding-left:14px!important;
                padding-right:14px!important;
            }
        }

        /* Medium desktop / tablet landscape: use a deliberate second row for
           the three mode buttons instead of squeezing or overlapping them. */
        @media(min-width:768px) and (max-width:1449px){
            body.rafflelb-raffle-archive .rl-shop-toolbar{
                display:grid!important;
                grid-template-columns:minmax(0,1fr) auto!important;
                grid-template-areas:"copy actions" "modes modes"!important;
                align-items:center!important;
                gap:10px 18px!important;
            }
            body.rafflelb-raffle-archive .rl-shop-mode-block{display:contents!important}
            body.rafflelb-raffle-archive .rl-shop-mode-copy{
                grid-area:copy!important;
                justify-self:start!important;
                min-width:0!important;
                width:auto!important;
            }
            body.rafflelb-raffle-archive .rl-shop-view-modes{
                grid-area:modes!important;
                justify-self:center!important;
                display:flex!important;
                width:max-content!important;
                max-width:100%!important;
                gap:8px!important;
            }
            body.rafflelb-raffle-archive .rl-shop-toolbar-actions{
                grid-area:actions!important;
                justify-self:end!important;
                display:flex!important;
                flex-wrap:nowrap!important;
                width:auto!important;
                gap:8px!important;
            }
            body.rafflelb-raffle-archive .rl-shop-sort-label{display:none!important}
            body.rafflelb-raffle-archive .rl-shop-sort .woocommerce-ordering{width:260px!important}
        }

        @media(max-width:767px){
            body.rafflelb-raffle-archive .rl-shop-mode-copy strong{
                font-size:18px!important;
            }
            body.rafflelb-raffle-archive .rl-shop-mode-copy span{
                font-size:15px!important;
            }
            body.rafflelb-raffle-archive .rl-shop-view-modes{
                grid-template-columns:1fr!important;
                gap:7px!important;
            }
            body.rafflelb-raffle-archive .rl-shop-view-mode{
                min-height:46px!important;
                justify-content:flex-start!important;
                padding:0 14px!important;
                font-size:14px!important;
                text-align:left!important;
            }
            body.rafflelb-raffle-archive .rl-shop-toolbar-actions{
                display:grid!important;
                grid-template-columns:auto minmax(0,1fr)!important;
                gap:8px!important;
            }
            body.rafflelb-raffle-archive .rl-shop-toolbar-actions:has(.rl-shop-clear-filters){
                grid-template-columns:1fr 1fr!important;
            }
            body.rafflelb-raffle-archive .rl-shop-toolbar-actions:has(.rl-shop-clear-filters) .rl-shop-sort{
                grid-column:1/-1!important;
            }
        }

        /* Keep the established stacked controls and hidden dividers below the
           desktop breakpoint. Desktop has one authoritative toolbar layer at
           the end of this stylesheet; keeping this legacy treatment scoped
           prevents it from participating in that desktop cascade. */
        @media(max-width:1180px){
            #rl-shop-controls .rl-shop-toolbar-divider{display:none;}
            #rl-shop-controls.rl-shop-toolbar .rl-shop-mode-block{
                display:flex!important;
                flex-direction:column!important;
                align-items:center!important;
                justify-content:center!important;
                gap:10px!important;
                text-align:center!important;
            }
            #rl-shop-controls .rl-shop-mode-eyebrow{
                display:inline-flex!important;
                align-items:center!important;
                gap:7px!important;
                background:rgba(186,255,0,.10)!important;
                border:1px solid rgba(186,255,0,.45)!important;
                color:#baff00!important;
                font-family:var(--rl-font,"Manrope",sans-serif)!important;
                font-size:11px!important;
                font-weight:800!important;
                letter-spacing:.14em!important;
                text-transform:uppercase!important;
                padding:6px 14px!important;
                border-radius:999px!important;
                white-space:nowrap!important;
                line-height:1!important;
            }
            #rl-shop-controls .rl-shop-mode-eyebrow:before{
                content:""!important;
                width:6px!important;
                height:6px!important;
                flex:0 0 auto!important;
                border-radius:50%!important;
                background:#baff00!important;
                box-shadow:0 0 8px rgba(186,255,0,.75)!important;
            }
            #rl-shop-controls .rl-shop-view-modes{
                flex:0 0 auto!important;
                justify-content:center!important;
            }
        }
        @media(max-width:600px){
            #rl-shop-controls .rl-shop-mode-eyebrow{
                font-size:10px!important;
                padding:5px 12px!important;
            }
        }

        /* v0.33.61 — Raffle Points balance badge, quiet card treatment.
           Fills the toolbar-spacer zone (previously empty, kept only to
           balance the centered mode badge+buttons) with the shopper's own
           Raffle Points balance instead of dead space. Uses a restrained
           dark card instead of a solid lime fill - a personal account stat
           reads as more premium understated than shouted, unlike the
           SHOPPING MODE label or the mode buttons themselves. Logged-in
           only - guests have no balance. */
        #rl-shop-controls .rl-shop-points-badge{
            display:inline-flex!important;
            align-items:center!important;
            gap:10px!important;
            min-height:46px!important;
            background:#0d110d!important;
            border:1px solid #262e25!important;
            color:#e7ece3!important;
            font-family:var(--rl-font,"Manrope",sans-serif)!important;
            text-decoration:none!important;
            padding:0 16px!important;
            border-radius:12px!important;
            white-space:nowrap!important;
            line-height:1!important;
            transition:border-color .15s ease,background .15s ease!important;
        }
        #rl-shop-controls .rl-shop-points-badge:hover{
            border-color:rgba(186,255,0,.5)!important;
            background:#11150f!important;
        }
        #rl-shop-controls .rl-shop-points-icon{
            display:inline-flex!important;
            align-items:center!important;
            justify-content:center!important;
            width:28px!important;
            height:28px!important;
            flex:0 0 auto!important;
            border-radius:8px!important;
            background:#181f17!important;
            border:1px solid #2c3529!important;
        }
        #rl-shop-controls .rl-shop-points-icon img{
            width:18px!important;
            height:18px!important;
            border-radius:4px!important;
        }
        #rl-shop-controls .rl-shop-points-label{
            color:#dbe1d6!important;
            font-size:15px!important;
            font-weight:700!important;
            letter-spacing:-.005em!important;
        }
        #rl-shop-controls .rl-shop-points-value{
            color:#baff00!important;
            font-size:18px!important;
            font-weight:900!important;
            letter-spacing:-.01em!important;
        }
        @media(max-width:1180px){
            #rl-shop-controls .rl-shop-points-badge{display:none!important}
        }

        /* v0.33.61 — smooth shop navigation loading overlay.
           Filter/category/mode links are real page reloads. The new page
           previously rendered at the top and only snapped down to the
           toolbar ~60-360ms later, producing a visible flash on every
           click. This overlay is shown immediately at the top of <body>
           (before the rest of the page renders) whenever a return-to-
           controls navigation is detected, and is only removed once the
           scroll position has already been corrected behind it. */
        #rl-shop-nav-overlay{
            position:fixed!important;
            inset:0!important;
            z-index:999999!important;
            display:none;
            align-items:center!important;
            justify-content:center!important;
            background:#050805!important;
            opacity:1;
            transition:opacity .25s ease!important;
        }
        #rl-shop-nav-overlay.is-active{
            display:flex!important;
        }
        #rl-shop-nav-overlay.is-hiding{
            opacity:0!important;
            pointer-events:none!important;
        }
        #rl-shop-nav-overlay .rl-shop-nav-overlay-inner{
            display:flex!important;
            flex-direction:column!important;
            align-items:center!important;
            gap:16px!important;
        }
        #rl-shop-nav-overlay .rl-shop-nav-overlay-ring{
            position:relative!important;
            width:76px!important;
            height:76px!important;
            display:flex!important;
            align-items:center!important;
            justify-content:center!important;
        }
        #rl-shop-nav-overlay .rl-shop-nav-overlay-ring:before{
            content:""!important;
            position:absolute!important;
            inset:0!important;
            border-radius:50%!important;
            border:3px solid rgba(186,255,0,.18)!important;
            border-top-color:#baff00!important;
            animation:rlShopNavSpin .8s linear infinite!important;
        }
        #rl-shop-nav-overlay img{
            width:52px!important;
            height:52px!important;
            border-radius:14px!important;
            animation:rlShopNavPulse 1.1s ease-in-out infinite!important;
        }
        #rl-shop-nav-overlay .rl-shop-nav-overlay-text{
            color:rgba(240,245,237,.72)!important;
            font-family:var(--rl-font,"Manrope",sans-serif)!important;
            font-size:12px!important;
            font-weight:700!important;
            letter-spacing:.14em!important;
            text-transform:uppercase!important;
        }
        @keyframes rlShopNavSpin{ to{ transform:rotate(360deg); } }
        @keyframes rlShopNavPulse{ 0%,100%{ transform:scale(1); } 50%{ transform:scale(1.08); } }

        /* v0.33.61 — mobile-only Store polish: legible Sort control and a
           denser two-column product grid. Scoped entirely to
           max-width:767px - desktop/tablet layouts above that width are
           untouched. The Raffle Points badge is deliberately kept hidden
           on mobile (desktop-only), same as before this whole pass. */
        @media(max-width:767px){
            #rl-shop-controls .rl-shop-toolbar-spacer{
                display:none!important;
            }

            /* Sort control: force clear, legible contrast and a properly
               sized custom arrow instead of relying on whatever the browser
               renders in the leftover space next to Filters. */
            body.rafflelb-raffle-archive .rl-shop-toolbar-actions{
                align-items:stretch!important;
            }
            body.rafflelb-raffle-archive .rl-shop-sort{
                display:block!important;
            }
            body.rafflelb-raffle-archive .rl-shop-sort .woocommerce-ordering{
                display:block!important;
                width:100%!important;
            }
            body.rafflelb-raffle-archive .rl-shop-sort .woocommerce-ordering select{
                display:block!important;
                width:100%!important;
                height:44px!important;
                padding:0 30px 0 12px!important;
                border:1px solid #2c352b!important;
                border-radius:9px!important;
                background-color:#080b08!important;
                background-image:url("data:image/svg+xml;charset=UTF-8,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 20 20' fill='%23baff00'%3E%3Cpath d='M5.5 7.5L10 12l4.5-4.5' stroke='%23baff00' stroke-width='1.6' fill='none' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E")!important;
                background-repeat:no-repeat!important;
                background-position:right 10px center!important;
                background-size:16px!important;
                -webkit-appearance:none!important;
                appearance:none!important;
                color:#eef2eb!important;
                font-size:12px!important;
                font-weight:700!important;
                line-height:44px!important;
                text-overflow:ellipsis!important;
                white-space:nowrap!important;
                overflow:hidden!important;
            }
            body.rafflelb-raffle-archive .rl-shop-sort .woocommerce-ordering select option{
                background:#0d110d!important;
                color:#eef2eb!important;
            }

            /* Category filter chips: the collapsed-state list sizes each
               chip to fit its own label (deliberate for desktop's flowing
               tag look), which reads as a ragged, uneven grid on a narrow
               phone screen. Force a clean two-column grid with full-width,
               equally sized chips instead. */
            body.rafflelb-raffle-archive .rl-category-widget-enhanced:not(.rl-cat-expanded) .rl-category-list{
                display:grid!important;
                grid-template-columns:repeat(2,minmax(0,1fr))!important;
                gap:8px!important;
            }
            body.rafflelb-raffle-archive .rl-category-widget-enhanced:not(.rl-cat-expanded) .rl-category-list > .rl-cat-item,
            body.rafflelb-raffle-archive .rl-category-widget-enhanced:not(.rl-cat-expanded) .rl-category-list > .rl-cat-more-item{
                width:100%!important;
                min-width:0!important;
            }
            body.rafflelb-raffle-archive .rl-category-widget-enhanced:not(.rl-cat-expanded) .rl-category-list > .rl-cat-item > a{
                width:100%!important;
                min-width:0!important;
                padding:0 10px!important;
                white-space:nowrap!important;
                overflow:hidden!important;
                text-overflow:ellipsis!important;
            }
            body.rafflelb-raffle-archive .rl-cat-more{
                width:100%!important;
            }

            /* Denser two-column product grid instead of one huge full-width
               card per row. */
            body.rafflelb-raffle-archive .products{
                grid-template-columns:repeat(2,minmax(0,1fr))!important;
                gap:10px!important;
            }
            body.rafflelb-raffle-archive .rl-raffle-card .product-wrapper{
                padding:7px 7px 10px!important;
                border-radius:14px!important;
            }
            body.rafflelb-raffle-archive .rl-raffle-card .product-information{
                padding:9px 3px 0!important;
            }
            body.rafflelb-raffle-archive .rl-raffle-card .wd-entities-title,
            body.rafflelb-raffle-archive .rl-raffle-card .product-title,
            body.rafflelb-raffle-archive .rl-raffle-card h3{
                min-height:0!important;
                margin:0 0 5px!important;
                font-size:12.5px!important;
                line-height:1.25!important;
            }
            body.rafflelb-raffle-archive .rl-raffle-card .wd-product-cats,
            body.rafflelb-raffle-archive .rl-raffle-card .product-categories{
                margin:0 0 6px!important;
                font-size:7.5px!important;
            }
            body.rafflelb-raffle-archive .rl-shop-prices{
                grid-template-columns:1fr!important;
                gap:5px!important;
            }
            body.rafflelb-raffle-archive .rl-shop-prices>div{
                padding:6px 8px!important;
            }
            body.rafflelb-raffle-archive .rl-shop-prices small{
                font-size:7px!important;
                margin-bottom:2px!important;
            }
            body.rafflelb-raffle-archive .rl-shop-prices strong,
            body.rafflelb-raffle-archive .rl-shop-prices strong *{
                font-size:14px!important;
            }
            body.rafflelb-raffle-archive .rl-shop-price-raffle strong,
            body.rafflelb-raffle-archive .rl-shop-price-raffle strong *{
                font-size:12.5px!important;
            }
            body.rafflelb-raffle-archive .rl-shop-card-actions{
                grid-template-columns:1fr!important;
                gap:6px!important;
            }
            body.rafflelb-raffle-archive .rl-shop-card-actions-or{
                display:block!important;
                margin:1px 0!important;
                text-align:center!important;
                color:#6d766a!important;
                font-family:var(--rl-font,"Manrope",sans-serif)!important;
                font-size:8px!important;
                font-weight:800!important;
                letter-spacing:.12em!important;
            }
            body.rafflelb-raffle-archive .rl-shop-buy,
            body.rafflelb-raffle-archive .rl-shop-enter{
                height:36px!important;
                font-size:9px!important;
                white-space:nowrap!important;
            }
            body.rafflelb-raffle-archive .rl-shop-buy span,
            body.rafflelb-raffle-archive .rl-shop-enter span{
                white-space:nowrap!important;
            }

            /* Raffle-availability box: stack the "RAFFLE AVAILABLE" label
               above the entry price instead of side-by-side, which was
               colliding/wrapping in the narrower two-column card. Also
               brings the closed-raffle variant ("RAFFLE CLOSED / DIRECT
               SHOPPING VIEW") to the same stacked treatment so every card
               reads consistently regardless of its raffle status. */
            body.rafflelb-raffle-archive .rl-shop-raffle-box{
                padding:8px 9px!important;
                margin:0 0 8px!important;
            }
            body.rafflelb-raffle-archive .rl-shop-raffle-line{
                flex-direction:column!important;
                align-items:flex-start!important;
                gap:2px!important;
            }
            body.rafflelb-raffle-archive .rl-shop-raffle-live{
                font-size:8px!important;
                letter-spacing:.05em!important;
                white-space:nowrap!important;
            }
            body.rafflelb-raffle-archive .rl-shop-raffle-line strong,
            body.rafflelb-raffle-archive .rl-shop-raffle-line strong *{
                font-size:14px!important;
            }
            body.rafflelb-raffle-archive .rl-shop-raffle-line strong small{
                font-size:8px!important;
            }
            body.rafflelb-raffle-archive .rl-shop-raffle-meta{
                flex-wrap:nowrap!important;
                gap:6px!important;
                font-size:7.5px!important;
            }
            body.rafflelb-raffle-archive .rl-shop-raffle-meta span,
            body.rafflelb-raffle-archive .rl-shop-raffle-meta b{
                white-space:nowrap!important;
                overflow:hidden!important;
                text-overflow:ellipsis!important;
            }
            body.rafflelb-raffle-archive .rl-shop-raffle-box.is-closed{
                padding:12px 9px!important;
            }
            body.rafflelb-raffle-archive .rl-shop-raffle-box.is-closed .rl-shop-raffle-line{
                gap:4px!important;
            }
            body.rafflelb-raffle-archive .rl-shop-raffle-box.is-closed .rl-shop-raffle-line span,
            body.rafflelb-raffle-archive .rl-shop-raffle-box.is-closed .rl-shop-raffle-line strong{
                font-size:9px!important;
                white-space:nowrap!important;
            }
        }
        </style>
        <?php
    }

    public static function shop_navigation_loading_overlay() {
        if (!self::shop_query_is_catalog()) return;

        $icon = plugins_url('assets/rafflelb-site-icon.png', __FILE__);
        $return_key = 'rafflelb_shop_return_v03324';

        echo '<div id="rl-shop-nav-overlay" aria-hidden="true">';
            echo '<div class="rl-shop-nav-overlay-inner">';
                echo '<div class="rl-shop-nav-overlay-ring"><img src="' . esc_url($icon) . '" alt=""></div>';
                echo '<span class="rl-shop-nav-overlay-text">Loading</span>';
            echo '</div>';
        echo '</div>';
        ?>
        <script>
        (function(){
            try {
                var isReturn = window.location.hash === '#rl-shop-controls';
                if (!isReturn) {
                    try {
                        var raw = window.sessionStorage.getItem(<?php echo wp_json_encode($return_key); ?>);
                        if (raw) {
                            var data = JSON.parse(raw);
                            if (data && data.ts && (Date.now() - Number(data.ts)) <= 15000) isReturn = true;
                        }
                    } catch (e) {}
                }
                if (!isReturn) {
                    // Arriving here via a normal link from a non-shop page
                    // (e.g. a category card on the homepage) sets this
                    // general navigation flag instead - recognize it too,
                    // so the overlay covers this page's own initial-render
                    // glitches the same way it already does for in-shop
                    // filter navigation, rather than leaving them exposed
                    // on every entry from outside the shop.
                    try {
                        var rawNav = window.sessionStorage.getItem('rafflelb_nav_loading_v1');
                        if (rawNav) {
                            window.sessionStorage.removeItem('rafflelb_nav_loading_v1');
                            var navData = JSON.parse(rawNav);
                            if (navData && navData.ts && (Date.now() - Number(navData.ts)) <= 8000) isReturn = true;
                        }
                    } catch (e) {}
                }
                if (isReturn) {
                    var el = document.getElementById('rl-shop-nav-overlay');
                    if (el) {
                        el.classList.add('is-active');
                        // Self-contained safety net: this overlay must never get
                        // stuck. It does not depend on any other script on the
                        // page succeeding - if the later filter-UI script fails
                        // to run/hide it for any reason, force it away here.
                        window.setTimeout(function(){
                            el.classList.remove('is-active', 'is-hiding');
                        }, 2500);
                    }
                }
            } catch (e) {}
        })();
        </script>
        <?php
    }

    public static function shop_native_filters_ui() {
        if (!self::shop_query_is_catalog()) return;

        // Build a small, read-only taxonomy map for the presentation layer.
        // Native WooCommerce category URLs remain the source of truth; this
        // data only lets the filter UI reveal child categories contextually.
        $rl_category_terms = [];
        if (taxonomy_exists('product_cat')) {
            $terms = get_terms([
                'taxonomy'   => 'product_cat',
                'hide_empty' => false,
                'orderby'    => 'name',
                'order'      => 'ASC',
            ]);
            if (!is_wp_error($terms)) {
                /* v0.33.17 — determine which taxonomy branches actually contain
                   products that qualify for the public STORE. WooCommerce term
                   counts can include raffle-only products, so they are not enough
                   for the Store category/subcategory filters. */
                $rl_store_term_has_products = [];
                $rl_shop_mode = self::shop_view_mode();
                global $wpdb;

                if ($rl_shop_mode === 'raffle') {
                    $store_rows = $wpdb->get_results($wpdb->prepare(
                        "SELECT DISTINCT tt.term_id
                         FROM {$wpdb->posts} p
                         INNER JOIN {$wpdb->term_relationships} tr ON tr.object_id = p.ID
                         INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id AND tt.taxonomy = 'product_cat'
                         INNER JOIN {$wpdb->postmeta} raffle_enabled ON raffle_enabled.post_id = p.ID AND raffle_enabled.meta_key = %s AND raffle_enabled.meta_value = 'yes'
                         WHERE p.post_type = 'product' AND p.post_status = 'publish'",
                        \RaffleLB\Core\Contracts::META_ENABLED
                    ));
                } elseif ($rl_shop_mode === 'retail') {
                    /* The Store category map is reused briefly because it is
                     * identical for every Store archive/category page. Unlike
                     * the prior three-way postmeta join, the correlated checks
                     * use the post_id/meta_key index and only cast a matching
                     * Buy Direct price row. */
                    $cache_key = 'rafflelb_shop_store_categories_v074';
                    $cached_ids = get_transient($cache_key);
                    if ($cached_ids === false) {
                        $enabled_key = \RaffleLB\Core\Contracts::META_ENABLED;
                        $buy_enabled_key = \RaffleLB\Core\Contracts::META_BUY_NOW_ENABLED;
                        $buy_price_key = \RaffleLB\Core\Contracts::META_BUY_NOW_PRICE;
                        $status_key = \RaffleLB\Core\Contracts::META_DRAW_STATUS;
                        $store_rows = $wpdb->get_col($wpdb->prepare(
                            "SELECT DISTINCT tt.term_id
                             FROM {$wpdb->posts} p
                             INNER JOIN {$wpdb->term_relationships} tr ON tr.object_id = p.ID
                             INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id AND tt.taxonomy = 'product_cat'
                             WHERE p.post_type = 'product' AND p.post_status = 'publish'
                               AND (
                                   NOT EXISTS (SELECT 1 FROM {$wpdb->postmeta} re WHERE re.post_id = p.ID AND re.meta_key = %s AND re.meta_value = 'yes')
                                   OR (
                                       EXISTS (SELECT 1 FROM {$wpdb->postmeta} be WHERE be.post_id = p.ID AND be.meta_key = %s AND be.meta_value = 'yes')
                                       AND EXISTS (SELECT 1 FROM {$wpdb->postmeta} bp WHERE bp.post_id = p.ID AND bp.meta_key = %s AND CAST(bp.meta_value AS DECIMAL(18,4)) > 0)
                                   )
                               )
                               AND (
                                   NOT EXISTS (SELECT 1 FROM {$wpdb->postmeta} re_winner WHERE re_winner.post_id = p.ID AND re_winner.meta_key = %s AND re_winner.meta_value = 'yes')
                                   OR NOT EXISTS (SELECT 1 FROM {$wpdb->postmeta} winner WHERE winner.post_id = p.ID AND winner.meta_key = %s AND winner.meta_value = 'winner_selected')
                               )",
                            $enabled_key,
                            $buy_enabled_key,
                            $buy_price_key,
                            $enabled_key,
                            $status_key
                        ));
                        $cached_ids = array_values(array_unique(array_map('absint', (array) $store_rows)));
                        set_transient($cache_key, $cached_ids, MINUTE_IN_SECONDS);
                    }
                    $store_rows = [];
                    foreach ((array) $cached_ids as $term_id) {
                        $store_rows[] = (object) ['term_id' => (int) $term_id];
                    }
                } else {
                    /* Default ALL PRODUCTS view: a category has eligible
                     * products if it contains anything except a winner-
                     * selected raffle, i.e. exactly the catalogue eligibility
                     * shop_catalog_scope() applies for this mode. Cached like
                     * the Store map above since it also scans every category. */
                    $cache_key = 'rafflelb_shop_all_categories_v1';
                    $cached_ids = get_transient($cache_key);
                    if ($cached_ids === false) {
                        $enabled_key = \RaffleLB\Core\Contracts::META_ENABLED;
                        $status_key = \RaffleLB\Core\Contracts::META_DRAW_STATUS;
                        $all_rows = $wpdb->get_col($wpdb->prepare(
                            "SELECT DISTINCT tt.term_id
                             FROM {$wpdb->posts} p
                             INNER JOIN {$wpdb->term_relationships} tr ON tr.object_id = p.ID
                             INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id AND tt.taxonomy = 'product_cat'
                             WHERE p.post_type = 'product' AND p.post_status = 'publish'
                               AND (
                                   NOT EXISTS (SELECT 1 FROM {$wpdb->postmeta} re WHERE re.post_id = p.ID AND re.meta_key = %s AND re.meta_value = 'yes')
                                   OR NOT EXISTS (SELECT 1 FROM {$wpdb->postmeta} winner WHERE winner.post_id = p.ID AND winner.meta_key = %s AND winner.meta_value = 'winner_selected')
                               )",
                            $enabled_key,
                            $status_key
                        ));
                        $cached_ids = array_values(array_unique(array_map('absint', (array) $all_rows)));
                        set_transient($cache_key, $cached_ids, MINUTE_IN_SECONDS);
                    }
                    $store_rows = [];
                    foreach ((array) $cached_ids as $term_id) {
                        $store_rows[] = (object) ['term_id' => (int) $term_id];
                    }
                }
                if (is_array($store_rows)) {
                    foreach ($store_rows as $row) {
                        $rl_store_term_has_products[(int) $row->term_id] = true;
                    }
                }

                // Roll visibility upward. An empty parent remains visible when a
                // child (or deeper descendant) contains a Store product.
                $rl_term_parent_map = [];
                foreach ($terms as $term) {
                    $rl_term_parent_map[(int) $term->term_id] = (int) $term->parent;
                }
                foreach (array_keys($rl_store_term_has_products) as $term_id) {
                    $cursor = (int) $term_id;
                    $guard = 0;
                    while (!empty($rl_term_parent_map[$cursor]) && $guard < 30) {
                        $cursor = (int) $rl_term_parent_map[$cursor];
                        $rl_store_term_has_products[$cursor] = true;
                        $guard++;
                    }
                }

                foreach ($terms as $term) {
                    $url = get_term_link($term);
                    if (is_wp_error($url)) continue;
                    $rl_category_terms[] = [
                        'id'                 => (int) $term->term_id,
                        'parent'             => (int) $term->parent,
                        'name'               => html_entity_decode((string) $term->name, ENT_QUOTES | ENT_HTML5, get_bloginfo('charset') ?: 'UTF-8'),
                        'slug'               => (string) $term->slug,
                        'url'                => (string) $url,
                        'count'              => (int) $term->count,
                        'store_has_products' => !empty($rl_store_term_has_products[(int) $term->term_id]),
                    ];
                }
            }
        }
        $rl_current_category_id = 0;
        if (function_exists('is_product_category') && is_product_category()) {
            $current = get_queried_object();
            if ($current instanceof WP_Term && $current->taxonomy === 'product_cat') {
                $rl_current_category_id = (int) $current->term_id;
            }
        }
        $rl_shop_url = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : home_url('/shop/');
        if (!$rl_shop_url) $rl_shop_url = home_url('/shop/');
        $rl_shop_mode = self::shop_view_mode();

        /* v0.2.35 — Brands live inside the same filter UI that already builds
         * Categories/Subcategories. Draw Engine delegates this method to Shop
         * on wp_footer, so this is the one filter-rendering path we know is
         * actually present on the live Store. Build the current category's
         * brand list server-side and hand it to that existing UI instead of
         * relying on woocommerce_before_shop_loop, which WoodMart does not
         * render in this customised archive layout.
         *
         * v0.2.36 — category_brand_terms() picks its own taxonomy from
         * evidence on the eligible products (see brand_taxonomy_candidates()
         * for why), so it is no longer told which one to use.
         *
         * v0.2.37 — this server-computed list now only seeds the client-side
         * cache for the category active at THIS request. Every other
         * category the shopper reaches through WoodMart's in-page AJAX shop
         * is fetched fresh — see rafflelb-shop-native-filters-ui-v03324's
         * setupBrandFilter()/fetchCategoryBrands() and
         * ajax_category_brands() below. */
        $rl_brand_terms = [];
        if ($rl_current_category_id > 0) {
            $rl_brand_result = self::category_brand_terms($rl_current_category_id, $rl_shop_mode);
            $rl_brand_terms = $rl_brand_result['brands'];
        }
        ?>
        <style id="rafflelb-brand-filter-css-v0235">
        body.rafflelb-raffle-archive .rl-brand-filter-widget{
            grid-column:1 / -1!important;
            width:100%!important;
            min-width:0!important;
            max-width:100%!important;
            margin:0!important;
            padding:20px 0 0!important;
            border-top:1px solid #252e24!important;
            box-sizing:border-box!important;
        }
        body.rafflelb-raffle-archive .rl-brand-filter-head{
            display:flex!important;
            align-items:baseline!important;
            justify-content:flex-start!important;
            flex-wrap:wrap!important;
            gap:8px 12px!important;
            margin:0 0 13px!important;
        }
        body.rafflelb-raffle-archive .rl-brand-filter-heading{
            margin:0!important;
            color:#f4f7f2!important;
            font-family:var(--rl-font,"Manrope",sans-serif)!important;
            font-size:16px!important;
            line-height:1.3!important;
            font-weight:700!important;
            letter-spacing:-.01em!important;
        }
        body.rafflelb-raffle-archive .rl-brand-filter-context{
            color:#899486!important;
            font-family:var(--rl-font,"Manrope",sans-serif)!important;
            font-size:13px!important;
            line-height:1.35!important;
            font-weight:600!important;
        }
        body.rafflelb-raffle-archive .rl-brand-filter-grid{
            display:grid!important;
            grid-template-columns:repeat(4,minmax(0,1fr))!important;
            gap:10px!important;
        }
        body.rafflelb-raffle-archive .rl-brand-filter-link{
            display:flex!important;
            min-width:0!important;
            min-height:46px!important;
            align-items:center!important;
            justify-content:flex-start!important;
            padding:0 15px!important;
            border:1px solid #303a2f!important;
            border-radius:11px!important;
            background:#090d09!important;
            color:#dce2d9!important;
            font-family:var(--rl-font,"Manrope",sans-serif)!important;
            font-size:14px!important;
            line-height:1.25!important;
            font-weight:650!important;
            letter-spacing:-.005em!important;
            text-decoration:none!important;
            overflow:hidden!important;
            text-overflow:ellipsis!important;
            white-space:nowrap!important;
            transition:border-color .18s ease,background .18s ease,color .18s ease!important;
        }
        body.rafflelb-raffle-archive .rl-brand-filter-link:hover,
        body.rafflelb-raffle-archive .rl-brand-filter-link.is-active{
            border-color:#baff00!important;
            background:rgba(186,255,0,.08)!important;
            color:#fff!important;
        }
        body.rafflelb-raffle-archive .rl-brand-filter-link.rl-brand-all{font-weight:700!important}
        body.rafflelb-raffle-archive .rl-brand-filter-search-wrap{display:none}
        body.rafflelb-raffle-archive .rl-brand-filter-empty{display:none}
        /* v0.2.45 — mobile Brand UX. The list used to become a horizontal
           swipe row on phones (display:flex; overflow-x:auto), which hid
           most brands off-screen. Replaced with the same 2-column grid
           style already used for mobile Category/Subcategory, plus a
           client-side search field (filters the already-loaded buttons in
           the DOM only — no new request, no taxonomy/query change; brand
           filtering of products is unchanged and still goes through the
           existing rl_brand link on each button). */
        @media(max-width:767px){
            body.rafflelb-raffle-archive .rl-brand-filter-search-wrap{
                display:block!important;
                margin:0 0 10px!important;
            }
            body.rafflelb-raffle-archive .rl-brand-filter-search{
                width:100%!important;
                min-height:42px!important;
                padding:0 14px!important;
                border:1px solid #303a2f!important;
                border-radius:10px!important;
                background:#090d09!important;
                color:#f4f7f2!important;
                font-family:var(--rl-font,"Manrope",sans-serif)!important;
                font-size:13px!important;
                box-sizing:border-box!important;
                -webkit-appearance:none!important;
                appearance:none!important;
            }
            body.rafflelb-raffle-archive .rl-brand-filter-search::placeholder{color:#7f8a7b!important}
            body.rafflelb-raffle-archive .rl-brand-filter-search:focus{outline:none!important;border-color:#baff00!important}
            body.rafflelb-raffle-archive .rl-brand-filter-grid{
                display:grid!important;
                grid-template-columns:repeat(2,minmax(0,1fr))!important;
                gap:8px!important;
                max-height:296px!important;
                overflow-y:auto!important;
                overflow-x:hidden!important;
                padding:0 0 2px!important;
            }
            body.rafflelb-raffle-archive .rl-brand-filter-empty{
                display:block!important;
                margin:8px 0 0!important;
                color:#7f8a7b!important;
                font-family:var(--rl-font,"Manrope",sans-serif)!important;
                font-size:12px!important;
                font-style:italic!important;
            }
            /* display:block!important above otherwise wins over the native
               [hidden]{display:none} UA rule, so toggling the `hidden`
               property from JS (empty.hidden = ...) had no visible effect. */
            body.rafflelb-raffle-archive .rl-brand-filter-empty[hidden]{
                display:none!important;
            }
            body.rafflelb-raffle-archive .rl-brand-filter-link{
                min-width:0!important;
                min-height:44px!important;
                font-size:13px!important;
            }
            /* .rl-brand-filter-link uses display:flex!important (desktop
               rule above), which otherwise wins over the native
               [hidden]{display:none} UA rule the same way .rl-brand-
               filter-empty did — so setting link.hidden = !match from the
               brand search JS had no visible effect on mobile. */
            body.rafflelb-raffle-archive .rl-brand-filter-link[hidden]{
                display:none!important;
            }
        }
        </style>
        <script id="rafflelb-shop-native-filters-ui-v03324">
        (function(){
            var body = document.body;
            if (!body || !body.classList.contains('rafflelb-raffle-archive')) return;

            var rlCategoryTerms = <?php echo wp_json_encode($rl_category_terms); ?> || [];
            var rlInitialCategoryId = <?php echo (int) $rl_current_category_id; ?>;
            var rlShopUrl = <?php echo wp_json_encode((string) $rl_shop_url); ?> || '/shop/';
            var rlShopMode = <?php echo wp_json_encode((string) $rl_shop_mode); ?> || 'both';
            var rlAjaxUrl = <?php echo wp_json_encode(admin_url('admin-ajax.php')); ?>;
            var rlReturnKey = 'rafflelb_shop_return_v03324';

            /* v0.2.37 — Brands used to be a one-time PHP snapshot
             * (rlBrandTerms) taken from the category active at the initial
             * full page load. WoodMart's AJAX shop swaps category/product
             * DOM in place without a full reload, so that snapshot went
             * stale the moment a shopper picked a different category
             * in-page: Category/Subcategory stayed correct because they
             * re-derive the active term from the live DOM/URL on every
             * sync() pass (currentCategoryTerm()), but Brands kept reading
             * the original request's data. Brands are now fetched per
             * category id + Shopping Mode through a small AJAX endpoint and
             * cached client-side, seeded with this request's own server-
             * rendered result so a normal full page load/back-navigation
             * still needs no round trip. */
            var rlBrandCache = {};
            var rlBrandPending = {};
            rlBrandCache[String(rlInitialCategoryId) + ':' + rlShopMode] = <?php echo wp_json_encode($rl_brand_terms); ?> || [];

            function showShopNavOverlay(){
                var el = document.getElementById('rl-shop-nav-overlay');
                if (!el) return;
                el.classList.remove('is-hiding');
                el.classList.add('is-active');
                // Self-contained safety net: some filter/category controls are
                // intercepted by the theme's own AJAX handling instead of doing
                // a real page navigation, in which case none of the reload-time
                // hide logic below ever runs. Never let a click leave this
                // overlay stuck - clear it on its own regardless of what (if
                // anything) happens afterward.
                window.setTimeout(function(){
                    el.classList.remove('is-active', 'is-hiding');
                }, 2500);
            }

            function hideShopNavOverlay(){
                var el = document.getElementById('rl-shop-nav-overlay');
                if (!el || !el.classList.contains('is-active')) return;
                el.classList.add('is-hiding');
                window.setTimeout(function(){
                    el.classList.remove('is-active', 'is-hiding');
                }, 260);
            }

            function rememberArchivePosition(openFilters){
                try {
                    window.sessionStorage.setItem(rlReturnKey, JSON.stringify({
                        openFilters: !!openFilters,
                        ts: Date.now()
                    }));
                } catch (e) {}
            }

            function addControlsAnchor(url){
                try {
                    var target = new URL(url, window.location.origin);
                    target.hash = 'rl-shop-controls';
                    return target.href;
                } catch (e) {
                    return url;
                }
            }

            function returnToArchiveControls(forceOpen){
                var shouldReturn = window.location.hash === '#rl-shop-controls';
                var remembered = null;
                try {
                    var raw = window.sessionStorage.getItem(rlReturnKey);
                    if (raw) {
                        remembered = JSON.parse(raw);
                        if (!remembered || !remembered.ts || (Date.now() - Number(remembered.ts)) > 15000) {
                            remembered = null;
                        }
                        window.sessionStorage.removeItem(rlReturnKey);
                    }
                } catch (e) {}

                if (!shouldReturn && !remembered) return;
                var openFilters = forceOpen === true || !!(remembered && remembered.openFilters);
                if (openFilters) body.classList.add('rl-shop-filters-open');

                var target = document.getElementById('rl-shop-controls');
                if (!target) return;
                var headerOffset = window.innerWidth <= 767 ? 82 : 104;
                var top = Math.max(0, target.getBoundingClientRect().top + window.pageYOffset - headerOffset);
                window.scrollTo(0, top);
            }

            function prepareArchiveNavigation(){
                // Custom mode/category/subcategory links navigate to real WooCommerce
                // archives. Keep the shopper at the controls instead of jumping back
                // to the page hero after each full-page request.
                Array.prototype.slice.call(document.querySelectorAll(
                    '.rl-shop-view-mode, .rl-shop-clear-filters, .rl-category-widget-enhanced a, .rl-subcategory-widget a, .rl-brand-filter-widget a'
                )).forEach(function(link){
                    if (!link || !link.href || link.dataset.rlReturnBound === '1') return;
                    link.dataset.rlReturnBound = '1';
                    link.href = addControlsAnchor(link.href);
                    link.addEventListener('click', function(){
                        var insideFilters = !!link.closest('.filters-area, .wd-filters-area, .shop-filters, .wd-shop-filters');
                        rememberArchivePosition(insideFilters || body.classList.contains('rl-shop-filters-open'));
                        showShopNavOverlay();
                    });
                });

                Array.prototype.slice.call(document.querySelectorAll('.rl-shop-sort form.woocommerce-ordering, .rl-shop-sort .woocommerce-ordering')).forEach(function(form){
                    if (!form || String(form.tagName || '').toLowerCase() !== 'form') return;
                    if (form.dataset.rlReturnBound !== '1') {
                        form.dataset.rlReturnBound = '1';
                        try { form.action = addControlsAnchor(form.action || window.location.href); } catch (e) {}
                        form.addEventListener('submit', function(){ rememberArchivePosition(false); showShopNavOverlay(); });
                    }
                    var select = form.querySelector('select[name="orderby"]');
                    if (select && select.dataset.rlReturnBound !== '1') {
                        select.dataset.rlReturnBound = '1';
                        select.addEventListener('change', function(){ rememberArchivePosition(false); showShopNavOverlay(); });
                    }
                });
            }

            function areas(){
                return Array.prototype.slice.call(document.querySelectorAll('.filters-area, .wd-filters-area, .shop-filters, .wd-shop-filters'));
            }
            function directItemLink(item){
                if (!item) return null;
                for (var i = 0; i < item.children.length; i++) {
                    if (item.children[i] && item.children[i].tagName === 'A') return item.children[i];
                }
                return item.querySelector('a');
            }

            function catalogUrl(baseUrl){
                try {
                    var target = new URL(baseUrl, window.location.origin);
                    var currentUrl = new URL(window.location.href);
                    ['min_price','max_price','orderby','rl_view'].forEach(function(key){
                        var value = currentUrl.searchParams.get(key);
                        if (value) target.searchParams.set(key, value);
                    });
                    target.searchParams.delete('product-page');
                    target.searchParams.delete('paged');
                    target.hash = 'rl-shop-controls';
                    return target.href;
                } catch (e) {
                    return baseUrl;
                }
            }

            function termById(id){
                id = Number(id || 0);
                if (!id) return null;
                return rlCategoryTerms.find(function(term){ return Number(term.id) === id; }) || null;
            }

            function termBySlug(slug){
                slug = String(slug || '').trim().toLowerCase();
                if (!slug) return null;
                try { slug = decodeURIComponent(slug); } catch (e) {}
                slug = slug.replace(/^\/+|\/+$/g, '');
                return rlCategoryTerms.find(function(term){
                    return String(term.slug || '').toLowerCase() === slug;
                }) || null;
            }

            function cleanCategoryText(text){
                return String(text || '')
                    .replace(/\(\s*\d+\s*\)\s*$/,'')
                    .replace(/\s+/g,' ')
                    .trim()
                    .toLowerCase();
            }

            function termForLink(link){
                if (!link || !link.href) return null;

                // 1) Exact canonical category archive path.
                var path = normalizedPath(link.href);
                var byPath = rlCategoryTerms.find(function(term){
                    return normalizedPath(term.url) === path;
                }) || null;
                if (byPath) return byPath;

                // 2) WoodMart/WooCommerce can render category filters as query URLs
                // instead of canonical term URLs. Resolve those query values by slug.
                try {
                    var parsed = new URL(link.href, window.location.origin);
                    var keys = ['product_cat','product-category','filter_product_cat','product_cat_slug','category'];
                    for (var i = 0; i < keys.length; i++) {
                        var raw = parsed.searchParams.get(keys[i]);
                        if (!raw) continue;
                        var first = raw.split(',')[0].trim();
                        var byQuery = termBySlug(first);
                        if (byQuery) return byQuery;
                    }

                    // Canonical category URLs normally end in the term slug.
                    var segments = (parsed.pathname || '').split('/').filter(Boolean);
                    if (segments.length) {
                        var byLastSegment = termBySlug(segments[segments.length - 1]);
                        if (byLastSegment) return byLastSegment;
                    }
                } catch (e) {}

                // 3) Last-resort exact visible-name match. This is intentionally
                // exact (not fuzzy) so child terms cannot be promoted accidentally.
                var label = cleanCategoryText(link.textContent || '');
                if (label) {
                    var matches = rlCategoryTerms.filter(function(term){
                        return cleanCategoryText(term.name) === label;
                    });
                    if (matches.length === 1) return matches[0];
                }
                return null;
            }

            function termForItem(item){
                if (!item) return null;

                // Native WooCommerce category widgets commonly expose the real
                // taxonomy term ID in classes such as cat-item-123. Prefer that
                // over URLs because WoodMart may rewrite filter links for AJAX.
                var classText = String(item.className || '');
                var idMatch = classText.match(/(?:^|\s)(?:cat-item-|product-cat-|term-)(\d+)(?=\s|$)/i);
                if (idMatch) {
                    var byClassId = termById(idMatch[1]);
                    if (byClassId) return byClassId;
                }

                var possibleIds = [
                    item.getAttribute('data-term-id'),
                    item.getAttribute('data-category-id'),
                    item.getAttribute('data-id')
                ];
                for (var i = 0; i < possibleIds.length; i++) {
                    var byDataId = termById(possibleIds[i]);
                    if (byDataId) return byDataId;
                }

                var link = directItemLink(item);
                if (link) {
                    var linkIds = [link.getAttribute('data-term-id'), link.getAttribute('data-category-id')];
                    for (var j = 0; j < linkIds.length; j++) {
                        var byLinkData = termById(linkIds[j]);
                        if (byLinkData) return byLinkData;
                    }
                }
                return termForLink(link);
            }

            function rootParentTerm(term){
                if (!term) return null;
                var root = term;
                var guard = 0;
                while (Number(root.parent) > 0 && guard < 20) {
                    var parent = rlCategoryTerms.find(function(candidate){
                        return Number(candidate.id) === Number(root.parent);
                    });
                    if (!parent) break;
                    root = parent;
                    guard++;
                }
                return root;
            }

            function setupCategoryExpansion(){
                var compactLimit = 4;
                areas().forEach(function(area){
                    Array.prototype.slice.call(area.querySelectorAll('.widget')).forEach(function(widget){
                        if (widget.dataset.rlCategoryEnhanced === '1') return;
                        var title = widget.querySelector('.widget-title, .wd-widget-title');
                        var titleText = title ? (title.textContent || '').trim().toLowerCase() : '';
                        var classText = (widget.className || '').toLowerCase();
                        var looksLikeCategory = titleText.indexOf('category') !== -1 || classText.indexOf('category-filter') !== -1 || classText.indexOf('product_categories') !== -1;
                        if (!looksLikeCategory) return;

                        var list = widget.querySelector('ul.product-categories, ul');
                        if (!list) return;
                        var nativeItems = Array.prototype.slice.call(list.children).filter(function(node){
                            return node && node.tagName === 'LI' && node.querySelector('a');
                        });

                        /* v0.33.18 — the parent-category selector must remain stable on
                           every archive. WoodMart narrows its native category widget to
                           the current branch after a parent is chosen, which made all
                           sibling parent categories disappear. Build this first section
                           from the complete Store-aware taxonomy map instead. */
                        nativeItems.forEach(function(item){
                            item.classList.add('rl-non-parent-cat');
                            item.style.display = 'none';
                        });

                        var rootTerms = rlCategoryTerms.filter(function(term){
                            return Number(term.parent) === 0 && !!term.store_has_products && String(term.slug || '').toLowerCase() !== 'uncategorized';
                        }).sort(function(a,b){
                            return String(a.name || '').localeCompare(String(b.name || ''));
                        });
                        if (!rootTerms.length) return;

                        widget.dataset.rlCategoryEnhanced = '1';
                        widget.classList.add('rl-category-widget-enhanced');
                        list.classList.add('rl-category-list');

                        var current = currentCategoryTerm(widget);
                        var selectedParent = rootParentTerm(current);

                        // A permanent reset/navigation choice gives the shopper a clear
                        // route back to the complete Store category list. Keep price and
                        // sort query values when possible; only the category is cleared.
                        function allCategoriesUrl(){
                            return catalogUrl(rlShopUrl);
                        }

                        var allItem = document.createElement('li');
                        allItem.className = 'cat-item rl-cat-item rl-cat-all-item';
                        var allLink = document.createElement('a');
                        allLink.href = allCategoriesUrl();
                        allLink.textContent = 'All Categories';
                        allItem.appendChild(allLink);
                        if (!selectedParent) allItem.classList.add('rl-parent-current');
                        list.appendChild(allItem);

                        var items = [];
                        rootTerms.forEach(function(root){
                            var item = document.createElement('li');
                            item.className = 'cat-item rl-synth-parent-cat rl-cat-item';
                            item.setAttribute('data-term-id', String(root.id));
                            var link = document.createElement('a');
                            link.href = catalogUrl(root.url);
                            link.textContent = root.name;
                            item.appendChild(link);
                            var matchesSelectedParent = selectedParent && Number(root.id) === Number(selectedParent.id);
                            item.classList.toggle('rl-parent-current', !!matchesSelectedParent);
                            list.appendChild(item);
                            items.push(item);
                        });

                        // If the active parent would normally be hidden beyond the
                        // compact limit, keep it among the visible parent choices.
                        var activeIndex = items.findIndex(function(item){
                            return item.classList.contains('rl-parent-current');
                        });
                        if (activeIndex >= compactLimit) {
                            var activeItem = items[activeIndex];
                            list.insertBefore(activeItem, items[compactLimit - 1]);
                            items.splice(activeIndex, 1);
                            items.splice(compactLimit - 1, 0, activeItem);
                        }

                        items.forEach(function(item, index){
                            item.classList.remove('rl-cat-extra');
                            if (index >= compactLimit) item.classList.add('rl-cat-extra');
                        });

                        if (items.length <= compactLimit) return;

                        var searchWrap = document.createElement('div');
                        searchWrap.className = 'rl-cat-search-wrap';
                        searchWrap.innerHTML = '<input class="rl-cat-search" type="search" autocomplete="off" placeholder="Search categories…" aria-label="Search product categories"><span class="rl-cat-search-icon" aria-hidden="true"></span>';
                        list.parentNode.insertBefore(searchWrap, list);

                        var empty = document.createElement('p');
                        empty.className = 'rl-cat-empty';
                        empty.textContent = 'No categories found.';
                        list.parentNode.insertBefore(empty, list.nextSibling);

                        var moreItem = document.createElement('li');
                        moreItem.className = 'rl-cat-more-item';
                        var moreButton = document.createElement('button');
                        moreButton.type = 'button';
                        moreButton.className = 'rl-cat-more';
                        moreButton.setAttribute('aria-expanded', 'false');
                        moreButton.textContent = '+ ' + (items.length - compactLimit) + ' MORE';
                        moreItem.appendChild(moreButton);
                        list.appendChild(moreItem);

                        var search = searchWrap.querySelector('.rl-cat-search');
                        function resetSearch(){
                            if (search) search.value = '';
                            items.forEach(function(item){ item.classList.remove('rl-cat-search-hidden'); });
                            empty.classList.remove('is-visible');
                        }
                        function applySearch(){
                            var q = (search.value || '').trim().toLowerCase();
                            var visible = 0;
                            items.forEach(function(item){
                                var link = item.querySelector('a');
                                var text = link ? (link.textContent || '').trim().toLowerCase() : '';
                                var match = !q || text.indexOf(q) !== -1;
                                item.classList.toggle('rl-cat-search-hidden', !match);
                                if (match) visible++;
                            });
                            empty.classList.toggle('is-visible', visible === 0);
                        }
                        moreButton.addEventListener('click', function(){
                            var expanded = widget.classList.toggle('rl-cat-expanded');
                            moreButton.setAttribute('aria-expanded', expanded ? 'true' : 'false');
                            moreButton.textContent = expanded ? 'SHOW LESS' : '+ ' + (items.length - compactLimit) + ' MORE';
                            if (expanded) {
                                window.setTimeout(function(){ if (search) search.focus(); }, 0);
                            } else {
                                resetSearch();
                            }
                        });
                        if (search) search.addEventListener('input', applySearch);
                    });
                });
            }

            function normalizedPath(url){
                try {
                    var parsed = new URL(url, window.location.origin);
                    return (parsed.pathname || '/').replace(/\/+$/, '') || '/';
                } catch (e) {
                    return '';
                }
            }

            function currentCategoryTerm(widget){
                var currentPath = normalizedPath(window.location.href);
                var byPath = rlCategoryTerms.find(function(term){
                    return normalizedPath(term.url) === currentPath;
                });
                if (byPath) return byPath;

                // Query-string category filters are common on WoodMart shop
                // archives, so resolve the current URL by slug as well.
                try {
                    var currentUrl = new URL(window.location.href);
                    var queryKeys = ['product_cat','product-category','filter_product_cat','product_cat_slug','category'];
                    for (var q = 0; q < queryKeys.length; q++) {
                        var raw = currentUrl.searchParams.get(queryKeys[q]);
                        if (!raw) continue;
                        var byQuery = termBySlug(raw.split(',')[0]);
                        if (byQuery) return byQuery;
                    }
                } catch (e) {}

                if (widget) {
                    var activeItem = widget.querySelector('li.current-cat, li.chosen, li.active, li.current-cat-parent');
                    var byActiveItem = termForItem(activeItem);
                    if (byActiveItem) return byActiveItem;

                    var active = widget.querySelector('a[aria-current="page"]');
                    var byActiveLink = termForLink(active);
                    if (byActiveLink) return byActiveLink;
                }

                if (rlInitialCategoryId) {
                    return rlCategoryTerms.find(function(term){ return Number(term.id) === Number(rlInitialCategoryId); }) || null;
                }
                return null;
            }

            function setupSubcategoryFilter(){
                areas().forEach(function(area){
                    Array.prototype.slice.call(area.querySelectorAll('.rl-subcategory-widget')).forEach(function(node){
                        node.remove();
                    });

                    var categoryWidget = area.querySelector('.rl-category-widget-enhanced');
                    if (!categoryWidget) return;

                    var current = currentCategoryTerm(categoryWidget);
                    if (!current) return;

                    var parent = rootParentTerm(current);
                    if (!parent) return;

                    // This second section always shows only the DIRECT children
                    // of the selected top-level parent. It never mixes parent
                    // categories back into the first Category filter.
                    var options = rlCategoryTerms.filter(function(term){
                        return Number(term.parent) === Number(parent.id) && !!term.store_has_products;
                    });
                    if (!options.length) return;

                    // If the current archive is deeper than one level, highlight
                    // the direct child branch that contains it.
                    var activeChildId = 0;
                    if (Number(current.id) !== Number(parent.id)) {
                        var branch = current;
                        var guard = 0;
                        while (Number(branch.parent) !== Number(parent.id) && Number(branch.parent) > 0 && guard < 20) {
                            var branchParent = rlCategoryTerms.find(function(term){
                                return Number(term.id) === Number(branch.parent);
                            });
                            if (!branchParent) break;
                            branch = branchParent;
                            guard++;
                        }
                        if (Number(branch.parent) === Number(parent.id)) activeChildId = Number(branch.id);
                    }

                    var block = document.createElement('section');
                    block.className = 'rl-subcategory-widget';
                    block.setAttribute('aria-label', 'Filter by subcategory');

                    var header = document.createElement('div');
                    header.className = 'rl-subcategory-header';

                    var heading = document.createElement('div');
                    heading.className = 'rl-subcategory-heading';
                    heading.textContent = 'Filter by subcategory';
                    header.appendChild(heading);

                    var context = document.createElement('span');
                    context.className = 'rl-subcategory-context';
                    context.textContent = parent.name;
                    header.appendChild(context);
                    block.appendChild(header);

                    var grid = document.createElement('div');
                    grid.className = 'rl-subcategory-grid';

                    var allLink = document.createElement('a');
                    allLink.className = 'rl-subcategory-link rl-subcategory-all';
                    if (Number(current.id) === Number(parent.id)) allLink.classList.add('is-active');
                    allLink.href = catalogUrl(parent.url);
                    allLink.textContent = 'All ' + parent.name;
                    grid.appendChild(allLink);

                    var subcategoryCompactLimit = 15;
                    // Keep the active subcategory visible in compact mode even
                    // when it would alphabetically fall beyond the first 15.
                    if (activeChildId) {
                        var activeOptionIndex = options.findIndex(function(term){
                            return Number(term.id) === Number(activeChildId);
                        });
                        if (activeOptionIndex >= subcategoryCompactLimit) {
                            var activeOption = options.splice(activeOptionIndex, 1)[0];
                            options.splice(subcategoryCompactLimit - 1, 0, activeOption);
                        }
                    }
                    var childLinks = [];
                    options.forEach(function(term, index){
                        var link = document.createElement('a');
                        link.className = 'rl-subcategory-link rl-subcategory-item';
                        if (index >= subcategoryCompactLimit) link.classList.add('rl-subcategory-extra');
                        if (Number(term.id) === Number(activeChildId)) link.classList.add('is-active');
                        link.href = catalogUrl(term.url);
                        link.textContent = term.name;
                        grid.appendChild(link);
                        childLinks.push(link);
                    });
                    block.appendChild(grid);

                    // Large parent branches stay compact. Up to 15 valid Store
                    // subcategories are visible immediately; beyond that, use the
                    // same MORE + searchable expansion pattern as main categories.
                    if (options.length > subcategoryCompactLimit) {
                        var subSearchWrap = document.createElement('div');
                        subSearchWrap.className = 'rl-subcategory-search-wrap';
                        subSearchWrap.innerHTML = '<input class="rl-cat-search rl-subcategory-search" type="search" autocomplete="off" placeholder="Search subcategories…" aria-label="Search subcategories"><span class="rl-cat-search-icon" aria-hidden="true"></span>';
                        block.insertBefore(subSearchWrap, grid);

                        var subEmpty = document.createElement('p');
                        subEmpty.className = 'rl-subcategory-empty';
                        subEmpty.textContent = 'No subcategories found.';
                        block.appendChild(subEmpty);

                        var subMore = document.createElement('button');
                        subMore.type = 'button';
                        subMore.className = 'rl-subcategory-more';
                        subMore.setAttribute('aria-expanded', 'false');
                        subMore.textContent = '+ ' + (options.length - subcategoryCompactLimit) + ' MORE';
                        block.appendChild(subMore);

                        var subSearch = subSearchWrap.querySelector('.rl-subcategory-search');
                        function resetSubcategorySearch(){
                            if (subSearch) subSearch.value = '';
                            childLinks.forEach(function(link){ link.classList.remove('rl-subcategory-search-hidden'); });
                            subEmpty.classList.remove('is-visible');
                        }
                        function applySubcategorySearch(){
                            var q = (subSearch.value || '').trim().toLowerCase();
                            var visible = 0;
                            childLinks.forEach(function(link){
                                var text = (link.textContent || '').trim().toLowerCase();
                                var match = !q || text.indexOf(q) !== -1;
                                link.classList.toggle('rl-subcategory-search-hidden', !match);
                                if (match) visible++;
                            });
                            subEmpty.classList.toggle('is-visible', visible === 0);
                        }
                        subMore.addEventListener('click', function(){
                            var expanded = block.classList.toggle('rl-subcategory-expanded');
                            subMore.setAttribute('aria-expanded', expanded ? 'true' : 'false');
                            subMore.textContent = expanded ? 'SHOW LESS' : '+ ' + (options.length - subcategoryCompactLimit) + ' MORE';
                            if (expanded) {
                                window.setTimeout(function(){ if (subSearch) subSearch.focus(); }, 0);
                            } else {
                                resetSubcategorySearch();
                            }
                        });
                        if (subSearch) subSearch.addEventListener('input', applySubcategorySearch);
                    }

                    // Keep this as a separate filter section, spanning the full
                    // width below the normal Category and Price widgets.
                    var host = categoryWidget.parentElement;
                    if (host) host.appendChild(block);
                });
            }

            function currentSelectedBrand(){
                try {
                    return new URLSearchParams(window.location.search).get('rl_brand') || '';
                } catch (e) {
                    return '';
                }
            }

            function fetchCategoryBrands(categoryId, mode, cacheKey){
                if (rlBrandPending[cacheKey]) return;
                rlBrandPending[cacheKey] = true;
                var xhr = new XMLHttpRequest();
                var url = rlAjaxUrl + '?action=rafflelb_category_brands&category_id=' + encodeURIComponent(categoryId) + '&mode=' + encodeURIComponent(mode) + '&_=' + Date.now();
                xhr.open('GET', url, true);
                xhr.onload = function(){
                    delete rlBrandPending[cacheKey];
                    if (xhr.status !== 200) return;
                    try {
                        var response = JSON.parse(xhr.responseText);
                        var brands = (response && response.success && Array.isArray(response.data && response.data.brands)) ? response.data.brands : [];
                        rlBrandCache[cacheKey] = brands;
                        setupBrandFilter();
                    } catch (e) {}
                };
                xhr.onerror = function(){ delete rlBrandPending[cacheKey]; };
                xhr.send();
            }

            function setupBrandFilter(){
                areas().forEach(function(area){
                    Array.prototype.slice.call(area.querySelectorAll('.rl-brand-filter-widget')).forEach(function(node){
                        node.remove();
                    });

                    var grid = area.querySelector('.filters-inner-area, .widget-area') || area;
                    var categoryWidget = area.querySelector('.rl-category-widget-enhanced');
                    if (!grid || !categoryWidget) return;

                    // Hidden until a real category is the active one (All
                    // Categories has no term here), matching Category/
                    // Subcategory's own live DOM/URL-derived state instead of
                    // a value frozen at the initial page load.
                    var current = currentCategoryTerm(categoryWidget);
                    if (!current) return;

                    var cacheKey = String(current.id) + ':' + rlShopMode;
                    if (!Object.prototype.hasOwnProperty.call(rlBrandCache, cacheKey)) {
                        fetchCategoryBrands(current.id, rlShopMode, cacheKey);
                        return; // setupBrandFilter() re-runs itself once the fetch resolves.
                    }

                    var brands = rlBrandCache[cacheKey];
                    if (!Array.isArray(brands) || !brands.length) return;

                    var selectedBrand = currentSelectedBrand();
                    if (selectedBrand && !brands.some(function(brand){ return brand && String(brand.slug) === selectedBrand; })) {
                        // The category changed (in-page, via WoodMart AJAX) and the
                        // brand carried over in the URL no longer applies to it.
                        // Drop it locally instead of leaving a filter applied that
                        // no longer matches what the Brand row shows as selected.
                        selectedBrand = '';
                        try {
                            var cleanUrl = new URL(window.location.href);
                            cleanUrl.searchParams.delete('rl_brand');
                            window.history.replaceState(window.history.state, '', cleanUrl.toString());
                        } catch (e) {}
                    }

                    var block = document.createElement('div');
                    block.className = 'widget rl-brand-filter-widget';
                    block.setAttribute('aria-label', 'Filter by brand');

                    var header = document.createElement('div');
                    header.className = 'rl-brand-filter-head';
                    var heading = document.createElement('h5');
                    heading.className = 'rl-brand-filter-heading';
                    heading.textContent = 'Filter by brand';
                    var context = document.createElement('span');
                    context.className = 'rl-brand-filter-context';
                    context.textContent = current.name || '';
                    header.appendChild(heading);
                    header.appendChild(context);
                    block.appendChild(header);

                    // Mobile brand search — client-side only. It filters the
                    // brand buttons already loaded above (no server request,
                    // no taxonomy/query change); brand FILTERING of products
                    // still goes through the existing rl_brand link below.
                    var searchWrap = document.createElement('div');
                    searchWrap.className = 'rl-brand-filter-search-wrap';
                    var search = document.createElement('input');
                    search.type = 'search';
                    search.className = 'rl-brand-filter-search';
                    search.placeholder = 'Search brands…';
                    search.setAttribute('autocomplete', 'off');
                    search.setAttribute('aria-label', 'Search brands');
                    searchWrap.appendChild(search);
                    block.appendChild(searchWrap);

                    var list = document.createElement('div');
                    list.className = 'rl-brand-filter-grid';

                    function brandUrl(slug){
                        try {
                            var url = new URL(window.location.href);
                            url.searchParams.delete('paged');
                            url.searchParams.delete('product-page');
                            if (slug) url.searchParams.set('rl_brand', slug);
                            else url.searchParams.delete('rl_brand');
                            url.hash = 'rl-shop-controls';
                            return url.href;
                        } catch (e) {
                            return window.location.href;
                        }
                    }

                    var all = document.createElement('a');
                    all.className = 'rl-brand-filter-link rl-brand-all' + (!selectedBrand ? ' is-active' : '');
                    all.href = brandUrl('');
                    all.textContent = 'All Brands';
                    list.appendChild(all);

                    brands.forEach(function(brand){
                        if (!brand || !brand.slug || !brand.name) return;
                        var link = document.createElement('a');
                        link.className = 'rl-brand-filter-link' + (selectedBrand === String(brand.slug) ? ' is-active' : '');
                        link.href = brandUrl(String(brand.slug));
                        link.textContent = String(brand.name);
                        list.appendChild(link);
                    });

                    block.appendChild(list);

                    var empty = document.createElement('p');
                    empty.className = 'rl-brand-filter-empty';
                    empty.textContent = 'No brands found';
                    empty.hidden = true;
                    block.appendChild(empty);

                    var brandLinks = Array.prototype.slice.call(list.querySelectorAll('.rl-brand-filter-link:not(.rl-brand-all)'));
                    search.addEventListener('input', function(){
                        var q = (search.value || '').trim().toLowerCase();
                        var visible = 0;
                        brandLinks.forEach(function(link){
                            var match = !q || (link.textContent || '').toLowerCase().indexOf(q) !== -1;
                            link.hidden = !match;
                            if (match) visible++;
                        });
                        empty.hidden = visible !== 0;
                    });

                    // setupSubcategoryFilter() appends its full-width section first;
                    // appending here therefore places Brand directly underneath it.
                    grid.appendChild(block);
                });
            }

            function setupRetailPriceInputs(){
                var currency = <?php echo wp_json_encode(function_exists('get_woocommerce_currency_symbol') ? html_entity_decode(get_woocommerce_currency_symbol(), ENT_QUOTES, 'UTF-8') : '$'); ?>;

                function looksLikeNativePriceWidget(widget){
                    if (!widget || widget.classList.contains('rl-synth-price-widget')) return false;
                    var title = widget.querySelector('.widget-title, .wd-widget-title');
                    var titleText = title ? (title.textContent || '').trim().toLowerCase() : '';
                    return titleText.indexOf('price') !== -1
                        || widget.classList.contains('widget_price_filter')
                        || /price[-_ ]filter/i.test(widget.className || '');
                }

                function filterGrid(area){
                    if (!area) return null;
                    return area.querySelector('.filters-inner-area, .widget-area') || area;
                }

                function updateFormValues(form){
                    if (!form) return;
                    var params = new URLSearchParams(window.location.search);
                    var minInput = form.querySelector('input[name="min_price"]');
                    var maxInput = form.querySelector('input[name="max_price"]');
                    var clear = form.querySelector('.rl-price-clear');
                    if (!minInput || !maxInput || !clear) return;
                    minInput.value = params.get('min_price') || '';
                    maxInput.value = params.get('max_price') || '';
                    clear.hidden = !minInput.value && !maxInput.value;
                }

                areas().forEach(function(area){
                    var grid = filterGrid(area);
                    if (!grid) return;

                    /* v0.33.20 — WoodMart can remove its native Price widget on a
                       leaf category when that widget decides there is no useful
                       native range to render. RaffleLB's Store price is a separate
                       Buy It Now value, so our MIN/MAX retail-price control must not
                       depend on WoodMart keeping that widget in the DOM.

                       Hide any native price presentation and render one persistent
                       RaffleLB price widget directly in the filter grid. The form
                       still uses WooCommerce min_price/max_price query arguments,
                       which are mapped server-side to the Buy It Now retail value. */
                    Array.prototype.slice.call(grid.querySelectorAll('.widget')).forEach(function(widget){
                        if (looksLikeNativePriceWidget(widget)) {
                            widget.classList.add('rl-native-price-source');
                        }
                    });

                    var widget = null;
                    Array.prototype.slice.call(grid.children || []).some(function(child){
                        if (child && child.classList && child.classList.contains('rl-synth-price-widget')) {
                            widget = child;
                            return true;
                        }
                        return false;
                    });

                    if (!widget) {
                        widget = document.createElement('div');
                        widget.className = 'widget rl-price-widget-enhanced rl-synth-price-widget';

                        var title = document.createElement('h5');
                        title.className = 'widget-title';
                        title.textContent = rlShopMode === 'raffle' ? 'Raffle entry price' : 'Price filter';
                        widget.appendChild(title);

                        var form = document.createElement('form');
                        form.className = 'rl-retail-price-form';
                        form.setAttribute('novalidate','novalidate');
                        var priceName = rlShopMode === 'raffle' ? 'raffle entry price' : 'retail price';
                        form.innerHTML = '<div class="rl-price-row">'
                            + '<label class="rl-price-field"><span class="rl-price-label">MIN</span><span class="rl-price-input-wrap"><i class="rl-price-currency"></i><input type="number" min="0" step="0.01" inputmode="decimal" name="min_price" placeholder="0" aria-label="Minimum ' + priceName + '"></span></label>'
                            + '<span class="rl-price-to">TO</span>'
                            + '<label class="rl-price-field"><span class="rl-price-label">MAX</span><span class="rl-price-input-wrap"><i class="rl-price-currency"></i><input type="number" min="0" step="0.01" inputmode="decimal" name="max_price" placeholder="Any" aria-label="Maximum ' + priceName + '"></span></label>'
                            + '<button type="submit" class="rl-price-apply">APPLY</button>'
                            + '<button type="button" class="rl-price-clear">CLEAR</button>'
                            + '</div>';
                        Array.prototype.slice.call(form.querySelectorAll('.rl-price-currency')).forEach(function(node){ node.textContent = currency; });
                        widget.appendChild(form);

                        var categoryWidget = grid.querySelector('.rl-category-widget-enhanced');
                        var subcategoryWidget = grid.querySelector('.rl-subcategory-widget');
                        if (subcategoryWidget) {
                            grid.insertBefore(widget, subcategoryWidget);
                        } else if (categoryWidget && categoryWidget.nextSibling) {
                            grid.insertBefore(widget, categoryWidget.nextSibling);
                        } else {
                            grid.appendChild(widget);
                        }

                        var minInput = form.querySelector('input[name="min_price"]');
                        var maxInput = form.querySelector('input[name="max_price"]');
                        var clear = form.querySelector('.rl-price-clear');

                        form.addEventListener('submit', function(event){
                            event.preventDefault();
                            var min = parseFloat(minInput.value);
                            var max = parseFloat(maxInput.value);
                            if (!isFinite(min) || min < 0) min = null;
                            if (!isFinite(max) || max < 0) max = null;
                            if (min !== null && max !== null && min > max) { var temp = min; min = max; max = temp; }
                            var url = new URL(window.location.href);
                            if (min !== null) url.searchParams.set('min_price', String(min)); else url.searchParams.delete('min_price');
                            if (max !== null) url.searchParams.set('max_price', String(max)); else url.searchParams.delete('max_price');
                            url.searchParams.delete('product-page');
                            url.searchParams.delete('paged');
                            rememberArchivePosition(true);
                            showShopNavOverlay();
                            window.location.assign(addControlsAnchor(url.toString()));
                        });
                        clear.addEventListener('click', function(){
                            var url = new URL(window.location.href);
                            url.searchParams.delete('min_price');
                            url.searchParams.delete('max_price');
                            url.searchParams.delete('product-page');
                            url.searchParams.delete('paged');
                            rememberArchivePosition(true);
                            showShopNavOverlay();
                            window.location.assign(addControlsAnchor(url.toString()));
                        });
                    }

                    updateFormValues(widget.querySelector('.rl-retail-price-form'));
                });
            }

            function syncOrderingMode(){
                Array.prototype.slice.call(document.querySelectorAll('.rl-shop-sort form.woocommerce-ordering, .rl-shop-sort .woocommerce-ordering')).forEach(function(form){
                    if (!form || String(form.tagName || '').toLowerCase() !== 'form') return;
                    var existing = form.querySelector('input[name="rl_view"]');
                    if (rlShopMode === 'both') {
                        if (existing) existing.remove();
                        return;
                    }
                    if (!existing) {
                        existing = document.createElement('input');
                        existing.type = 'hidden';
                        existing.name = 'rl_view';
                        form.appendChild(existing);
                    }
                    existing.value = rlShopMode;
                });
            }

            function sync(){
                var toggle = document.querySelector('.rl-shop-filter-toggle');
                var list = areas();
                setupCategoryExpansion();
                setupRetailPriceInputs();
                setupSubcategoryFilter();
                setupBrandFilter();
                syncOrderingMode();
                prepareArchiveNavigation();
                if (!toggle) return;
                if (!list.length) {
                    toggle.style.display = 'none';
                    return;
                }
                toggle.style.display = '';
                var open = body.classList.contains('rl-shop-filters-open');
                toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
                list.forEach(function(area, index){
                    if (!area.id) area.id = 'rl-shop-native-filters-' + index;
                    area.setAttribute('aria-hidden', open ? 'false' : 'true');
                });
            }

            document.addEventListener('click', function(event){
                var toggle = event.target.closest && event.target.closest('.rl-shop-filter-toggle');
                if (!toggle) return;
                event.preventDefault();
                body.classList.toggle('rl-shop-filters-open');
                sync();
            });
            document.addEventListener('keydown', function(event){
                if (event.key === 'Escape' && body.classList.contains('rl-shop-filters-open')) {
                    body.classList.remove('rl-shop-filters-open');
                    sync();
                    var toggle = document.querySelector('.rl-shop-filter-toggle');
                    if (toggle) toggle.focus();
                }
            });

            function scheduleSyncSeries(){
                // WoodMart can rebuild its category/price widgets after the first
                // paint (and after archive navigation). Re-run the enhancer a few
                // times so the visible filter panel never falls back to native
                // child-category links or preset price chips.
                [0, 180, 450, 900, 1600].forEach(function(delay){
                    window.setTimeout(sync, delay);
                });
            }

            scheduleSyncSeries();
            // The archive markup can shift slightly while WoodMart initializes;
            // restore twice so desktop and mobile both land on the controls rather
            // than the top of the page after a filter/mode/category request.
            window.setTimeout(returnToArchiveControls, 60);
            window.setTimeout(returnToArchiveControls, 360);
            // Reveal the page only after both scroll corrections have landed, so
            // the loading overlay (shown immediately at the top of <body> when a
            // return-to-controls navigation is detected) hides the flash-to-top
            // entirely instead of fading out before the second correction.
            window.setTimeout(hideShopNavOverlay, 460);
            // Safety net: never leave the overlay stuck if something above fails.
            window.setTimeout(hideShopNavOverlay, 3000);

            // Full page/back-forward navigation.
            window.addEventListener('pageshow', scheduleSyncSeries);
            window.addEventListener('popstate', scheduleSyncSeries);

            if (window.jQuery) {
                // WoodMart event names vary by version/build. Listen on both the
                // document and body, and also to completed AJAX requests as a
                // compatibility fallback. The setup functions are idempotent.
                window.jQuery(document).on('woodmart-ajax-shop-after wdShopPageInit', scheduleSyncSeries);
                window.jQuery(document.body).on('woodmart-ajax-shop-after wc_fragments_refreshed wdShopPageInit', scheduleSyncSeries);
                window.jQuery(document).ajaxComplete(function(){
                    window.setTimeout(sync, 80);
                    window.setTimeout(sync, 420);
                });
            }
        })();
        </script>
        <?php
    }

    public static function shop_sort_select_force_fix() {
        if (!self::shop_query_is_catalog()) return;
        ?>
        <style id="rafflelb-shop-sort-custom-css-v1">
        .rl-shop-sort-custom{position:relative!important;display:inline-flex!important;align-self:flex-start!important;width:max-content!important;max-width:100%!important;min-width:0}
        /* The <form class="woocommerce-ordering"> wrapper carries its own
           border/background from the theme, previously invisible only
           because the native select filled it exactly. Now that the
           custom trigger is correctly sized to its own content (smaller
           than the full wrapper), that chrome shows as a second box
           around it - strip it so only the trigger's own border shows. */
        #rl-shop-controls .rl-shop-sort .woocommerce-ordering{
            border:0!important;
            background:none!important;
            box-shadow:none!important;
            padding:0!important;
            margin:0!important;
        }
        /* wd-ordering-mb-icon's name is literally WoodMart's own "mobile
           icon" flag - it paints its up/down sort icon via a pseudo-
           element on the wrapper, a separate DOM node the hidden <select>
           JS fix could never reach (JS can't inline-style ::before/
           ::after, only a CSS rule can remove them). */
        #rl-shop-controls .rl-shop-sort .woocommerce-ordering.wd-ordering-mb-icon:before,
        #rl-shop-controls .rl-shop-sort .woocommerce-ordering.wd-ordering-mb-icon:after,
        #rl-shop-controls .rl-shop-sort .woocommerce-ordering:before,
        #rl-shop-controls .rl-shop-sort .woocommerce-ordering:after{
            content:none!important;
            display:none!important;
        }
        .rl-shop-sort-trigger{display:inline-flex!important;align-items:center!important;justify-content:center!important;gap:8px!important;min-height:42px!important;margin:0!important;padding:0 15px!important;border:1px solid #354033!important;border-radius:9px!important;background:#101510!important;color:#eef3eb!important;font-family:inherit!important;font-size:9px!important;font-weight:900!important;letter-spacing:.08em!important;text-transform:uppercase!important;box-shadow:none!important;cursor:pointer!important;transition:.18s ease!important;white-space:nowrap;max-width:100%;overflow:hidden;text-overflow:ellipsis}
        .rl-shop-sort-trigger:hover,.rl-shop-sort-trigger[aria-expanded="true"]{border-color:#baff00!important;color:#baff00!important}
        .rl-shop-sort-value{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
        .rl-shop-sort-caret{flex:0 0 auto;font-size:10px;transition:transform .15s ease}
        .rl-shop-sort-trigger[aria-expanded="true"] .rl-shop-sort-caret{transform:rotate(180deg)}
        .rl-shop-sort-menu{position:fixed;z-index:999999;min-width:200px;max-width:calc(100vw - 24px);padding:6px;border:1px solid #273126;border-radius:10px;background:#0d110d;box-shadow:0 20px 50px rgba(0,0,0,.45)}
        .rl-shop-sort-option{display:block;width:100%;padding:9px 12px;border:0;border-radius:7px;background:transparent;color:#d8ded5;font-family:inherit;font-size:11px;font-weight:800;letter-spacing:.03em;text-transform:uppercase;text-align:left;cursor:pointer}
        .rl-shop-sort-option:hover{background:rgba(186,255,0,.08);color:#fff}
        .rl-shop-sort-option.is-active{color:#baff00}
        @media(max-width:767px){
            #rl-shop-controls .rl-shop-toolbar-actions{
                grid-template-columns:minmax(112px,.82fr) minmax(0,1.65fr)!important;
                width:100%!important;
                max-width:none!important;
                gap:8px!important;
            }
            #rl-shop-controls .rl-shop-filter-toggle{width:100%!important;min-width:0!important}
            #rl-shop-controls .rl-shop-sort,
            #rl-shop-controls .rl-shop-sort .woocommerce-ordering,
            #rl-shop-controls .rl-shop-sort-custom{
                display:flex!important;
                width:100%!important;
                min-width:0!important;
                max-width:none!important;
                margin:0!important;
            }
            #rl-shop-controls .rl-shop-sort-trigger{
                width:100%!important;
                min-width:0!important;
                max-width:none!important;
                justify-content:space-between!important;
                padding-left:12px!important;
                padding-right:12px!important;
            }
        }
        </style>
        <script id="rafflelb-shop-sort-custom-v1">
        (function(){
            function force(el, styles){
                if (!el) return;
                Object.keys(styles).forEach(function(prop){
                    el.style.setProperty(prop, styles[prop], 'important');
                });
            }

            function buildOne(wrap){
                var select = wrap.querySelector('select');
                if (!select || wrap.dataset.rlSortCustom) return;
                wrap.dataset.rlSortCustom = '1';

                // CSS rules for a chrome-free wrapper here kept getting
                // silently overridden specifically when a filter/category
                // was already active (never pinned down exactly why, and
                // not worth more guessing) - force it inline on every
                // wrapper level instead, which can't lose to anything.
                force(wrap, {border:'0', background:'none', 'box-shadow':'none', padding:'0', margin:'0'});
                var sortBox = wrap.closest('.rl-shop-sort');
                force(sortBox, {border:'0', background:'none', 'box-shadow':'none', padding:'0'});

                var custom = document.createElement('div');
                custom.className = 'rl-shop-sort-custom';

                var trigger = document.createElement('button');
                trigger.type = 'button';
                trigger.className = 'rl-shop-sort-trigger';
                trigger.setAttribute('aria-haspopup', 'true');
                trigger.setAttribute('aria-expanded', 'false');
                trigger.innerHTML = '<span class="rl-shop-sort-value"></span><span class="rl-shop-sort-caret" aria-hidden="true">&#9662;</span>';
                var valueEl = trigger.querySelector('.rl-shop-sort-value');

                if (window.matchMedia && window.matchMedia('(max-width:767px)').matches) {
                    force(wrap, {width:'100%', 'min-width':'0', 'max-width':'none'});
                    force(sortBox, {width:'100%', 'min-width':'0', 'max-width':'none'});
                    force(custom, {width:'100%', 'min-width':'0', 'max-width':'none', display:'flex'});
                    force(trigger, {width:'100%', 'min-width':'0', 'max-width':'none', 'justify-content':'center'});
                }

                var menu = document.createElement('div');
                menu.className = 'rl-shop-sort-menu';
                menu.hidden = true;
                menu.setAttribute('role', 'menu');

                function syncLabel(){
                    var opt = select.options[select.selectedIndex];
                    valueEl.textContent = opt ? opt.textContent : '';
                    Array.prototype.forEach.call(menu.children, function(item){
                        item.classList.toggle('is-active', item.dataset.value === select.value);
                    });
                }

                Array.prototype.forEach.call(select.options, function(opt){
                    var item = document.createElement('button');
                    item.type = 'button';
                    item.className = 'rl-shop-sort-option' + (opt.selected ? ' is-active' : '');
                    item.textContent = opt.textContent;
                    item.dataset.value = opt.value;
                    item.setAttribute('role', 'menuitemradio');
                    item.addEventListener('click', function(){
                        if (select.value !== opt.value) {
                            select.value = opt.value;
                            closeMenu();
                            if (select.form) select.form.submit();
                        } else {
                            closeMenu();
                        }
                    });
                    menu.appendChild(item);
                });

                function positionMenu(){
                    var rect = trigger.getBoundingClientRect();
                    menu.style.top = (rect.bottom + 6) + 'px';
                    var right = window.innerWidth - rect.right;
                    menu.style.right = Math.max(12, right) + 'px';
                    menu.style.left = 'auto';
                }

                function openMenu(){
                    document.body.appendChild(menu);
                    menu.hidden = false;
                    positionMenu();
                    trigger.setAttribute('aria-expanded', 'true');
                }
                function closeMenu(){
                    menu.hidden = true;
                    trigger.setAttribute('aria-expanded', 'false');
                }

                trigger.addEventListener('click', function(e){
                    e.stopPropagation();
                    if (menu.hidden) openMenu(); else closeMenu();
                });
                document.addEventListener('click', function(e){
                    if (!menu.hidden && e.target !== trigger && !menu.contains(e.target)) closeMenu();
                });
                document.addEventListener('keydown', function(e){
                    if (e.key === 'Escape' && !menu.hidden) closeMenu();
                });
                window.addEventListener('scroll', function(){ if (!menu.hidden) closeMenu(); }, true);
                window.addEventListener('resize', function(){ if (!menu.hidden) closeMenu(); });

                syncLabel();
                // width:1px/height:1px alone still let the theme's arrow
                // background-image paint outside that tiny box on mobile.
                // The standard "visually hidden" recipe (clip + overflow)
                // guarantees nothing from this element is ever painted,
                // regardless of its own background/border/icon styling.
                [
                    ['position', 'absolute'],
                    ['width', '1px'],
                    ['height', '1px'],
                    ['padding', '0'],
                    ['margin', '-1px'],
                    ['overflow', 'hidden'],
                    ['clip', 'rect(0,0,0,0)'],
                    ['clip-path', 'inset(50%)'],
                    ['white-space', 'nowrap'],
                    ['border', '0'],
                    ['background', 'none'],
                    ['pointer-events', 'none']
                ].forEach(function(pair){
                    select.style.setProperty(pair[0], pair[1], 'important');
                });

                trigger.classList.add('rl-shop-filter-toggle');
                custom.appendChild(trigger);
                wrap.appendChild(custom);
            }

            function isCategoryFilterActive(){
                // The rafflelb-category-active body class is set once at
                // page load from is_product_category() server-side, but
                // WoodMart's AJAX shop lets a category be picked from the
                // Filters panel on the plain /shop/ page WITHOUT a real
                // navigation - the URL updates via pushState but the page
                // never reloads, so that server-rendered class never
                // changes. Re-checking the live URL on every call catches
                // that case too.
                if (document.body.classList.contains('rafflelb-category-active')) return true;
                if (/\/product-category\//.test(window.location.pathname)) return true;
                if (/[?&](product_cat|filter_category)=/.test(window.location.search)) return true;
                return false;
            }

            function fix(){
                // The CSS hide rule for .rl-shop-sort on category pages
                // keeps losing to some other rule we couldn't pin down
                // without live access - force it the same reliable way
                // as everything else here instead of chasing that fight.
                var hide = isCategoryFilterActive();
                document.querySelectorAll('#rl-shop-controls .rl-shop-sort').forEach(function(el){
                    if (hide) {
                        force(el, {display:'none'});
                    } else {
                        el.style.removeProperty('display');
                    }
                });
                if (hide) return;
                document.querySelectorAll('#rl-shop-controls .rl-shop-sort .woocommerce-ordering').forEach(buildOne);
            }

            fix();
            // Re-run after any AJAX-driven shop UI refresh, since those
            // can replace this markup with a fresh, un-enhanced select,
            // and after browser back/forward (WoodMart's AJAX filtering
            // uses pushState, so those are real URL changes too.
            if (window.jQuery) {
                window.jQuery(document.body).on('woodmart-ajax-shop-after wc_fragments_refreshed wdShopPageInit', fix);
            }
            window.addEventListener('popstate', fix);
            // Belt-and-braces: a category pill in the Filters panel is
            // itself a plain click inside the shop controls area, so
            // re-check shortly after any click there regardless of
            // whether a specific AJAX event above also covers it.
            document.addEventListener('click', function(e){
                if (e.target.closest && e.target.closest('#rl-shop-controls, .filters-area, .wd-filters-area')) {
                    window.setTimeout(fix, 60);
                    window.setTimeout(fix, 400);
                }
            }, true);

            // The element picker confirmed the native <select> is fully
            // visible and un-enhanced on category pages - fix() never
            // reached it at all, despite no console error. That, plus
            // this site's known "AJAX shop" async rendering, points at
            // #rl-shop-controls itself being REPLACED (not just mutated)
            // by an async render sometime after this script's first
            // pass - which would leave a MutationObserver watching that
            // original (now-detached, discarded) node useless. Watching
            // document.body instead can never go stale, since body
            // itself is never replaced, and subtree:true still catches
            // a replacement happening anywhere underneath it.
            if (window.MutationObserver) {
                var pending = false;
                var mo = new MutationObserver(function(){
                    if (pending) return;
                    pending = true;
                    window.setTimeout(function(){
                        pending = false;
                        fix();
                    }, 40);
                });
                mo.observe(document.body, {childList:true, subtree:true});
            }
        })();
        </script>
        <?php
    }

    public static function raffle_product_styles() {
        if (!function_exists('is_product') || !is_product()) return;
        if (!self::is_raffle_product()) return;
        /*
         * wp_head already printed this stylesheet render-blocking, ahead of the
         * theme. Enqueueing it a second time would only hand an optimiser a
         * second, deferrable copy to reorder.
         */
        if (self::$early_style_printed) return;
        wp_enqueue_style('rafflelb-single-product', plugins_url('assets/single-product.css', __FILE__), [], self::VERSION);
        return;
        ?>
        <style id="rafflelb-single-raffle-product-v0232">
        body.rafflelb-raffle-product{
            background:#090a09 !important;
            color:#f5f6f2 !important;
        }

        .rafflelb-raffle-product .main-page-wrapper,
        .rafflelb-raffle-product .site-content,
        .rafflelb-raffle-product .page-title,
        .rafflelb-raffle-product .product-image-summary,
        .rafflelb-raffle-product .product-image-summary-inner,
        .rafflelb-raffle-product .product-tabs-wrapper,
        .rafflelb-raffle-product .wd-content-layout,
        .rafflelb-raffle-product .site-content > .container{
            background:
                radial-gradient(circle at 78% 18%,rgba(202,255,22,.055),transparent 26%),
                linear-gradient(180deg,#0c0e0c 0%,#080908 100%) !important;
        }

        /* Keep the existing RaffleLB header white so the real black/lime logo stays readable. */
        .rafflelb-raffle-product .whb-header,
        .rafflelb-raffle-product .whb-row,
        .rafflelb-raffle-product .whb-general-header,
        .rafflelb-raffle-product .whb-header .container{
            background:#ffffff !important;
        }
        .rafflelb-raffle-product .whb-header a,
        .rafflelb-raffle-product .whb-header .wd-nav > li > a,
        .rafflelb-raffle-product .whb-header .wd-tools-text,
        .rafflelb-raffle-product .whb-header .wd-tools-icon{
            color:#0a0b09 !important;
        }

        .rafflelb-raffle-product .product-image-summary{
            padding-top:34px !important;
            padding-bottom:52px !important;
            border-bottom:1px solid #242722 !important;
        }
        .rafflelb-raffle-product .product-image-summary-inner{
            align-items:flex-start !important;
        }
        .rafflelb-raffle-product .product-images{
            padding-right:34px !important;
        }
        .rafflelb-raffle-product .woocommerce-product-gallery,
        .rafflelb-raffle-product .product-image-wrap{
            border:1px solid #2a2d28 !important;
            border-radius:18px !important;
            overflow:hidden !important;
            background:#0c0d0b !important;
            box-shadow:0 22px 60px rgba(0,0,0,.28) !important;
        }
        .rafflelb-raffle-product .woocommerce-product-gallery img,
        .rafflelb-raffle-product .product-image-wrap img{
            width:100% !important;
            height:auto !important;
            display:block !important;
        }
        .rafflelb-raffle-product .summary-inner{
            padding:5px 0 0 18px !important;
        }
        .rafflelb-raffle-product .woocommerce-breadcrumb,
        .rafflelb-raffle-product .wd-breadcrumbs{
            margin-bottom:20px !important;
            color:#979c92 !important;
            font-size:11px !important;
            display:flex !important;
            align-items:center !important;
            flex-wrap:nowrap !important;
            gap:7px !important;
            white-space:nowrap !important;
            line-height:1.4 !important;
            min-width:0 !important;
            overflow:hidden !important;
        }
        .rafflelb-raffle-product .woocommerce-breadcrumb a,
        .rafflelb-raffle-product .wd-breadcrumbs a,
        .rafflelb-raffle-product .woocommerce-breadcrumb span,
        .rafflelb-raffle-product .wd-breadcrumbs span{
            color:#979c92 !important;
            font-size:11px !important;
            display:inline-flex !important;
            align-items:center !important;
            margin:0 !important;
            padding:0 !important;
            white-space:nowrap !important;
            line-height:1.4 !important;
        }
        .rafflelb-raffle-product .woocommerce-breadcrumb > *,
        .rafflelb-raffle-product .wd-breadcrumbs > *{
            float:none !important;
            clear:none !important;
        }
        .rafflelb-raffle-product .woocommerce-breadcrumb .breadcrumb-last,
        .rafflelb-raffle-product .wd-breadcrumbs .breadcrumb-last{
            min-width:0 !important;
            overflow:hidden !important;
            text-overflow:ellipsis !important;
        }
        .rafflelb-raffle-product .product_title{
            max-width:760px !important;
            margin:0 0 16px !important;
            color:#f7f8f4 !important;
            font-size:clamp(34px,3vw,48px) !important;
            line-height:1.08 !important;
            font-weight:760 !important;
            letter-spacing:-.03em !important;
            text-transform:none !important;
        }
        .rafflelb-raffle-product .summary-inner > .price{
            margin:0 0 20px !important;
        }
        .rafflelb-raffle-product .rl-entry-price{
            color:#caff16 !important;
            font-size:35px !important;
            font-weight:900 !important;
            line-height:1 !important;
        }
        .rafflelb-raffle-product .rl-entry-price small{
            color:#f1f3ed !important;
            font-size:12px !important;
            font-weight:800 !important;
            letter-spacing:.08em !important;
            vertical-align:middle !important;
        }
        .rafflelb-raffle-product .woocommerce-product-details__short-description{
            max-width:720px !important;
            margin-bottom:20px !important;
            color:#c9cdc4 !important;
            font-size:14px !important;
            line-height:1.72 !important;
        }

        .rafflelb-raffle-product .rafflelb-live-panel{
            margin:20px 0 24px !important;
            padding:24px !important;
            border:1px solid #30332e !important;
            border-radius:16px !important;
            background:linear-gradient(145deg,#171916,#10120f) !important;
            box-shadow:0 20px 55px rgba(0,0,0,.25) !important;
        }
        .rafflelb-raffle-product .rafflelb-live-panel .rlp-counts strong{
            color:#f7f8f4 !important;
            font-size:36px !important;
        }
        .rafflelb-raffle-product .rafflelb-live-panel .rlp-bar{
            height:8px !important;
            background:#333731 !important;
        }

        .rafflelb-raffle-product form.cart{
            margin-top:0 !important;
            padding:0 !important;
            border:0 !important;
            background:transparent !important;
            box-shadow:none !important;
        }
        .rafflelb-raffle-product .rl-entry-label{
            display:block !important;
            width:100% !important;
            margin:0 0 12px !important;
            color:#f0f2ec !important;
            font-size:11px !important;
            font-weight:900 !important;
            letter-spacing:.08em !important;
        }
        .rafflelb-raffle-product form.cart .quantity{
            height:54px !important;
            margin-right:12px !important;
            border:1px solid #343833 !important;
            border-radius:9px !important;
            overflow:hidden !important;
            background:#141613 !important;
        }
        .rafflelb-raffle-product form.cart .quantity input,
        .rafflelb-raffle-product form.cart .quantity button{
            height:52px !important;
            background:#141613 !important;
            color:#f7f8f4 !important;
            -webkit-text-fill-color:#f7f8f4 !important;
        }
        .rafflelb-raffle-product form.cart .single_add_to_cart_button{
            min-height:54px !important;
            padding:0 38px !important;
            border:0 !important;
            border-radius:999px !important;
            background:#caff16 !important;
            color:#080908 !important;
            -webkit-text-fill-color:#080908 !important;
            font-size:12px !important;
            font-weight:900 !important;
            letter-spacing:.07em !important;
            box-shadow:0 8px 28px rgba(202,255,22,.10) !important;
        }
        .rafflelb-raffle-product form.cart .single_add_to_cart_button:hover{
            background:#bdf20e !important;
            color:#080908 !important;
        }

        .rafflelb-raffle-product .rl-entry-trust{
            display:flex !important;
            flex-wrap:wrap !important;
            align-items:center !important;
            gap:10px !important;
            margin-top:20px !important;
        }
        .rafflelb-raffle-product .rl-entry-trust span{
            display:inline-flex !important;
            align-items:center !important;
            min-height:34px !important;
            padding:8px 12px !important;
            border:1px solid #30342e !important;
            border-radius:999px !important;
            background:#111310 !important;
            color:#c8ccc3 !important;
            font-size:10px !important;
            line-height:1.2 !important;
            font-weight:800 !important;
            letter-spacing:.055em !important;
            white-space:nowrap !important;
        }
        .rafflelb-raffle-product .rl-entry-trust span:before,
        .rafflelb-raffle-product .rl-entry-trust span:after{
            display:none !important;
            content:none !important;
        }

        .rafflelb-raffle-product .product_meta{
            display:none !important;
        }
        .rafflelb-raffle-product .woocommerce-tabs,
        .rafflelb-raffle-product .product-tabs-wrapper .woocommerce-tabs{
            display:none !important;
        }

        /* Dedicated default Raffle Details section. */
        .rafflelb-raffle-product .rl-raffle-details-section{
            width:100% !important;
            margin:0 !important;
            padding:42px 24px 72px !important;
            background:linear-gradient(180deg,#090a09 0%,#070807 100%) !important;
        }
        .rafflelb-raffle-product .rl-raffle-details-inner{
            width:100% !important;
            max-width:1320px !important;
            margin:0 auto !important;
            padding:0 30px 30px !important;
            border:1px solid #2c302a !important;
            border-radius:18px !important;
            background:linear-gradient(145deg,#111310,#0b0c0a) !important;
            box-shadow:0 22px 60px rgba(0,0,0,.22) !important;
        }
        .rafflelb-raffle-product .rl-raffle-details-heading{
            display:flex !important;
            justify-content:center !important;
            padding:24px 0 20px !important;
            border-bottom:1px solid #2b2e29 !important;
        }
        .rafflelb-raffle-product .rl-raffle-details-heading span{
            position:relative !important;
            color:#f5f6f2 !important;
            font-size:15px !important;
            font-weight:900 !important;
            letter-spacing:.13em !important;
        }
        .rafflelb-raffle-product .rl-raffle-details-heading span:after{
            content:"" !important;
            position:absolute !important;
            left:50% !important;
            bottom:-20px !important;
            width:42px !important;
            height:2px !important;
            background:#caff16 !important;
            transform:translateX(-50%) !important;
        }
        .rafflelb-raffle-product .rl-raffle-details-grid{
            display:grid !important;
            grid-template-columns:repeat(4,minmax(0,1fr)) !important;
            column-gap:34px !important;
            padding-top:28px !important;
        }
        .rafflelb-raffle-product .rl-raffle-detail-col{
            min-width:0 !important;
            padding:8px 26px 4px 0 !important;
            border-right:1px solid #232821 !important;
        }
        .rafflelb-raffle-product .rl-raffle-detail-col:first-child{
            padding-left:10px !important;
        }
        .rafflelb-raffle-product .rl-raffle-detail-col:last-child{
            padding-right:10px !important;
            border-right:0 !important;
        }
        .rafflelb-raffle-product .rl-raffle-detail-col h3{
            position:relative !important;
            margin:0 0 18px !important;
            padding-top:14px !important;
            color:#eef2ea !important;
            font-size:12px !important;
            font-weight:900 !important;
            letter-spacing:.11em !important;
            text-align:left !important;
        }
        .rafflelb-raffle-product .rl-raffle-detail-col h3:before{
            content:"" !important;
            position:absolute !important;
            top:0 !important;
            left:0 !important;
            width:26px !important;
            height:3px !important;
            border-radius:99px !important;
            background:#caff16 !important;
        }
        .rafflelb-raffle-product .rl-raffle-detail-col p,
        .rafflelb-raffle-product .rl-raffle-detail-col li{
            color:#c7cbc2 !important;
            font-size:13px !important;
            line-height:1.72 !important;
        }
        .rafflelb-raffle-product .rl-raffle-detail-col p{
            margin:0 0 6px !important;
            text-align:left !important;
        }
        .rafflelb-raffle-product .rl-raffle-detail-col p strong{
            color:#f5f6f2 !important;
            font-weight:750 !important;
        }
        .rafflelb-raffle-product .rl-raffle-detail-list{
            list-style:none !important;
            counter-reset:rlraffledetail !important;
            margin:0 !important;
            padding:0 !important;
        }
        .rafflelb-raffle-product .rl-raffle-detail-list li{
            position:relative !important;
            padding-left:30px !important;
            margin-bottom:12px !important;
            counter-increment:rlraffledetail !important;
        }
        .rafflelb-raffle-product .rl-raffle-detail-list li:last-child{
            margin-bottom:0 !important;
        }
        .rafflelb-raffle-product .rl-raffle-detail-list li:before{
            content:counter(rlraffledetail) !important;
            position:absolute !important;
            top:0 !important;
            left:0 !important;
            width:19px !important;
            height:19px !important;
            display:flex !important;
            align-items:center !important;
            justify-content:center !important;
            border:1px solid rgba(202,255,22,.45) !important;
            border-radius:50% !important;
            background:rgba(202,255,22,.08) !important;
            color:#caff16 !important;
            font-size:10px !important;
            font-weight:800 !important;
            line-height:1 !important;
        }

        /* WoodMart can leave white wrappers around the single product. Force the
           approved RaffleLB dark surface all the way through the content area. */
        .rafflelb-raffle-product .main-page-wrapper,
        .rafflelb-raffle-product .site-content,
        .rafflelb-raffle-product .product-image-summary-wrap,
        .rafflelb-raffle-product .product-image-summary,
        .rafflelb-raffle-product .product-tabs-wrapper,
        .rafflelb-raffle-product .wd-page-content,
        .rafflelb-raffle-product .shop-content-area{
            background-color:#090a09 !important;
        }
        .rafflelb-raffle-product .product-image-summary > .container,
        .rafflelb-raffle-product .product-image-summary-inner{
            max-width:1320px !important;
            margin-left:auto !important;
            margin-right:auto !important;
        }
        .rafflelb-raffle-product .product-image-summary-inner{
            padding:28px !important;
            border:1px solid #272b26 !important;
            border-radius:20px !important;
            background:linear-gradient(145deg,#0f110f 0%,#0a0b0a 100%) !important;
            box-shadow:0 26px 80px rgba(0,0,0,.28) !important;
        }

        @media(min-width:1025px){
            .rafflelb-raffle-product .product-image-summary-inner > .product-images{
                width:46% !important;
                flex:0 0 46% !important;
                max-width:46% !important;
            }
            .rafflelb-raffle-product .product-image-summary-inner > .summary{
                width:54% !important;
                flex:0 0 54% !important;
                max-width:54% !important;
            }
        }
        @media(max-width:1024px){
            .rafflelb-raffle-product .product-images{padding-right:0 !important}
            .rafflelb-raffle-product .summary-inner{padding-left:0 !important;padding-top:24px !important}
            .rafflelb-raffle-product .product_title{font-size:36px !important}
            .rafflelb-raffle-product .rl-raffle-details-grid{grid-template-columns:repeat(2,minmax(0,1fr)) !important}
            .rafflelb-raffle-product .rl-raffle-detail-col{border-bottom:1px solid #2a2d28 !important}
            .rafflelb-raffle-product .rl-raffle-detail-col:nth-child(2){border-right:0 !important}
            .rafflelb-raffle-product .rl-raffle-detail-col:nth-child(3),
            .rafflelb-raffle-product .rl-raffle-detail-col:nth-child(4){border-bottom:0 !important}
        }
        @media(max-width:600px){
            .rafflelb-raffle-product .product-image-summary{padding-top:18px !important;padding-bottom:34px !important}
            .rafflelb-raffle-product .product_title{font-size:30px !important;line-height:1.12 !important}
            .rafflelb-raffle-product .rl-entry-price{font-size:30px !important}
            .rafflelb-raffle-product .rafflelb-live-panel{padding:18px !important}
            .rafflelb-raffle-product form.cart .single_add_to_cart_button{width:calc(100% - 104px) !important;padding:0 16px !important}
            .rafflelb-raffle-product .rl-entry-trust{display:grid !important;grid-template-columns:1fr !important;gap:8px !important}
            .rafflelb-raffle-product .rl-entry-trust span{justify-content:center !important;width:100% !important;padding:10px 12px !important}
            .rafflelb-raffle-product .rl-raffle-details-section{padding:28px 14px 50px !important}
            .rafflelb-raffle-product .rl-raffle-details-inner{padding:0 18px 8px !important}
            .rafflelb-raffle-product .rl-raffle-details-grid{grid-template-columns:1fr !important;padding-top:10px !important}
            .rafflelb-raffle-product .rl-raffle-detail-col,
            .rafflelb-raffle-product .rl-raffle-detail-col:first-child,
            .rafflelb-raffle-product .rl-raffle-detail-col:last-child{
                padding:22px 4px !important;
                border-right:0 !important;
                border-bottom:1px solid #2a2d28 !important;
            }
            .rafflelb-raffle-product .rl-raffle-detail-col:last-child{border-bottom:0 !important}
            .rafflelb-raffle-product .rl-raffle-detail-col h3{text-align:left !important}
            .rafflelb-raffle-product .rl-raffle-detail-col p{text-align:left !important}
        }

        /* =====================================================
           v0.23.1 — alignment / WoodMart gallery hardening
           ===================================================== */

        /* Keep the main two-column card wide, balanced and aligned. */
        .rafflelb-raffle-product .product-image-summary > .container,
        .rafflelb-raffle-product .product-image-summary-inner{
            width:calc(100% - 48px) !important;
            max-width:1320px !important;
        }
        .rafflelb-raffle-product .product-image-summary-inner{
            display:flex !important;
            gap:46px !important;
            padding:34px !important;
        }
        .rafflelb-raffle-product .product-images{
            padding-right:0 !important;
            margin:0 !important;
            align-self:stretch !important;
        }
        .rafflelb-raffle-product .summary{
            min-width:0 !important;
            padding:0 !important;
            margin:0 !important;
        }
        .rafflelb-raffle-product .summary-inner{
            width:100% !important;
            max-width:none !important;
            padding:0 !important;
            margin:0 !important;
        }

        /* WoodMart carousel wrappers were shrinking the main product image. */
        .rafflelb-raffle-product .product-images-inner,
        .rafflelb-raffle-product .woocommerce-product-gallery,
        .rafflelb-raffle-product .woocommerce-product-gallery__wrapper,
        .rafflelb-raffle-product .wd-carousel-container,
        .rafflelb-raffle-product .wd-carousel,
        .rafflelb-raffle-product .wd-carousel-wrap{
            width:100% !important;
            max-width:100% !important;
            min-width:100% !important;
        }
        .rafflelb-raffle-product .woocommerce-product-gallery__wrapper{
            display:block !important;
            transform:none !important;
        }
        .rafflelb-raffle-product .woocommerce-product-gallery__image,
        .rafflelb-raffle-product .wd-carousel-item{
            width:100% !important;
            max-width:100% !important;
            min-width:100% !important;
            flex:0 0 100% !important;
            margin:0 !important;
        }
        .rafflelb-raffle-product .woocommerce-product-gallery__image > a,
        .rafflelb-raffle-product .woocommerce-product-gallery__image img,
        .rafflelb-raffle-product .product-image-wrap img{
            display:block !important;
            width:100% !important;
            max-width:100% !important;
            height:auto !important;
            margin:0 !important;
        }

        /* Give the image and summary the same visual start line. */
        .rafflelb-raffle-product .woocommerce-product-gallery,
        .rafflelb-raffle-product .product-image-wrap{
            width:100% !important;
            min-height:0 !important;
        }

        /* Make the title use the available right column instead of wrapping too early. */
        .rafflelb-raffle-product .product_title{
            width:100% !important;
            max-width:100% !important;
            font-size:clamp(34px,2.5vw,46px) !important;
        }
        .rafflelb-raffle-product .woocommerce-product-details__short-description{
            width:100% !important;
            max-width:680px !important;
        }

        /* Keep all three assurances on one clean line on desktop. */
        .rafflelb-raffle-product .rl-entry-trust{
            display:flex !important;
            flex-wrap:nowrap !important;
            align-items:center !important;
            gap:0 !important;
            width:100% !important;
            margin-top:18px !important;
        }
        .rafflelb-raffle-product .rl-entry-trust span{
            min-height:auto !important;
            padding:0 16px !important;
            border:0 !important;
            border-right:1px solid #3a3d37 !important;
            border-radius:0 !important;
            background:transparent !important;
            color:#cdd1c8 !important;
            font-size:10px !important;
            line-height:1.35 !important;
            white-space:nowrap !important;
            text-align:center !important;
        }
        .rafflelb-raffle-product .rl-entry-trust span:first-child{padding-left:0 !important}
        .rafflelb-raffle-product .rl-entry-trust span:last-child{border-right:0 !important;padding-right:0 !important}

        /* More balanced controls. */
        .rafflelb-raffle-product form.cart{
            display:flex !important;
            flex-wrap:wrap !important;
            align-items:center !important;
            gap:14px !important;
        }
        .rafflelb-raffle-product .rl-entry-label{
            flex:0 0 100% !important;
            margin:0 0 2px !important;
        }
        .rafflelb-raffle-product form.cart .quantity{
            margin:0 !important;
        }
        .rafflelb-raffle-product form.cart .single_add_to_cart_button{
            margin:0 !important;
        }

        /* Details section lines/columns remain perfectly aligned. */
        .rafflelb-raffle-product .rl-raffle-details-section{
            padding-top:48px !important;
        }
        .rafflelb-raffle-product .rl-raffle-details-inner{
            max-width:1320px !important;
        }
        .rafflelb-raffle-product .rl-raffle-details-grid{
            align-items:stretch !important;
        }
        .rafflelb-raffle-product .rl-raffle-detail-col{
            height:100% !important;
        }

        /* WoodMart sticky add-to-cart duplicates RaffleLB's custom product controls.
         * Disable it for every RaffleLB product mode (Store Only, Raffle Only, Store + Raffle). */
        body.rafflelb-product-page .wd-sticky-btn,
        body.rafflelb-product-page .wd-sticky-btn-wrapper,
        body.rafflelb-product-page .wd-sticky-add-to-cart,
        body.rafflelb-product-page .sticky-add-to-cart,
        body.rafflelb-product-page .wd-sticky-btn-on,
        body.rafflelb-product-page.wd-sticky-btn-on .wd-sticky-btn,
        body.rafflelb-product-page.wd-sticky-btn-on .wd-sticky-btn-wrapper{
            display:none !important;
            visibility:hidden !important;
            opacity:0 !important;
            pointer-events:none !important;
        }

        /* 0.2.55 — WoodMart reserves bottom page space for its sticky add-to-cart
         * even when the sticky controls themselves are hidden. Remove that reserved
         * height as well so no empty white strip remains below the RaffleLB footer. */
        body.rafflelb-product-page{
            --wd-sticky-btn-height:0px !important;
        }
        body.rafflelb-product-page.wd-sticky-btn-on,
        body.rafflelb-product-page.wd-sticky-btn-on-mb,
        body.rafflelb-product-page.sticky-toolbar-on.wd-sticky-btn-on,
        body.rafflelb-product-page.sticky-toolbar-on.wd-sticky-btn-on-mb{
            padding-bottom:0 !important;
        }
        body.rafflelb-product-page .wd-sticky-btn,
        body.rafflelb-product-page .wd-sticky-btn-wrapper,
        body.rafflelb-product-page .wd-sticky-btn-container{
            height:0 !important;
            min-height:0 !important;
            max-height:0 !important;
            margin:0 !important;
            padding:0 !important;
            border:0 !important;
            background:transparent !important;
            box-shadow:none !important;
            overflow:hidden !important;
        }

        @media(min-width:1025px){
            .rafflelb-raffle-product .product-image-summary-inner > .product-images{
                width:48% !important;
                flex:0 0 calc(48% - 23px) !important;
                max-width:calc(48% - 23px) !important;
            }
            .rafflelb-raffle-product .product-image-summary-inner > .summary{
                width:52% !important;
                flex:1 1 calc(52% - 23px) !important;
                max-width:none !important;
            }
        }

        @media(max-width:1024px){
            .rafflelb-raffle-product .product-image-summary > .container,
            .rafflelb-raffle-product .product-image-summary-inner{
                width:calc(100% - 28px) !important;
            }
            .rafflelb-raffle-product .product-image-summary-inner{
                display:block !important;
                padding:22px !important;
            }
            .rafflelb-raffle-product .summary-inner{
                padding-top:26px !important;
            }
            .rafflelb-raffle-product .rl-entry-trust{
                flex-wrap:wrap !important;
                gap:8px 0 !important;
            }
        }

        @media(max-width:600px){
            .rafflelb-raffle-product .rl-entry-trust{
                display:grid !important;
                grid-template-columns:1fr !important;
                gap:8px !important;
            }
            .rafflelb-raffle-product .rl-entry-trust span,
            .rafflelb-raffle-product .rl-entry-trust span:first-child,
            .rafflelb-raffle-product .rl-entry-trust span:last-child{
                width:100% !important;
                padding:10px 12px !important;
                border:1px solid #30342e !important;
                border-radius:999px !important;
                background:#111310 !important;
                text-align:center !important;
            }
        }

        /* =====================================================
           v0.23.2 — footer isolation
           Keep raffle product styling inside the product content only.
           WoodMart footer containers must retain the site's normal footer layout.
           ===================================================== */
        .rafflelb-raffle-product footer,
        .rafflelb-raffle-product footer .container,
        .rafflelb-raffle-product .footer-container,
        .rafflelb-raffle-product .footer-container .container,
        .rafflelb-raffle-product .main-footer,
        .rafflelb-raffle-product .main-footer .container,
        .rafflelb-raffle-product .copyrights-wrapper,
        .rafflelb-raffle-product .copyrights-wrapper .container{
            background-image:none !important;
        }

        .rafflelb-raffle-product footer .container,
        .rafflelb-raffle-product .footer-container .container,
        .rafflelb-raffle-product .main-footer .container,
        .rafflelb-raffle-product .copyrights-wrapper .container{
            width:auto;
        }
        
        /* =====================================================
           v0.28.8 — RESTORE SHORT + FULL PRODUCT DESCRIPTIONS
           ===================================================== */

        .rafflelb-raffle-product .rl-raffle-short-description{
            max-width:720px!important;
            margin:10px 0 18px!important;
            padding:13px 15px!important;
            border-left:2px solid #caff16!important;
            border-radius:0 10px 10px 0!important;
            background:rgba(202,255,22,.025)!important;
            color:#cbd1c7!important;
            font-size:14px!important;
            line-height:1.7!important;
        }

        .rafflelb-raffle-product .rl-raffle-short-description p{
            margin:0!important;
            color:inherit!important;
        }

        .rafflelb-raffle-product .rl-raffle-product-description{
            margin:0 0 30px!important;
            padding:24px 26px!important;
            border:1px solid #2d332b!important;
            border-radius:14px!important;
            background:linear-gradient(145deg,#10130f,#0a0c09)!important;
            color:#cbd1c7!important;
        }

        .rafflelb-raffle-product .rl-raffle-description-kicker{
            margin:0 0 12px!important;
            color:#caff16!important;
            font-size:10px!important;
            line-height:1!important;
            font-weight:900!important;
            letter-spacing:.12em!important;
            text-transform:uppercase!important;
        }

        .rafflelb-raffle-product .rl-raffle-description-content{
            color:#cbd1c7!important;
            font-size:14px!important;
            line-height:1.75!important;
        }

        .rafflelb-raffle-product .rl-raffle-description-content p:last-child{
            margin-bottom:0!important;
        }

        .rafflelb-raffle-product .rl-raffle-description-content ul,
        .rafflelb-raffle-product .rl-raffle-description-content ol{
            padding-left:20px!important;
        }

        @media(max-width:767px){
            .rafflelb-raffle-product .rl-raffle-short-description{
                font-size:13px!important;
            }

            .rafflelb-raffle-product .rl-raffle-product-description{
                padding:20px 18px!important;
                margin-bottom:22px!important;
            }

            .rafflelb-raffle-product .rl-raffle-description-content{
                font-size:13px!important;
            }
        }

        /* =====================================================
           v0.29.2 — PREMIUM DIRECT PURCHASE PANEL
           ===================================================== */
        .rafflelb-raffle-product .rl-buy-now-panel{
            display:grid!important;
            grid-template-columns:minmax(260px,1fr) auto!important;
            gap:34px!important;
            align-items:center!important;
            margin:22px 0 0!important;
            padding:24px 26px!important;
            border:1px solid #343a32!important;
            border-radius:16px!important;
            background:
                radial-gradient(circle at 90% 18%,rgba(255,255,255,.05),transparent 30%),
                linear-gradient(145deg,#121510 0%,#0a0c09 78%)!important;
            box-shadow:
                inset 0 1px 0 rgba(255,255,255,.025),
                0 14px 34px rgba(0,0,0,.18)!important;
        }

        .rafflelb-raffle-product .rl-buy-now-copy{
            min-width:0!important;
        }

        .rafflelb-raffle-product .rl-buy-now-kicker{
            display:inline-flex!important;
            align-items:center!important;
            min-height:24px!important;
            margin:0 0 9px!important;
            padding:0 9px!important;
            border:1px solid rgba(255,255,255,.10)!important;
            border-radius:999px!important;
            color:#b9c0b5!important;
            background:rgba(255,255,255,.018)!important;
            font-size:8px!important;
            line-height:1!important;
            font-weight:900!important;
            letter-spacing:.13em!important;
            text-transform:uppercase!important;
        }

        .rafflelb-raffle-product .rl-buy-now-copy strong{
            display:block!important;
            margin:0 0 6px!important;
            color:#ffffff!important;
            font-size:22px!important;
            line-height:1.08!important;
            font-weight:900!important;
            letter-spacing:-.02em!important;
        }

        .rafflelb-raffle-product .rl-buy-now-copy p{
            max-width:520px!important;
            margin:0!important;
            color:#b8beb5!important;
            font-size:12px!important;
            line-height:1.6!important;
        }

        .rafflelb-raffle-product .rl-buy-now-action{
            display:flex!important;
            align-items:center!important;
            gap:18px!important;
        }

        .rafflelb-raffle-product .rl-buy-now-price-wrap{
            min-width:138px!important;
            text-align:left!important;
        }

        .rafflelb-raffle-product .rl-buy-now-price-label{
            display:block!important;
            margin:0 0 5px!important;
            color:#7f887d!important;
            font-size:8px!important;
            line-height:1!important;
            font-weight:850!important;
            letter-spacing:.12em!important;
            text-transform:uppercase!important;
        }

        .rafflelb-raffle-product .rl-buy-now-price{
            color:#ffffff!important;
            font-size:29px!important;
            line-height:1!important;
            font-weight:900!important;
            letter-spacing:-.03em!important;
            white-space:nowrap!important;
        }

        .rafflelb-raffle-product .rl-buy-now-price .woocommerce-Price-amount,
        .rafflelb-raffle-product .rl-buy-now-price .woocommerce-Price-currencySymbol{
            color:#ffffff!important;
        }

        .rafflelb-raffle-product .rl-buy-now-form{
            margin:0!important;
        }

        .rafflelb-raffle-product .rl-buy-now-button{
            display:inline-flex!important;
            align-items:center!important;
            justify-content:space-between!important;
            gap:24px!important;
            min-width:190px!important;
            min-height:54px!important;
            padding:0 20px!important;
            border:1px solid #caff16!important;
            border-radius:10px!important;
            background:#caff16!important;
            color:#080908!important;
            box-shadow:
                inset 0 1px 0 rgba(255,255,255,.02),
                0 0 0 1px rgba(202,255,22,.03)!important;
            font-size:10px!important;
            line-height:1!important;
            font-weight:900!important;
            letter-spacing:.09em!important;
            text-transform:uppercase!important;
            transition:.18s ease!important;
        }

        .rafflelb-raffle-product .rl-buy-now-button span,
        .rafflelb-raffle-product .rl-buy-now-button b{
            color:inherit!important;
        }

        .rafflelb-raffle-product .rl-buy-now-button:hover{
            background:#d5ff43!important;
            border-color:#d5ff43!important;
            color:#080908!important;
            transform:translateY(-1px)!important;
            box-shadow:0 10px 26px rgba(202,255,22,.14)!important;
        }

        @media(max-width:767px){
            .rafflelb-raffle-product .rl-buy-now-panel{
                grid-template-columns:1fr!important;
                gap:16px!important;
                padding:18px!important;
            }

            .rafflelb-raffle-product .rl-buy-now-action{
                display:grid!important;
                grid-template-columns:1fr!important;
                gap:12px!important;
            }

            .rafflelb-raffle-product .rl-buy-now-price-wrap{
                min-width:0!important;
                text-align:left!important;
            }

            .rafflelb-raffle-product .rl-buy-now-button{
                width:100%!important;
            }
        }


        /* =====================================================
           v0.29.4 — BUY NOW CARD CONTAINMENT FIX
           Keep all direct-purchase content inside the card.
           ===================================================== */
        .rafflelb-raffle-product .rl-buy-now-panel{
            grid-template-columns:minmax(0,1.15fr) minmax(0,.85fr)!important;
            gap:20px!important;
            overflow:hidden!important;
        }

        .rafflelb-raffle-product .rl-buy-now-action{
            display:grid!important;
            grid-template-columns:minmax(110px,.75fr) minmax(150px,1fr)!important;
            gap:14px!important;
            align-items:center!important;
            min-width:0!important;
            width:100%!important;
        }

        .rafflelb-raffle-product .rl-buy-now-price-wrap{
            min-width:0!important;
            width:100%!important;
        }

        .rafflelb-raffle-product .rl-buy-now-price{
            font-size:26px!important;
            white-space:nowrap!important;
        }

        .rafflelb-raffle-product .rl-buy-now-form{
            min-width:0!important;
            width:100%!important;
        }

        .rafflelb-raffle-product .rl-buy-now-button{
            width:100%!important;
            min-width:0!important;
            max-width:100%!important;
            padding:0 16px!important;
            box-sizing:border-box!important;
        }

        @media(max-width:1100px){
            .rafflelb-raffle-product .rl-buy-now-panel{
                grid-template-columns:1fr!important;
            }

            .rafflelb-raffle-product .rl-buy-now-action{
                grid-template-columns:minmax(120px,.7fr) minmax(170px,1fr)!important;
            }
        }

        @media(max-width:767px){
            .rafflelb-raffle-product .rl-buy-now-action{
                grid-template-columns:1fr!important;
            }

            .rafflelb-raffle-product .rl-buy-now-price{
                font-size:25px!important;
            }

            .rafflelb-raffle-product .rl-buy-now-button{
                width:100%!important;
            }
        }

        /* =====================================================
           v0.29.6 — STABLE RETAIL-FIRST HIERARCHY
           Rebuilt from v0.29.4. No form-hook layout manipulation.
           ===================================================== */
        .rafflelb-raffle-product .rl-buy-now-panel{
            display:block!important;
            width:100%!important;
            margin:20px 0 18px!important;
            padding:22px!important;
            overflow:hidden!important;
            box-sizing:border-box!important;
            border:1px solid rgba(202,255,22,.42)!important;
            border-radius:16px!important;
            background:
                radial-gradient(circle at 92% 10%,rgba(202,255,22,.07),transparent 34%),
                linear-gradient(145deg,#121610 0%,#090c08 78%)!important;
        }

        .rafflelb-raffle-product .rl-buy-now-copy{
            width:100%!important;
            min-width:0!important;
            margin:0 0 18px!important;
        }

        .rafflelb-raffle-product .rl-buy-now-copy > strong{
            display:block!important;
            margin-top:8px!important;
            font-size:22px!important;
            line-height:1.08!important;
        }

        .rafflelb-raffle-product .rl-buy-now-copy > p{
            max-width:460px!important;
            margin:7px 0 0!important;
        }

        .rafflelb-raffle-product .rl-buy-now-kicker{
            color:#caff16!important;
            border-color:rgba(202,255,22,.32)!important;
        }

        .rafflelb-raffle-product .rl-buy-now-action{
            display:grid!important;
            grid-template-columns:minmax(0,1fr) minmax(170px,210px)!important;
            gap:18px!important;
            align-items:end!important;
            width:100%!important;
            min-width:0!important;
        }

        .rafflelb-raffle-product .rl-buy-now-price-wrap,
        .rafflelb-raffle-product .rl-buy-now-form{
            width:100%!important;
            min-width:0!important;
            margin:0!important;
        }

        .rafflelb-raffle-product .rl-buy-now-price{
            font-size:28px!important;
            line-height:1.05!important;
            white-space:nowrap!important;
        }

        .rafflelb-raffle-product .rl-buy-now-button{
            width:100%!important;
            min-width:0!important;
            max-width:100%!important;
            height:54px!important;
            margin:0!important;
            padding:0 18px!important;
            box-sizing:border-box!important;
            background:#caff16!important;
            border:1px solid #caff16!important;
            color:#070907!important;
            box-shadow:none!important;
        }

        .rafflelb-raffle-product .rl-raffle-secondary-intro{
            width:100%!important;
            margin:20px 0 13px!important;
            padding:14px 16px!important;
            box-sizing:border-box!important;
            border-left:2px solid #caff16!important;
            background:rgba(255,255,255,.018)!important;
        }

        .rafflelb-raffle-product .rl-raffle-secondary-kicker{
            display:block!important;
            margin:0 0 5px!important;
            color:#8d968b!important;
            font-size:8px!important;
            line-height:1.2!important;
            font-weight:900!important;
            letter-spacing:.14em!important;
        }

        .rafflelb-raffle-product .rl-raffle-secondary-intro strong{
            display:block!important;
            margin:0!important;
            color:#f3f5f1!important;
            font-size:15px!important;
            line-height:1.25!important;
        }

        .rafflelb-raffle-product .rl-raffle-secondary-intro p{
            margin:5px 0 0!important;
            color:#9aa197!important;
            font-size:10px!important;
            line-height:1.4!important;
        }

        .rafflelb-raffle-product .rl-raffle-secondary-intro p b{
            color:#caff16!important;
        }

        /* Raffle remains functional but visually secondary. */
        .rafflelb-raffle-product form.cart .single_add_to_cart_button{
            background:transparent!important;
            border:1px solid #caff16!important;
            color:#f5f7f2!important;
            box-shadow:none!important;
        }

        .rafflelb-raffle-product form.cart .single_add_to_cart_button:hover{
            background:rgba(202,255,22,.08)!important;
            color:#caff16!important;
        }

        @media(max-width:767px){
            .rafflelb-raffle-product .rl-buy-now-panel{
                padding:18px!important;
            }
            .rafflelb-raffle-product .rl-buy-now-action{
                grid-template-columns:1fr!important;
                gap:14px!important;
            }
            .rafflelb-raffle-product .rl-buy-now-button{
                width:100%!important;
            }
        }

        /* =====================================================
           v0.29.7 — PRODUCT ACTION ORDER + CTA VISIBILITY
           Retail first: Buy Now -> raffle availability -> Enter Raffle.
           ===================================================== */
        .rafflelb-raffle-product form.cart .single_add_to_cart_button,
        .rafflelb-raffle-product form.cart button.single_add_to_cart_button{
            background:#caff16!important;
            border:1px solid #caff16!important;
            color:#070907!important;
            box-shadow:0 8px 22px rgba(202,255,22,.12)!important;
            opacity:1!important;
        }

        .rafflelb-raffle-product form.cart .single_add_to_cart_button:hover,
        .rafflelb-raffle-product form.cart button.single_add_to_cart_button:hover{
            background:#d5ff39!important;
            border-color:#d5ff39!important;
            color:#070907!important;
        }

        .rafflelb-raffle-product form.cart .single_add_to_cart_button:disabled,
        .rafflelb-raffle-product form.cart button.single_add_to_cart_button:disabled{
            background:#caff16!important;
            border-color:#caff16!important;
            color:#070907!important;
            opacity:.55!important;
        }

        /* =====================================================
           v0.29.8 — CLEAR RETAIL / RAFFLE SEPARATION
           ===================================================== */
        .rafflelb-raffle-product .rl-buy-now-panel{
            margin-bottom:28px!important;
        }

        /* Separator before the raffle availability section */
        .rafflelb-raffle-product .rl-buy-now-panel + *{
            position:relative;
        }

        .rafflelb-raffle-product .rl-buy-now-panel + *:before{
            content:"RAFFLE OPTION";
            display:flex;
            align-items:center;
            gap:12px;
            width:100%;
            margin:0 0 22px;
            color:#9ba397;
            font-size:9px;
            line-height:1;
            font-weight:900;
            letter-spacing:.16em;
            white-space:nowrap;
        }

        .rafflelb-raffle-product .rl-buy-now-panel + *:after{
            content:"";
            position:absolute;
            top:4px;
            left:105px;
            right:0;
            height:1px;
            background:linear-gradient(90deg,rgba(202,255,22,.72),rgba(255,255,255,.12));
        }

        /* Make the secondary raffle introduction substantial and readable */
        .rafflelb-raffle-product .rl-raffle-secondary-intro{
            margin:24px 0 17px!important;
            padding:18px 20px!important;
            border-left:3px solid #caff16!important;
            background:linear-gradient(90deg,rgba(202,255,22,.045),rgba(255,255,255,.018))!important;
        }

        .rafflelb-raffle-product .rl-raffle-secondary-kicker{
            margin-bottom:7px!important;
            color:#aab2a7!important;
            font-size:9px!important;
            letter-spacing:.15em!important;
        }

        .rafflelb-raffle-product .rl-raffle-secondary-intro strong{
            font-size:19px!important;
            line-height:1.2!important;
        }

        .rafflelb-raffle-product .rl-raffle-secondary-intro p{
            margin-top:7px!important;
            font-size:12px!important;
            line-height:1.5!important;
        }

        @media(max-width:767px){
            .rafflelb-raffle-product .rl-buy-now-panel{
                margin-bottom:24px!important;
            }
            .rafflelb-raffle-product .rl-raffle-secondary-intro{
                padding:16px!important;
            }
            .rafflelb-raffle-product .rl-raffle-secondary-intro strong{
                font-size:18px!important;
            }
        }

        /* =====================================================
           v0.29.9 — UNIFIED RAFFLE OPTION CARD
           Two clear choices: Buy Direct / Enter the Raffle.
           ===================================================== */

        /* Remove the previous standalone separator treatment. */
        .rafflelb-raffle-product .rl-buy-now-panel{
            margin-bottom:24px!important;
        }

        .rafflelb-raffle-product .rl-buy-now-panel + *:before,
        .rafflelb-raffle-product .rl-buy-now-panel + *:after{
            content:none!important;
            display:none!important;
        }

        .rafflelb-raffle-product .rl-raffle-option-card{
            display:block!important;
            width:100%!important;
            margin:0 0 22px!important;
            padding:22px!important;
            box-sizing:border-box!important;
            border:1px solid #2c312a!important;
            border-radius:16px!important;
            background:
                radial-gradient(circle at 95% 8%,rgba(202,255,22,.035),transparent 32%),
                linear-gradient(145deg,#111410,#0b0d0b)!important;
            overflow:hidden!important;
        }

        .rafflelb-raffle-product .rl-raffle-option-head{
            margin:0 0 16px!important;
            padding:0 0 15px!important;
            border-bottom:1px solid rgba(255,255,255,.08)!important;
        }

        .rafflelb-raffle-product .rl-raffle-option-head > span{
            display:block!important;
            margin:0 0 6px!important;
            color:#caff16!important;
            font-size:9px!important;
            line-height:1.15!important;
            font-weight:900!important;
            letter-spacing:.15em!important;
        }

        .rafflelb-raffle-product .rl-raffle-option-head > strong{
            display:block!important;
            margin:0!important;
            color:#f6f8f3!important;
            font-size:20px!important;
            line-height:1.2!important;
            font-weight:900!important;
        }

        /* Progress becomes part of the unified raffle card, not a card inside a card. */
        .rafflelb-raffle-product .rl-raffle-option-card .rafflelb-live-panel{
            margin:0!important;
            padding:4px 0 18px!important;
            border:0!important;
            border-radius:0!important;
            background:transparent!important;
            box-shadow:none!important;
        }

        .rafflelb-raffle-product .rl-raffle-secondary-intro{
            margin:0 0 15px!important;
            padding:15px 0 0!important;
            border:0!important;
            border-top:1px solid rgba(255,255,255,.08)!important;
            background:transparent!important;
        }

        .rafflelb-raffle-product .rl-raffle-secondary-intro p{
            margin:0!important;
            color:#adb4aa!important;
            font-size:12px!important;
            line-height:1.55!important;
        }

        .rafflelb-raffle-product .rl-raffle-secondary-intro p b{
            color:#caff16!important;
            font-weight:900!important;
        }

        .rafflelb-raffle-product .rl-raffle-option-card form.cart{
            margin:0!important;
            padding:0!important;
        }

        .rafflelb-raffle-product .rl-raffle-option-card .rl-entry-trust{
            margin:16px 0 0!important;
            padding:15px 0 0!important;
            border-top:1px solid rgba(255,255,255,.08)!important;
        }

        .rafflelb-raffle-product .rl-raffle-option-card form.cart .single_add_to_cart_button,
        .rafflelb-raffle-product .rl-raffle-option-card form.cart button.single_add_to_cart_button{
            background:#caff16!important;
            border:1px solid #caff16!important;
            color:#070907!important;
            opacity:1!important;
            box-shadow:none!important;
        }

        @media(max-width:767px){
            .rafflelb-raffle-product .rl-raffle-option-card{
                padding:18px!important;
            }

            .rafflelb-raffle-product .rl-raffle-option-head > strong{
                font-size:18px!important;
            }
        }

        /* =====================================================
           v0.30.0 — LEFT COLUMN PRODUCT DETAILS
           Uses the empty space below the gallery for retail information.
           ===================================================== */
        .rafflelb-raffle-product .rl-product-details-panel{
            display:none;
        }

        .rafflelb-raffle-product .rl-product-details-panel.rl-product-details-mounted{
            display:block!important;
            width:100%!important;
            margin:26px 0 0!important;
            padding:0 4px 4px!important;
            box-sizing:border-box!important;
            color:#eef1ec!important;
        }

        .rafflelb-raffle-product .rl-product-details-head{
            padding:0 0 14px!important;
            margin:0 0 14px!important;
            border-bottom:1px solid rgba(255,255,255,.10)!important;
        }

        .rafflelb-raffle-product .rl-product-details-head > span{
            display:block!important;
            margin:0 0 6px!important;
            color:#caff16!important;
            font-size:9px!important;
            line-height:1.1!important;
            font-weight:900!important;
            letter-spacing:.15em!important;
        }

        .rafflelb-raffle-product .rl-product-details-head > strong{
            display:block!important;
            margin:0!important;
            color:#f6f8f3!important;
            font-size:21px!important;
            line-height:1.2!important;
            font-weight:900!important;
        }

        .rafflelb-raffle-product .rl-product-specs{
            display:grid!important;
            grid-template-columns:repeat(3,minmax(0,1fr))!important;
            gap:0!important;
            margin:0 0 16px!important;
            border-top:1px solid rgba(255,255,255,.07)!important;
            border-bottom:1px solid rgba(255,255,255,.07)!important;
        }

        .rafflelb-raffle-product .rl-product-spec-row{
            min-width:0!important;
            padding:13px 14px!important;
            border-right:1px solid rgba(255,255,255,.07)!important;
        }

        .rafflelb-raffle-product .rl-product-spec-row:first-child{
            padding-left:0!important;
        }

        .rafflelb-raffle-product .rl-product-spec-row:last-child{
            border-right:0!important;
        }

        .rafflelb-raffle-product .rl-product-spec-row > span{
            display:block!important;
            margin:0 0 5px!important;
            color:#7f887d!important;
            font-size:8px!important;
            line-height:1.1!important;
            font-weight:900!important;
            letter-spacing:.13em!important;
            text-transform:uppercase!important;
        }

        .rafflelb-raffle-product .rl-product-spec-row > strong{
            display:block!important;
            color:#f0f3ee!important;
            font-size:12px!important;
            line-height:1.35!important;
            font-weight:800!important;
            overflow-wrap:anywhere!important;
        }

        .rafflelb-raffle-product .rl-product-retail-notes{
            display:flex!important;
            flex-wrap:wrap!important;
            gap:9px 18px!important;
            margin:0 0 18px!important;
            color:#aeb6ab!important;
            font-size:10px!important;
            line-height:1.4!important;
        }

        .rafflelb-raffle-product .rl-product-retail-notes span:first-child{
            color:#caff16!important;
        }

        .rafflelb-raffle-product .rl-product-description{
            margin-top:4px!important;
            color:#a8afa5!important;
            font-size:11px!important;
            line-height:1.7!important;
        }

        .rafflelb-raffle-product .rl-product-description p{
            margin:0 0 10px!important;
        }

        @media(max-width:767px){
            .rafflelb-raffle-product .rl-product-details-panel.rl-product-details-mounted{
                margin-top:20px!important;
                padding:0!important;
            }

            .rafflelb-raffle-product .rl-product-specs{
                grid-template-columns:1fr!important;
            }

            .rafflelb-raffle-product .rl-product-spec-row,
            .rafflelb-raffle-product .rl-product-spec-row:first-child{
                padding:11px 0!important;
                border-right:0!important;
                border-bottom:1px solid rgba(255,255,255,.07)!important;
            }

            .rafflelb-raffle-product .rl-product-spec-row:last-child{
                border-bottom:0!important;
            }
        }

        /* =====================================================
           v0.30.1 — PRODUCT PAGE VISUAL POLISH
           ===================================================== */

        /* Hide the redundant entry-price line directly under the title.
           The raffle card already communicates the entry price clearly. */
        .rafflelb-raffle-product .summary > .price,
        .rafflelb-raffle-product .summary .price.rafflelb-entry-price,
        .rafflelb-raffle-product .summary > p.price{
            display:none!important;
        }

        /* Product information: match the short-description lime accent language. */
        .rafflelb-raffle-product .rl-product-details-panel.rl-product-details-mounted{
            position:relative!important;
            padding:18px 20px 18px 22px!important;
            margin-top:24px!important;
            border-left:3px solid #caff16!important;
            background:
                linear-gradient(90deg,rgba(202,255,22,.035),rgba(255,255,255,.012))!important;
            box-sizing:border-box!important;
        }

        .rafflelb-raffle-product .rl-product-details-head{
            margin:0 0 14px!important;
            padding:0 0 12px!important;
        }

        /* Direct-purchase price should carry the same lime emphasis as raffle pricing. */
        .rafflelb-raffle-product .rl-buy-now-price{
            color:#caff16!important;
            font-weight:900!important;
        }

        /* Make BUY NOW visually match ENTER RAFFLE: centered, compact, deliberate. */
        .rafflelb-raffle-product .rl-buy-now-button{
            display:flex!important;
            align-items:center!important;
            justify-content:center!important;
            text-align:center!important;
            gap:0!important;
            min-height:54px!important;
            padding:0 24px!important;
            font-size:11px!important;
            font-weight:900!important;
            letter-spacing:.08em!important;
            text-transform:uppercase!important;
        }

        /* Remove/neutralize any arrow or pseudo-decoration that pushes the label left. */
        .rafflelb-raffle-product .rl-buy-now-button:before,
        .rafflelb-raffle-product .rl-buy-now-button:after{
            content:none!important;
            display:none!important;
        }

        .rafflelb-raffle-product .rl-buy-now-button svg,
        .rafflelb-raffle-product .rl-buy-now-button i{
            display:none!important;
        }

        .rafflelb-raffle-product .rl-buy-now-action{
            align-items:center!important;
        }

        @media(max-width:767px){
            .rafflelb-raffle-product .rl-product-details-panel.rl-product-details-mounted{
                padding:16px 16px 16px 18px!important;
            }
        }

        /* =====================================================
           v0.30.2 — REMOVE DUPLICATE SINGLE PRICE + CTA POLISH
           ===================================================== */

        /* WooCommerce price output is intentionally removed on raffle single
           product pages; force-hide any cached/theme wrapper as a fallback. */
        .rafflelb-raffle-product .summary .rl-entry-price{
            display:none!important;
        }

        /* wc_price() adds nested amount/bdi markup; color every level lime. */
        .rafflelb-raffle-product .rl-buy-now-price,
        .rafflelb-raffle-product .rl-buy-now-price .woocommerce-Price-amount,
        .rafflelb-raffle-product .rl-buy-now-price .woocommerce-Price-currencySymbol,
        .rafflelb-raffle-product .rl-buy-now-price bdi{
            color:#caff16!important;
        }

        /* BUY NOW should visually match ENTER RAFFLE exactly: centered label,
           no separate arrow occupying the right edge. */
        .rafflelb-raffle-product .rl-buy-now-button{
            display:flex!important;
            align-items:center!important;
            justify-content:center!important;
            text-align:center!important;
        }

        .rafflelb-raffle-product .rl-buy-now-button > span{
            display:block!important;
            width:auto!important;
            margin:0!important;
            text-align:center!important;
        }

        .rafflelb-raffle-product .rl-buy-now-button > b{
            display:none!important;
        }

        /* =====================================================
           v0.30.3 — SIMPLIFIED RAFFLE DETAILS
           Remove duplicated product description; keep 4-column guide.
           ===================================================== */
        .rafflelb-raffle-product .rl-raffle-details-heading{
            margin-bottom:18px!important;
        }

        .rafflelb-raffle-product .rl-raffle-details-grid{
            margin-top:0!important;
        }

        /* =====================================================
           v0.30.4 — MOBILE PRODUCT FLOW
           Mobile order: image -> product/purchase/raffle -> product details.
           ===================================================== */
        @media(max-width:767px){
            .rafflelb-raffle-product .summary .rl-product-details-panel.rl-product-details-mounted{
                width:100%!important;
                margin:24px 0 0!important;
                padding:18px 18px 18px 20px!important;
                border-left:3px solid #caff16!important;
                background:linear-gradient(90deg,rgba(202,255,22,.035),rgba(255,255,255,.012))!important;
            }

            .rafflelb-raffle-product .summary .rl-product-details-head > strong{
                font-size:20px!important;
            }

            .rafflelb-raffle-product .summary .rl-product-description{
                font-size:12px!important;
                line-height:1.65!important;
            }
        }

        /* v0.30.5 — remove stray divider between product area and Raffle Details */
        .rafflelb-raffle-product .rl-raffle-details-section{
            border-top:0!important;
        }

        .rafflelb-raffle-product .rl-raffle-details-section:before,
        .rafflelb-raffle-product .rl-raffle-details-section:after{
            content:none!important;
            display:none!important;
        }

        .rafflelb-raffle-product .product-image-summary-wrap + hr,
        .rafflelb-raffle-product .product-image-summary + hr,
        .rafflelb-raffle-product .summary + hr{
            display:none!important;
            border:0!important;
        }

        /* =====================================================
           v0.30.6 — REMOVE OUTER BETWEEN-SECTIONS DIVIDER
           The visible line is on the outer product/detail boundary,
           not the Raffle Details card itself.
           ===================================================== */
        .rafflelb-raffle-product .product-image-summary-wrap{
            border-bottom:0!important;
        }

        .rafflelb-raffle-product .product-image-summary{
            border-bottom:0!important;
        }

        .rafflelb-raffle-product .product-image-summary-wrap:after,
        .rafflelb-raffle-product .product-image-summary:after{
            content:none!important;
            display:none!important;
            border:0!important;
        }

        .rafflelb-raffle-product .woocommerce-tabs,
        .rafflelb-raffle-product .wd-accordion,
        .rafflelb-raffle-product .rl-raffle-details-section{
            border-top:0!important;
        }

        /* Custom layout may render a dedicated divider before the details area. */
        .rafflelb-raffle-product .rl-product-divider,
        .rafflelb-raffle-product .rl-details-divider,
        .rafflelb-raffle-product .wd-separator,
        .rafflelb-raffle-product .wd-products-nav + .wd-separator{
            display:none!important;
            border:0!important;
            height:0!important;
            margin:0!important;
        }

        /* v0.30.7 — bring Raffle Details slightly closer to main product area */
        .rafflelb-raffle-product .rl-raffle-details-section{
            margin-top:-28px!important;
        }

        @media(max-width:767px){
            .rafflelb-raffle-product .rl-raffle-details-section{
                margin-top:-12px!important;
            }
        }

        /* v0.30.8 — stronger emphasis for raffle entry price */
        .rafflelb-raffle-product .rl-raffle-secondary-intro p b{
            color:#caff16!important;
            font-size:15px!important;
            line-height:1!important;
            font-weight:900!important;
            letter-spacing:.01em!important;
        }

        @media(max-width:767px){
            .rafflelb-raffle-product .rl-raffle-secondary-intro p b{
                font-size:14px!important;
            }
        }
        /* v0.1.28 — structural single-product layout.
           Presentation-only: native WooCommerce gallery, forms and Draw Engine
           progress markup remain intact. */
        body.rafflelb-raffle-product .main-page-wrapper{background:#080b09!important}
        body.rafflelb-raffle-product .product-image-summary{max-width:1320px!important;margin:0 auto!important;padding:42px 24px 64px!important;border:0!important}
        body.rafflelb-raffle-product .product-image-summary-inner{display:block!important}
        body.rafflelb-raffle-product .rl-product-layout{display:grid!important;grid-template-columns:minmax(0,1fr) minmax(0,1.04fr)!important;gap:38px!important;align-items:start!important;width:100%!important;clear:both!important}
        body.rafflelb-raffle-product .rl-product-left,body.rafflelb-raffle-product .rl-product-right{float:none!important;clear:none!important;width:auto!important;min-width:0!important;margin:0!important}
        body.rafflelb-raffle-product .product-image-summary .product-images,
        body.rafflelb-raffle-product .product-image-summary .woocommerce-product-gallery,
        body.rafflelb-raffle-product .product-image-summary .wd-product-gallery{min-width:0!important}
        body.rafflelb-raffle-product .woocommerce-product-gallery__wrapper,
        body.rafflelb-raffle-product .product-images .woocommerce-product-gallery__image{overflow:hidden!important;border:1px solid rgba(186,255,0,.4)!important;border-radius:18px!important;background:#030503!important;box-shadow:0 0 32px rgba(186,255,0,.07)!important}
        body.rafflelb-raffle-product .woocommerce-product-gallery__image img{width:100%!important;aspect-ratio:1/1!important;object-fit:contain!important}
        body.rafflelb-raffle-product .flex-control-thumbs{display:flex!important;gap:10px!important;margin:14px 0 20px!important;overflow-x:auto!important;scrollbar-width:none!important}
        body.rafflelb-raffle-product .flex-control-thumbs li{float:none!important;flex:0 0 88px!important;width:88px!important;margin:0!important;border:1px solid #303a2e!important;border-radius:9px!important;overflow:hidden!important;background:#050705!important}
        body.rafflelb-raffle-product .flex-control-thumbs li img{height:76px!important;width:100%!important;object-fit:cover!important;opacity:.72!important}
        body.rafflelb-raffle-product .flex-control-thumbs li img.flex-active{opacity:1!important;outline:2px solid #baff00!important;outline-offset:-2px!important}
        body.rafflelb-raffle-product .summary{min-width:0!important;padding:0!important}
        body.rafflelb-raffle-product .product_title{margin:16px 0 12px!important;color:#f7f8f3!important;font-size:clamp(34px,3.1vw,54px)!important;line-height:1.05!important;font-weight:900!important;letter-spacing:-.045em!important}
        body.rafflelb-raffle-product .rl-raffle-short-description{margin:0 0 24px!important;padding:0!important;border:0!important;background:none!important;color:#bfc7bd!important;font-size:16px!important;line-height:1.55!important}
        body.rafflelb-raffle-product .rl-buy-now-panel,
        body.rafflelb-raffle-product .rl-raffle-option-card{margin:0 0 22px!important;padding:26px 28px!important;border:1px solid rgba(186,255,0,.56)!important;border-radius:16px!important;background:linear-gradient(125deg,#121811,#090d09)!important;box-shadow:0 10px 30px rgba(0,0,0,.2),inset 0 1px 0 rgba(255,255,255,.035)!important}
        body.rafflelb-raffle-product .rl-buy-now-panel{display:grid!important;grid-template-columns:minmax(0,1fr) auto!important;gap:22px!important;align-items:center!important}
        body.rafflelb-raffle-product .rl-buy-now-copy{min-width:0!important}
        body.rafflelb-raffle-product .rl-buy-now-kicker{display:inline-flex!important;margin:0 0 10px!important;padding:5px 10px!important;border:1px solid rgba(186,255,0,.58)!important;border-radius:999px!important;color:#baff00!important;font-size:9px!important;font-weight:900!important;letter-spacing:.12em!important}
        body.rafflelb-raffle-product .rl-buy-now-copy strong{display:block!important;color:#fff!important;font-size:24px!important;line-height:1.15!important;font-weight:850!important}
        body.rafflelb-raffle-product .rl-buy-now-copy p{max-width:490px!important;margin:8px 0 0!important;color:#c0c8be!important;font-size:13px!important;line-height:1.55!important}
        body.rafflelb-raffle-product .rl-buy-now-action{display:grid!important;grid-template-columns:auto minmax(164px,1fr)!important;gap:18px!important;align-items:end!important;min-width:330px!important}
        body.rafflelb-raffle-product .rl-buy-now-price-label{display:block!important;margin-bottom:6px!important;color:#aab4a5!important;font-size:9px!important;font-weight:800!important;letter-spacing:.12em!important}
        body.rafflelb-raffle-product .rl-buy-now-price,.rafflelb-raffle-product .rl-buy-now-price *{white-space:nowrap!important;color:#baff00!important;font-size:29px!important;font-weight:900!important;line-height:1!important}
        body.rafflelb-raffle-product .rl-buy-now-button{display:flex!important;align-items:center!important;justify-content:center!important;gap:12px!important;min-height:54px!important;width:100%!important;border:1px solid #baff00!important;border-radius:10px!important;background:#baff00!important;color:#060806!important;font-size:12px!important;font-weight:900!important;letter-spacing:.07em!important}
        body.rafflelb-raffle-product .rl-raffle-option-card{display:block!important;border-color:#35412f!important;background:linear-gradient(125deg,#101510,#090c09)!important}
        body.rafflelb-raffle-product .rl-raffle-option-head{display:flex!important;align-items:end!important;justify-content:space-between!important;gap:16px!important;margin:0 0 18px!important;padding:0 0 16px!important;border-bottom:1px solid rgba(255,255,255,.1)!important}
        body.rafflelb-raffle-product .rl-raffle-option-head span{order:2!important;display:inline-flex!important;align-items:center!important;gap:7px!important;padding:8px 13px!important;border:1px solid rgba(186,255,0,.42)!important;border-radius:999px!important;color:#baff00!important;font-size:10px!important;font-weight:900!important;letter-spacing:.1em!important}
        body.rafflelb-raffle-product .rl-raffle-option-head strong{color:#fff!important;font-size:24px!important;line-height:1.12!important;font-weight:850!important}
        body.rafflelb-raffle-product .rafflelb-live-panel{margin:0 0 20px!important;padding:20px!important;border:1px solid #283127!important;border-radius:13px!important;background:#0b100c!important;box-shadow:none!important}
        body.rafflelb-raffle-product .rafflelb-live-panel .rlp-status{background:#baff00!important;color:#071004!important}
        body.rafflelb-raffle-product .rafflelb-live-panel .rlp-bar{height:10px!important;background:#303933!important}
        body.rafflelb-raffle-product .rafflelb-live-panel .rlp-bar span{background:#baff00!important}
        body.rafflelb-raffle-product .rl-raffle-secondary-intro{margin:0 0 18px!important;padding:0!important}
        body.rafflelb-raffle-product .rl-raffle-secondary-intro p{margin:0!important;color:#d9ded7!important;font-size:15px!important}
        body.rafflelb-raffle-product .rl-entry-label{margin:0 0 9px!important;color:#abb4a7!important;font-size:9px!important;font-weight:900!important;letter-spacing:.11em!important}
        body.rafflelb-raffle-product form.cart{display:grid!important;grid-template-columns:148px minmax(0,1fr)!important;gap:14px!important;align-items:center!important;margin:0!important;padding:0!important}
        body.rafflelb-raffle-product form.cart .quantity{height:56px!important;margin:0!important;border-color:#4a5645!important;border-radius:10px!important;background:#0b100c!important}
        body.rafflelb-raffle-product form.cart .single_add_to_cart_button{min-height:56px!important;width:100%!important;border-radius:10px!important;background:#baff00!important;border-color:#baff00!important;color:#071004!important;font-size:13px!important;font-weight:900!important;letter-spacing:.055em!important}
        body.rafflelb-raffle-product .rl-entry-trust{display:grid!important;grid-template-columns:repeat(3,minmax(0,1fr))!important;gap:0!important;margin:20px 0 0!important;padding:17px 0 0!important;border-top:1px solid rgba(255,255,255,.1)!important}
        body.rafflelb-raffle-product .rl-entry-trust span{justify-content:center!important;padding:0 10px!important;border-right:1px solid rgba(255,255,255,.1)!important;color:#dce3da!important;font-size:9px!important}
        body.rafflelb-raffle-product .rl-product-details-panel{margin:0!important;padding:24px 26px!important;border:1px solid #344032!important;border-radius:16px!important;background:linear-gradient(135deg,#121611,#0b0e0b)!important}
        body.rafflelb-raffle-product .rl-product-details-head{display:flex!important;align-items:baseline!important;justify-content:space-between!important;gap:15px!important;margin-bottom:17px!important;padding-bottom:15px!important;border-bottom:1px solid rgba(255,255,255,.1)!important}
        body.rafflelb-raffle-product .rl-product-details-head span{color:#baff00!important;font-size:9px!important;font-weight:900!important;letter-spacing:.12em!important}
        body.rafflelb-raffle-product .rl-product-details-head strong{color:#fff!important;font-size:21px!important}
        body.rafflelb-raffle-product .rl-product-spec-row{padding:5px 0!important;border:0!important}
        body.rafflelb-raffle-product .rl-product-description{margin-top:15px!important;padding-top:15px!important;border-top:1px solid rgba(255,255,255,.1)!important}
        body.rafflelb-raffle-product .rl-raffle-details-section{max-width:1320px!important;margin:0 auto!important;padding:4px 24px 72px!important;background:transparent!important}
        body.rafflelb-raffle-product .rl-raffle-details-inner{border-color:#303a2e!important;border-radius:17px!important;background:linear-gradient(135deg,#101410,#090c09)!important}
        @media(max-width:900px){body.rafflelb-raffle-product .rl-product-layout{grid-template-columns:1fr!important;gap:24px!important}body.rafflelb-raffle-product .rl-product-details-panel{order:2!important}body.rafflelb-raffle-product .rl-buy-now-panel{grid-template-columns:1fr!important}body.rafflelb-raffle-product .rl-buy-now-action{min-width:0!important}}
        /* v0.1.29 — stable desktop product composition.  The action column is
           intentionally a single vertical rail; the former nested two-column
           grid was what compressed Buy Direct copy into one-word lines. */
        @media(min-width:901px){
            body.rafflelb-raffle-product .rl-product-layout{grid-template-columns:minmax(0,1fr) minmax(0,1fr)!important;gap:36px!important}
            body.rafflelb-raffle-product .rl-buy-now-panel{grid-template-columns:minmax(0,1fr) 178px!important;gap:26px!important;min-height:174px!important}
            body.rafflelb-raffle-product .rl-buy-now-copy p{max-width:none!important}
            body.rafflelb-raffle-product .rl-buy-now-action{display:flex!important;flex-direction:column!important;align-items:stretch!important;justify-content:center!important;gap:20px!important;min-width:0!important;width:178px!important}
            body.rafflelb-raffle-product .rl-buy-now-price-wrap{padding:0 0 0 18px!important;border-left:1px solid rgba(255,255,255,.12)!important}
            body.rafflelb-raffle-product .rl-buy-now-button{min-height:52px!important;white-space:nowrap!important}
            body.rafflelb-raffle-product .rl-raffle-option-card{padding:24px 26px!important}
            body.rafflelb-raffle-product .rl-raffle-option-card form.cart{display:flex!important;align-items:center!important;gap:16px!important}
            body.rafflelb-raffle-product .rl-raffle-option-card form.cart .quantity{flex:0 0 148px!important}
            body.rafflelb-raffle-product .rl-raffle-option-card form.cart .single_add_to_cart_button{flex:1 1 auto!important}
            body.rafflelb-raffle-product .rl-raffle-option-card .rl-entry-label{display:none!important}
        }
        /* Keep a single DOM structure at every breakpoint.  At narrower widths
           the left column becomes transparent so its gallery, summary and
           details can follow the intended reading order without JS relocation. */
        @media(max-width:900px){
            body.rafflelb-raffle-product .rl-product-layout{display:grid!important;grid-template-columns:1fr!important;gap:24px!important}
            body.rafflelb-raffle-product .rl-product-left{display:contents!important}
            body.rafflelb-raffle-product .rl-product-left .product-images,body.rafflelb-raffle-product .rl-product-left .woocommerce-product-gallery,body.rafflelb-raffle-product .rl-product-left .wd-product-gallery{order:1!important}
            body.rafflelb-raffle-product .rl-product-right{order:2!important}
            body.rafflelb-raffle-product .rl-product-details-panel{order:3!important}
        }
        @media(max-width:767px){body.rafflelb-raffle-product .product-image-summary{padding:22px 14px 40px!important}body.rafflelb-raffle-product .product_title{font-size:32px!important;margin-top:8px!important}body.rafflelb-raffle-product .rl-raffle-short-description{font-size:14px!important;margin-bottom:18px!important}body.rafflelb-raffle-product .rl-buy-now-panel,body.rafflelb-raffle-product .rl-raffle-option-card{padding:19px!important;margin-bottom:15px!important;border-radius:13px!important}body.rafflelb-raffle-product .rl-buy-now-copy strong,body.rafflelb-raffle-product .rl-raffle-option-head strong{font-size:21px!important}body.rafflelb-raffle-product .rl-buy-now-action{grid-template-columns:1fr!important;gap:14px!important;align-items:start!important}body.rafflelb-raffle-product .rl-buy-now-button{min-height:52px!important}body.rafflelb-raffle-product .rl-raffle-option-head{align-items:flex-start!important;flex-direction:column!important;margin-bottom:15px!important}.rafflelb-raffle-product .rl-raffle-option-head span{order:0!important}body.rafflelb-raffle-product form.cart{grid-template-columns:104px minmax(0,1fr)!important;gap:10px!important}body.rafflelb-raffle-product .rl-entry-trust{grid-template-columns:1fr!important;gap:11px!important}body.rafflelb-raffle-product .rl-entry-trust span{justify-content:flex-start!important;padding:0!important;border:0!important}body.rafflelb-raffle-product .rl-product-details-panel{padding:19px!important}body.rafflelb-raffle-product .rl-raffle-details-section{padding:0 14px 44px!important}}
</style>
        <?php
    }

    public static function legacy_callback_18334() {
    if (!function_exists('is_shop')) return;
    $is_shop = is_shop();
    $is_cat  = function_exists('is_product_category') && is_product_category();
    if (!$is_shop && !$is_cat) return;
    ?>
    <style id="rafflelb-mobile-store-card-alignment-v03370">
    @media (max-width:767px){
        body.rafflelb-raffle-archive .products{
            align-items:stretch!important;
        }
        body.rafflelb-raffle-archive .rl-raffle-card{
            display:flex!important;
            height:100%!important;
            min-height:0!important;
        }
        body.rafflelb-raffle-archive .rl-raffle-card .product-wrapper{
            display:flex!important;
            flex-direction:column!important;
            width:100%!important;
            height:100%!important;
        }
        body.rafflelb-raffle-archive .rl-raffle-card .product-information{
            display:flex!important;
            flex:1 1 auto!important;
            flex-direction:column!important;
            height:100%!important;
            min-height:0!important;
        }

        /* Two identical text slots keep every element below them aligned. */
        body.rafflelb-raffle-archive .rl-raffle-card .wd-entities-title,
        body.rafflelb-raffle-archive .rl-raffle-card .product-title,
        body.rafflelb-raffle-archive .rl-raffle-card h3{
            display:-webkit-box!important;
            width:100%!important;
            height:42px!important;
            min-height:42px!important;
            max-height:42px!important;
            margin:0 0 8px!important;
            overflow:hidden!important;
            -webkit-box-orient:vertical!important;
            -webkit-line-clamp:2!important;
            line-clamp:2!important;
            line-height:1.32!important;
            text-align:center!important;
        }
        body.rafflelb-raffle-archive .rl-raffle-card .wd-entities-title a,
        body.rafflelb-raffle-archive .rl-raffle-card .product-title a,
        body.rafflelb-raffle-archive .rl-raffle-card h3 a{
            display:-webkit-box!important;
            overflow:hidden!important;
            -webkit-box-orient:vertical!important;
            -webkit-line-clamp:2!important;
            line-clamp:2!important;
        }
        body.rafflelb-raffle-archive .rl-raffle-card .wd-product-cats,
        body.rafflelb-raffle-archive .rl-raffle-card .product-categories{
            display:flex!important;
            align-items:center!important;
            justify-content:center!important;
            width:100%!important;
            height:30px!important;
            min-height:30px!important;
            max-height:30px!important;
            margin:0 0 8px!important;
            overflow:hidden!important;
            line-height:1.3!important;
            text-align:center!important;
        }
        body.rafflelb-raffle-archive .rl-raffle-card .wd-product-cats a,
        body.rafflelb-raffle-archive .rl-raffle-card .product-categories a{
            display:-webkit-box!important;
            overflow:hidden!important;
            -webkit-box-orient:vertical!important;
            -webkit-line-clamp:2!important;
            line-clamp:2!important;
        }

        /* Keep the lower action area pinned consistently when card content varies. */
        body.rafflelb-raffle-archive .rl-raffle-card .rl-shop-card-actions{
            margin-top:auto!important;
        }
    }
    /* 0.1.95 — typography/readability only. Geometry and behavior are frozen. */
    body.rafflelb-raffle-archive :is(.rl-shop-hero,#rl-shop-controls,.rl-raffle-card),
    body.rafflelb-raffle-archive :is(.rl-shop-hero,#rl-shop-controls,.rl-raffle-card) *{
        font-family:var(--rl-font,"Manrope",sans-serif)!important;
        text-rendering:optimizeLegibility;
        -webkit-font-smoothing:antialiased;
    }
    body.rafflelb-raffle-archive .rl-shop-heading::before{
        font-size:var(--rl-text-xs,10px)!important;
        font-weight:var(--rl-weight-bold,700)!important;
        line-height:var(--rl-line-heading,1.2)!important;
    }
    body.rafflelb-raffle-archive .rl-shop-title-row h1{
        font-size:48px!important;
        font-weight:var(--rl-weight-heavy,800)!important;
        line-height:var(--rl-line-heading,1.08)!important;
        letter-spacing:var(--rl-tracking-tight,-.025em)!important;
    }
    body.rafflelb-raffle-archive .rl-shop-title-row p{
        font-size:var(--rl-text-base,15px)!important;
        font-weight:var(--rl-weight-medium,500)!important;
        line-height:var(--rl-line-body,1.55)!important;
    }
    #rl-shop-controls .rl-shop-points-label{font-size:13px!important;font-weight:var(--rl-weight-semibold,600)!important}
    #rl-shop-controls .rl-shop-points-value{font-size:22px!important;font-weight:var(--rl-weight-heavy,800)!important}
    #rl-shop-controls .rl-shop-mode-eyebrow{font-size:11px!important;font-weight:var(--rl-weight-bold,700)!important}
    #rl-shop-controls .rl-shop-view-mode,
    #rl-shop-controls .rl-shop-toolbar-actions>.rl-shop-filter-toggle:not(.rl-shop-sort-trigger),
    #rl-shop-controls .rl-shop-sort-trigger{
        font-size:12px!important;
        font-weight:var(--rl-weight-bold,700)!important;
        line-height:var(--rl-line-heading,1.2)!important;
        letter-spacing:0!important;
    }
    body.rafflelb-raffle-archive .rl-raffle-card :is(.wd-product-cats,.product-categories),
    body.rafflelb-raffle-archive .rl-raffle-card :is(.wd-product-cats,.product-categories) a{
        font-size:10px!important;
        font-weight:var(--rl-weight-semibold,600)!important;
        line-height:1.35!important;
    }
    body.rafflelb-raffle-archive .rl-raffle-card :is(.wd-entities-title,.product-title,h3),
    body.rafflelb-raffle-archive .rl-raffle-card :is(.wd-entities-title,.product-title,h3) a{
        font-size:17px!important;
        font-weight:var(--rl-weight-bold,700)!important;
        line-height:1.35!important;
        letter-spacing:var(--rl-tracking-tight,-.015em)!important;
    }
    body.rafflelb-raffle-archive.rl-store-reference .rl-shop-prices:not(.is-dual-price) small{
        font-size:10px!important;
        font-weight:var(--rl-weight-semibold,600)!important;
        line-height:1.3!important;
    }
    body.rafflelb-raffle-archive.rl-store-reference .rl-shop-prices:not(.is-dual-price) strong,
    body.rafflelb-raffle-archive.rl-store-reference .rl-shop-prices:not(.is-dual-price) strong *{
        font-size:20px!important;
        font-weight:var(--rl-weight-heavy,800)!important;
        line-height:1.15!important;
    }
    body.rafflelb-raffle-archive.rl-store-reference .rl-shop-prices.is-retail-only strong,
    body.rafflelb-raffle-archive.rl-store-reference .rl-shop-prices.is-retail-only strong *{font-size:22px!important}
    body.rafflelb-raffle-archive .rl-shop-prices:not(.is-dual-price) .rl-shop-price-raffle .rl-shop-entry-value em{
        font-size:11px!important;
        font-weight:var(--rl-weight-medium,500)!important;
    }
    body.rafflelb-raffle-archive .rl-shop-raffle-live,
    body.rafflelb-raffle-archive .rl-shop-raffle-line strong,
    body.rafflelb-raffle-archive .rl-shop-raffle-line strong *,
    body.rafflelb-raffle-archive .rl-shop-raffle-meta,
    body.rafflelb-raffle-archive .rl-shop-raffle-meta *{
        font-size:11px!important;
        font-weight:var(--rl-weight-semibold,600)!important;
        line-height:1.35!important;
    }
    body.rafflelb-raffle-archive .rl-shop-buy,
    body.rafflelb-raffle-archive .rl-shop-buy-label,
    body.rafflelb-raffle-archive .rl-shop-enter-label,
    body.rafflelb-raffle-archive .rl-shop-enter-price,
    body.rafflelb-raffle-archive .rl-shop-enter-price *{
        font-size:12px!important;
        font-weight:var(--rl-weight-bold,700)!important;
        letter-spacing:0!important;
    }
    body.rafflelb-raffle-archive .rl-shop-card-actions-or{font-size:11px!important;font-weight:var(--rl-weight-medium,500)!important}
    @media(max-width:767px){
        body.rafflelb-raffle-archive .rl-shop-title-row h1{font-size:44px!important}
        body.rafflelb-raffle-archive .rl-shop-title-row p{font-size:15px!important}
        #rl-shop-controls .rl-shop-view-mode,
        #rl-shop-controls .rl-shop-toolbar-actions>.rl-shop-filter-toggle:not(.rl-shop-sort-trigger),
        #rl-shop-controls .rl-shop-sort-trigger{font-size:12px!important}
        body.rafflelb-raffle-archive .rl-raffle-card :is(.wd-entities-title,.product-title,h3),
        body.rafflelb-raffle-archive .rl-raffle-card :is(.wd-entities-title,.product-title,h3) a{font-size:16px!important;line-height:1.35!important}
        body.rafflelb-raffle-archive.rl-store-reference .rl-shop-prices:not(.is-dual-price) strong,
        body.rafflelb-raffle-archive.rl-store-reference .rl-shop-prices:not(.is-dual-price) strong *{font-size:18px!important}
        body.rafflelb-raffle-archive.rl-store-reference .rl-shop-prices:not(.is-dual-price) small{font-size:10px!important}
        body.rafflelb-raffle-archive .rl-shop-raffle-live,
        body.rafflelb-raffle-archive .rl-shop-raffle-line strong,
        body.rafflelb-raffle-archive .rl-shop-raffle-line strong *,
        body.rafflelb-raffle-archive .rl-shop-raffle-meta,
        body.rafflelb-raffle-archive .rl-shop-raffle-meta *{font-size:11px!important}
    }
    </style>
    <?php
}

    public static function legacy_callback_18430() {
    if (!function_exists('is_shop')) return;
    $is_shop = is_shop();
    $is_cat  = function_exists('is_product_category') && is_product_category();
    if (!$is_shop && !$is_cat) return;
    ?>
    <style id="rafflelb-mobile-store-card-equal-v03371">
    @media (max-width:767px){
        /* Make every grid cell a true equal-height flex item. */
        body.rafflelb-raffle-archive .products{
            align-items:stretch!important;
            grid-auto-rows:auto!important;
        }
        body.rafflelb-raffle-archive .products > .product,
        body.rafflelb-raffle-archive .products > .product-grid-item{
            display:flex!important;
            align-self:stretch!important;
            min-width:0!important;
            height:auto!important;
        }
        body.rafflelb-raffle-archive .rl-raffle-card{
            display:flex!important;
            width:100%!important;
            height:auto!important;
            min-height:0!important;
        }
        body.rafflelb-raffle-archive .rl-raffle-card .product-wrapper{
            display:flex!important;
            flex-direction:column!important;
            width:100%!important;
            height:100%!important;
            min-width:0!important;
            min-height:0!important;
            overflow:hidden!important;
            box-sizing:border-box!important;
        }
        body.rafflelb-raffle-archive .rl-raffle-card .product-information{
            display:flex!important;
            flex:1 1 auto!important;
            flex-direction:column!important;
            width:100%!important;
            height:auto!important;
            min-width:0!important;
            min-height:0!important;
            box-sizing:border-box!important;
        }

        /* Fixed two-line title slot: one-line and two-line names align. */
        body.rafflelb-raffle-archive .rl-raffle-card .wd-entities-title,
        body.rafflelb-raffle-archive .rl-raffle-card .product-title,
        body.rafflelb-raffle-archive .rl-raffle-card h3{
            display:flex!important;
            align-items:center!important;
            justify-content:center!important;
            width:100%!important;
            height:48px!important;
            min-height:48px!important;
            max-height:48px!important;
            margin:0 0 5px!important;
            padding:0 3px!important;
            overflow:hidden!important;
            box-sizing:border-box!important;
            text-align:center!important;
        }
        body.rafflelb-raffle-archive .rl-raffle-card .wd-entities-title a,
        body.rafflelb-raffle-archive .rl-raffle-card .product-title a,
        body.rafflelb-raffle-archive .rl-raffle-card h3 a{
            display:-webkit-box!important;
            width:100%!important;
            max-height:44px!important;
            overflow:hidden!important;
            -webkit-box-orient:vertical!important;
            -webkit-line-clamp:2!important;
            line-clamp:2!important;
            line-height:1.25!important;
            text-align:center!important;
        }

        /* Compact one-line category slot; no large empty vertical band. */
        body.rafflelb-raffle-archive .rl-raffle-card .wd-product-cats,
        body.rafflelb-raffle-archive .rl-raffle-card .product-categories{
            display:flex!important;
            align-items:center!important;
            justify-content:center!important;
            width:100%!important;
            height:22px!important;
            min-height:22px!important;
            max-height:22px!important;
            margin:0 0 8px!important;
            padding:0 2px!important;
            overflow:hidden!important;
            box-sizing:border-box!important;
            line-height:1.2!important;
            white-space:nowrap!important;
            text-align:center!important;
        }
        body.rafflelb-raffle-archive .rl-raffle-card .wd-product-cats a,
        body.rafflelb-raffle-archive .rl-raffle-card .product-categories a{
            display:block!important;
            max-width:100%!important;
            overflow:hidden!important;
            text-overflow:ellipsis!important;
            white-space:nowrap!important;
            line-height:1.2!important;
        }

        /* Never let WoodMart auto-margins create a giant gap before price. */
        body.rafflelb-raffle-archive .rl-raffle-card .price{
            width:100%!important;
            margin:0 0 8px!important;
            padding:0!important;
            flex:0 0 auto!important;
            box-sizing:border-box!important;
        }
        body.rafflelb-raffle-archive .rl-shop-prices,
        body.rafflelb-raffle-archive .rl-shop-raffle-box{
            width:100%!important;
            min-width:0!important;
            box-sizing:border-box!important;
        }

        /* Bottom actions remain inside the rounded card, never wider than it. */
        body.rafflelb-raffle-archive .rl-shop-card-actions{
            width:100%!important;
            min-width:0!important;
            max-width:100%!important;
            margin-top:auto!important;
            padding:0!important;
            overflow:hidden!important;
            box-sizing:border-box!important;
        }
        body.rafflelb-raffle-archive .rl-shop-buy-form{
            display:block!important;
            width:100%!important;
            min-width:0!important;
            max-width:100%!important;
            margin:0!important;
            padding:0!important;
            box-sizing:border-box!important;
        }
        body.rafflelb-raffle-archive .rl-shop-buy,
        body.rafflelb-raffle-archive .rl-shop-enter{
            width:100%!important;
            min-width:0!important;
            max-width:100%!important;
            margin:0!important;
            box-sizing:border-box!important;
            overflow:hidden!important;
        }
        body.rafflelb-raffle-archive .rl-shop-enter{
            padding-left:8px!important;
            padding-right:8px!important;
        }
        body.rafflelb-raffle-archive .rl-shop-enter > span{
            min-width:0!important;
            overflow:hidden!important;
            text-overflow:ellipsis!important;
        }
    }
    </style>
    <?php
}

    public static function legacy_callback_18600() {
    if (!function_exists('is_shop')) return;
    $is_shop = is_shop();
    $is_cat  = function_exists('is_product_category') && is_product_category();
    if (!$is_shop && !$is_cat) return;
    ?>
    <style id="rafflelb-store-card-polish-v014">
    /* Final presentation pass: keep product media square and frameless so the
       black-background product artwork reads like Featured Products instead of
       a tall framed image well. Applies to desktop and mobile archives. */
    body.rafflelb-raffle-archive .rl-raffle-card .product-element-top{
        align-self:start!important;
        width:100%!important;
        height:auto!important;
        min-height:0!important;
        max-height:none!important;
        aspect-ratio:1/1!important;
        border:0!important;
        border-radius:0!important;
        background:transparent!important;
        box-shadow:none!important;
        overflow:hidden!important;
    }
    body.rafflelb-raffle-archive .rl-raffle-card .product-element-top > a,
    body.rafflelb-raffle-archive .rl-raffle-card .product-element-top :is(.product-image-link,.product-image-wrap,.product-element-top-inner){
        display:flex!important;
        align-items:center!important;
        justify-content:center!important;
        width:100%!important;
        height:100%!important;
        min-height:0!important;
        aspect-ratio:1/1!important;
        margin:0!important;
        padding:0!important;
        border:0!important;
        border-radius:0!important;
        background:transparent!important;
        box-shadow:none!important;
        overflow:hidden!important;
    }
    body.rafflelb-raffle-archive .rl-raffle-card .product-element-top img{
        display:block!important;
        width:100%!important;
        height:100%!important;
        max-width:100%!important;
        max-height:100%!important;
        margin:0!important;
        padding:0!important;
        border:0!important;
        border-radius:0!important;
        background:transparent!important;
        box-shadow:none!important;
        object-fit:contain!important;
        object-position:center!important;
        transform:none!important;
        filter:none!important;
    }

    /* v0.2.42 — RAFFLE ENTRY price/suffix overlap fix.
       .rl-shop-entry-value (the <strong> price + <em>/ entry</em> suffix
       inside .rl-shop-price-raffle) only ever got a flex/baseline/gap
       layout inside the max-width:767px media query below, and inside the
       :not(.is-dual-price) scope used for raffle-only cards. Neither one
       covers the default desktop STORE & RAFFLE card, where the raffle
       price box is the narrower .85fr column of the dual-price grid
       (.rl-shop-prices.is-dual-price) and the price carries a large
       font-size (21-27px depending on breakpoint, see .rl-shop-price-raffle
       strong above). With no flex/gap/nowrap control at that scope,
       .rl-shop-entry-value stayed a plain inline <span>, so a wider price
       (15.00 vs 1.37) had no reserved space and could run into the " /
       entry" suffix right beside it. This applies the same flex layout the
       mobile rule below already proves correct, unconditionally, so every
       raffle price box gets it regardless of viewport or dual-price/
       raffle-only variant; the media query below still layers its own
       mobile-specific spacing on top via normal cascade order. */
    body.rafflelb-raffle-archive .rl-shop-price-raffle .rl-shop-entry-value{
        display:flex!important;
        flex-direction:row!important;
        align-items:baseline!important;
        flex-wrap:nowrap!important;
        gap:4px!important;
        min-width:0!important;
        max-width:100%!important;
    }
    body.rafflelb-raffle-archive .rl-shop-price-raffle .rl-shop-entry-value strong{
        flex:0 1 auto!important;
        min-width:0!important;
        white-space:nowrap!important;
        margin:0!important;
    }
    body.rafflelb-raffle-archive .rl-shop-price-raffle .rl-shop-entry-value em{
        flex:0 0 auto!important;
        white-space:nowrap!important;
    }

    @media (max-width:767px){
        /* Lock the amount first and the " / entry" suffix second. Explicit LTR
           isolation prevents the WooCommerce price markup from visually
           reordering the suffix around the currency symbol on narrow screens. */
        body.rafflelb-raffle-archive .rl-shop-price-raffle .rl-shop-entry-value{
            display:flex!important;
            flex-direction:row!important;
            align-items:baseline!important;
            justify-content:flex-start!important;
            gap:0!important;
            width:100%!important;
            min-width:0!important;
            max-width:100%!important;
            overflow:visible!important;
            white-space:nowrap!important;
            direction:ltr!important;
            unicode-bidi:isolate!important;
        }
        body.rafflelb-raffle-archive .rl-shop-price-raffle .rl-shop-entry-value strong{
            order:1!important;
            flex:0 1 auto!important;
            min-width:0!important;
            width:auto!important;
            margin:0!important;
            white-space:nowrap!important;
        }
        body.rafflelb-raffle-archive .rl-shop-price-raffle .rl-shop-entry-value em{
            order:2!important;
            flex:0 0 auto!important;
            display:inline-block!important;
            width:auto!important;
            margin:0 0 0 5px!important;
            padding:0!important;
            color:#d9dfd6!important;
            font-size:10px!important;
            line-height:1!important;
            font-style:normal!important;
            font-weight:500!important;
            white-space:nowrap!important;
        }
        body.rafflelb-raffle-archive .rl-shop-enter:not(.is-muted){
            display:flex!important;
            flex-direction:column!important;
            align-items:center!important;
            justify-content:center!important;
            gap:2px!important;
            min-width:0!important;
            padding:6px 8px!important;
            text-align:center!important;
            white-space:normal!important;
        }
        body.rafflelb-raffle-archive .rl-shop-enter:not(.is-muted) .rl-shop-enter-label,
        body.rafflelb-raffle-archive .rl-shop-enter:not(.is-muted) .rl-shop-enter-price{
            display:block!important;
            width:100%!important;
            min-width:0!important;
            max-width:100%!important;
            margin:0!important;
            overflow:visible!important;
            text-overflow:clip!important;
            white-space:nowrap!important;
            text-align:center!important;
            letter-spacing:0!important;
        }
        body.rafflelb-raffle-archive .rl-shop-enter:not(.is-muted) .rl-shop-enter-label{
            font-size:12px!important;
            line-height:1.05!important;
            font-weight:850!important;
        }
        body.rafflelb-raffle-archive .rl-shop-enter:not(.is-muted) .rl-shop-enter-price,
        body.rafflelb-raffle-archive .rl-shop-enter:not(.is-muted) .rl-shop-enter-price *{
            font-size:11px!important;
            line-height:1.05!important;
            font-weight:800!important;
        }
    }
    @media (max-width:390px){
        body.rafflelb-raffle-archive .rl-shop-enter:not(.is-muted) .rl-shop-enter-label{font-size:11px!important}
        body.rafflelb-raffle-archive .rl-shop-enter:not(.is-muted) .rl-shop-enter-price,
        body.rafflelb-raffle-archive .rl-shop-enter:not(.is-muted) .rl-shop-enter-price *{font-size:10px!important}
    }

    /* 0.1.19 — authoritative desktop presentation layer.
       This replaces the accumulated desktop test overrides. Mobile rules above
       are intentionally preserved byte-for-byte. */
    @media (min-width:768px){
        /* Crisp storefront typography. Keep geometry independent from type. */
        body.rafflelb-raffle-archive .rl-raffle-card,
        body.rafflelb-raffle-archive .rl-raffle-card *,
        body.rafflelb-raffle-archive #rl-shop-controls,
        body.rafflelb-raffle-archive #rl-shop-controls *{
            font-family:var(--rl-font,"Manrope",sans-serif)!important;
            text-shadow:none!important;
            -webkit-text-stroke:0!important;
            filter:none!important;
            font-style:normal!important;
            text-rendering:optimizeLegibility;
            -webkit-font-smoothing:antialiased;
        }
        body.rafflelb-raffle-archive .rl-raffle-card :is(.wd-product-cats,.product-categories),
        body.rafflelb-raffle-archive .rl-raffle-card :is(.wd-product-cats,.product-categories) a{
            color:#aebaa8!important;
            font-size:10px!important;
            font-weight:600!important;
            line-height:1.35!important;
            letter-spacing:.045em!important;
            text-transform:uppercase!important;
        }
        body.rafflelb-raffle-archive .rl-raffle-card :is(.wd-entities-title,.product-title,h3),
        body.rafflelb-raffle-archive .rl-raffle-card :is(.wd-entities-title,.product-title,h3) a{
            color:#f6f8f4!important;
            font-size:15px!important;
            font-weight:650!important;
            line-height:1.32!important;
            letter-spacing:-.012em!important;
        }

        /* Dedicated Store + Raffle desktop geometry is owned by
           assets/shop-reference.css. These legacy rules now serve solo rows. */
        body.rafflelb-raffle-archive .rl-shop-prices:not(.is-dual-price) > div{
            min-width:0!important;
            box-sizing:border-box!important;
            padding:9px 7px!important;
            border:1px solid #334032!important;
            border-radius:9px!important;
            background:#0a110c!important;
            overflow:hidden!important;
        }
        body.rafflelb-raffle-archive.rl-store-reference .rl-shop-prices:not(.is-dual-price) small{
            display:block!important;
            margin:0 0 5px!important;
            color:#aab4a6!important;
            font-size:9px!important;
            font-weight:550!important;
            line-height:1.2!important;
            letter-spacing:.04em!important;
        }
        body.rafflelb-raffle-archive.rl-store-reference .rl-shop-prices:not(.is-dual-price) .rl-shop-price-main strong,
        body.rafflelb-raffle-archive.rl-store-reference .rl-shop-prices:not(.is-dual-price) .rl-shop-price-main strong *,
        body.rafflelb-raffle-archive.rl-store-reference .rl-shop-prices:not(.is-dual-price) .rl-shop-price-raffle .rl-shop-entry-value strong,
        body.rafflelb-raffle-archive.rl-store-reference .rl-shop-prices:not(.is-dual-price) .rl-shop-price-raffle .rl-shop-entry-value strong *{
            font-size:clamp(15px,1.05vw,17px)!important;
            line-height:1.12!important;
            font-weight:750!important;
            letter-spacing:-.025em!important;
            white-space:nowrap!important;
        }
        body.rafflelb-raffle-archive.rl-store-reference .rl-shop-prices:not(.is-dual-price) .rl-shop-price-main strong,
        body.rafflelb-raffle-archive.rl-store-reference .rl-shop-prices:not(.is-dual-price) .rl-shop-price-main strong .woocommerce-Price-amount,
        body.rafflelb-raffle-archive.rl-store-reference .rl-shop-prices:not(.is-dual-price) .rl-shop-price-main strong bdi{
            display:inline-flex!important;
            align-items:baseline!important;
            min-width:0!important;
            width:auto!important;
            max-width:100%!important;
            white-space:nowrap!important;
        }
        body.rafflelb-raffle-archive.rl-store-reference .rl-shop-prices:not(.is-dual-price) .rl-shop-price-main strong .woocommerce-Price-currencySymbol{
            display:inline!important;
            flex:0 0 auto!important;
            margin-left:.16em!important;
            white-space:nowrap!important;
        }
        body.rafflelb-raffle-archive .rl-shop-prices:not(.is-dual-price) .rl-shop-price-raffle .rl-shop-entry-value{
            display:flex!important;
            align-items:baseline!important;
            flex-wrap:nowrap!important;
            gap:4px!important;
            width:100%!important;
            min-width:0!important;
            white-space:nowrap!important;
            direction:ltr!important;
            overflow:hidden!important;
        }
        body.rafflelb-raffle-archive .rl-shop-prices:not(.is-dual-price) .rl-shop-price-raffle .rl-shop-entry-value strong{
            flex:0 1 auto!important;
            min-width:0!important;
            margin:0!important;
            white-space:nowrap!important;
        }
        body.rafflelb-raffle-archive .rl-shop-prices:not(.is-dual-price) .rl-shop-price-raffle .rl-shop-entry-value em{
            flex:0 0 auto!important;
            display:inline!important;
            margin:0!important;
            padding:0!important;
            color:#c5cec1!important;
            font-size:9px!important;
            line-height:1!important;
            font-weight:500!important;
            white-space:nowrap!important;
        }

        /* STORE ONLY — match the approved reference: media left, information
           right, CTA under information (not full-card width). */
        body.rafflelb-raffle-archive.rl-shop-view-retail .rl-raffle-card .product-wrapper{
            display:grid!important;
            grid-template-columns:minmax(0,43%) minmax(0,57%)!important;
            grid-template-rows:26px minmax(58px,max-content) 54px 54px!important;
            column-gap:18px!important;
            row-gap:9px!important;
            align-content:start!important;
            min-height:247px!important;
            height:auto!important;
            padding:14px!important;
            border:1px solid rgba(186,255,0,.38)!important;
            border-radius:15px!important;
            background:linear-gradient(135deg,#0d160f 0%,#080d0a 72%)!important;
            box-shadow:0 10px 28px rgba(0,0,0,.24)!important;
            overflow:hidden!important;
        }
        body.rafflelb-raffle-archive.rl-shop-view-retail .rl-raffle-card .product-information,
        body.rafflelb-raffle-archive.rl-shop-view-retail .rl-raffle-card .product-element-bottom{
            display:contents!important;
        }
        body.rafflelb-raffle-archive.rl-shop-view-retail .rl-raffle-card .product-element-top{
            grid-column:1!important;
            grid-row:1 / 5!important;
            align-self:stretch!important;
            width:100%!important;
            height:100%!important;
            min-height:0!important;
            max-height:none!important;
            aspect-ratio:auto!important;
            margin:0!important;
            padding:0!important;
            border:0!important;
            border-radius:10px!important;
            background:#000!important;
            box-shadow:none!important;
            overflow:hidden!important;
        }
        body.rafflelb-raffle-archive.rl-shop-view-retail .rl-raffle-card .product-element-top > a,
        body.rafflelb-raffle-archive.rl-shop-view-retail .rl-raffle-card .product-element-top :is(.product-image-link,.product-image-wrap,.product-element-top-inner){
            display:flex!important;
            align-items:center!important;
            justify-content:center!important;
            width:100%!important;
            height:100%!important;
            margin:0!important;
            padding:0!important;
            border:0!important;
            background:#000!important;
            overflow:hidden!important;
        }
        body.rafflelb-raffle-archive.rl-shop-view-retail .rl-raffle-card .product-element-top img{
            display:block!important;
            width:100%!important;
            height:100%!important;
            max-width:100%!important;
            max-height:100%!important;
            margin:0!important;
            padding:0!important;
            object-fit:contain!important;
            object-position:center!important;
            transform:none!important;
            filter:none!important;
        }
        body.rafflelb-raffle-archive.rl-shop-view-retail .rl-raffle-card :is(.wd-product-cats,.product-categories){
            grid-column:2!important;
            grid-row:1!important;
            align-self:end!important;
            margin:0!important;
            min-height:0!important;
        }
        body.rafflelb-raffle-archive.rl-shop-view-retail .rl-raffle-card :is(.wd-entities-title,.product-title,h3){
            grid-column:2!important;
            grid-row:2!important;
            align-self:start!important;
            margin:0!important;
            min-height:0!important;
            height:auto!important;
            font-size:16px!important;
            font-weight:650!important;
            line-height:1.28!important;
        }
        body.rafflelb-raffle-archive.rl-shop-view-retail .rl-shop-prices.is-retail-only{
            grid-column:2!important;
            grid-row:3!important;
            align-self:stretch!important;
            justify-self:stretch!important;
            display:block!important;
            width:100%!important;
            max-width:100%!important;
            min-width:0!important;
            margin:0!important;
            padding:0!important;
            border:0!important;
            background:transparent!important;
            box-sizing:border-box!important;
        }
        body.rafflelb-raffle-archive.rl-shop-view-retail .rl-shop-prices.is-retail-only .rl-shop-price-main{
            display:flex!important;
            flex-direction:column!important;
            align-items:center!important;
            justify-content:center!important;
            width:100%!important;
            max-width:100%!important;
            height:54px!important;
            min-height:54px!important;
            margin:0!important;
            padding:5px 12px!important;
            border:1px solid #3a4938!important;
            border-radius:10px!important;
            background:#0a0f0b!important;
            box-sizing:border-box!important;
            overflow:hidden!important;
            text-align:center!important;
        }
        body.rafflelb-raffle-archive.rl-shop-view-retail .rl-shop-prices.is-retail-only .rl-shop-price-main small{
            display:block!important;
            width:100%!important;
            margin:0 0 2px!important;
            color:#aeb8ab!important;
            font-size:10px!important;
            font-weight:550!important;
            letter-spacing:.05em!important;
            text-align:center!important;
        }
        body.rafflelb-raffle-archive.rl-shop-view-retail .rl-shop-prices.is-retail-only .rl-shop-price-main strong,
        body.rafflelb-raffle-archive.rl-shop-view-retail .rl-shop-prices.is-retail-only .rl-shop-price-main strong .woocommerce-Price-amount,
        body.rafflelb-raffle-archive.rl-shop-view-retail .rl-shop-prices.is-retail-only .rl-shop-price-main strong bdi{
            display:inline-flex!important;
            align-items:baseline!important;
            justify-content:center!important;
            flex-wrap:nowrap!important;
            width:auto!important;
            min-width:0!important;
            max-width:100%!important;
            margin:0!important;
            color:#f7faf5!important;
            font-size:clamp(20px,1.55vw,24px)!important;
            font-weight:750!important;
            line-height:1!important;
            letter-spacing:-.03em!important;
            white-space:nowrap!important;
        }
        body.rafflelb-raffle-archive.rl-shop-view-retail .rl-shop-prices.is-retail-only .rl-shop-price-main strong .woocommerce-Price-currencySymbol{
            display:inline!important;
            flex:0 0 auto!important;
            margin-left:.18em!important;
            white-space:nowrap!important;
        }
        body.rafflelb-raffle-archive.rl-shop-view-retail .rl-shop-card-actions{
            grid-column:2!important;
            grid-row:4!important;
            align-self:end!important;
            display:flex!important;
            width:100%!important;
            min-width:0!important;
            margin:0!important;
            padding:0!important;
        }
        body.rafflelb-raffle-archive.rl-shop-view-retail .rl-shop-buy-form{
            display:block!important;
            width:100%!important;
            margin:0!important;
        }
        body.rafflelb-raffle-archive.rl-shop-view-retail .rl-shop-buy{
            display:flex!important;
            align-items:center!important;
            justify-content:center!important;
            width:100%!important;
            height:54px!important;
            min-height:54px!important;
            margin:0!important;
            border-radius:10px!important;
            background:linear-gradient(100deg,#baff00 0%,#d1ff52 100%)!important;
            color:#071004!important;
            font-size:13px!important;
            font-weight:800!important;
            letter-spacing:.02em!important;
            box-shadow:0 0 18px rgba(186,255,0,.13)!important;
        }
    }

    @media (min-width:1181px){
        /* Toolbar: use the two divider DOM nodes that already sit in exactly
           the correct semantic locations. No group pseudo-dividers. */
        #rl-shop-controls.rl-shop-toolbar{
            display:flex!important;
            align-items:center!important;
            flex-wrap:nowrap!important;
            gap:0!important;
            min-height:84px!important;
            height:84px!important;
            padding:14px 18px!important;
            border:1px solid rgba(186,255,0,.30)!important;
            border-radius:17px!important;
            background:linear-gradient(110deg,rgba(16,27,14,.92),rgba(6,11,8,.94))!important;
            box-shadow:0 0 26px rgba(186,255,0,.07),inset 0 0 24px rgba(186,255,0,.025)!important;
            box-sizing:border-box!important;
        }
        #rl-shop-controls .rl-shop-toolbar-spacer{
            flex:0 0 auto!important;
            display:flex!important;
            align-items:center!important;
            justify-content:flex-start!important;
            min-width:0!important;
            margin:0!important;
            padding:0!important;
            border:0!important;
        }
        #rl-shop-controls .rl-shop-points-badge{
            display:flex!important;
            align-items:center!important;
            gap:12px!important;
            min-height:54px!important;
            padding:0 16px!important;
            border:1px solid #394735!important;
            border-radius:11px!important;
            background:#0b120d!important;
            box-shadow:none!important;
        }
        #rl-shop-controls .rl-shop-points-label{
            color:#f3f6ef!important;
            font-size:13px!important;
            font-weight:650!important;
            letter-spacing:0!important;
            text-transform:none!important;
        }
        #rl-shop-controls .rl-shop-points-value{
            color:#baff00!important;
            font-size:22px!important;
            font-weight:800!important;
            letter-spacing:-.02em!important;
        }
        #rl-shop-controls .rl-shop-toolbar-divider{
            display:block!important;
            flex:0 0 1px!important;
            width:1px!important;
            height:44px!important;
            margin:0 22px!important;
            padding:0!important;
            border:0!important;
            background:#344132!important;
            opacity:1!important;
        }
        #rl-shop-controls .rl-shop-mode-block{
            position:static!important;
            display:flex!important;
            flex:0 0 auto!important;
            flex-direction:row!important;
            align-items:center!important;
            gap:18px!important;
            min-width:0!important;
            margin:0!important;
            padding:0!important;
            border:0!important;
        }
        #rl-shop-controls .rl-shop-mode-eyebrow{
            position:relative!important;
            display:inline-flex!important;
            align-items:center!important;
            gap:8px!important;
            margin:0!important;
            padding:0!important;
            border:0!important;
            border-radius:0!important;
            background:transparent!important;
            box-shadow:none!important;
            color:#baff00!important;
            font-size:11px!important;
            font-weight:750!important;
            line-height:1!important;
            letter-spacing:.035em!important;
            text-transform:uppercase!important;
            white-space:nowrap!important;
            transform:none!important;
        }
        #rl-shop-controls .rl-shop-mode-eyebrow:before{
            content:""!important;
            display:block!important;
            width:18px!important;
            height:18px!important;
            flex:0 0 18px!important;
            border-radius:0!important;
            box-shadow:none!important;
            background-color:#baff00!important;
            -webkit-mask:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'%3E%3Cpath fill='none' stroke='black' stroke-width='2' stroke-linecap='round' stroke-linejoin='round' d='M3 4h2l2.2 10.2a2 2 0 0 0 2 1.6h7.6a2 2 0 0 0 2-1.6L20 8H7M10 20a1 1 0 1 0 0-2 1 1 0 0 0 0 2Zm7 0a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z'/%3E%3C/svg%3E") center/contain no-repeat!important;
            mask:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'%3E%3Cpath fill='none' stroke='black' stroke-width='2' stroke-linecap='round' stroke-linejoin='round' d='M3 4h2l2.2 10.2a2 2 0 0 0 2 1.6h7.6a2 2 0 0 0 2-1.6L20 8H7M10 20a1 1 0 1 0 0-2 1 1 0 0 0 0 2Zm7 0a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z'/%3E%3C/svg%3E") center/contain no-repeat!important;
        }
        #rl-shop-controls .rl-shop-view-modes{
            display:flex!important;
            align-items:center!important;
            gap:8px!important;
            min-height:54px!important;
            margin:0!important;
            padding:5px!important;
            border:1px solid #405034!important;
            border-radius:12px!important;
            background:#0a110c!important;
        }
        #rl-shop-controls .rl-shop-view-mode{
            display:inline-flex!important;
            align-items:center!important;
            justify-content:center!important;
            min-width:118px!important;
            height:44px!important;
            min-height:44px!important;
            padding:0 16px!important;
            border:1px solid #647e27!important;
            border-radius:9px!important;
            background:#0a100b!important;
            color:#f3f6ef!important;
            font-size:11px!important;
            font-weight:700!important;
            line-height:1!important;
            letter-spacing:.01em!important;
            text-transform:uppercase!important;
            text-align:center!important;
            box-shadow:none!important;
        }
        #rl-shop-controls .rl-shop-view-mode.is-active{
            border-color:#baff00!important;
            background:linear-gradient(100deg,#baff00,#d0ff4e)!important;
            color:#071004!important;
            box-shadow:0 0 18px rgba(186,255,0,.15)!important;
        }
        #rl-shop-controls .rl-shop-toolbar-actions{
            flex:0 1 auto!important;
            display:flex!important;
            align-items:center!important;
            justify-content:flex-end!important;
            gap:12px!important;
            min-width:0!important;
            margin:0 0 0 auto!important;
            padding:0!important;
            border:0!important;
        }
        #rl-shop-controls .rl-shop-toolbar-actions > .rl-shop-filter-toggle:not(.rl-shop-sort-trigger){
            flex:0 1 170px!important;
            width:clamp(150px,11vw,170px)!important;
            min-width:150px!important;
            height:54px!important;
            min-height:54px!important;
            margin:0!important;
            border:1px solid #3e493c!important;
            border-radius:10px!important;
            background:#0b110d!important;
            color:#f4f6f2!important;
            font-size:11px!important;
            font-weight:700!important;
            letter-spacing:.01em!important;
            text-transform:uppercase!important;
        }
        #rl-shop-controls .rl-shop-sort{
            display:flex!important;
            align-items:center!important;
            flex:0 1 250px!important;
            width:clamp(220px,17vw,250px)!important;
            min-width:220px!important;
            height:54px!important;
            min-height:54px!important;
            margin:0!important;
        }
        #rl-shop-controls .rl-shop-sort .woocommerce-ordering,
        #rl-shop-controls .rl-shop-sort-custom{
            display:flex!important;
            align-items:center!important;
            width:100%!important;
            height:54px!important;
            min-height:54px!important;
            min-width:0!important;
            margin:0!important;
        }
        #rl-shop-controls .rl-shop-sort-trigger{
            position:relative!important;
            display:flex!important;
            align-items:center!important;
            justify-content:center!important;
            height:54px!important;
            min-height:54px!important;
            width:100%!important;
            padding:0 38px 0 18px!important;
            border:1px solid #3e493c!important;
            border-radius:10px!important;
            background:#0b110d!important;
            color:#f4f6f2!important;
            font-size:11px!important;
            font-weight:700!important;
            letter-spacing:.01em!important;
            text-transform:uppercase!important;
        }
        #rl-shop-controls .rl-shop-sort-value{
            width:100%!important;
            padding:0!important;
            text-align:center!important;
            white-space:nowrap!important;
            overflow:hidden!important;
            text-overflow:ellipsis!important;
        }
        #rl-shop-controls .rl-shop-sort-caret{
            position:absolute!important;
            right:14px!important;
            top:50%!important;
            transform:translateY(-50%)!important;
            color:#f4f6f2!important;
        }
    }
    /* Keep the 0.1.95 type layer authoritative over historical presentation rules. */
    body.rafflelb-raffle-archive :is(.rl-shop-hero,#rl-shop-controls,.rl-raffle-card),
    body.rafflelb-raffle-archive :is(.rl-shop-hero,#rl-shop-controls,.rl-raffle-card) *{font-family:var(--rl-font,"Manrope",sans-serif)!important}
    body.rafflelb-raffle-archive .rl-shop-title-row h1{font-size:48px!important;font-weight:var(--rl-weight-heavy,800)!important;line-height:var(--rl-line-heading,1.08)!important;letter-spacing:var(--rl-tracking-tight,-.025em)!important}
    body.rafflelb-raffle-archive .rl-shop-title-row p{font-size:var(--rl-text-base,15px)!important;font-weight:var(--rl-weight-medium,500)!important;line-height:var(--rl-line-body,1.55)!important}
    #rl-shop-controls .rl-shop-points-label{font-size:13px!important;font-weight:var(--rl-weight-semibold,600)!important}
    #rl-shop-controls .rl-shop-points-value{font-size:22px!important;font-weight:var(--rl-weight-heavy,800)!important}
    #rl-shop-controls .rl-shop-mode-eyebrow{font-size:11px!important;font-weight:var(--rl-weight-bold,700)!important}
    #rl-shop-controls .rl-shop-view-mode,#rl-shop-controls .rl-shop-toolbar-actions>.rl-shop-filter-toggle:not(.rl-shop-sort-trigger),#rl-shop-controls .rl-shop-sort-trigger{font-size:12px!important;font-weight:var(--rl-weight-bold,700)!important;letter-spacing:0!important}
    body.rafflelb-raffle-archive .rl-raffle-card :is(.wd-entities-title,.product-title,h3),body.rafflelb-raffle-archive .rl-raffle-card :is(.wd-entities-title,.product-title,h3) a{font-size:17px!important;font-weight:var(--rl-weight-bold,700)!important;line-height:1.35!important}
    body.rafflelb-raffle-archive.rl-store-reference .rl-shop-prices:not(.is-dual-price) small{font-size:10px!important;font-weight:var(--rl-weight-semibold,600)!important}
    body.rafflelb-raffle-archive.rl-store-reference .rl-shop-prices:not(.is-dual-price) strong,body.rafflelb-raffle-archive.rl-store-reference .rl-shop-prices:not(.is-dual-price) strong *{font-size:20px!important;font-weight:var(--rl-weight-heavy,800)!important}
    body.rafflelb-raffle-archive .rl-shop-prices:not(.is-dual-price) .rl-shop-price-raffle .rl-shop-entry-value em{font-size:11px!important}
    body.rafflelb-raffle-archive .rl-shop-raffle-live,body.rafflelb-raffle-archive .rl-shop-raffle-line strong,body.rafflelb-raffle-archive .rl-shop-raffle-line strong *,body.rafflelb-raffle-archive .rl-shop-raffle-meta,body.rafflelb-raffle-archive .rl-shop-raffle-meta *{font-size:11px!important;font-weight:var(--rl-weight-semibold,600)!important}
    body.rafflelb-raffle-archive .rl-shop-buy,body.rafflelb-raffle-archive .rl-shop-buy-label,body.rafflelb-raffle-archive .rl-shop-enter-label,body.rafflelb-raffle-archive .rl-shop-enter-price,body.rafflelb-raffle-archive .rl-shop-enter-price *{font-size:12px!important;font-weight:var(--rl-weight-bold,700)!important;letter-spacing:0!important}
    @media(max-width:767px){body.rafflelb-raffle-archive .rl-shop-title-row h1{font-size:44px!important}body.rafflelb-raffle-archive .rl-raffle-card :is(.wd-entities-title,.product-title,h3),body.rafflelb-raffle-archive .rl-raffle-card :is(.wd-entities-title,.product-title,h3) a{font-size:16px!important}body.rafflelb-raffle-archive.rl-store-reference .rl-shop-prices:not(.is-dual-price) strong,body.rafflelb-raffle-archive.rl-store-reference .rl-shop-prices:not(.is-dual-price) strong *{font-size:18px!important}}
    </style>
    <?php
}

    private static function shop_banner_defaults() {
        return [
            'global' => [
                'enabled'     => 'no',
                'text'        => '',
                'motion'      => 'moving',
                'font_size'   => 13,
                'font_weight' => 700,
                'height'      => 46,
                'speed'       => 14,
            ],
            'both' => [
                'enabled'     => 'no',
                'text'        => '',
                'motion'      => 'moving',
                'font_size'   => 13,
                'font_weight' => 700,
                'height'      => 46,
                'speed'       => 14,
            ],
            'retail' => [
                'enabled'     => 'no',
                'text'        => '',
                'motion'      => 'moving',
                'font_size'   => 13,
                'font_weight' => 700,
                'height'      => 46,
                'speed'       => 14,
            ],
            'raffle' => [
                'enabled'     => 'no',
                'text'        => '',
                'motion'      => 'moving',
                'font_size'   => 13,
                'font_weight' => 700,
                'height'      => 46,
                'speed'       => 14,
            ],
        ];
    }

    private static function shop_banner_settings() {
        $saved = get_option('rafflelb_shop_banners', []);
        $saved = is_array($saved) ? $saved : [];
        $defaults = self::shop_banner_defaults();
        foreach ($defaults as $mode => $config) {
            $mode_saved = isset($saved[$mode]) && is_array($saved[$mode]) ? $saved[$mode] : [];
            $defaults[$mode] = wp_parse_args($mode_saved, $config);
        }
        return $defaults;
    }

    public static function sanitize_shop_banner_settings($value) {
        $value = is_array($value) ? $value : [];
        $clean = self::shop_banner_defaults();
        foreach (array_keys($clean) as $mode) {
            $row = isset($value[$mode]) && is_array($value[$mode]) ? $value[$mode] : [];
            $clean[$mode]['enabled'] = !empty($row['enabled']) ? 'yes' : 'no';
            $clean[$mode]['text'] = isset($row['text']) ? sanitize_text_field(wp_unslash($row['text'])) : '';
            $motion = isset($row['motion']) ? sanitize_key($row['motion']) : 'moving';
            $clean[$mode]['motion'] = in_array($motion, ['moving', 'static'], true) ? $motion : 'moving';

            $font_size = isset($row['font_size']) ? absint($row['font_size']) : 13;
            $clean[$mode]['font_size'] = max(10, min(24, $font_size ?: 13));

            $font_weight = isset($row['font_weight']) ? absint($row['font_weight']) : 700;
            $allowed_weights = [500, 600, 700, 800, 900];
            $clean[$mode]['font_weight'] = in_array($font_weight, $allowed_weights, true) ? $font_weight : 700;

            $height = isset($row['height']) ? absint($row['height']) : 46;
            $clean[$mode]['height'] = max(38, min(64, $height ?: 46));

            $speed = isset($row['speed']) ? absint($row['speed']) : 14;
            $clean[$mode]['speed'] = max(5, min(60, $speed ?: 14));
        }
        return $clean;
    }

    public static function register_shop_banner_settings() {
        register_setting(
            'rafflelb_shop_banners_group',
            'rafflelb_shop_banners',
            [
                'type'              => 'array',
                'sanitize_callback' => [__CLASS__, 'sanitize_shop_banner_settings'],
                'default'           => self::shop_banner_defaults(),
            ]
        );
    }

    public static function banner_settings_updated($old_value, $new_value) {
        if ($old_value === $new_value) return;
        if (function_exists('rocket_clean_domain')) {
            rocket_clean_domain();
        }
        do_action('rafflelb_shop_banners_updated', $new_value, $old_value);
    }

    public static function register_shop_banner_menu() {
        /*
         * Keep this screen hidden from the normal/Advanced WordPress menu.
         * RaffleLB Admin owns the visible Store Banners item in RaffleLB Mode.
         */
        add_submenu_page(
            null,
            __('Store Banners', 'rafflelb-shop'),
            __('Store Banners', 'rafflelb-shop'),
            'manage_woocommerce',
            'rafflelb-shop-banners',
            [__CLASS__, 'render_shop_banner_settings_page']
        );
    }

    public static function render_shop_banner_settings_page() {
        if (!current_user_can('manage_woocommerce')) return;
        $settings = self::shop_banner_settings();
        $labels = [
            'global' => ['GLOBAL', 'Shown across the entire public RaffleLB website, including the Store.'],
            'both'   => ['STORE & RAFFLE', 'Shown only while the Store is in Store & Raffle mode.'],
            'retail' => ['STORE ONLY', 'Shown only while the Store is in Store Only mode.'],
            'raffle' => ['RAFFLE ONLY', 'Shown only while the Store is in Raffle Only mode.'],
        ];
        ?>
        <div class="wrap rafflelb-shop-banner-settings">
            <div class="rl-banner-admin-heading">
                <div>
                    <div class="rl-banner-admin-eyebrow">RAFFLELB / STORE</div>
                    <h1><?php echo esc_html__('Promotion Banners', 'rafflelb-shop'); ?></h1>
                    <p><?php echo esc_html__('Use GLOBAL for site-wide updates, or create a separate message for each Store shopping mode.', 'rafflelb-shop'); ?></p>
                </div>
            </div>
            <form method="post" action="options.php">
                <?php settings_fields('rafflelb_shop_banners_group'); ?>
                <div class="rl-admin-banner-grid">
                    <?php foreach ($labels as $mode => $label) : $config = $settings[$mode]; ?>
                        <section class="rl-admin-banner-card <?php echo $mode === 'global' ? 'is-global' : ''; ?>">
                            <div class="rl-admin-banner-card-head">
                                <div>
                                    <h2><?php echo esc_html($label[0]); ?></h2>
                                    <p><?php echo esc_html($label[1]); ?></p>
                                </div>
                                <label class="rl-admin-banner-toggle">
                                    <input type="checkbox" name="rafflelb_shop_banners[<?php echo esc_attr($mode); ?>][enabled]" value="1" <?php checked($config['enabled'], 'yes'); ?>>
                                    <span class="rl-admin-toggle-ui" aria-hidden="true"><span></span></span>
                                    <span class="rl-admin-toggle-copy"><?php echo esc_html__('Enabled', 'rafflelb-shop'); ?></span>
                                </label>
                            </div>
                            <label class="rl-admin-field">
                                <span><?php echo esc_html__('Promotion / update text', 'rafflelb-shop'); ?></span>
                                <input type="text" class="regular-text" maxlength="240" name="rafflelb_shop_banners[<?php echo esc_attr($mode); ?>][text]" value="<?php echo esc_attr($config['text']); ?>" placeholder="<?php echo esc_attr__('Example: Weekend promotion — selected raffles now live.', 'rafflelb-shop'); ?>">
                            </label>
                            <label class="rl-admin-field">
                                <span><?php echo esc_html__('Display style', 'rafflelb-shop'); ?></span>
                                <select name="rafflelb_shop_banners[<?php echo esc_attr($mode); ?>][motion]">
                                    <option value="moving" <?php selected($config['motion'], 'moving'); ?>><?php echo esc_html__('Animated / moving', 'rafflelb-shop'); ?></option>
                                    <option value="static" <?php selected($config['motion'], 'static'); ?>><?php echo esc_html__('Not moving / static', 'rafflelb-shop'); ?></option>
                                </select>
                            </label>
                            <div class="rl-admin-banner-options">
                                <label class="rl-admin-field">
                                    <span><?php echo esc_html__('Font size', 'rafflelb-shop'); ?></span>
                                    <div class="rl-admin-number-wrap"><input type="number" min="10" max="24" step="1" name="rafflelb_shop_banners[<?php echo esc_attr($mode); ?>][font_size]" value="<?php echo esc_attr((int) $config['font_size']); ?>"><em>px</em></div>
                                </label>
                                <label class="rl-admin-field">
                                    <span><?php echo esc_html__('Font weight', 'rafflelb-shop'); ?></span>
                                    <select name="rafflelb_shop_banners[<?php echo esc_attr($mode); ?>][font_weight]">
                                        <option value="500" <?php selected((int) $config['font_weight'], 500); ?>><?php echo esc_html__('Medium', 'rafflelb-shop'); ?></option>
                                        <option value="600" <?php selected((int) $config['font_weight'], 600); ?>><?php echo esc_html__('Semi Bold', 'rafflelb-shop'); ?></option>
                                        <option value="700" <?php selected((int) $config['font_weight'], 700); ?>><?php echo esc_html__('Bold', 'rafflelb-shop'); ?></option>
                                        <option value="800" <?php selected((int) $config['font_weight'], 800); ?>><?php echo esc_html__('Extra Bold', 'rafflelb-shop'); ?></option>
                                        <option value="900" <?php selected((int) $config['font_weight'], 900); ?>><?php echo esc_html__('Black', 'rafflelb-shop'); ?></option>
                                    </select>
                                </label>
                                <label class="rl-admin-field">
                                    <span><?php echo esc_html__('Banner height', 'rafflelb-shop'); ?></span>
                                    <div class="rl-admin-number-wrap"><input type="number" min="38" max="64" step="1" name="rafflelb_shop_banners[<?php echo esc_attr($mode); ?>][height]" value="<?php echo esc_attr((int) $config['height']); ?>"><em>px</em></div>
                                </label>
                                <label class="rl-admin-field">
                                    <span><?php echo esc_html__('Moving speed', 'rafflelb-shop'); ?></span>
                                    <div class="rl-admin-number-wrap"><input type="number" min="5" max="60" step="1" name="rafflelb_shop_banners[<?php echo esc_attr($mode); ?>][speed]" value="<?php echo esc_attr((int) $config['speed']); ?>"><em>sec</em></div>
                                    <small><?php echo esc_html__('Lower = faster. Used only when Animated / moving is selected.', 'rafflelb-shop'); ?></small>
                                </label>
                            </div>
                        </section>
                    <?php endforeach; ?>
                </div>
                <?php submit_button(__('Save Banner Settings', 'rafflelb-shop')); ?>
            </form>
        </div>
        <style id="rafflelb-banner-admin-v0213">
            .rafflelb-shop-banner-settings{max-width:1240px;color:#f5f7f2;padding-top:8px}
            .rafflelb-shop-banner-settings,.rafflelb-shop-banner-settings *{box-sizing:border-box}
            .rafflelb-shop-banner-settings h1,.rafflelb-shop-banner-settings h2,.rafflelb-shop-banner-settings strong,.rafflelb-shop-banner-settings label,.rafflelb-shop-banner-settings p{color:inherit}
            .rl-banner-admin-heading{display:flex;align-items:flex-end;justify-content:space-between;margin:0 0 22px}
            .rl-banner-admin-heading h1{margin:4px 0 7px!important;font-size:30px!important;line-height:1.1!important;color:#fff!important}
            .rl-banner-admin-heading p{margin:0!important;color:#9ca59b!important;font-size:13px!important}
            .rl-banner-admin-eyebrow{color:#baff00;font-size:10px;font-weight:800;letter-spacing:.13em}
            .rl-admin-banner-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px;margin-top:18px}
            .rl-admin-banner-card{padding:20px;border:1px solid #263126;border-radius:13px;background:#0d110d!important;box-shadow:none;color:#f7f8f5}
            .rl-admin-banner-card.is-global{border-color:rgba(186,255,0,.48);box-shadow:inset 0 0 0 1px rgba(186,255,0,.05)}
            .rl-admin-banner-card-head{display:flex;gap:18px;align-items:flex-start;justify-content:space-between}
            .rl-admin-banner-card h2{margin:0 0 6px!important;color:#fff!important;font-size:14px!important;font-weight:800!important;letter-spacing:.04em}
            .rl-admin-banner-card-head p{margin:0!important;max-width:450px;min-height:36px;color:#929b92!important;font-size:12px!important;line-height:1.5!important}
            .rl-admin-field{display:block;margin-top:17px!important;color:#f2f4ef!important}
            .rl-admin-field>span{display:block;margin:0 0 7px;color:#cbd1c8!important;font-size:11px;font-weight:700}
            .rafflelb-shop-banner-settings input[type=text],.rafflelb-shop-banner-settings input[type=number],.rafflelb-shop-banner-settings select{width:100%!important;max-width:none!important;min-height:40px!important;margin:0!important;padding:0 12px!important;border:1px solid #303b30!important;border-radius:7px!important;background:#070a07!important;color:#fff!important;box-shadow:none!important;outline:none!important}
            .rafflelb-shop-banner-settings input[type=text]:focus,.rafflelb-shop-banner-settings input[type=number]:focus,.rafflelb-shop-banner-settings select:focus{border-color:#baff00!important;box-shadow:0 0 0 1px #baff00!important}
            .rafflelb-shop-banner-settings select option{background:#070a07;color:#fff}
            .rl-admin-banner-options{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px 14px;margin-top:2px}
            .rl-admin-banner-options .rl-admin-field{margin-top:14px!important}
            .rl-admin-number-wrap{position:relative}
            .rl-admin-number-wrap input{padding-right:48px!important}
            .rl-admin-number-wrap em{position:absolute;right:11px;top:50%;transform:translateY(-50%);color:#7f897e;font-size:10px;font-style:normal;font-weight:800;pointer-events:none;text-transform:uppercase}
            .rl-admin-field small{display:block;margin-top:6px;color:#737d72;font-size:10px;line-height:1.35}
            .rl-admin-banner-toggle{display:flex!important;align-items:center!important;gap:9px!important;flex:0 0 auto!important;margin:0!important;cursor:pointer;user-select:none}
            .rl-admin-banner-toggle input{position:absolute!important;opacity:0!important;pointer-events:none!important;width:1px!important;height:1px!important}
            .rl-admin-toggle-ui{position:relative;display:inline-flex!important;align-items:center;width:38px;height:22px;padding:2px;border:1px solid #3b473a;border-radius:999px;background:#171d16;transition:.18s ease}
            .rl-admin-toggle-ui>span{display:block;width:16px;height:16px;border-radius:50%;background:#7d877a;transition:.18s ease;transform:translateX(0)}
            .rl-admin-banner-toggle input:checked + .rl-admin-toggle-ui{border-color:#baff00;background:#baff00}
            .rl-admin-banner-toggle input:checked + .rl-admin-toggle-ui>span{background:#050705;transform:translateX(16px)}
            .rl-admin-banner-toggle input:focus-visible + .rl-admin-toggle-ui{outline:2px solid #fff;outline-offset:2px}
            .rl-admin-toggle-copy{color:#cfd5cc!important;font-size:11px!important;font-weight:700!important}
            .rafflelb-shop-banner-settings .submit{margin:18px 0 0!important;padding:0!important}
            .rafflelb-shop-banner-settings .button-primary{min-height:42px!important;padding:0 18px!important;border:0!important;border-radius:8px!important;background:#baff00!important;color:#050705!important;font-weight:800!important;text-shadow:none!important;box-shadow:none!important}
            .rafflelb-shop-banner-settings .button-primary:hover{background:#c8ff34!important;color:#050705!important}
            @media(max-width:1000px){.rl-admin-banner-grid{grid-template-columns:1fr}}
            @media(max-width:600px){.rl-admin-banner-card-head{display:block}.rl-admin-banner-toggle{margin-top:14px!important}.rl-admin-banner-options{grid-template-columns:1fr}}
        </style>
        <?php
    }

    private static function public_banner_markup($text, $moving, $badge, $scope_class, $config = []) {
        $text = trim((string) $text);
        if ($text === '') return '';
        $config = is_array($config) ? $config : [];
        $font_size = max(10, min(24, absint($config['font_size'] ?? 13)));
        $font_weight = absint($config['font_weight'] ?? 700);
        if (!in_array($font_weight, [500, 600, 700, 800, 900], true)) $font_weight = 700;
        $height = max(38, min(64, absint($config['height'] ?? 46)));
        $speed = max(5, min(60, absint($config['speed'] ?? 14)));
        $inline_style = sprintf(
            '--rl-banner-font-size:%dpx;--rl-banner-font-weight:%d;--rl-banner-height:%dpx;--rl-banner-speed:%ds;',
            $font_size,
            $font_weight,
            $height,
            $speed
        );
        ob_start();
        ?>
        <section class="rl-shop-announcement <?php echo $moving ? 'is-moving' : 'is-static'; ?> <?php echo esc_attr($scope_class); ?>" style="<?php echo esc_attr($inline_style); ?>" role="status" aria-label="RaffleLB update">
            <span class="rl-shop-announcement-badge"><?php echo esc_html($badge); ?></span>
            <div class="rl-shop-announcement-viewport">
                <?php if ($moving) : ?>
                    <div class="rl-shop-announcement-track">
                        <span class="rl-shop-announcement-item"><?php echo esc_html($text); ?></span>
                    </div>
                <?php else : ?>
                    <div class="rl-shop-announcement-static"><?php echo esc_html($text); ?></div>
                <?php endif; ?>
            </div>
        </section>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * Public banner stack. GLOBAL is shown site-wide; the active Store mode
     * banner is added only on Shop/product-category archives. Rendering at the
     * footer and moving the stack directly below the header makes this reliable
     * even when WoodMart replaces WooCommerce archive hooks with AJAX markup.
     */
    public static function public_banner_stack() {
        if (is_admin() || self::$public_banners_rendered) return;

        $settings = self::shop_banner_settings();
        $items = [];

        if (!empty($settings['global']) && $settings['global']['enabled'] === 'yes' && trim((string) $settings['global']['text']) !== '') {
            $items[] = self::public_banner_markup(
                $settings['global']['text'],
                $settings['global']['motion'] === 'moving',
                'RAFFLELB',
                'is-global',
                $settings['global']
            );
        }

        if (self::shop_query_is_catalog()) {
            $mode = self::shop_view_mode();
            if (!empty($settings[$mode]) && $settings[$mode]['enabled'] === 'yes' && trim((string) $settings[$mode]['text']) !== '') {
                $badge = $mode === 'raffle' ? 'RAFFLE' : ($mode === 'retail' ? 'STORE' : 'UPDATE');
                $items[] = self::public_banner_markup(
                    $settings[$mode]['text'],
                    $settings[$mode]['motion'] === 'moving',
                    $badge,
                    'is-mode-' . $mode,
                    $settings[$mode]
                );
            }
        }

        if (!$items) return;
        self::$public_banners_rendered = true;

        echo '<div id="rafflelb-site-banner-stack" class="rl-site-banner-stack">' . implode('', $items) . '</div>';
        ?>
        <script id="rafflelb-site-banner-placement-v0213">
        (function(){
            var stack=document.getElementById('rafflelb-site-banner-stack');
            if(!stack) return;
            function place(){
                var header=document.querySelector('.whb-header,header.site-header,#masthead,header');
                if(header && header.parentNode){
                    header.insertAdjacentElement('afterend',stack);
                    return true;
                }
                var main=document.querySelector('.main-page-wrapper,#main,.site-content');
                if(main && main.parentNode){
                    main.parentNode.insertBefore(stack,main);
                    return true;
                }
                return false;
            }
            if(!place()){
                if(document.readyState==='loading') document.addEventListener('DOMContentLoaded',place,{once:true});
                else setTimeout(place,0);
            }
        })();
        </script>
        <?php
    }

    /** Backward-compatible mode-only renderer for third-party/custom hooks. */
    public static function shop_mode_banner() {
        if (!self::shop_query_is_catalog()) return;
        $mode = self::shop_view_mode();
        $settings = self::shop_banner_settings();
        if (empty($settings[$mode]) || $settings[$mode]['enabled'] !== 'yes') return;
        echo self::public_banner_markup(
            $settings[$mode]['text'],
            $settings[$mode]['motion'] === 'moving',
            $mode === 'raffle' ? 'RAFFLE' : ($mode === 'retail' ? 'STORE' : 'UPDATE'),
            'is-mode-' . $mode,
            $settings[$mode]
        );
    }

    /** Store search taxonomies: categories plus any registered product brand taxonomy. */
    private static function store_search_taxonomies() {
        $taxonomies = ['product_cat'];
        $objects = get_object_taxonomies('product', 'objects');
        if (is_array($objects)) {
            foreach ($objects as $taxonomy => $object) {
                $name = strtolower((string) $taxonomy);
                $label = isset($object->label) ? strtolower((string) $object->label) : '';
                if (strpos($name, 'brand') !== false || strpos($label, 'brand') !== false) {
                    $taxonomies[] = sanitize_key($taxonomy);
                }
            }
        }
        return array_values(array_unique(array_filter($taxonomies)));
    }

    /**
     * Build a catalogue-derived spelling lexicon.
     *
     * This intentionally learns from the live Store instead of relying only on a
     * hard-coded dictionary, so product names, categories and brands can correct
     * customer typos automatically (for example: "airfrier" -> "Air Fryer").
     */
    private static function store_search_lexicon() {
        $cache_key = 'rafflelb_store_search_lexicon_v0215';
        $cached = get_transient($cache_key);
        if (is_array($cached) && !empty($cached)) return $cached;

        $entries = [];
        $add = static function (&$entries, $key_source, $label, $priority = 10) {
            $key_source = function_exists('remove_accents') ? remove_accents((string) $key_source) : (string) $key_source;
            $key = strtolower($key_source);
            $key = preg_replace('/[^a-z0-9]+/', '', $key);
            $label = trim(wp_strip_all_tags((string) $label));
            if ($key === '' || $label === '' || strlen($key) < 3 || strlen($key) > 48) return;
            if (!isset($entries[$key]) || $priority < (int) $entries[$key]['priority']) {
                $entries[$key] = ['label' => $label, 'priority' => (int) $priority];
            }
        };

        /* A few high-confidence retail spellings; the rest comes from the catalogue. */
        $aliases = [
            'airfrier' => 'Air Fryer',
            'airfryer' => 'Air Fryer',
            'airfyer'  => 'Air Fryer',
            'earpod'   => 'AirPods',
            'earpods'  => 'AirPods',
            'airpod'   => 'AirPods',
            'airpods'  => 'AirPods',
        ];
        foreach ($aliases as $key => $label) $add($entries, $key, $label, 0);

        /* Categories + registered product brand taxonomies. */
        foreach (self::store_search_taxonomies() as $taxonomy) {
            $terms = get_terms([
                'taxonomy'   => $taxonomy,
                'hide_empty' => false,
                'number'     => 1000,
            ]);
            if (is_wp_error($terms) || !is_array($terms)) continue;
            foreach ($terms as $term) {
                if (!is_object($term) || empty($term->name)) continue;
                $name = html_entity_decode((string) $term->name, ENT_QUOTES, get_bloginfo('charset'));
                $add($entries, $name, $name, 1);
            }
        }

        /* Product-title words and adjacent 2/3-word phrases. */
        $titles = get_posts([
            'post_type'              => 'product',
            'post_status'            => 'publish',
            'posts_per_page'         => 3000,
            'orderby'                => 'ID',
            'order'                  => 'DESC',
            'fields'                 => 'ids',
            'no_found_rows'          => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
        ]);
        $stop = array_fill_keys(['the','and','with','for','from','into','this','that','your','our','new','set','pack','pcs','piece'], true);
        foreach ((array) $titles as $product_id) {
            $title = html_entity_decode((string) get_the_title($product_id), ENT_QUOTES, get_bloginfo('charset'));
            $plain = function_exists('remove_accents') ? remove_accents($title) : $title;
            $parts = preg_split('/[^A-Za-z0-9]+/', $plain, -1, PREG_SPLIT_NO_EMPTY);
            if (!$parts) continue;
            $parts = array_values($parts);
            $count = count($parts);
            for ($i = 0; $i < $count; $i++) {
                $word = (string) $parts[$i];
                $low = strtolower($word);
                if (strlen($word) >= 4 && !isset($stop[$low])) {
                    $add($entries, $word, $word, 4);
                }
                for ($span = 2; $span <= 3; $span++) {
                    if ($i + $span > $count) break;
                    $phrase_parts = array_slice($parts, $i, $span);
                    $phrase = implode(' ', $phrase_parts);
                    $joined = implode('', $phrase_parts);
                    if (strlen($joined) >= 5 && strlen($joined) <= 32) {
                        $add($entries, $joined, $phrase, 3);
                    }
                }
            }
        }

        /* Keep the lexicon bounded for fast Levenshtein comparisons. */
        if (count($entries) > 7000) $entries = array_slice($entries, 0, 7000, true);
        set_transient($cache_key, $entries, 12 * HOUR_IN_SECONDS);
        return $entries;
    }

    public static function clear_store_search_lexicon_cache() {
        delete_transient('rafflelb_store_search_lexicon_v0215');
    }

    private static function store_search_normalized_key($value) {
        $value = function_exists('remove_accents') ? remove_accents((string) $value) : (string) $value;
        $value = strtolower($value);
        return preg_replace('/[^a-z0-9]+/', '', $value);
    }

    private static function store_search_best_spelling($value, $lexicon, $allow_phrase = true) {
        $raw = trim((string) $value);
        $key = self::store_search_normalized_key($raw);
        $len = strlen($key);
        if ($len < 3 || empty($lexicon)) return $raw;

        if (isset($lexicon[$key]['label'])) return (string) $lexicon[$key]['label'];
        if ($len < 4) return $raw;

        $max_distance = $len <= 5 ? 1 : ($len <= 9 ? 2 : 3);
        $best = null;
        $best_score = PHP_INT_MAX;
        $best_priority = PHP_INT_MAX;

        foreach ($lexicon as $candidate_key => $entry) {
            $candidate_key = (string) $candidate_key;
            $candidate_len = strlen($candidate_key);
            if ($candidate_len < 3 || abs($candidate_len - $len) > $max_distance) continue;
            if ($candidate_key[0] !== $key[0]) continue;
            if (!$allow_phrase && strpos((string) $entry['label'], ' ') !== false) continue;

            $distance = levenshtein($key, $candidate_key);
            if ($distance > $max_distance) continue;
            $priority = isset($entry['priority']) ? (int) $entry['priority'] : 10;
            $score = ($distance * 100) + abs($candidate_len - $len);
            if ($score < $best_score || ($score === $best_score && $priority < $best_priority)) {
                $best = (string) $entry['label'];
                $best_score = $score;
                $best_priority = $priority;
            }
        }
        return $best !== null ? $best : $raw;
    }

    /** Correct high-confidence misspellings using catalogue words/phrases. */
    private static function store_search_autocorrect_term($term) {
        $original = trim(wp_strip_all_tags((string) $term));
        if ($original === '') return $original;
        $lexicon = self::store_search_lexicon();
        if (empty($lexicon)) return $original;

        /* Whole-query correction first: catches joined phrases like airfrier -> Air Fryer. */
        $whole = self::store_search_best_spelling($original, $lexicon, true);
        if (strcasecmp(trim($whole), trim($original)) !== 0) {
            return $whole;
        }

        /* Otherwise correct individual words conservatively. */
        $tokens = preg_split('/\s+/', $original, -1, PREG_SPLIT_NO_EMPTY);
        if (!$tokens) return $original;
        $changed = false;
        foreach ($tokens as &$token) {
            $fixed = self::store_search_best_spelling($token, $lexicon, false);
            if (self::store_search_normalized_key($fixed) !== self::store_search_normalized_key($token)) {
                $token = $fixed;
                $changed = true;
            } elseif (isset($lexicon[self::store_search_normalized_key($token)]['label'])) {
                /* Canonical capitalization for known catalogue words. */
                $canonical = (string) $lexicon[self::store_search_normalized_key($token)]['label'];
                if (strpos($canonical, ' ') === false) $token = $canonical;
            }
        }
        unset($token);
        return $changed ? implode(' ', $tokens) : $original;
    }

    /** Find published parent product IDs by product name, SKU, category or brand. */
    private static function store_search_candidate_ids($term, $limit = 200) {
        global $wpdb;
        $term = trim(wp_strip_all_tags((string) $term));
        if ($term === '') return [];
        $limit = max(1, min(1000, absint($limit)));

        $taxonomies = self::store_search_taxonomies();
        $tax_placeholders = implode(',', array_fill(0, count($taxonomies), '%s'));
        $contains = '%' . $wpdb->esc_like($term) . '%';
        $starts = $wpdb->esc_like($term) . '%';

        $sql = "SELECT p.ID,
                    MIN(CASE
                        WHEN p.post_title LIKE %s THEN 0
                        WHEN p.post_title LIKE %s THEN 1
                        WHEN sku.meta_value LIKE %s THEN 2
                        WHEN vsku.meta_value LIKE %s THEN 2
                        WHEN t.name LIKE %s THEN 3
                        ELSE 4
                    END) AS rl_relevance
                FROM {$wpdb->posts} p
                LEFT JOIN {$wpdb->postmeta} sku
                    ON sku.post_id = p.ID AND sku.meta_key = '_sku'
                LEFT JOIN {$wpdb->posts} v
                    ON v.post_parent = p.ID AND v.post_type = 'product_variation' AND v.post_status IN ('publish','private')
                LEFT JOIN {$wpdb->postmeta} vsku
                    ON vsku.post_id = v.ID AND vsku.meta_key = '_sku'
                LEFT JOIN {$wpdb->term_relationships} tr
                    ON tr.object_id = p.ID
                LEFT JOIN {$wpdb->term_taxonomy} tt
                    ON tt.term_taxonomy_id = tr.term_taxonomy_id
                LEFT JOIN {$wpdb->terms} t
                    ON t.term_id = tt.term_id
                WHERE p.post_type = 'product'
                  AND p.post_status = 'publish'
                  AND (
                        p.post_title LIKE %s
                        OR sku.meta_value LIKE %s
                        OR vsku.meta_value LIKE %s
                        OR (tt.taxonomy IN ({$tax_placeholders}) AND t.name LIKE %s)
                  )
                GROUP BY p.ID
                ORDER BY rl_relevance ASC, p.post_title ASC
                LIMIT %d";

        $args = [
            $starts,
            $contains,
            $contains,
            $contains,
            $contains,
            $contains,
            $contains,
            $contains,
        ];
        foreach ($taxonomies as $taxonomy) $args[] = $taxonomy;
        $args[] = $contains;
        $args[] = $limit;

        $prepared = $wpdb->prepare($sql, $args);
        $ids = $wpdb->get_col($prepared);
        return array_values(array_unique(array_map('absint', is_array($ids) ? $ids : [])));
    }

    private static function store_search_product_allowed($product, $mode, $all_store = false) {
        if (!$product instanceof WC_Product || !$product->is_visible()) return false;
        $product_id = $product->get_id();
        if (!$product_id) return false;

        $product_mode = self::shop_product_mode($product);
        if ($product_mode === 'cancelled') return false;

        /* Mirror the Store catalogue's completed-raffle exclusion. */
        $is_raffle = get_post_meta($product_id, \RaffleLB\Core\Contracts::META_ENABLED, true) === 'yes';
        $draw_status = (string) get_post_meta($product_id, \RaffleLB\Core\Contracts::META_DRAW_STATUS, true);
        if ($is_raffle && $draw_status === 'winner_selected') return false;

        if ($all_store) return true;
        return self::shop_mode_includes_product($mode, $product_mode);
    }

    private static function store_search_filtered_ids($term, $mode, $all_store = false, $limit = 500) {
        if (!function_exists('wc_get_product')) return [];
        $mode = in_array($mode, ['both', 'retail', 'raffle'], true) ? $mode : 'both';
        $candidate_ids = self::store_search_candidate_ids($term, min(1000, max(80, absint($limit) * 3)));
        $ids = [];
        foreach ($candidate_ids as $product_id) {
            $product = wc_get_product($product_id);
            if (!self::store_search_product_allowed($product, $mode, $all_store)) continue;
            $ids[] = $product_id;
            if (count($ids) >= $limit) break;
        }
        return $ids;
    }

    /**
     * Resolve a Store search and retry once with a high-confidence correction
     * only when the original spelling returns no eligible products.
     */
    private static function store_search_resolve($term, $mode, $all_store = false, $limit = 500) {
        $term = trim((string) $term);
        $ids = self::store_search_filtered_ids($term, $mode, $all_store, $limit);
        $corrected = false;
        $resolved = $term;

        if (empty($ids)) {
            $candidate = self::store_search_autocorrect_term($term);
            if ($candidate !== '' && self::store_search_normalized_key($candidate) !== self::store_search_normalized_key($term)) {
                $candidate_ids = self::store_search_filtered_ids($candidate, $mode, $all_store, $limit);
                if (!empty($candidate_ids)) {
                    $resolved = $candidate;
                    $ids = $candidate_ids;
                    $corrected = true;
                }
            }
        }

        return [
            'original'  => $term,
            'term'      => $resolved,
            'corrected' => $corrected,
            'ids'       => $ids,
        ];
    }

    /** Full-results catalogue filtering for ?rl_search=. */
    public static function store_search_catalog_filter($query) {
        if (!$query instanceof WP_Query || !$query->is_main_query()) return;
        if (is_admin() && !(function_exists('wp_doing_ajax') && wp_doing_ajax())) return;
        if (!self::shop_query_is_catalog($query)) return;

        $term = isset($_GET['rl_search']) ? sanitize_text_field(wp_unslash($_GET['rl_search'])) : '';
        $term = trim($term);
        if (strlen($term) < 2) return;

        $all_store = isset($_GET['rl_search_scope']) && sanitize_key(wp_unslash($_GET['rl_search_scope'])) === 'all';
        $mode = $all_store ? 'both' : self::shop_view_mode();
        $resolved = self::store_search_resolve($term, $mode, $all_store, 1000);
        $ids = $resolved['ids'];
        if (!empty($resolved['corrected'])) {
            $GLOBALS['rafflelb_store_search_corrected'] = $resolved['term'];
            $GLOBALS['rafflelb_store_search_original'] = $resolved['original'];
        }

        $existing = $query->get('post__in');
        if (is_array($existing) && !empty($existing)) {
            $ids = array_values(array_intersect(array_map('absint', $existing), $ids));
        }
        $query->set('post__in', $ids ? $ids : [0]);

        if (!isset($_GET['orderby']) || sanitize_key(wp_unslash($_GET['orderby'])) === '') {
            $query->set('orderby', 'post__in');
        }
    }

    private static function store_search_display_price($amount) {
        $amount = (float) $amount;
        if ($amount <= 0 || !function_exists('wc_price')) return '';
        return html_entity_decode(wp_strip_all_tags(wc_price($amount)), ENT_QUOTES, get_bloginfo('charset'));
    }

    /** Public read-only instant Store search. */
    public static function ajax_store_search() {
        if (!function_exists('wc_get_product')) {
            wp_send_json_success(['items' => [], 'count' => 0, 'view_all' => '']);
        }

        $term = isset($_POST['q']) ? sanitize_text_field(wp_unslash($_POST['q'])) : '';
        $term = trim($term);
        if (strlen($term) < 2) {
            wp_send_json_success(['items' => [], 'count' => 0, 'view_all' => '']);
        }
        if (strlen($term) > 80) $term = substr($term, 0, 80);

        $mode = isset($_POST['mode']) ? sanitize_key(wp_unslash($_POST['mode'])) : 'both';
        if (!in_array($mode, ['both', 'retail', 'raffle'], true)) $mode = 'both';
        $all_store = isset($_POST['scope']) && sanitize_key(wp_unslash($_POST['scope'])) === 'all';

        $resolved = self::store_search_resolve($term, $mode, $all_store, 9);
        $search_term = $resolved['term'];
        $ids = $resolved['ids'];
        $items = [];
        foreach ($ids as $product_id) {
            $product = wc_get_product($product_id);
            if (!$product) continue;
            $product_mode = self::shop_product_mode($product);

            $type_label = 'STORE';
            if ($product_mode === 'raffle') $type_label = 'RAFFLE';
            elseif ($product_mode === 'both') $type_label = 'STORE + RAFFLE';

            $categories = wp_get_post_terms($product_id, 'product_cat', ['fields' => 'names']);
            if (is_wp_error($categories) || !is_array($categories)) $categories = [];
            $categories = array_values(array_filter($categories, static function ($name) {
                return strtolower(trim((string) $name)) !== 'uncategorized';
            }));

            $price_parts = [];
            if ($product_mode === 'retail' || $product_mode === 'both') {
                $retail = $product_mode === 'both'
                    ? (float) get_post_meta($product_id, \RaffleLB\Core\Contracts::META_BUY_NOW_PRICE, true)
                    : (float) $product->get_price();
                $formatted = self::store_search_display_price($retail);
                if ($formatted !== '') $price_parts[] = 'BUY NOW ' . $formatted;
            }
            if ($product_mode === 'raffle' || $product_mode === 'both') {
                $formatted = self::store_search_display_price((float) $product->get_price());
                if ($formatted !== '') $price_parts[] = 'ENTRY ' . $formatted;
            }

            $image = '';
            $image_id = $product->get_image_id();
            if ($image_id) $image = (string) wp_get_attachment_image_url($image_id, 'woocommerce_thumbnail');
            if ($image === '' && function_exists('wc_placeholder_img_src')) $image = (string) wc_placeholder_img_src('woocommerce_thumbnail');

            $items[] = [
                'id'       => $product_id,
                'title'    => html_entity_decode($product->get_name(), ENT_QUOTES, get_bloginfo('charset')),
                'url'      => get_permalink($product_id),
                'image'    => $image,
                'type'     => $type_label,
                'category' => isset($categories[0]) ? html_entity_decode((string) $categories[0], ENT_QUOTES, get_bloginfo('charset')) : '',
                'price'    => implode('  •  ', $price_parts),
                'sku'      => (string) $product->get_sku(),
            ];
        }

        $shop_url = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : home_url('/shop/');
        $view_args = ['rl_search' => $search_term];
        if ($all_store) {
            $view_args['rl_search_scope'] = 'all';
        } elseif ($mode !== 'both') {
            $view_args['rl_view'] = $mode;
        }
        $view_all = add_query_arg($view_args, $shop_url) . '#rl-store-search';

        wp_send_json_success([
            'items'      => $items,
            'count'      => count($items),
            'view_all'   => $view_all,
            'query'      => $search_term,
            'original'   => $resolved['original'],
            'corrected'  => !empty($resolved['corrected']),
        ]);
    }

    /** Premium Store search UI, mounted directly below the Shopping Mode toolbar. */
    public static function store_search_ui() {
        if (is_admin() || !self::shop_query_is_catalog()) return;
        $mode = self::shop_view_mode();
        $mode_labels = [
            'both' => 'Store & Raffle',
            'retail' => 'Store Only',
            'raffle' => 'Raffle Only',
        ];
        $current = isset($_GET['rl_search']) ? sanitize_text_field(wp_unslash($_GET['rl_search'])) : '';
        $corrected_from = '';
        if (!empty($GLOBALS['rafflelb_store_search_corrected'])) {
            $corrected_from = !empty($GLOBALS['rafflelb_store_search_original']) ? (string) $GLOBALS['rafflelb_store_search_original'] : $current;
            $current = (string) $GLOBALS['rafflelb_store_search_corrected'];
        }
        $all_store = isset($_GET['rl_search_scope']) && sanitize_key(wp_unslash($_GET['rl_search_scope'])) === 'all';
        $ajax_url = admin_url('admin-ajax.php');
        $shop_url = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : home_url('/shop/');
        ?>
        <section id="rl-store-search" class="rl-store-search" data-mode="<?php echo esc_attr($mode); ?>" data-ajax="<?php echo esc_url($ajax_url); ?>" data-shop="<?php echo esc_url($shop_url); ?>" data-corrected-from="<?php echo esc_attr($corrected_from); ?>" aria-label="Search the RaffleLB Store">
            <div class="rl-store-search-head">
                <div class="rl-store-search-title"><span class="rl-store-search-dot"></span><strong>SEARCH THE STORE</strong><small>Products, raffles, brands &amp; categories</small></div>
                <span class="rl-store-search-scope-text">Searching <b><?php echo esc_html($all_store ? 'All Store' : $mode_labels[$mode]); ?></b></span>
            </div>
            <div class="rl-store-search-shell">
                <span class="rl-store-search-icon" aria-hidden="true"></span>
                <input class="rl-store-search-input" type="search" autocomplete="off" spellcheck="false" value="<?php echo esc_attr($current); ?>" placeholder="Search products, raffles, brands..." aria-label="Search products, raffles, brands and categories" aria-expanded="false" aria-controls="rl-store-search-results">
                <button class="rl-store-search-clear" type="button" aria-label="Clear search"<?php echo $current === '' ? ' hidden' : ''; ?>>×</button>
                <label class="rl-store-search-all">
                    <input type="checkbox"<?php checked($all_store); ?>>
                    <span class="rl-store-search-switch" aria-hidden="true"></span>
                    <span>SEARCH ALL STORE</span>
                </label>
            </div>
            <div id="rl-store-search-results" class="rl-store-search-results" hidden>
                <div class="rl-store-search-status">Start typing to search.</div>
            </div>
        </section>
        <style id="rafflelb-store-search-css-v0215">
        .rl-store-search{position:relative;z-index:36;width:100%;margin:-2px 0 18px;padding:14px;border:1px solid #222a21;border-radius:14px;background:linear-gradient(145deg,#090d09,#050705);box-shadow:0 14px 34px rgba(0,0,0,.16);font-family:var(--rl-font,"Manrope",sans-serif);box-sizing:border-box}
        .rl-store-search *{box-sizing:border-box}
        .rl-store-search-head{display:flex;align-items:center;justify-content:space-between;gap:14px;margin:0 2px 10px}
        .rl-store-search-title{display:flex;align-items:center;gap:8px;min-width:0;color:#fff}
        .rl-store-search-dot{width:6px;height:6px;flex:0 0 6px;border-radius:50%;background:#baff00;box-shadow:0 0 10px rgba(186,255,0,.55)}
        .rl-store-search-title strong{font-size:10px;line-height:1;font-weight:900;letter-spacing:.13em;color:#baff00}
        .rl-store-search-title small{font-size:10px;line-height:1.2;font-weight:650;color:#747c72}
        .rl-store-search-scope-text{font-size:9px;font-weight:700;letter-spacing:.04em;color:#71786f;white-space:nowrap}.rl-store-search-scope-text b{color:#c8cec5;font-weight:850}
        .rl-store-search-shell{position:relative;display:flex;align-items:center;min-height:54px;border:1px solid #2c342b;border-radius:11px;background:#020302;transition:border-color .18s ease,box-shadow .18s ease}
        .rl-store-search-shell:focus-within{border-color:rgba(186,255,0,.7);box-shadow:0 0 0 3px rgba(186,255,0,.07)}
        .rl-store-search-icon{position:relative;width:18px;height:18px;flex:0 0 18px;margin-left:17px;margin-right:11px;border:2px solid #7f887b;border-radius:50%}
        .rl-store-search-icon:after{content:"";position:absolute;width:7px;height:2px;right:-5px;bottom:-2px;border-radius:2px;background:#7f887b;transform:rotate(45deg);transform-origin:center}
        .rl-store-search-input{min-width:0;flex:1 1 auto;height:52px!important;margin:0!important;padding:0 42px 0 0!important;border:0!important;outline:0!important;background:transparent!important;box-shadow:none!important;color:#f7f8f5!important;font-family:inherit!important;font-size:14px!important;font-weight:650!important;letter-spacing:0!important}
        .rl-store-search-input::placeholder{color:#646b62!important;opacity:1}
        .rl-store-search-clear{position:absolute;right:210px;width:30px;height:30px;display:flex;align-items:center;justify-content:center;border:0;border-radius:7px;background:transparent;color:#7e867b;font-size:21px;line-height:1;cursor:pointer}.rl-store-search-clear:hover{background:#111610;color:#fff}
        .rl-store-search-all{height:52px;display:flex;align-items:center;gap:9px;flex:0 0 auto;padding:0 15px;border-left:1px solid #20271f;color:#92998f;font-size:9px;font-weight:850;letter-spacing:.08em;cursor:pointer;user-select:none}
        .rl-store-search-all input{position:absolute;opacity:0;pointer-events:none}
        .rl-store-search-switch{position:relative;width:32px;height:18px;flex:0 0 32px;border:1px solid #3a4238;border-radius:999px;background:#171b16;transition:.18s ease}
        .rl-store-search-switch:after{content:"";position:absolute;top:3px;left:3px;width:10px;height:10px;border-radius:50%;background:#858d82;transition:.18s ease}
        .rl-store-search-all input:checked+.rl-store-search-switch{border-color:#baff00;background:rgba(186,255,0,.14)}
        .rl-store-search-all input:checked+.rl-store-search-switch:after{left:17px;background:#baff00;box-shadow:0 0 8px rgba(186,255,0,.45)}
        .rl-store-search-results{position:absolute;left:14px;right:14px;top:100%;margin-top:-2px;overflow:hidden;border:1px solid #283027;border-radius:12px;background:#070a07;box-shadow:0 22px 60px rgba(0,0,0,.52)}
        .rl-store-search-status{padding:18px;color:#828a7f;font-size:12px;font-weight:650;text-align:center}
        .rl-store-search-list{max-height:430px;overflow:auto;padding:7px}
        .rl-store-search-item{display:grid;grid-template-columns:58px minmax(0,1fr) auto;gap:12px;align-items:center;min-height:72px;padding:7px 10px;border-radius:9px;text-decoration:none!important;transition:.15s ease}
        .rl-store-search-item:hover,.rl-store-search-item:focus{outline:0;background:#111610}
        .rl-store-search-thumb{width:58px;height:58px;border-radius:8px;overflow:hidden;background:#000;border:1px solid #171d16}.rl-store-search-thumb img{width:100%;height:100%;display:block;object-fit:contain;background:#000}
        .rl-store-search-copy{min-width:0}.rl-store-search-badges{display:flex;align-items:center;gap:6px;margin-bottom:4px}.rl-store-search-type{font-size:8px;font-weight:900;letter-spacing:.08em;color:#baff00}.rl-store-search-cat{overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:8px;font-weight:750;color:#687065}
        .rl-store-search-name{display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:#f4f6f2;font-size:13px;font-weight:800;line-height:1.25}.rl-store-search-sku{display:block;margin-top:3px;color:#5f675d;font-size:9px;font-weight:650}
        .rl-store-search-price{max-width:240px;color:#cbd0c8;font-size:10px;font-weight:800;text-align:right;white-space:nowrap}
        .rl-store-search-correction{display:flex;align-items:center;gap:8px;padding:9px 12px;border-bottom:1px solid rgba(186,255,0,.16);background:rgba(186,255,0,.055);color:#aab2a6;font-size:10px;font-weight:750}.rl-store-search-correction b{color:#baff00;font-weight:900}.rl-store-search-correction s{color:#667063;text-decoration-color:#667063}.rl-store-search-footer{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:10px 12px;border-top:1px solid #1e251d;background:#090d09}.rl-store-search-footer span{color:#6f776d;font-size:10px;font-weight:700}.rl-store-search-view-all{display:inline-flex;align-items:center;justify-content:center;min-height:34px;padding:0 13px;border:1px solid rgba(186,255,0,.34);border-radius:7px;background:rgba(186,255,0,.06);color:#baff00!important;font-size:9px;font-weight:900;letter-spacing:.06em;text-decoration:none!important}.rl-store-search-view-all:hover{background:#baff00;color:#080a07!important}
        @media(max-width:767px){.rl-store-search{margin-bottom:12px;padding:10px;border-radius:12px}.rl-store-search-head{margin-bottom:8px}.rl-store-search-title small,.rl-store-search-scope-text{display:none}.rl-store-search-shell{display:grid;grid-template-columns:40px minmax(0,1fr) 34px;min-height:52px}.rl-store-search-icon{margin-left:14px;margin-right:8px}.rl-store-search-input{height:50px!important;padding-right:4px!important;font-size:12px!important}.rl-store-search-clear{position:static;right:auto}.rl-store-search-all{grid-column:1/-1;height:40px;justify-content:flex-start;padding:0 13px;border-top:1px solid #1e251d;border-left:0}.rl-store-search-results{left:10px;right:10px}.rl-store-search-item{grid-template-columns:50px minmax(0,1fr);gap:10px}.rl-store-search-thumb{width:50px;height:50px}.rl-store-search-price{grid-column:2;max-width:none;text-align:left;white-space:normal;margin-top:-5px}.rl-store-search-list{max-height:360px}}
        </style>
        <script id="RaffleLBStoreSearch" data-no-optimize="1">
        (function(){
            var root=document.getElementById('rl-store-search'); if(!root) return;
            var input=root.querySelector('.rl-store-search-input'), results=root.querySelector('.rl-store-search-results'), clear=root.querySelector('.rl-store-search-clear'), allToggle=root.querySelector('.rl-store-search-all input'), scopeText=root.querySelector('.rl-store-search-scope-text b');
            var mode=root.getAttribute('data-mode')||'both', ajax=root.getAttribute('data-ajax'), shop=root.getAttribute('data-shop'), timer=null, controller=null, requestSeq=0;
            var correctedFrom=root.getAttribute('data-corrected-from')||'';
            var labels={both:'Store & Raffle',retail:'Store Only',raffle:'Raffle Only'};

            function place(){
                var toolbar=document.getElementById('rl-shop-controls');
                if(toolbar && toolbar.parentNode){ if(toolbar.nextElementSibling!==root) toolbar.insertAdjacentElement('afterend',root); return true; }
                var products=document.querySelector('.products');
                if(products && products.parentNode){ products.parentNode.insertBefore(root,products); return true; }
                return false;
            }
            place();
            if(document.body){ new MutationObserver(function(){ if(!document.body.contains(root)) return; place(); }).observe(document.body,{childList:true,subtree:true}); }

            function esc(text){ var d=document.createElement('div'); d.textContent=text==null?'':String(text); return d.innerHTML; }
            function scope(){ return allToggle.checked?'all':'current'; }
            function viewUrl(queryOverride){
                var u=new URL(shop,window.location.href), q=(queryOverride==null?input.value:String(queryOverride)).trim();
                if(q) u.searchParams.set('rl_search',q);
                if(allToggle.checked){ u.searchParams.set('rl_search_scope','all'); u.searchParams.delete('rl_view'); }
                else { u.searchParams.delete('rl_search_scope'); if(mode!=='both')u.searchParams.set('rl_view',mode); else u.searchParams.delete('rl_view'); }
                u.hash='rl-store-search'; return u.href;
            }
            function hide(){ results.hidden=true; input.setAttribute('aria-expanded','false'); }
            function show(html){ results.innerHTML=html; results.hidden=false; input.setAttribute('aria-expanded','true'); }
            function render(data){
                var items=(data&&data.items)||[];
                var fixed=(data&&data.corrected&&data.query)?String(data.query):'';
                var original=(data&&data.original)?String(data.original):'';
                if(fixed){ input.value=fixed; clear.hidden=false; }
                var correction=fixed?'<div class="rl-store-search-correction">AUTOCORRECTED <s>'+esc(original)+'</s> → <b>'+esc(fixed)+'</b></div>':'';
                if(!items.length){ show(correction+'<div class="rl-store-search-status">No matching products found.</div>'); return; }
                var html=correction+'<div class="rl-store-search-list">';
                items.forEach(function(item){
                    html+='<a class="rl-store-search-item" href="'+esc(item.url)+'">';
                    html+='<span class="rl-store-search-thumb"><img src="'+esc(item.image)+'" alt="" loading="lazy"></span>';
                    html+='<span class="rl-store-search-copy"><span class="rl-store-search-badges"><b class="rl-store-search-type">'+esc(item.type)+'</b>'+(item.category?'<em class="rl-store-search-cat">'+esc(item.category)+'</em>':'')+'</span><strong class="rl-store-search-name">'+esc(item.title)+'</strong>'+(item.sku?'<small class="rl-store-search-sku">SKU '+esc(item.sku)+'</small>':'')+'</span>';
                    html+='<span class="rl-store-search-price">'+esc(item.price)+'</span></a>';
                });
                html+='</div><div class="rl-store-search-footer"><span>Search by product, SKU, category or brand</span><a class="rl-store-search-view-all" data-no-ajax="1" href="'+esc((data&&data.view_all)||viewUrl((data&&data.query)||null))+'">VIEW ALL RESULTS →</a></div>';
                show(html);
            }
            function searchNow(){
                var q=input.value.trim(); clear.hidden=!q;
                if(q.length<2){ if(q.length) show('<div class="rl-store-search-status">Type at least 2 characters.</div>'); else hide(); return; }
                if(controller) controller.abort(); controller=window.AbortController?new AbortController():null;
                var seq=++requestSeq;
                show('<div class="rl-store-search-status">Searching the Store…</div>');
                var body=new URLSearchParams(); body.set('action','rafflelb_store_search'); body.set('q',q); body.set('mode',mode); body.set('scope',scope());
                fetch(ajax,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8'},body:body.toString(),signal:controller?controller.signal:undefined})
                    .then(function(r){return r.json();}).then(function(json){ if(seq!==requestSeq)return; if(json&&json.success) render(json.data); else show('<div class="rl-store-search-status">Search is temporarily unavailable.</div>'); })
                    .catch(function(err){ if(err&&err.name==='AbortError')return; show('<div class="rl-store-search-status">Search is temporarily unavailable.</div>'); });
            }
            function queue(){ clearTimeout(timer); timer=setTimeout(searchNow,180); }
            /* WoodMart can intercept archive links and rebuild the product grid via
             * its own AJAX request, which may drop our custom rl_search query arg.
             * Force VIEW ALL RESULTS to be a real document navigation so the
             * authoritative filtered catalogue query runs with the exact search. */
            document.addEventListener('click',function(e){
                var link=e.target&&e.target.closest?e.target.closest('.rl-store-search-view-all'):null;
                if(!link || !root.contains(link) || !link.href) return;
                if(e.button && e.button!==0) return;
                if(e.metaKey||e.ctrlKey||e.shiftKey||e.altKey) return;
                e.preventDefault();
                e.stopPropagation();
                e.stopImmediatePropagation();
                window.location.assign(link.href);
            },true);

            input.addEventListener('input',queue);
            input.addEventListener('focus',function(){ if(input.value.trim().length>=2) queue(); });
            input.addEventListener('keydown',function(e){ if(e.key==='Enter'){ e.preventDefault(); if(input.value.trim().length>=2) window.location.assign(viewUrl()); } else if(e.key==='Escape') hide(); });
            clear.addEventListener('click',function(){ input.value=''; clear.hidden=true; hide(); input.focus(); });
            allToggle.addEventListener('change',function(){ if(scopeText) scopeText.textContent=allToggle.checked?'All Store':(labels[mode]||'Store'); if(input.value.trim().length>=2) queue(); });
            document.addEventListener('click',function(e){ if(!root.contains(e.target)) hide(); });
            if(input.value.trim().length>=2) clear.hidden=false;
        })();
        </script>
        <?php
    }

    public static function public_banner_css() {
        if (is_admin()) return;
        ?>
        <style id="rafflelb-public-banners-v0213">
        /* v0.2.13 configurable announcement strip: font size, weight, height and animation speed. */
        .rl-site-banner-stack{position:relative;z-index:45;width:100%;padding:0;background:#000;box-sizing:border-box}
        .rl-site-banner-stack .rl-shop-announcement{width:100%;margin:0}
        .rl-shop-announcement{display:flex!important;align-items:stretch!important;gap:0!important;max-width:none!important;min-height:46px!important;border:0!important;border-top:1px solid rgba(186,255,0,.16)!important;border-bottom:1px solid rgba(186,255,0,.30)!important;border-radius:0!important;background:#030503!important;color:#fff!important;overflow:hidden!important;box-sizing:border-box!important;font-family:var(--rl-font,"Manrope",sans-serif)!important;box-shadow:0 8px 28px rgba(0,0,0,.24)!important}
        .rl-shop-announcement.is-global{background:linear-gradient(90deg,#020402 0%,#071006 48%,#020402 100%)!important;border-bottom-color:rgba(186,255,0,.48)!important}
        .rl-shop-announcement-badge{position:relative;display:flex!important;align-items:center!important;justify-content:center!important;align-self:stretch!important;flex:0 0 auto!important;min-width:132px!important;padding:0 18px!important;border-right:1px solid rgba(186,255,0,.24)!important;background:rgba(186,255,0,.075)!important;color:#baff00!important;font-size:10px!important;font-weight:900!important;letter-spacing:.13em!important;line-height:1!important;z-index:3!important;text-transform:uppercase!important}
        .rl-shop-announcement-badge:before{content:"";display:block;width:6px;height:6px;margin-right:9px;border-radius:50%;background:#baff00;box-shadow:0 0 12px rgba(186,255,0,.75)}
        .rl-shop-announcement-viewport{position:relative!important;min-width:0!important;flex:1 1 auto!important;overflow:hidden!important;white-space:nowrap!important;background:transparent!important}
        .rl-shop-announcement-viewport:before,.rl-shop-announcement-viewport:after{content:"";position:absolute;top:0;bottom:0;width:48px;z-index:2;pointer-events:none}
        .rl-shop-announcement-viewport:before{left:0;background:linear-gradient(90deg,#030503 0%,rgba(3,5,3,0) 100%)}
        .rl-shop-announcement-viewport:after{right:0;background:linear-gradient(270deg,#030503 0%,rgba(3,5,3,0) 100%)}
        .rl-shop-announcement.is-global .rl-shop-announcement-viewport:before{background:linear-gradient(90deg,#071006 0%,rgba(7,16,6,0) 100%)}
        .rl-shop-announcement.is-global .rl-shop-announcement-viewport:after{background:linear-gradient(270deg,#020402 0%,rgba(2,4,2,0) 100%)}
        .rl-shop-announcement-track{display:inline-flex!important;align-items:center!important;width:max-content!important;min-width:0!important;height:calc(var(--rl-banner-height,46px) - 2px)!important;padding-left:100%!important;will-change:transform!important;transform:translate3d(0,0,0);animation:rlShopAnnouncementTicker var(--rl-banner-speed,14s) linear infinite!important;animation-play-state:running!important}
        .rl-shop-announcement-item{display:inline-flex!important;align-items:center!important;flex:0 0 auto!important;padding:0 72px 0 34px!important;color:#f8faf6!important;font-size:var(--rl-banner-font-size,13px)!important;font-weight:var(--rl-banner-font-weight,700)!important;letter-spacing:.015em!important;line-height:calc(var(--rl-banner-height,46px) - 2px)!important;white-space:nowrap!important;text-shadow:0 1px 0 rgba(0,0,0,.35)!important}
        .rl-shop-announcement-static{display:flex!important;align-items:center!important;min-height:calc(var(--rl-banner-height,46px) - 2px)!important;padding:0 30px!important;color:#f8faf6!important;font-size:var(--rl-banner-font-size,13px)!important;font-weight:var(--rl-banner-font-weight,700)!important;letter-spacing:.015em!important;line-height:1.35!important;white-space:normal!important}
        .rl-shop-announcement.is-moving:hover .rl-shop-announcement-track{animation-play-state:paused!important}
        @keyframes rlShopAnnouncementTicker{0%{transform:translate3d(0,0,0)}100%{transform:translate3d(-100%,0,0)}}
        @media(max-width:767px){
            .rl-shop-announcement{min-height:max(40px,calc(var(--rl-banner-height,46px) - 4px))!important}
            .rl-shop-announcement-badge{min-width:96px!important;padding:0 11px!important;font-size:8px!important;letter-spacing:.10em!important}
            .rl-shop-announcement-badge:before{width:5px;height:5px;margin-right:6px}
            .rl-shop-announcement-track{height:calc(max(40px,calc(var(--rl-banner-height,46px) - 4px)) - 2px)!important}
            .rl-shop-announcement-item{padding:0 48px 0 24px!important;font-size:max(10px,calc(var(--rl-banner-font-size,13px) - 2px))!important;line-height:calc(max(40px,calc(var(--rl-banner-height,46px) - 4px)) - 2px)!important}
            .rl-shop-announcement-static{min-height:calc(max(40px,calc(var(--rl-banner-height,46px) - 4px)) - 2px)!important;padding:7px 16px!important;font-size:max(10px,calc(var(--rl-banner-font-size,13px) - 2px))!important}
            .rl-shop-announcement-viewport:before,.rl-shop-announcement-viewport:after{width:24px}
        }
        </style>
        <?php
    }

    /** Match Store Only's solid black media well in every shopping mode. */
    /**
     * v0.2.57 — root cause of the 0.2.56 mobile Store redesign not
     * appearing on the live site: it was an inline <style id="..."> block
     * printed on wp_head. WP Rocket's "Remove Unused CSS" (RUCSS) rewrites
     * or strips inline style blocks it does not recognize, and this
     * plugin already had to special-case three other inline blocks for
     * the exact same reason (see rocket_rucss_inline_content_exclusions
     * below) — the new block was simply never added to that list, and its
     * rules live only inside a mobile-only @media query that a desktop-
     * width RUCSS "used CSS" crawl will not detect as used at all, so the
     * whole block was a prime candidate for being dropped from what real
     * phones actually received. On top of that, updating the plugin file
     * on the server does not by itself purge WP Rocket's existing page
     * cache, so anonymous/mobile visitors could keep being served an
     * HTML snapshot generated before 0.2.56 even existed.
     *
     * v0.2.57 fixes both instead of stacking another inline patch:
     *  - the mobile card CSS now lives in its own enqueued file
     *    (assets/mobile-store-card.css) and is excluded from WP Rocket's
     *    CSS optimization the same proven way assets/single-product.css
     *    already is (rocket_exclude_css / rocket_minify_excluded_external_css
     *    / rocket_exclude_defer_css / rocket_rucss_safelist), so RUCSS
     *    can never rewrite or drop it, mobile media query included;
     *  - mobile_store_assets_version_bump_purge() below clears WP
     *    Rocket's (and other common) page cache the moment this
     *    constant's VERSION changes, so a plugin file update is enough —
     *    nobody has to be told to clear cache by hand.
     *
     * Presentation only. No markup, hooks, pricing, filtering, sorting,
     * cart, checkout, raffle, or Selection logic changes. Desktop
     * (>767px) and the single product page are untouched — see
     * assets/mobile-store-card.css for the actual rules.
     */
    public static function enqueue_mobile_store_card_styles() {
        if (!self::shop_query_is_catalog()) return;
        $path = plugin_dir_path(__FILE__) . 'assets/mobile-store-card.css';
        $version = file_exists($path) ? (string) filemtime($path) : self::VERSION;
        /* v0.2.58 — assets/shop-reference.css is unconditionally enqueued
           on every Shop/category archive (legacy_callback_18806, proxied
           from RaffleLB_Draw_Engine) and its selectors always carry the
           .rl-store-reference body class (legacy_callback_18802) in
           addition to .rafflelb-raffle-archive, which gives its rules one
           more class of specificity than a plain
           "body.rafflelb-raffle-archive ..." selector. Declaring it here
           as a dependency guarantees this stylesheet always prints AFTER
           shop-reference.css regardless of hook-priority timing, and every
           selector in mobile-store-card.css now also carries
           .rl-store-reference so it matches (never loses on) that same
           specificity instead of quietly losing the cascade to it. See
           the v0.2.58 note in shop-reference.css and mobile-store-card.css.
        */
        wp_enqueue_style(
            'rafflelb-mobile-store-card',
            plugins_url('assets/mobile-store-card.css', __FILE__),
            ['rafflelb-store-reference'],
            $version
        );
    }

    /**
     * Purges common page-cache plugins once, the first time a request is
     * served after this plugin's VERSION constant changes (i.e. right
     * after a deploy). Idempotent: every later request on the same
     * version sees the stored option already matches and returns
     * immediately, so this never runs a real cache purge on normal
     * traffic. This does not depend on WordPress firing an activation
     * hook, which it never does for a plain file overwrite on the
     * server (the actual way this plugin gets updated) without an
     * explicit deactivate/reactivate.
     */
    public static function mobile_store_assets_version_bump_purge() {
        $stored = get_option('rafflelb_shop_version', '');
        if ($stored === self::VERSION) return;
        update_option('rafflelb_shop_version', self::VERSION, false);
        if ($stored === '') return; // First install: nothing stale to purge.

        if (function_exists('rocket_clean_domain')) {
            rocket_clean_domain();
        }
        if (function_exists('w3tc_flush_all')) {
            w3tc_flush_all();
        }
        if (function_exists('wp_cache_clear_cache')) {
            wp_cache_clear_cache();
        }
        if (function_exists('litespeed_purge_all')) {
            litespeed_purge_all();
        }
        if (function_exists('opcache_reset')) {
            @opcache_reset();
        }
    }

    public static function shop_archive_black_media_css() {
        if (!self::shop_query_is_catalog()) return;
        ?>
        <style id="rafflelb-shop-black-media-v0211">
        body.rafflelb-raffle-archive .rl-raffle-card .product-element-top,
        body.rafflelb-raffle-archive .rl-raffle-card .product-element-top > a,
        body.rafflelb-raffle-archive .rl-raffle-card .product-element-top :is(.product-image-link,.product-image-wrap,.product-element-top-inner),
        body.rafflelb-raffle-archive .rl-raffle-card .product-element-top img{
            background:#000!important;
        }
        </style>
        <?php
    }

    /**
     * v0.2.48 — On mobile, the site-wide "Refer & Earn" pill (owned by
     * RaffleLB Referral Points) and the floating AI Assistant launcher
     * (owned by RaffleLB AI Assistant) sit bottom-left/bottom-right and
     * visually cover the lower part of the last visible product card on
     * the Shop archive. Neither plugin's own file is touched: this is a
     * CSS-only, mobile-only, Shop-archive-only override (their normal
     * site-wide appearance elsewhere is unaffected) that modestly shrinks
     * each widget and keeps it closer to the screen edge. Their markup,
     * links, and click behaviour are untouched, so functionality is
     * unaffected.
     */
    public static function mobile_floating_widgets_css() {
        if (!self::shop_query_is_catalog()) return;
        ?>
        <style id="rafflelb-mobile-floating-widgets-v0248">
        @media(max-width:767px){
            body.rafflelb-raffle-archive .rl-ref-float{
                width:112px!important;
                min-width:112px!important;
                max-width:112px!important;
                height:40px!important;
                min-height:40px!important;
                max-height:40px!important;
                left:max(10px,env(safe-area-inset-left))!important;
                bottom:calc(12px + env(safe-area-inset-bottom))!important;
                padding:3px 9px 3px 3px!important;
                gap:6px!important;
            }
            body.rafflelb-raffle-archive .rl-ref-float-icon{
                width:30px!important;
                height:30px!important;
                flex:0 0 30px!important;
                font-size:13px!important;
            }
            body.rafflelb-raffle-archive .rl-ref-float-label{
                font-size:9px!important;
            }
            body.rafflelb-raffle-archive #rlb-ai.rlb-ai .rlb-ai__launcher{
                width:56px!important;
                height:56px!important;
                border-radius:16px!important;
            }
            body.rafflelb-raffle-archive #rlb-ai.rlb-ai .rlb-ai__launcher-image{
                width:37px!important;
                height:34px!important;
            }
            body.rafflelb-raffle-archive #rlb-ai.rlb-ai{
                right:max(14px,env(safe-area-inset-right))!important;
                bottom:calc(12px + env(safe-area-inset-bottom))!important;
            }
        }
        </style>
        <?php
    }

    public static function legacy_callback_18802($classes) {
    if ((function_exists('is_shop') && is_shop()) || (function_exists('is_product_category') && is_product_category())) $classes[] = 'rl-store-reference';
    return $classes;
}

    public static function legacy_callback_18806() {
    if ((function_exists('is_shop') && is_shop()) || (function_exists('is_product_category') && is_product_category())) {
        wp_enqueue_style('rafflelb-store-reference', plugins_url('assets/shop-reference.css', __FILE__), [], self::VERSION);
    }
}

    /* -----------------------------------------------------------------
     * Dynamic category-scoped Brands filter (v0.2.30).
     *
     * Brands stay hidden until a product category is selected. The
     * taxonomy is detected from the taxonomies actually registered on
     * WooCommerce products, and the visible brand list is rebuilt from
     * products that are eligible in the current Shopping Mode.
     *
     * Filtering itself uses RaffleLB's own `rl_brand` query argument so
     * the feature works with WooCommerce Brands, product attributes, and
     * third-party brand taxonomies alike; it does not depend on a theme's
     * layered-navigation query format.
     * --------------------------------------------------------------- */

    /**
     * Every taxonomy registered on 'product' that looks like a brand
     * taxonomy, in the same preference order rafflelb-products uses to pick
     * the one it saves to (product_brand first, since that is the taxonomy
     * Product Studio's own ensure_brand_taxonomy() fallback registers).
     *
     * v0.2.36 — category_brand_terms() no longer commits to a single "best
     * guess" taxonomy the way brand_taxonomy() used to. Across the several
     * debug iterations on this feature, brand terms already saved on live
     * products did not reliably end up in whichever taxonomy this static
     * guess preferred that day, so the Brands row silently rendered empty
     * (server-side $rl_brand_terms === [] short-circuits setupBrandFilter()
     * in the JS) even though the product genuinely had a Brand assigned.
     * Returning every candidate lets category_brand_terms() pick the one
     * that actually has term relationships on the eligible products, i.e.
     * the taxonomy Product Studio really saved to, not the one we assumed.
     */
    private static function brand_taxonomy_candidates() {
        static $resolved = null;
        if ($resolved !== null) return $resolved;

        $candidates = [
            'product_brand',       // WooCommerce Brands / modern WooCommerce.
            'pa_brands',
            'pa_brand',
            'pwb-brand',           // Perfect Brands for WooCommerce.
            'yith_product_brand',
            'berocket_brand',
            'brand',
        ];

        $found = [];
        foreach ($candidates as $taxonomy) {
            if (!taxonomy_exists($taxonomy)) continue;
            $object_types = get_taxonomy($taxonomy);
            $object_types = $object_types && !empty($object_types->object_type) ? (array) $object_types->object_type : [];
            if (empty($object_types) || in_array('product', $object_types, true)) {
                $found[] = $taxonomy;
            }
        }

        $objects = get_object_taxonomies('product', 'objects');
        if (is_array($objects)) {
            foreach ($objects as $taxonomy => $object) {
                if (in_array($taxonomy, $found, true)) continue;
                if (in_array($taxonomy, ['product_cat', 'product_tag', 'product_type', 'product_visibility', 'product_shipping_class'], true)) continue;
                $name = strtolower((string) $taxonomy);
                $label = isset($object->label) ? strtolower((string) $object->label) : '';
                $singular = isset($object->labels->singular_name) ? strtolower((string) $object->labels->singular_name) : '';
                if (strpos($name, 'brand') !== false || strpos($label, 'brand') !== false || strpos($singular, 'brand') !== false) {
                    $found[] = $taxonomy;
                }
            }
        }

        $resolved = $found;
        return $resolved;
    }

    /** The single most-preferred brand taxonomy, if any (display/cache use only). */
    private static function brand_taxonomy() {
        $candidates = self::brand_taxonomy_candidates();
        return $candidates ? $candidates[0] : '';
    }

    /** One selected brand at a time; empty means All Brands. */
    private static function shop_brand_slug() {
        if (!isset($_GET['rl_brand'])) return '';
        return sanitize_title(wp_unslash($_GET['rl_brand']));
    }

    /** Bump the cache-busting version whenever products or terms change. */
    public static function bump_brand_cache_version() {
        $version = (int) get_option('rafflelb_brand_cache_version', 1);
        update_option('rafflelb_brand_cache_version', $version + 1, false);
    }

    /** Invalidate category/brand caches after term relationships are assigned. */
    public static function brand_object_terms_changed($object_id, $terms, $tt_ids, $taxonomy, $append, $old_tt_ids) {
        if (get_post_type((int) $object_id) !== 'product') return;
        if ($taxonomy === 'product_cat' || in_array($taxonomy, self::brand_taxonomy_candidates(), true)) {
            self::bump_brand_cache_version();
        }
    }

    /**
     * Brand terms actually represented by visible products in this category
     * and Shopping Mode. Tax-query include_children remains true, matching a
     * normal WooCommerce category archive.
     *
     * Returns ['taxonomy' => string, 'brands' => [['slug'=>,'name'=>], ...]].
     * Every brand-like taxonomy candidate is tried against this exact set of
     * eligible product IDs, and the first one with any term relationship on
     * them wins — see brand_taxonomy_candidates() for why this is evidence-
     * based instead of a single fixed guess.
     */
    private static function category_brand_terms($category_term_id, $mode = 'both') {
        $category_term_id = (int) $category_term_id;
        $mode = in_array($mode, ['both', 'retail', 'raffle'], true) ? $mode : 'both';
        $candidates = self::brand_taxonomy_candidates();
        if ($category_term_id <= 0 || !$candidates) return ['taxonomy' => '', 'brands' => []];

        $version = (int) get_option('rafflelb_brand_cache_version', 1);
        $cache_key = 'rlb_cat_brands_v5_' . $category_term_id . '_' . $mode . '_' . md5(implode(',', $candidates)) . '_' . $version;
        $cached = get_transient($cache_key);
        if (is_array($cached) && isset($cached['brands'])) return $cached;

        $product_ids = get_posts([
            'post_type'              => 'product',
            'post_status'            => 'publish',
            'posts_per_page'         => -1,
            'fields'                 => 'ids',
            'no_found_rows'          => true,
            'update_post_meta_cache' => true,
            'update_post_term_cache' => false,
            'tax_query'              => [[
                'taxonomy'         => 'product_cat',
                'field'            => 'term_id',
                'terms'            => [$category_term_id],
                'include_children' => true,
            ]],
        ]);

        $eligible_ids = [];
        $hide_out_of_stock = ('yes' === get_option('woocommerce_hide_out_of_stock_items', 'no'));
        foreach ((array) $product_ids as $product_id) {
            $product_id = (int) $product_id;
            if ($product_id <= 0) continue;

            /* v0.2.33 — determine catalogue eligibility from persisted product
             * data only. Do not call WC_Product::is_visible() or any session-
             * sensitive visibility helper: Brands must be identical for guests
             * and logged-in customers. */
            if (taxonomy_exists('product_visibility')) {
                $visibility = wp_get_object_terms($product_id, 'product_visibility', ['fields' => 'slugs']);
                if (!is_wp_error($visibility) && in_array('exclude-from-catalog', (array) $visibility, true)) continue;
            }
            if ($hide_out_of_stock && get_post_meta($product_id, '_stock_status', true) === 'outofstock') continue;

            $product_mode = self::shop_product_mode($product_id);
            if (!self::shop_mode_includes_product($mode, $product_mode)) continue;
            $eligible_ids[] = $product_id;
        }

        $result = ['taxonomy' => '', 'brands' => []];
        if ($eligible_ids) {
            /* wp_get_object_terms() is deliberately used here instead of a
             * Term_Query object_ids filter. It reads the actual relationships
             * assigned to these exact products and works for native Brands,
             * WooCommerce attributes (pa_brand/pa_brands), and plugin brands.
             * Try each candidate taxonomy in preference order and use the
             * first one that actually has a term on at least one of these
             * products — i.e. the taxonomy these products were really saved
             * to, not just the one we'd guess first. */
            foreach ($candidates as $taxonomy) {
                $terms = wp_get_object_terms($eligible_ids, $taxonomy, ['fields' => 'all']);
                if (is_wp_error($terms) || !is_array($terms) || !$terms) continue;

                $brands = [];
                $seen = [];
                foreach ($terms as $term) {
                    if (!$term instanceof WP_Term || $term->slug === '') continue;
                    if (isset($seen[$term->term_id])) continue;
                    $seen[$term->term_id] = true;
                    $brands[] = [
                        'slug' => (string) $term->slug,
                        'name' => html_entity_decode((string) $term->name, ENT_QUOTES | ENT_HTML5, get_bloginfo('charset') ?: 'UTF-8'),
                    ];
                }
                if ($brands) {
                    usort($brands, static function ($a, $b) { return strcasecmp($a['name'], $b['name']); });
                    $result = ['taxonomy' => $taxonomy, 'brands' => $brands];
                    break;
                }
            }
        }

        // Cache only a non-empty result. An empty list is intentionally
        // re-evaluated on the next request so a newly assigned first brand can
        // appear immediately even if another save path did not bump our cache.
        if ($result['brands']) {
            set_transient($cache_key, $result, HOUR_IN_SECONDS);
        }
        return $result;
    }

    /**
     * Public read-only endpoint: brands for a category + Shopping Mode.
     *
     * v0.2.37 — WoodMart's AJAX shop changes the active category in place
     * (no full page reload), so the Brands row can no longer rely on the
     * PHP-rendered snapshot taken for whichever category was active at the
     * initial page load (that snapshot is what shop_native_filters_ui()
     * still seeds its client-side cache with, for the one category it
     * already knows about for free). Every other category reached through
     * an in-page AJAX transition is fetched through this endpoint instead,
     * keyed by category id + mode exactly like that client-side cache.
     */
    public static function ajax_category_brands() {
        $category_id = isset($_GET['category_id']) ? absint(wp_unslash($_GET['category_id'])) : 0;
        $mode = isset($_GET['mode']) ? sanitize_key(wp_unslash($_GET['mode'])) : 'both';
        if (!in_array($mode, ['both', 'retail', 'raffle'], true)) $mode = 'both';

        $term = $category_id > 0 ? get_term($category_id, 'product_cat') : null;
        if (!$term || is_wp_error($term)) {
            wp_send_json_success(['brands' => []]);
        }

        $result = self::category_brand_terms($category_id, $mode);
        wp_send_json_success(['brands' => $result['brands']]);
    }

    /** Apply the selected brand to the real WooCommerce category catalogue. */
    public static function apply_brand_filter_to_catalog($query) {
        if (!$query instanceof WP_Query || !$query->is_main_query()) return;
        if (is_admin() && !(function_exists('wp_doing_ajax') && wp_doing_ajax())) return;
        if (!$query->is_tax('product_cat')) return;

        $brand_slug = self::shop_brand_slug();
        if ($brand_slug === '') return;

        // Try every brand-like taxonomy candidate rather than assuming the
        // single most-preferred one; the slug only exists on whichever
        // taxonomy the product was actually saved to (see
        // brand_taxonomy_candidates()).
        $taxonomy = '';
        foreach (self::brand_taxonomy_candidates() as $candidate) {
            if (term_exists($brand_slug, $candidate)) { $taxonomy = $candidate; break; }
        }
        if ($taxonomy === '') return;

        $tax_query = $query->get('tax_query');
        if (!is_array($tax_query)) $tax_query = [];

        // Do not add the same restriction twice if another catalogue callback
        // re-runs the query preparation stage.
        foreach ($tax_query as $clause) {
            if (is_array($clause) && ($clause['taxonomy'] ?? '') === $taxonomy && ($clause['field'] ?? '') === 'slug') {
                $terms = array_map('sanitize_title', (array) ($clause['terms'] ?? []));
                if (in_array($brand_slug, $terms, true)) return;
            }
        }

        $tax_query[] = [
            'taxonomy' => $taxonomy,
            'field'    => 'slug',
            'terms'    => [$brand_slug],
            'operator' => 'IN',
        ];
        $query->set('tax_query', $tax_query);
    }

    /**
     * If a stale brand is carried to another category/mode, remove it instead
     * of letting the catalogue appear empty.
     */
    public static function validate_brand_filter_request() {
        if (is_admin() || !function_exists('is_product_category') || !is_product_category()) return;

        $requested = self::shop_brand_slug();
        if ($requested === '') return;

        if (!self::brand_taxonomy_candidates()) return;

        $queried = get_queried_object();
        if (!$queried instanceof WP_Term || $queried->taxonomy !== 'product_cat') return;

        $valid_slugs = wp_list_pluck(self::category_brand_terms($queried->term_id, self::shop_view_mode())['brands'], 'slug');
        if (in_array($requested, $valid_slugs, true)) return;

        $url = remove_query_arg(['rl_brand', 'paged', 'product-page']);
        wp_safe_redirect($url, 302);
        exit;
    }

}

/* v0.2.18 — generated product SEO descriptions for The SEO Framework.
 * Manual per-product TSF descriptions remain authoritative. */
add_filter('the_seo_framework_description_excerpt', ['RaffleLB_Shop', 'tsf_product_description_excerpt'], 20, 2);

/* v0.2.20 — treat raffle-enabled physical prizes as shippable for Merchant schema even when WooCommerce marks them virtual. */
/* v0.2.19 — enrich direct-purchase Merchant Offers with Lebanon shipping + 7-day return policy. */
add_filter('woocommerce_structured_data_product_offer', ['RaffleLB_Shop', 'structured_data_product_offer_policies'], 100, 2);

/* v0.2.17 — Google/WooCommerce product schema uses retail Buy Now pricing, never Selection entry pricing. */
add_filter('woocommerce_structured_data_product_offer', ['RaffleLB_Shop', 'structured_data_product_offer'], 99, 2);
add_filter('woocommerce_structured_data_product', ['RaffleLB_Shop', 'structured_data_product'], 99, 2);
add_filter('woocommerce_structured_data_type_for_page', ['RaffleLB_Shop', 'structured_data_types_for_page'], 99);

/* v0.2.16 — Store search View All filtering hardening plus existing autocorrect and banner controls. */
add_action('admin_init', ['RaffleLB_Shop', 'register_shop_banner_settings']);
add_filter('option_page_capability_rafflelb_shop_banners_group', static function () { return 'manage_woocommerce'; });
add_action('admin_menu', ['RaffleLB_Shop', 'register_shop_banner_menu'], 9999);
add_action('update_option_rafflelb_shop_banners', ['RaffleLB_Shop', 'banner_settings_updated'], 10, 2);
add_action('wp_head', ['RaffleLB_Shop', 'public_banner_css'], 998);
add_action('wp_head', ['RaffleLB_Shop', 'shop_archive_black_media_css'], 1000);
add_action('wp_enqueue_scripts', ['RaffleLB_Shop', 'enqueue_mobile_store_card_styles'], 30);
add_action('init', ['RaffleLB_Shop', 'mobile_store_assets_version_bump_purge'], 5);
add_action('wp_head', ['RaffleLB_Shop', 'mobile_floating_widgets_css'], 1000);
add_action('wp_footer', ['RaffleLB_Shop', 'public_banner_stack'], 2);

/* v0.2.14 — Store-wide instant search by product, SKU, category and brand. */
add_action('pre_get_posts', ['RaffleLB_Shop', 'store_search_catalog_filter'], 8);
/* Re-apply at WooCommerce's product-query stage as a late safety net. */
add_action('woocommerce_product_query', ['RaffleLB_Shop', 'store_search_catalog_filter'], 99);
add_action('wp_ajax_rafflelb_store_search', ['RaffleLB_Shop', 'ajax_store_search']);
add_action('wp_ajax_nopriv_rafflelb_store_search', ['RaffleLB_Shop', 'ajax_store_search']);
add_action('wp_footer', ['RaffleLB_Shop', 'store_search_ui'], 3);
add_action('save_post_product', ['RaffleLB_Shop', 'clear_store_search_lexicon_cache']);
add_action('created_term', ['RaffleLB_Shop', 'clear_store_search_lexicon_cache']);
add_action('edited_term', ['RaffleLB_Shop', 'clear_store_search_lexicon_cache']);
add_action('delete_term', ['RaffleLB_Shop', 'clear_store_search_lexicon_cache']);

/* Dynamic category-scoped Brands filter. The row itself is rendered by
 * shop_native_filters_ui() (see that method) — the filter UI Draw Engine
 * actually delegates to on wp_footer, and the only one confirmed live on
 * this theme's customised archive layout. These hooks apply/validate the
 * selection against the real WooCommerce query, and serve fresh per-
 * category brand lists to that JS for categories reached through
 * WoodMart's in-page AJAX shop (see ajax_category_brands()). */
add_action('pre_get_posts', ['RaffleLB_Shop', 'apply_brand_filter_to_catalog'], 9);
add_action('woocommerce_product_query', ['RaffleLB_Shop', 'apply_brand_filter_to_catalog'], 98);
add_action('template_redirect', ['RaffleLB_Shop', 'validate_brand_filter_request']);
add_action('wp_ajax_rafflelb_category_brands', ['RaffleLB_Shop', 'ajax_category_brands']);
add_action('wp_ajax_nopriv_rafflelb_category_brands', ['RaffleLB_Shop', 'ajax_category_brands']);
add_action('wp_footer', ['RaffleLB_Shop', 'shop_filter_hint_assets'], 4);
add_action('save_post_product', ['RaffleLB_Shop', 'bump_brand_cache_version']);
add_action('created_term', ['RaffleLB_Shop', 'bump_brand_cache_version']);
add_action('edited_term', ['RaffleLB_Shop', 'bump_brand_cache_version']);
add_action('delete_term', ['RaffleLB_Shop', 'bump_brand_cache_version']);
add_action('set_object_terms', ['RaffleLB_Shop', 'brand_object_terms_changed'], 10, 6);

/* Install first-paint product mounting before the browser parses product markup. */
add_action('wp_head', ['RaffleLB_Shop', 'product_layout_bootstrap'], 1);

/* Register the Shopping Mode capture listener before theme Shop AJAX scripts. */
add_action('wp_head', ['RaffleLB_Shop', 'shop_mode_navigation_guard'], 0);

/* Store Only is rendered by WooCommerce's normal content template-part path. */
add_filter('wc_get_template_part', ['RaffleLB_Shop', 'store_only_template_part'], 99, 3);
add_action('wp_head', ['RaffleLB_Shop', 'store_only_product_head'], 2);
add_action('wp_head', ['RaffleLB_Shop', 'raffle_price_visibility_css'], 999);
add_filter('woocommerce_product_single_add_to_cart_text', ['RaffleLB_Shop', 'store_only_add_to_cart_text'], 20);
add_filter('posts_clauses', ['RaffleLB_Shop', 'shop_effective_retail_sort'], 20, 2);
add_filter('posts_clauses', ['RaffleLB_Shop', 'shop_store_catalog_clauses'], 10, 2);
add_filter('posts_clauses', ['RaffleLB_Shop', 'shop_cancelled_catalog_clauses'], 11, 2);
add_filter('woocommerce_catalog_orderby', ['RaffleLB_Shop', 'shop_orderby_options'], 50);
add_filter('woocommerce_default_catalog_orderby_options', ['RaffleLB_Shop', 'shop_orderby_options'], 50);
add_filter('woocommerce_get_catalog_ordering_args', ['RaffleLB_Shop', 'shop_catalog_ordering_args'], 50, 3);
add_filter('posts_clauses', ['RaffleLB_Shop', 'shop_raffle_closest_sort'], 30, 2);

/* Drop the enqueued duplicate when wp_head already printed the stylesheet. */
add_action('wp_print_styles', function () {
    if (RaffleLB_Shop::early_style_printed()) wp_dequeue_style('rafflelb-single-product');
}, 0);

/*
 * Keep the layout stylesheet out of WP Rocket's CSS rewriting. Async delivery
 * or Remove Unused CSS lets the theme's product styling paint for a frame
 * before the raffle layout lands, which is the flash this guards against.
 */
foreach (['rocket_exclude_css', 'rocket_minify_excluded_external_css', 'rocket_exclude_defer_css'] as $rafflelb_css_filter) {
    add_filter($rafflelb_css_filter, function ($items) {
        if (!is_array($items)) return $items;
        $items[] = 'rafflelb-shop/assets/single-product.css';
        $items[] = 'rafflelb-shop/assets/mobile-store-card.css';
        return $items;
    });
}
add_filter('rocket_rucss_safelist', function ($safelist) {
    if (!is_array($safelist)) return $safelist;
    $safelist[] = 'rafflelb-shop/assets/single-product.css';
    $safelist[] = 'rafflelb-shop/assets/mobile-store-card.css';
    $safelist[] = '.rl-css-probe';
    return $safelist;
});
add_filter('rocket_rucss_inline_content_exclusions', function ($exclusions) {
    if (!is_array($exclusions)) return $exclusions;
    $exclusions[] = 'rafflelb-product-first-paint';
    $exclusions[] = 'rl-product-guard';
    $exclusions[] = 'rafflelb-shop-filter-hint-v0241';
    return $exclusions;
});

/* Keep the tiny inline bootstrap executable when WP Rocket delays/defer scripts. */
add_filter('rocket_delay_js_exclusions', function ($exclusions) {
    $exclusions[] = 'RaffleLBProductBootstrap';
    $exclusions[] = 'RaffleLBStoreSearch';
    $exclusions[] = 'RaffleLBFilterHint';
    return $exclusions;
});
add_filter('rocket_defer_js_exclusions', function ($exclusions) {
    $exclusions[] = 'RaffleLBProductBootstrap';
    $exclusions[] = 'RaffleLBStoreSearch';
    $exclusions[] = 'RaffleLBFilterHint';
    return $exclusions;
});
