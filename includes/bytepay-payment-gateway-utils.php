<?php

/**
 * Check the environment for compatibility issues.
 *
 * @return string|false
 */
function bytepay_check_system_requirements()
{
	if (version_compare(phpversion(), BYTEPAY_PAYMENT_GATEWAY_MIN_PHP_VER, '<')) {
		return sprintf(
			// translators: %1$s is the minimum required PHP version, %2$s is the current PHP version
			__('The Bytepay Payment Gateway plugin requires PHP version %1$s or greater. You are running %2$s.', 'bytepay-payment-gateway'),
			BYTEPAY_PAYMENT_GATEWAY_MIN_PHP_VER,
			phpversion()
		);
	}

	// Get WooCommerce versions
	$wc_db_version = get_option('woocommerce_db_version');
	$wc_plugin_version = defined('WC_VERSION') ? WC_VERSION : null;

	// Check if the WooCommerce database version is outdated
	if (!$wc_db_version || version_compare($wc_db_version, BYTEPAY_PAYMENT_GATEWAY_MIN_WC_VER, '<')) {
		return sprintf(
			// translators: %1$s is the minimum required WooCommerce database version, %2$s is the current WooCommerce database version (or "undefined" if not available)
			__('The Bytepay Payment Gateway plugin requires WooCommerce database version %1$s or greater. You are running %2$s.', 'bytepay-payment-gateway'),
			BYTEPAY_PAYMENT_GATEWAY_MIN_WC_VER,
			$wc_db_version ? $wc_db_version : __('undefined', 'bytepay-payment-gateway')
		);
	}

	// Check if WooCommerce plugin version is outdated
	if (!$wc_plugin_version || version_compare($wc_plugin_version, BYTEPAY_PAYMENT_GATEWAY_MIN_WC_VER, '<')) {
		return sprintf(
			// translators: %1$s is the minimum required WooCommerce plugin version, %2$s is the current WooCommerce plugin version (or "undefined" if not available)
			__('The Bytepay Payment Gateway plugin requires WooCommerce plugin version %1$s or greater. You are running %2$s.', 'bytepay-payment-gateway'),
			BYTEPAY_PAYMENT_GATEWAY_MIN_WC_VER,
			$wc_plugin_version ? $wc_plugin_version : __('undefined', 'bytepay-payment-gateway')
		);
	}

	// Check if WooCommerce plugin version and database version are different
	if ($wc_plugin_version && $wc_db_version && $wc_plugin_version !== $wc_db_version) {
		return sprintf(
			// translators: %1$s is the WooCommerce plugin version, %2$s is the WooCommerce database version
			__('Warning: The WooCommerce plugin version (%1$s) and database version (%2$s) do not match. Please ensure both are synchronized.', 'bytepay-payment-gateway'),
			$wc_plugin_version,
			$wc_db_version
		);
	}

	return false;
}

/**
 * Activation check for the plugin.
 */
function bytepay_activation_check()
{
	$environment_warning = bytepay_check_system_requirements();
	if ($environment_warning) {
		deactivate_plugins(plugin_basename(BYTEPAY_PAYMENT_GATEWAY_FILE));
		wp_die(esc_html($environment_warning)); // Escape the output before calling wp_die
	}
}