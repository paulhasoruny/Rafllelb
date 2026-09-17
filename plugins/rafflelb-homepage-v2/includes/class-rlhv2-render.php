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
        <div class="rl-hv2">
            <?php
            echo self::hero();
            echo self::trust_strip();
            echo self::featured_products();
            echo self::shop_categories();
            echo self::points_section();
            echo self::featured_selections();
            echo self::how_it_works();
            echo self::verified_results();
            echo self::customer_reviews();
            ?>
        </div>
        <?php
        return ob_get_clean();
    }

    private static function currency() {
        return function_exists('get_woocommerce_currency_symbol')
            ? html_entity_decode(get_woocommerce_currency_symbol(), ENT_QUOTES | ENT_HTML5, 'UTF-8')
            : '$';
    }

    private static function money($amount) {
        return self::currency() . number_format((float) $amount, 2, '.', ',');
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
                <?php if ($items): ?>
                <div class="rl-hv2-hero-media" aria-hidden="true">
                    <div class="rl-hv2-hero-glow"></div>
                    <div class="rl-hv2-hero-grid">
                        <?php foreach ($items as $item): ?>
                            <div class="rl-hv2-hero-item">
                                <img src="<?php echo esc_url($item['image']); ?>" alt="<?php echo esc_attr($item['title']); ?>" loading="eager">
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </section>
        <?php
        return ob_get_clean();
    }

    private static function trust_strip() {
        $items = [
            ['label' => 'Authentic products', 'sub' => 'Original products only'],
            ['label' => 'Fast delivery', 'sub' => 'Across Lebanon'],
            ['label' => 'Secure payments', 'sub' => 'Multiple payment options'],
            ['label' => 'Customer support', 'sub' => "We're here to help"],
            ['label' => 'Transparent selections', 'sub' => 'Fair and verifiable results'],
        ];
        ob_start();
        ?>
        <section class="rl-hv2-trust" aria-label="Why shop with RaffleLB">
            <div class="rl-hv2-trust-inner">
                <?php foreach ($items as $item): ?>
                    <div class="rl-hv2-trust-item">
                        <strong><?php echo esc_html($item['label']); ?></strong>
                        <span><?php echo esc_html($item['sub']); ?></span>
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
                                <?php if ($product['category']): ?>
                                    <span class="rl-hv2-product-cat"><?php echo esc_html($product['category']); ?></span>
                                <?php endif; ?>
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
                            <div class="rl-hv2-category-noimg" aria-hidden="true"><?php echo esc_html(function_exists('mb_substr') ? mb_substr($category['name'], 0, 1) : substr($category['name'], 0, 1)); ?></div>
                        <?php endif; ?>
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
        ob_start();
        ?>
        <section class="rl-hv2-points" aria-labelledby="rl-hv2-points-title">
            <div class="rl-hv2-points-inner">
                <div class="rl-hv2-points-copy">
                    <h2 id="rl-hv2-points-title">RAFFLELB <span>POINTS</span></h2>
                    <p>SHOP MORE. EARN MORE.</p>
                    <div class="rl-hv2-points-steps">
                        <div><strong>Shop</strong><span>Purchase authentic products from our store.</span></div>
                        <div><strong>Refer</strong><span>Invite friends and both earn bonus points.</span></div>
                        <div><strong>Earn</strong><span>Collect RaffleLB Points with every purchase.</span></div>
                        <div><strong>Redeem</strong><span>Use your points for discounts and more.</span></div>
                    </div>
                    <a class="rl-hv2-btn rl-hv2-btn-secondary" href="<?php echo esc_url($account_url); ?>">Learn More</a>
                </div>
                <?php if ($points_html): ?>
                    <div class="rl-hv2-points-balance"><?php echo $points_html; /* trusted plugin-rendered markup */ ?></div>
                <?php endif; ?>
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
                <div class="rl-hv2-empty">No Selections are currently open. Check back soon.</div>
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
                                            <span><?php echo esc_html($selection['claimed']); ?> of <?php echo esc_html($selection['total']); ?> entries</span>
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
                <div class="rl-hv2-how-step">
                    <span class="rl-hv2-how-num">1</span>
                    <strong>Choose a Selection</strong>
                    <span>Browse open Selections and pick the one you want to join.</span>
                </div>
                <div class="rl-hv2-how-step">
                    <span class="rl-hv2-how-num">2</span>
                    <strong>Participate</strong>
                    <span>Secure your entry at the listed entry price.</span>
                </div>
                <div class="rl-hv2-how-step">
                    <span class="rl-hv2-how-num">3</span>
                    <strong>Verified Selection Result</strong>
                    <span>Results are recorded and published for full transparency.</span>
                </div>
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
                <div class="rl-hv2-empty">No verified results yet. Check back once the first Selection completes.</div>
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
                <div class="rl-hv2-empty">No customer reviews yet.</div>
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
