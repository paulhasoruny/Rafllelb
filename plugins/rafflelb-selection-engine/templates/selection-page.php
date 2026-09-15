<?php
if (!defined('ABSPATH')) {
    exit;
}

get_header();

if (get_query_var('rafflelb_selection_hub')) {
    echo RaffleLB_Selection_Renderer::hub();
} else {
    $slug = sanitize_title((string) get_query_var('rafflelb_selection'));
    $product = RaffleLB_Selection_Adapter::product_by_slug($slug);
    echo RaffleLB_Selection_Renderer::raffle($product);
}

get_footer();

