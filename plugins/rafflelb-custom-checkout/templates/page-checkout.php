<?php
defined('ABSPATH') || exit;

get_header();

if (!function_exists('WC') || !WC()->cart) {
    echo '<main class="rlcc-app"><div class="rlcc-wrap"><p>Checkout is temporarily unavailable.</p></div></main>';
    get_footer();
    exit;
}
?>
<main id="primary" class="rlcc-app" role="main">
    <?php
    // Use the WooCommerce checkout shortcode as the engine, but the actual
    // form/review templates are supplied by this plugin.
    echo do_shortcode('[woocommerce_checkout]');
    ?>
</main>
<?php
get_footer();
