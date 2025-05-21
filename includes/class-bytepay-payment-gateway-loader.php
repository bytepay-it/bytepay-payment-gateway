<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Include the configuration file
require_once plugin_dir_path(__FILE__) . 'config.php';

/**
 * Class BYTEPAY_PAYMENT_GATEWAY_Loader
 * Handles the loading and initialization of the Bytepay Payment Gateway plugin.
 */
class BYTEPAY_PAYMENT_GATEWAY_Loader
{
    private static $instance = null;
    private $admin_notices;
    private $sip_protocol;
    private $sip_host;

    /**
     * Get the singleton instance of this class.
     * @return BYTEPAY_PAYMENT_GATEWAY_Loader
     */
    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor. Sets up actions and hooks.
     */
    private function __construct()
    {
        $this->sip_protocol = BYTEPAY_SIP_PROTOCOL;
        $this->sip_host = BYTEPAY_SIP_HOST;

        $this->admin_notices = new BYTEPAY_PAYMENT_GATEWAY_Admin_Notices();

        add_action('admin_init', [$this, 'bytepay_handle_environment_check']);
		add_action('admin_notices', [$this->admin_notices, 'display_notices']);
		add_action('plugins_loaded', [$this, 'bytepay_init']);

		// Register the AJAX action callback for checking payment status
		add_action('wp_ajax_check_payment_status', array($this, 'handle_check_payment_status'));
		add_action('wp_ajax_nopriv_check_payment_status', array($this, 'handle_check_payment_status'));

		add_action('wp_ajax_popup_closed_event', array($this, 'bytepay_handle_popup_close'));
		add_action('wp_ajax_nopriv_popup_closed_event', array($this, 'bytepay_handle_popup_close'));

		register_activation_hook(BYTEPAY_PAYMENT_GATEWAY_FILE, 'bytepay_activation_check');

		register_deactivation_hook(BYTEPAY_PAYMENT_GATEWAY_FILE, 'bytepay_plugin_deactivation');
    }

    /**
     * Initializes the plugin.
     * This method is hooked into 'plugins_loaded' action.
     */
    public function bytepay_init()
    {

        // Check if the environment is compatible
		$environment_warning = bytepay_check_system_requirements();
		if ($environment_warning) {
			return;
		}

        // Initialize bytepay_init_gateways
        $this->bytepay_init_gateways();

        // Initialize REST API
        $rest_api = BYTEPAY_PAYMENT_GATEWAY_REST_API::get_instance();
        $rest_api->bytepay_register_routes();

        // Add plugin action links
        add_filter('plugin_action_links_' . plugin_basename(BYTEPAY_PAYMENT_GATEWAY_FILE), [$this, 'bytepay_plugin_action_links']);

        // Add plugin row meta
        add_filter('plugin_row_meta', [$this, 'bytepay_plugin_row_meta'], 10, 2);

		 // Register custom status
	    register_post_status('wc-ach-in-process', [
	        'label'                     => _x('ACH in Process', 'Order status', 'bytepay-payment-gateway'),
	        'public'                    => true,
	        'exclude_from_search'       => false,
	        'show_in_admin_all_list'    => true,
	        'show_in_admin_status_list' => true,
	        'label_count'               => _n_noop('ACH in Process <span class="count">(%s)</span>', 'ACH in Process <span class="count">(%s)</span>', 'bytepay-payment-gateway'),
	    ]);

	    // Add custom status to the WooCommerce dropdown/order filters
	    add_filter('wc_order_statuses', function ($order_statuses) {
	        $new_statuses = [];
	        foreach ($order_statuses as $key => $label) {
	            $new_statuses[$key] = $label;

	            // Inject your custom status after 'on-hold'
	            if ('wc-on-hold' === $key) {
	                $new_statuses['wc-ach-in-process'] = _x('ACH in Process', 'Order status', 'bytepay-payment-gateway');
	            }
	        }
	        return $new_statuses;
	    });
    }

    /**
     * Initialize bytepay_init_gateways.
     */
    private function bytepay_init_gateways()
	{
		if (!class_exists('WC_Payment_Gateway')) {
			return;
		}

		include_once BYTEPAY_PAYMENT_GATEWAY_PLUGIN_DIR . 'includes/class-bytepay-payment-gateway.php';

		add_filter('woocommerce_payment_gateways', function ($methods) {
			$methods[] = 'BYTEPAY_PAYMENT_GATEWAY';
			return $methods;
		});
	}

