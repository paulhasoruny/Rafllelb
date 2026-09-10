<?php
defined('ABSPATH') || exit;
$rlcc_mode = function_exists('rlcc_get_checkout_mode') ? rlcc_get_checkout_mode() : 'raffle_entry';
$rlcc_direct = ($rlcc_mode === 'buy_now');
$rlcc_mixed = ($rlcc_mode === 'mixed');
?>
<div class="rlcc-wrap">
 <section class="rlcc-hero">
  <?php if($rlcc_direct): ?>
   <div class="rlcc-kicker">SECURE CHECKOUT</div>
   <h1>Complete your purchase</h1>
   <p>Confirm your contact details and payment to place your product order securely.</p>
   <div class="rlcc-trust"><span>Secure checkout</span><span>Direct product purchase</span><span>Order confirmation after payment</span></div>
  <?php elseif($rlcc_mixed): ?>
   <div class="rlcc-kicker">SECURE CHECKOUT</div>
   <h1>Complete your order</h1>
   <p>Confirm your details and payment for the items in your order.</p>
   <div class="rlcc-trust"><span>Secure checkout</span><span>Order review</span><span>Payment confirmation</span></div>
  <?php else: ?>
   <div class="rlcc-kicker">SECURE ENTRY CHECKOUT</div>
   <h1>Complete your entry</h1>
   <p>Confirm your details and payment. Your unique raffle entries are assigned automatically after successful payment.</p>
   <div class="rlcc-trust"><span>Secure checkout</span><span>Live entry reservation</span><span>Automatic entry assignment</span></div>
  <?php endif; ?>
 </section>

 <?php if(function_exists('rlcc_direct_cart_has_digital_voucher') && rlcc_direct_cart_has_digital_voucher()): ?>
 <div class="rlcc-voucher-delivery-note">
  <div class="rlcc-voucher-delivery-icon">✓</div>
  <div>
   <strong>DIGITAL DELIVERY</strong>
   <span>Voucher and gift-card items in this order will be delivered to the WhatsApp number and email address provided at checkout after your order is confirmed.</span>
  </div>
 </div>
 <?php endif; ?>

 <div class="rlcc-coupon"><?php do_action('woocommerce_before_checkout_form',$checkout); ?></div>

 <?php if(!$checkout->is_registration_enabled() && $checkout->is_registration_required() && !is_user_logged_in()): ?>
  <?php echo esc_html(apply_filters('woocommerce_checkout_must_be_logged_in_message',__('You must be logged in to checkout.','woocommerce'))); ?>
 <?php else: ?>
 <form name="checkout" method="post" class="checkout woocommerce-checkout" action="<?php echo esc_url(wc_get_checkout_url()); ?>" enctype="multipart/form-data" aria-label="<?php echo esc_attr__('Checkout','woocommerce'); ?>">
  <div class="rlcc-grid">
   <main class="rlcc-left">
    <section class="rlcc-card rlcc-pay">
     <h2 class="rlcc-heading">PAYMENT</h2>
     <p class="rlcc-muted">Choose your preferred payment method.</p>
     <div id="order_review" class="woocommerce-checkout-review-order rlcc-payment-loading" aria-busy="true">
      <div class="rlcc-payment-verification" role="status" aria-live="polite" aria-atomic="true">
       <span class="rlcc-payment-verification__spinner" aria-hidden="true"></span>
       <span>Verifying secure checkout&hellip;</span>
      </div>
      <?php wc_get_template('checkout/payment.php',array('checkout'=>$checkout)); ?>
     </div>
    </section>

    <?php if($checkout->get_checkout_fields()): ?>
    <details class="rlcc-card rlcc-details" id="rlcc-entry-details" open>
     <summary>CONTACT DETAILS</summary>
     <div class="rlcc-details-body">
      <div class="rlcc-contact-intro">
       <strong>Your details</strong>
       <span><?php echo $rlcc_direct ? 'Enter the information we will use for your product order.' : ($rlcc_mixed ? 'Enter the information we will use for your order.' : 'Enter the information we will use for your order and raffle entry.'); ?></span>
      </div>
      <?php do_action('woocommerce_checkout_before_customer_details'); ?>
      <div id="customer_details">
       <?php do_action('woocommerce_checkout_billing'); ?>
       <?php do_action('woocommerce_checkout_shipping'); ?>
      </div>
      <?php do_action('woocommerce_checkout_after_customer_details'); ?>
     </div>
    </details>
    <?php endif; ?>

   </main>

   <aside class="rlcc-right">
    <section class="rlcc-card rlcc-order">
     <div class="rlcc-order-head">
      <h2 class="rlcc-heading">YOUR ORDER</h2>
      <?php if($rlcc_direct): ?>
       <span class="rlcc-entry-badge">DIRECT PURCHASE</span>
      <?php elseif($rlcc_mixed): ?>
       <span class="rlcc-entry-badge">ORDER</span>
      <?php else: ?>
       <span class="rlcc-entry-badge">RAFFLE ENTRY</span>
      <?php endif; ?>
     </div>
     <div class="rlcc-order-fragment"><?php wc_get_template('checkout/review-order.php'); ?></div>
    </section>
   </aside>
  </div>
 </form>
 <?php do_action('woocommerce_after_checkout_form',$checkout); ?>
 <?php endif; ?>
</div>
