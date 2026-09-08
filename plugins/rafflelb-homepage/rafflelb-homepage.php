<?php
/**
 * Plugin Name: RaffleLB Homepage
 * Description: Existing RaffleLB homepage presentation with reversible Draw Engine delegation.
 * Version: 0.1.2
 * Author: RaffleLB
 * Requires PHP: 7.4
 */
if (!defined('ABSPATH')) { exit; }
final class RaffleLB_Homepage {
    const VERSION = '0.1.2';
    public static function ready() {
        return class_exists('RaffleLB\\Core\\Contracts')
            && version_compare(\RaffleLB\Core\Contracts::VERSION, '0.1.0', '>=')
            && defined('RaffleLB_Draw_Engine::HOMEPAGE_BRIDGE_VERSION')
            && RaffleLB_Draw_Engine::HOMEPAGE_BRIDGE_VERSION === '1';
    }
    public static function homepage_hero_products_inject() {
        if (is_admin() || !function_exists('wc_get_product')) return;

        $ids = get_posts([
            'post_type'      => 'product',
            'post_status'    => 'publish',
            'posts_per_page' => 3,
            'fields'         => 'ids',
            'orderby'        => [
                'menu_order' => 'ASC',
                'date'       => 'DESC',
            ],
            'tax_query'      => [[
                'taxonomy' => 'product_tag',
                'field'    => 'slug',
                'terms'    => ['homepage-hero'],
            ]],
        ]);

        if (!$ids) return;

        $items = [];
        foreach ($ids as $pid) {
            $product = wc_get_product($pid);
            if (!$product || !$product->is_visible()) continue;

            $image_id = absint(get_post_meta($pid, \RaffleLB\Core\Contracts::META_HERO_IMAGE, true));
            if (!$image_id) continue;
            $image = wp_get_attachment_image_url($image_id, 'full');
            if (!$image) continue;

            $category = 'PREMIUM PICK';
            $terms = get_the_terms($pid, 'product_cat');
            if ($terms && !is_wp_error($terms)) {
                foreach ($terms as $term) {
                    if (strtolower($term->name) === 'uncategorized') continue;
                    $category = html_entity_decode($term->name, ENT_QUOTES, get_bloginfo('charset'));
                    break;
                }
            }

            $items[] = [
                'title' => $product->get_name(),
                'url'   => get_permalink($pid),
                'image' => $image,
                'label' => $category,
            ];
        }

        if (!$items) return;
        ?>
        <style id="rafflelb-homepage-hero-products">
        .rl-home-c-products-visual.rlhd-active .rl-product-shape{display:none!important}
        .rl-home-c-products-visual .rlhd-item{position:absolute;bottom:24px;z-index:4;display:flex!important;flex-direction:column;align-items:center;justify-content:flex-end;text-decoration:none!important;overflow:visible!important;transition:transform .22s ease,filter .22s ease}
        .rl-home-c-products-visual .rlhd-item:hover{transform:translateY(-5px);filter:brightness(1.06)}
        .rl-home-c-products-visual .rlhd-item-1{left:-1%;width:35%;height:82%}
        .rl-home-c-products-visual .rlhd-item-2{left:32.5%;width:35%;height:72%}
        .rl-home-c-products-visual .rlhd-item-3{right:-1%;width:35%;height:88%}
        .rl-home-c-products-visual .rlhd-imgwrap{display:flex;width:100%;height:calc(100% - 25px);align-items:flex-end;justify-content:center;overflow:visible}
        .rl-home-c-products-visual .rlhd-img{display:block!important;width:100%!important;height:100%!important;max-width:100%!important;max-height:100%!important;object-fit:contain!important;object-position:center bottom!important;padding:0!important;margin:0!important;filter:drop-shadow(0 20px 28px rgba(0,0,0,.58));mix-blend-mode:normal}
        .rl-home-c-products-visual .rlhd-label{display:block;max-width:100%;margin-top:5px;padding:3px 7px;border-radius:5px;background:rgba(5,8,5,.70);color:#fff!important;font-family:Inter,Arial,Helvetica,sans-serif!important;font-size:9px!important;line-height:1.15!important;font-weight:700!important;letter-spacing:.025em!important;text-align:center;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;backdrop-filter:blur(4px)}
        .rl-home-c-products-visual .rl-product-stage{z-index:1}
        .rl-home-c-products-visual .rl-product-glow{z-index:0}
        @media(max-width:767px){
            .rl-home-c-products-visual .rlhd-item{bottom:18px}
            .rl-home-c-products-visual .rlhd-item-1{left:7%;width:28%;height:68%}
            .rl-home-c-products-visual .rlhd-item-2{left:36%;width:28%;height:58%}
            .rl-home-c-products-visual .rlhd-item-3{right:7%;width:28%;height:72%}
            .rl-home-c-products-visual .rlhd-label{font-size:8px!important;padding:3px 5px}
        }
        </style>
        <script id="rafflelb-homepage-hero-products-js">
        (function(){
            var items = <?php echo wp_json_encode($items); ?>;
            function esc(value){
                return String(value == null ? '' : value)
                    .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
                    .replace(/"/g,'&quot;').replace(/'/g,'&#039;');
            }
            function mount(){
                var visual = document.querySelector('.rl-home-c-products-visual');
                if(!visual || !items || !items.length) return;

                visual.querySelectorAll('.rlhd-item').forEach(function(el){ el.remove(); });
                visual.classList.add('rlhd-active');

                items.slice(0,3).forEach(function(item,index){
                    var a = document.createElement('a');
                    a.className = 'rlhd-item rlhd-item-' + (index + 1);
                    a.href = item.url;
                    a.setAttribute('aria-label', item.title || item.label || 'View product');
                    a.innerHTML = '<span class="rlhd-imgwrap"><img class="rlhd-img" src="' + esc(item.image) + '" alt="' + esc(item.title) + '" loading="eager" decoding="async"></span><span class="rlhd-label">' + esc(item.label) + '</span>';

                    var stage = visual.querySelector('.rl-product-stage');
                    if(stage) visual.insertBefore(a, stage); else visual.appendChild(a);
                });
            }
            if(document.readyState === 'loading') document.addEventListener('DOMContentLoaded', mount); else mount();
        })();
        </script>
        <?php
    }

    public static function homepage_live_raffles_inject() {
        if (is_admin() || !function_exists('wc_get_product')) return;

        $posts = get_posts([
            'post_type'      => 'product',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => [
                'menu_order' => 'ASC',
                'date'       => 'DESC',
            ],
            'meta_query'     => [[
                'key'     => \RaffleLB\Core\Contracts::META_ENABLED,
                'value'   => 'yes',
                'compare' => '=',
            ]],
            'tax_query'      => [[
                'taxonomy' => 'product_tag',
                'field'    => 'slug',
                'terms'    => ['homepage-live-raffle'],
            ]],
        ]);

        $items = [];
        foreach ($posts as $post) {
            $pid = absint($post->ID);
            $product = wc_get_product($pid);
            if (!$product) continue;

            $stats = RaffleLB_Draw_Engine::homepage_stats($pid, true);
            if (!$stats || $stats['status'] !== 'live' || absint($stats['available']) < 1) continue;

            $entry_value = (float) wc_get_price_to_display($product);
            $items[] = [
                'title'     => $product->get_name(),
                'url'       => get_permalink($pid),
                'price'     => html_entity_decode(get_woocommerce_currency_symbol(), ENT_QUOTES | ENT_HTML5, 'UTF-8') . number_format($entry_value, 2, '.', ''),
                'claimed'   => absint($stats['claimed']),
                'total'     => absint($stats['total']),
                'available' => absint($stats['available']),
                'percent'   => max(0, min(100, absint($stats['percent']))),
                'image'     => get_the_post_thumbnail_url($pid, 'woocommerce_thumbnail') ?: '',
                'initial'   => strtoupper(substr(wp_strip_all_tags($product->get_name()), 0, 1)),
            ];

            if (count($items) >= 4) break;
        }

        $shop_url = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : home_url('/shop/');
        ?>
        <script id="rafflelb-home-live-raffles-js">
        document.addEventListener('DOMContentLoaded', function(){
            var list = document.querySelector('.rl-home-c-raffle-list');
            if (!list) return;

            var data = <?php echo wp_json_encode($items); ?>;
            var viewAll = document.querySelector('.rl-home-c-viewall');
            if (viewAll) viewAll.href = <?php echo wp_json_encode(add_query_arg('rl_view', 'raffle', $shop_url) . '#rl-shop-controls'); ?>;

            list.innerHTML = '';

            if (!data.length) {
                var empty = document.createElement('div');
                empty.className = 'rl-home-c-raffles-empty';
                empty.innerHTML = '<strong>No live raffles right now.</strong><span>Explore our shop and check back for new raffles.</span>';
                list.appendChild(empty);
                return;
            }

            data.forEach(function(item){
                var card = document.createElement('a');
                card.className = 'rl-home-c-raffle';
                card.href = item.url;

                var image = document.createElement('div');
                image.className = 'rl-home-c-raffle-image';
                if (item.image) {
                    var img = document.createElement('img');
                    img.src = item.image;
                    img.alt = item.title || 'Raffle product';
                    img.loading = 'lazy';
                    img.decoding = 'async';
                    image.appendChild(img);
                } else {
                    image.textContent = item.initial || 'R';
                }

                var info = document.createElement('div');
                info.className = 'rl-home-c-raffle-info';

                var h4 = document.createElement('h4');
                h4.textContent = item.title;

                var price = document.createElement('div');
                price.className = 'rl-home-c-raffle-price';
                var strong = document.createElement('strong');
                strong.textContent = item.price;
                price.appendChild(strong);
                price.appendChild(document.createTextNode(' / Entry'));

                var progressRow = document.createElement('div');
                progressRow.className = 'rl-home-c-progress-row';

                var progress = document.createElement('div');
                progress.className = 'rl-home-c-progress';
                var bar = document.createElement('span');
                bar.style.width = item.percent + '%';
                progress.appendChild(bar);

                var progressText = document.createElement('div');
                progressText.className = 'rl-home-c-progress-text';
                progressText.textContent = item.claimed + ' / ' + item.total;

                progressRow.appendChild(progress);
                progressRow.appendChild(progressText);

                info.appendChild(h4);
                info.appendChild(price);
                info.appendChild(progressRow);

                var left = document.createElement('div');
                left.className = 'rl-home-c-left';
                var leftStrong = document.createElement('strong');
                leftStrong.textContent = item.available;
                var leftSmall = document.createElement('small');
                leftSmall.textContent = list.closest('.rl-home-modern') ? 'entries left' : 'LEFT';
                left.appendChild(leftStrong);
                left.appendChild(leftSmall);

                card.appendChild(image);
                card.appendChild(info);
                card.appendChild(left);
                list.appendChild(card);
            });
        });
        </script>
        <style id="rafflelb-home-live-raffles-css">
        .rl-home-c-raffles-empty{
            min-height:230px;
            display:flex;
            flex-direction:column;
            align-items:center;
            justify-content:center;
            gap:7px;
            padding:28px;
            text-align:center;
            color:#c7cec3;
            font-family:Inter,Arial,Helvetica,sans-serif;
        }
        .rl-home-c-raffles-empty strong{color:#fff;font-size:14px;}
        .rl-home-c-raffle-list{padding-top:2px!important;padding-bottom:5px!important;}
        .rl-home-c-raffle{padding:11px 2px!important;grid-template-columns:58px minmax(0,1fr) 58px!important;gap:11px!important;}
        .rl-home-c-raffle-image{
            width:58px!important;
            height:58px!important;
            font-size:21px!important;
            overflow:hidden!important;
            padding:4px!important;
        }
        .rl-home-c-raffle-image img{
            display:block!important;
            width:100%!important;
            height:100%!important;
            object-fit:contain!important;
            object-position:center!important;
            border-radius:7px!important;
        }
        .rl-home-c-left{height:54px!important;}
        .rl-home-c-raffles-empty span{font-size:11px;color:#9da798;}
        </style>
        <?php
    }

    public static function featured_products_shortcode($atts = []) {
        if (!function_exists('wc_get_product')) return '';

        $atts = shortcode_atts([
            'limit' => 6,
            'tag'   => '',
        ], $atts, 'rafflelb_featured_products');

        $limit = max(1, min(12, absint($atts['limit'])));
        $tag   = sanitize_title($atts['tag']);

        $query_args = [
            'post_type'      => 'product',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'orderby'        => 'date',
            'order'          => 'DESC',
            'meta_query'     => [
                'relation' => 'AND',
                [
                    'key'     => \RaffleLB\Core\Contracts::META_ENABLED,
                    'value'   => 'yes',
                    'compare' => '=',
                ],
                [
                    'key'     => \RaffleLB\Core\Contracts::META_BUY_NOW_ENABLED,
                    'value'   => 'yes',
                    'compare' => '=',
                ],
                [
                    'key'     => \RaffleLB\Core\Contracts::META_BUY_NOW_PRICE,
                    'value'   => 0,
                    'type'    => 'NUMERIC',
                    'compare' => '>',
                ],
            ],
        ];

        // Optional WooCommerce product-tag filter. Example:
        // [rafflelb_featured_products tag="homepage-featured"]
        if ($tag !== '') {
            $query_args['tax_query'] = [
                [
                    'taxonomy' => 'product_tag',
                    'field'    => 'slug',
                    'terms'    => [$tag],
                ],
            ];
        }

        $ids = get_posts($query_args);

        $products = [];
        foreach ($ids as $pid) {
            $product = wc_get_product($pid);
            if (!$product || !$product->is_visible()) continue;

            // Featured Products represents products whose raffle is currently open.
            // Keep the product tag as an editorial flag, but automatically hide a
            // product once its raffle fills, is closed early, or has a winner.
            $draw_pid = RaffleLB_Draw_Engine::homepage_draw_id($product);
            if (!$draw_pid) continue;

            $stats = RaffleLB_Draw_Engine::homepage_stats($draw_pid, true);
            if (!$stats || (string) $stats['status'] !== 'live' || absint($stats['available']) < 1) continue;

            // A recorded result is definitive evidence that this raffle cycle is over.
            if (RaffleLB_Draw_Engine::homepage_get_draw_result($draw_pid)) continue;

            $products[] = $product;
            if (count($products) >= $limit) break;
        }

        ob_start();
        ?>
        <section class="rlfp318 rlfp3113">
            <div class="rlfp318-head">
                <div>
                    <div class="rlfp318-kicker"><i></i> SHOP RAFFLELB</div>
                    <h2>FEATURED <span>PRODUCTS</span></h2>
                    <p>Shop products directly at the displayed retail price. Selected products also offer a separate raffle entry option.</p>
                </div>
                <a class="rlfp318-view" href="<?php echo esc_url(wc_get_page_permalink('shop')); ?>"><span>VIEW ALL PRODUCTS</span><span class="rlfp318-view-arrow" aria-hidden="true">→</span></a>
            </div>

            <?php if (!$products): ?>
                <div class="rlfp318-empty">No direct-purchase products are available right now.</div>
            <?php else: ?>
                <div class="rlfp318-grid">
                    <?php foreach ($products as $product):
                        $pid          = $product->get_id();
                        $url          = get_permalink($pid);
                        $title        = $product->get_name();
                        $image_id     = $product->get_image_id();
                        $buy_price    = RaffleLB_Draw_Engine::homepage_buy_now_price($product);
                        $entry_price  = (float) $product->get_price();
                        $stats        = RaffleLB_Draw_Engine::homepage_stats(RaffleLB_Draw_Engine::homepage_draw_id($product), true);
                        $available    = $stats ? absint($stats['available']) : 0;
                        $total        = $stats ? absint($stats['total']) : 0;
                        $status       = $stats ? (string) $stats['status'] : '';

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
                    ?>
                    <article class="rlfp318-card">
                        <a class="rlfp318-media" href="<?php echo esc_url($url); ?>" aria-label="<?php echo esc_attr($title); ?>">
                            <?php if ($category): ?><span class="rlfp318-cat"><?php echo esc_html($category); ?></span><?php endif; ?>
                            <div class="rlfp318-glow"></div>
                            <?php
                            if ($image_id) {
                                echo wp_get_attachment_image($image_id, 'woocommerce_thumbnail', false, [
                                    'class'   => 'rlfp318-img',
                                    'loading' => 'lazy',
                                    'alt'     => $title,
                                ]);
                            } else {
                                echo '<div class="rlfp318-noimg">PRODUCT</div>';
                            }
                            ?>
                        </a>

                        <div class="rlfp318-body">
                            <h3><a href="<?php echo esc_url($url); ?>"><?php echo esc_html($title); ?></a></h3>
                            <div class="rlfp318-price"><span class="rlfp318-currency"><?php echo esc_html(get_woocommerce_currency_symbol()); ?></span><span class="rlfp318-amount"><?php echo esc_html(number_format((float) $buy_price, 2, '.', ',')); ?></span></div>

                            <a class="rlfp318-buy" href="<?php echo esc_url($url); ?>">
                                <span>BUY NOW</span><span class="rlfp318-bag" aria-hidden="true"><svg viewBox="0 0 24 24" focusable="false"><path d="M6.5 8V6.5a5.5 5.5 0 0 1 11 0V8M4.5 8h15l1 13h-17l1-13Z"/></svg></span>
                            </a>

                            <div class="rlfp318-or" aria-hidden="true"><span>OR</span></div>

                            <a class="rlfp318-enter" href="<?php echo esc_url($url); ?>">
                                <span>Enter Raffle from</span>
                                <strong><span class="rlfp318-currency"><?php echo esc_html(get_woocommerce_currency_symbol()); ?></span><span class="rlfp318-amount"><?php echo esc_html(number_format((float) $entry_price, 2, '.', ',')); ?></span></strong>
                            </a>

                            <?php if ($status === 'live' && $total > 0): ?>
                                <div class="rlfp318-availability"><strong><?php echo esc_html($available); ?></strong> of <?php echo esc_html($total); ?> entries available</div>
                            <?php elseif ($status === 'ready_to_draw'): ?>
                                <div class="rlfp318-availability">Raffle filled — awaiting draw</div>
                            <?php endif; ?>
                        </div>
                    </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div class="rlfp318-note"><div class="rlfp318-note-lead"><svg class="rlfp318-shield" viewBox="0 0 24 28" aria-hidden="true"><path d="M12 2 21 6v8c0 6-9 11-9 11S3 20 3 14V6Z"/><path d="m8 13 3 3 5-6"/></svg><strong>BUYING DIRECTLY IS A STANDARD PRODUCT PURCHASE.</strong></div><span>Raffle participation is a separate option and does not increase the price or alter the product.</span><span class="rlfp318-note-tag"><i aria-hidden="true">/////</i> SHOP · PLAY · WIN BIGGER</span></div>
        </section>

        <style>

.rlfp318{--lime:#baff00;--bg:#090d09;--line:#2b342b;width:100%;padding:48px clamp(16px,2.2vw,42px);background:var(--bg);color:#f5f7f2;font-family:Inter,"Segoe UI",Arial,sans-serif!important;box-sizing:border-box}
.rlfp318 *{box-sizing:border-box;text-shadow:none!important;font-family:inherit!important}
.rlfp318 a{text-decoration:none!important}.rlfp318 a:focus-visible{outline:3px solid var(--lime);outline-offset:4px}
.rlfp318-head{display:flex;justify-content:space-between;align-items:flex-end;gap:24px;margin:0 auto 28px;max-width:1840px}
.rlfp318-kicker{display:flex;align-items:center;gap:8px;color:var(--lime);font-size:11px;font-weight:700;letter-spacing:.14em;margin-bottom:12px}.rlfp318-kicker i{width:6px;height:6px;background:var(--lime);border-radius:50%}
.rlfp318-head h2{margin:0!important;font-size:clamp(28px,2.3vw,42px)!important;line-height:1.1!important;font-weight:800!important;letter-spacing:-.035em!important;color:#f5f7f2!important}.rlfp318-head h2 span{color:var(--lime)}
.rlfp318-head p{max-width:740px;margin:12px 0 0!important;color:#b8c1b5!important;font-size:14px!important;line-height:1.6!important;font-weight:400!important}
.rlfp318-view{display:inline-flex;align-items:center;justify-content:center;min-height:44px;gap:18px;min-width:212px;padding:12px 22px;border:1px solid #91b500;border-radius:10px;background:#080e0a;box-shadow:0 0 24px #baff0017,inset 0 1px 0 #baff0014;color:#f5f7f2!important;font-size:11px;font-weight:700;letter-spacing:.05em;white-space:nowrap}
.rlfp318-grid{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:16px;max-width:1840px;margin:auto}
.rlfp318-card{display:flex;flex-direction:column;min-width:0;border:1px solid var(--line);border-radius:14px;overflow:hidden;background:#101610;transition:border-color .2s,transform .2s}.rlfp318-card:hover{border-color:#61704d;transform:translateY(-3px)}
.rlfp318-media{display:flex;align-items:center;justify-content:center;position:relative;aspect-ratio:1.15;isolation:isolate;background:#000;border-bottom:1px solid var(--line);padding:38px 16px 16px;overflow:hidden}
.rlfp318-img{display:block!important;width:100%!important;height:100%!important;min-height:0;mix-blend-mode:normal!important;filter:none!important;opacity:1!important;object-fit:contain!important;transform:none!important;padding:0!important}.rlfp318-glow{display:none}.rlfp318-cat{position:absolute;top:12px;left:12px;right:12px;color:#bbc5b6!important;font-size:11px;line-height:1.4;font-weight:500}.rlfp318-noimg{color:#b8c1b5;font-size:14px}
.rlfp318-body{display:flex;flex-direction:column;flex:1;padding:18px}
.rlfp318-body h3{margin:0 0 18px!important;min-height:60px;font-size:14px!important;line-height:1.45!important;letter-spacing:0!important;font-weight:600!important;overflow-wrap:anywhere}.rlfp318-body h3 a{color:#f5f7f2!important}
.rlfp318-price{display:flex;align-items:baseline;margin-top:auto;margin-bottom:18px;color:#f5f7f2;font-size:26px;font-weight:700;line-height:1.1;letter-spacing:-.04em;font-variant-numeric:tabular-nums}.rlfp318-currency,.rlfp318-amount{font:inherit!important;color:inherit!important}
.rlfp318-buy,.rlfp318-enter{display:flex;align-items:center;justify-content:center;gap:8px;min-height:44px;width:100%;padding:10px 8px;border-radius:8px;font-size:12px;font-weight:700;line-height:1.4}
.rlfp318-buy{background:var(--lime);color:#0a1002!important}.rlfp318-buy:hover{background:#ccff47}.rlfp318-bag,.rlfp318-bag svg{display:block;width:17px;height:17px}.rlfp318-bag svg{fill:none;stroke:currentColor;stroke-width:1.7;stroke-linecap:round;stroke-linejoin:round}
.rlfp318-or{display:flex;align-items:center;gap:10px;margin:12px 0;color:#acb6a7;font-size:10px;font-weight:600;letter-spacing:.1em}.rlfp318-or:before,.rlfp318-or:after{content:"";height:1px;flex:1;background:#344031}
.rlfp318-enter{gap:4px;border:1px solid #536733;background:#0c120b;color:#dbe3d7!important;font-weight:400;flex-wrap:wrap}.rlfp318-enter strong{color:var(--lime);font-size:inherit;white-space:nowrap}.rlfp318-enter:hover,.rlfp318-view:hover{border-color:var(--lime)}
.rlfp318-availability{margin-top:12px;color:#b8c1b5;font-size:12px;line-height:1.5}.rlfp318-availability strong{color:var(--lime);font-weight:600}
.rlfp318-note{display:flex;align-items:center;gap:18px;max-width:1840px;margin:24px auto 0;padding:16px 18px;border:1px solid #829d00;border-radius:9px;background:linear-gradient(100deg,#111900,#070b07 60%);box-shadow:inset 0 1px 0 #c0ef0033;color:#bcc4b7;font-size:12px;line-height:1.6}
.rlfp318-note-lead{display:flex;align-items:center;gap:10px;flex-shrink:0}.rlfp318-note strong{color:#d3ff1f;font-size:11px;font-weight:700}.rlfp318-shield{width:20px;height:24px;flex-shrink:0;fill:none;stroke:#d3ff1f;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round}.rlfp318-note-tag{display:flex;align-items:center;gap:20px;margin-left:auto;white-space:nowrap;font-size:10px;letter-spacing:.2em}.rlfp318-note-tag i{color:#d3ff1f;font-size:21px;font-weight:800;letter-spacing:1px}
.rlfp318-empty{padding:32px;border:1px solid var(--line);border-radius:12px;color:#b8c1b5;text-align:center}
@media(max-width:1500px){.rlfp318-grid{grid-template-columns:repeat(3,minmax(0,1fr))}.rlfp318-media{aspect-ratio:1.55}.rlfp318-note{flex-wrap:wrap;gap:10px 18px}.rlfp318-note-tag{margin-left:auto}}
@media(max-width:850px){.rlfp318-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.rlfp318-head{align-items:flex-start;flex-direction:column;gap:16px}.rlfp318-media{aspect-ratio:1.15}.rlfp318-note-lead{flex-shrink:1}.rlfp318-note-tag{margin-left:0}}
@media(max-width:480px){.rlfp318{padding:28px 12px}.rlfp318-grid{grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}.rlfp318-card{border-radius:11px}.rlfp318-media{aspect-ratio:1;padding:36px 8px 10px}.rlfp318-cat{top:10px;left:10px;right:10px;font-size:10px;line-height:1.3}.rlfp318-body{padding:12px 10px}.rlfp318-body h3{min-height:72px;font-size:13px!important;line-height:1.4!important;margin-bottom:12px!important}.rlfp318-price{font-size:22px;margin-bottom:14px}.rlfp318-buy,.rlfp318-enter{min-height:44px;font-size:11px;padding:9px 4px;gap:4px}.rlfp318-enter{flex-direction:column;gap:1px}.rlfp318-availability{font-size:11px;margin-top:10px}.rlfp318-or{margin:10px 0}.rlfp318-note{padding:14px;gap:12px}.rlfp318-note strong{font-size:11px}.rlfp318-note-tag{font-size:9px;letter-spacing:.12em}.rlfp318-head p{font-size:14px!important}}
@media(prefers-reduced-motion:reduce){.rlfp318-card{transition:none}.rlfp318-card:hover{transform:none}}

.rlfp318-view-arrow{color:var(--lime);font-size:19px;line-height:1;font-weight:400}.rlfp318-view:hover{box-shadow:0 0 28px #baff002b;background:#111b0b}


/* A continuous outer canvas for hero, products and categories. */
.rl-home-modern,.rlfp318,.rlsc316{background:#090d09!important;border-top:0!important;border-bottom:0!important;box-shadow:none!important;margin-top:0!important;margin-bottom:0!important}
.rlfp318::before,.rlfp318::after{content:none!important;display:none!important}
/* Clear only the widgets that own these sections, not unrelated page sections. */
body.home :is(.elementor-widget-html,.elementor-widget-shortcode,.wp-block-shortcode):has(:is(.rl-home-modern,.rlfp318,.rlsc316)),
body.home :is(.elementor-widget-html,.elementor-widget-shortcode):has(:is(.rl-home-modern,.rlfp318,.rlsc316)) > .elementor-widget-container{
background:#090d09!important;margin-block:0!important;padding-block:0!important;border-block:0!important;box-shadow:none!important}
body.home :is(.e-con,.elementor-section,.elementor-column):has(:is(.rl-home-modern,.rlfp318,.rlsc316)){background-color:#090d09!important;background-image:none!important}
        </style>    <?php
        return ob_get_clean();
    }

    public static function shop_categories_shortcode($atts = []) {
        if (!taxonomy_exists('product_cat')) return '';

        $atts = shortcode_atts([
            'hide_empty' => 'no',
            'exclude'    => 'uncategorized',
            'include'    => '',
            'limit'      => 6,
        ], $atts, 'rafflelb_shop_categories');

        $display_limit = max(1, min(12, absint($atts['limit'])));
        $exclude_slugs = array_filter(array_map('sanitize_title', array_map('trim', explode(',', (string) $atts['exclude']))));
        $include_slugs = array_values(array_filter(array_map('sanitize_title', array_map('trim', explode(',', (string) $atts['include'])))));
        $all_terms = get_terms([
            'taxonomy'   => 'product_cat',
            'hide_empty' => strtolower((string) $atts['hide_empty']) === 'yes',
            'orderby'    => 'menu_order',
            'order'      => 'ASC',
            'parent'     => 0,
        ]);
        if (is_wp_error($all_terms)) return '';
        $all_terms = array_values(array_filter($all_terms, function($term) use ($exclude_slugs) {
            return !in_array($term->slug, $exclude_slugs, true);
        }));

        $terms = $all_terms;
        if (!empty($include_slugs)) {
            $selected = [];
            $extras = [];
            foreach ($all_terms as $term) {
                if (in_array($term->slug, $include_slugs, true)) $selected[] = $term;
                else $extras[] = $term;
            }
            $order_map = array_flip($include_slugs);
            usort($selected, function($a, $b) use ($order_map) {
                return ($order_map[$a->slug] ?? PHP_INT_MAX) <=> ($order_map[$b->slug] ?? PHP_INT_MAX);
            });
            $terms = array_merge($selected, $extras);
        }
        // Show a controlled number of homepage categories. Included slugs are
        // always prioritized in the order supplied; if fewer than the limit
        // are supplied, fill the remaining homepage slots from other categories.
        $selected_count = min($display_limit, count($terms));

        ob_start(); ?>
        <section id="rafflelb-shop-categories" class="rlsc316">
            <div class="rlsc316-head">
                <div>
                    <div class="rlsc316-kicker"><i></i> EXPLORE RAFFLELB</div>
                    <h2>SHOP BY <span>CATEGORY</span></h2>
                    <p>Browse our product categories and find what you're looking for.</p>
                </div>
                <?php if (!empty($include_slugs)): ?><button type="button" class="rlsc316-view rlsc316-toggle" aria-expanded="false" data-has-extras="<?php echo (count($terms) > $selected_count) ? '1' : '0'; ?>">VIEW ALL CATEGORIES →</button><?php endif; ?>
            </div>
            <?php if (!$terms): ?>
                <div class="rlsc316-empty">No product categories are available right now.</div>
            <?php else: ?>
                <div class="rlsc316-grid">
                <?php foreach ($terms as $index => $term):
                    $url = get_term_link($term);
                    if (is_wp_error($url)) continue;
                    $thumb_id = absint(get_term_meta($term->term_id, 'thumbnail_id', true));
                    $name = html_entity_decode($term->name, ENT_QUOTES, get_bloginfo('charset'));
                ?>
                    <a class="rlsc316-card<?php echo ($index >= $selected_count) ? ' rlsc316-extra' : ''; ?>" href="<?php echo esc_url($url); ?>">
                        <div class="rlsc316-media">
                            <div class="rlsc316-glow"></div>
                            <?php if ($thumb_id): ?>
                                <?php echo wp_get_attachment_image($thumb_id, 'large', false, ['class'=>'rlsc316-img','loading'=>'lazy','alt'=>$name]); ?>
                            <?php else: ?>
                                <div class="rlsc316-placeholder"><span><?php echo esc_html(mb_substr($name,0,1)); ?></span></div>
                            <?php endif; ?>
                            <div class="rlsc316-shade"></div>
                        </div>
                        <div class="rlsc316-content">
                            <h3><?php echo esc_html($name); ?></h3>
                            <div class="rlsc316-shop"><strong>SHOP NOW</strong><span><?php echo esc_html($term->count); ?> products</span></div>
                        </div>
                        <span class="rlsc316-arrow">→</span>
                    </a>
                <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <div class="rlsc316-signoff" aria-hidden="true">PREMIUM BRANDS &nbsp;•&nbsp; BIGGER POSSIBILITIES</div>
        </section>
        <style>
        .rlsc316{--lime:#baff00;--line:#293228;position:relative;width:100vw!important;max-width:100vw!important;margin-left:calc(50% - 50vw)!important;margin-right:calc(50% - 50vw)!important;padding:42px 28px 48px;background:#070a07;color:#fff;box-sizing:border-box;overflow:hidden}.rlsc316 *{box-sizing:border-box}.rlsc316 a{text-decoration:none!important}.rlsc316-head{display:flex;align-items:flex-end;justify-content:space-between;gap:30px;margin-bottom:20px}.rlsc316-kicker{display:flex;align-items:center;gap:8px;margin-bottom:8px;color:var(--lime);font-family:Inter,"Segoe UI",Arial,sans-serif!important;font-size:11px!important;font-weight:800!important;letter-spacing:.08em!important}.rlsc316-kicker i{width:7px;height:7px;border-radius:50%;background:var(--lime);box-shadow:0 0 12px rgba(186,255,0,.55)}.rlsc316-head h2{margin:0!important;color:#fff!important;font-size:30px!important;line-height:1.1!important;font-weight:900!important;letter-spacing:-.025em!important}.rlsc316-head h2 span{color:var(--lime)!important}.rlsc316-head p{margin:8px 0 0!important;font-family:Inter,"Segoe UI",Arial,sans-serif!important;color:#c2c9be!important;font-size:14px!important;line-height:1.6!important}.rlsc316-view{display:inline-flex!important;height:42px;align-items:center;justify-content:center;padding:0 20px;border:1px solid #394238;border-radius:8px;background:transparent!important;color:#fff!important;font-family:Inter,"Segoe UI",Arial,sans-serif!important;font-size:10px!important;font-weight:800!important;white-space:nowrap;cursor:pointer}.rlsc316-view:hover{border-color:var(--lime);color:var(--lime)!important}.rlsc316-extra{display:none!important}.rlsc316-show-all .rlsc316-extra{display:block!important}.rlsc316-grid{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:12px}.rlsc316-card{position:relative;display:block;height:245px;overflow:hidden;border:1px solid var(--line);border-radius:15px;background:#0d110d;transition:.25s ease}.rlsc316-card:hover{transform:translateY(-4px);border-color:#465242;box-shadow:0 20px 45px rgba(0,0,0,.3)}.rlsc316-media{position:absolute;inset:0;overflow:hidden;background:#0d120d}.rlsc316-glow{position:absolute;z-index:1;width:70%;height:65%;left:50%;top:12%;transform:translateX(-50%);border-radius:50%;background:radial-gradient(circle,rgba(186,255,0,.11),transparent 70%)}.rlsc316-img{position:absolute!important;z-index:2;inset:0;width:100%!important;height:100%!important;max-width:none!important;object-fit:cover!important;transition:transform .3s ease}.rlsc316-card:hover .rlsc316-img{transform:scale(1.04)}.rlsc316-placeholder{position:absolute;z-index:2;inset:0;display:flex;align-items:center;justify-content:center}.rlsc316-placeholder span{display:flex;width:92px;height:92px;align-items:center;justify-content:center;border:1px solid #354033;border-radius:24px;background:#101610;color:var(--lime);font-size:42px;font-weight:900}.rlsc316-shade{position:absolute;z-index:3;inset:0;background:linear-gradient(to bottom,rgba(5,7,5,.02) 25%,rgba(5,7,5,.2) 55%,rgba(5,7,5,.97) 100%)}.rlsc316-content{position:absolute;z-index:5;left:18px;right:52px;bottom:16px}.rlsc316-content h3{margin:0 0 5px!important;color:#fff!important;font-family:Inter,"Segoe UI",Arial,sans-serif!important;font-size:17px!important;line-height:1.2!important;font-weight:800!important}.rlsc316-shop{display:flex;align-items:center;gap:6px;font-family:Inter,"Segoe UI",Arial,sans-serif!important;color:#b8c1b4!important;font-size:10px!important}.rlsc316-shop strong{color:var(--lime)!important;font-weight:800!important}.rlsc316-arrow{position:absolute;z-index:6;right:16px;bottom:16px;display:flex;width:32px;height:32px;align-items:center;justify-content:center;border:1px solid rgba(186,255,0,.3);border-radius:50%;color:var(--lime)!important;font-size:17px;transition:.2s ease}.rlsc316-card:hover .rlsc316-arrow{background:var(--lime);color:#050705!important}.rlsc316-empty{padding:28px;border:1px solid var(--line);border-radius:10px;color:#aab3a6;text-align:center}
        @media(max-width:1050px){.rlsc316-grid{grid-template-columns:repeat(3,minmax(0,1fr))}.rlsc316-card{height:235px}}
        @media(max-width:767px){.rlsc316{padding:36px 12px 42px}.rlsc316-head{display:block}.rlsc316-head h2{font-size:27px!important}.rlsc316-view{margin-top:18px}.rlsc316-grid{grid-template-columns:repeat(2,1fr);gap:10px}.rlsc316-card{height:220px}.rlsc316-content{left:16px;right:16px;bottom:16px}.rlsc316-content h3{font-size:16px!important}.rlsc316-arrow{display:none}}
        @media(max-width:480px){.rlsc316{padding:32px 10px 38px}.rlsc316-grid{grid-template-columns:repeat(2,minmax(0,1fr));gap:8px}.rlsc316-card{height:178px;border-radius:12px}.rlsc316-content{left:11px;right:11px;bottom:11px}.rlsc316-content h3{margin-bottom:4px!important;font-size:13px!important;line-height:1.15!important}.rlsc316-shop{gap:4px;font-size:8.5px!important;line-height:1.2!important;flex-wrap:wrap}.rlsc316-shop strong{font-size:8.5px!important}.rlsc316-placeholder span{width:62px;height:62px;border-radius:18px;font-size:30px}.rlsc316-shade{background:linear-gradient(to bottom,rgba(5,7,5,.01) 20%,rgba(5,7,5,.14) 50%,rgba(5,7,5,.96) 100%)}}
/* Category gallery: reference proportions and a dedicated readable caption area. */
.rlsc316{padding:64px 36px 36px;background-color:#090d09!important;isolation:isolate}
.rlsc316::before{content:"";position:absolute;z-index:-1;pointer-events:none;width:1000px;height:440px;right:-230px;top:-365px;border:1px solid #baff002b;border-radius:50%;transform:rotate(-18deg);box-shadow:0 30px 80px #baff0007}
.rlsc316-head{margin-bottom:22px;gap:24px}
.rlsc316-kicker{font-size:12px!important;letter-spacing:.19em!important;margin-bottom:14px;gap:10px}
.rlsc316-kicker i{width:9px;height:9px}
.rlsc316-head h2{font-family:Arial,Helvetica,sans-serif!important;font-size:clamp(32px,2.35vw,48px)!important;line-height:1.08!important;font-weight:900!important}
.rlsc316-head p{font-size:16px!important;line-height:1.5!important;margin-top:8px!important}
.rlsc316 .rlsc316-view,.rlfp318 .rlfp318-view{border:1px solid #baff00!important;border-radius:10px;background:#080c08!important;color:#f7f8f5!important;min-height:46px;padding:0 24px;font-size:12px!important;box-shadow:inset 0 1px 0 #baff0017}
.rlsc316 .rlsc316-view:hover,.rlfp318 .rlfp318-view:hover{background:#18210a!important;color:#baff00!important}
.rlsc316-grid{grid-template-columns:repeat(6,minmax(0,1fr));gap:16px}
.rlsc316 .rlsc316-card{height:auto;min-width:0;border:1px solid #303a2a;border-radius:15px;background:#060906;box-shadow:inset 0 1px 0 #ffffff07;transition:border-color .2s,transform .2s}
.rlsc316 .rlsc316-media{position:relative;inset:auto;width:100%;aspect-ratio:1.08;background:#000}
.rlsc316 .rlsc316-img{object-fit:contain!important;object-position:center bottom;transform:none;filter:none}
.rlsc316 .rlsc316-glow{display:none}
.rlsc316 .rlsc316-shade{background:linear-gradient(180deg,transparent 84%,#060906 100%)}
.rlsc316 .rlsc316-content{position:relative;inset:auto;min-height:94px;padding:10px 64px 22px 20px}
.rlsc316 .rlsc316-content h3{font-size:18px!important;line-height:1.25!important;margin:0 0 10px!important;font-weight:750!important}
.rlsc316 .rlsc316-shop{font-size:11px!important;gap:10px;flex-wrap:wrap;line-height:1.4}
.rlsc316 .rlsc316-shop span{border-left:1px solid #718330;padding-left:10px;color:#a6ada1}
.rlsc316 .rlsc316-arrow{display:flex;width:44px;height:44px;right:18px;bottom:25px;border:1px solid #baff00;font-size:23px;box-shadow:0 0 20px #baff0009}
.rlsc316 .rlsc316-card:hover{border-color:#baff0099;transform:translateY(-3px)}
.rlsc316 .rlsc316-card:hover .rlsc316-img{transform:scale(1.025)}
.rlsc316 a:focus-visible,.rlsc316 button:focus-visible{outline:2px solid #baff00;outline-offset:4px}
.rlsc316-signoff{display:flex;align-items:center;gap:28px;margin:42px auto 0;max-width:1600px;color:#78816f;font:700 9px/1.5 Arial,sans-serif;letter-spacing:.35em;text-align:center}
.rlsc316-signoff::before,.rlsc316-signoff::after{content:"";height:1px;flex:1;background:linear-gradient(90deg,transparent,#90b719)}
.rlsc316-signoff::after{transform:rotate(180deg)}
@media(max-width:1500px) and (min-width:1251px){.rlsc316 .rlsc316-content{padding:10px 45px 18px 12px;min-height:104px}.rlsc316 .rlsc316-content h3{font-size:15px!important}.rlsc316 .rlsc316-arrow{width:30px;height:30px;right:10px;bottom:24px;font-size:18px}.rlsc316 .rlsc316-shop{font-size:10px!important;gap:6px}}
@media(max-width:1250px){.rlsc316-grid{grid-template-columns:repeat(3,minmax(0,1fr))}}
@media(max-width:640px){.rlsc316{padding:36px 14px 28px}.rlsc316-head{display:block}.rlsc316-head h2{font-size:30px!important}.rlsc316-head p{font-size:14px!important}.rlsc316-kicker{font-size:10px!important}.rlsc316 .rlsc316-view{margin-top:18px;min-height:44px;font-size:11px!important;padding:0 18px}.rlsc316-grid{grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}.rlsc316 .rlsc316-card{border-radius:12px}.rlsc316 .rlsc316-content{padding:8px 10px 42px;min-height:110px}.rlsc316 .rlsc316-content h3{font-size:14px!important;line-height:1.3!important;margin-bottom:7px!important}.rlsc316 .rlsc316-shop{font-size:10px!important;gap:6px}.rlsc316 .rlsc316-shop strong{font-size:10px!important}.rlsc316 .rlsc316-shop span{padding-left:6px}.rlsc316 .rlsc316-arrow{width:28px;height:28px;right:10px;bottom:9px;font-size:17px}.rlsc316-signoff{gap:10px;margin-top:28px;font-size:7px;letter-spacing:.18em}}
@media(prefers-reduced-motion:reduce){.rlsc316 .rlsc316-card,.rlsc316 .rlsc316-img,.rlsc316 .rlsc316-arrow{transition:none!important;transform:none!important}}

        </style>
        <script>
        document.addEventListener('click', function(e){
            var btn=e.target.closest('.rlsc316-toggle');
            if(!btn) return;
            var section=btn.closest('.rlsc316');
            if(!section) return;
            if(btn.getAttribute('data-has-extras') !== '1'){
                var original='VIEW ALL CATEGORIES →';
                btn.textContent='ALL CATEGORIES SHOWN ✓';
                window.setTimeout(function(){ btn.textContent=original; }, 1400);
                return;
            }
            var open=section.classList.toggle('rlsc316-show-all');
            btn.setAttribute('aria-expanded', open ? 'true' : 'false');
            btn.textContent=open ? 'SHOW LESS ↑' : 'VIEW ALL CATEGORIES →';
        });
        </script>
        <?php return ob_get_clean();
    }

    public static function live_raffles_shortcode() {
        $products = get_posts([
            'post_type'      => 'product',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => 'date',
            'order'          => 'DESC',
            'meta_query'     => [[
                'key'     => \RaffleLB\Core\Contracts::META_ENABLED,
                'value'   => 'yes',
                'compare' => '=',
            ]],
        ]);

        $live = [];
        foreach ($products as $post) {
            $pid = absint($post->ID);
            $product = wc_get_product($pid);
            if (!$product) continue;

            $stats = RaffleLB_Draw_Engine::homepage_stats($pid, true);
            if (!$stats || $stats['status'] !== 'live' || $stats['available'] < 1) continue;

            $live[] = [$product, $stats];
        }

        $count = count($live);
        $cols  = max(1, min(4, $count));

        ob_start();
        ?>
        <section class="rlp270-stage">
            <div class="rlp270-inner">

                <div class="rlp270-kicker">
                    <span class="rlp270-bolt">⚡</span>
                    <span>LIVE NOW</span>
                </div>

                <?php if (!$live): ?>

                    <div class="rlp270-empty">
                        <strong>No live raffles right now.</strong>
                        <span>New raffles will appear here automatically as soon as they open.</span>
                    </div>

                <?php else: ?>

                    <div class="rlp270-grid" style="--rlp270-cols:<?php echo esc_attr($cols); ?>">

                        <?php foreach ($live as $row):
                            /** @var WC_Product $product */
                            [$product, $stats] = $row;

                            $pid       = $product->get_id();
                            $title     = $product->get_name();
                            $url       = get_permalink($pid);
                            $image     = wp_get_attachment_image_url($product->get_image_id(), 'large');
                            $price     = $product->get_price_html();
                            $available = absint($stats['available']);
                            $total     = absint($stats['total']);
                            $claimed   = absint($stats['claimed']);
                            $percent   = absint($stats['percent']);

                            $terms = get_the_terms($pid, 'product_cat');
                            $category = '';
                            if ($terms && !is_wp_error($terms)) {
                                foreach ($terms as $term) {
                                    if (strtolower($term->name) !== 'uncategorized') {
                                        $category = $term->name;
                                        break;
                                    }
                                }
                            }
                        ?>

                        <article class="rlp270-card rlp275-card">

                            <a class="rlp270-media rlp275-media" href="<?php echo esc_url($url); ?>">
                                <?php if ($image): ?>
                                    <img src="<?php echo esc_url($image); ?>" alt="<?php echo esc_attr($title); ?>">
                                <?php else: ?>
                                    <div class="rlp270-placeholder">R</div>
                                <?php endif; ?>
                            </a>

                            <div class="rlp270-body rlp275-body">

                                <h3 class="rlp275-title">
                                    <a href="<?php echo esc_url($url); ?>">
                                        <?php echo esc_html($title); ?>
                                    </a>
                                </h3>

                                <?php if ($category): ?>
                                    <div class="rlp275-category"><?php echo esc_html(strtoupper($category)); ?></div>
                                <?php endif; ?>

                                <?php
                                    $entry_value = (float) wc_get_price_to_display($product);
                                    $entry_formatted = rtrim(rtrim(number_format($entry_value, 2, '.', ''), '0'), '.');
                                    $entry_symbol = get_woocommerce_currency_symbol();
                                ?>
                                <div class="rlp275-price">
                                    <strong><?php echo esc_html($entry_symbol . $entry_formatted); ?></strong>
                                    <small>PER ENTRY</small>
                                </div>

                                <div class="rlp275-progress" aria-label="Raffle entry progress">
                                    <div class="rlp275-progress-head">
                                        <span><strong><?php echo esc_html($claimed); ?></strong> CLAIMED</span>
                                        <span><strong><?php echo esc_html($available); ?></strong> LEFT</span>
                                    </div>

                                    <div class="rlp275-track">
                                        <span style="width:<?php echo esc_attr($percent); ?>%"></span>
                                    </div>

                                    <div class="rlp275-progress-foot">
                                        <span><?php echo esc_html($percent); ?>% CLAIMED</span>
                                        <span><?php echo esc_html($total); ?> TOTAL ENTRIES</span>
                                    </div>
                                </div>

                                <a class="rlp275-button" href="<?php echo esc_url($url); ?>">
                                    <span>VIEW RAFFLE</span>
                                    <b>→</b>
                                </a>

                            </div>

                        </article>

                        <?php endforeach; ?>

                    </div>

                    <?php if ( ! is_page('raffles') ): ?>
                        <div class="rlp270-actions">
                            <a class="rlp270-viewall" href="<?php echo esc_url(home_url('/raffles/')); ?>">
                                <span>VIEW ALL RAFFLES</span>
                                <b>→</b>
                            </a>
                        </div>
                    <?php endif; ?>

                <?php endif; ?>

            </div>
        </section>

        <style>
        /* ==========================================================
           RaffleLB Live Raffles v0.27.0
           Complete premium rebuild with unique class names.
           No legacy raffle-card CSS can affect this block.
           ========================================================== */

        .rlp270-stage,
        .rlp270-stage *{
            box-sizing:border-box;
        }

        .rlp270-stage{
            position:relative!important;
            left:50%!important;
            width:100vw!important;
            max-width:none!important;
            margin:0 0 0 -50vw!important;
            padding:44px 24px 52px!important;

            background:
                radial-gradient(circle at 50% -10%,rgba(202,255,22,.09),transparent 34%),
                linear-gradient(180deg,#0a0d09 0%,#050605 100%)!important;

            border:0!important;
            border-radius:0!important;
            box-shadow:none!important;
            overflow:hidden!important;
            font-family:Inter,"Segoe UI",Arial,sans-serif!important;
        }

        .rlp270-stage:before{
            content:"";
            position:absolute;
            inset:0 auto auto 0;
            width:100%;
            height:1px;
            background:linear-gradient(90deg,transparent,rgba(202,255,22,.75),transparent);
        }

        .rlp270-inner{
            position:relative;
            z-index:1;
            width:min(1240px,100%)!important;
            margin:0 auto!important;
            padding:0!important;
        }

        .rlp270-kicker{
            display:flex!important;
            align-items:center!important;
            justify-content:center!important;
            gap:9px!important;
            margin:0 0 26px!important;
            color:#fff!important;
            font-size:13px!important;
            line-height:1!important;
            font-weight:900!important;
            letter-spacing:1.4px!important;
            text-transform:uppercase!important;
        }

        .rlp270-bolt{
            display:grid!important;
            place-items:center!important;
            width:24px!important;
            height:24px!important;
            border:1px solid rgba(202,255,22,.58)!important;
            border-radius:50%!important;
            color:#caff16!important;
            font-size:11px!important;
            box-shadow:0 0 16px rgba(202,255,22,.12)!important;
        }

        .rlp270-grid{
            display:grid!important;
            grid-template-columns:repeat(var(--rlp270-cols),minmax(0,340px))!important;
            justify-content:center!important;
            align-items:stretch!important;
            gap:24px!important;
            width:100%!important;
            margin:0 auto!important;
        }

        .rlp270-card{
            width:100%!important;
            max-width:340px!important;
            margin:0!important;
            overflow:hidden!important;

            border:1px solid rgba(202,255,22,.44)!important;
            border-radius:18px!important;

            background:#0a0d0a!important;
            box-shadow:
                0 18px 38px rgba(0,0,0,.34),
                0 0 0 1px rgba(255,255,255,.025) inset!important;

            transition:transform .22s ease,border-color .22s ease,box-shadow .22s ease!important;
        }

        .rlp270-card:hover{
            transform:translateY(-4px)!important;
            border-color:rgba(202,255,22,.8)!important;
            box-shadow:
                0 22px 46px rgba(0,0,0,.42),
                0 0 24px rgba(202,255,22,.05)!important;
        }

        .rlp270-media{
            position:relative!important;
            display:block!important;
            width:100%!important;
            aspect-ratio:1.12/1!important;
            overflow:hidden!important;
            background:#080908!important;
            text-decoration:none!important;
        }

        .rlp270-media img{
            display:block!important;
            width:100%!important;
            height:100%!important;
            object-fit:cover!important;
            transition:transform .32s ease!important;
        }

        .rlp270-card:hover .rlp270-media img{
            transform:scale(1.025)!important;
        }

        .rlp270-placeholder{
            display:grid!important;
            place-items:center!important;
            width:100%!important;
            height:100%!important;
            color:#caff16!important;
            background:#0a0c0a!important;
            font-size:72px!important;
            font-weight:900!important;
        }

        .rlp270-live{
            position:absolute!important;
            top:13px!important;
            left:13px!important;
            display:inline-flex!important;
            align-items:center!important;
            gap:7px!important;
            min-height:30px!important;
            padding:0 13px!important;
            border:1px solid rgba(255,255,255,.30)!important;
            border-radius:999px!important;
            background:#caff16!important;
            color:#050605!important;
            box-shadow:0 6px 20px rgba(202,255,22,.35),0 0 0 1px rgba(0,0,0,.08) inset!important;
            font-size:10px!important;
            line-height:1!important;
            font-weight:900!important;
            letter-spacing:.9px!important;
            text-shadow:none!important;
        }

        .rlp270-live i{
            width:7px!important;
            height:7px!important;
            flex:0 0 7px!important;
            border-radius:50%!important;
            background:#050605!important;
            box-shadow:0 0 0 2px rgba(5,6,5,.10)!important;
        }

        .rlp270-category{
            position:absolute!important;
            top:13px!important;
            right:13px!important;
            display:inline-flex!important;
            align-items:center!important;
            min-height:30px!important;
            padding:0 12px!important;
            border:1px solid rgba(202,255,22,.72)!important;
            border-radius:999px!important;
            background:rgba(5,7,5,.94)!important;
            color:#ffffff!important;
            box-shadow:0 6px 20px rgba(0,0,0,.38),0 0 12px rgba(202,255,22,.08)!important;
            font-size:9px!important;
            line-height:1!important;
            font-weight:900!important;
            letter-spacing:1px!important;
            text-shadow:0 1px 2px rgba(0,0,0,.65)!important;
            backdrop-filter:blur(9px)!important;
        }

        .rlp270-body{
            padding:18px 18px 19px!important;
            background:
                linear-gradient(180deg,rgba(16,20,15,.98),rgba(8,10,8,.99))!important;
        }

        .rlp270-body h3{
            min-height:44px!important;
            margin:0 0 15px!important;
            color:#fff!important;
            font-size:17px!important;
            line-height:1.22!important;
            font-weight:900!important;
            letter-spacing:-.35px!important;
        }

        .rlp270-body h3 a{
            color:#fff!important;
            text-decoration:none!important;
        }

        .rlp270-stats{
            display:grid!important;
            grid-template-columns:1fr 1fr!important;
            gap:14px!important;
            margin-top:2px!important;
            padding:14px 12px!important;
            border:1px solid rgba(255,255,255,.09)!important;
            border-radius:11px!important;
            background:linear-gradient(180deg,rgba(255,255,255,.035),rgba(255,255,255,.012))!important;
            box-shadow:inset 0 1px 0 rgba(255,255,255,.025)!important;
        }

        .rlp270-stats>div:last-child{
            text-align:right!important;
        }

        .rlp270-stats small{
            display:block!important;
            margin:0 0 6px!important;
            color:#c4cbc0!important;
            font-size:8px!important;
            line-height:1!important;
            font-weight:900!important;
            letter-spacing:1.35px!important;
        }

        .rlp270-stats strong{
            display:block!important;
            color:#ffffff!important;
            font-size:14px!important;
            line-height:1.2!important;
            font-weight:900!important;
            letter-spacing:.1px!important;
        }

        .rlp270-stats .woocommerce-Price-amount,
        .rlp270-stats .woocommerce-Price-currencySymbol{
            color:#fff!important;
        }

        .rlp270-progress{
            margin-top:11px!important;
            padding:11px 12px 12px!important;
            border:1px solid rgba(255,255,255,.07)!important;
            border-radius:11px!important;
            background:rgba(255,255,255,.018)!important;
        }

        .rlp270-progress-label{
            display:flex!important;
            align-items:center!important;
            justify-content:space-between!important;
            gap:14px!important;
            margin:0 0 9px!important;
            color:#d3d8d0!important;
            font-size:10px!important;
            line-height:1.3!important;
            font-weight:750!important;
        }

        .rlp270-progress-label strong{
            color:#caff16!important;
            font-size:11px!important;
            font-weight:900!important;
            text-shadow:0 0 10px rgba(202,255,22,.18)!important;
        }

        .rlp270-track{
            width:100%!important;
            height:7px!important;
            overflow:hidden!important;
            border-radius:999px!important;
            background:#2e352b!important;
            box-shadow:inset 0 1px 2px rgba(0,0,0,.55)!important;
        }

        .rlp270-track span{
            display:block!important;
            height:100%!important;
            border-radius:inherit!important;
            background:linear-gradient(90deg,#a9d900,#caff16)!important;
        }

        .rlp270-button{
            display:flex!important;
            align-items:center!important;
            justify-content:space-between!important;
            width:100%!important;
            min-height:46px!important;
            margin-top:13px!important;
            padding:0 16px!important;
            border:1px solid rgba(202,255,22,.78)!important;
            border-radius:12px!important;
            background:linear-gradient(180deg,rgba(202,255,22,.075),rgba(202,255,22,.018))!important;
            color:#ffffff!important;
            box-shadow:inset 0 1px 0 rgba(255,255,255,.035),0 8px 20px rgba(0,0,0,.20)!important;
            text-decoration:none!important;
            font-size:10px!important;
            line-height:1!important;
            font-weight:900!important;
            letter-spacing:1.15px!important;
            transition:.18s ease!important;
        }

        .rlp270-button:hover{
            border-color:#caff16!important;
            background:#caff16!important;
            color:#080908!important;
        }

        .rlp270-button b{
            font-size:16px!important;
            font-weight:700!important;
        }

        .rlp270-actions{
            display:flex!important;
            justify-content:center!important;
            margin:28px 0 0!important;
        }

        .rlp270-viewall{
            display:flex!important;
            align-items:center!important;
            justify-content:center!important;
            gap:42px!important;
            min-width:285px!important;
            min-height:48px!important;
            padding:0 23px!important;
            border-radius:999px!important;
            background:#caff16!important;
            color:#080908!important;
            text-decoration:none!important;
            font-size:10px!important;
            line-height:1!important;
            font-weight:900!important;
            letter-spacing:.8px!important;
            box-shadow:0 12px 28px rgba(202,255,22,.11)!important;
            transition:.18s ease!important;
        }

        .rlp270-viewall:hover{
            transform:translateY(-2px)!important;
            background:#bff10b!important;
            color:#080908!important;
        }

        .rlp270-viewall b{
            font-size:18px!important;
            font-weight:700!important;
        }

        .rlp270-empty{
            width:min(640px,100%)!important;
            margin:0 auto!important;
            padding:34px 28px!important;
            border:1px solid #293126!important;
            border-radius:16px!important;
            background:#0b0e0b!important;
            text-align:center!important;
        }

        .rlp270-empty strong{
            display:block!important;
            margin-bottom:7px!important;
            color:#fff!important;
            font-size:20px!important;
            font-weight:900!important;
        }

        .rlp270-empty span{
            display:block!important;
            color:#9aa295!important;
            font-size:12px!important;
            line-height:1.6!important;
        }

        @media(max-width:1180px){
            .rlp270-grid{
                grid-template-columns:repeat(min(var(--rlp270-cols),3),minmax(0,310px))!important;
                gap:19px!important;
            }
        }

        @media(max-width:767px){
            .rlp270-stage{
                padding:28px 12px 36px!important;
            }

            .rlp270-kicker{
                margin-bottom:18px!important;
                font-size:11px!important;
            }

            .rlp270-grid{
                grid-template-columns:repeat(2,minmax(0,1fr))!important;
                gap:10px!important;
            }

            .rlp270-card{
                max-width:none!important;
                border-radius:13px!important;
            }

            .rlp270-body{
                padding:10px 10px 11px!important;
            }

            .rlp270-body h3{
                min-height:34px!important;
                margin-bottom:9px!important;
                font-size:12px!important;
            }

            .rlp270-stats{
                gap:7px!important;
                padding:9px 0!important;
            }

            .rlp270-stats small{
                font-size:6px!important;
            }

            .rlp270-stats strong{
                font-size:10px!important;
            }

            .rlp270-progress{
                padding:10px 0 11px!important;
            }

            .rlp270-progress-label{
                font-size:7px!important;
            }

            .rlp270-progress-label strong{
                font-size:7px!important;
            }

            .rlp270-button{
                min-height:36px!important;
                padding:0 10px!important;
                font-size:7px!important;
            }

            .rlp270-actions{
                margin-top:21px!important;
            }

            .rlp270-viewall{
                min-width:230px!important;
                min-height:43px!important;
                gap:28px!important;
                font-size:8px!important;
            }
        }

        @media(max-width:390px){
            .rlp270-grid{
                grid-template-columns:1fr!important;
                width:min(100%,320px)!important;
            }

            .rlp270-body h3{
                min-height:0!important;
                font-size:15px!important;
            }
        }
        
        /* ==========================================================
           v0.27.5 — HOMEPAGE CARDS MATCH CATEGORY CARD LAYOUT
           ========================================================== */
        .rlp275-card{
            max-width:380px!important;
            padding:12px 12px 16px!important;
            border:1px solid #292d27!important;
            border-radius:17px!important;
            background:linear-gradient(145deg,#111310,#0b0c0a)!important;
            box-shadow:0 18px 50px rgba(0,0,0,.20)!important;
            transition:transform .22s ease,border-color .22s ease,box-shadow .22s ease!important;
        }
        .rlp275-card:hover{
            transform:translateY(-4px)!important;
            border-color:#454b40!important;
            box-shadow:0 26px 65px rgba(0,0,0,.30)!important;
        }
        .rlp275-media{
            height:auto!important;
            min-height:0!important;
            padding:0!important;
            overflow:hidden!important;
            border:1px solid #242823!important;
            border-radius:12px!important;
            background:#070807!important;
        }
        .rlp275-media img{
            width:100%!important;
            height:auto!important;
            aspect-ratio:16/9!important;
            object-fit:cover!important;
            display:block!important;
            transition:transform .3s ease!important;
        }
        .rlp275-card:hover .rlp275-media img{
            transform:scale(1.025)!important;
        }
        .rlp275-body{
            padding:18px 8px 0!important;
            display:flex!important;
            flex-direction:column!important;
            flex:1 1 auto!important;
            text-align:left!important;
        }
        .rlp275-title{
            min-height:46px!important;
            margin:0 0 9px!important;
            display:flex!important;
            align-items:flex-start!important;
            justify-content:center!important;
            color:#f4f6f1!important;
            font-size:17px!important;
            line-height:1.35!important;
            font-weight:800!important;
            letter-spacing:-.015em!important;
            text-align:center!important;
        }
        .rlp275-title a{color:#f4f6f1!important;text-decoration:none!important}
        .rlp275-title a:hover{color:#caff16!important}
        .rlp275-category{
            min-height:18px!important;
            display:flex!important;
            align-items:center!important;
            justify-content:center!important;
            margin:0!important;
            color:#737970!important;
            font-size:11px!important;
            line-height:1.2!important;
            font-weight:500!important;
            text-transform:uppercase!important;
            letter-spacing:.08em!important;
            text-align:center!important;
        }
        .rlp275-price{
            min-height:34px!important;
            margin:14px 0 16px!important;
            display:flex!important;
            align-items:center!important;
            justify-content:center!important;
            color:#caff16!important;
            text-align:center!important;
        }
        .rlp275-price strong{
            color:#caff16!important;
            font-size:24px!important;
            line-height:1!important;
            font-weight:900!important;
            letter-spacing:-.03em!important;
        }
        .rlp275-price small{
            margin-left:6px!important;
            color:#d3d7cf!important;
            font-size:9px!important;
            line-height:1!important;
            font-weight:850!important;
            letter-spacing:.11em!important;
        }
        .rlp275-progress{
            width:100%!important;
            min-height:76px!important;
            margin:0 0 16px!important;
            padding:12px 13px!important;
            border:1px solid #242a22!important;
            border-radius:11px!important;
            background:#0b0d0a!important;
            box-sizing:border-box!important;
        }
        .rlp275-progress-head,
        .rlp275-progress-foot{
            display:flex!important;
            align-items:center!important;
            justify-content:space-between!important;
            gap:12px!important;
            color:#9ba197!important;
            font-size:9px!important;
            line-height:1.2!important;
            font-weight:750!important;
            letter-spacing:.07em!important;
            text-transform:uppercase!important;
            white-space:nowrap!important;
        }
        .rlp275-progress-head strong{
            color:#f3f5f1!important;
            font-size:12px!important;
            font-weight:900!important;
        }
        .rlp275-track{
            position:relative!important;
            width:100%!important;
            height:7px!important;
            margin:9px 0 8px!important;
            overflow:hidden!important;
            border-radius:999px!important;
            background:#2a2f29!important;
        }
        .rlp275-track>span{
            display:block!important;
            height:100%!important;
            border-radius:inherit!important;
            background:#caff16!important;
            transition:width .25s ease!important;
        }
        .rlp275-progress-foot span:last-child{color:#caff16!important}
        .rlp275-button{
            display:flex!important;
            width:100%!important;
            min-height:46px!important;
            align-items:center!important;
            justify-content:space-between!important;
            gap:12px!important;
            margin-top:auto!important;
            padding:0 17px!important;
            border:1px solid #caff16!important;
            border-radius:999px!important;
            background:#caff16!important;
            color:#080908!important;
            font-size:11px!important;
            line-height:1!important;
            font-weight:900!important;
            letter-spacing:.08em!important;
            text-decoration:none!important;
            transition:background .2s ease,transform .2s ease!important;
        }
        .rlp275-button span,.rlp275-button b{color:#080908!important}
        .rlp275-button:hover{
            background:#bdf20e!important;
            color:#080908!important;
            transform:translateY(-1px)!important;
        }
        @media(max-width:767px){
            .rlp275-title{font-size:15px!important;min-height:42px!important}
            .rlp275-price strong{font-size:22px!important}
        }

        /* ==========================================================
           v0.27.6 — HOMEPAGE IMAGE FIT + PREMIUM VIEW ALL
           ========================================================== */

        /* Force the homepage artwork area to the exact 16:9 image ratio.
           This removes the extra black space below the product artwork. */
        .rlp275-media{
            position:relative!important;
            width:100%!important;
            height:auto!important;
            min-height:0!important;
            aspect-ratio:16 / 9!important;
            padding:0!important;
            display:block!important;
            overflow:hidden!important;
            line-height:0!important;
            background:#050605!important;
        }

        .rlp275-media img{
            position:absolute!important;
            inset:0!important;
            width:100%!important;
            height:100%!important;
            max-width:none!important;
            max-height:none!important;
            margin:0!important;
            padding:0!important;
            display:block!important;
            object-fit:cover!important;
            object-position:center center!important;
        }

        /* Distinct premium secondary CTA — intentionally different
           from the bright lime VIEW RAFFLE buttons. */
        .rlp270-viewall{
            position:relative!important;
            display:inline-flex!important;
            align-items:center!important;
            justify-content:center!important;
            gap:18px!important;
            min-width:310px!important;
            min-height:54px!important;
            margin-top:28px!important;
            padding:0 28px!important;

            border:1.5px solid #caff16!important;
            border-radius:999px!important;

            background:
                linear-gradient(180deg,rgba(202,255,22,.055),rgba(202,255,22,.012)),
                #090b08!important;

            color:#ffffff!important;
            box-shadow:
                inset 0 1px 0 rgba(255,255,255,.045),
                0 0 0 1px rgba(202,255,22,.05),
                0 12px 34px rgba(0,0,0,.34),
                0 0 24px rgba(202,255,22,.08)!important;

            font-size:11px!important;
            line-height:1!important;
            font-weight:900!important;
            letter-spacing:1.35px!important;
            text-transform:uppercase!important;
            text-decoration:none!important;

            transition:
                transform .2s ease,
                border-color .2s ease,
                background .2s ease,
                box-shadow .2s ease!important;
        }

        .rlp270-viewall:before,
        .rlp270-viewall:after{
            content:"✦"!important;
            color:#caff16!important;
            font-size:13px!important;
            line-height:1!important;
            text-shadow:0 0 12px rgba(202,255,22,.45)!important;
        }

        .rlp270-viewall:hover{
            transform:translateY(-2px)!important;
            border-color:#d8ff56!important;
            background:
                linear-gradient(180deg,rgba(202,255,22,.095),rgba(202,255,22,.025)),
                #0a0c09!important;
            box-shadow:
                inset 0 1px 0 rgba(255,255,255,.055),
                0 14px 38px rgba(0,0,0,.38),
                0 0 30px rgba(202,255,22,.14)!important;
            color:#ffffff!important;
        }

        @media(max-width:767px){
            .rlp270-viewall{
                min-width:0!important;
                width:min(100%,320px)!important;
                min-height:50px!important;
                padding:0 20px!important;
                gap:13px!important;
                font-size:10px!important;
            }
        }

        /* ==========================================================
           v0.27.7 — MOBILE PROGRESS BOX OVERFLOW FIX
           ========================================================== */
        @media(max-width:767px){

            .rlp275-progress{
                width:100%!important;
                min-width:0!important;
                padding:12px 11px!important;
                box-sizing:border-box!important;
                overflow:hidden!important;
            }

            .rlp275-progress-head,
            .rlp275-progress-foot{
                width:100%!important;
                min-width:0!important;
                gap:8px!important;
                white-space:normal!important;
            }

            .rlp275-progress-head span,
            .rlp275-progress-foot span{
                min-width:0!important;
                max-width:50%!important;
                flex:1 1 50%!important;
                overflow:hidden!important;
                text-overflow:ellipsis!important;
            }

            .rlp275-progress-head span:last-child,
            .rlp275-progress-foot span:last-child{
                text-align:right!important;
            }

            .rlp275-progress-foot{
                font-size:8px!important;
                letter-spacing:.035em!important;
            }

            .rlp275-progress-foot span:last-child{
                white-space:nowrap!important;
                font-size:8px!important;
            }

            .rlp275-progress-head{
                font-size:9px!important;
            }

            .rlp275-progress-head strong{
                font-size:11px!important;
            }
        }

        @media(max-width:390px){
            .rlp275-progress-foot{
                font-size:7px!important;
            }

            .rlp275-progress-foot span:last-child{
                font-size:7px!important;
                letter-spacing:0!important;
            }
        }

        /* v0.27.8 — slightly larger homepage raffle cards/images on desktop */
        @media(min-width:768px){
            .rlp270-grid{
                grid-template-columns:repeat(auto-fit,minmax(300px,390px))!important;
                gap:24px!important;
            }
            .rlp275-card{
                width:100%!important;
                max-width:390px!important;
            }
        }

        /* v0.27.9 — larger homepage raffle cards on mobile */
        @media(max-width:767px){
            .rlp270-inner{
                width:100%!important;
                padding-left:10px!important;
                padding-right:10px!important;
            }
            .rlp270-grid{
                width:100%!important;
                grid-template-columns:repeat(2,minmax(0,1fr))!important;
                gap:10px!important;
            }
            .rlp275-card{
                width:100%!important;
                max-width:none!important;
                padding:9px 9px 13px!important;
            }
            .rlp275-body{
                padding:15px 5px 0!important;
            }
        }

        @media(max-width:370px){
            .rlp270-grid{
                gap:7px!important;
            }
            .rlp275-card{
                padding:7px 7px 11px!important;
            }
        }
</style>
        <?php

        return ob_get_clean();
    }

    public static function recent_winners_shortcode($atts = []) {
        global $wpdb;

        $atts = shortcode_atts([
            'limit' => 4,
        ], $atts, 'rafflelb_latest_winners');

        $limit = max(1, min(8, absint($atts['limit'])));
        $table = $wpdb->prefix . \RaffleLB\Core\Contracts::RESULT_TABLE;

        $results = $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM {$table} ORDER BY selected_at DESC, id DESC LIMIT %d", $limit)
        );

        $total = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}");

        ob_start();
        ?>
        <section class="rlw322" id="latest-winners">
            <div class="rlw322-inner">
                <div class="rlw322-head">
                    <div>
                        <div class="rlw322-kicker"><i></i> VERIFIED RESULTS</div>
                        <h2>LATEST <span>WINNERS</span></h2>
                        <p>Completed RaffleLB draws appear here automatically from the recorded draw results.</p>
                    </div>
                    <a class="rlw322-all" href="<?php echo esc_url(home_url('/winners/')); ?>"><span>VIEW ALL WINNERS</span><span class="rlw322-arrow" aria-hidden="true">→</span></a>
                </div>

                <?php if (!$results): ?>
                    <div class="rlw322-empty">
                        <div class="rlw322-empty-icon" aria-hidden="true"><svg viewBox="0 0 32 32"><path d="M10 5h12v8a6 6 0 0 1-12 0V5Zm0 2H6v4a5 5 0 0 0 5 5m11-9h4v4a5 5 0 0 1-5 5M16 19v6m-5 2h10M13 25h6"/></svg></div>
                        <div>
                            <strong>FIRST WINNERS <em>COMING SOON</em></strong>
                            <span>Once the first RaffleLB draw is completed, the verified winner will appear here automatically.</span>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="rlw322-grid" style="--rlw-count:<?php echo esc_attr(min($limit, count($results))); ?>">
                    <?php foreach ($results as $result):
                        $pid = absint($result->product_id);
                        $product = function_exists('wc_get_product') ? wc_get_product($pid) : false;
                        $title = get_the_title($pid);
                        $image_id = $product ? $product->get_image_id() : 0;
                        $url = get_permalink($pid);
                        $entry = '#' . str_pad((string) absint($result->entry_number), 3, '0', STR_PAD_LEFT);
                        $date = !empty($result->selected_at) ? mysql2date('M j, Y', $result->selected_at) : '';

                        $masked = RaffleLB_Draw_Engine::homepage_winner_masked_name($result);
                    ?>
                        <article class="rlw322-card">
                            <a class="rlw322-media" href="<?php echo esc_url($url); ?>" aria-label="<?php echo esc_attr($title); ?>">
                                <div class="rlw322-glow"></div>
                                <?php if ($image_id): ?>
                                    <?php echo wp_get_attachment_image($image_id, 'medium_large', false, ['loading'=>'lazy','alt'=>$title]); ?>
                                <?php else: ?>
                                    <div class="rlw322-placeholder">R</div>
                                <?php endif; ?>
                                <span class="rlw322-badge"><b>✓</b> WINNER</span>
                            </a>

                            <div class="rlw322-body">
                                <div class="rlw322-date"><?php echo esc_html(strtoupper($date)); ?></div>
                                <h3><?php echo esc_html($title); ?></h3>

                                <div class="rlw322-result">
                                    <div><small>WINNER</small><strong><?php echo esc_html($masked); ?></strong></div>
                                    <div><small>WINNING ENTRY</small><strong><?php echo esc_html($entry); ?></strong></div>
                                </div>

                                <div class="rlw322-foot">
                                    <span><i></i> VERIFIED RESULT</span>
                                    <a href="<?php echo esc_url($url); ?>">VIEW →</a>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <div class="rlw322-trust">
                    <div><i class="rlw322-stat-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="m12 2 9 5v10l-9 5-9-5V7zM3 7l9 5 9-5M12 12v10"/></svg></i><strong><?php echo esc_html($total); ?></strong><span>COMPLETED <?php echo $total === 1 ? 'DRAW' : 'DRAWS'; ?></span></div>
                    <div><i class="rlw322-stat-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="9" cy="7" r="4"/><path d="M2 22v-4a7 7 0 0 1 14 0v4M17 3a4 4 0 0 1 0 8M19 14a5 5 0 0 1 3 4v4"/></svg></i><strong><?php echo esc_html($total); ?></strong><span>WINNERS RECORDED</span></div>
                    <div><i class="rlw322-stat-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="m12 2 9 4v7c0 5-9 9-9 9s-9-4-9-9V6zM8 12l3 3 5-6"/></svg></i><strong>100%</strong><span>RECORDED RESULTS</span></div>
                </div>
            </div>
        </section>

        <style>
        .rlw322,.rlw322 *{box-sizing:border-box}
        .rlw322{--lime:#baff00;position:relative;width:100vw!important;max-width:100vw!important;margin-left:calc(50% - 50vw)!important;margin-right:calc(50% - 50vw)!important;padding:64px 28px 62px;overflow:hidden;background:radial-gradient(circle at 50% 0,rgba(186,255,0,.055),transparent 31%),#050805;color:#fff;font-family:Inter,Arial,Helvetica,sans-serif}
        .rlw322:before{content:"";position:absolute;left:10%;right:10%;top:0;height:1px;background:linear-gradient(90deg,transparent,rgba(186,255,0,.25),transparent)}
        .rlw322-inner{position:relative;z-index:2;max-width:1500px;margin:0 auto}
        .rlw322-head{display:flex;align-items:flex-end;justify-content:space-between;gap:28px;margin-bottom:27px}
        .rlw322-kicker{display:flex;align-items:center;gap:8px;margin-bottom:9px;color:var(--lime);font-size:10px;font-weight:900;letter-spacing:.13em}
        .rlw322-kicker i{width:7px;height:7px;border-radius:50%;background:var(--lime);box-shadow:0 0 13px rgba(186,255,0,.7)}
        .rlw322-head h2{margin:0!important;color:#fff!important;font-size:32px!important;line-height:1.08!important;font-weight:900!important;letter-spacing:-.03em!important}
        .rlw322-head h2 span{color:var(--lime)}
        .rlw322-head p{max-width:650px;margin:9px 0 0!important;color:#aab2a7!important;font-size:13px!important;line-height:1.6!important}
        .rlw322-all{display:inline-flex!important;min-height:42px;align-items:center;justify-content:center;padding:0 19px;border:1px solid #344033;border-radius:9px;color:#fff!important;text-decoration:none!important;font-size:9px;font-weight:900;letter-spacing:.05em;white-space:nowrap;transition:.2s}
        .rlw322-all:hover{border-color:var(--lime);color:var(--lime)!important}

        /* Homepage Latest Winners — compact Winners-page style on desktop */
        .rlw322-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:18px;align-items:start}
        .rlw322-card{min-width:0;overflow:hidden;display:block;border:1px solid #2a3026;border-radius:18px;background:linear-gradient(180deg,#12150f 0%,#0e110c 100%);box-shadow:0 18px 44px rgba(0,0,0,.24);transition:transform .2s ease,border-color .2s ease,box-shadow .2s ease}
        .rlw322-card:only-child{grid-column:1/-1;width:100%;max-width:430px;margin:0 auto}
        .rlw322-card:hover{transform:translateY(-4px);border-color:rgba(186,255,0,.42);box-shadow:0 22px 52px rgba(0,0,0,.34)}
        .rlw322-media{position:relative;display:block;width:100%;aspect-ratio:16/9;min-height:0;overflow:hidden;background:#0c0f0a;text-decoration:none!important;border-right:0;border-bottom:1px solid #202820}
        .rlw322-card:only-child .rlw322-media{min-height:0}
        .rlw322-media:after{content:"";position:absolute;inset:auto 0 0 0;height:34%;background:linear-gradient(180deg,transparent,rgba(0,0,0,.48));pointer-events:none;z-index:3}
        .rlw322-glow{position:absolute;width:180px;height:180px;left:50%;top:50%;transform:translate(-50%,-50%);border-radius:50%;background:rgba(186,255,0,.045);filter:blur(28px)}
        .rlw322-media img{position:relative;z-index:2;display:block;width:100%;height:100%;object-fit:cover;object-position:center;padding:0;transition:transform .25s ease}
        .rlw322-card:hover .rlw322-media img{transform:scale(1.025)}
        .rlw322-placeholder{position:absolute;z-index:2;left:50%;top:50%;transform:translate(-50%,-50%);color:var(--lime);font-size:64px;font-weight:900}
        .rlw322-badge{position:absolute;z-index:5;top:12px;left:12px;display:inline-flex;align-items:center;gap:6px;padding:7px 10px;border-radius:999px;background:var(--lime);color:#050705;font-size:8px;font-weight:900;letter-spacing:.08em}
        .rlw322-badge b{font-size:10px}
        .rlw322-body{min-width:0;padding:16px;display:block}
        .rlw322-date{margin-bottom:7px;color:var(--lime);font-size:8px;font-weight:900;letter-spacing:.10em}
        .rlw322-body h3{min-height:0;margin:0 0 13px!important;color:#fff!important;font-size:18px!important;line-height:1.22!important;font-weight:900!important;letter-spacing:-.01em!important}
        .rlw322-result{display:grid;grid-template-columns:1fr 1fr;gap:10px;max-width:none;padding:0;border:0}
        .rlw322-result>div{min-width:0;padding:11px;border:1px solid #31372d;border-radius:11px;background:#171b14}
        .rlw322-result small{display:block;margin-bottom:5px;color:#8d9686;font-size:8px;font-weight:900;letter-spacing:.08em}
        .rlw322-result strong{display:block;color:#fff;font-size:13px;font-weight:900;line-height:1.2;overflow-wrap:anywhere}
        .rlw322-result>div:last-child{text-align:left}
        .rlw322-result>div:last-child strong{color:var(--lime);font-size:16px}
        .rlw322-foot{display:flex;align-items:center;justify-content:space-between;gap:14px;padding-top:13px}
        .rlw322-foot>span{display:flex;align-items:center;gap:7px;color:#8f9889;font-size:8px;font-weight:900;letter-spacing:.06em}
        .rlw322-foot>span i{width:7px;height:7px;border-radius:50%;background:var(--lime);box-shadow:0 0 9px rgba(186,255,0,.5)}
        .rlw322-foot>a{display:inline-flex;min-width:72px;height:34px;padding:0 11px;align-items:center;justify-content:center;border:1px solid rgba(186,255,0,.28);border-radius:9px;color:var(--lime)!important;text-decoration:none!important;font-size:8px;font-weight:900}
        @media(max-width:1250px){
            .rlw322-grid{grid-template-columns:repeat(2,minmax(0,1fr))}
            .rlw322-card,.rlw322-card:only-child{max-width:430px}
        }
        /* Desktop-only: match the Inter font used by every other homepage section (Featured Products,
           Shop by Category, Community Reviews, etc.) instead of plain Arial, which renders heavier
           weights (900/950) fuzzier — plus bump up the smallest label sizes (was as low as 8px) so
           everything reads comfortably. Mobile keeps its original look untouched. */
        @media(min-width:768px){
            .rlw322{font-family:Inter,"Segoe UI",Arial,sans-serif!important;-webkit-font-smoothing:antialiased;text-rendering:optimizeLegibility}
            .rlw322-kicker{font-size:12px}
            .rlw322-head p{font-size:14.5px!important}
            .rlw322-all{font-size:11px}
            .rlw322-badge{font-size:10px;padding:8px 12px}
            .rlw322-badge b{font-size:12px}
            .rlw322-date{font-size:10px}
            .rlw322-body h3{font-size:20px!important}
            .rlw322-result small{font-size:10px}
            .rlw322-result strong{font-size:15px}
            .rlw322-result>div:last-child strong{font-size:18px}
            .rlw322-foot>span{font-size:10px}
            .rlw322-foot>a{font-size:10px;min-width:78px}
            .rlw322-trust strong{font-size:21px}
            .rlw322-trust span{font-size:12px}
        }
        .rlw322-empty{display:flex;min-height:180px;align-items:center;justify-content:center;gap:18px;padding:34px;border:1px solid #273126;border-radius:16px;background:radial-gradient(circle at 50% 50%,rgba(186,255,0,.04),transparent 45%),#090d09;text-align:left}
        .rlw322-empty-icon{display:flex;width:62px;height:62px;flex:0 0 62px;align-items:center;justify-content:center;border:1px solid rgba(186,255,0,.45);border-radius:50%;color:var(--lime);font-size:24px;box-shadow:0 0 35px rgba(186,255,0,.05)}
        .rlw322-empty strong{display:block;margin-bottom:7px;color:#fff;font-size:16px;font-weight:900}
        .rlw322-empty span{display:block;max-width:620px;color:#9ea69b;font-size:12px;line-height:1.6}
        .rlw322-trust{display:grid;grid-template-columns:repeat(3,1fr);margin-top:24px;border:1px solid #202820;border-radius:12px;background:rgba(255,255,255,.01);overflow:hidden}
        .rlw322-trust>div{padding:16px;text-align:center}
        .rlw322-trust>div+div{border-left:1px solid #202820}
        .rlw322-trust strong{display:block;color:var(--lime);font-size:19px;font-weight:900}
        .rlw322-trust span{display:block;margin-top:5px;color:#aab2a7;font-size:11px;font-weight:800;line-height:1.25;letter-spacing:.055em}

        @media(max-width:767px){
            .rlw322{padding:38px 14px 42px}
            .rlw322-head{display:grid;grid-template-columns:1fr auto;align-items:end;gap:14px;margin-bottom:20px}
            .rlw322-head>div{min-width:0}
            .rlw322-kicker{margin-bottom:7px;font-size:9px}
            .rlw322-head h2{font-size:27px!important;line-height:1.05!important}
            .rlw322-head p{margin-top:8px!important;font-size:11.5px!important;line-height:1.5!important}
            .rlw322-all{min-height:38px;margin-top:0;padding:0 13px;font-size:8px;border-radius:8px}
            .rlw322-grid{grid-template-columns:1fr;gap:13px}
            .rlw322-card,.rlw322-card:only-child{display:block;min-height:0;max-width:none;width:100%;margin:0}
            .rlw322-media,.rlw322-card:only-child .rlw322-media{height:220px;min-height:220px;border-right:0;border-bottom:1px solid #202820}
            .rlw322-media img{object-fit:contain;padding:12px 14px}
            .rlw322-body{padding:18px 17px 17px}
            .rlw322-date{font-size:8px;margin-bottom:6px}
            .rlw322-body h3{margin-bottom:14px!important;font-size:16px!important;line-height:1.3!important}
            .rlw322-result{grid-template-columns:1fr 1fr;gap:14px;max-width:none;padding:13px 0}
            .rlw322-result strong{font-size:13px}
            .rlw322-result>div:last-child{text-align:right}
            .rlw322-result>div:last-child strong{font-size:16px}
            .rlw322-foot{justify-content:space-between;padding-top:13px}
            .rlw322-empty{min-height:132px;display:grid;grid-template-columns:50px 1fr;align-items:center;gap:14px;padding:20px;border-radius:13px}
            .rlw322-empty-icon{width:50px;height:50px;flex-basis:50px;font-size:20px}
            .rlw322-empty strong{margin-bottom:5px;font-size:14px}
            .rlw322-empty span{font-size:10.5px;line-height:1.45}
            .rlw322-trust{margin-top:16px;border-radius:10px}
            .rlw322-trust>div{padding:11px 5px}
            .rlw322-trust strong{font-size:16px}
            .rlw322-trust span{margin-top:4px;font-size:8.5px;line-height:1.2;letter-spacing:.02em}
        }
        @media(max-width:480px){
            .rlw322{padding:34px 12px 38px}
            .rlw322-head{grid-template-columns:1fr;gap:10px}
            .rlw322-all{justify-self:start}
            .rlw322-head h2{font-size:25px!important}
            .rlw322-media,.rlw322-card:only-child .rlw322-media{height:205px;min-height:205px}
            .rlw322-empty{grid-template-columns:44px 1fr;padding:17px 15px}
            .rlw322-empty-icon{width:44px;height:44px;flex-basis:44px;font-size:18px}
            .rlw322-trust strong{font-size:15px}
            .rlw322-trust span{font-size:8px}
        }
        
/* Reference design: compact homepage presentation with live data unchanged. */
.rlw322{width:100%!important;max-width:100%!important;margin:0!important;padding:42px 28px;background:radial-gradient(ellipse at 50% 40%,#baff0009,transparent 65%),#050805;font-family:Inter,"Segoe UI",Arial,sans-serif!important}
.rlw322-inner{max-width:1440px}.rlw322-head{margin-bottom:24px}.rlw322-head h2{font-family:inherit!important;font-size:36px!important;font-weight:800!important}.rlw322-head p{max-width:850px;font-size:14px!important}.rlw322-kicker{font-size:11px;letter-spacing:.18em}.rlw322-all{min-height:46px;border-color:#8baa18;font-size:11px;padding:0 23px;box-shadow:inset 0 1px 0 #baff001a}.rlw322 a:focus-visible{outline:3px solid #baff00;outline-offset:4px}
.rlw322-empty{position:relative;isolation:isolate;overflow:hidden;display:flex;flex-direction:column;gap:20px;min-height:240px;padding:30px 24px;text-align:center;border:1px solid #475a2c;border-radius:20px;background:radial-gradient(ellipse at 50% 30%,#baff000d,transparent 65%),#080c06}
.rlw322-empty::before{content:'';position:absolute;inset:24px;border:1px solid #baff0028;border-top-color:transparent;border-bottom-color:transparent;border-radius:10px;pointer-events:none;z-index:-1}
.rlw322-empty-icon{width:76px;height:76px;flex:0 0 76px;font-size:38px;border-color:#baff00;background:radial-gradient(circle at 40% 25%,#203405,#080c03 70%);box-shadow:0 0 0 9px #baff0007,0 0 30px #baff0015}
.rlw322-empty strong{font-size:23px;font-weight:750;line-height:1.25;margin-bottom:10px}.rlw322-empty strong em{color:#baff00;font-style:normal}.rlw322-empty span{font-size:14px;line-height:1.55;max-width:570px;margin:auto;color:#b7c0b1}
.rlw322-trust{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:20px;margin-top:22px;border:0;border-radius:0;background:none;overflow:visible}
.rlw322-trust>div{display:grid;grid-template-columns:58px minmax(0,1fr);grid-template-rows:auto auto;gap:4px 20px;align-items:center;text-align:left;padding:22px 26px;border:1px solid #475638!important;border-radius:17px;background:linear-gradient(135deg,#11180c,#080c07)}
.rlw322-stat-icon{grid-row:1/3;display:flex;align-items:center;justify-content:center;width:58px;height:58px;border:1px solid #baff0024;border-radius:13px;background:#0a1007}.rlw322-stat-icon svg{width:29px;height:29px;fill:none;stroke:#baff00;stroke-width:1.5;stroke-linecap:round;stroke-linejoin:round}
.rlw322-trust strong{font-size:30px;line-height:1.15;font-weight:750}.rlw322-trust span{font-size:10px;letter-spacing:.14em;line-height:1.4;margin:0;color:#b7c0b1}
.rlw322-foot>a{min-height:44px}.rlw322-media img{object-fit:contain;transform:none!important}.rlw322-grid{align-items:stretch}
@media(max-width:767px){.rlw322{padding:30px 14px}.rlw322-head{display:flex;flex-direction:column;align-items:flex-start;gap:14px;margin-bottom:20px}.rlw322-head h2{font-size:28px!important}.rlw322-head p{font-size:13px!important}.rlw322-all{min-height:44px;font-size:10px}.rlw322-empty{padding:24px 18px;min-height:0;gap:18px;border-radius:16px}.rlw322-empty-icon{width:58px;height:58px;flex-basis:58px;font-size:30px}.rlw322-empty strong{font-size:19px}.rlw322-empty span{font-size:13px}.rlw322-trust{grid-template-columns:1fr;gap:9px;margin-top:16px}.rlw322-trust>div{grid-template-columns:40px minmax(0,1fr);padding:12px 16px;gap:2px 14px;border-radius:12px}.rlw322-stat-icon{width:40px;height:40px;border-radius:9px}.rlw322-stat-icon svg{width:24px;height:24px}.rlw322-trust strong{font-size:24px}.rlw322-trust span{font-size:10px}}
@media(prefers-reduced-motion:reduce){.rlw322-card,.rlw322-all{transition:none}.rlw322-card:hover{transform:none}}

/* Comfortable mobile inset and the same action treatment as Featured Products. */
.rlw322 .rlw322-all{display:inline-flex!important;gap:18px;min-width:212px;min-height:44px;padding:12px 22px;border:1px solid #baff00!important;border-radius:10px;background:#080e0a;box-shadow:0 0 24px #baff0017,inset 0 1px 0 #baff0014;font-size:11px;line-height:1.4}
.rlw322 .rlw322-all:hover{background:#111b0b;box-shadow:0 0 28px #baff002b}.rlw322-arrow{color:#baff00;font-size:19px;line-height:1;font-weight:400}
.rlw322-empty-icon svg{display:block;width:38px;height:38px;fill:none;stroke:currentColor;stroke-width:1.7;stroke-linecap:round;stroke-linejoin:round}
@media(max-width:767px){.rlw322 .rlw322-empty{padding:26px 30px;gap:20px}.rlw322 .rlw322-empty::before{inset:14px}.rlw322 .rlw322-empty>div:last-child{width:100%;min-width:0}.rlw322 .rlw322-empty strong{font-size:19px;line-height:1.35}.rlw322 .rlw322-empty strong em{display:block}.rlw322 .rlw322-empty span{font-size:13px;line-height:1.6}.rlw322-empty-icon svg{width:31px;height:31px}}
</style>
        <?php
        return ob_get_clean();
    }

    public static function append_home_community_sections($content) {
        // Community Reviews and "What Should We Raffle Next?" are no longer
        // injected automatically on the WordPress front page. The underlying
        // shortcode remains available for use on a dedicated page if desired.
        return $content;
    }

    public static function community_sections_shortcode() {
        $reviews = get_posts([
            'post_type' => 'rafflelb_review',
            'post_status' => 'publish',
            'posts_per_page' => 3,
            'orderby' => 'date',
            'order' => 'DESC',
            'no_found_rows' => true,
        ]);

        $status = isset($_GET['raffle_request']) ? sanitize_key(wp_unslash($_GET['raffle_request'])) : '';
        $review_status = isset($_GET['review_submit']) ? sanitize_key(wp_unslash($_GET['review_submit'])) : '';
        $user = wp_get_current_user();
        $contact_default = ($user && $user->exists()) ? $user->user_email : '';

        ob_start();
        ?>
        <section class="rl-community-reviews" id="community-reviews">
            <div class="rl-community-inner">
                <div class="rl-community-head">
                    <div>
                        <div class="rl-community-kicker"><i></i> COMMUNITY REVIEWS</div>
                        <h2>WHAT OUR<br><span>COMMUNITY SAYS.</span></h2>
                    </div>
                    <p>Real feedback from people using RaffleLB. Reviews shown here are added only after they have been approved for publication.</p>
                </div>

                <?php if ($reviews): ?>
                    <div class="rl-review-grid">
                        <?php foreach ($reviews as $review):
                            $rating = max(1, min(5, absint(get_post_meta($review->ID, '_rafflelb_review_rating', true) ?: 5)));
                            $raffle = (string) get_post_meta($review->ID, '_rafflelb_review_raffle', true);
                            $name = trim(get_the_title($review));
                            $initial = $name !== '' ? strtoupper(substr($name, 0, 1)) : 'R';
                            $review_text = wp_strip_all_tags($review->post_content);
                            ?>
                            <article class="rl-review-card">
                                <div class="rl-review-top">
                                    <span class="rl-review-stars" aria-label="<?php echo esc_attr($rating); ?> out of 5 stars"><?php echo esc_html(str_repeat('★', $rating)); ?></span>
                                    <span class="rl-review-quote">“</span>
                                </div>
                                <p><?php echo esc_html($review_text); ?></p>
                                <div class="rl-review-person">
                                    <span class="rl-review-avatar"><?php echo esc_html($initial); ?></span>
                                    <span>
                                        <strong><?php echo esc_html($name ?: 'RaffleLB Customer'); ?></strong>
                                        <small><?php echo esc_html($raffle ?: 'RAFFLELB COMMUNITY'); ?></small>
                                    </span>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="rl-review-empty">
                        <span class="rl-review-empty-mark">“</span>
                        <div>
                            <strong>Community reviews are coming.</strong>
                            <p>Verified feedback will appear here as real customer reviews are added.</p>
                        </div>
                    </div>
                <?php endif; ?>
<div class="rl-review-submit-wrap">
                    <div class="rl-review-submit-copy"><span>SHARE YOUR EXPERIENCE</span><h3>USED RAFFLELB?<br><b>LEAVE A REVIEW.</b></h3><p>Your feedback helps new members understand the RaffleLB experience. Reviews are checked before they are published.</p></div>
                    <div class="rl-review-form-card">
                        <?php if ($review_status === 'success'): ?><div class="rl-review-message success"><strong>THANK YOU.</strong><span>Your review has been submitted for approval.</span></div><?php elseif ($review_status === 'missing'): ?><div class="rl-review-message error"><strong>ALMOST THERE.</strong><span>Please enter your name, rating and review.</span></div><?php elseif ($review_status === 'rate'): ?><div class="rl-review-message error"><strong>REVIEW ALREADY SENT.</strong><span>Please wait a moment before submitting again.</span></div><?php elseif ($review_status === 'error' || $review_status === 'security'): ?><div class="rl-review-message error"><strong>COULDN'T SEND.</strong><span>Please refresh and try again.</span></div><?php endif; ?>
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                            <input type="hidden" name="action" value="rafflelb_submit_review"><?php wp_nonce_field('rafflelb_submit_review','rafflelb_review_nonce'); ?>
                            <div class="rl-hp-field"><input type="text" name="review_website" tabindex="-1" autocomplete="off"></div>
                            <div class="rl-review-form-row"><div class="rl-review-field"><label>YOUR NAME <em>*</em></label><input name="review_name" type="text" required maxlength="100" placeholder="Your name"></div><div class="rl-review-field"><label>YOUR RATING <em>*</em></label><div class="rl-review-rating" role="radiogroup" aria-label="Your rating"><input type="radio" name="review_rating" id="rl-rating-5" value="5" required><label for="rl-rating-5" title="5 out of 5">★<span>5</span></label><input type="radio" name="review_rating" id="rl-rating-4" value="4"><label for="rl-rating-4" title="4 out of 5">★<span>4</span></label><input type="radio" name="review_rating" id="rl-rating-3" value="3"><label for="rl-rating-3" title="3 out of 5">★<span>3</span></label><input type="radio" name="review_rating" id="rl-rating-2" value="2"><label for="rl-rating-2" title="2 out of 5">★<span>2</span></label><input type="radio" name="review_rating" id="rl-rating-1" value="1"><label for="rl-rating-1" title="1 out of 5">★<span>1</span></label></div></div></div>
                            <div class="rl-review-field"><label>ORDER NUMBER <small>OPTIONAL</small></label><input name="review_order_number" type="text" maxlength="60" placeholder="e.g. 1542"></div>
                            <div class="rl-review-field"><label>YOUR REVIEW <em>*</em></label><textarea name="review_text" rows="4" required maxlength="900" placeholder="Tell us about your RaffleLB experience..."></textarea></div>
                            <button class="rl-review-submit" type="submit">SUBMIT REVIEW <span>→</span></button><p class="rl-review-note">Reviews are submitted for approval and are not published automatically.</p>
                        </form>
                    </div>
                </div>
            </div>
        </section>

        <section class="rl-request-section" id="request-a-raffle">
            <div class="rl-request-inner">
                <div class="rl-request-copy">
                    <div class="rl-request-kicker"><i></i> YOU CHOOSE WHAT'S NEXT</div>
                    <h2>WHAT SHOULD WE<br><span>RAFFLE NEXT?</span></h2>
                    <p>Have something in mind? Tell us what you want to see on RaffleLB. Popular requests help us decide which prizes and products to source next.</p>

                    <div class="rl-request-points">
                        <span><b>01</b> Request any item, brand or experience.</span>
                        <span><b>02</b> We track what the community wants most.</span>
                        <span><b>03</b> Popular ideas can become future raffles.</span>
                    </div>
                </div>

                <div class="rl-request-card">
                    <?php if ($status === 'success'): ?>
                        <div class="rl-request-message success"><strong>REQUEST RECEIVED.</strong><span>Thanks — your idea has been added to our request list.</span></div>
                    <?php elseif ($status === 'missing'): ?>
                        <div class="rl-request-message error"><strong>ALMOST THERE.</strong><span>Please enter the item you want and a way for us to contact you.</span></div>
                    <?php elseif ($status === 'rate'): ?>
                        <div class="rl-request-message error"><strong>REQUEST ALREADY SENT.</strong><span>Please wait a moment before submitting another item.</span></div>
                    <?php elseif ($status === 'error' || $status === 'security'): ?>
                        <div class="rl-request-message error"><strong>COULDN'T SEND.</strong><span>Please refresh the page and try again.</span></div>
                    <?php endif; ?>

                    <form class="rl-request-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <input type="hidden" name="action" value="rafflelb_submit_raffle_request">
                        <?php wp_nonce_field('rafflelb_submit_raffle_request', 'rafflelb_request_nonce'); ?>

                        <div class="rl-hp-field" aria-hidden="true">
                            <label>Website<input type="text" name="website" tabindex="-1" autocomplete="off"></label>
                        </div>

                        <div class="rl-request-field rl-request-field-wide">
                            <label for="rl-request-item">ITEM YOU WANT <em>*</em></label>
                            <input id="rl-request-item" name="item" type="text" required maxlength="160" placeholder="e.g. iPhone 17 Pro, Dior Sauvage...">
                        </div>

                        <div class="rl-request-row">
                            <div class="rl-request-field">
                                <label for="rl-request-brand">BRAND</label>
                                <input id="rl-request-brand" name="brand" type="text" maxlength="100" placeholder="Brand name">
                            </div>
                            <div class="rl-request-field">
                                <label for="rl-request-category">CATEGORY</label>
                                <select id="rl-request-category" name="category">
                                    <option value="">Choose category</option>
                                    <option>Electronics</option>
                                    <option>Perfumes</option>
                                    <option>Cosmetics</option>
                                    <option>Experiences</option>
                                    <option>Vouchers & Gift Cards</option>
                                    <option>Other</option>
                                </select>
                            </div>
                        </div>

                        <div class="rl-request-field rl-request-field-wide">
                            <label for="rl-request-link">PRODUCT LINK <small>OPTIONAL</small></label>
                            <input id="rl-request-link" name="product_link" type="url" placeholder="https://...">
                        </div>

                        <div class="rl-request-field rl-request-field-wide">
                            <label for="rl-request-contact">EMAIL OR PHONE <em>*</em></label>
                            <input id="rl-request-contact" name="contact" type="text" required maxlength="160" value="<?php echo esc_attr($contact_default); ?>" placeholder="So we can update you if it goes live">
                        </div>

                        <div class="rl-request-field rl-request-field-wide">
                            <label for="rl-request-notes">ANYTHING ELSE? <small>OPTIONAL</small></label>
                            <textarea id="rl-request-notes" name="notes" rows="3" maxlength="700" placeholder="Size, model, edition, value, color..."></textarea>
                        </div>

                        <button type="submit" class="rl-request-submit">REQUEST THIS RAFFLE <span>→</span></button>
                        <p class="rl-request-privacy">Your request helps us understand community demand. Submitting a request does not guarantee the item will be raffled.</p>
                    </form>
                </div>
            </div>
        </section>

        <style>
        .rl-community-reviews,.rl-request-section{position:relative;width:100vw!important;max-width:none!important;margin-left:calc(50% - 50vw)!important;margin-right:calc(50% - 50vw)!important;box-sizing:border-box;font-family:Inter,"Segoe UI",Arial,sans-serif}.rl-community-reviews *,.rl-request-section *{box-sizing:border-box}.rl-community-reviews{padding:92px 24px;background:#080a08;color:#fff;overflow:hidden}.rl-community-reviews:before{content:"";position:absolute;width:520px;height:520px;border-radius:50%;right:-220px;top:-250px;background:radial-gradient(circle,rgba(202,255,22,.10),rgba(202,255,22,0) 68%);pointer-events:none}.rl-community-inner{position:relative;z-index:1;max-width:1220px;margin:0 auto}.rl-community-head{display:grid;grid-template-columns:1.08fr .92fr;gap:70px;align-items:end;margin-bottom:42px}.rl-community-kicker,.rl-request-kicker{display:flex;align-items:center;gap:9px;margin-bottom:18px;font-size:10px;font-weight:900;letter-spacing:1.8px}.rl-community-kicker{color:#caff16}.rl-community-kicker i,.rl-request-kicker i{width:7px;height:7px;border-radius:50%;background:#caff16;box-shadow:0 0 15px rgba(202,255,22,.35)}.rl-community-head h2,.rl-request-copy h2{margin:0;font-size:clamp(48px,6vw,76px);line-height:.92;letter-spacing:-4px;font-weight:900}.rl-community-head h2{color:#fff}.rl-community-head h2 span{color:#caff16}.rl-request-copy h2 span{display:inline-block;color:#caff16;background:#0a0c0a;padding:5px 12px 7px;border-radius:8px;line-height:.95}.rl-community-head>p{max-width:500px;margin:0 0 5px;color:#a8aea3;font-size:15px;line-height:1.75;font-weight:500}.rl-review-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:18px}.rl-review-card{min-height:300px;padding:28px;border:1px solid rgba(255,255,255,.11);border-radius:20px;background:linear-gradient(145deg,#111411,#0b0d0b);display:flex;flex-direction:column;box-shadow:0 20px 55px rgba(0,0,0,.25)}.rl-review-top{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:28px}.rl-review-stars{color:#caff16;font-size:13px;letter-spacing:3px}.rl-review-quote{color:#caff16;font-size:50px;line-height:.65;font-family:Georgia,serif}.rl-review-card>p{flex:1;margin:0 0 30px;color:#f4f5f1;font-size:16px;line-height:1.75;font-weight:550}.rl-review-person{display:flex;align-items:center;gap:13px;padding-top:18px;border-top:1px solid rgba(255,255,255,.09)}.rl-review-avatar{display:flex;width:42px;height:42px;border-radius:50%;align-items:center;justify-content:center;background:#caff16;color:#080908;font-weight:900}.rl-review-person strong,.rl-review-person small{display:block}.rl-review-person strong{color:#fff;font-size:13px}.rl-review-person small{margin-top:4px;color:#777f74;font-size:8px;font-weight:900;letter-spacing:1px}.rl-review-empty{display:flex;align-items:center;gap:28px;min-height:170px;padding:30px 36px;border:1px solid rgba(255,255,255,.11);border-radius:20px;background:#0d100d}.rl-review-empty-mark{color:#caff16;font-size:78px;line-height:.6;font-family:Georgia,serif}.rl-review-empty strong{display:block;margin-bottom:6px;color:#fff;font-size:20px}.rl-review-empty p{margin:0;color:#92998e;font-size:13px}.rl-request-section{padding:92px 24px;background:#f1f0eb;color:#0b0b0b}.rl-request-inner{max-width:1220px;margin:0 auto;display:grid;grid-template-columns:.9fr 1.1fr;gap:78px;align-items:start}.rl-request-kicker{color:#151615}.rl-request-copy h2{color:#0b0b0b}.rl-request-copy>p{max-width:520px;margin:27px 0 35px;color:#50524d;font-size:15px;line-height:1.8}.rl-request-points{display:grid;gap:0;border-top:1px solid #d0cec5}.rl-request-points span{display:flex;align-items:center;gap:16px;padding:17px 0;border-bottom:1px solid #d0cec5;color:#32332f;font-size:12px;font-weight:750}.rl-request-points b{color:#8d9087;font-size:9px;letter-spacing:1px}.rl-request-card{padding:30px;border-radius:22px;background:#0a0c0a;box-shadow:0 24px 55px rgba(0,0,0,.14)}.rl-request-form{margin:0}.rl-request-row{display:grid;grid-template-columns:1fr 1fr;gap:14px}.rl-request-field{margin-bottom:15px}.rl-request-field label{display:block;margin:0 0 8px;color:#c7ccc3!important;font-size:9px!important;font-weight:900!important;letter-spacing:1.15px!important}.rl-request-field label em{color:#caff16;font-style:normal}.rl-request-field label small{color:#6e756b;font-size:7px}.rl-request-field input,.rl-request-field select,.rl-request-field textarea{width:100%!important;min-height:50px!important;margin:0!important;padding:13px 15px!important;border:1px solid rgba(255,255,255,.12)!important;border-radius:11px!important;outline:0!important;background:#101310!important;color:#fff!important;-webkit-text-fill-color:#fff!important;font:600 13px/1.4 Inter,"Segoe UI",Arial,sans-serif!important;box-shadow:none!important}.rl-request-field textarea{min-height:88px!important;resize:vertical}.rl-request-field input::placeholder,.rl-request-field textarea::placeholder{color:#697066!important;opacity:1!important}.rl-request-field select option{background:#101310;color:#fff}.rl-request-field input:focus,.rl-request-field select:focus,.rl-request-field textarea:focus{border-color:#caff16!important;box-shadow:0 0 0 2px rgba(202,255,22,.08)!important}.rl-request-submit{display:flex!important;align-items:center!important;justify-content:space-between!important;width:100%!important;min-height:56px!important;margin-top:5px!important;padding:0 20px!important;border:0!important;border-radius:11px!important;background:#caff16!important;color:#080908!important;-webkit-text-fill-color:#080908!important;font-size:11px!important;font-weight:900!important;letter-spacing:.8px!important;cursor:pointer!important}.rl-request-submit span{font-size:19px}.rl-request-privacy{margin:13px 2px 0!important;color:#70766d!important;font-size:9px!important;line-height:1.55!important}.rl-request-message{display:flex;flex-direction:column;gap:4px;margin-bottom:17px;padding:14px 16px;border-radius:11px}.rl-request-message.success{background:rgba(202,255,22,.11);border:1px solid rgba(202,255,22,.3)}.rl-request-message.error{background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.13)}.rl-request-message strong{color:#fff;font-size:10px;letter-spacing:.8px}.rl-request-message span{color:#afb5ab;font-size:11px}.rl-hp-field{position:absolute!important;left:-9999px!important;width:1px!important;height:1px!important;overflow:hidden!important}
        @media(max-width:900px){.rl-community-head,.rl-request-inner{grid-template-columns:1fr;gap:28px}.rl-review-grid{grid-template-columns:1fr 1fr}.rl-request-copy>p{max-width:700px}.rl-request-inner{gap:42px}}
        @media(max-width:767px){.rl-community-reviews,.rl-request-section{padding:70px 18px}.rl-review-form-row{grid-template-columns:1fr}.rl-review-form-card{padding:18px 15px}.rl-community-head h2,.rl-request-copy h2{font-size:47px;letter-spacing:-2.6px}.rl-community-head>p{font-size:13px}.rl-review-grid{grid-template-columns:1fr}.rl-review-card{min-height:260px;padding:23px}.rl-review-empty{align-items:flex-start;padding:26px 22px}.rl-review-empty-mark{font-size:58px}.rl-request-card{padding:20px 17px;border-radius:18px}.rl-request-row{grid-template-columns:1fr;gap:0}.rl-request-points span{font-size:11px}.rl-request-field input,.rl-request-field select,.rl-request-field textarea{font-size:16px!important}}
        .rl-review-submit-wrap{display:grid;grid-template-columns:.82fr 1.18fr;gap:54px;align-items:start;margin-top:30px;padding-top:34px;border-top:1px solid rgba(255,255,255,.09)}.rl-review-submit-copy>span{display:block;margin-bottom:12px;color:#caff16;font-size:9px;font-weight:900;letter-spacing:1.5px}.rl-review-submit-copy h3{margin:0;color:#fff;font-size:34px;line-height:1.03;letter-spacing:-1.7px;font-weight:900}.rl-review-submit-copy h3 b{color:#caff16}.rl-review-submit-copy p{max-width:430px;margin:18px 0 0;color:#92998e;font-size:13px;line-height:1.7}.rl-review-form-card{padding:24px;border:1px solid rgba(255,255,255,.11);border-radius:18px;background:#0d100d}.rl-review-form-row{display:grid;grid-template-columns:1fr 1fr;gap:14px}.rl-review-field{margin-bottom:15px}.rl-review-field label{display:block;margin:0 0 8px;color:#c7ccc3!important;font-size:9px!important;font-weight:900!important;letter-spacing:1.15px!important}.rl-review-field label em{color:#caff16;font-style:normal}.rl-review-field label small{color:#6e756b;font-size:7px}.rl-review-field input,.rl-review-field select,.rl-review-field textarea{width:100%!important;margin:0!important;padding:13px 15px!important;border:1px solid rgba(255,255,255,.12)!important;border-radius:11px!important;outline:0!important;background:#101310!important;color:#fff!important;box-shadow:none!important}.rl-review-field input,.rl-review-field select{min-height:50px!important}.rl-review-field textarea{min-height:118px!important;resize:vertical}.rl-review-field select option{background:#fff!important;color:#111!important;-webkit-text-fill-color:#111!important;opacity:1!important}.rl-review-rating{display:flex!important;flex-direction:row-reverse!important;justify-content:flex-end!important;gap:8px!important;min-height:50px!important;align-items:center!important;padding:8px 12px!important;border:1px solid rgba(255,255,255,.12)!important;border-radius:11px!important;background:#101310!important}.rl-review-rating input{position:absolute!important;opacity:0!important;pointer-events:none!important;width:1px!important;height:1px!important}.rl-review-rating label{display:flex!important;align-items:center!important;justify-content:center!important;gap:4px!important;width:42px!important;height:32px!important;margin:0!important;border:1px solid rgba(255,255,255,.12)!important;border-radius:8px!important;background:#0b0e0b!important;color:#8a9187!important;-webkit-text-fill-color:#8a9187!important;font-size:18px!important;font-weight:900!important;letter-spacing:0!important;cursor:pointer!important;transition:.18s ease!important}.rl-review-rating label span{font-size:9px!important;color:inherit!important;-webkit-text-fill-color:inherit!important}.rl-review-rating label:hover,.rl-review-rating label:hover~label,.rl-review-rating input:checked~label{border-color:#caff16!important;background:rgba(202,255,22,.09)!important;color:#caff16!important;-webkit-text-fill-color:#caff16!important}.rl-review-submit{display:flex!important;width:100%!important;min-height:52px!important;align-items:center!important;justify-content:space-between!important;padding:0 18px!important;border:0!important;border-radius:11px!important;background:#caff16!important;color:#090a09!important;font-size:11px!important;font-weight:900!important}.rl-review-note{margin:10px 0 0!important;color:#747b71!important;font-size:9px!important}.rl-review-message{display:flex;flex-direction:column;gap:4px;margin-bottom:16px;padding:13px 15px;border-radius:10px;font-size:11px}.rl-review-message.success{background:rgba(202,255,22,.09);border:1px solid rgba(202,255,22,.28);color:#eaffb2}.rl-review-message.error{background:rgba(255,100,100,.08);border:1px solid rgba(255,100,100,.22);color:#ffd0d0}.rl-hp-field{position:absolute!important;left:-9999px!important;width:1px!important;height:1px!important;overflow:hidden!important}@media(max-width:990px){.rl-review-submit-wrap{grid-template-columns:1fr;gap:28px}}@media(max-width:767px){.rl-review-form-row{grid-template-columns:1fr}.rl-review-form-card{padding:18px 15px}.rl-review-submit-copy h3{font-size:30px}}
</style>
        <?php
        return ob_get_clean();
    }

    public static function hero_artwork() {
    if (is_admin()) return;
    $art = plugins_url('assets/hero-reference-scene.webp', __FILE__);
    ?>
    <script>
    document.querySelectorAll('.rl-home-reference .rl-home-c-hero-art').forEach(function(img) {
        img.src = <?php echo wp_json_encode($art); ?>;
    });
    </script>
    <?php
}
}