    /**
     * Get the API URL based on protocol and host.
     */
    private function get_api_url($endpoint)
	{
		$base_url = $this->sip_host;
		return $this->sip_protocol . $base_url . $endpoint;
	}

    /**
	 * Add action links to the plugin page.
	 * @param array $links
	 * @return array
	 */
	public function bytepay_plugin_action_links($links)
	{
		$plugin_links = [
			'<a href="' . esc_url(admin_url('admin.php?page=wc-settings&tab=checkout&section=bytepay')) . '">' . esc_html__('Settings', 'bytepay-payment-gateway') . '</a>',
		];

		return array_merge($plugin_links, $links);
	}

    /**
	 * Add row meta to the plugin page.
	 * @param array $links
	 * @param string $file
	 * @return array
	 */
	public function bytepay_plugin_row_meta($links, $file)
	{
		if (plugin_basename(BYTEPAY_PAYMENT_GATEWAY_FILE) === $file) {
			$row_meta = [
				'docs'    => '<a href="' . esc_url(apply_filters('bytepay_docs_url', 'https://www.bytepay.it/api/docs/wordpress-plugin')) . '" target="_blank">' . esc_html__('Documentation', 'bytepay-payment-gateway') . '</a>',
				'support' => '<a href="' . esc_url(apply_filters('bytepay_support_url', 'https://www.bytepay.it/reach-out')) . '" target="_blank">' . esc_html__('Support', 'bytepay-payment-gateway') . '</a>',
			];

			$links = array_merge($links, $row_meta);
		}

		return $links;
	}

    /**
	 * Check the environment and display notices if necessary.
	 */
	public function bytepay_handle_environment_check()
	{
		$environment_warning = bytepay_check_system_requirements();
		if ($environment_warning) {
			// Sanitize the environment warning before displaying it
			$this->admin_notices->bytepay_add_notice('error', 'error', sanitize_text_field($environment_warning));
		}
	}

	/**
    * Handle the AJAX request for checking payment status.
    * @param $request
    */
	public function handle_check_payment_status()
	{
        // Verify nonce for security (recommended)
		// Sanitize and unslash the 'security' value
	    $security = isset($_POST['security']) ? sanitize_text_field(wp_unslash($_POST['security'])) : '';
        // Check the nonce for security
        if (empty($security) || !wp_verify_nonce($security, 'bytepay_payment')) {
		    wp_send_json_error(['message' => 'Nonce verification failed.']);
		    wp_die();
		}
	   
	    // Sanitize and validate the order ID from $_POST
        $order_id = isset($_POST['order_id']) ? intval(sanitize_text_field(wp_unslash($_POST['order_id']))) : null;
	    if (!$order_id) {
	        wp_send_json_error(['error' => esc_html__('Invalid order ID', 'bytepay-payment-gateway')]);
	    }

	    return $this->bytepay_check_payment_status($order_id);
	}


    /**
	 * Check the payment status for an order.
	 * @param int $order_id
	 * @return WP_REST_Response
	 */
    public function bytepay_check_payment_status($order_id)
    {
        // Get the order details
        $order = wc_get_order($order_id);

        if (!$order) {
            return new WP_REST_Response(['error' => esc_html__('Order not found', 'bytepay-payment-gateway')], 404);
        }

        $payment_return_url = esc_url($order->get_checkout_order_received_url());
        // Check the payment status
        if ($order) {
            if ($order->is_paid()) {
                return wp_send_json_success(['status' => 'success', 'redirect_url' => $payment_return_url]);
            } elseif ($order->has_status('failed')) {
                return wp_send_json_success(['status' => 'failed', 'redirect_url' => $payment_return_url]);
            }
        }

        // Default to pending status
        wp_send_json_success(['status' => 'pending']);
    }

