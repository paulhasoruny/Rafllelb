<?php
/**
 * Plugin Name: RaffleLB Custom Checkout
 * Description: Native custom WooCommerce checkout template for RaffleLB. Keeps WooCommerce order/payment processing while replacing the checkout presentation.
 * Version: 4.8.13
 * Author: RaffleLB
 */
if ( ! defined( 'ABSPATH' ) ) exit;
define('RLCC_VERSION','4.8.13');
define('RLCC_PATH',plugin_dir_path(__FILE__));


/**
 * Checkout purchase mode.
 * - buy_now: every cart item is a direct retail purchase
 * - raffle_entry: every cart item is a raffle entry (or legacy raffle item)
 * - mixed: both modes are present
 */
function rlcc_get_checkout_mode() {
    if (!function_exists('WC') || !WC()->cart) return 'raffle_entry';

    $has_direct = false;
    $has_raffle = false;

    foreach (WC()->cart->get_cart() as $item) {
        $mode = isset($item['_rafflelb_purchase_mode'])
            ? sanitize_key($item['_rafflelb_purchase_mode'])
            : 'raffle_entry';

        if ($mode === 'buy_now') {
            $has_direct = true;
        } else {
            $has_raffle = true;
        }
    }

    if ($has_direct && $has_raffle) return 'mixed';
    if ($has_direct) return 'buy_now';
    return 'raffle_entry';
}

function rlcc_is_direct_checkout() {
    return rlcc_get_checkout_mode() === 'buy_now';
}



/**
 * True when a direct Buy Now cart contains only voucher/gift-card products.
 * These products are fulfilled digitally through the customer's WhatsApp
 * number and email address collected at checkout.
 */

function rlcc_voucher_category_slugs() {
    return [
        'vouchers-and-gift-cards',
        'voucher',
        'vouchers',
        'gift-card',
        'gift-cards',
        'gift-cards-vouchers',
    ];
}

function rlcc_item_is_voucher_or_gift_card($product) {
    if (!$product || !is_object($product) || !$product->exists()) return false;

    // Explicit per-product override (set from RaffleLB Raffle Manager's
    // Delivery Type field) takes priority over the category guess below.
    $override = get_post_meta($product->get_id(), '_rafflelb_item_type', true);
    if ($override === 'digital') return true;
    if ($override === 'tangible') return false;

    return has_term(rlcc_voucher_category_slugs(), 'product_cat', $product->get_id());
}

/**
 * True when a direct Buy Now cart contains at least one voucher/gift card.
 */
function rlcc_direct_cart_has_digital_voucher() {
    if (!function_exists('WC') || !WC()->cart) return false;
    if (rlcc_get_checkout_mode() !== 'buy_now' || WC()->cart->is_empty()) return false;

    foreach (WC()->cart->get_cart() as $item) {
        $product = isset($item['data']) && is_object($item['data']) ? $item['data'] : null;
        if (rlcc_item_is_voucher_or_gift_card($product)) return true;
    }
    return false;
}

/**
 * True when a direct Buy Now cart contains at least one tangible item.
 * We intentionally ignore WooCommerce's Virtual flag because raffle products
 * may be marked Virtual for the entry workflow while still representing a
 * physical Buy Now item.
 */
function rlcc_is_tangible_direct_purchase() {
    if (!function_exists('WC') || !WC()->cart) return false;
    if (rlcc_get_checkout_mode() !== 'buy_now' || WC()->cart->is_empty()) return false;

    foreach (WC()->cart->get_cart() as $item) {
        $product = isset($item['data']) && is_object($item['data']) ? $item['data'] : null;
        if (!$product || !$product->exists()) continue;

        if (!rlcc_item_is_voucher_or_gift_card($product)) {
            return true;
        }
    }

    return false;
}

/**
 * True only when every direct-purchase item is a voucher/gift card.
 */
function rlcc_is_digital_voucher_direct_purchase() {
    if (!function_exists('WC') || !WC()->cart) return false;
    if (rlcc_get_checkout_mode() !== 'buy_now' || WC()->cart->is_empty()) return false;

    $found = false;
    foreach (WC()->cart->get_cart() as $item) {
        $product = isset($item['data']) && is_object($item['data']) ? $item['data'] : null;
        if (!$product || !$product->exists()) return false;

        if (!rlcc_item_is_voucher_or_gift_card($product)) {
            return false;
        }
        $found = true;
    }

    return $found;
}

add_filter('woocommerce_available_payment_gateways', function($gateways){
    if (is_admin() && !defined('DOING_AJAX')) return $gateways;
    if (!function_exists('is_checkout') || !is_checkout()) return $gateways;

    // Delivery COD is permitted only for a tangible, Buy Direct-only cart.
    // Raffle and mixed carts are intentionally ineligible.
    if (isset($gateways['cod']) && !rlcc_is_tangible_direct_purchase()) {
        unset($gateways['cod']);
    }

    return $gateways;
}, 9998);

add_filter('woocommerce_checkout_fields', function($fields){
    if (!rlcc_is_tangible_direct_purchase()) return $fields;

    if (!isset($fields['billing'])) $fields['billing'] = [];

    $fields['billing']['billing_city'] = array_merge(
        $fields['billing']['billing_city'] ?? [],
        [
            'type'        => 'text',
            'label'       => 'Area / City',
            'placeholder' => 'e.g. Beirut, Achrafieh',
            'required'    => false,
            'priority'    => 65,
            'class'       => ['form-row-wide','rlcc-cod-address-field'],
        ]
    );

    $fields['billing']['billing_address_1'] = array_merge(
        $fields['billing']['billing_address_1'] ?? [],
        [
            'type'        => 'text',
            'label'       => 'Street / Building / Floor',
            'placeholder' => 'Street, building, floor, apartment',
            'required'    => false,
            'priority'    => 66,
            'class'       => ['form-row-wide','rlcc-cod-address-field'],
        ]
    );

    return $fields;
}, 9999);

add_action('woocommerce_after_checkout_validation', function($data, $errors){
    if (!rlcc_is_tangible_direct_purchase()) return;

    $payment_method = isset($_POST['payment_method']) ? sanitize_key(wp_unslash($_POST['payment_method'])) : '';
    if ($payment_method !== 'cod') return;

    $city = isset($_POST['billing_city']) ? trim(wp_unslash($_POST['billing_city'])) : '';
    $address = isset($_POST['billing_address_1']) ? trim(wp_unslash($_POST['billing_address_1'])) : '';

    if ($city === '') {
        $errors->add('billing_city_required', __('Please enter your delivery area / city.','woocommerce'));
    }
    if ($address === '') {
        $errors->add('billing_address_1_required', __('Please enter your street, building and floor for delivery.','woocommerce'));
    }
}, 9999, 2);



function rlcc_get_order_mode($order) {
    if (!$order || !is_a($order, 'WC_Order')) return 'raffle_entry';

    $has_direct = false;
    $has_raffle = false;

    foreach ($order->get_items() as $item) {
        $mode = sanitize_key((string) $item->get_meta('_rafflelb_purchase_mode', true));
        if ($mode === '') {
            $mode = sanitize_key((string) $item->get_meta('Purchase mode', true));
        }

        if ($mode === 'buy_now') $has_direct = true;
        else $has_raffle = true;
    }

    if ($has_direct && $has_raffle) return 'mixed';
    if ($has_direct) return 'buy_now';
    return 'raffle_entry';
}

function rlcc_order_fulfillment_types($order) {
    $physical = [];
    $digital = [];

    if (!$order || !is_a($order, 'WC_Order')) return compact('physical','digital');

    foreach ($order->get_items() as $item) {
        $mode = sanitize_key((string) $item->get_meta('_rafflelb_purchase_mode', true));
        if ($mode === '') $mode = sanitize_key((string) $item->get_meta('Purchase mode', true));
        if ($mode !== 'buy_now') continue;

        $product = $item->get_product();
        $name = $item->get_name();

        if ($product && function_exists('rlcc_item_is_voucher_or_gift_card') && rlcc_item_is_voucher_or_gift_card($product)) {
            $digital[] = $name;
        } else {
            $physical[] = $name;
        }
    }

    return compact('physical','digital');
}

add_action('woocommerce_before_thankyou', function($order_id){
    $order = wc_get_order($order_id);
    if (!$order) return;

    $mode = rlcc_get_order_mode($order);
    $types = rlcc_order_fulfillment_types($order);
    $has_physical = !empty($types['physical']);
    $has_digital = !empty($types['digital']);
    $is_raffle = ($mode === 'raffle_entry');

    $payment_title = $order->get_payment_method_title();
    $date = $order->get_date_created();
    $date_text = $date ? wc_format_datetime($date, 'F j, Y') : '';
    $phone = $order->get_billing_phone();
    $email = $order->get_billing_email();
    $city = $order->get_billing_city();
    $address = $order->get_billing_address_1();

    echo '<main class="rlcc-confirmation">';

    echo '<section class="rlcc-confirm-hero">';
    echo '<div class="rlcc-confirm-check">✓</div>';
    echo '<div class="rlcc-confirm-copy">';
    echo '<div class="rlcc-confirm-kicker">' . ($is_raffle ? 'ENTRY CONFIRMED' : 'ORDER CONFIRMED') . '</div>';
    echo '<h1>' . ($is_raffle ? 'Your raffle entry is in.' : 'Thank you for your purchase.') . '</h1>';
    echo '<p>' . ($is_raffle
        ? 'Your entry order has been received and will be processed according to its payment status.'
        : 'Your order has been received successfully and is now being processed.') . '</p>';
    echo '</div>';
    echo '</section>';

    echo '<section class="rlcc-confirm-meta">';
    echo '<div><span>ORDER NUMBER</span><strong>#' . esc_html($order->get_order_number()) . '</strong></div>';
    echo '<div><span>DATE</span><strong>' . esc_html($date_text) . '</strong></div>';
    echo '<div><span>PAYMENT</span><strong>' . esc_html($payment_title ?: '—') . '</strong></div>';
    echo '<div><span>TOTAL</span><strong>' . wp_kses_post($order->get_formatted_order_total()) . '</strong></div>';
    echo '</section>';

    if (!$is_raffle) {
        echo '<section class="rlcc-confirm-fulfillment">';

        if ($has_physical) {
            echo '<div class="rlcc-confirm-note rlcc-confirm-physical">';
            echo '<span class="rlcc-confirm-note-tag">PHYSICAL DELIVERY</span>';
            echo '<strong>Your physical item' . (count($types['physical']) > 1 ? 's are' : ' is') . ' being prepared for delivery.</strong>';
            if ($city || $address) {
                echo '<small>Delivery to: ' . esc_html(trim($city . ($city && $address ? ' — ' : '') . $address)) . '</small>';
            } else {
                echo '<small>We will use the delivery information provided with your order.</small>';
            }
            echo '</div>';
        }

        if ($has_digital) {
            echo '<div class="rlcc-confirm-note rlcc-confirm-digital">';
            echo '<span class="rlcc-confirm-note-tag">DIGITAL DELIVERY</span>';
            echo '<strong>Voucher and gift-card items are delivered digitally.</strong>';
            echo '<small>They will be sent to your WhatsApp number' . ($phone ? ' ' . esc_html($phone) : '') . ' and email' . ($email ? ' ' . esc_html($email) : '') . ' after order confirmation.</small>';
            echo '</div>';
        }

        if ($has_physical && $has_digital) {
            echo '<div class="rlcc-confirm-mixed">This is a mixed order. Physical and digital items will be fulfilled separately.</div>';
        }

        echo '</section>';
    } else {
        $entry_qty = 0;
        foreach ($order->get_items() as $item) {
            $entry_qty += max(1, (int) $item->get_quantity());
        }

        echo '<section class="rlcc-confirm-raffle-grid">';
        echo '<div class="rlcc-confirm-raffle rlcc-confirm-raffle-count">';
        echo '<span>ENTRIES PURCHASED</span>';
        echo '<strong>' . esc_html($entry_qty) . '</strong>';
        echo '<small>Your paid raffle entries will appear in My Raffles once the order is confirmed.</small>';
        echo '</div>';

        echo '<div class="rlcc-confirm-raffle rlcc-confirm-raffle-status">';
        echo '<span>ENTRY STATUS</span>';
        echo '<strong>' . esc_html(ucfirst($order->get_status())) . '</strong>';
        echo '<small>Entry assignment follows the payment status of this order.</small>';
        echo '</div>';
        echo '</section>';
    }

    echo '<section class="rlcc-confirm-items">';
    echo '<div class="rlcc-confirm-section-head"><span>' . ($is_raffle ? 'YOUR ENTRY' : 'YOUR ORDER') . '</span><strong>' . count($order->get_items()) . ' ITEM' . (count($order->get_items()) === 1 ? '' : 'S') . '</strong></div>';

    foreach ($order->get_items() as $item) {
        $product = $item->get_product();
        $image = $product ? $product->get_image('woocommerce_thumbnail', ['loading'=>'lazy']) : '';
        $line_total = $order->get_formatted_line_subtotal($item);

        $item_mode = sanitize_key((string) $item->get_meta('_rafflelb_purchase_mode', true));
        if ($item_mode === '') $item_mode = sanitize_key((string) $item->get_meta('Purchase mode', true));
        $direct = ($item_mode === 'buy_now');

        $delivery_label = '';
        if ($direct && $product && function_exists('rlcc_item_is_voucher_or_gift_card')) {
            $delivery_label = rlcc_item_is_voucher_or_gift_card($product) ? 'DIGITAL DELIVERY' : 'PHYSICAL DELIVERY';
        }

        echo '<article class="rlcc-confirm-item">';
        echo '<div class="rlcc-confirm-thumb">' . wp_kses_post($image) . '</div>';
        echo '<div class="rlcc-confirm-item-copy">';
        echo '<h3>' . esc_html($item->get_name()) . '</h3>';
        echo '<div class="rlcc-confirm-pills">';
        echo '<span>QTY ' . esc_html($item->get_quantity()) . '</span>';
        echo '<span>' . ($direct ? 'DIRECT PURCHASE' : 'PAID RAFFLE ENTRY') . '</span>';
        if ($delivery_label) echo '<span class="' . ($delivery_label === 'DIGITAL DELIVERY' ? 'is-digital' : '') . '">' . esc_html($delivery_label) . '</span>';
        echo '</div>';
        echo '</div>';
        echo '<div class="rlcc-confirm-price">' . wp_kses_post($line_total) . '</div>';
        echo '</article>';
    }

    echo '<div class="rlcc-confirm-total"><span>' . ($is_raffle ? 'ENTRY TOTAL' : 'ORDER TOTAL') . '</span><strong>' . wp_kses_post($order->get_formatted_order_total()) . '</strong></div>';

    if ($is_raffle) {
        echo '<div class="rlcc-raffle-confirm-help">';
        echo '<div><strong>WHAT HAPPENS NEXT?</strong><span>Once payment is confirmed, your raffle entries are assigned and can be viewed from My Raffles.</span></div>';
        echo '<div><strong>DRAW RESULT</strong><span>When the raffle closes and the draw is completed, the result will be recorded in your account.</span></div>';
        echo '</div>';
    }

    echo '</section>';

    echo '<section class="rlcc-confirm-contact">';
    echo '<div><span>EMAIL</span><strong>' . esc_html($email ?: '—') . '</strong></div>';
    echo '<div><span>PHONE / WHATSAPP</span><strong>' . esc_html($phone ?: '—') . '</strong></div>';
    if ($has_physical) {
        echo '<div><span>DELIVERY ADDRESS</span><strong>' . esc_html(trim($city . ($city && $address ? ', ' : '') . $address) ?: 'Provided with order') . '</strong></div>';
    }
    echo '</section>';

    if ($is_raffle) {
        echo '<div class="rlcc-confirm-actions"><a href="' . esc_url(wc_get_account_endpoint_url('rafflelb-entries')) . '">VIEW MY RAFFLES</a></div>';
    } else {
        echo '<div class="rlcc-confirm-actions"><a href="' . esc_url(wc_get_page_permalink('shop')) . '">CONTINUE SHOPPING</a></div>';
    }

    echo '</main>';
}, 5);

