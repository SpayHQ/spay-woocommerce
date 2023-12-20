<?php

const LIVE_JS_URL = plugins_url('assets/pay_live/static/js/spay_checkout.js', __FILE__);
const TEST_JS_URL = plugins_url('assets/pay_test/static/js/spay_checkout.js', __FILE__);
const ICON_URL =   plugins_url('assets/images/spay-icon.png', __FILE__);


class DQ_Spay_Payments extends WC_Payment_Gateway_CC {
    public function __construct() {
        $this->id                 = 'dq_spay_payments';
        $this->method_title       = esc_html__('SPay', 'dq-spay-gateway');
        $this->method_description = esc_html__('SPay Plug-in for WooCommerce', 'dq-spay-gateway');
        $this->title              = $this->get_option('title');
        $this->description        = $this->get_option('description');
        $this->icon               = apply_filters('woocommerce_webpay_icon',ICON_URL);
        $this->has_fields         = false;

        // Setting defines
        $this->init_form_fields();

        // Load time variable setting
        $this->init_settings();

        // Turn these settings into variables we can use
        foreach ($this->settings as $setting_key => $value) {
            $this->$setting_key = $value;
        }

        // Admin notice for test mode
        add_action('admin_notices', array($this, 'dq_spay_payments_testmode_notice'));

        // Further check of SSL if you want
       // add_action('admin_notices', array($this, 'do_ssl_check'));

        add_action('woocommerce_receipt_' . $this->id, array($this, 'receipt_page'));

        // Payment listener/API hook
        add_action('woocommerce_api_' . $this->id, array($this, 'check_payment_response'));

        // Save settings
        if (is_admin()) {
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        }

        //Check if the gateway can be used
        if (!$this->is_valid_for_use()) {
            $this->enabled = false;
        }
    }