    public function bytepay_handle_popup_close() {
		// Sanitize and unslash the 'security' value
		$security = isset($_POST['security']) ? sanitize_text_field(wp_unslash($_POST['security'])) : '';
	
		// Check the nonce for security
		if (empty($security) || !wp_verify_nonce($security, 'bytepay_payment')) {
			wp_send_json_error(['message' => 'Nonce verification failed.']);
			wp_die();
		}
	
		// Get the order ID from the request
		$order_id = isset($_POST['order_id']) ? sanitize_text_field(wp_unslash($_POST['order_id'])) : null;
	
		// Validate order ID
		if (!$order_id) {
			wp_send_json_error(['message' => 'Order ID is missing.']);
			wp_die();
		}
	
		// Fetch the WooCommerce order
		$order = wc_get_order($order_id);
	
		// Check if the order exists
		if (!$order) {
			wp_send_json_error(['message' => 'Order not found in WordPress.']);
			wp_die();
		}
		//Get uuid from WP
		$payment_token = $order->get_meta('_bytepay_pay_id');
	
		// Proceed only if the order status is 'pending'
		if ($order->get_status() === 'pending') {
			// Call the Bytepay to update status
			$transactionStatusApiUrl = $this->get_api_url('/api/update-txn-status');
			$response = wp_remote_post($transactionStatusApiUrl, [
				'method'    => 'POST',
				'body'      => wp_json_encode(['order_id' => $order_id,'payment_token' => $payment_token]),
				'headers'   => [
					'Content-Type'  => 'application/json',
					'Authorization' => 'Bearer ' . $security,
				],
				'timeout'   => 15,
			]);
	
			// Check for errors in the API request
			if (is_wp_error($response)) {
				wp_send_json_error(['message' => 'Failed to connect to the Bytepay.']);
				wp_die();
			}
	
			// Parse the API response
			$response_body = wp_remote_retrieve_body($response);
			$response_data = json_decode($response_body, true);
	
			// Ensure the response contains the expected data
			if (!isset($response_data['transaction_status'])) {
				wp_send_json_error(['message' => 'Invalid response from Bytepay.']);
				wp_die();
			}
	
			// Get the configured order status from the payment gateway settings
			$gateway_id = 'bytepay'; // Replace with your gateway ID
			$payment_gateways = WC()->payment_gateways->payment_gateways();
			if (isset($payment_gateways[$gateway_id])) {
				$gateway = $payment_gateways[$gateway_id];
				$configured_order_status = sanitize_text_field($gateway->get_option('order_status'));
			} else {
				wp_send_json_error(['message' => 'Payment gateway not found.']);
				wp_die();
			}
	
			// Validate the configured order status
			$allowed_statuses = wc_get_order_statuses();
			if (!array_key_exists('wc-' . $configured_order_status, $allowed_statuses)) {
				wp_send_json_error(['message' => 'Invalid order status configured: ' . esc_html($configured_order_status)]);
				wp_die();
			}
			
			$payment_return_url = esc_url($order->get_checkout_order_received_url());
			// Handle transaction status from API
			switch ($response_data['transaction_status']) {
				case 'success':
				case 'paid':
				case 'processing':
					// Update the order status based on the selected value
					try {
						$order->update_status($configured_order_status, 'Order marked as ' . $configured_order_status . ' by Bytepay.');
						wp_send_json_success(['message' => 'Order status updated successfully.', 'order_id' => $order_id,'redirect_url'=>$payment_return_url]);
					} catch (Exception $e) {
						wp_send_json_error(['message' => 'Failed to update order status: ' . $e->getMessage()]);
					}
					break;
	
				case 'failed':
					try {
						$order->update_status('failed', 'Order marked as failed by Bytepay.');
						wp_send_json_success(['message' => 'Order status updated to failed.', 'order_id' => $order_id,'redirect_url'=>$payment_return_url]);
					} catch (Exception $e) {
						wp_send_json_error(['message' => 'Failed to update order status: ' . $e->getMessage()]);
					}
					break;
				case 'canceled':	
				case 'expired':
					try {
						$order->update_status('canceled', 'Order marked as canceled by Bytepay.');
						wp_send_json_success(['message' => 'Order status updated to canceled.', 'order_id' => $order_id,'redirect_url'=>$payment_return_url]);
					} catch (Exception $e) {
						wp_send_json_error(['message' => 'Failed to update order status: ' . $e->getMessage()]);
					}
					break;
				default:
					wp_send_json_error(['message' => 'Unknown transaction status received.']);
			}
		} else {
			// Skip API call if the order status is not 'pending'
			wp_send_json_success(['message' => 'No update required as the order status is not pending.', 'order_id' => $order_id]);
		}
	
		wp_die();
	}
}
