<?php
if (!defined('ABSPATH')) { exit; }

interface RaffleLB_Auth_SMS_Provider_Interface {
    public function get_id();
    public function get_label();
    public function is_available();
    public function send($phone, $message, $context = array());
}

