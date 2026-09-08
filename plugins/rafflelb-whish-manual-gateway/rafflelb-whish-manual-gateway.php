<?php
/**
 * Plugin Name: RaffleLB Whish Manual Gateway
 * Description: Manual Whish Money payment gateway for WooCommerce with transaction reference and/or payment screenshot verification.
 * Version: 1.1.4
 * Author: RaffleLB
 * Text Domain: rafflelb-whish-manual
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'RL_WHISH_VERSION', '1.0.1' );

add_action( 'before_woocommerce_init', function () {
    if ( class_exists( '\\Automattic\\WooCommerce\\Utilities\\FeaturesUtil' ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
    }
} );

add_action( 'plugins_loaded', function () {
    if ( ! class_exists( 'WC_Payment_Gateway' ) ) return;

    class WC_Gateway_RaffleLB_Whish_Manual extends WC_Payment_Gateway {
        public function __construct() {
            $this->id = 'rafflelb_whish_manual';
            $this->icon = '';
            $this->has_fields = true;
            $this->method_title = __( 'Whish Checkout – Manual Gateway', 'rafflelb-whish-manual' );
            $this->method_description = __( 'Accept Whish payments that are manually verified using a transaction reference and/or payment screenshot.', 'rafflelb-whish-manual' );
            $this->supports = array( 'products' );

            $this->init_form_fields();
            $this->init_settings();

            $this->title = $this->get_option( 'title', 'Pay with Whish Money' );
            $this->description = $this->get_option( 'description', 'Pay using Whish, then upload your payment screenshot or enter the Whish transaction reference.' );
            $this->account_name = $this->get_option( 'account_name', '' );
            $this->whish_number = $this->get_option( 'whish_number', '' );
            $this->instructions = $this->get_option( 'instructions', '' );
            $this->max_mb = max( 1, absint( $this->get_option( 'max_mb', 8 ) ) );

            add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
            add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'thankyou_page' ) );
            add_action( 'woocommerce_email_before_order_table', array( $this, 'email_instructions' ), 10, 3 );
        }

        public function init_form_fields() {
            $this->form_fields = array(
                'enabled' => array(
                    'title' => __( 'Enable/Disable', 'rafflelb-whish-manual' ),
                    'type' => 'checkbox',
                    'label' => __( 'Enable Whish Checkout – Manual Gateway', 'rafflelb-whish-manual' ),
                    'default' => 'yes',
                ),
                'title' => array(
                    'title' => __( 'Title', 'rafflelb-whish-manual' ),
                    'type' => 'text',
                    'default' => __( 'Pay with Whish Money', 'rafflelb-whish-manual' ),
                ),
                'description' => array(
                    'title' => __( 'Checkout description', 'rafflelb-whish-manual' ),
                    'type' => 'textarea',
                    'default' => __( 'Pay using Whish, then upload your payment screenshot or enter the Whish transaction reference.', 'rafflelb-whish-manual' ),
                ),
                'account_name' => array(
                    'title' => __( 'Whish account / recipient name', 'rafflelb-whish-manual' ),
                    'type' => 'text',
                    'default' => '',
                ),
                'whish_number' => array(
                    'title' => __( 'Whish phone / account number', 'rafflelb-whish-manual' ),
                    'type' => 'text',
                    'default' => '',
                ),
                'instructions' => array(
                    'title' => __( 'Payment instructions', 'rafflelb-whish-manual' ),
                    'type' => 'textarea',
                    'default' => __( 'Send the exact order total through Whish. Then upload a screenshot of the successful payment or enter the transaction reference below. Your raffle entries are confirmed only after RaffleLB verifies the payment.', 'rafflelb-whish-manual' ),
                ),
                'max_mb' => array(
                    'title' => __( 'Maximum screenshot size (MB)', 'rafflelb-whish-manual' ),
                    'type' => 'number',
                    'default' => 8,
                    'custom_attributes' => array( 'min' => 1, 'max' => 20, 'step' => 1 ),
                ),
            );
        }

        public function payment_fields() {
            $recipient_name  = $this->get_option( 'recipient_name', '' );
            $recipient_phone = $this->get_option( 'recipient_phone', '' );
            $max_mb          = absint( $this->get_option( 'max_upload_mb', 8 ) );
            if ( $max_mb < 1 ) {
                $max_mb = 8;
            }
            ?>
            <div class="rl-whish-box">
                <div class="rl-whish-steps">
                    <div class="rl-whish-step">
                        <span class="rl-whish-step-no">1</span>
                        <div>
                            <strong>Send payment</strong>
                            <p>Send the exact order total to our Whish account.</p>
                            <?php if ( $recipient_name || $recipient_phone ) : ?>
                                <div class="rl-whish-payto">
                                    <?php if ( $recipient_name ) : ?><span><?php echo esc_html( $recipient_name ); ?></span><?php endif; ?>
                                    <?php if ( $recipient_phone ) : ?><strong><?php echo esc_html( $recipient_phone ); ?></strong><?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="rl-whish-step">
                        <span class="rl-whish-step-no">2</span>
                        <div>
                            <strong>Confirm payment</strong>
                            <p>Upload a screenshot <b>or</b> enter the reference phone number or transaction number.</p>
                        </div>
                    </div>

                    <div class="rl-whish-step">
                        <span class="rl-whish-step-no">3</span>
                        <div>
                            <strong>Verification</strong>
                            <p>Your raffle entry is confirmed after payment is verified.</p>
                        </div>
                    </div>
                </div>

                <div class="rl-whish-confirm">
                    <label class="rl-whish-field">
                        <span>Payment screenshot <small>optional</small></span>
                        <input type="file" id="rl_whish_screenshot" name="rl_whish_screenshot"
                               accept=".jpg,.jpeg,.png,.webp,.heic,.heif,image/jpeg,image/png,image/webp,image/heic,image/heif" />
                        <small>JPG, PNG, WEBP, HEIC or HEIF · Max <?php echo esc_html( $max_mb ); ?> MB</small>
                    </label>

                    <div class="rl-whish-divider"><span>OR</span></div>

                    <label class="rl-whish-field">
                        <span>Reference phone number or transaction number <small>optional</small></span>
                        <input type="text" id="rl_whish_reference" name="rl_whish_reference"
                               autocomplete="off" placeholder="Enter phone number or transaction number" />
                    </label>
                </div>

                <div class="rl-whish-notice">
                    <strong>Payment verification</strong>
                    <span>Order remains On hold until your Whish payment is verified.</span>
                </div>
            </div>
            <?php
        }

        public function validate_fields() {
            $reference = isset( $_POST['rl_whish_reference'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['rl_whish_reference'] ) ) ) : '';
            $has_file = ! empty( $_FILES['rl_whish_screenshot']['name'] );

            if ( '' === $reference && ! $has_file ) {
                wc_add_notice( __( 'Please upload your Whish payment screenshot or enter the Whish transaction reference.', 'rafflelb-whish-manual' ), 'error' );
                return false;
            }
            if ( $has_file ) {
                $validation = $this->validate_screenshot_file( $_FILES['rl_whish_screenshot'] );
                if ( is_wp_error( $validation ) ) {
                    wc_add_notice( $validation->get_error_message(), 'error' );
                    return false;
                }
            }
            return true;
        }

        private function validate_screenshot_file( $file ) {
            if ( ! isset( $file['error'] ) || UPLOAD_ERR_OK !== (int) $file['error'] ) {
                return new WP_Error( 'rl_whish_upload', __( 'The payment screenshot could not be uploaded. Please try again or enter the Whish reference instead.', 'rafflelb-whish-manual' ) );
            }
            if ( empty( $file['size'] ) || (int) $file['size'] > ( $this->max_mb * 1024 * 1024 ) ) {
                return new WP_Error( 'rl_whish_size', sprintf( __( 'The payment screenshot must be smaller than %d MB.', 'rafflelb-whish-manual' ), $this->max_mb ) );
            }
            $allowed_exts = array( 'jpg', 'jpeg', 'png', 'webp', 'heic', 'heif' );
            $ext = strtolower( pathinfo( sanitize_file_name( $file['name'] ), PATHINFO_EXTENSION ) );
            if ( ! in_array( $ext, $allowed_exts, true ) ) {
                return new WP_Error( 'rl_whish_type', __( 'Please upload a JPG, PNG, WEBP, HEIC or HEIF screenshot.', 'rafflelb-whish-manual' ) );
            }
            return true;
        }

        private function save_screenshot_to_order( $order_id ) {
            if ( empty( $_FILES['rl_whish_screenshot']['name'] ) ) return true;
            $validation = $this->validate_screenshot_file( $_FILES['rl_whish_screenshot'] );
            if ( is_wp_error( $validation ) ) return $validation;

            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';

            $uploaded = wp_handle_upload( $_FILES['rl_whish_screenshot'], array(
                'test_form' => false,
                'mimes' => array(
                    'jpg|jpeg|jpe' => 'image/jpeg',
                    'png' => 'image/png',
                    'webp' => 'image/webp',
                    'heic' => 'image/heic',
                    'heif' => 'image/heif',
                ),
            ) );
            if ( isset( $uploaded['error'] ) ) return new WP_Error( 'rl_whish_save', $uploaded['error'] );

            $attachment_id = wp_insert_attachment( array(
                'post_mime_type' => $uploaded['type'],
                'post_title' => 'Whish payment - Order ' . absint( $order_id ),
                'post_content' => '',
                'post_status' => 'private',
            ), $uploaded['file'], 0, true );

            if ( is_wp_error( $attachment_id ) ) {
                @unlink( $uploaded['file'] );
                return $attachment_id;
            }

            $metadata = wp_generate_attachment_metadata( $attachment_id, $uploaded['file'] );
            if ( $metadata ) wp_update_attachment_metadata( $attachment_id, $metadata );

            $order = wc_get_order( $order_id );
            $order->update_meta_data( '_rl_whish_screenshot_id', absint( $attachment_id ) );
            $order->save();
            return $attachment_id;
        }

        public function process_payment( $order_id ) {
            $order = wc_get_order( $order_id );
            if ( ! $order ) return array( 'result' => 'failure' );

            $reference = isset( $_POST['rl_whish_reference'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['rl_whish_reference'] ) ) ) : '';
            if ( $reference ) $order->update_meta_data( '_rl_whish_reference', $reference );

            if ( ! empty( $_FILES['rl_whish_screenshot']['name'] ) ) {
                $attachment = $this->save_screenshot_to_order( $order_id );
                if ( is_wp_error( $attachment ) ) {
                    wc_add_notice( $attachment->get_error_message(), 'error' );
                    return array( 'result' => 'failure' );
                }
            }

            $order->update_status( 'on-hold', __( 'Awaiting manual Whish payment verification.', 'rafflelb-whish-manual' ) );
            $order->add_order_note( __( 'Customer selected Whish manual payment. Verify the payment in Whish before confirming this order.', 'rafflelb-whish-manual' ) );
            $order->save();
            if ( function_exists( 'WC' ) && WC()->cart ) WC()->cart->empty_cart();

            return array( 'result' => 'success', 'redirect' => $this->get_return_url( $order ) );
        }

        public function thankyou_page( $order_id ) {
            echo '<section class="rl-whish-thankyou"><h2>' . esc_html__( 'Whish payment verification pending', 'rafflelb-whish-manual' ) . '</h2><p>' . esc_html__( 'We received your order. Your raffle entries will be confirmed only after RaffleLB manually verifies your Whish payment.', 'rafflelb-whish-manual' ) . '</p></section>';
        }

        public function email_instructions( $order, $sent_to_admin, $plain_text = false ) {
            if ( $sent_to_admin || ! $order instanceof WC_Order || $this->id !== $order->get_payment_method() || 'on-hold' !== $order->get_status() ) return;
            $text = __( 'Whish payment verification pending. Your raffle entries will be confirmed after RaffleLB verifies the payment.', 'rafflelb-whish-manual' );
            echo $plain_text ? "\n" . wp_strip_all_tags( $text ) . "\n" : '<p><strong>' . esc_html( $text ) . '</strong></p>';
        }
    }
}, 20 );

add_filter( 'woocommerce_payment_gateways', function ( $gateways ) {
    $gateways[] = 'WC_Gateway_RaffleLB_Whish_Manual';
    return $gateways;
} );

add_action( 'wp_footer', function () {
    if ( ! function_exists( 'is_checkout' ) || ! is_checkout() || is_order_received_page() ) return;
    ?>
    <script>
    (function(){
        function prep(){
            var form=document.querySelector('form.checkout');
            if(form){form.setAttribute('enctype','multipart/form-data');form.setAttribute('encoding','multipart/form-data');}
            var file=document.getElementById('rl_whish_screenshot'), label=document.getElementById('rl-whish-file-name');
            if(file&&label&&!file.dataset.rlBound){file.dataset.rlBound='1';file.addEventListener('change',function(){label.textContent=(file.files&&file.files[0])?'✓ '+file.files[0].name:'';});}
        }
        document.addEventListener('DOMContentLoaded',prep);
        if(window.jQuery){jQuery(document.body).on('updated_checkout',prep);}
        setInterval(prep,1200);
    })();
    </script>
    <?php
}, 99 );

add_action( 'wp_enqueue_scripts', function () {
    if ( ! function_exists( 'is_checkout' ) || ! is_checkout() ) return;
    $css = '.rl-whish-box{margin-top:14px;padding:18px;border:1px solid rgba(197,255,0,.35);border-radius:14px;background:rgba(255,255,255,.03)}.rl-whish-payto{margin:14px 0;padding:14px;border-radius:10px;background:rgba(197,255,0,.08)}.rl-whish-number{margin-top:4px;font-size:18px;font-weight:700}.rl-whish-confirm-title{margin-top:18px;font-size:16px}.rl-whish-help,.rl-whish-file-note{opacity:.78}#rl_whish_screenshot{display:block;width:100%;margin-top:7px;padding:13px;border:1px dashed rgba(197,255,0,.55);border-radius:10px;cursor:pointer}.rl-whish-file-name{display:block;margin-top:8px;font-weight:700;color:#c5ff00;word-break:break-word}.rl-whish-or{display:flex;align-items:center;gap:10px;margin:12px 0;font-size:12px;font-weight:800;opacity:.65}.rl-whish-or:before,.rl-whish-or:after{content:"";height:1px;flex:1;background:currentColor;opacity:.25}.rl-whish-notice{margin-top:12px;padding:11px 12px;border-left:3px solid #c5ff00;background:rgba(197,255,0,.06);font-size:13px}@media(max-width:767px){.rl-whish-box{padding:14px}#rl_whish_screenshot{min-height:52px;font-size:16px}#rl_whish_reference{min-height:48px;font-size:16px}}';
    wp_register_style( 'rafflelb-whish-manual-inline', false, array(), RL_WHISH_VERSION );
    wp_enqueue_style( 'rafflelb-whish-manual-inline' );
    wp_add_inline_style( 'rafflelb-whish-manual-inline', $css );
}, 50 );

add_action( 'woocommerce_admin_order_data_after_billing_address', function ( $order ) {
    if ( ! $order instanceof WC_Order || 'rafflelb_whish_manual' !== $order->get_payment_method() ) return;
    $reference = $order->get_meta( '_rl_whish_reference' );
    $screenshot_id = absint( $order->get_meta( '_rl_whish_screenshot_id' ) );
    echo '<div style="margin-top:18px;padding:14px;border:1px solid #ccd0d4;border-radius:6px"><h3 style="margin-top:0">' . esc_html__( 'Whish Payment Verification', 'rafflelb-whish-manual' ) . '</h3>';
    echo '<p><strong>' . esc_html__( 'Reference:', 'rafflelb-whish-manual' ) . '</strong><br>' . ( $reference ? esc_html( $reference ) : '—' ) . '</p>';
    if ( $screenshot_id ) {
        $url = wp_get_attachment_url( $screenshot_id );
        if ( $url ) echo '<p><strong>' . esc_html__( 'Payment screenshot:', 'rafflelb-whish-manual' ) . '</strong><br><a href="' . esc_url( $url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Open payment screenshot', 'rafflelb-whish-manual' ) . '</a></p>';
    } else {
        echo '<p><strong>' . esc_html__( 'Payment screenshot:', 'rafflelb-whish-manual' ) . '</strong> —</p>';
    }
    echo '<p style="margin-bottom:0"><em>' . esc_html__( 'Verify the payment in Whish before changing the order from On hold.', 'rafflelb-whish-manual' ) . '</em></p></div>';
} );

add_filter( 'upload_mimes', function ( $mimes ) {
    $mimes['heic'] = 'image/heic';
    $mimes['heif'] = 'image/heif';
    return $mimes;
} );


add_action( 'wp_enqueue_scripts', function() {
    if ( function_exists( 'is_checkout' ) && is_checkout() && ! is_order_received_page() ) {
        wp_enqueue_style(
            'rafflelb-whish-checkout',
            plugin_dir_url( __FILE__ ) . 'assets/checkout.css',
            array(),
            '1.1.4'
        );
    }
}, 30 );
