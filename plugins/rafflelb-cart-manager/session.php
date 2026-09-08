<?php
defined('ABSPATH') || exit;

/** Keep the standard WooCommerce handler, adding per-customer request serialization. */
class RLCM_Session_Handler extends WC_Session_Handler {
    public function get_session($customer_id, $default_value = false) {
        RLCM::lock($customer_id);
        return parent::get_session($customer_id, $default_value);
    }
    public function save_data($old_session_key = '') {
        RLCM::lock($this->get_customer_id());
        if ($old_session_key) RLCM::lock($old_session_key);
        if (!empty($this->_data['cart'])) {
            $this->set('rlcm_last_activity', time());
        }
        parent::save_data($old_session_key);
    }
    public function delete_session($customer_id) {
        RLCM::lock($customer_id);
        parent::delete_session($customer_id);
    }
}

/** In-memory engine context: no cookies, customer impersonation, or session hooks. */
class RLCM_Remote_Session extends WC_Session {
    public function __construct($data) { $this->_data = $data; }
}
class RLCM_Remote_Cart {
    private $items;
    public function __construct($items) { $this->items = $items; }
    public function get_cart() { return $this->items; }
}