add_filter('woocommerce_order_button_text', function($text){
    if (!function_exists('is_checkout') || !is_checkout() || is_order_received_page()) return $text;

    $mode = rlcc_get_checkout_mode();
    if ($mode === 'buy_now') return 'COMPLETE PURCHASE';
    if ($mode === 'raffle_entry') return 'COMPLETE ENTRY';
    return 'PLACE ORDER';
}, 9999);

/**
 * RaffleLB checkout gateway order.
 * Whish first, Raffle Points second.
 */
add_filter('woocommerce_available_payment_gateways', function($gateways){
    if (is_admin() && !defined('DOING_AJAX')) return $gateways;
    if (!function_exists('is_checkout') || !is_checkout()) return $gateways;

    $ordered = [];
    foreach (['rafflelb_whish_manual', 'rafflelb_omt_pay_manual', 'rafflelb_points'] as $id) {
        if (isset($gateways[$id])) {
            $ordered[$id] = $gateways[$id];
            unset($gateways[$id]);
        }
    }
    foreach ($gateways as $id => $gateway) {
        $ordered[$id] = $gateway;
    }
    return $ordered;
}, 9999);


add_filter('body_class', function($c){
 if(function_exists('is_checkout') && is_checkout()) {
   if(is_order_received_page()) {
     $c[]='rlcc-thankyou';
   } else {
     $c[]='rlcc-v3';
     $mode = function_exists('rlcc_get_checkout_mode') ? rlcc_get_checkout_mode() : 'raffle_entry';
     $c[] = 'rlcc-mode-' . sanitize_html_class($mode);
     if(get_site_icon_url(192)) $c[]='rlcc-has-points-logo';
     $c[]='rlcc-has-whish-logo';
   }
 }
 return $c;
});

add_filter('woocommerce_locate_template', function($template,$template_name,$template_path){
 if(!function_exists('is_checkout') || !is_checkout() || is_order_received_page()) return $template;
 $map=[
  'checkout/form-checkout.php'=>RLCC_PATH.'templates/checkout/form-checkout.php',
  'checkout/review-order.php'=>RLCC_PATH.'templates/checkout/review-order.php',
 ];
 return isset($map[$template_name]) && file_exists($map[$template_name]) ? $map[$template_name] : $template;
},999,3);

add_action('wp_enqueue_scripts', function(){
 if(!function_exists('is_checkout') || !is_checkout()) return;
 wp_register_style('rlcc-v3',false,[],RLCC_VERSION); wp_enqueue_style('rlcc-v3');
 $points_logo = esc_url(get_site_icon_url(192));
 $whish_logo = esc_url(plugin_dir_url(__FILE__).'assets/whish.png');
 $omt_logo = esc_url(plugin_dir_url(__FILE__).'assets/omt-pay.png');
 $css=<<<'CSS'

:root{
	--rl-bg:#070907;
	--rl-card:#0e120e;
	--rl-card-2:#111611;
	--rl-line:#2b332b;
	--rl-line-2:#364035;
	--rl-lime:#baff00;
	--rl-text:#f7f8f5;
	--rl-muted:#9ea89b;
	--rl-purple:#7b4de6;
}
body.rlcc-v3,
body.rlcc-v3 .website-wrapper,
body.rlcc-v3 .main-page-wrapper,
body.rlcc-v3 .site-content,
body.rlcc-v3 .woocommerce{
	background:var(--rl-bg)!important;
	color:var(--rl-text)!important;
}
body.rlcc-v3 .main-page-wrapper{padding-top:0!important}
body.rlcc-v3 .rlcc-app{background:var(--rl-bg)!important;padding:42px 0 72px!important;min-height:70vh}
body.rlcc-v3 .rlcc-wrap{max-width:1180px!important;margin:0 auto!important;padding:0 18px!important}

/* Preserve normal WoodMart header/footer sizing */
body.rlcc-v3 .container,
body.rlcc-v3 .whb-header .container,
body.rlcc-v3 footer .container,
body.rlcc-v3 .footer-container .container{
	max-width:var(--wd-container-w)!important;
	width:100%!important;
	margin-left:auto!important;
	margin-right:auto!important;
	padding-left:15px!important;
	padding-right:15px!important;
}
body.rlcc-v3 .whb-header,
body.rlcc-v3 .whb-row,
body.rlcc-v3 .whb-general-header{background:#fff!important}

/* Hide legacy Draw Engine intro if present */
body.rlcc-v3 .rafflelb-checkout-intro{display:none!important}

/* Hero */
.rlcc-hero{
	position:relative;
	overflow:hidden;
	padding:30px 34px;
	margin-bottom:20px;
	border:1px solid var(--rl-line);
	border-radius:18px;
	background:
		radial-gradient(circle at 92% 8%,rgba(186,255,0,.08),transparent 30%),
		linear-gradient(135deg,#121712 0%,#0b0f0b 100%);
}
.rlcc-hero:after{
	content:"";
	position:absolute;
	left:0;right:0;top:0;height:2px;
	background:linear-gradient(90deg,var(--rl-lime),rgba(186,255,0,.15),transparent);
}
.rlcc-kicker{
	display:flex;align-items:center;gap:8px;
	color:var(--rl-lime);
	font-size:10px;font-weight:900;letter-spacing:.17em;
}
.rlcc-kicker:before{
	content:"";width:7px;height:7px;border-radius:50%;background:var(--rl-lime);
	box-shadow:0 0 15px rgba(186,255,0,.65);
}
.rlcc-hero h1{
	margin:8px 0 7px!important;
	color:#fff!important;
	font-size:36px!important;
	line-height:1.05!important;
	letter-spacing:-.5px!important;
}
.rlcc-hero p{margin:0;color:#b7c0b4;font-size:13px}
.rlcc-trust{
	display:flex;gap:18px;flex-wrap:wrap;
	margin-top:17px;padding-top:15px;border-top:1px solid #283028;
	color:#dce2d9;font-size:10px;letter-spacing:.08em;
}
.rlcc-trust span:before{content:"•";color:var(--rl-lime);margin-right:7px}

/* Coupon */
.rlcc-coupon{margin:0 0 18px}
.rlcc-coupon .woocommerce-form-coupon-toggle{margin:0!important}
.rlcc-coupon .woocommerce-info{padding:0!important;border:0!important;background:transparent!important;color:#fff!important}
.rlcc-coupon a{color:var(--rl-lime)!important;font-weight:800!important}

/* Main layout */
.rlcc-grid{
	display:grid;
	grid-template-columns:minmax(0,1.55fr) minmax(390px,.9fr);
	gap:22px;
	align-items:start;
}
.rlcc-left,.rlcc-right{min-width:0}
.rlcc-right{position:sticky;top:105px}

/* Generic cards */
.rlcc-card{
	background:linear-gradient(145deg,#101510 0%,#0a0e0a 100%);
	border:1px solid var(--rl-line);
	border-radius:18px;
	color:var(--rl-text);
	box-shadow:0 18px 44px rgba(0,0,0,.20);
}
.rlcc-heading{
	margin:0!important;color:#fff!important;
	font-size:21px!important;font-weight:900!important;letter-spacing:.04em!important;
}
.rlcc-muted{margin:5px 0 18px;color:var(--rl-muted);font-size:12px}

/* Entry details only if incomplete */
.rlcc-details{margin-bottom:12px;overflow:hidden}
.rlcc-details summary{
	cursor:pointer;list-style:none;padding:13px 16px;
	color:#fff;font-weight:900;font-size:12px;letter-spacing:.06em;
}
.rlcc-details summary::-webkit-details-marker{display:none}
.rlcc-details summary:before{content:"•";color:var(--rl-lime);font-size:20px;vertical-align:-2px;margin-right:8px}
.rlcc-details-body{padding:0 16px 16px;border-top:1px solid #252c25}
.rlcc-details .woocommerce-billing-fields>h3,
.rlcc-details .woocommerce-additional-fields>h3,
.rlcc-details .woocommerce-shipping-fields{display:none!important}
.rlcc-details .woocommerce-billing-fields__field-wrapper{
	display:grid;grid-template-columns:1fr 1fr;gap:12px 14px;margin-top:14px;
}
.rlcc-details .form-row{width:100%!important;float:none!important;margin:0!important;padding:0!important}
.rlcc-details .form-row-wide,
.rlcc-details #billing_country_field,
.rlcc-details #billing_address_1_field,
.rlcc-details #billing_address_2_field{grid-column:1/-1}


/* v4.6.3 — RESTORE CUSTOMER CONTACT DETAILS */
.rlcc-contact-intro{
	display:flex;
	flex-direction:column;
	gap:3px;
	margin:14px 0 4px;
}
.rlcc-contact-intro strong{
	color:#fff!important;
	font-size:15px!important;
	font-weight:900!important;
}
.rlcc-contact-intro span{
	color:#aeb7ab!important;
	font-size:11px!important;
	line-height:1.45!important;
}
.rlcc-details #billing_first_name_field,
.rlcc-details #billing_last_name_field,
.rlcc-details #billing_phone_field,
.rlcc-details #billing_email_field{
	display:block!important;
	visibility:visible!important;
	opacity:1!important;
}
.rlcc-details input[type="text"],
.rlcc-details input[type="tel"],
.rlcc-details input[type="email"]{
	min-height:46px!important;
	border:1px solid #303930!important;
	border-radius:10px!important;
	background:#0a0e0a!important;
	color:#fff!important;
	padding:0 13px!important;
	box-shadow:none!important;
}
.rlcc-details input:focus{
	border-color:var(--rl-lime)!important;
	box-shadow:0 0 0 1px rgba(186,255,0,.10)!important;
	outline:none!important;
}
.rlcc-details label{
	color:#dce2d9!important;
	font-size:11px!important;
	font-weight:800!important;
	letter-spacing:.02em!important;
}
@media(max-width:767px){
	.rlcc-details .woocommerce-billing-fields__field-wrapper{
		grid-template-columns:1fr!important;
	}
	.rlcc-details .form-row{
		grid-column:1/-1!important;
	}
}

/* PAYMENT */
.rlcc-pay{padding:26px!important}
.rlcc-pay #payment{
	margin:0!important;padding:0!important;border:0!important;background:transparent!important;
}
.rlcc-pay #payment ul.payment_methods{
	display:grid!important;gap:12px!important;
	margin:0!important;padding:0!important;border:0!important;background:transparent!important;
}
.rlcc-pay #payment ul.payment_methods>li{
	position:relative;
	margin:0!important;
	padding:16px!important;
	border:1px solid #303930!important;
	border-radius:14px!important;
	background:#0b0f0b!important;
	color:#fff!important;
	overflow:hidden;
}
.rlcc-pay #payment ul.payment_methods>li:has(input:checked){
	border-color:var(--rl-lime)!important;
	box-shadow:0 0 0 1px rgba(186,255,0,.10) inset,0 12px 30px rgba(0,0,0,.18)!important;
	background:linear-gradient(145deg,#111711,#0b100b)!important;
}
.rlcc-pay #payment ul.payment_methods>li>label{
	display:flex!important;align-items:center!important;gap:10px!important;
	min-height:30px;color:#fff!important;font-size:15px!important;font-weight:900!important;
	cursor:pointer!important;
}
.rlcc-pay #payment input[type=radio]{
	appearance:none!important;-webkit-appearance:none!important;
	width:18px!important;height:18px!important;flex:0 0 18px;
	border:2px solid #697468!important;border-radius:50%!important;
	background:#080b08!important;display:grid!important;place-content:center!important;margin:0!important;
}
.rlcc-pay #payment input[type=radio]:checked{border-color:var(--rl-lime)!important}
.rlcc-pay #payment input[type=radio]:checked:before{
	content:""!important;width:8px!important;height:8px!important;border-radius:50%!important;background:var(--rl-lime)!important;
	box-shadow:0 0 11px rgba(186,255,0,.7)!important;
}
/* Payment brand chips */
.rlcc-pay #payment .payment_method_rafflelb_points>label:before{
	content:"R";display:grid;place-items:center;width:27px;height:27px;border-radius:7px;
	background:#20300a;color:var(--rl-lime);border:1px solid #4b6716;font-weight:900;font-size:14px;
}
.rlcc-pay #payment .payment_method_rafflelb_whish_manual>label:before{
	content:"W";display:grid;place-items:center;width:27px;height:27px;border-radius:7px;
	background:var(--rl-purple);color:#fff;font-weight:900;font-size:14px;
}

/* Payment boxes */
.rlcc-pay #payment .payment_box{
	margin:13px 0 0!important;
	padding:15px!important;
	border:1px solid #303930!important;
	border-radius:11px!important;
	background:#111611!important;
	color:#eef2eb!important;
	box-shadow:none!important;
}
.rlcc-pay #payment .payment_box:before{display:none!important}
.rlcc-pay #payment .payment_box *,
.rlcc-pay .rafflelb-points-payment-box *{
	background-color:transparent!important;
	background-image:none!important;
	color:#eef2eb!important;
	text-shadow:none!important;
	box-shadow:none!important;
}
.rlcc-pay #payment .payment_box strong{color:#fff!important}

