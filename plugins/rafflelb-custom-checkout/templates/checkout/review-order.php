<?php
defined('ABSPATH') || exit;
$rlcc_mode = function_exists('rlcc_get_checkout_mode') ? rlcc_get_checkout_mode() : 'raffle_entry';
$rlcc_direct = ($rlcc_mode === 'buy_now');
$rlcc_mixed = ($rlcc_mode === 'mixed');
?>
<div class="woocommerce-checkout-review-order-table rlcc-native-fragment">
 <div class="rlcc-order-list">
 <?php
 $visible_items=0;
 foreach(WC()->cart->get_cart() as $cart_item_key=>$cart_item):
  $_product=apply_filters('woocommerce_cart_item_product',$cart_item['data'],$cart_item,$cart_item_key);
  if(!$_product || !$_product->exists() || $cart_item['quantity']<=0 || !apply_filters('woocommerce_checkout_cart_item_visible',true,$cart_item,$cart_item_key)) continue;
  $visible_items += (int)$cart_item['quantity'];

  $item_mode = isset($cart_item['_rafflelb_purchase_mode']) ? sanitize_key($cart_item['_rafflelb_purchase_mode']) : 'raffle_entry';
  $item_direct = ($item_mode === 'buy_now');
 ?>
  <div class="rlcc-order-item <?php echo esc_attr(apply_filters('woocommerce_cart_item_class','cart_item',$cart_item,$cart_item_key)); ?>">
   <div class="rlcc-thumb"><?php echo wp_kses_post($_product->get_image('woocommerce_thumbnail')); ?></div>

   <div class="rlcc-item-copy">
    <div class="rlcc-item-name"><?php echo wp_kses_post(apply_filters('woocommerce_cart_item_name',$_product->get_name(),$cart_item,$cart_item_key)); ?></div>
    <div class="rlcc-item-meta">
     <span class="rlcc-qty">QTY <?php echo esc_html($cart_item['quantity']); ?></span>
     <?php if($item_direct): ?>
      <span class="rlcc-entry-pill">DIRECT PURCHASE</span>
      <?php if(function_exists('rlcc_item_is_voucher_or_gift_card') && rlcc_item_is_voucher_or_gift_card($_product)): ?>
       <span class="rlcc-fulfillment-pill rlcc-digital-pill">DIGITAL DELIVERY</span>
      <?php else: ?>
       <span class="rlcc-fulfillment-pill rlcc-physical-pill">PHYSICAL DELIVERY</span>
      <?php endif; ?>
     <?php else: ?>
      <span class="rlcc-entry-pill"><?php echo esc_html($cart_item['quantity']); ?> RAFFLE <?php echo (int)$cart_item['quantity']===1?'ENTRY':'ENTRIES'; ?></span>
     <?php endif; ?>
    </div>
   </div>

   <div class="rlcc-item-price">
    <span><?php echo $item_direct ? 'PRODUCT PRICE' : 'ENTRY VALUE'; ?></span>
    <strong><?php echo wp_kses_post(WC()->cart->get_product_subtotal($_product,$cart_item['quantity'])); ?></strong>
   </div>
  </div>
 <?php endforeach; ?>
 </div>

 <div class="rlcc-totals">
  <?php foreach(WC()->cart->get_coupons() as $code=>$coupon): ?>
   <div class="rlcc-total-row"><span><?php wc_cart_totals_coupon_label($coupon); ?></span><strong><?php wc_cart_totals_coupon_html($coupon); ?></strong></div>
  <?php endforeach; ?>

  <?php foreach(WC()->cart->get_fees() as $fee): ?>
   <div class="rlcc-total-row"><span><?php echo esc_html($fee->name); ?></span><strong><?php wc_cart_totals_fee_html($fee); ?></strong></div>
  <?php endforeach; ?>

  <div class="rlcc-total-row grand"><span>ORDER TOTAL</span><strong><?php wc_cart_totals_order_total_html(); ?></strong></div>
 </div>

 <?php if($rlcc_direct): ?>
  <div class="rlcc-order-security">
   <strong>SECURE PRODUCT CHECKOUT</strong>
   <small>Your product order is confirmed after payment is successfully processed.</small>
  </div>
 <?php elseif($rlcc_mixed): ?>
  <div class="rlcc-order-security">
   <strong>SECURE CHECKOUT</strong>
   <small>Your order is confirmed after payment is successfully processed.</small>
  </div>
 <?php else: ?>
  <div class="rlcc-order-security">
   <strong>SECURE ENTRY CHECKOUT</strong>
   <small>Your entry is assigned only after payment is confirmed.</small>
  </div>
 <?php endif; ?>

 <?php do_action('woocommerce_review_order_after_order_total'); ?>
</div>
