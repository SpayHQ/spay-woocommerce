<?php
  if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly   
  class DQ_spay_payments extends WC_Payment_Gateway {
    function __construct() {
      $this->id = "dq_spay_payments";
      $this->method_title = "SPay";
      $this->method_description = "SPay Plug-in for WooCommerce";
      $this->title = $this->get_option( 'title' );
      $this->description = $this->get_option( 'description' );
      $this->icon = apply_filters( 'woocommerce_webpay_icon', plugins_url( 'assets/images/spay-icon.png' , __FILE__ ) );
      $this->has_fields = false;
     
      
    
      // setting defines
      $this->init_form_fields();

      // load time variable setting
      $this->init_settings();
      
      // Turn these settings into variables we can use
      foreach ( $this->settings as $setting_key => $value ) {
        $this->$setting_key = $value;
      }
      
      // admin notice for test mode
      add_action( 'admin_notices', array( $this,'dq_spay_payments_testmode_notice') );

      // further check of SSL if you want
      add_action( 'admin_notices', array( $this,  'do_ssl_check' ) );

      add_action( 'woocommerce_receipt_' . $this->id, array( $this, 'receipt_page' ) ); 

      // Payment listener/API hook
			add_action( 'woocommerce_api_' . $this->id, array( $this, 'check_payment_response' ) );
    
      // Save settings
      if ( is_admin() ) {
        add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
      }  

      // Check if the gateway can be used
      if ( ! $this->is_valid_for_use() ) {
          $this->enabled = false;
      }

    } // Here is the  End __construct()

    // administration fields for specific Gateway
    public function init_form_fields() {
      $this->form_fields = array(
          'enabled'       => array(
          'title' 		    => 'Enable/Disable',
          'type' 			    => 'checkbox',
          'label' 		    => 'Enable SPay Payment Gateway',
          'description' 	=> 'Enable or disable the gateway.',
          'desc_tip'      => true,
          'default' 		  => 'yes'
        ),
          'title'         => array(
          'title' 		    => 'Title',
          'type' 			    => 'text',
          'description' 	=> 'This controls the title which the user sees during checkout.',
          'desc_tip'      => false,
          'default' 		  => 'Payment Gateway: Card,USSD & Transfer'
        ),
        'description'     => array(
          'title' 		    => 'Description',
          'type' 			    => 'textarea',
          'description'   => 'SPay WooCommerce Payment Gateway allows you to accept payment on your Woocommerce store via Cards, USSD and Transfer',
          'default' 		  => 'Accepts Cards, USSD and Transfer'
        ),
        'merchantcode'    => array(
          'title'       	=> 'Merchant Code',
          'type'        	=> 'text',
          'description' 	=> 'Merchant Code from your SPay Dashboard',
          'desc_tip'      => true,
        ),
        'environment'     => array(
          'title'       	=> 'Test Mode',
          'type'        	=> 'checkbox',
          'label'       	=> 'Enable Test Mode',
          'default'     	=> 'yes',
          'description' 	=> 'Test mode enables you to test payments before going live. <br />If you ready to start receving payment on your site, kindly uncheck this.',
        )
      );    
    }

    // Response handled for payment gateway
    public function process_payment( $order_id ) {
      $customer_order = new WC_Order( $order_id );

      return array(
          'result' 	=> 'success',
          'redirect'	=> $customer_order->get_checkout_payment_url( true )
      );

    }


    // Check if it is valid
    public function is_valid_for_use() {

			if( ! in_array( get_woocommerce_currency(), array('NGN') ) ) {
				$this->msg = 'SPay Gateway doesn\'t support your store currency, set it to Nigerian Naira &#8358; <a href="' . get_bloginfo('wpurl') . '/wp-admin/admin.php?page=wc-settings&tab=general">here</a>';
				return false;
			}

			return true;
		}
  
    // Validate fields
    public function validate_fields() {
      return true;
    }

    // Check SSL
    public function do_ssl_check() {
      if( $this->enabled == "yes" ) {
        if( get_option( 'woocommerce_force_ssl_checkout' ) == "no" ) {
          echo esc_html("<div class=\"error\"><p>"). sprintf( __( "<strong>%s</strong> is enabled and WooCommerce is not forcing the SSL certificate on your checkout page. Please ensure that you have a valid SSL certificate and that you are <a href=\"%s\">forcing the checkout pages to be secured.</a>" ), $this->method_title, admin_url( 'admin.php?page=wc-settings&tab=checkout' ) ) .esc_html("</p></div>");  
        }
      }    
    }

    // Display Button
    public function generate_spay_form($order_id) {

      // Get order details
      $customer_order = wc_get_order( $order_id );
     
      $order_total	= $customer_order->get_total();
      $trans_ref		  = "woo-" . uniqid() . '-' . $order_id;
			$first_name  	  = method_exists( $customer_order, 'get_billing_first_name' ) ? $customer_order->get_billing_first_name() : $customer_order->billing_first_name;
			$last_name  	  = method_exists( $customer_order, 'get_billing_last_name' ) ? $customer_order->get_billing_last_name() : $customer_order->billing_last_name;
      $billing_email  = method_exists( $customer_order, 'get_billing_email' ) ? $customer_order->get_billing_email() : $customer_order->billing_email;
      $billing_phone  = method_exists( $customer_order, 'get_billing_phone' ) ? $customer_order->get_billing_phone() : $customer_order->billing_phone;
    
     
      // checck setting environement
      $environment = ( $this->environment == "yes" ) ? 'TRUE' : 'FALSE';
      
      // set environment url for JavaScript
      $environment_url = ( "FALSE" == $environment ) 
                                ? 'https://checkout.spaybusiness.com/pay/static/js/spay_checkout.js'
                : 'https://testcheckout.spaybusiness.com/pay/static/js/spay_checkout.js';

      wp_enqueue_style( 'style', 'https://checkout.spaybusiness.com/pay/static/css/spay_checkout.css' );

      wc_enqueue_js( '
				jQuery("#payWithSpay").click();
			' );

      return '
        <script>
          function payWithSpay() {
          var handler = {
            amount:' . $order_total .',
            currency: "NGN",
            reference: "' .  $trans_ref .'",
            merchantCode: "'. $this->merchantcode .'",
            customer: {
              firstName:"' . $first_name .'",
              lastName: "' . $last_name .'",
              phone: "' . $billing_phone .'",
              email:"' . $billing_email .'",
            },
            callback: function (response) {
              url = "'. WC()->api_request_url( 'DQ_spay_payments' ). '";
              url += "?trans_ref=";
              url += "' .  $trans_ref .'";
              url += "&spay_trans_ref=";
              url += response.reference;
              window.location.replace(url);
            
            },
            onClose: function () {
              url = "'. WC()->api_request_url( 'DQ_spay_payments' ). '";
              url += "?trans_ref=";
              url += "' .  $trans_ref .'";
              window.location.replace(url);
            },
        };

        window.SpayCheckout.init(handler);
      }
          </script>
        <div class="payment_buttons" style="visibility: hidden">
          <button id="payWithSpay" onclick="payWithSpay()">Pay With Spay</button>
        </div>
    
        <script type="text/javascript" src="'. $environment_url .'"> </script> 
        ';
      
    }


    // Receipt page
    public function receipt_page($order_id) {
      echo esc_html('<p>Thank you - your order is now pending payment. You will be automatically redirected to SPay Gateway to make payment.</p>');
      echo esc_attr($this->generate_spay_form($order_id));
    }

    public function check_payment_response() {
      $spay_trans_ref = sanitize_text_field($_GET['spay_trans_ref']);
      $trans_ref = sanitize_text_field($_GET['trans_ref']);
      $reference = isset($spay_trans_ref) ? $spay_trans_ref :  $trans_ref;

      $order_id =  explode( '-',  $trans_ref);

      $order    = wc_get_order( $order_id[2] );

      $order_total	= $order->get_total();

      $response       = $this->check_transaction_details($reference);

      $response_code 	= $response['status'];
      $response_amount 	= $response['amount'];

      if( 'SUCCESSFUL' == $response_code ) {

            // check if the amount paid is less than the order amount.
            if($response_amount < $order_total ) {

              //Update the order status
              $order->update_status( 'on-hold', '' );

              //Error Note
              $message = 'Payment successful, but the amount paid is less than the total order amount.<br />Your order is currently on-hold.<br />Kindly contact us for more information regarding your order and payment status.<br />Transaction Reference: '.$reference;
              $message_type = 'notice';

              //Add Customer Order Note
              $order->add_order_note( $message, 1 );

              //Add Admin Order Note
              $order->add_order_note( 'Look into this order. <br />This order is currently on hold.<br />Reason: Amount paid is less than the total order amount.<br />Amount Paid was &#8358;'.$response_amount.' while the total order amount is &#8358;'.$order_total.'<br />Transaction Reference: '.$reference );

              add_post_meta( $order_id, '_transaction_id', $reference, true );

              // Reduce stock levels
              $order->reduce_order_stock();

              // Empty cart
              wc_empty_cart();

            } 
            else {

              $message = 'Payment Successful.<br />Transaction Reference: '.$reference;
              $message_type = 'success';

              //Add admin order note
              $order->add_order_note( 'Payment Via SPay Gateway<br />Transaction Reference: '.$reference );

                        //Add customer order note
              $order->add_order_note( 'Payment Successful.<br />Transaction Reference: '.$reference, 1 );

              $order->payment_complete( $reference );

							
							$order->update_status( 'completed' );
							

              // Empty cart
              wc_empty_cart();
              
            }

      }elseif( 'PROCESSING' == $response_code ) {

          $order->update_status( 'processing', '' );

          //Error Note
          $message = 'Payment in progress, Kindly contact us for more information regarding your order and payment status.<br />Transaction Reference: '.$reference ;
          $message_type = 'notice';

          //Add Customer Order Note
          $order->add_order_note( $message, 1 );

          //Add Admin Order Note
          $order->add_order_note( 'Look into this order. <br />This order is currently on hold.<br />Reason: payment is processing' );

          add_post_meta( $order_id, '_transaction_id', $reference, true );

          // Reduce stock levels
          $order->reduce_order_stock();

          // Empty cart
          wc_empty_cart();

      }elseif( 'FAILED' == $response_code ) {

         //process a failed transaction
         $message =  'Transaction '. $reference . ' Failed';
         $message_type = 'error';

         //Add Customer Order Note
         $order->add_order_note( $message, 1 );

         //Add Admin Order Note
         $order->add_order_note( $message );

         $order->save();

         //Update the order status
         $order->update_status( 'failed', '' );

      }else{

         //process a failed transaction
         $message =  'Transaction '. $reference . ' not completed by customer';
         $message_type = 'error';

         //Add Customer Order Note
         $order->add_order_note( $message, 1 );

         //Add Admin Order Note
         $order->add_order_note( $message );

         $order->save();

         //Update the order status
         $order->update_status( 'pending-payment', '' );

      }
  
      $notification_message = array(
          'message'		=> $message,
          'message_type' 	=> $message_type
      );

			update_post_meta( $order_id, '_spay_wc_message', $notification_message );

      wp_redirect( $this->get_return_url( $order ) );

      exit;
    }


    public function check_transaction_details( $txnref ) {

        $environment = ( $this->environment == "yes" ) ? 'TRUE' : 'FALSE';

        $status_url = ( "FALSE" == $environment ) ? 'https://collections.spaybusiness.com//api/v1/payments/' : 'https://testcollections.spaybusiness.com//api/v1/payments/';

        $url  = $status_url .  $txnref ;

        $args = array(
          'timeout'	=> 90
        );

        $response = wp_remote_get( $url, $args );

        if ( ! is_wp_error( $response ) && 200 == wp_remote_retrieve_response_code( $response ) ) {

          $response = json_decode( $response['body'], true );

        } else {
          $response['status'] = 'NOT_FOUND';
        }

        return $response;
		}

    public function  dq_spay_payments_testmode_notice() {

      if ( ! current_user_can( 'manage_options' ) ) {
        return;
      }
    
      $environment = ( $this->environment == "yes" ) ? 'TRUE' : 'FALSE';

    
      if ( "TRUE" == $environment  ) {
        /*  SPay settings page URL link. */
        echo esc_html('<div class="error"><p>') . sprintf( __( 'SPay test mode is still enabled, Click <strong><a href="%s">here</a></strong> to disable it when you want to start accepting live payment on your site.', 'woo-spay' ), esc_url( admin_url( 'admin.php?page=wc-settings&tab=checkout&section=dq_spay_payments' ) ) ) . esc_html('</p></div>');
      }
    }
  }