/* Points wallet */
.rlcc-pay .rafflelb-points-payment-box{
	padding:4px 0!important;
	background:transparent!important;
}
.rlcc-pay .rafflelb-points-payment-box .rl-points-row{
	display:flex!important;justify-content:space-between!important;align-items:center!important;
	gap:14px!important;min-height:34px!important;
	border-bottom:1px solid #252d24!important;
}
.rlcc-pay .rafflelb-points-payment-box .rl-points-row:last-of-type{border-bottom:0!important}
.rlcc-pay .rafflelb-points-payment-box .rl-points-label{color:#b7c0b4!important}
.rlcc-pay .rafflelb-points-payment-box .rl-points-value{color:#fff!important;font-size:15px!important;font-weight:900!important}
.rlcc-pay .rafflelb-points-payment-box .rl-points-status{margin:10px 0 5px!important;color:#d6ddd3!important}
.rlcc-pay .rafflelb-points-payment-box .rl-points-note{margin:0!important;color:#aeb7ab!important}

/* Whish */
.rlcc-pay .rl-whish-box{
	display:grid!important;
	grid-template-columns:1fr 1fr!important;
	gap:12px 14px!important;
	padding:16px!important;
	border:1px solid #303930!important;
	border-radius:11px!important;
	background:linear-gradient(145deg,#141914,#0e120e)!important;
}
.rlcc-pay .rl-whish-box>.rl-whish-desc,
.rlcc-pay .rl-whish-box>.rl-whish-payto,
.rlcc-pay .rl-whish-box>.rl-whish-instructions,
.rlcc-pay .rl-whish-box>.rl-whish-confirm-title,
.rlcc-pay .rl-whish-box>.rl-whish-help,
.rlcc-pay .rl-whish-box>.rl-whish-notice{grid-column:1/-1!important}
.rlcc-pay .rl-whish-or{display:none!important}
.rlcc-pay .rl-whish-box p:has(#rl_whish_screenshot){
	grid-column:1!important;margin:0!important;padding-right:10px!important;border-right:1px solid #293129!important;
}
.rlcc-pay .rl-whish-box p:has(#rl_whish_reference){grid-column:2!important;margin:0!important}
.rlcc-pay #rl_whish_screenshot{
	width:100%!important;min-height:58px!important;padding:9px!important;
	border:1px dashed #596456!important;border-radius:9px!important;background:#090c09!important;color:#fff!important;
}
.rlcc-pay #rl_whish_screenshot::file-selector-button{
	background:var(--rl-lime)!important;color:#071000!important;border:0!important;border-radius:7px!important;
	padding:9px 12px!important;font-weight:900!important;margin-right:9px!important;
}
.rlcc-pay #rl_whish_reference{
	height:50px!important;border-radius:9px!important;border:1px solid #394438!important;background:#0b0f0b!important;color:#fff!important;
}
.rlcc-pay .rl-whish-notice{
	padding:11px 12px!important;border-radius:9px!important;
	border:1px solid #23445b!important;border-left:3px solid #4ca8ff!important;
	background:#0b1820!important;color:#c9e7ff!important;
}

/* Privacy + CTA */
.rlcc-pay .woocommerce-privacy-policy-text,
.rlcc-pay .woocommerce-terms-and-conditions-wrapper{
	margin-top:18px!important;padding-top:16px!important;border-top:1px solid #252d24!important;
	color:#98a096!important;font-size:11px!important;line-height:1.65!important;
}
.rlcc-pay a{color:var(--rl-lime)!important}
body.rlcc-v3 #place_order{
	width:100%!important;min-height:62px!important;margin-top:10px!important;
	border:0!important;border-radius:11px!important;
	background:linear-gradient(90deg,#aaff00,#caff16)!important;
	color:#071000!important;font-size:16px!important;font-weight:900!important;letter-spacing:.10em!important;
	text-transform:uppercase!important;box-shadow:0 12px 30px rgba(186,255,0,.13)!important;
}

/* ORDER */
.rlcc-order{
	position:relative;overflow:hidden;
	padding:24px!important;
	border-top:2px solid var(--rl-lime)!important;
	background:
		radial-gradient(circle at 100% 0,rgba(186,255,0,.075),transparent 38%),
		linear-gradient(180deg,#111611,#0a0e0a)!important;
}
.rlcc-order-head{
	display:flex;justify-content:space-between;align-items:center;gap:12px;
	padding-bottom:14px;border-bottom:1px solid #2c342c;
}
.rlcc-order-kicker{display:none!important}
.rlcc-entry-badge{
	display:inline-flex;align-items:center;padding:6px 9px;border:1px solid #4b6518;border-radius:999px;
	background:#17200d;color:var(--rl-lime);font-size:9px;font-weight:900;letter-spacing:.10em;
}
.rlcc-order-list{margin:0!important;border:0!important}
.rlcc-order-item{
	display:grid!important;
	grid-template-columns:118px minmax(0,1fr)!important;
	gap:16px!important;
	align-items:center!important;
	padding:20px 0!important;
	border-bottom:1px solid #2a322a!important;
}
.rlcc-thumb{
	width:118px!important;height:118px!important;
	padding:4px!important;
	border:1px solid #3c463a!important;border-radius:14px!important;
	background:#f3f4ef!important;overflow:hidden!important;
	box-shadow:0 15px 30px rgba(0,0,0,.28)!important;
}
.rlcc-thumb img{width:100%!important;height:100%!important;object-fit:cover!important;border-radius:10px!important;display:block!important}
.rlcc-item-name{
	color:#fff!important;font-size:15px!important;line-height:1.35!important;font-weight:900!important;
}
.rlcc-item-meta{display:flex!important;gap:7px!important;align-items:center!important;flex-wrap:wrap!important;margin-top:9px!important}
.rlcc-qty,.rlcc-entry-pill{
	display:inline-flex!important;align-items:center!important;padding:5px 8px!important;border-radius:999px!important;
	font-size:9px!important;font-weight:800!important;
}
.rlcc-qty{background:#151b15!important;border:1px solid #303930!important;color:#aeb8ab!important}
.rlcc-entry-pill{background:#17200d!important;border:1px solid #405914!important;color:var(--rl-lime)!important}
.rlcc-item-price{
	grid-column:1/-1!important;
	display:flex!important;justify-content:space-between!important;align-items:center!important;
	margin-top:0!important;padding:12px 14px!important;
	border:1px solid #2c352b!important;border-radius:10px!important;background:#0d110d!important;
}
.rlcc-item-price>span{color:#93a08f!important;font-size:9px!important;font-weight:900!important;letter-spacing:.08em!important}
.rlcc-item-price strong,
.rlcc-item-price strong *,
.rlcc-item-price .woocommerce-Price-amount,
.rlcc-item-price .woocommerce-Price-currencySymbol{
	color:var(--rl-lime)!important;font-size:16px!important;font-weight:900!important;
}

/* Total */
.rlcc-totals{
	margin-top:16px!important;padding:14px!important;
	border:1px solid #2d352d!important;border-radius:12px!important;background:#0c100c!important;
}
.rlcc-total-row{display:none!important}
.rlcc-total-row.grand{
	display:flex!important;justify-content:space-between!important;align-items:center!important;
	padding:2px 0!important;border:0!important;
}
.rlcc-total-row.grand span{color:#fff!important;font-size:12px!important;font-weight:900!important;letter-spacing:.10em!important}
.rlcc-total-row.grand strong,
.rlcc-total-row.grand strong *,
.rlcc-total-row.grand .woocommerce-Price-amount,
.rlcc-total-row.grand .woocommerce-Price-currencySymbol{
	color:var(--rl-lime)!important;font-size:27px!important;font-weight:900!important;
}
.rlcc-order-security{
	margin-top:13px!important;padding:13px 14px!important;
	border:1px solid #303a2f!important;border-radius:10px!important;background:#0d120d!important;
}
.rlcc-order-security strong{display:flex!important;align-items:center!important;gap:8px!important;color:#fff!important;font-size:11px!important}
.rlcc-order-security strong:before{
	content:"✓";display:grid;place-items:center;width:25px;height:25px;border-radius:7px;
	background:rgba(186,255,0,.09);border:1px solid rgba(186,255,0,.35);color:var(--rl-lime);
}
.rlcc-order-security small{display:block!important;margin:5px 0 0 33px!important;color:#8f9a8d!important;font-size:9px!important;line-height:1.45!important}

/* Turnstile on order side */
.rlcc-order .cf-turnstile,
.rlcc-order [class*=turnstile]{margin-top:14px!important;max-width:100%!important}
body.rlcc-v3 iframe{max-width:100%!important;border-radius:8px!important}

/* Inputs */
body.rlcc-v3 label{color:#e8ede6!important}
body.rlcc-v3 input[type=text],
body.rlcc-v3 input[type=email],
body.rlcc-v3 input[type=tel],
body.rlcc-v3 input[type=number],
body.rlcc-v3 input[type=password],
body.rlcc-v3 textarea,
body.rlcc-v3 select{
	background:#0a0d0a!important;color:#fff!important;border:1px solid #394239!important;border-radius:9px!important;min-height:44px!important;
}

/* Responsive */
@media(max-width:991px){
	body.rlcc-v3 .rlcc-app{padding-top:24px!important}
	.rlcc-grid{grid-template-columns:1fr!important}
	.rlcc-right{position:static!important}
}
@media(max-width:767px){
	body.rlcc-v3 .rlcc-wrap{padding:0 10px!important}
	.rlcc-hero{padding:23px 18px!important}
	.rlcc-hero h1{font-size:29px!important}
	.rlcc-pay,.rlcc-order{padding:17px!important}
	.rlcc-details .woocommerce-billing-fields__field-wrapper{grid-template-columns:1fr!important}
	.rlcc-details .form-row-wide,
	.rlcc-details #billing_country_field,
	.rlcc-details #billing_address_1_field,
	.rlcc-details #billing_address_2_field{grid-column:auto!important}
	.rlcc-pay .rl-whish-box{grid-template-columns:1fr!important}
	.rlcc-pay .rl-whish-box p:has(#rl_whish_screenshot),
	.rlcc-pay .rl-whish-box p:has(#rl_whish_reference){
		grid-column:1!important;border-right:0!important;padding-right:0!important;
	}
	.rlcc-order-item{grid-template-columns:92px minmax(0,1fr)!important;gap:12px!important}
	.rlcc-thumb{width:92px!important;height:92px!important}
	.rlcc-item-name{font-size:14px!important}
}

/* v4.1.0 premium order-card refinement */
@media(min-width:992px){.rlcc-grid{grid-template-columns:minmax(0,1.42fr) minmax(430px,.98fr)!important;gap:26px!important}}
.rlcc-order{padding:27px!important;border:1px solid rgba(186,255,0,.72)!important;border-top:3px solid var(--rl-lime)!important;box-shadow:0 0 0 1px rgba(186,255,0,.05) inset,0 0 34px rgba(186,255,0,.055),0 24px 60px rgba(0,0,0,.32)!important;background:radial-gradient(circle at 96% 3%,rgba(186,255,0,.12),transparent 34%),linear-gradient(145deg,#121812 0%,#090d09 72%)!important}
.rlcc-order-head{padding-bottom:18px!important;border-bottom:1px solid rgba(186,255,0,.22)!important}.rlcc-order .rlcc-heading{font-size:23px!important}.rlcc-entry-badge{padding:7px 11px!important;border-color:rgba(186,255,0,.48)!important;background:rgba(186,255,0,.075)!important}
.rlcc-order-item{grid-template-columns:168px minmax(0,1fr)!important;gap:20px!important;align-items:center!important;padding:24px 0 21px!important;border-bottom:1px solid rgba(186,255,0,.18)!important}
.rlcc-thumb{width:168px!important;height:168px!important;padding:5px!important;border:1px solid rgba(186,255,0,.52)!important;border-radius:18px!important;background:#f4f5ef!important;box-shadow:0 0 0 4px rgba(186,255,0,.045),0 18px 38px rgba(0,0,0,.38)!important}.rlcc-thumb img{width:100%!important;height:100%!important;object-fit:cover!important;border-radius:13px!important}
.rlcc-item-name{color:#fff!important;font-size:18px!important;line-height:1.32!important;font-weight:900!important}.rlcc-item-meta{margin-top:13px!important;gap:8px!important}
.rlcc-item-price{grid-column:1/-1!important;padding:14px 16px!important;border:1px solid rgba(186,255,0,.25)!important;border-left:3px solid var(--rl-lime)!important;border-radius:11px!important;background:linear-gradient(90deg,rgba(186,255,0,.065),rgba(186,255,0,.012) 52%,transparent)!important}.rlcc-item-price>span{color:#b9c5b4!important;font-size:10px!important;letter-spacing:.12em!important}.rlcc-item-price strong,.rlcc-item-price strong *{color:var(--rl-lime)!important;font-size:19px!important}
.rlcc-totals{margin-top:17px!important;padding:17px 18px!important;border:1px solid rgba(186,255,0,.34)!important;background:linear-gradient(105deg,rgba(186,255,0,.055),rgba(10,14,10,.98) 46%)!important}.rlcc-total-row.grand strong,.rlcc-total-row.grand strong *{color:var(--rl-lime)!important;font-size:31px!important}
.rlcc-order-security{border:1px solid rgba(186,255,0,.22)!important;background:rgba(186,255,0,.025)!important}
@media(max-width:767px){.rlcc-order{padding:19px!important}.rlcc-order-item{grid-template-columns:122px minmax(0,1fr)!important;gap:14px!important}.rlcc-thumb{width:122px!important;height:122px!important}.rlcc-item-name{font-size:15px!important}}

/* ==========================================================
   v4.1.1 — requested order-card polish
   ========================================================== */

/* 1) Thinner product image frame */
.rlcc-thumb{
  padding:2px!important;
  border-width:1px!important;
  box-shadow:0 12px 28px rgba(0,0,0,.30)!important;
}
.rlcc-thumb img{
  border-radius:14px!important;
}

/* 2) Remove the unnecessary separator line above ORDER TOTAL */
.rlcc-order-item{
  border-bottom:0!important;
  padding-bottom:14px!important;
}
.rlcc-totals{
  margin-top:8px!important;
}

/* 3) Make ALL currency symbols inside ORDER TOTAL lime */
.rlcc-total-row.grand strong,
.rlcc-total-row.grand strong *,
.rlcc-total-row.grand .woocommerce-Price-amount,
.rlcc-total-row.grand .woocommerce-Price-currencySymbol,
.rlcc-total-row.grand bdi,
.rlcc-total-row.grand bdi *{
  color:var(--rl-lime)!important;
  -webkit-text-fill-color:var(--rl-lime)!important;
}

/* 4) Make Qty pill lime instead of white/gray */
.rlcc-qty{
  color:var(--rl-lime)!important;
  -webkit-text-fill-color:var(--rl-lime)!important;
  border-color:rgba(186,255,0,.45)!important;
  background:rgba(186,255,0,.07)!important;
}
.rlcc-qty *{
  color:var(--rl-lime)!important;
  -webkit-text-fill-color:var(--rl-lime)!important;
}

/* ==========================================================
   v4.2.0 — payment row alignment + configurable logos
   ========================================================== */
.rlcc-pay #payment ul.payment_methods>li{
  position:relative!important;
}
.rlcc-pay #payment ul.payment_methods>li>input[type=radio]{
  position:absolute!important;
  left:16px!important;
  top:22px!important;
  margin:0!important;
  z-index:2!important;
}
.rlcc-pay #payment ul.payment_methods>li>label{
  display:flex!important;
  align-items:center!important;
  gap:10px!important;
  min-height:32px!important;
  padding-left:28px!important;
  color:#fff!important;
  font-size:15px!important;
  font-weight:900!important;
  line-height:1.2!important;
}

/* Configurable logo slot before payment title */
.rlcc-pay #payment .payment_method_rafflelb_points>label:before,
.rlcc-pay #payment .payment_method_rafflelb_whish_manual>label:before{
  content:""!important;
  display:block!important;
  flex:0 0 30px!important;
  width:30px!important;
  height:30px!important;
  border-radius:8px!important;
  background-size:contain!important;
  background-position:center!important;
  background-repeat:no-repeat!important;
}

/* Fallback badge backgrounds */
.rlcc-pay #payment .payment_method_rafflelb_points>label:before{
  background-color:#20300a!important;
  border:1px solid #4b6716!important;
  background-image:var(--rlcc-points-logo,none)!important;
}
.rlcc-pay #payment .payment_method_rafflelb_whish_manual>label:before{
  background-color:#7b4de6!important;
  border:1px solid rgba(255,255,255,.10)!important;
  background-image:var(--rlcc-whish-logo,none)!important;
}

body.rlcc-has-points-logo .payment_method_rafflelb_points>label:before,
body.rlcc-has-whish-logo .payment_method_rafflelb_whish_manual>label:before{
  background-color:transparent!important;
  border-color:#303930!important;
}

/* WooCommerce gateway-provided icon remains neatly aligned */
.rlcc-pay #payment ul.payment_methods>li>label img{
  max-height:30px!important;
  max-width:100px!important;
  width:auto!important;
  margin-left:auto!important;
  object-fit:contain!important;
}

/* ==========================================================
   v4.2.1 — bundled clear Whish logo
   ========================================================== */

/* Whish needs a wide wordmark slot, not a square icon */
.rlcc-pay #payment .payment_method_rafflelb_whish_manual>label:before{
  flex:0 0 82px!important;
  width:82px!important;
  height:30px!important;
  border:0!important;
  border-radius:0!important;
  background-color:transparent!important;
  background-size:contain!important;
  background-position:left center!important;
  background-repeat:no-repeat!important;
}

/* Keep radio → logo → name comfortably aligned */
.rlcc-pay #payment .payment_method_rafflelb_whish_manual>label{
  gap:12px!important;
}

/* Raffle Points can keep compact square badge/logo */
.rlcc-pay #payment .payment_method_rafflelb_points>label:before{
  flex:0 0 30px!important;
  width:30px!important;
  height:30px!important;
}

/* Mobile: slightly smaller Whish wordmark so the title stays on one line */
@media(max-width:480px){
  .rlcc-pay #payment .payment_method_rafflelb_whish_manual>label:before{
    flex-basis:68px!important;
    width:68px!important;
    height:26px!important;
  }
  .rlcc-pay #payment .payment_method_rafflelb_whish_manual>label{
    gap:9px!important;
    font-size:14px!important;
  }
}

/* ==========================================================
   v4.3.0 — automatic payment branding
   ========================================================== */

/* Raffle Points automatically uses the WordPress Site Icon */
.rlcc-pay #payment .payment_method_rafflelb_points>label:before{
  flex:0 0 34px!important;
  width:34px!important;
  height:34px!important;
  padding:2px!important;
  border:1px solid rgba(186,255,0,.38)!important;
  border-radius:9px!important;
  background-color:#0b0f0b!important;
  background-size:28px 28px!important;
  background-position:center!important;
  background-repeat:no-repeat!important;
}

/* Whish continues using the bundled official SVG */
.rlcc-pay #payment .payment_method_rafflelb_whish_manual>label:before{
  flex:0 0 82px!important;
  width:82px!important;
  height:30px!important;
  border:0!important;
  background-color:transparent!important;
  background-size:contain!important;
  background-position:left center!important;
}

/* ==========================================================
   v4.3.1 — Whish logo clarity + tight row alignment
   ========================================================== */

/* Keep every payment row left-aligned: radio, logo, title */
.rlcc-pay #payment .payment_method_rafflelb_whish_manual>label{
  justify-content:flex-start!important;
  gap:9px!important;
  padding-left:28px!important;
  width:auto!important;
}

/* The supplied Whish SVG is an app-icon style asset, so display it
   as a crisp square just like the RaffleLB site icon. */
.rlcc-pay #payment .payment_method_rafflelb_whish_manual>label:before{
  flex:0 0 34px!important;
  width:34px!important;
  height:34px!important;
  padding:2px!important;
  margin:0!important;
  border:1px solid rgba(255,255,255,.16)!important;
  border-radius:9px!important;
  background-color:#0b0f0b!important;
  background-size:30px 30px!important;
  background-position:center!important;
  background-repeat:no-repeat!important;
  box-sizing:border-box!important;
}

/* Prevent WoodMart/WooCommerce label spacing from pushing the title away */
.rlcc-pay #payment .payment_method_rafflelb_whish_manual>label > *{
  margin-left:0!important;
  margin-right:0!important;
}
.rlcc-pay #payment .payment_method_rafflelb_whish_manual>label img{
  margin-left:0!important;
}

