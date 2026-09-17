<?php
/**
 * Markup for the Home V2 shortcode.
 *
 * Structure and density are a deliberate recreation of the client-approved
 * Home V2 preview image (composition, spacing, card density, icon
 * treatment, CTA sizing) rather than an original layout — see the plugin
 * README for the section-by-section mapping back to real data sources.
 *
 * All classes are prefixed rl-hv2- to stay isolated from the live
 * homepage's CSS (rlfp318/rlsc316/rlp270/etc.) and from theme/page-builder
 * styles.
 */

if (!defined('ABSPATH')) {
    exit;
}

final class RLHV2_Render {

    public static function page() {
        ob_start();
        ?>
        <div class="rl-hv2"><?php
            echo self::hero();
            echo self::trust_strip();
            echo self::featured_products();
            echo self::shop_categories();
            echo self::points_section();
            echo self::featured_selections();
            echo self::how_it_works();
            echo self::verified_results();
            echo self::customer_reviews();
        ?></div>
        <?php
        // Trimmed: avoids wpautop wrapping the shortcode output in a stray
        // leading/trailing paragraph, which is what produces extra blank
        // space above/below the section on some themes.
        return trim(ob_get_clean());
    }

    private static function currency() {
        return function_exists('get_woocommerce_currency_symbol')
            ? html_entity_decode(get_woocommerce_currency_symbol(), ENT_QUOTES | ENT_HTML5, 'UTF-8')
            : '$';
    }

    private static function money($amount) {
        return self::currency() . number_format((float) $amount, 2, '.', ',');
    }

    private static function initials($name) {
        $name = trim((string) $name);
        if ($name === '') {
            return 'R';
        }
        $parts = preg_split('/\s+/', $name);
        $letters = mb_substr($parts[0], 0, 1);
        if (count($parts) > 1) {
            $letters .= mb_substr(end($parts), 0, 1);
        }
        return strtoupper($letters);
    }

    /**
     * Small inline line-style SVG icons, lime stroke, no external library —
     * matches the live homepage's inline-SVG icon convention.
     */
    private static function icon($name) {
        $icons = [
            'shield'  => '<path d="M12 3 4 6v6c0 5 3.4 8.4 8 9 4.6-.6 8-4 8-9V6l-8-3Z"/><path d="m8.5 12 2.3 2.3L15.5 9.5"/>',
            'truck'   => '<path d="M3 7h10v9H3z"/><path d="M13 10h4l3 3v3h-7z"/><circle cx="7" cy="18" r="1.6"/><circle cx="17" cy="18" r="1.6"/>',
            'card'    => '<rect x="3" y="6" width="18" height="12" rx="2"/><path d="M3 10h18"/><path d="M7 14h4"/>',
            'shuffle' => '<path d="m17 3 4 4-4 4"/><path d="M3 11h6l3-4h9"/><path d="m17 21 4-4-4-4"/><path d="M3 13h6l3 4h9"/>',
            'headset' => '<path d="M4 13v-1a8 8 0 0 1 16 0v1"/><rect x="3" y="13" width="4" height="6" rx="1.5"/><rect x="17" y="13" width="4" height="6" rx="1.5"/><path d="M20 19v1a3 3 0 0 1-3 3h-3"/>',
            'check'   => '<circle cx="12" cy="12" r="9"/><path d="m8 12 3 3 5-6"/>',
            'bag'     => '<path d="M4 8h16l-1.4 11.2a2 2 0 0 1-2 1.8H7.4a2 2 0 0 1-2-1.8L4 8Z"/><path d="M8 8V6a4 4 0 0 1 8 0v2"/>',
            'cart'    => '<circle cx="9" cy="20" r="1.4"/><circle cx="17" cy="20" r="1.4"/><path d="M2 3h2l2.4 12.2a2 2 0 0 0 2 1.6h8.2a2 2 0 0 0 2-1.6L21 7H6"/>',
            'trophy'  => '<path d="M8 4h8v5a4 4 0 0 1-8 0V4Z"/><path d="M6 4H4v2a3 3 0 0 0 3 3"/><path d="M18 4h2v2a3 3 0 0 1-3 3"/><path d="M10 13v3H8l-1 4h10l-1-4h-2v-3"/>',
            'award'   => '<circle cx="12" cy="9" r="5.5"/><path d="m8.5 13.5-1.7 6.5L12 18l5.2 2-1.7-6.5"/>',
            'tag'     => '<path d="M12 3h6a2 2 0 0 1 2 2v6l-9.5 9.5a2 2 0 0 1-2.8 0l-5.2-5.2a2 2 0 0 1 0-2.8L12 3Z"/><circle cx="16" cy="8" r="1.4"/>',
            'people'  => '<circle cx="8" cy="8" r="3"/><circle cx="17" cy="9" r="2.6"/><path d="M3 20c0-3 2.5-5 5-5s5 2 5 5"/><path d="M14.5 20c.2-2.2 1.8-3.7 3.5-4"/>',
            'refer'   => '<circle cx="8" cy="8" r="3"/><circle cx="17" cy="9" r="2.6"/><path d="M3 20c0-3 2.5-5 5-5s5 2 5 5"/><path d="M14.5 20c.2-2.2 1.8-3.7 3.5-4"/>',
            'coins'   => '<ellipse cx="9" cy="7" rx="6" ry="3"/><path d="M3 7v10c0 1.7 2.7 3 6 3s6-1.3 6-3V7"/><path d="M15 9.4c2.9.3 5 1.5 5 2.9v7c0 1.7-2.7 3-6 3-1.5 0-2.9-.3-4-.8"/>',
            'redeem'  => '<rect x="3" y="9" width="18" height="12" rx="2"/><path d="M3 13h18"/><path d="M12 9v12"/><path d="M12 9c-1.8 0-4-1-4-3.2A2.3 2.3 0 0 1 10.3 3C12 3 12 6 12 9Z"/><path d="M12 9c1.8 0 4-1 4-3.2A2.3 2.3 0 0 0 13.7 3C12 3 12 6 12 9Z"/>',
            'ticket'  => '<path d="M4 8a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v2a2 2 0 0 0 0 4v2a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-2a2 2 0 0 0 0-4Z"/><path d="M10 6v12" stroke-dasharray="2 3"/>',
            'result'  => '<path d="M12 2 20 6v5c0 5.2-3.4 8.9-8 10-4.6-1.1-8-4.8-8-10V6l8-4Z"/><path d="m9 12 2 2 4-4"/>',
            'chat'    => '<path d="M4 5h16v11H8l-4 4V5Z"/>',
            'arrow'   => '<path d="M5 12h13"/><path d="m13 6 6 6-6 6"/>',
        ];
        if (!isset($icons[$name])) {
            return '';
        }
        return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $icons[$name] . '</svg>';
    }

