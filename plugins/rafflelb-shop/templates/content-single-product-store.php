<?php
/** Dedicated RaffleLB Store Only single-product template. */
defined('ABSPATH') || exit;

global $product;

do_action('woocommerce_before_single_product');

if (post_password_required()) {
    echo get_the_password_form(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    return;
}

RaffleLB_Store_Only_Renderer::render($product);
do_action('woocommerce_after_single_product');