/* Match the Raffle Points icon geometry */
.rlcc-pay #payment .payment_method_rafflelb_points>label{
  justify-content:flex-start!important;
  gap:9px!important;
}
.rlcc-pay #payment .payment_method_rafflelb_points>label:before{
  flex:0 0 34px!important;
  width:34px!important;
  height:34px!important;
  box-sizing:border-box!important;
}

@media(max-width:480px){
  .rlcc-pay #payment .payment_method_rafflelb_whish_manual>label:before{
    flex-basis:32px!important;
    width:32px!important;
    height:32px!important;
    background-size:28px 28px!important;
  }
}

/* ==========================================================
   v4.3.2 — clearer supplied Whish PNG
   ========================================================== */
.rlcc-pay #payment .payment_method_rafflelb_whish_manual>label:before{
  flex:0 0 38px!important;
  width:38px!important;
  height:38px!important;
  padding:0!important;
  border:1px solid rgba(255,255,255,.18)!important;
  border-radius:9px!important;
  background-color:#f51646!important;
  background-size:38px 38px!important;
  background-position:center!important;
  background-repeat:no-repeat!important;
  box-shadow:0 0 0 1px rgba(255,255,255,.025) inset!important;
}
.rlcc-pay #payment .payment_method_rafflelb_whish_manual>label{
  gap:10px!important;
}
@media(max-width:480px){
  .rlcc-pay #payment .payment_method_rafflelb_whish_manual>label:before{
    flex-basis:34px!important;
    width:34px!important;
    height:34px!important;
    background-size:34px 34px!important;
  }
}

/* ==========================================================
   v4.4.0 — premium payment presentation
   ========================================================== */

/* Overall payment card */
.rlcc-pay{
  padding:30px!important;
  background:
    radial-gradient(circle at 0 0,rgba(186,255,0,.045),transparent 28%),
    linear-gradient(145deg,#101510 0%,#090d09 100%)!important;
  border-color:#2f392e!important;
}
.rlcc-pay .rlcc-heading{
  font-size:22px!important;
  letter-spacing:.055em!important;
}
.rlcc-pay .rlcc-muted{
  margin-top:6px!important;
  margin-bottom:20px!important;
  color:#aab4a7!important;
  font-size:12px!important;
  letter-spacing:.01em!important;
}

/* Payment method cards */
.rlcc-pay #payment ul.payment_methods{
  gap:14px!important;
}
.rlcc-pay #payment ul.payment_methods>li{
  padding:18px 18px 17px!important;
  border:1px solid #313b30!important;
  border-radius:15px!important;
  background:
    linear-gradient(145deg,#111611 0%,#0b0f0b 100%)!important;
  box-shadow:0 10px 28px rgba(0,0,0,.16)!important;
  transition:border-color .18s ease,box-shadow .18s ease,background .18s ease!important;
}
.rlcc-pay #payment ul.payment_methods>li:has(input:checked){
  border-color:var(--rl-lime)!important;
  background:
    radial-gradient(circle at 4% 0,rgba(186,255,0,.075),transparent 35%),
    linear-gradient(145deg,#121812 0%,#0b100b 100%)!important;
  box-shadow:
    0 0 0 1px rgba(186,255,0,.08) inset,
    0 15px 34px rgba(0,0,0,.22)!important;
}

/* Radio */
.rlcc-pay #payment ul.payment_methods>li>input[type=radio]{
  left:18px!important;
  top:25px!important;
  width:18px!important;
  height:18px!important;
  border-width:1.5px!important;
}
.rlcc-pay #payment ul.payment_methods>li>input[type=radio]:checked{
  border-color:var(--rl-lime)!important;
  box-shadow:0 0 0 3px rgba(186,255,0,.06)!important;
}

/* Method title */
.rlcc-pay #payment ul.payment_methods>li>label{
  padding-left:30px!important;
  gap:11px!important;
  min-height:36px!important;
  font-size:16px!important;
  font-weight:900!important;
  line-height:1.2!important;
  letter-spacing:.015em!important;
  color:#fff!important;
  text-transform:none!important;
}

/* Raffle Points icon */
.rlcc-pay #payment .payment_method_rafflelb_points>label:before{
  flex:0 0 36px!important;
  width:36px!important;
  height:36px!important;
  padding:2px!important;
  border-radius:10px!important;
  border:1px solid rgba(186,255,0,.42)!important;
  background-size:30px 30px!important;
  box-shadow:0 0 16px rgba(186,255,0,.05)!important;
}

/* Whish icon */
.rlcc-pay #payment .payment_method_rafflelb_whish_manual>label:before{
  flex:0 0 38px!important;
  width:38px!important;
  height:38px!important;
  border-radius:10px!important;
  box-shadow:0 0 16px rgba(245,22,70,.10)!important;
}

/* Payment detail boxes */
.rlcc-pay #payment .payment_box{
  margin-top:15px!important;
  padding:16px 17px!important;
  border:1px solid #344033!important;
  border-radius:12px!important;
  background:
    linear-gradient(180deg,#121712 0%,#0d120d 100%)!important;
  color:#dfe5dc!important;
}
.rlcc-pay #payment .payment_box p{
  color:#cfd6cc!important;
  font-size:12px!important;
  line-height:1.65!important;
}

/* Premium Raffle Points summary */
.rlcc-pay .rafflelb-points-payment-box{
  padding:1px 0!important;
}
.rlcc-pay .rafflelb-points-payment-box .rl-points-row{
  min-height:38px!important;
  padding:2px 0!important;
  border-bottom:1px solid rgba(255,255,255,.07)!important;
}
.rlcc-pay .rafflelb-points-payment-box .rl-points-label{
  color:#9faa9c!important;
  font-size:12px!important;
  letter-spacing:.01em!important;
}
.rlcc-pay .rafflelb-points-payment-box .rl-points-value{
  color:#fff!important;
  font-size:15px!important;
  font-weight:900!important;
  letter-spacing:.02em!important;
}
.rlcc-pay .rafflelb-points-payment-box .rl-points-status{
  margin:13px 0 4px!important;
  color:#e5eae2!important;
  font-size:12px!important;
  line-height:1.55!important;
}
.rlcc-pay .rafflelb-points-payment-box .rl-points-status strong{
  color:var(--rl-lime)!important;
}
.rlcc-pay .rafflelb-points-payment-box .rl-points-note{
  margin:0!important;
  color:#99a496!important;
  font-size:11px!important;
  line-height:1.55!important;
}

/* Whish method gets a subtle brand cue without overdoing pink */
.rlcc-pay #payment .payment_method_rafflelb_whish_manual:has(input:checked){
  border-color:#ff315b!important;
  box-shadow:
    0 0 0 1px rgba(255,49,91,.07) inset,
    0 15px 34px rgba(0,0,0,.22)!important;
}
.rlcc-pay #payment .payment_method_rafflelb_whish_manual:has(input:checked)>input[type=radio]{
  border-color:#ff315b!important;
}
.rlcc-pay #payment .payment_method_rafflelb_whish_manual:has(input:checked)>input[type=radio]:before{
  background:#ff315b!important;
  box-shadow:0 0 11px rgba(255,49,91,.45)!important;
}

/* Better breathing room before privacy / CTA */
.rlcc-pay .woocommerce-privacy-policy-text,
.rlcc-pay .woocommerce-terms-and-conditions-wrapper{
  margin-top:22px!important;
  padding-top:18px!important;
}
body.rlcc-v3 #place_order{
  margin-top:16px!important;
  min-height:60px!important;
  border-radius:12px!important;
  font-size:15px!important;
  letter-spacing:.12em!important;
}

/* ==========================================================
   v4.5.0 — MOBILE CHECKOUT REBUILD
   Order summary first, payment second.
   ========================================================== */
@media(max-width:767px){

  body.rlcc-v3 .rlcc-app{
    padding:12px 0 38px!important;
  }

  body.rlcc-v3 .rlcc-wrap{
    padding:0 12px!important;
  }

  /* On mobile: product/order FIRST, payment SECOND */
  .rlcc-grid{
    display:flex!important;
    flex-direction:column!important;
    gap:16px!important;
  }
  .rlcc-right{
    order:1!important;
    position:static!important;
    width:100%!important;
  }
  .rlcc-left{
    order:2!important;
    width:100%!important;
  }

  /* ORDER CARD — compact premium mobile layout */
  .rlcc-order{
    padding:18px!important;
    border-radius:16px!important;
    border-width:1px!important;
    border-top-width:2px!important;
  }
  .rlcc-order-head{
    padding-bottom:13px!important;
  }
  .rlcc-order .rlcc-heading{
    font-size:23px!important;
    line-height:1.1!important;
    letter-spacing:.035em!important;
  }
  .rlcc-entry-badge{
    padding:6px 9px!important;
    font-size:8px!important;
  }

  .rlcc-order-item{
    grid-template-columns:112px minmax(0,1fr)!important;
    gap:13px!important;
    padding:16px 0 12px!important;
    align-items:center!important;
  }
  .rlcc-thumb{
    width:112px!important;
    height:112px!important;
    padding:2px!important;
    border-radius:14px!important;
  }
  .rlcc-thumb img{
    border-radius:11px!important;
  }
  .rlcc-item-name{
    font-size:15px!important;
    line-height:1.32!important;
  }
  .rlcc-item-meta{
    gap:6px!important;
    margin-top:9px!important;
  }
  .rlcc-qty,
  .rlcc-entry-pill{
    padding:5px 7px!important;
    font-size:8px!important;
  }
  .rlcc-item-price{
    margin-top:0!important;
    padding:11px 12px!important;
    border-radius:9px!important;
  }
  .rlcc-item-price>span{
    font-size:9px!important;
  }
  .rlcc-item-price strong,
  .rlcc-item-price strong *{
    font-size:17px!important;
  }
  .rlcc-totals{
    margin-top:11px!important;
    padding:13px 14px!important;
  }
  .rlcc-total-row.grand span{
    font-size:10px!important;
  }
  .rlcc-total-row.grand strong,
  .rlcc-total-row.grand strong *,
  .rlcc-total-row.grand .woocommerce-Price-amount,
  .rlcc-total-row.grand .woocommerce-Price-currencySymbol{
    font-size:26px!important;
  }
  .rlcc-order-security{
    margin-top:11px!important;
    padding:11px 12px!important;
  }
  .rlcc-order-security strong{
    font-size:10px!important;
  }
  .rlcc-order-security small{
    margin-left:31px!important;
    font-size:8px!important;
  }

  /* PAYMENT CARD — remove oversized mobile spacing */
  .rlcc-pay{
    padding:18px!important;
    border-radius:16px!important;
  }
  .rlcc-pay .rlcc-heading{
    font-size:22px!important;
    line-height:1.1!important;
  }
  .rlcc-pay .rlcc-muted{
    margin:5px 0 15px!important;
    font-size:11px!important;
  }
  .rlcc-pay #payment ul.payment_methods{
    gap:11px!important;
  }
  .rlcc-pay #payment ul.payment_methods>li{
    padding:14px!important;
    border-radius:13px!important;
  }
  .rlcc-pay #payment ul.payment_methods>li>input[type=radio]{
    left:14px!important;
    top:21px!important;
    width:17px!important;
    height:17px!important;
  }
  .rlcc-pay #payment ul.payment_methods>li>label{
    min-height:34px!important;
    padding-left:27px!important;
    gap:9px!important;
    font-size:14px!important;
    line-height:1.2!important;
  }

  .rlcc-pay #payment .payment_method_rafflelb_points>label:before{
    flex-basis:34px!important;
    width:34px!important;
    height:34px!important;
    background-size:28px 28px!important;
  }
  .rlcc-pay #payment .payment_method_rafflelb_whish_manual>label:before{
    flex-basis:34px!important;
    width:34px!important;
    height:34px!important;
    background-size:34px 34px!important;
  }

  /* PAYMENT EXPANDED CONTENT */
  .rlcc-pay #payment .payment_box{
    margin-top:12px!important;
    padding:13px!important;
    border-radius:10px!important;
  }
  .rlcc-pay #payment .payment_box p,
  .rlcc-pay .rl-whish-box p{
    font-size:11px!important;
    line-height:1.5!important;
  }

  .rlcc-pay .rl-whish-box{
    display:block!important;
    padding:13px!important;
    border-radius:10px!important;
  }
  .rlcc-pay .rl-whish-box>*{
    margin-bottom:10px!important;
  }
  .rlcc-pay .rl-whish-box>*:last-child{
    margin-bottom:0!important;
  }
  .rlcc-pay .rl-whish-box p:has(#rl_whish_screenshot),
  .rlcc-pay .rl-whish-box p:has(#rl_whish_reference){
    padding:0!important;
    border:0!important;
    margin:10px 0!important;
  }
  .rlcc-pay #rl_whish_screenshot{
    min-height:50px!important;
    padding:7px!important;
    font-size:11px!important;
  }
  .rlcc-pay #rl_whish_screenshot::file-selector-button{
    padding:8px 10px!important;
    font-size:11px!important;
  }
  .rlcc-pay #rl_whish_reference{
    height:46px!important;
    font-size:12px!important;
  }
  .rlcc-pay .rl-whish-notice{
    padding:10px!important;
    font-size:11px!important;
    line-height:1.45!important;
  }

  /* Raffle Points compact mobile wallet */
  .rlcc-pay .rafflelb-points-payment-box .rl-points-row{
    min-height:33px!important;
  }
  .rlcc-pay .rafflelb-points-payment-box .rl-points-label{
    font-size:11px!important;
  }
  .rlcc-pay .rafflelb-points-payment-box .rl-points-value{
    font-size:13px!important;
  }
  .rlcc-pay .rafflelb-points-payment-box .rl-points-status{
    font-size:11px!important;
    margin-top:10px!important;
  }
  .rlcc-pay .rafflelb-points-payment-box .rl-points-note{
    font-size:10px!important;
  }

  /* Privacy + CTA */
  .rlcc-pay .woocommerce-privacy-policy-text,
  .rlcc-pay .woocommerce-terms-and-conditions-wrapper{
    margin-top:16px!important;
    padding-top:14px!important;
    font-size:10px!important;
    line-height:1.55!important;
  }
  body.rlcc-v3 #place_order{
    min-height:54px!important;
    margin-top:12px!important;
    font-size:14px!important;
    border-radius:10px!important;
  }

  /* Turnstile: keep it compact inside order card */
  .rlcc-order .cf-turnstile,
  .rlcc-order [class*=turnstile]{
    margin-top:12px!important;
    transform-origin:left top!important;
    max-width:100%!important;
  }

  /* Coupon line */
  .rlcc-coupon{
    margin-bottom:12px!important;
    font-size:11px!important;
  }
}