    /* ---------------------------------------------------------------- */

    private static function hero() {
        $scene_url = RLHV2_Data::hero_scene_url();
        $items     = $scene_url ? [] : RLHV2_Data::hero_items(3);
        $shop_url  = RLHV2_Data::shop_url();
        $benefits  = [
            ['icon' => 'award',  'label' => 'Premium Brands'],
            ['icon' => 'coins',  'label' => 'RaffleLB Points'],
            ['icon' => 'tag',    'label' => 'Featured Selections'],
            ['icon' => 'people', 'label' => 'Trusted by Thousands'],
        ];

        ob_start();
        ?>
        <section class="rl-hv2-hero" aria-labelledby="rl-hv2-hero-title">
            <div class="rl-hv2-hero-inner">
                <div class="rl-hv2-hero-copy">
                    <p class="rl-hv2-eyebrow">Authentic products. Real rewards.</p>
                    <h1 id="rl-hv2-hero-title">SHOP. EARN.<br><span>GET REWARDED.</span></h1>
                    <p class="rl-hv2-hero-sub">Discover premium products at great prices, earn RaffleLB Points with every purchase, and explore exclusive Selections for a chance to win incredible prizes.</p>
                    <div class="rl-hv2-hero-cta">
                        <a class="rl-hv2-btn rl-hv2-btn-primary" href="<?php echo esc_url($shop_url); ?>"><?php echo self::icon('bag'); ?> Shop Products <span aria-hidden="true">&rarr;</span></a>
                        <a class="rl-hv2-btn rl-hv2-btn-secondary" href="#rl-hv2-selections"><?php echo self::icon('trophy'); ?> Explore Selections <span aria-hidden="true">&rarr;</span></a>
                    </div>
                    <ul class="rl-hv2-hero-benefits">
                        <?php foreach ($benefits as $benefit): ?>
                            <li><?php echo self::icon($benefit['icon']); ?><span><?php echo esc_html($benefit['label']); ?></span></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <div class="rl-hv2-hero-media" aria-hidden="true">
                    <?php if ($scene_url): ?>
                        <img class="rl-hv2-hero-scene" src="<?php echo esc_url($scene_url); ?>" alt="" loading="eager">
                        <span class="rl-hv2-hero-tagline">Premium Products<br>Bigger Possibilities</span>
                    <?php elseif ($items): ?>
                        <div class="rl-hv2-hero-glow"></div>
                        <div class="rl-hv2-hero-grid rl-hv2-hero-grid-<?php echo (int) count($items); ?>">
                            <?php foreach ($items as $i => $item): ?>
                                <div class="rl-hv2-hero-item rl-hv2-hero-item-<?php echo (int) ($i + 1); ?>">
                                    <img src="<?php echo esc_url($item['image']); ?>" alt="<?php echo esc_attr($item['title']); ?>" loading="eager">
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="rl-hv2-hero-glow"></div>
                        <div class="rl-hv2-hero-placeholder"><span><?php echo self::icon('bag'); ?></span></div>
                    <?php endif; ?>
                </div>
            </div>
        </section>
        <?php
        return ob_get_clean();
    }

