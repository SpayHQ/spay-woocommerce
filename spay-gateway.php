<?php
/*
  Plugin Name: SPay Payment Gateway
	Plugin URI: https://spaybusiness.com
	Description: SPay Payment Gateway allows you to accept payment on your Woocommerce store via Cards, USSD and Transfer.
	Version: 1.0
	Author: Data Quotient Limited
	Author URI: http://dataquot.com
  License:         GPL-2.0+
 	License URI:     http://www.gnu.org/licenses/gpl-2.0.txt
 	GitHub Plugin URI: https://github.com/SpayHQ/spay-woocommerce
*/
add_action( 'plugins_loaded', 'dq_spay_payments_init', 0 );
function dq_spay_payments_init() {
    //if condition use to do nothin while WooCommerce is not installed
  if ( ! class_exists( 'WC_Payment_Gateway' ) ) return;
  include_once( 'spay-payment.php' );
  // class add it too WooCommerce
  add_filter( 'woocommerce_payment_gateways', 'dq_spay_payments_gateway' );
  function dq_spay_payments_gateway( $methods ) {
    $methods[] = 'DQ_Spay_Payments';
    return $methods;
  }
}
// Add custom action links
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'dq_spay_payments_action_links' );
function dq_spay_payments_action_links( $links ) {
  $plugin_links = array(
    '<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout' ) . '">' . __( 'Settings', 'dq-spay-gateway' ) . '</a>',
  );
  return array_merge( $plugin_links, $links );
}
