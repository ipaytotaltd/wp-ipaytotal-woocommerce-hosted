<?php 
/**
* @since 1.4.0
* @package wp-ipaytotal-woocommerce
* @author iPayTotal Ltd
* 
* Plugin Name: iPayTotal - WooCommerce Payment Gateway
* Plugin URI: https://ipaytotal.com/contact
* Description: WooCommerce custom payment gateway integration with iPayTotal.
* Version: 1.4.0
* Author: iPayTotal
* Author URI: https://ipaytotal.com/ipaytotal-high-risk-merchant-account/
* License: GNU General Public License v2 or later
* License URI: http://www.gnu.org/licenses/gpl-2.0.html
* Text Domain: wp-ipaytotal-woocommerce
* Domain Path: /languages/
* WC requires at least: 3.0.0 
* WC tested up to: 4.9.8 
*/
 
require 'ipaytotal/plugin-update-checker.php';

$MyUpdateChecker = Puc_v4_Factory::buildUpdateChecker(
    'http://ipaytotalwp.inanceorganix.com/plugin_update/index.php?action=get_metadata&slug=wp-ipaytotal-woocommerce-master', 
    __FILE__, //Full path to the main plugin file.
    'wp-ipaytotal-woocommerce-master' //Plugin slug. Usually it's the same as the name of the directory.
);

/**
 * Tell WordPress to load a translation file if it exists for the user's language
 */
function wowp_iptwpg_load_plugin_textdomain() {
    load_plugin_textdomain( 'wp-ipaytotal-woocommerce', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
}


add_action( 'plugins_loaded', 'wowp_iptwpg_load_plugin_textdomain' );


function wowp_iptwpg_ipaytotal_init() {
    //if condition use to do nothin while WooCommerce is not installed
	if ( ! class_exists( 'WC_Payment_Gateway_CC' ) ) return;
	include_once( 'includes/wp-ipaytotal-woocommerce-admin.php' );
	include_once( 'includes/wp-ipaytotal-woocommerce-api.php' );
	// class add it too WooCommerce
	add_filter( 'woocommerce_payment_gateways', 'wowp_iptwpg_add_ipaytotal_gateway' );
	function wowp_iptwpg_add_ipaytotal_gateway( $methods ) {
		$methods[] = 'wowp_iptwpg_ipaytotal';
		return $methods;
	}
}


add_action( 'plugins_loaded', 'wowp_iptwpg_ipaytotal_init', 0 );


/**
* Add custom action links
*/
function wowp_iptwpg_ipaytotal_action_links( $links ) {
	$plugin_links = array(
		'<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout' ) . '">' . __( 'Settings', 'wp-ipaytotal-woocommerce' ) . '</a>',
	);
	return array_merge( $plugin_links, $links );
}

add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'wowp_iptwpg_ipaytotal_action_links' );

add_filter('query_vars', 'ipaytotal_query_vars');
add_action('init', 'ipaytotal_payment_callback_urls');

function ipaytotal_query_vars($vars){
	$vars[] = 'sulte_apt_no';
	$vars[] = 'order_id';
	$vars[] = 'status';
	$vars[] = 'reason';
	$vars[] = 'message';
	return $vars;
}

function ipaytotal_payment_callback_urls() {

  add_rewrite_rule(
    '^ipayment-callback/(\w)?',
    'index.php?sulte_apt_no=$matches[1]',
    'top'
  );

}
add_action('parse_request', 'ipayment_total_callback');
function ipayment_total_callback( $wp ){
	$IpaymentTotalCallback = new IpaymentTotalCallback();
	$IpaymentTotalCallback->IpaymentCallback($wp);
}


class IpaymentTotalCallback extends WC_Payment_Gateway {
    public function IpaymentCallback( $wp ) {
    	$valid_actions = array('sulte_apt_no');

		if( isset($wp->query_vars['sulte_apt_no']) && !empty($wp->query_vars['sulte_apt_no']) ) {
			
			$orderId = $wp->query_vars['sulte_apt_no'];
			$status = $wp->query_vars['status'];
			$message = isset($wp->query_vars['reason']) ? $wp->query_vars['reason'] : '';
			if( empty($message) ){
				$message = isset( $wp->query_vars['message'] ) ? $wp->query_vars['message'] : "";
			}
			
			if( $status == "success" ){				
				global $woocommerce;

				// we need it to get any order detailes
				$order = wc_get_order( $orderId );

				$order->payment_complete();
				$order->reduce_order_stock();
				$order->add_order_note( $message, true );
				$woocommerce->cart->empty_cart();
                wc_add_notice($message,'Success');
				wc_add_notice( __( $message, 'woocommerce' ), 'success' );
				$order_url = $this->get_return_url( $order );
				wp_redirect($order_url);
				exit;
				
			} else {
				global $woocommerce;
				$order = wc_get_order( $orderId );
				$order->add_order_note( $message, true );
                $order->update_status( 'failed', $message );
                wc_add_notice($message,'Error');
				wc_add_notice( __( $message, 'woocommerce' ), 'error' );
				wp_safe_redirect( wc_get_checkout_url() );
				exit;
			}
		}
    }
}