    private static function trust_strip() {
        $items = [
            ['icon' => 'shield',  'label' => 'Authentic Products',    'sub' => '100% genuine & original'],
            ['icon' => 'truck',   'label' => 'Fast Delivery',         'sub' => 'Across Lebanon'],
            ['icon' => 'card',    'label' => 'Secure Payments',       'sub' => 'Safe & encrypted'],
            ['icon' => 'shuffle', 'label' => 'Transparent Selections', 'sub' => 'Fair & verifiable results'],
            ['icon' => 'headset', 'label' => 'Customer Support',      'sub' => "We're here to help"],
        ];
        ob_start();
        ?>
        <section class="rl-hv2-trust" aria-label="Why shop with RaffleLB">
            <div class="rl-hv2-trust-inner">
                <?php foreach ($items as $item): ?>
                    <div class="rl-hv2-trust-item">
                        <span class="rl-hv2-trust-icon"><?php echo self::icon($item['icon']); ?></span>
                        <span class="rl-hv2-trust-copy">
                            <strong><?php echo esc_html($item['label']); ?></strong>
                            <span><?php echo esc_html($item['sub']); ?></span>
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
        <?php
        return ob_get_clean();
    }

    private static function featured_products() {
        $products = RLHV2_Data::featured_products(6);
        ob_start();
        ?>
        <section class="rl-hv2-products" aria-labelledby="rl-hv2-products-title">
            <div class="rl-hv2-section-head">
                <div>
                    <h2 id="rl-hv2-products-title">FEATURED <span>PRODUCTS</span></h2>
                    <p>Top products at great prices. Shop now and earn RaffleLB Points.</p>
                </div>
                <a class="rl-hv2-view-all" href="<?php echo esc_url(RLHV2_Data::shop_url()); ?>">View All Products <span aria-hidden="true">&rarr;</span></a>
            </div>

            <?php if (!$products): ?>
                <div class="rl-hv2-empty">No products are available right now.</div>
            <?php else: ?>
                <div class="rl-hv2-products-grid">
                    <?php foreach ($products as $product): ?>
                        <article class="rl-hv2-product-card">
                            <a class="rl-hv2-product-media" href="<?php echo esc_url($product['url']); ?>" aria-label="<?php echo esc_attr($product['title']); ?>">
                                <?php if ($product['image_id']): ?>
                                    <?php echo wp_get_attachment_image($product['image_id'], 'woocommerce_thumbnail', false, [
                                        'class'   => 'rl-hv2-product-img',
                                        'loading' => 'lazy',
                                        'alt'     => $product['title'],
                                    ]); ?>
                                <?php else: ?>
                                    <div class="rl-hv2-product-noimg">PRODUCT</div>
                                <?php endif; ?>
                            </a>
                            <div class="rl-hv2-product-body">
                                <h3><a href="<?php echo esc_url($product['url']); ?>"><?php echo esc_html($product['title']); ?></a></h3>
                                <div class="rl-hv2-product-price"><?php echo esc_html(self::money($product['price'])); ?></div>
                                <a class="rl-hv2-buy-now" href="<?php echo esc_url($product['url']); ?>">
                                    <?php echo self::icon('bag'); ?> Buy Now
                                </a>
                                <?php /* Slot always renders so Buy Now aligns at the same
                                         row across every card, whether or not this product
                                         also has a Selection (a real ecommerce-grid detail,
                                         not decoration). */ ?>
                                <span class="rl-hv2-selection-slot">
                                    <?php if ($product['has_selection']): ?>
                                        <a class="rl-hv2-selection-link" href="<?php echo esc_url($product['selection_url']); ?>"><?php echo self::icon('ticket'); ?> Selection available</a>
                                    <?php endif; ?>
                                </span>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
        <?php
        return ob_get_clean();
    }

