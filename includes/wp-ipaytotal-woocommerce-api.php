<?php

/**
* @package wp-ipaytotal-woocommerce
* @author  iPayTotal Ltd
* @since   1.0.0
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * WC_Cardpay_Authnet_API
 */
 class WOWP_IPTWPG_iPayTotal_API {

	public $wc_pre_30;

	public function __construct() {
		$this->wc_pre_30 = version_compare( WC_VERSION, '3.0.0', '<' );
	}

	/**
	 * get_detalle_data function
	 * 
	 * @return string
	 */
	public function get_detalle_data( $products ) {
		foreach ( $products as $product ) {
    	$detalle[] = array(
				'id_producto'	=> $product->get_product_id(),
				'cantidad'		=> $product->get_quantity(),
				'tipo'				=> $product->get_type(),
				'nombre'			=> $product->get_name(),
				'precio'			=> get_post_meta( $product->get_product_id(), '_regular_price', true),
				'Subtotal'		=> $product->get_total(),
			);
		}

		$detalle_data = json_encode( $detalle );
		return $detalle_data;
	}
    
	/**
	 * get_response_body function
	 * 
	 * @return string
	 */
	public function get_response_body($response) {

		// get body response while get not error
		$response_body = wp_remote_retrieve_body( $response);

		foreach ( preg_split( "/\r?\n/", $response_body ) as $line ) {
			$resp = explode( "|", $line );
		}

		// values get
		$r = json_decode( $resp[0], true );

		return $r;
	}
}
