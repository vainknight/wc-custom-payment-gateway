<?php
if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('WC_Payment_Gateway')) {
    return;
}

if (class_exists('PPL_Gateway_Manual')) {
    return;
}

/**
 * PPL_Gateway_Manual
 * -------------------
 * A "placeholder" WooCommerce payment gateway so that orders created
 * by this plugin's manual methods (bank transfer, Zelle, etc.) have a
 * valid payment_method value WooCommerce recognizes. It is never
 * shown on the native WooCommerce checkout (is_available() returns
 * false) — it is only ever assigned programmatically to orders
 * created through [pasarela_pago].
 */
class PPL_Gateway_Manual extends WC_Payment_Gateway {

    public function __construct() {
        $this->id           = 'ppl_manual';
        $this->method_title = __('Manual payment (custom gateway)', 'custom-payment-gateway');
        $this->title        = __('Manual payment', 'custom-payment-gateway');
        $this->has_fields   = false;
        $this->enabled      = 'yes';
    }

    public function is_available() {
        return false;
    }
}