    private static function shop_categories() {
        $categories = RLHV2_Data::shop_categories(6);
        if (!$categories) {
            return '';
        }
        ob_start();
        ?>
        <section class="rl-hv2-categories" aria-labelledby="rl-hv2-categories-title">
            <div class="rl-hv2-section-head">
                <div>
                    <h2 id="rl-hv2-categories-title">SHOP BY <span>CATEGORY</span></h2>
                    <p>Explore our wide range of products.</p>
                </div>
                <a class="rl-hv2-view-all" href="<?php echo esc_url(RLHV2_Data::shop_url()); ?>">View All Categories <span aria-hidden="true">&rarr;</span></a>
            </div>
            <div class="rl-hv2-categories-grid">
                <?php foreach ($categories as $category): ?>
                    <a class="rl-hv2-category-card" href="<?php echo esc_url($category['url']); ?>">
                        <?php if ($category['image']): ?>
                            <img class="rl-hv2-category-img" src="<?php echo esc_url($category['image']); ?>" alt="<?php echo esc_attr($category['name']); ?>" loading="lazy">
                        <?php else: ?>
                            <div class="rl-hv2-category-noimg" aria-hidden="true"><?php echo self::icon('bag'); ?></div>
                        <?php endif; ?>
                        <span class="rl-hv2-category-shade"></span>
                        <span class="rl-hv2-category-content">
                            <span class="rl-hv2-category-name"><?php echo esc_html($category['name']); ?></span>
                            <span class="rl-hv2-category-shop">Shop now <span aria-hidden="true">&rarr;</span></span>
                        </span>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>
        <?php
        return ob_get_clean();
    }

    private static function points_section() {
        $points_html = RLHV2_Data::points_header_html();
        $account_url = RLHV2_Data::account_points_url();
        $icon_url    = RLHV2_Data::points_icon_url();
        $steps = [
            ['icon' => 'cart',   'label' => 'Shop',   'sub' => 'Earn points with every purchase'],
            ['icon' => 'refer',  'label' => 'Refer',  'sub' => 'Invite friends and earn together'],
            ['icon' => 'coins',  'label' => 'Earn',   'sub' => 'Complete activities and get rewarded'],
            ['icon' => 'redeem', 'label' => 'Redeem', 'sub' => 'Use your points for discounts & more'],
        ];
        ob_start();
        ?>
        <section class="rl-hv2-points" aria-labelledby="rl-hv2-points-title">
            <div class="rl-hv2-points-inner">
                <div class="rl-hv2-points-copy">
                    <p class="rl-hv2-eyebrow"><?php echo self::icon('coins'); ?> RaffleLB Points</p>
                    <h2 id="rl-hv2-points-title">SHOP MORE.<br><span>EARN MORE.</span></h2>
                    <p>Turn every purchase into rewards. Earn RaffleLB Points, refer your friends, and redeem them for discounts, exclusive perks, and more.</p>
                    <?php if ($points_html): ?>
                        <div class="rl-hv2-points-balance"><?php echo $points_html; /* trusted plugin-rendered markup */ ?></div>
                    <?php endif; ?>
                    <a class="rl-hv2-btn rl-hv2-btn-primary" href="<?php echo esc_url($account_url); ?>">Learn More <span aria-hidden="true">&rarr;</span></a>
                </div>
                <div class="rl-hv2-points-steps">
                    <?php foreach ($steps as $i => $step): ?>
                        <?php if ($i > 0): ?><span class="rl-hv2-points-arrow" aria-hidden="true"><?php echo self::icon('arrow'); ?></span><?php endif; ?>
                        <div class="rl-hv2-points-step">
                            <span class="rl-hv2-points-step-icon"><?php echo self::icon($step['icon']); ?></span>
                            <strong><?php echo esc_html($step['label']); ?></strong>
                            <span><?php echo esc_html($step['sub']); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="rl-hv2-points-art" aria-hidden="true">
                    <?php if ($icon_url): ?>
                        <img src="<?php echo esc_url($icon_url); ?>" alt="">
                    <?php endif; ?>
                    <span class="rl-hv2-points-tagline">More Ways<br>to be Rewarded</span>
                </div>
            </div>
        </section>
        <?php
        return ob_get_clean();
    }

