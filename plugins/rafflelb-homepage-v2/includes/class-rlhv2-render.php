<?php
/**
 * Markup for the Home V2 shortcode. All classes are prefixed rl-hv2- to stay
 * isolated from the live homepage's CSS (rlfp318/rlsc316/rlp270/etc.) and
 * from any theme/page-builder styles.
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

    /**
     * Small inline line-style SVG icons, lime stroke, no external library —
     * matches the live homepage's inline-SVG icon convention.
     */
    private static function icon($name) {
        $icons = [
            'shield'   => '<path d="M12 3 4 6v6c0 5 3.4 8.4 8 9 4.6-.6 8-4 8-9V6l-8-3Z"/><path d="m8.5 12 2.3 2.3L15.5 9.5"/>',
            'truck'    => '<path d="M3 7h10v9H3z"/><path d="M13 10h4l3 3v3h-7z"/><circle cx="7" cy="18" r="1.6"/><circle cx="17" cy="18" r="1.6"/>',
            'card'     => '<rect x="3" y="6" width="18" height="12" rx="2"/><path d="M3 10h18"/><path d="M7 14h4"/>',
            'headset'  => '<path d="M4 13v-1a8 8 0 0 1 16 0v1"/><rect x="3" y="13" width="4" height="6" rx="1.5"/><rect x="17" y="13" width="4" height="6" rx="1.5"/><path d="M20 19v1a3 3 0 0 1-3 3h-3"/>',
            'check'    => '<circle cx="12" cy="12" r="9"/><path d="m8 12 3 3 5-6"/>',
            'shop'     => '<path d="M4 8h16l-1.4 11.2a2 2 0 0 1-2 1.8H7.4a2 2 0 0 1-2-1.8L4 8Z"/><path d="M8 8V6a4 4 0 0 1 8 0v2"/>',
            'refer'    => '<circle cx="8" cy="8" r="3"/><circle cx="17" cy="9" r="2.6"/><path d="M3 20c0-3 2.5-5 5-5s5 2 5 5"/><path d="M14.5 20c.2-2.2 1.8-3.7 3.5-4"/>',
            'coins'    => '<ellipse cx="9" cy="7" rx="6" ry="3"/><path d="M3 7v10c0 1.7 2.7 3 6 3s6-1.3 6-3V7"/><path d="M15 9.4c2.9.3 5 1.5 5 2.9v7c0 1.7-2.7 3-6 3-1.5 0-2.9-.3-4-.8"/>',
            'redeem'   => '<rect x="3" y="9" width="18" height="12" rx="2"/><path d="M3 13h18"/><path d="M12 9v12"/><path d="M12 9c-1.8 0-4-1-4-3.2A2.3 2.3 0 0 1 10.3 3C12 3 12 6 12 9Z"/><path d="M12 9c1.8 0 4-1 4-3.2A2.3 2.3 0 0 0 13.7 3C12 3 12 6 12 9Z"/>',
            'target'   => '<circle cx="12" cy="12" r="8.5"/><circle cx="12" cy="12" r="5"/><circle cx="12" cy="12" r="1.5"/>',
            'ticket'   => '<path d="M4 8a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v2a2 2 0 0 0 0 4v2a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-2a2 2 0 0 0 0-4Z"/><path d="M10 6v12" stroke-dasharray="2 3"/>',
            'result'   => '<path d="M12 2 20 6v5c0 5.2-3.4 8.9-8 10-4.6-1.1-8-4.8-8-10V6l8-4Z"/><path d="m9 12 2 2 4-4"/>',
            'chat'     => '<path d="M4 5h16v11H8l-4 4V5Z"/>',
        ];
        if (!isset($icons[$name])) {
            return '';
        }
        return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $icons[$name] . '</svg>';
    }

    /* ---------------------------------------------------------------- */

    private static function hero() {
        $items = RLHV2_Data::hero_items(3);
        $shop_url = RLHV2_Data::shop_url();

        ob_start();
        ?>
        <section class="rl-hv2-hero" aria-labelledby="rl-hv2-hero-title">
            <div class="rl-hv2-hero-inner">
                <div class="rl-hv2-hero-copy">
                    <p class="rl-hv2-eyebrow">AUTHENTIC PRODUCTS &middot; REWARDS &middot; SELECTIONS</p>
                    <h1 id="rl-hv2-hero-title">SHOP. EARN.<br><span>GET REWARDED.</span></h1>
                    <p class="rl-hv2-hero-sub">Shop authentic products, earn RaffleLB Points, and explore featured Selections.</p>
                    <div class="rl-hv2-hero-cta">
                        <a class="rl-hv2-btn rl-hv2-btn-primary" href="<?php echo esc_url($shop_url); ?>">SHOP PRODUCTS</a>
                        <a class="rl-hv2-btn rl-hv2-btn-secondary" href="#rl-hv2-selections">EXPLORE SELECTIONS</a>
                    </div>
                </div>
                <div class="rl-hv2-hero-media" aria-hidden="true">
                    <div class="rl-hv2-hero-glow"></div>
                    <?php if ($items): ?>
                        <div class="rl-hv2-hero-grid rl-hv2-hero-grid-<?php echo (int) count($items); ?>">
                            <?php foreach ($items as $i => $item): ?>
                                <div class="rl-hv2-hero-item rl-hv2-hero-item-<?php echo (int) ($i + 1); ?>">
                                    <img src="<?php echo esc_url($item['image']); ?>" alt="<?php echo esc_attr($item['title']); ?>" loading="eager">
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="rl-hv2-hero-placeholder">
                            <span><?php echo self::icon('shop'); ?></span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </section>
        <?php
        return ob_get_clean();
    }

    private static function trust_strip() {
        $items = [
            ['icon' => 'shield',  'label' => 'Authentic products',    'sub' => 'Original products only'],
            ['icon' => 'truck',   'label' => 'Fast delivery',         'sub' => 'Across Lebanon'],
            ['icon' => 'card',    'label' => 'Secure payments',       'sub' => 'Multiple payment options'],
            ['icon' => 'headset', 'label' => 'Customer support',      'sub' => "We're here to help"],
            ['icon' => 'check',   'label' => 'Transparent selections', 'sub' => 'Fair and verifiable results'],
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
                    <p>Shop our most popular products at great prices.</p>
                </div>
                <a class="rl-hv2-view-all" href="<?php echo esc_url(RLHV2_Data::shop_url()); ?>">View all products &rarr;</a>
            </div>

            <?php if (!$products): ?>
                <div class="rl-hv2-empty">No products are available right now.</div>
            <?php else: ?>
                <div class="rl-hv2-products-grid">
                    <?php foreach ($products as $product): ?>
                        <article class="rl-hv2-product-card">
                            <a class="rl-hv2-product-media" href="<?php echo esc_url($product['url']); ?>" aria-label="<?php echo esc_attr($product['title']); ?>">
                                <?php if ($product['category']): ?>
                                    <span class="rl-hv2-product-cat"><?php echo esc_html($product['category']); ?></span>
                                <?php endif; ?>
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
                                    Buy Now <span aria-hidden="true">&rarr;</span>
                                </a>
                                <?php if ($product['has_selection']): ?>
                                    <a class="rl-hv2-selection-link" href="<?php echo esc_url($product['selection_url']); ?>">Selection available</a>
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
                    <p>Shop our wide range of product categories.</p>
                </div>
            </div>
            <div class="rl-hv2-categories-grid">
                <?php foreach ($categories as $category): ?>
                    <a class="rl-hv2-category-card" href="<?php echo esc_url($category['url']); ?>">
                        <?php if ($category['image']): ?>
                            <img class="rl-hv2-category-img" src="<?php echo esc_url($category['image']); ?>" alt="<?php echo esc_attr($category['name']); ?>" loading="lazy">
                        <?php else: ?>
                            <div class="rl-hv2-category-noimg" aria-hidden="true"><?php echo self::icon('shop'); ?></div>
                        <?php endif; ?>
                        <span class="rl-hv2-category-shade"></span>
                        <span class="rl-hv2-category-name"><?php echo esc_html($category['name']); ?></span>
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
        $steps = [
            ['icon' => 'shop',   'label' => 'Shop',   'sub' => 'Purchase authentic products from our store.'],
            ['icon' => 'refer',  'label' => 'Refer',  'sub' => 'Invite friends and both earn bonus points.'],
            ['icon' => 'coins',  'label' => 'Earn',   'sub' => 'Collect RaffleLB Points with every purchase.'],
            ['icon' => 'redeem', 'label' => 'Redeem', 'sub' => 'Use your points for discounts and more.'],
        ];
        ob_start();
        ?>
        <section class="rl-hv2-points" aria-labelledby="rl-hv2-points-title">
            <div class="rl-hv2-points-inner">
                <div class="rl-hv2-points-copy">
                    <p class="rl-hv2-eyebrow">RAFFLELB POINTS</p>
                    <h2 id="rl-hv2-points-title">SHOP MORE. <span>EARN MORE.</span></h2>
                    <?php if ($points_html): ?>
                        <div class="rl-hv2-points-balance"><?php echo $points_html; /* trusted plugin-rendered markup */ ?></div>
                    <?php endif; ?>
                    <a class="rl-hv2-btn rl-hv2-btn-secondary" href="<?php echo esc_url($account_url); ?>">Learn More</a>
                </div>
                <div class="rl-hv2-points-steps">
                    <?php foreach ($steps as $step): ?>
                        <div class="rl-hv2-points-step">
                            <span class="rl-hv2-points-step-icon"><?php echo self::icon($step['icon']); ?></span>
                            <strong><?php echo esc_html($step['label']); ?></strong>
                            <span><?php echo esc_html($step['sub']); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
        <?php
        return ob_get_clean();
    }

    private static function featured_selections() {
        $selections = RLHV2_Data::featured_selections(3);
        ob_start();
        ?>
        <section class="rl-hv2-selections" id="rl-hv2-selections" aria-labelledby="rl-hv2-selections-title">
            <div class="rl-hv2-section-head">
                <div>
                    <h2 id="rl-hv2-selections-title">FEATURED <span>SELECTIONS</span></h2>
                    <p>Explore our widest product of Selections.</p>
                </div>
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
                        <article class="rl-hv2-selection-card">
                            <a class="rl-hv2-selection-media" href="<?php echo esc_url($selection['selection_url']); ?>" aria-label="<?php echo esc_attr($selection['name']); ?>">
                                <?php if ($selection['image']): ?>
                                    <img src="<?php echo esc_url($selection['image']); ?>" alt="<?php echo esc_attr($selection['name']); ?>" loading="lazy">
                                <?php else: ?>
                                    <div class="rl-hv2-product-noimg">SELECTION</div>
                                <?php endif; ?>
                                <span class="rl-hv2-selection-badge">SELECTION</span>
                            </a>
                            <div class="rl-hv2-selection-body">
                                <h3><a href="<?php echo esc_url($selection['selection_url']); ?>"><?php echo esc_html($selection['name']); ?></a></h3>
                                <p class="rl-hv2-selection-price"><?php echo esc_html(self::money($selection['entry_price'])); ?> <span>/ entry</span></p>
                                <div class="rl-hv2-selection-progress">
                                    <div class="rl-hv2-selection-progress-track">
                                        <span style="width:<?php echo esc_attr(min(100, max(0, $selection['percent_filled']))); ?>%"></span>
                                    </div>
                                    <div class="rl-hv2-selection-progress-meta">
                                        <span><?php echo esc_html($selection['percent_filled']); ?>% filled</span>
                                        <?php if ($selection['total'] > 0): ?>
                                            <span><?php echo esc_html($selection['claimed']); ?> claimed &middot; <?php echo esc_html($selection['remaining']); ?> remaining</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <a class="rl-hv2-btn rl-hv2-btn-primary rl-hv2-btn-block" href="<?php echo esc_url($selection['selection_url']); ?>">View Selection</a>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
        <?php
        return ob_get_clean();
    }

    private static function how_it_works() {
        $steps = [
            ['icon' => 'target', 'num' => '1', 'label' => 'Choose a Selection', 'sub' => 'Browse open Selections and pick the one you want to join.'],
            ['icon' => 'ticket', 'num' => '2', 'label' => 'Participate',        'sub' => 'Secure your entry at the listed entry price.'],
            ['icon' => 'result', 'num' => '3', 'label' => 'Verified Selection Result', 'sub' => 'Results are recorded and published for full transparency.'],
        ];
        ob_start();
        ?>
        <section class="rl-hv2-how" aria-labelledby="rl-hv2-how-title">
            <div class="rl-hv2-section-head">
                <div>
                    <h2 id="rl-hv2-how-title">HOW <span>SELECTIONS</span> WORK</h2>
                    <p>A simple and transparent process.</p>
                </div>
            </div>
            <div class="rl-hv2-how-steps">
                <?php foreach ($steps as $step): ?>
                    <div class="rl-hv2-how-step">
                        <span class="rl-hv2-how-icon"><?php echo self::icon($step['icon']); ?></span>
                        <span class="rl-hv2-how-num"><?php echo esc_html($step['num']); ?></span>
                        <strong><?php echo esc_html($step['label']); ?></strong>
                        <span><?php echo esc_html($step['sub']); ?></span>
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
        ob_start();
        ?>
        <section class="rl-hv2-results" aria-labelledby="rl-hv2-results-title">
            <div class="rl-hv2-section-head">
                <div>
                    <h2 id="rl-hv2-results-title">VERIFIED <span>RESULTS</span></h2>
                    <p>All Selection results are recorded and published for full transparency.</p>
                </div>
            </div>

            <?php if ($count > 0): ?>
                <div class="rl-hv2-results-stats">
                    <div><strong><?php echo esc_html(number_format_i18n($count)); ?></strong><span>Completed selections</span></div>
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
                    <p>What our customers are saying about RaffleLB.</p>
                </div>
            </div>

            <?php if (!$reviews): ?>
                <div class="rl-hv2-empty">
                    <span class="rl-hv2-empty-icon"><?php echo self::icon('chat'); ?></span>
                    <strong>No customer reviews yet</strong>
                    <span>Be the first to share your experience with RaffleLB.</span>
                    <a class="rl-hv2-btn rl-hv2-btn-secondary" href="<?php echo esc_url(RLHV2_Data::share_review_url()); ?>">Share Your Experience</a>
                </div>
            <?php else: ?>
                <div class="rl-hv2-reviews-grid">
                    <?php foreach ($reviews as $review): ?>
                        <article class="rl-hv2-review-card">
                            <div class="rl-hv2-review-stars" aria-label="<?php echo esc_attr($review['rating']); ?> out of 5 stars">
                                <?php echo str_repeat('&#9733;', $review['rating']) . str_repeat('&#9734;', 5 - $review['rating']); ?>
                            </div>
                            <p><?php echo esc_html($review['text']); ?></p>
                            <span class="rl-hv2-review-author">&mdash; <?php echo esc_html($review['author']); ?></span>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
        <?php
        return ob_get_clean();
    }
}