    // Administration fields for specific Gateway
    public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'title'       => esc_html__('Enable/Disable', 'dq-spay-gateway'),
                'type'        => 'checkbox',
                'label'       => esc_html__('Enable SPay Payment Gateway', 'dq-spay-gateway'),
                'description' => esc_html__('Enable or disable the gateway.', 'dq-spay-gateway'),
                'desc_tip'    => true,
                'default'     => 'yes',
            ),
            'title' => array(
                'title'       => esc_html__('Title', 'dq-spay-gateway'),
                'type'        => 'text',
                'description' => esc_html__('This controls the title which the user sees during checkout.', 'dq-spay-gateway'),
                'desc_tip'    => false,
                'default'     => esc_html__('Debit/Credit Cards, USSD & Transfer', 'dq-spay-gateway'),
            ),
            'description' => array(
                'title'       => esc_html__('Description', 'dq-spay-gateway'),
                'type'        => 'textarea',
                'description' => esc_html__('SPay WooCommerce Payment Gateway allows you to accept payment on your Woocommerce store via Cards, USSD and Transfer', 'dq-spay-gateway'),
                'default'     => esc_html__('Accepts Cards, USSD and Transfer', 'dq-spay-gateway'),
            ),
            'merchantcode' => array(
                'title'       => esc_html__('Merchant Code', 'dq-spay-gateway'),
                'type'        => 'text',
                'description' => esc_html__('Merchant Code from your SPay Dashboard', 'dq-spay-gateway'),
                'desc_tip'    => true,
            ),
            'environment' => array(
                'title'       => esc_html__('Test Mode', 'dq-spay-gateway'),
                'type'        => 'checkbox',
                'label'       => esc_html__('Enable Test Mode', 'dq-spay-gateway'),
                'default'     => 'yes',
                'description' => esc_html__('Test mode enables you to test payments before going live. <br />If you are ready to start receiving payments on your site, kindly uncheck this.', 'dq-spay-gateway'),
            ),
        );
    }


    // Response handled for payment gateway
    public function process_payment($order_id) {
      $customer_order = wc_get_order($order_id);

      return array(
          'result'   => 'success',
          'redirect' => esc_url($customer_order->get_checkout_payment_url(true)),
      );
    }

    // Check if it is valid
    public function is_valid_for_use() {
    //   if (!in_array(get_woocommerce_currency(), array('NGN'), true)) {
    //       $this->msg = sprintf(
    //           esc_html('SPay Gateway doesn\'t support your store currency, set it to Nigerian Naira &#8358; <a href="%s">here</a>'),
    //           esc_url(admin_url('admin.php?page=wc-settings&tab=general'))
    //       );
    //       return false;
    //   }

      return true;
    }

    // Validate fields
    public function validate_fields() {
      return true;
    }

    // // Check SSL
    // public function do_ssl_check() {
    //   if ($this->enabled === "yes") {
    //       if (get_option('woocommerce_force_ssl_checkout') === "no") {
    //           printf(
    //               '<div class="error"><p>%s</p></div>',
    //               sprintf(
    //                   esc_html('<strong>%s</strong> is enabled and WooCommerce is not forcing the SSL certificate on your checkout page. Please ensure that you have a valid SSL certificate and that you are <a href="%s">forcing the checkout pages to be secured.</a>'),
    //                   $this->method_title,
    //                   esc_url(admin_url('admin.php?page=wc-settings&tab=checkout'))
    //               )
    //           );
    //       }
    //   }
    // } 
  


    // Display Button
    public function generate_spay_form($order_id) {
      // Get order details
      $customer_order = wc_get_order($order_id);

      $order_total    = $customer_order->get_total();
      $trans_ref      = "woo-" . uniqid() . '-' . $order_id;
      $first_name     = $this->get_order_billing_first_name($customer_order);
      $last_name      = $this->get_order_billing_last_name($customer_order);
      $billing_email  = $this->get_order_billing_email($customer_order);
      $billing_phone  = $this->get_order_billing_phone($customer_order);

      // Check setting environment
      $environment = ($this->environment === "yes") ? 'TRUE' : 'FALSE';

      // Set environment URL for JavaScript
      $environment_url = ($environment === 'FALSE')? LIVE_JS_URL : TEST_JS_URL;

      wp_enqueue_style('style', 'assets/pay_live/static/css/spay_checkout.css');

      wc_enqueue_js('
          jQuery("#payWithSpay").click();
      ');

      return '
          <script>
              function payWithSpay() {
                  var handler = {
                      amount:' . $order_total . ',
                      currency: "NGN",
                      reference: "' . $trans_ref . '",
                      merchantCode: "' . $this->merchantcode . '",
                      customer: {
                          firstName:"' . esc_js($first_name) . '",
                          lastName: "' . esc_js($last_name) . '",
                          phone: "' . esc_js($billing_phone) . '",
                          email:"' . esc_js($billing_email) . '",
                      },
                      callback: function (response) {
                          url = "' . WC()->api_request_url('DQ_spay_payments') . '";
                          url += "?trans_ref=";
                          url += "' . $trans_ref . '";
                          url += "&spay_trans_ref=";
                          url += response.reference;
                          window.location.replace(url);
                      },
                      onClose: function () {
                          url = "' . WC()->api_request_url('DQ_spay_payments') . '";
                          url += "?trans_ref=";
                          url += "' . $trans_ref . '";
                          window.location.replace(url);
                      },
                  };

                  window.SpayCheckout.init(handler);
              }
          </script>
          <div class="payment_buttons" style="visibility: hidden">
              <button id="payWithSpay" onclick="payWithSpay()">Pay With Spay</button>
          </div>

          <script type="text/javascript" src="' . esc_url($environment_url) . '"> </script>
      ';
    }

    // Helper function to get billing first name
    private function get_order_billing_first_name($order) {
      return method_exists($order, 'get_billing_first_name') ? $order->get_billing_first_name() : $order->billing_first_name;
    }

    // Helper function to get billing last name
    private function get_order_billing_last_name($order) {
      return method_exists($order, 'get_billing_last_name') ? $order->get_billing_last_name() : $order->billing_last_name;
    }

    // Helper function to get billing email
    private function get_order_billing_email($order) {
      return method_exists($order, 'get_billing_email') ? $order->get_billing_email() : $order->billing_email;
    }

    // Helper function to get billing phone
    private function get_order_billing_phone($order) {
      return method_exists($order, 'get_billing_phone') ? $order->get_billing_phone() : $order->billing_phone;
    }



    // Receipt page
    public function receipt_page($order_id) {
      printf(
          '<p>%s</p>',
          esc_html__('Thank you - your order is now pending payment. You will be automatically redirected to SPay Gateway to make payment.', 'dq-spay-gateway')
      );
      echo esc_attr($this->generate_spay_form($order_id));
    }
  

    // Check payment response
    public function check_payment_response() {
      $spay_trans_ref = sanitize_text_field($_GET['spay_trans_ref']);
      $trans_ref      = sanitize_text_field($_GET['trans_ref']);
      $reference      = isset($spay_trans_ref) ? $spay_trans_ref : $trans_ref;

      $order_id = explode('-', $trans_ref);
      $order    = wc_get_order($order_id[2]);

      $order_total   = $order->get_total();
      $response      = $this->check_transaction_details($reference);
      $response_code = $response['status'];
      $response_amount = $response['amount'];

      if ('SUCCESSFUL' === $response_code) {
          // Check if the amount paid is less than the order amount.
          if ($response_amount < $order_total) {
              // Update the order status
              $order->update_status('on-hold', '');

              // Error Note
              $message = sprintf(
                  esc_html__('Payment successful, but the amount paid is less than the total order amount. Your order is currently on-hold. Kindly contact us for more information regarding your order and payment status. Transaction Reference: %s', 'dq-spay-gateway'),
                  $reference
              );
              $message_type = 'notice';

              // Add Customer Order Note
              $order->add_order_note($message, 1);

              // Add Admin Order Note
              $order->add_order_note(sprintf(
                  esc_html__('Look into this order. This order is currently on hold. Reason: Amount paid is less than the total order amount. Amount Paid was &#8358;%s while the total order amount is &#8358;%s. Transaction Reference: %s', 'dq-spay-gateway'),
                  $response_amount,
                  $order_total,
                  $reference
              ));

              add_post_meta($order_id, '_transaction_id', $reference, true);

              // Reduce stock levels
              $order->reduce_order_stock();

              // Empty cart
              wc_empty_cart();
          } else {
              $message      = sprintf(
                  esc_html__('Payment Successful. Transaction Reference: %s', 'dq-spay-gateway'),
                  $reference
              );
              $message_type = 'success';

              // Add admin order note
              $order->add_order_note(sprintf(
                  esc_html__('Payment Via SPay Gateway Transaction Reference: %s', 'dq-spay-gateway'),
                  $reference
              ));

              // Add customer order note
              $order->add_order_note(sprintf(
                  esc_html__('Payment Successful. Transaction Reference: %s', 'dq-spay-gateway'),
                  $reference
              ), 1);

              $order->payment_complete($reference);
              $order->update_status('completed');

              // Empty cart
              wc_empty_cart();
          }
      } elseif ('PROCESSING' === $response_code) {
          $order->update_status('processing', '');

          // Error Note
          $message      = sprintf(
              esc_html__('Payment in progress, Kindly contact us for more information regarding your order and payment status. Transaction Reference: %s', 'dq-spay-gateway'),
              $reference
          );
          $message_type = 'notice';

          // Add Customer Order Note
          $order->add_order_note($message, 1);

          // Add Admin Order Note
          $order->add_order_note(esc_html__('Look into this order. This order is currently on hold. Reason: payment is processing', 'dq-spay-gateway'));

          add_post_meta($order_id, '_transaction_id', $reference, true);

          // Reduce stock levels
          $order->reduce_order_stock();

          // Empty cart
          wc_empty_cart();
      } elseif ('FAILED' === $response_code) {
          // Process a failed transaction
          $message      = sprintf(esc_html__('Transaction %s Failed', 'dq-spay-gateway'), $reference);
          $message_type = 'error';

          // Add Customer Order Note
          $order->add_order_note($message, 1);

          // Add Admin Order Note
          $order->add_order_note($message);

          $order->save();

          // Update the order status
          $order->update_status('failed', '');
      } else {
          // Process a failed transaction
          $message      = sprintf(esc_html__('Transaction %s not completed by customer', 'dq-spay-gateway'), $reference);
          $message_type = 'error';

          // Add Customer Order Note
          $order->add_order_note($message, 1);

          // Add Admin Order Note
          $order->add_order_note($message);

          $order->save();

          // Update the order status
          $order->update_status('pending-payment', '');
      }

      $notification_message = array(
          'message'       => $message,
          'message_type'  => $message_type,
      );

      update_post_meta($order_id, '_spay_wc_message', $notification_message);

      wp_redirect($this->get_return_url($order));

      exit;
    }

    // Check transaction details
    public function check_transaction_details($txnref) {
      $environment = ($this->environment === "yes") ? 'TRUE' : 'FALSE';
      $status_url  = ($environment === 'FALSE') ? 'https://collections.spaybusiness.com/api/v1/payments/' : 'https://testcollections.spaybusiness.com/api/v1/payments/';
      $url         = $status_url . $txnref;
  
      $args     = array(
          'timeout' => 90,
      );
      $response = wp_remote_get($url, $args);
  
      if (!is_wp_error($response) && 200 === wp_remote_retrieve_response_code($response)) {
          $response = json_decode($response['body'], true);
      } else {
          $response['status'] = 'NOT_FOUND';
      }
  
      return $response;
    }
  
    // print test notice
    public function dq_spay_payments_testmode_notice() {
        if (!current_user_can('manage_options')) {
            return;
        }
    
        $environment      = ($this->environment === "yes") ? true : false;
        if($environment){
            $settings_page_url = esc_url(admin_url('admin.php?page=wc-settings&tab=checkout&section=dq_spay_payments'));
        
            printf(
                '<div class="error"><p>%s</p></div>',
                sprintf(
                    esc_html__('SPay test mode is still enabled. Click %s to disable it when you want to start accepting live payments on your site.', 'woo-spay'),
                    '<strong><a href="' . $settings_page_url . '">' . esc_html__('here') . '</a></strong>'
                )
            );
        }
    }
  
  }