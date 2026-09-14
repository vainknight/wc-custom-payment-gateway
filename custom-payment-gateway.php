<?php
/**
 * Plugin Name: Custom Payment Gateway (WooCommerce)
 * Description: App-style custom checkout flow for WooCommerce (cart -> payment -> confirmation) with configurable payment methods (PayPal via your existing WooCommerce gateway, plus manual methods like bank transfer, mobile payment, Zelle, Binance, cash on site), fully configurable colors/branding, and automatic creation of the required pages.
 * Version: 1.0.0
 * Author: Fran Velazco
 * Author URI: https://www.linkedin.com/in/fran-velazco/
 * Text Domain: custom-payment-gateway
 * Requires Plugins: woocommerce
 * License: GPLv2
 */

if (!defined('ABSPATH')) {
    exit; // Direct access not allowed.
}

define('PPL_PLUGIN_FILE', __FILE__);
define('PPL_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('PPL_PLUGIN_URL', plugin_dir_url(__FILE__));
define('PPL_VERSION', '1.0.0');

require_once PPL_PLUGIN_DIR . 'includes/class-ppl-opciones.php';
require_once PPL_PLUGIN_DIR . 'includes/class-ppl-paginas.php';
require_once PPL_PLUGIN_DIR . 'includes/class-ppl-core.php';
require_once PPL_PLUGIN_DIR . 'includes/class-ppl-ajax.php';
require_once PPL_PLUGIN_DIR . 'includes/class-ppl-admin-order.php';

// Notice if WooCommerce is not active.
add_action('admin_notices', function () {
    if (!class_exists('WooCommerce') && current_user_can('activate_plugins')) {
        echo '<div class="notice notice-warning"><p>';
        echo esc_html__('The "Custom Payment Gateway" plugin requires WooCommerce to be active to work.', 'custom-payment-gateway');
        echo '</p></div>';
    }
});

register_activation_hook(__FILE__, array('PPL_Paginas', 'crear_paginas_en_activacion'));

add_action('plugins_loaded', function () {
    PPL_Opciones::instancia();
    PPL_Paginas::instancia();
    PPL_Core::instancia();
    PPL_Ajax::instancia();
    PPL_Admin_Order::instancia();
});
