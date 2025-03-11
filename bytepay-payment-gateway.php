<?php

/**
 * Plugin Name: Bytepay Payment Gateway
 * Description: Secure payment gateway integration allowing USD bank-to-bank transfers.
 * Author: Bytepay
 * Author URI: https://www.bytepay.it/
 * Text Domain: bytepay-payment-gateway
 * Plugin URI: https://github.com/bytepay-it/bytepay-payment-gateway.git
 * Version: 1.0.1
 * License: GPLv3 or later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 *
 * Copyright (c) 2025 Bytepay
 */

if (!defined('ABSPATH')) {
    exit; // Prevent direct access
}

define('BYTEPAY_PAYMENT_GATEWAY_MIN_PHP_VER', '8.0');
define('BYTEPAY_PAYMENT_GATEWAY_MIN_WC_VER', '6.5.4');
define('BYTEPAY_PAYMENT_GATEWAY_FILE', __FILE__);
define('BYTEPAY_PAYMENT_GATEWAY_PLUGIN_DIR', __DIR__ . '/');

// Include utility functions
require_once BYTEPAY_PAYMENT_GATEWAY_PLUGIN_DIR . 'includes/bytepay-payment-gateway-utils.php';

// Autoload classes
spl_autoload_register(function ($class) {
	if (strpos($class, 'BYTEPAY_PAYMENT_GATEWAY_') === 0) {
		$class_file = BYTEPAY_PAYMENT_GATEWAY_PLUGIN_DIR . 'includes/class-' . str_replace('_', '-', strtolower($class)) . '.php';
		if (file_exists($class_file)) {
			require_once $class_file;
		}
	}
});

BYTEPAY_PAYMENT_GATEWAY_Loader::get_instance();
