<?php
/**
* @package wp-ipaytotal-woocommerce
* @author  iPayTotal Ltd
* @since   1.0.0
 */

class wowp_iptwpg_ipaytotal extends WC_Payment_Gateway {

	public function __construct() {

		// payment gateway plugin ID
		$this->id = "wowp_iptwpg_ipaytotal";

		// Show Description
		$this->method_description = __( "IPayTotal Payment Gateway Plug-in for WooCommerce", 'wp-ipaytotal-woocommerce' );

		// URL of the icon that will be displayed on checkout page near your gateway name
		$this->icon = null;

		// in case you need a custom credit card form
		$this->has_fields = false;

		// Method with all the options fields
		$this->init_form_fields();

		// load time variable setting
		$this->init_settings();
		
		// Turn these settings into variables we can use
		foreach ( $this->settings as $setting_key => $value ) {
			$this->$setting_key = $value;
		}

		// Show Title
		$this->method_title = __( $this->title, 'wp-ipaytotal-woocommerce' );

		$this->testmode = 'yes' === $this->get_option( 'testmode' );
		
		// further check of SSL if you want
		// add_action( 'admin_notices', array( $this, 'do_ssl_check' ) );

		// Check if the keys have been configured
		if( !is_admin() ) {
			// wc_add_notice( __("This website is on test mode, so orders are not going to be processed. Please contact the store owner for more information or alternative ways to pay.", "wp-ipaytotal-woocommerce") );
		}

		// Save settings
		if ( is_admin() ) {
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		}		
	}

	// administration fields for specific Gateway
	public function init_form_fields() {
		$this->form_fields = array(
			'enabled' => array(
				'title'			=> __( 'Enable / Disable', 'wp-ipaytotal-woocommerce' ),
				'label'			=> __( 'Enable this payment gateway', 'wp-ipaytotal-woocommerce' ),
				'type'			=> 'checkbox',
				'default'		=> 'no',
			),
			'ipt_key_secret' => array(
				'title'			=> __( 'API Secret Key', 'wp-ipaytotal-woocommerce' ),
				'type'			=> 'text',
				'desc_tip'	=> __( 'This is the API Secret Key provided by iPayTotal when you signed up for an account.', 'wp-ipaytotal-woocommerce' ),
			),
			'ipt_test_mode' => array(
				'title'			=> __( 'Enable / Disable', 'wp-ipaytotal-woocommerce' ),
				'label'			=> __( 'Enable this checkmark to go Test Mode', 'wp-ipaytotal-woocommerce' ),
				'type'			=> 'checkbox',
				'default'		=> 'yes',
			),
			'title' => array(
				'title'			=> __( 'Title', 'wp-ipaytotal-woocommerce' ),
				'type'			=> 'text',
				'desc_tip'	=> __( 'Payment method title that the customer will see on your checkout.', 'wp-ipaytotal-woocommerce' ),
				'default'		=> 'Credit/Debit Card - iPayTotal',
			),
			'description' => array(
	            'title'       => __( 'Description', 'wp-ipaytotal-woocommerce' ),
	            'type'        => 'textarea',
	            'desc_tip' => __( 'Payment method description that the customer will see on your checkout.', 'wp-ipaytotal-woocommerce' ),
	            'default'     => __( 'Pay with your Mastercard or Visa card using iPayTotal payment processor', 'wp-ipaytotal-woocommerce' ),
	        ),
		);		
	}
	
	// Response handled for payment gateway
	public function process_payment( $order_id ) {
		global $woocommerce;

		$customer_order = new WC_Order( $order_id );

		$products = $customer_order->get_items();

		$ipaytotal_card = new WOWP_IPTWPG_iPayTotal_API();
                
		$ipt_response_url = site_url('ipayment-callback');
	
		$billing_state = $customer_order->get_billing_state();
		$billing_city = $customer_order->get_billing_city();
		$zip = $customer_order->get_billing_postcode();
        $data = array(
            'api_key'       => $this->ipt_key_secret,
            'response_url'  => $ipt_response_url,
            'first_name'    => $customer_order->get_billing_first_name(),
            'last_name'     => $customer_order->get_billing_last_name(),
            'address'       => $customer_order->get_billing_address_1(),
            'sulte_apt_no'  => $order_id,
            'country'       => $customer_order->get_billing_country(),
            'state'         => empty($billing_state) ? $billing_city : $billing_state,
            'city'          => $billing_city,
            'zip'           => !empty($zip) ? $zip : "NA",
            'ip_address'    => $customer_order->get_customer_ip_address(),
            'birth_date'    => rand(1,12).'/'.rand(1,30).'/'.rand(1985,1991),
            'email'         => $customer_order->get_billing_email(),
            'phone_no'      => $customer_order->get_billing_phone(),
            'amount'        => $customer_order->get_total(),
            'currency'      => $customer_order->get_currency(),
            'shipping_first_name'   => $customer_order->get_shipping_first_name(),
            'shipping_last_name'    => $customer_order->get_shipping_last_name(),
            'shipping_address'      => $customer_order->get_shipping_address_1(),
            'shipping_country'      => $customer_order->get_shipping_address_2(),
            'shipping_state'        => $customer_order->get_shipping_country(),
            'shipping_city'         => $customer_order->get_shipping_state(), // if 
            'shipping_zip'          => $customer_order->get_shipping_city(),
            'shipping_email'        => $customer_order->get_shipping_postcode(),
            'shipping_phone_no'     => $customer_order->get_billing_phone(),
        );

		// Decide which URL to post to
		if ($this->ipt_test_mode == 'yes') {
			$environment_url = "https://ipaytotal.solutions/api/test/hosted-pay/payment-request";
		} else {
			$environment_url = "https://ipaytotal.solutions/api/hosted-pay/payment-request";
		}
		

        $result = wp_remote_post( $environment_url, array( 
            'method'    => 'POST', 
            'body'      => json_encode( $data ), 
            'timeout'   => 90, 
            'sslverify' => true, 
            'headers' => array( 'Content-Type' => 'application/json' ) 
        ) );

		if ( is_wp_error( $result ) ) {
			throw new Exception( __( 'There is issue for connecting payment gateway. Sorry for the inconvenience.', 'wp-ipaytotal-woocommerce' ) );
			if ( empty( $result['body'] ) ) {
				throw new Exception( __( 'iPayTotal\'s Response was not get any data.', 'wp-ipaytotal-woocommerce' ) );	
			}
		}
		
		// get body response while get not error
		$response_body = $ipaytotal_card->get_response_body($result);
		
		if ( $response_body['status'] == 'success' ) {
			$customer_order->update_status('on-hold', __( 'Redirecting to 3D secure page.', 'wp-ipaytotal-woocommerce' ));

			return array(
		        'result' => 'success',
		        'redirect' => $response_body['payment_redirect_url']
		    );

		} elseif ( $response_body['status'] == 'fail' ) {
			$errorsMessage = "";
			if(isset($response_body['errors']) && !empty($response_body['errors'])) {
				$errors = $response_body['errors'];
				
				foreach( $errors as $key => $value ){
					$errorsMessage .= "<br>". $value[0];	
				}
			} else {
				$errorsMessage = $response_body['message'];
			}

			wc_add_notice( __('Payment failed. ') . $errorsMessage , 'error' );
			$customer_order->update_status('failed');
		} else {
			wc_add_notice( __('Payment failed. ') . $response_body['message'], 'error' );
			$customer_order->update_status('failed');
		}
	}
	
	// Validate fields
	public function validate_fields()
	{
		return true;
	}
}