/* very small phones */
@media(max-width:390px){
  .rlcc-order-item{
    grid-template-columns:96px minmax(0,1fr)!important;
  }
  .rlcc-thumb{
    width:96px!important;
    height:96px!important;
  }
  .rlcc-item-name{
    font-size:14px!important;
  }
  .rlcc-pay #payment ul.payment_methods>li>label{
    font-size:13px!important;
  }
}

/* ==========================================================
   v4.5.1 — replacement Whish logo supplied by user
   ========================================================== */
.rlcc-pay #payment .payment_method_rafflelb_whish_manual>label:before{
  flex:0 0 40px!important;
  width:40px!important;
  height:40px!important;
  border-radius:10px!important;
  background-size:40px 40px!important;
  background-position:center!important;
  background-repeat:no-repeat!important;
  image-rendering:auto!important;
}
@media(max-width:767px){
  .rlcc-pay #payment .payment_method_rafflelb_whish_manual>label:before{
    flex-basis:38px!important;
    width:38px!important;
    height:38px!important;
    background-size:38px 38px!important;
  }
}

/* ==========================================================
   v4.5.2 — simplify Whish nesting / remove middle outer box
   ========================================================== */

/* Keep the main selected Whish card only. Remove the extra
   WooCommerce payment_box frame that was creating a box-in-box look. */
.rlcc-pay #payment .payment_method_rafflelb_whish_manual .payment_box{
  margin-top:12px!important;
  padding:0!important;
  border:0!important;
  border-radius:0!important;
  background:transparent!important;
  box-shadow:none!important;
}

/* Also remove any extra outer frame applied to the gateway content itself. */
.rlcc-pay #payment .payment_method_rafflelb_whish_manual .rl-whish-box{
  padding:0!important;
  border:0!important;
  border-radius:0!important;
  background:transparent!important;
  box-shadow:none!important;
}

/* Let the actual content blocks provide the visual structure. */
.rlcc-pay #payment .payment_method_rafflelb_whish_manual .rl-whish-steps,
.rlcc-pay #payment .payment_method_rafflelb_whish_manual .rl-whish-confirm{
  margin-left:0!important;
  margin-right:0!important;
}

/* Desktop: slightly more breathing room without another frame. */
@media(min-width:768px){
  .rlcc-pay #payment .payment_method_rafflelb_whish_manual .payment_box{
    padding:4px 0 0!important;
  }
}

/* Mobile: completely flat wrapper, no nested rectangles around the section. */
@media(max-width:767px){
  .rlcc-pay #payment .payment_method_rafflelb_whish_manual .payment_box,
  .rlcc-pay #payment .payment_method_rafflelb_whish_manual .rl-whish-box{
    margin-top:10px!important;
    padding:0!important;
    border:0!important;
    background:transparent!important;
    box-shadow:none!important;
  }
}

/* ==========================================================
   v4.6.0 — OMT Pay gateway
   ========================================================== */
.rlcc-pay #payment .payment_method_rafflelb_omt_pay_manual>label:before{
  content:""!important;
  display:block!important;
  flex:0 0 74px!important;
  width:74px!important;
  height:38px!important;
  border-radius:10px!important;
  background-image:var(--rlcc-omt-logo)!important;
  background-color:#ffd600!important;
  background-position:center!important;
  background-repeat:no-repeat!important;
  background-size:70px auto!important;
  border:1px solid rgba(255,214,0,.35)!important;
}
.rlcc-pay #payment .payment_method_rafflelb_omt_pay_manual:has(input:checked){
  border-color:#ffd600!important;
  box-shadow:
    0 0 0 1px rgba(255,214,0,.08) inset,
    0 15px 34px rgba(0,0,0,.22)!important;
}
.rlcc-pay #payment .payment_method_rafflelb_omt_pay_manual:has(input:checked)>input[type=radio]{
  border-color:#ffd600!important;
}
.rlcc-pay #payment .payment_method_rafflelb_omt_pay_manual:has(input:checked)>input[type=radio]:before{
  background:#ffd600!important;
  box-shadow:0 0 11px rgba(255,214,0,.38)!important;
}

/* Same clean wrapper treatment as Whish */
.rlcc-pay #payment .payment_method_rafflelb_omt_pay_manual .payment_box{
  margin-top:12px!important;
  padding:0!important;
  border:0!important;
  border-radius:0!important;
  background:transparent!important;
  box-shadow:none!important;
}
.rlcc-pay #payment .payment_method_rafflelb_omt_pay_manual .rl-omt-pay-box{
  padding:0!important;
  border:0!important;
  border-radius:0!important;
  background:transparent!important;
  box-shadow:none!important;
}
@media(max-width:767px){
  .rlcc-pay #payment .payment_method_rafflelb_omt_pay_manual>label:before{
    flex-basis:66px!important;
    width:66px!important;
    height:34px!important;
    background-size:62px auto!important;
  }
  .rlcc-pay #payment .payment_method_rafflelb_omt_pay_manual .payment_box,
  .rlcc-pay #payment .payment_method_rafflelb_omt_pay_manual .rl-omt-pay-box{
    margin-top:10px!important;
    padding:0!important;
    border:0!important;
    background:transparent!important;
    box-shadow:none!important;
  }
}

/* ==========================================================
   v4.6.1 — WHISH layout exactly like OMT Pay
   Remove legacy 2-column Whish presentation.
   ========================================================== */

/* Main Woo payment wrapper: same flat treatment as OMT */
.rlcc-pay #payment .payment_method_rafflelb_whish_manual .payment_box{
  margin-top:12px!important;
  padding:0!important;
  border:0!important;
  border-radius:0!important;
  background:transparent!important;
  box-shadow:none!important;
}

/* Kill old Whish grid / middle frame */
.rlcc-pay #payment .payment_method_rafflelb_whish_manual .rl-whish-box{
  display:block!important;
  grid-template-columns:none!important;
  gap:0!important;
  padding:0!important;
  margin:0!important;
  border:0!important;
  border-radius:0!important;
  background:transparent!important;
  box-shadow:none!important;
}

/* 3 steps stacked full-width, exactly like OMT */
.rlcc-pay #payment .payment_method_rafflelb_whish_manual .rl-whish-steps{
  display:grid!important;
  grid-template-columns:1fr!important;
  gap:9px!important;
  margin:0 0 13px!important;
  padding:0!important;
}
.rlcc-pay #payment .payment_method_rafflelb_whish_manual .rl-whish-step{
  display:grid!important;
  grid-template-columns:26px minmax(0,1fr)!important;
  gap:10px!important;
  align-items:start!important;
  width:100%!important;
  margin:0!important;
  padding:10px 11px!important;
  border:1px solid #303930!important;
  border-radius:10px!important;
  background:#0d120d!important;
}
.rlcc-pay #payment .payment_method_rafflelb_whish_manual .rl-whish-step-no{
  display:grid!important;
  place-items:center!important;
  width:26px!important;
  height:26px!important;
  margin:0!important;
  border-radius:50%!important;
  background:transparent!important;
  color:#fff!important;
  font-size:11px!important;
  font-weight:900!important;
}
.rlcc-pay #payment .payment_method_rafflelb_whish_manual .rl-whish-step strong{
  display:block!important;
  color:#fff!important;
  font-size:12px!important;
  line-height:1.3!important;
}
.rlcc-pay #payment .payment_method_rafflelb_whish_manual .rl-whish-step p{
  margin:3px 0 0!important;
  color:#aeb8ab!important;
  font-size:10px!important;
  line-height:1.45!important;
}

/* Screenshot/reference area full width under steps — same as OMT */
.rlcc-pay #payment .payment_method_rafflelb_whish_manual .rl-whish-confirm{
  display:block!important;
  width:100%!important;
  margin:0!important;
  padding:12px!important;
  border:1px solid #303930!important;
  border-radius:10px!important;
  background:#0b0f0b!important;
}
.rlcc-pay #payment .payment_method_rafflelb_whish_manual .rl-whish-field{
  display:block!important;
  width:100%!important;
  margin:0!important;
  padding:0!important;
  border:0!important;
}
.rlcc-pay #payment .payment_method_rafflelb_whish_manual .rl-whish-divider{
  display:flex!important;
  align-items:center!important;
  gap:8px!important;
  margin:10px 0!important;
}
.rlcc-pay #payment .payment_method_rafflelb_whish_manual .rl-whish-divider:before,
.rlcc-pay #payment .payment_method_rafflelb_whish_manual .rl-whish-divider:after{
  content:""!important;
  height:1px!important;
  flex:1!important;
  background:#293129!important;
}

/* Remove legacy selectors that were forcing screenshot/reference side-by-side */
.rlcc-pay #payment .payment_method_rafflelb_whish_manual .rl-whish-box p:has(#rl_whish_screenshot),
.rlcc-pay #payment .payment_method_rafflelb_whish_manual .rl-whish-box p:has(#rl_whish_reference){
  display:block!important;
  grid-column:auto!important;
  width:100%!important;
  margin:0!important;
  padding:0!important;
  border:0!important;
}

/* Verification notice same width/placement as OMT */
.rlcc-pay #payment .payment_method_rafflelb_whish_manual .rl-whish-notice{
  display:flex!important;
  flex-direction:column!important;
  gap:4px!important;
  width:100%!important;
  margin:11px 0 0!important;
  padding:9px 11px!important;
  border:1px solid #23445b!important;
  border-left:3px solid #4ca8ff!important;
  border-radius:8px!important;
  background:#0b1820!important;
}

/* Mobile keeps same stacked structure, just slightly tighter */
@media(max-width:767px){
  .rlcc-pay #payment .payment_method_rafflelb_whish_manual .payment_box{
    margin-top:10px!important;
  }
  .rlcc-pay #payment .payment_method_rafflelb_whish_manual .rl-whish-steps{
    gap:8px!important;
    margin-bottom:10px!important;
  }
  .rlcc-pay #payment .payment_method_rafflelb_whish_manual .rl-whish-step{
    grid-template-columns:24px minmax(0,1fr)!important;
    gap:8px!important;
    padding:9px 10px!important;
    border:1px solid #303930!important;
    border-radius:9px!important;
    background:#0d120d!important;
  }
  .rlcc-pay #payment .payment_method_rafflelb_whish_manual .rl-whish-confirm{
    padding:10px!important;
    border:1px solid #303930!important;
    border-radius:9px!important;
    background:#0b0f0b!important;
  }
}

/* v4.6.2 — Whish Choose File text: force BLACK on lime */
.rlcc-pay #payment .payment_method_rafflelb_whish_manual input#rl_whish_screenshot[type="file"]::file-selector-button,
.rlcc-pay #payment .payment_method_rafflelb_whish_manual input#rl_whish_screenshot[type="file"]::-webkit-file-upload-button{
  -webkit-appearance:none!important;
  appearance:none!important;
  background:#baff00!important;
  background-color:#baff00!important;
  color:#070b05!important;
  -webkit-text-fill-color:#070b05!important;
  text-shadow:none!important;
  opacity:1!important;
  font-weight:900!important;
  border:0!important;
  box-shadow:none!important;
}


/* =========================================================
   v4.7.0 — MODE-AWARE CHECKOUT
   Direct Buy Now orders use conventional e-commerce language.
   ========================================================= */