    private static function featured_selections() {
        $selections = RLHV2_Data::featured_selections(4);
        ob_start();
        ?>
        <section class="rl-hv2-selections" id="rl-hv2-selections" aria-labelledby="rl-hv2-selections-title">
            <div class="rl-hv2-section-head">
                <div>
                    <h2 id="rl-hv2-selections-title">FEATURED <span>SELECTIONS</span></h2>
                    <p>Exclusive Selections with amazing prizes.</p>
                </div>
                <a class="rl-hv2-view-all" href="<?php echo esc_url(RLHV2_Data::shop_url()); ?>">View All Selections <span aria-hidden="true">&rarr;</span></a>
            </div>

            <?php if (!$selections): ?>
                <div class="rl-hv2-empty">
                    <span class="rl-hv2-empty-icon"><?php echo self::icon('ticket'); ?></span>
                    <strong>No Selections are open right now</strong>
                    <span>Check back soon &mdash; new Selections are added regularly.</span>
                </div>
            <?php else: ?>
                <div class="rl-hv2-selections-grid">
                    <?php foreach ($selections as $selection): ?>
                        <a class="rl-hv2-selection-card" href="<?php echo esc_url($selection['selection_url']); ?>">
                            <span class="rl-hv2-selection-media">
                                <?php if ($selection['image']): ?>
                                    <img src="<?php echo esc_url($selection['image']); ?>" alt="<?php echo esc_attr($selection['name']); ?>" loading="lazy">
                                <?php else: ?>
                                    <span class="rl-hv2-product-noimg">SELECTION</span>
                                <?php endif; ?>
                            </span>
                            <span class="rl-hv2-selection-body">
                                <span class="rl-hv2-selection-name"><?php echo esc_html($selection['name']); ?></span>
                                <span class="rl-hv2-selection-price"><?php echo esc_html(self::money($selection['entry_price'])); ?> <em>/ entry</em></span>
                                <span class="rl-hv2-selection-progress">
                                    <span class="rl-hv2-selection-progress-track"><span style="width:<?php echo esc_attr(min(100, max(0, $selection['percent_filled']))); ?>%"></span></span>
                                </span>
                                <?php if ($selection['total'] > 0): ?>
                                    <span class="rl-hv2-selection-meta"><?php echo esc_html($selection['claimed']); ?> claimed &middot; <?php echo esc_html($selection['remaining']); ?> left</span>
                                <?php endif; ?>
                            </span>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
        <?php
        return ob_get_clean();
    }

    private static function how_it_works() {
        $steps = [
            ['icon' => 'cart',   'num' => '1', 'label' => 'Choose a Selection', 'sub' => "Pick a product you'd like to win."],
            ['icon' => 'ticket', 'num' => '2', 'label' => 'Enter',              'sub' => 'Pay the entry fee to get your entries.'],
            ['icon' => 'trophy', 'num' => '3', 'label' => 'We Select a Winner', 'sub' => 'Results are published and verified.'],
        ];
        ob_start();
        ?>
        <section class="rl-hv2-how" aria-labelledby="rl-hv2-how-title">
            <div class="rl-hv2-section-head">
                <div>
                    <h2 id="rl-hv2-how-title">HOW SELECTIONS <span>WORK</span></h2>
                    <p>A simple and transparent process.</p>
                </div>
                <a class="rl-hv2-view-all" href="<?php echo esc_url(RLHV2_Data::shop_url()); ?>">Learn More <span aria-hidden="true">&rarr;</span></a>
            </div>
            <div class="rl-hv2-how-steps">
                <?php foreach ($steps as $i => $step): ?>
                    <?php if ($i > 0): ?><span class="rl-hv2-how-arrow" aria-hidden="true"><?php echo self::icon('arrow'); ?></span><?php endif; ?>
                    <div class="rl-hv2-how-step">
                        <span class="rl-hv2-how-num"><?php echo esc_html($step['num']); ?></span>
                        <span class="rl-hv2-how-icon"><?php echo self::icon($step['icon']); ?></span>
                        <span class="rl-hv2-how-copy">
                            <strong><?php echo esc_html($step['label']); ?></strong>
                            <span><?php echo esc_html($step['sub']); ?></span>
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
        <?php
        return ob_get_clean();
    }