body.rlcc-mode-buy_now .rlcc-hero{
	background:
		radial-gradient(circle at 92% 8%,rgba(186,255,0,.06),transparent 30%),
		linear-gradient(135deg,#121712 0%,#0b0f0b 100%);
}
body.rlcc-mode-buy_now .rlcc-order{
	border-top-color:var(--rl-lime)!important;
}
body.rlcc-mode-buy_now .rlcc-entry-badge{
	background:rgba(186,255,0,.07)!important;
	border-color:rgba(186,255,0,.34)!important;
	color:#d8ff5a!important;
}
body.rlcc-mode-buy_now .rlcc-item-price strong,
body.rlcc-mode-buy_now .rlcc-total-row.grand strong{
	color:var(--rl-lime)!important;
}


/* v4.7.1 — COD address block */
.rlcc-cod-address-wrap{
	display:none;
	margin-top:18px;
	padding:18px;
	border:1px solid rgba(186,255,0,.26);
	border-radius:14px;
	background:rgba(186,255,0,.025);
}
.rlcc-cod-address-wrap .rlcc-contact-intro{
	margin-bottom:14px;
}
.rlcc-cod-address-wrap .rlcc-contact-intro strong{
	display:block;
	color:#fff;
	font-size:14px;
}
.rlcc-cod-address-wrap .rlcc-contact-intro span{
	display:block;
	margin-top:4px;
	color:#aeb5aa;
	font-size:11px;
}
.rlcc-cod-address-wrap .form-row{
	margin-bottom:12px!important;
}


/* v4.7.4 — voucher / gift-card digital delivery notice */
.rlcc-voucher-delivery-note{
	display:flex;
	align-items:center;
	gap:13px;
	margin:0 0 18px;
	padding:15px 17px;
	border:1px solid rgba(186,255,0,.32);
	border-left:3px solid var(--rl-lime);
	border-radius:12px;
	background:rgba(186,255,0,.035);
}
.rlcc-voucher-delivery-icon{
	display:flex;
	align-items:center;
	justify-content:center;
	flex:0 0 28px;
	width:28px;
	height:28px;
	border-radius:50%;
	background:var(--rl-lime);
	color:#050805;
	font-weight:900;
}
.rlcc-voucher-delivery-note strong{
	display:block;
	color:var(--rl-lime);
	font-size:11px;
	letter-spacing:.14em;
}
.rlcc-voucher-delivery-note span{
	display:block;
	margin-top:4px;
	color:#e7ebe5;
	font-size:12px;
	line-height:1.55;
}


/* v4.7.5 — improve Digital Delivery notice readability */
.rlcc-voucher-delivery-note span{
	color:#caff16!important;
	font-weight:600!important;
	opacity:.92!important;
}


/* v4.7.6 — COD delivery fields are mandatory when COD is selected */
body.rlcc-cod-selected .rlcc-cod-address-wrap label .optional{
	display:none!important;
}
body.rlcc-cod-selected .rlcc-cod-address-wrap label:after{
	content:" *";
	color:#caff16;
	font-weight:900;
}


/* v4.7.7 — mixed direct-purchase fulfillment labels */
.rlcc-fulfillment-pill{
	display:inline-flex;
	align-items:center;
	margin-left:5px;
	padding:4px 8px;
	border-radius:999px;
	font-size:8px;
	font-weight:900;
	letter-spacing:.08em;
	line-height:1;
}
.rlcc-digital-pill{
	color:#caff16;
	border:1px solid rgba(202,255,22,.35);
	background:rgba(202,255,22,.05);
}
.rlcc-physical-pill{
	color:#f2f4ef;
	border:1px solid rgba(255,255,255,.14);
	background:rgba(255,255,255,.03);
}



/* =========================================================
   v4.8.1 — RAFFLELB ORDER CONFIRMATION
   ========================================================= */
body.rlcc-thankyou,
body.rlcc-thankyou .website-wrapper,
body.rlcc-thankyou .main-page-wrapper,
body.rlcc-thankyou .site-content,
body.rlcc-thankyou .woocommerce{
	background:#070907!important;
	color:#f7f8f5!important;
}
body.rlcc-thankyou .main-page-wrapper{padding-top:0!important}
body.rlcc-thankyou .container,
body.rlcc-thankyou .woocommerce-order{
	max-width:1180px!important;
}
body.rlcc-thankyou .woocommerce-order{
	margin:0 auto!important;
	padding:46px 0 72px!important;
}

/* Hide WooCommerce/theme legacy confirmation output — our panel replaces it. */
body.rlcc-thankyou .woocommerce-order > .woocommerce-notice,
body.rlcc-thankyou .woocommerce-order > .woocommerce-thankyou-order-received,
body.rlcc-thankyou .woocommerce-order > .woocommerce-order-overview,
body.rlcc-thankyou .woocommerce-order > .woocommerce-order-details,
body.rlcc-thankyou .woocommerce-order > .woocommerce-customer-details,
body.rlcc-thankyou .woocommerce-order > p:not(.rlcc-keep),
body.rlcc-thankyou .woocommerce-order > section:not(.rlcc-confirmation){
	display:none!important;
}

body.rlcc-thankyou .rlcc-confirmation{
	display:block!important;
	max-width:1080px;
	margin:0 auto;
	font-family:inherit;
}
.rlcc-confirm-hero{
	display:flex;
	align-items:center;
	gap:20px;
	padding:28px 30px;
	border:1px solid #2a3328;
	border-top:3px solid #baff00;
	border-radius:16px;
	background:
		radial-gradient(circle at 95% 0,rgba(186,255,0,.09),transparent 34%),
		linear-gradient(145deg,#111610,#0a0d09);
	box-shadow:0 24px 60px rgba(0,0,0,.24);
}
.rlcc-confirm-check{
	display:flex;
	flex:0 0 54px;
	width:54px;
	height:54px;
	align-items:center;
	justify-content:center;
	border-radius:50%;
	background:#baff00;
	color:#070907;
	font-size:26px;
	font-weight:900;
}
.rlcc-confirm-kicker{
	color:#baff00;
	font-size:10px;
	font-weight:900;
	letter-spacing:.17em;
	margin-bottom:6px;
}
.rlcc-confirm-copy h1{
	margin:0 0 7px!important;
	color:#fff!important;
	font-size:30px!important;
	line-height:1.15!important;
	font-weight:900!important;
}
.rlcc-confirm-copy p{
	margin:0!important;
	color:#a7b0a3!important;
	font-size:13px!important;
	line-height:1.6!important;
}
.rlcc-confirm-meta{
	display:grid;
	grid-template-columns:repeat(4,1fr);
	gap:1px;
	margin-top:16px;
	overflow:hidden;
	border:1px solid #242c23;
	border-radius:13px;
	background:#242c23;
}
.rlcc-confirm-meta>div{
	padding:17px 18px;
	background:#0d110c;
}
.rlcc-confirm-meta span,
.rlcc-confirm-contact span,
.rlcc-confirm-raffle span{
	display:block;
	margin-bottom:5px;
	color:#7f8a7c;
	font-size:9px;
	font-weight:900;
	letter-spacing:.13em;
}
.rlcc-confirm-meta strong,
.rlcc-confirm-contact strong{
	display:block;
	color:#fff;
	font-size:13px;
	font-weight:800;
}
.rlcc-confirm-fulfillment{
	display:grid;
	grid-template-columns:repeat(2,minmax(0,1fr));
	gap:12px;
	margin-top:16px;
}
.rlcc-confirm-note{
	padding:17px 18px;
	border:1px solid #2a3229;
	border-left:3px solid #74806f;
	border-radius:12px;
	background:#0c100c;
}
.rlcc-confirm-note.rlcc-confirm-digital{border-left-color:#baff00}
.rlcc-confirm-note-tag{
	display:block;
	margin-bottom:6px;
	color:#baff00;
	font-size:9px;
	font-weight:900;
	letter-spacing:.13em;
}
.rlcc-confirm-note strong{
	display:block;
	margin-bottom:5px;
	color:#fff;
	font-size:13px;
}
.rlcc-confirm-note small{
	display:block;
	color:#98a394;
	font-size:11px;
	line-height:1.55;
}
.rlcc-confirm-mixed{
	grid-column:1/-1;
	padding:11px 14px;
	border-radius:9px;
	background:rgba(186,255,0,.06);
	color:#ccef69;
	font-size:11px;
}
.rlcc-confirm-raffle{
	margin-top:16px;
	padding:18px;
	border:1px solid #33402e;
	border-left:3px solid #baff00;
	border-radius:12px;
	background:#0d120c;
}
.rlcc-confirm-raffle strong{
	display:block;
	color:#baff00;
	font-size:26px;
	line-height:1;
}
.rlcc-confirm-raffle small{
	display:block;
	margin-top:6px;
	color:#98a394;
	font-size:11px;
}
.rlcc-confirm-items{
	margin-top:16px;
	padding:24px;
	border:1px solid #252e24;
	border-radius:16px;
	background:#0c100c;
}
.rlcc-confirm-section-head{
	display:flex;
	justify-content:space-between;
	align-items:center;
	padding-bottom:14px;
	border-bottom:1px solid #252e24;
}
.rlcc-confirm-section-head span{
	color:#baff00;
	font-size:10px;
	font-weight:900;
	letter-spacing:.14em;
}
.rlcc-confirm-section-head strong{
	color:#7f8a7c;
	font-size:9px;
	letter-spacing:.10em;
}
.rlcc-confirm-item{
	display:grid;
	grid-template-columns:82px minmax(0,1fr) auto;
	gap:16px;
	align-items:center;
	padding:18px 0;
	border-bottom:1px solid #202820;
}
.rlcc-confirm-thumb{
	width:82px;
	height:82px;
	overflow:hidden;
	border:1px solid #303a2e;
	border-radius:12px;
	background:#fff;
}
.rlcc-confirm-thumb img{
	width:100%!important;
	height:100%!important;
	object-fit:contain!important;
}
.rlcc-confirm-item-copy h3{
	margin:0 0 9px!important;
	color:#fff!important;
	font-size:14px!important;
	line-height:1.3!important;
}
.rlcc-confirm-pills{
	display:flex;
	flex-wrap:wrap;
	gap:6px;
}
.rlcc-confirm-pills span{
	padding:5px 7px;
	border:1px solid #30382f;
	border-radius:999px;
	color:#aab3a6;
	font-size:8px;
	font-weight:900;
	letter-spacing:.07em;
}
.rlcc-confirm-pills span.is-digital{
	border-color:rgba(186,255,0,.35);
	color:#baff00;
	background:rgba(186,255,0,.05);
}
.rlcc-confirm-price{
	color:#fff;
	font-size:14px;
	font-weight:900;
	white-space:nowrap;
}
.rlcc-confirm-total{
	display:flex;
	align-items:center;
	justify-content:space-between;
	padding-top:18px;
}
.rlcc-confirm-total span{
	color:#909a8c;
	font-size:10px;
	font-weight:900;
	letter-spacing:.11em;
}
.rlcc-confirm-total strong{
	color:#baff00;
	font-size:21px;
	font-weight:900;
}
.rlcc-confirm-contact{
	display:grid;
	grid-template-columns:repeat(3,minmax(0,1fr));
	gap:10px;
	margin-top:16px;
}
.rlcc-confirm-contact>div{
	padding:15px 17px;
	border:1px solid #242d23;
	border-radius:11px;
	background:#0c100c;
}
.rlcc-confirm-actions{
	margin-top:18px;
	text-align:center;
}
.rlcc-confirm-actions a{
	display:inline-flex;
	align-items:center;
	justify-content:center;
	min-width:210px;
	min-height:48px;
	padding:0 22px;
	border-radius:999px;
	background:#baff00;
	color:#070907!important;
	font-size:10px;
	font-weight:900;
	letter-spacing:.11em;
	text-decoration:none!important;
}

@media(max-width:767px){
	body.rlcc-thankyou .woocommerce-order{padding:22px 12px 48px!important}
	.rlcc-confirm-hero{padding:21px 18px;align-items:flex-start}
	.rlcc-confirm-check{flex-basis:42px;width:42px;height:42px;font-size:20px}
	.rlcc-confirm-copy h1{font-size:23px!important}
	.rlcc-confirm-meta{grid-template-columns:repeat(2,1fr)}
	.rlcc-confirm-fulfillment{grid-template-columns:1fr}
	.rlcc-confirm-items{padding:18px}
	.rlcc-confirm-item{grid-template-columns:62px minmax(0,1fr);gap:12px}
	.rlcc-confirm-thumb{width:62px;height:62px}
	.rlcc-confirm-price{grid-column:2;margin-top:-5px}
	.rlcc-confirm-contact{grid-template-columns:1fr}
}


/* =========================================================
   v4.8.2 — CLEARER TOTALS / PRICE EMPHASIS
   ========================================================= */
body.rlcc-thankyou .rlcc-confirm-meta > div:last-child strong,
body.rlcc-thankyou .rlcc-confirm-meta > div:last-child strong *,
body.rlcc-thankyou .rlcc-confirm-meta > div:last-child .woocommerce-Price-amount,
body.rlcc-thankyou .rlcc-confirm-meta > div:last-child .woocommerce-Price-currencySymbol{
	color:#baff00!important;
	font-size:18px!important;
	font-weight:900!important;
	line-height:1.15!important;
	white-space:nowrap!important;
}

body.rlcc-thankyou .rlcc-confirm-price,
body.rlcc-thankyou .rlcc-confirm-price *,
body.rlcc-thankyou .rlcc-confirm-price .woocommerce-Price-amount,
body.rlcc-thankyou .rlcc-confirm-price .woocommerce-Price-currencySymbol{
	color:#fff!important;
	font-size:16px!important;
	font-weight:900!important;
	line-height:1.2!important;
	white-space:nowrap!important;
}

body.rlcc-thankyou .rlcc-confirm-total{
	margin-top:2px;
	padding-top:18px;
	border-top:1px solid #293128;
}

body.rlcc-thankyou .rlcc-confirm-total span{
	color:#d9ded6!important;
	font-size:11px!important;
	font-weight:900!important;
	letter-spacing:.13em!important;
}

body.rlcc-thankyou .rlcc-confirm-total strong,
body.rlcc-thankyou .rlcc-confirm-total strong *,
body.rlcc-thankyou .rlcc-confirm-total .woocommerce-Price-amount,
body.rlcc-thankyou .rlcc-confirm-total .woocommerce-Price-currencySymbol{
	color:#baff00!important;
	font-size:24px!important;
	font-weight:900!important;
	line-height:1!important;
	white-space:nowrap!important;
}

@media(max-width:767px){
	body.rlcc-thankyou .rlcc-confirm-meta > div:last-child strong,
	body.rlcc-thankyou .rlcc-confirm-meta > div:last-child strong *{
		font-size:16px!important;
	}
	body.rlcc-thankyou .rlcc-confirm-total strong,
	body.rlcc-thankyou .rlcc-confirm-total strong *{
		font-size:21px!important;
	}
}


/* v4.8.3 — confirmation totals: white and currency kept inline */
body.rlcc-thankyou .rlcc-confirm-meta > div:last-child strong,
body.rlcc-thankyou .rlcc-confirm-meta > div:last-child strong *,
body.rlcc-thankyou .rlcc-confirm-meta > div:last-child .woocommerce-Price-amount,
body.rlcc-thankyou .rlcc-confirm-meta > div:last-child .woocommerce-Price-currencySymbol,
body.rlcc-thankyou .rlcc-confirm-total strong,
body.rlcc-thankyou .rlcc-confirm-total strong *,
body.rlcc-thankyou .rlcc-confirm-total .woocommerce-Price-amount,
body.rlcc-thankyou .rlcc-confirm-total .woocommerce-Price-currencySymbol{
	color:#fff!important;
}

body.rlcc-thankyou .rlcc-confirm-meta > div:last-child strong,
body.rlcc-thankyou .rlcc-confirm-meta > div:last-child .woocommerce-Price-amount,
body.rlcc-thankyou .rlcc-confirm-total strong,
body.rlcc-thankyou .rlcc-confirm-total .woocommerce-Price-amount{
	display:inline-flex!important;
	flex-wrap:nowrap!important;
	align-items:baseline!important;
	gap:4px!important;
	width:auto!important;
	white-space:nowrap!important;
	word-break:keep-all!important;
	overflow-wrap:normal!important;
}

body.rlcc-thankyou .woocommerce-Price-currencySymbol{
	display:inline!important;
	white-space:nowrap!important;
}


/* =========================================================
   v4.8.4 — RAFFLE ENTRY CONFIRMATION
   ========================================================= */
.rlcc-confirm-raffle-grid{
	display:grid;
	grid-template-columns:repeat(2,minmax(0,1fr));
	gap:12px;
	margin-top:16px;
}
.rlcc-confirm-raffle-grid .rlcc-confirm-raffle{
	margin-top:0;
	min-height:118px;
}
.rlcc-confirm-raffle-count strong{
	font-size:34px!important;
}
.rlcc-confirm-raffle-status strong{
	color:#fff!important;
	font-size:18px!important;
	text-transform:uppercase;
}
.rlcc-raffle-confirm-help{
	display:grid;
	grid-template-columns:repeat(2,minmax(0,1fr));
	gap:10px;
	margin-top:18px;
	padding-top:18px;
	border-top:1px solid #252e24;
}
.rlcc-raffle-confirm-help>div{
	padding:14px 15px;
	border:1px solid #2a3328;
	border-radius:10px;
	background:rgba(255,255,255,.015);
}
.rlcc-raffle-confirm-help strong{
	display:block;
	margin-bottom:5px;
	color:#baff00;
	font-size:9px;
	font-weight:900;
	letter-spacing:.12em;
}
.rlcc-raffle-confirm-help span{
	display:block;
	color:#9aa495;
	font-size:11px;
	line-height:1.55;
}
body.rlcc-thankyou .rlcc-confirmation .rlcc-confirm-total strong,
body.rlcc-thankyou .rlcc-confirmation .rlcc-confirm-total strong *{
	color:#fff!important;
}
@media(max-width:767px){
	.rlcc-confirm-raffle-grid,
	.rlcc-raffle-confirm-help{
		grid-template-columns:1fr;
	}
}

CSS;
 wp_add_inline_style('rlcc-v3',$css);
 $vars=':root{';
 if($points_logo){ $vars.='--rlcc-points-logo:url("'.esc_url($points_logo).'");'; }
 $vars.='--rlcc-whish-logo:url("'.esc_url($whish_logo).'");';
 $vars.='--rlcc-omt-logo:url("'.esc_url($omt_logo).'");';
 $vars.='}';
 wp_add_inline_style('rlcc-v3',$vars);

},999);


add_action('wp_footer', function(){
 if(!function_exists('is_order_received_page') || !is_order_received_page()) return;
 ?>
 <script>
 (function(){
   function cleanLegacyConfirmation(){
     var root=document.querySelector('.woocommerce-order');
     if(!root) return;

     var phrases=[
       'Thanks for using C&E online',
       'Thank you. Your order has been received.'
     ];

     Array.prototype.slice.call(root.querySelectorAll('p,div,section')).forEach(function(el){
       if(el.closest('.rlcc-confirmation')) return;
       var text=(el.textContent||'').replace(/\s+/g,' ').trim();
       if(!text) return;
       phrases.forEach(function(phrase){
         if(text.indexOf(phrase)!==-1 && el.children.length<3){
           el.style.display='none';
         }
       });
     });
   }
   document.addEventListener('DOMContentLoaded',cleanLegacyConfirmation);
   setTimeout(cleanLegacyConfirmation,250);
 })();
 </script>
 <?php
}, 9999);




add_action('wp_footer', function(){
 if(!function_exists('is_checkout') || !is_checkout() || is_order_received_page()) return;
 ?>
 <script>
 (function($){
   function openContactDetails(){
     var details=document.getElementById('rlcc-entry-details');
     if(details) details.open=true;
   }
   if(document.readyState==='loading'){
     document.addEventListener('DOMContentLoaded',openContactDetails,{once:true});
   }else{
     openContactDetails();
   }
   $(document.body).on('checkout_error.rlccContactDetails',openContactDetails);
 })(jQuery);
 </script>
 <?php
}, 1000);




add_action('wp_footer', function(){
 if(!function_exists('is_checkout') || !is_checkout() || is_order_received_page()) return;
 if(!rlcc_is_tangible_direct_purchase()) return;
 ?>
 <script>
 (function(){
   function mountCodAddress(){
     var form=document.querySelector('form.checkout');
     if(!form) return;

     var customer=form.querySelector('#customer_details');
     if(!customer) return;

     var city=form.querySelector('#billing_city_field');
     var address=form.querySelector('#billing_address_1_field');
     if(!city || !address) return;

     var wrap=form.querySelector('.rlcc-cod-address-wrap');
     if(!wrap){
       wrap=document.createElement('div');
       wrap.className='rlcc-cod-address-wrap';
       wrap.innerHTML='<div class="rlcc-contact-intro"><strong>Delivery address</strong><span>Required only when Cash on Delivery is selected.</span></div>';
       customer.appendChild(wrap);
     }

     if(city.parentElement!==wrap) wrap.appendChild(city);
     if(address.parentElement!==wrap) wrap.appendChild(address);

     function update(){
       var selected=form.querySelector('input[name="payment_method"]:checked');
       var isCod=!!(selected && selected.value==='cod');

       wrap.style.display=isCod?'block':'none';
       document.body.classList.toggle('rlcc-cod-selected',isCod);

       var cityInput=form.querySelector('#billing_city');
       var addressInput=form.querySelector('#billing_address_1');
       if(cityInput){
         cityInput.required=isCod;
         cityInput.setAttribute('aria-required',isCod?'true':'false');
       }
       if(addressInput){
         addressInput.required=isCod;
         addressInput.setAttribute('aria-required',isCod?'true':'false');
       }

       [city,address].forEach(function(field){
         var optional=field.querySelector('label .optional');
         if(optional) optional.style.display=isCod?'none':'';
       });
     }

     form.querySelectorAll('input[name="payment_method"]').forEach(function(el){
       el.addEventListener('change',update);
     });

     update();
   }

   document.addEventListener('DOMContentLoaded',mountCodAddress);
   if(window.jQuery){
     jQuery(document.body).on('updated_checkout',function(){setTimeout(mountCodAddress,20);});
   }
 })();
 </script>
 <?php
}, 1100);

add_action('wp_head', function(){ if(!function_exists('is_checkout') || !is_checkout() || is_order_received_page()) return; ?>
<style id="rlcc-v320-premium">
/* v3.2.0 — premium compact checkout structure */
body.rlcc-v3 .rlcc-grid{grid-template-columns:minmax(0,1.5fr) minmax(390px,.9fr);gap:24px!important}
body.rlcc-v3 .rlcc-pay{padding:26px!important}
body.rlcc-v3 .rlcc-pay #payment ul.payment_methods>li{padding:17px 18px!important;margin-bottom:12px!important}
body.rlcc-v3 .rlcc-pay #payment .payment_box{padding:16px!important}
body.rlcc-v3 .rlcc-order{padding:24px!important;background:radial-gradient(circle at 100% 0,rgba(201,255,25,.075),transparent 38%),linear-gradient(180deg,#111611,#0a0e0a)!important;border-top:2px solid #c9ff19!important}
body.rlcc-v3 .rlcc-order-head{display:flex;justify-content:space-between;align-items:flex-end;gap:12px;padding-bottom:14px;border-bottom:1px solid #2c342c}
body.rlcc-v3 .rlcc-order-kicker{display:block;color:#c9ff19;font-size:9px;font-weight:900;letter-spacing:.18em;margin-bottom:5px}
body.rlcc-v3 .rlcc-entry-badge{white-space:nowrap;border:1px solid rgba(201,255,25,.35);background:rgba(201,255,25,.07);color:#d8ff5a;border-radius:999px;padding:6px 9px;font-size:9px;font-weight:900;letter-spacing:.08em}
body.rlcc-v3 .rlcc-order-list{border-top:0!important;margin-top:0!important}
body.rlcc-v3 .rlcc-order-item{grid-template-columns:112px minmax(0,1fr)!important;gap:16px!important;padding:20px 0!important;position:relative}
body.rlcc-v3 .rlcc-thumb{width:112px!important;height:112px!important;border-radius:14px!important;border:1px solid #4b5748!important;box-shadow:0 10px 30px rgba(0,0,0,.35)}
body.rlcc-v3 .rlcc-item-copy{min-width:0;align-self:center}
body.rlcc-v3 .rlcc-item-name{font-size:16px!important;line-height:1.28!important;font-weight:900!important;margin-bottom:10px}
body.rlcc-v3 .rlcc-item-meta{display:flex;gap:7px;flex-wrap:wrap;align-items:center}
body.rlcc-v3 .rlcc-qty,body.rlcc-v3 .rlcc-entry-pill{display:inline-flex!important;align-items:center;min-height:25px;padding:4px 8px!important;border:1px solid #364036;border-radius:999px;background:#121712;color:#c7cec4!important;font-size:9px!important;font-weight:800;letter-spacing:.04em;margin:0!important}
body.rlcc-v3 .rlcc-entry-pill{border-color:rgba(201,255,25,.28);color:#d8ff5a!important;background:rgba(201,255,25,.055)}
body.rlcc-v3 .rlcc-item-price{grid-column:1/-1;display:flex;justify-content:space-between;align-items:center;padding:12px 14px!important;border:1px solid #2f382f;border-radius:10px;background:#0c100c;color:#b9c1b6!important;font-size:10px!important;font-weight:700!important}
body.rlcc-v3 .rlcc-item-price strong{color:#fff;font-size:15px}
body.rlcc-v3 .rlcc-totals{padding-top:0!important}
body.rlcc-v3 .rlcc-total-row:not(.grand){display:none!important}
body.rlcc-v3 .rlcc-total-row.grand{margin-top:2px;padding:18px 0 8px!important;border-top:1px solid #303830!important;border-bottom:0!important;align-items:center!important}
body.rlcc-v3 .rlcc-total-row.grand span{font-size:11px!important;letter-spacing:.12em;text-transform:uppercase;color:#c2cac0!important}
body.rlcc-v3 .rlcc-total-row.grand strong{font-size:30px!important;line-height:1!important;letter-spacing:-.03em;color:#c9ff19!important}
body.rlcc-v3 .rlcc-order-security{margin-top:16px;padding:13px 14px;border:1px solid #303a2f;border-radius:10px;background:#0d120d}
body.rlcc-v3 .rlcc-order-security strong{display:flex;align-items:center;gap:8px;color:#fff;font-size:11px}
body.rlcc-v3 .rlcc-order-security strong:before{content:'✓';display:grid;place-items:center;width:24px;height:24px;border-radius:7px;background:rgba(201,255,25,.09);border:1px solid rgba(201,255,25,.35);color:#c9ff19}
body.rlcc-v3 .rlcc-order-security small{display:block;margin:5px 0 0 32px;color:#8f9a8d;font-size:9px;line-height:1.45}
body.rlcc-v3 .rlcc-secure{display:none!important}
@media(max-width:991px){body.rlcc-v3 .rlcc-grid{grid-template-columns:1fr!important}body.rlcc-v3 .rlcc-order-item{grid-template-columns:92px minmax(0,1fr)!important}body.rlcc-v3 .rlcc-thumb{width:92px!important;height:92px!important}}
@media(max-width:480px){body.rlcc-v3 .rlcc-order{padding:18px!important}body.rlcc-v3 .rlcc-order-head{align-items:flex-start;flex-direction:column}body.rlcc-v3 .rlcc-order-item{grid-template-columns:82px minmax(0,1fr)!important;gap:12px!important}body.rlcc-v3 .rlcc-thumb{width:82px!important;height:82px!important}body.rlcc-v3 .rlcc-item-name{font-size:14px!important}body.rlcc-v3 .rlcc-total-row.grand strong{font-size:26px!important}}


/* =========================================================
   v4.6.4 — CONTACT FIELD ALIGNMENT + DESKTOP TWO-COLUMN LOCK
   ========================================================= */

/* Force Contact Details fields into a predictable left/right order. */
.rlcc-details .woocommerce-billing-fields__field-wrapper{
	display:grid!important;
	grid-template-columns:minmax(0,1fr) minmax(0,1fr)!important;
	gap:12px 14px!important;
	align-items:start!important;
}

.rlcc-details #billing_first_name_field{
	grid-column:1!important;
	grid-row:auto!important;
}

.rlcc-details #billing_last_name_field{
	grid-column:2!important;
	grid-row:auto!important;
}

.rlcc-details #billing_country_field,
.rlcc-details #billing_email_field,
.rlcc-details #billing_phone_field,
.rlcc-details #billing_address_1_field,
.rlcc-details #billing_address_2_field{
	grid-column:1 / -1!important;
}

.rlcc-details .form-row{
	float:none!important;
	clear:none!important;
	width:100%!important;
	max-width:none!important;
	margin:0!important;
	padding:0!important;
}

/* Desktop: PAYMENT/CONTACT always left, YOUR ORDER always right. */
@media(min-width:900px){
	.rlcc-grid{
		display:grid!important;
		grid-template-columns:minmax(0,1.42fr) minmax(410px,.98fr)!important;
		gap:26px!important;
		align-items:start!important;
		width:100%!important;
	}

	.rlcc-left{
		grid-column:1!important;
		grid-row:1!important;
		min-width:0!important;
		width:100%!important;
	}

	.rlcc-right{
		grid-column:2!important;
		grid-row:1!important;
		min-width:0!important;
		width:100%!important;
		position:sticky!important;
		top:105px!important;
		align-self:start!important;
	}

	.rlcc-order{
		width:100%!important;
		margin:0!important;
	}
}

/* Tablet / mobile remains stacked, with order above payment on narrow screens. */
@media(max-width:899px){
	.rlcc-grid{
		display:flex!important;
		flex-direction:column!important;
		gap:18px!important;
	}
	.rlcc-right{
		order:1!important;
		position:static!important;
		width:100%!important;
	}
	.rlcc-left{
		order:2!important;
		width:100%!important;
	}
}

@media(max-width:767px){
	.rlcc-details .woocommerce-billing-fields__field-wrapper{
		grid-template-columns:1fr!important;
	}
	.rlcc-details #billing_first_name_field,
	.rlcc-details #billing_last_name_field,
	.rlcc-details #billing_country_field,
	.rlcc-details #billing_email_field,
	.rlcc-details #billing_phone_field,
	.rlcc-details #billing_address_1_field,
	.rlcc-details #billing_address_2_field{
		grid-column:1!important;
	}
}

</style>
<?php }, 999);


add_action('wp_footer', function(){
 if(!function_exists('is_checkout') || !is_checkout() || is_order_received_page()) return;
 ?>
 <script>
 (function($){
   function moveTurnstile(){
     var target=document.querySelector('.rlcc-order');
     if(!target) return;
     document.querySelectorAll('.cf-turnstile,[class*="turnstile"]').forEach(function(el){
       if(el.closest('.rlcc-order')) return;
       /* do not move hidden template clones */
       if(el.offsetParent===null && !el.querySelector('iframe')) return;
       target.appendChild(el);
     });
   }
   document.addEventListener('DOMContentLoaded',moveTurnstile);
   window.addEventListener('load',moveTurnstile);
   $(document.body).on('updated_checkout',function(){setTimeout(moveTurnstile,30);});
   setTimeout(moveTurnstile,500);
 })(jQuery);
 </script>
 <?php
}, 1001);

/*
 * Keep WooCommerce's real unavailable-gateway notice intact.  The status below
 * only masks that notice while the checkout payment fragment is being refreshed.
 */
add_action('wp_head', function(){
 if(!function_exists('is_checkout') || !is_checkout() || is_order_received_page()) return;
 ?>
<style id="rlcc-payment-loading-state">
.rlcc-payment-verification{
 display:flex;align-items:center;gap:9px;margin:0;padding:12px 14px;
 border:1px solid rgba(186,255,0,.28);border-radius:10px;
 background:rgba(186,255,0,.055);color:#dce6d5;font-size:13px;line-height:1.4;
}
.rlcc-payment-verification[hidden]{display:none!important}
.rlcc-payment-verification__spinner{
 width:15px;height:15px;flex:0 0 15px;border:2px solid rgba(186,255,0,.26);
 border-top-color:#baff00;border-radius:50%;animation:rlcc-payment-spin .7s linear infinite;
}
.rlcc-payment-loading #payment .woocommerce-info{display:none!important}
@keyframes rlcc-payment-spin{to{transform:rotate(360deg)}}
@media (prefers-reduced-motion:reduce){.rlcc-payment-verification__spinner{animation:none}}
</style>
<?php
}, 1002);

/* v4.8.13 — checkout Country / Region SelectWoo presentation only. */
add_action('wp_head', function(){
 if(!function_exists('is_checkout') || !is_checkout() || is_order_received_page()) return;
 ?>
<style id="rlcc-v4813-country-selectwoo">
body.rlcc-v3 #billing_country_field .select2-container{
 display:block!important;width:100%!important;
 font-family:var(--rl-font, "Manrope", sans-serif)!important;
}
body.rlcc-v3 #billing_country_field .select2-container--default .select2-selection--single{
 display:block!important;width:100%!important;height:46px!important;min-height:46px!important;
 border:1px solid #303930!important;border-radius:10px!important;
 background:#0a0e0a!important;color:#fff!important;box-shadow:none!important;
}
body.rlcc-v3 #billing_country_field .select2-container--default .select2-selection--single .select2-selection__rendered{
 display:flex!important;align-items:center!important;
 height:44px!important;min-height:44px!important;margin:0!important;padding:0 42px 0 13px!important;
 color:#fff!important;-webkit-text-fill-color:#fff!important;
 font-family:var(--rl-font, "Manrope", sans-serif)!important;
 font-size:16px!important;font-weight:500!important;line-height:1.3!important;
}
body.rlcc-v3 #billing_country_field .select2-container--default .select2-selection--single .select2-selection__placeholder{
 color:#9ea89b!important;-webkit-text-fill-color:#9ea89b!important;
}
body.rlcc-v3 #billing_country_field .select2-container--default .select2-selection--single .select2-selection__arrow{
 top:0!important;right:4px!important;width:34px!important;height:44px!important;
}
body.rlcc-v3 #billing_country_field .select2-container--default.select2-container--focus .select2-selection--single,
body.rlcc-v3 #billing_country_field .select2-container--default.select2-container--open .select2-selection--single{
 border-color:#baff00!important;box-shadow:0 0 0 1px rgba(186,255,0,.10)!important;
}

/* SelectWoo appends this panel beneath body, outside the checkout card. */
body.rlcc-v3 .select2-container--open .select2-dropdown{
 overflow:hidden!important;border:1px solid #394438!important;border-radius:10px!important;
 background:#0a0e0a!important;color:#f7f8f5!important;
 box-shadow:0 18px 44px rgba(0,0,0,.46)!important;
 font-family:var(--rl-font, "Manrope", sans-serif)!important;
}
body.rlcc-v3 .select2-container--open .select2-search--dropdown{
 padding:10px!important;border:0!important;border-bottom:1px solid #303930!important;
 background:#0d120d!important;
}
body.rlcc-v3 .select2-container--open .select2-search--dropdown .select2-search__field{
 width:100%!important;height:42px!important;margin:0!important;padding:0 12px!important;
 border:1px solid #394438!important;border-radius:8px!important;
 background:#070a07!important;color:#fff!important;-webkit-text-fill-color:#fff!important;
 caret-color:#baff00!important;box-shadow:none!important;outline:none!important;
 font-family:var(--rl-font, "Manrope", sans-serif)!important;
 font-size:16px!important;font-weight:500!important;line-height:1.3!important;
}
body.rlcc-v3 .select2-container--open .select2-search--dropdown .select2-search__field:focus{
 border-color:#baff00!important;box-shadow:0 0 0 1px rgba(186,255,0,.12)!important;
}
body.rlcc-v3 .select2-container--open :is(.select2-results,.select2-results__options){
 background:#0a0e0a!important;color:#f7f8f5!important;
}
body.rlcc-v3 .select2-container--open .select2-results__options{
 scrollbar-color:#596456 #0a0e0a!important;scrollbar-width:thin!important;
}
body.rlcc-v3 .select2-container--open .select2-results__option{
 min-height:40px!important;padding:10px 13px!important;
 background:#0a0e0a!important;color:#e8ece5!important;-webkit-text-fill-color:#e8ece5!important;
 font-family:var(--rl-font, "Manrope", sans-serif)!important;
 font-size:15px!important;font-weight:500!important;line-height:1.35!important;
}
body.rlcc-v3 .select2-container--open .select2-results__option[aria-selected="true"],
body.rlcc-v3 .select2-container--open .select2-results__option[data-selected="true"]{
 background:#17200d!important;color:#baff00!important;-webkit-text-fill-color:#baff00!important;
 font-weight:700!important;
}
body.rlcc-v3 .select2-container--open .select2-results__option--highlighted[aria-selected],
body.rlcc-v3 .select2-container--open .select2-results__option--highlighted[data-selected]{
 background:#baff00!important;color:#071000!important;-webkit-text-fill-color:#071000!important;
 font-weight:700!important;
}
body.rlcc-v3 .select2-container--open .select2-results__message{
 background:#0a0e0a!important;color:#b7c0b4!important;-webkit-text-fill-color:#b7c0b4!important;
}
</style>
<?php
}, 2200);

/* v4.8.10 — checkout-owned typography/readability only. */
add_action('wp_head', function(){
 if(!function_exists('is_checkout') || !is_checkout() || is_order_received_page()) return;
 ?>
<style id="rlcc-v4810-typography">
body.rlcc-v3 .rlcc-app,
body.rlcc-v3 .rlcc-hero,
body.rlcc-v3 .rlcc-coupon,
body.rlcc-v3 .rlcc-details,
body.rlcc-v3 .rlcc-contact-intro,
body.rlcc-v3 .rlcc-pay,
body.rlcc-v3 .rlcc-order,
body.rlcc-v3 #payment,
body.rlcc-v3 #order_review,
body.rlcc-v3 .woocommerce-form-coupon,
body.rlcc-v3 :is(.woocommerce-error,.woocommerce-info,.woocommerce-message){
 font-family:var(--rl-font, "Manrope", sans-serif)!important;
}
body.rlcc-v3 .rlcc-hero h1{
 font-family:var(--rl-font, "Manrope", sans-serif)!important;
 font-size:38px!important;font-weight:800!important;line-height:1.08!important;letter-spacing:-.035em!important;
}
body.rlcc-v3 .rlcc-kicker{
 font-family:var(--rl-font, "Manrope", sans-serif)!important;
 font-size:11px!important;font-weight:800!important;line-height:1.35!important;letter-spacing:.12em!important;
}
body.rlcc-v3 .rlcc-hero p{
 font-family:var(--rl-font, "Manrope", sans-serif)!important;
 font-size:15px!important;font-weight:450!important;line-height:1.6!important;
}
body.rlcc-v3 .rlcc-trust,
body.rlcc-v3 .rlcc-trust span{
 font-family:var(--rl-font, "Manrope", sans-serif)!important;
 font-size:12px!important;font-weight:550!important;line-height:1.5!important;letter-spacing:.035em!important;
}
body.rlcc-v3 .rlcc-heading,
body.rlcc-v3 .rlcc-details summary,
body.rlcc-v3 :is(.woocommerce-billing-fields,.woocommerce-shipping-fields,.woocommerce-additional-fields)>h3{
 font-family:var(--rl-font, "Manrope", sans-serif)!important;
 font-size:23px!important;font-weight:800!important;line-height:1.18!important;letter-spacing:-.02em!important;
}
body.rlcc-v3 .rlcc-muted{
 font-family:var(--rl-font, "Manrope", sans-serif)!important;
 font-size:14px!important;font-weight:450!important;line-height:1.55!important;
}
body.rlcc-v3 .rlcc-contact-intro strong{
 font-family:var(--rl-font, "Manrope", sans-serif)!important;
 font-size:16px!important;font-weight:750!important;line-height:1.35!important;
}
body.rlcc-v3 .rlcc-contact-intro span{
 font-family:var(--rl-font, "Manrope", sans-serif)!important;
 font-size:13px!important;font-weight:450!important;line-height:1.5!important;
}
body.rlcc-v3 .rlcc-details .form-row label,
body.rlcc-v3 .rlcc-pay .form-row label,
body.rlcc-v3 .woocommerce-form-coupon .form-row label{
 font-family:var(--rl-font, "Manrope", sans-serif)!important;
 font-size:13px!important;font-weight:700!important;line-height:1.4!important;letter-spacing:0!important;
}
body.rlcc-v3 .rlcc-details :is(input.input-text,textarea,select),
body.rlcc-v3 .rlcc-pay :is(input.input-text,input[type="text"],input[type="email"],input[type="tel"],textarea,select),
body.rlcc-v3 .woocommerce-form-coupon :is(input.input-text,input[type="text"]),
body.rlcc-v3 .select2-container .select2-selection__rendered{
 font-family:var(--rl-font, "Manrope", sans-serif)!important;
 font-size:16px!important;font-weight:500!important;line-height:1.45!important;
}
body.rlcc-v3 .rlcc-details :is(input,textarea)::placeholder,
body.rlcc-v3 .rlcc-pay :is(input,textarea)::placeholder,
body.rlcc-v3 .woocommerce-form-coupon :is(input,textarea)::placeholder{
 font-family:var(--rl-font, "Manrope", sans-serif)!important;
 font-size:15px!important;font-weight:450!important;
}
body.rlcc-v3 .rlcc-pay #payment ul.payment_methods>li>label{
 font-family:var(--rl-font, "Manrope", sans-serif)!important;
 font-size:16px!important;font-weight:700!important;line-height:1.35!important;letter-spacing:0!important;
}
body.rlcc-v3 .rlcc-pay #payment .payment_box,
body.rlcc-v3 .rlcc-pay #payment .payment_box p,
body.rlcc-v3 .rlcc-pay :is(.rl-points-label,.rl-points-status,.rl-points-note,.rl-whish-box p,.rl-omt-pay-box p){
 font-family:var(--rl-font, "Manrope", sans-serif)!important;
 font-size:13px!important;font-weight:450!important;line-height:1.55!important;
}
body.rlcc-v3 .rlcc-pay .rafflelb-points-payment-box .rl-points-value{
 font-family:var(--rl-font, "Manrope", sans-serif)!important;
 font-size:16px!important;font-weight:750!important;line-height:1.35!important;
}
body.rlcc-v3 .rlcc-item-name{
 font-family:var(--rl-font, "Manrope", sans-serif)!important;
 font-size:16px!important;font-weight:700!important;line-height:1.35!important;letter-spacing:-.01em!important;
}
body.rlcc-v3 :is(.rlcc-order-kicker,.rlcc-entry-badge,.rlcc-qty,.rlcc-entry-pill,.rlcc-item-price>span){
 font-family:var(--rl-font, "Manrope", sans-serif)!important;
 font-size:11px!important;font-weight:700!important;line-height:1.35!important;letter-spacing:.055em!important;
}
body.rlcc-v3 .rlcc-item-price,
body.rlcc-v3 .rlcc-item-price strong,
body.rlcc-v3 .rlcc-item-price strong *{
 font-family:var(--rl-font, "Manrope", sans-serif)!important;
 font-size:15px!important;font-weight:650!important;line-height:1.4!important;
}
body.rlcc-v3 .rlcc-total-row.grand span{
 font-family:var(--rl-font, "Manrope", sans-serif)!important;
 font-size:12px!important;font-weight:750!important;line-height:1.35!important;letter-spacing:.08em!important;
}
body.rlcc-v3 .rlcc-total-row.grand strong,
body.rlcc-v3 .rlcc-total-row.grand strong *,
body.rlcc-v3 .rlcc-total-row.grand .woocommerce-Price-amount,
body.rlcc-v3 .rlcc-total-row.grand .woocommerce-Price-currencySymbol{
 font-family:var(--rl-font, "Manrope", sans-serif)!important;
 font-size:26px!important;font-weight:800!important;line-height:1!important;letter-spacing:-.025em!important;
}
body.rlcc-v3 .rlcc-order-security strong{
 font-family:var(--rl-font, "Manrope", sans-serif)!important;
 font-size:12px!important;font-weight:700!important;line-height:1.4!important;
}
body.rlcc-v3 .rlcc-order-security small{
 font-family:var(--rl-font, "Manrope", sans-serif)!important;
 font-size:11px!important;font-weight:450!important;line-height:1.5!important;
}
body.rlcc-v3 #place_order{
 font-family:var(--rl-font, "Manrope", sans-serif)!important;
 font-size:15px!important;font-weight:800!important;line-height:1.35!important;letter-spacing:.055em!important;
 color:#071000!important;-webkit-text-fill-color:#071000!important;
}
body.rlcc-v3 .rlcc-coupon :is(.woocommerce-info,a,input,button),
body.rlcc-v3 .woocommerce-form-coupon :is(input,button),
body.rlcc-v3 :is(.woocommerce-error,.woocommerce-info,.woocommerce-message){
 font-family:var(--rl-font, "Manrope", sans-serif)!important;
 font-size:14px!important;font-weight:600!important;line-height:1.5!important;
}
body.rlcc-v3 .rlcc-payment-verification{
 font-family:var(--rl-font, "Manrope", sans-serif)!important;
 font-size:13px!important;font-weight:550!important;line-height:1.45!important;
}
@media(max-width:767px){
 body.rlcc-v3 .rlcc-hero h1{font-size:clamp(28px,8vw,32px)!important;line-height:1.1!important;letter-spacing:-.032em!important;overflow-wrap:anywhere!important}
 body.rlcc-v3 .rlcc-heading,
 body.rlcc-v3 .rlcc-details summary,
 body.rlcc-v3 :is(.woocommerce-billing-fields,.woocommerce-shipping-fields,.woocommerce-additional-fields)>h3{font-size:20px!important;line-height:1.2!important}
 body.rlcc-v3 .rlcc-item-name{font-size:15px!important}
 body.rlcc-v3 .rlcc-total-row.grand strong,
 body.rlcc-v3 .rlcc-total-row.grand strong *,
 body.rlcc-v3 .rlcc-total-row.grand .woocommerce-Price-amount,
 body.rlcc-v3 .rlcc-total-row.grand .woocommerce-Price-currencySymbol{font-size:25px!important}
 body.rlcc-v3 #place_order{font-size:14px!important}
}
</style>
<?php
}, 2000);

add_action('wp_footer', function(){
 if(!function_exists('is_checkout') || !is_checkout() || is_order_received_page()) return;
 ?>
<script>
(function($){
  var loading = true;

  function syncPaymentLoadingState(){
    var review = document.getElementById('order_review');
    if(!review) return;

    var payment = review.querySelector('#payment');
    var status = review.querySelector('.rlcc-payment-verification');
    var hasGateway = !!(payment && payment.querySelector('input[name="payment_method"], ul.payment_methods > li'));
    var notice = payment ? payment.querySelector('.woocommerce-info') : null;
    var pendingUnavailable = !hasGateway && (loading || hasPendingTurnstile());

    review.classList.toggle('rlcc-payment-loading', pendingUnavailable);
    review.setAttribute('aria-busy', pendingUnavailable ? 'true' : 'false');
    if(status) status.hidden = !pendingUnavailable;
    if(notice) notice.classList.toggle('rlcc-payment-unavailable-pending', pendingUnavailable);
  }

  function setLoading(nextLoading){
    loading = nextLoading;
    syncPaymentLoadingState();
  }

  function hasPendingTurnstile(){
    var widgets = document.querySelectorAll('.cf-turnstile,[class*="turnstile"]');
    if(!widgets.length) return false;

    var responses = document.querySelectorAll('input[name^="cf-turnstile-response"],textarea[name^="cf-turnstile-response"]');
    if(responses.length < widgets.length) return true;

    return Array.prototype.some.call(responses, function(response){
      return !String(response.value || response.getAttribute('value') || '').trim();
    });
  }

  syncPaymentLoadingState();

  document.addEventListener('DOMContentLoaded', function(){
    syncPaymentLoadingState();

    var review = document.getElementById('order_review');
    if(window.MutationObserver){
      new MutationObserver(syncPaymentLoadingState).observe(document.body, {childList:true, subtree:true, attributes:true, attributeFilter:['value']});
    }
    document.addEventListener('input', syncPaymentLoadingState, true);
    document.addEventListener('change', syncPaymentLoadingState, true);
  });

  $(document.body).on('update_checkout.rlccPaymentLoading', function(){ setLoading(true); });
  $(document.body).on('updated_checkout.rlccPaymentLoading checkout_error.rlccPaymentLoading', function(){ setLoading(false); });
})(jQuery);
</script>
<?php
}, 1002);