    private static function verified_results() {
        $results = RLHV2_Data::verified_results(6);
        $count   = RLHV2_Data::verified_results_count();
        $badges  = RLHV2_Data::verified_results_badges();
        ob_start();
        ?>
        <section class="rl-hv2-results" aria-labelledby="rl-hv2-results-title">
            <div class="rl-hv2-section-head">
                <div>
                    <h2 id="rl-hv2-results-title">VERIFIED <span>RESULTS</span></h2>
                    <p>Transparent. Fair. Always published.</p>
                </div>
                <a class="rl-hv2-view-all" href="<?php echo esc_url(home_url('/selection-engine/')); ?>">View Recent Results <span aria-hidden="true">&rarr;</span></a>
            </div>

            <?php if ($count > 0): ?>
                <div class="rl-hv2-results-stats">
                    <div class="rl-hv2-results-stat">
                        <span class="rl-hv2-results-stat-icon"><?php echo self::icon('result'); ?></span>
                        <span><strong><?php echo esc_html(number_format_i18n($count)); ?>+</strong><span>Published Results</span></span>
                    </div>
                    <?php foreach ($badges as $badge): ?>
                        <div class="rl-hv2-results-stat">
                            <span class="rl-hv2-results-stat-icon"><?php echo self::icon('check'); ?></span>
                            <span><strong><?php echo esc_html($badge['label']); ?></strong><span><?php echo esc_html($badge['sub']); ?></span></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if (!$results): ?>
                <div class="rl-hv2-empty">
                    <span class="rl-hv2-empty-icon"><?php echo self::icon('result'); ?></span>
                    <strong>No verified results yet</strong>
                    <span>Once the first Selection completes, its result will be published here.</span>
                </div>
            <?php else: ?>
                <div class="rl-hv2-results-grid">
                    <?php foreach ($results as $result): ?>
                        <article class="rl-hv2-result-card">
                            <?php if (!empty($result['image'])): ?>
                                <img src="<?php echo esc_url($result['image']); ?>" alt="<?php echo esc_attr($result['name']); ?>" loading="lazy">
                            <?php endif; ?>
                            <div class="rl-hv2-result-body">
                                <h3><?php echo esc_html($result['name']); ?></h3>
                                <p><?php echo esc_html($result['winner']); ?> &middot; <?php echo esc_html($result['entry']); ?></p>
                                <span class="rl-hv2-result-date"><?php echo esc_html($result['selected_display']); ?></span>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
        <?php
        return ob_get_clean();
    }

    private static function customer_reviews() {
        $reviews = RLHV2_Data::customer_reviews(3);
        ob_start();
        ?>
        <section class="rl-hv2-reviews" aria-labelledby="rl-hv2-reviews-title">
            <div class="rl-hv2-section-head">
                <div>
                    <h2 id="rl-hv2-reviews-title">CUSTOMER <span>REVIEWS</span></h2>
                    <p>Real customers. Real experiences.</p>
                </div>
            </div>

            <?php if (!$reviews): ?>
                <div class="rl-hv2-empty">
                    <span class="rl-hv2-empty-icon"><?php echo self::icon('chat'); ?></span>
                    <strong>Be the first to share your experience</strong>
                    <span>No customer reviews have been published yet.</span>
                    <a class="rl-hv2-btn rl-hv2-btn-secondary" href="<?php echo esc_url(RLHV2_Data::share_review_url()); ?>">Share Your Experience</a>
                </div>
            <?php else: ?>
                <div class="rl-hv2-reviews-grid">
                    <?php foreach ($reviews as $review): ?>
                        <article class="rl-hv2-review-card">
                            <div class="rl-hv2-review-top">
                                <span class="rl-hv2-review-avatar"><?php echo esc_html(self::initials($review['author'])); ?></span>
                                <div class="rl-hv2-review-stars" aria-label="<?php echo esc_attr($review['rating']); ?> out of 5 stars">
                                    <?php echo str_repeat('&#9733;', $review['rating']) . str_repeat('&#9734;', 5 - $review['rating']); ?>
                                </div>
                            </div>
                            <p>&ldquo;<?php echo esc_html($review['text']); ?>&rdquo;</p>
                            <div class="rl-hv2-review-author">
                                <?php echo esc_html($review['author']); ?>
                                <?php if ($review['verified']): ?>
                                    <span class="rl-hv2-review-verified"><?php echo self::icon('check'); ?> Verified Customer</span>
                                <?php endif; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
        <?php
        return ob_get_clean();
    }
}
