<?php
if (! defined('ABSPATH')) {
    exit;
}

class WC_Paga_Checkout extends WC_Payment_Gateway {
    /**
     * Check If Test mode is active;
     * 
     * @var bool
     */
    public $testmode;

    /**
     * Paga Payment Page Type
     * 
     * @var string
     */
    // public $payment_page;

    /**
     * Paga Checkout test public key.
     * 
     * @var string
     */

    public $test_public_key;

    /**
     * Paga Checkout test secret key.
     * 
     * @var string
     */
    public $test_secret_key;

    /**
     * Paga Checkout live public key
     * 
     * @var string
     */
    public $live_public_key;

    /**
     * Paga Checkout live secret key
     * 
     * @var string
     */
    public $live_secret_key;

    /**
     * Constructor
     */
    function __construct() 
    {
        $this->id  = 'paga-checkout';
        $this->icon = apply_filters('woocommerce_paga_icon', plugins_url( 'assets/pay-with-paga.png' , __FILE__ ) );
        $this->has_fields = false;
        $this->method_title = __('Pay with Paga ', 'paga-checkout');
        $this->method_description=sprintf(
            __('Paga Checkout provides an easy-to-integrate payment collection tool for any online merchant. It supports funding sources from Cards, Bank accounts and Paga wallet.<a href="%1$s" target="_blank">Sign up</a> for a Paga account, and <a href="%2$s" target="_blank">get your paga-checkout credentials</a>.', 'paga-checkout'),'https://www.mypaga.com/', 
            'https://www.mypaga.com/paga-business/register.paga'
        );
        $this->has_fields = true;

         //Loads the form fields
        $this->init_form_fields();

         //Load the settings
        $this->init_settings();


        //Get setting values
        $this->title =                      'Pay with Paga';
        $this->description = $this->get_option('description');
        $this->enabled = $this->get_option('enabled');
        $this->testmode=$this->get_option('testmode') == 'yes' ? true : false;

        // $this->payment_page = $this->get_option('payment_page');

        $this->test_public_key = $this->get_option('test_public_key');
        $this->test_secret_key = $this->get_option('test_secret_key');

        $this->live_public_key = $this->get_option('live_public_key');
        $this->live_secret_key = $this->get_option('live_secret_key');

        $this->public_key = $this->testmode ? $this->test_public_key : $this->live_public_key;
        $this->secret_key = $this->testmode ? $this->test_secret_key : $this->live_secret_key;

        //Hooks
        // add_action('wp_enqueue_scripts', array($this, 'generate_paga_checkout_widget'));
        // add_action('admin_enqueue_scripts', array($this, 'admin_scripts'));

        add_action('admin_notices', array($this, 'admin_notices'));
        add_action('woocommerce_update_options_payment_gateways_' . $this->id,
        array(
            $this, 'process_admin_options'
        )
        );

        
        add_action('woocommerce_receipt_' . $this->id, array($this, 'receipt_page'));

        //Webhook listener/API hook
        add_action('woocommerce_api_tbz_wc_paga_checkout_webhook', array($this, 'verify_transaction'));

        //Check if the gateway can be used 
        if (!$this->is_valid_for_use()) {
            $this->enabled= false;
        }

    }

     /**
         * Check if the gateway is enabled and available for user's country.
         */

        public function is_valid_for_use()
        {
            if (! in_array(get_woocommerce_currency(), apply_filters('woocommerce_paga_checkout_supported_currencies', array('NGN')))) {
               $this->msg= sprintf(__('Paga Checkout does not support your store currency, Kindly set it to either NGN (&#8358) <a href="%s">here</a>','paga-checkout'),admin_url('admin.php?page=wc-settings&tab=general'));
               return false;
            }
            return true;
        }



        /**
         * Display pagacheckout payment icon
         */

        public function get_icon()
        {
            $icon = '<img src="' .WC_HTTPS::force_https_url(plugins_url('assets/images/pay-with-paga.png', PAGA_CHECKOUT_MAIN_FILE)).'" alt="cards"/>';
            return apply_filters('woocommerce_gateway_icon', $icon, $this->id);
        }

        /**
         * Check if Paga Checkout merchant details is filled.
         */

        public function admin_notices()
         {
             if ($this ->enabled == 'no') {
                 return;
             }

             //Check required fields.
             if (!($this->public_key && $this->secret_key)) {
                 echo '<div class="error" </p>' .sprintf(__('Please enter your Paga merchant details <a href="%s">here</a> to be able to use Paga Checkout WooCommerce plugin.','paga-checkout'), admin_url('admin.php?page=wc-settings&tab=checkout&section=pagacheckout')) .'</p></div>';
                 return;
             }
         }

        
         /**
          * Check if Paga Checkout is enabled
          * 
          * @return bool
          */
         public function is_available ()
         {
            if ('yes' == $this->enabled) {
                if (!($this->public_key && $this->test_secret_key)) {
                    return false;
                }
                return true;
            }
            return false;
         }

         /**
          * Admin Panel Options
          */

          public function admin_options()
          {
              ?>
              <h2><?php _e('Paga Checkout', 'paga-checkout'); ?>
              <?php
            //   if (function_exists('wc_back_link')) {
            //       wc_back_link(__('Return to payments', 'paga-checkout'), admin_url('admin.php?page=wc-settings&tab=checkout'));
            //   }
               ?>
              </h2>
              <p><?php _e('Paga Payment Gateway allows you to accept payment on your Woocommerce Powered Store Using Paga Express Checkout', 'paga-checkout'); ?></p>
              <?php
              if ($this->is_valid_for_use()) {
                 echo '<table class="form-button">';
                 $this->generate_settings_html();
                 echo '</table>';
              } else {
                  ?>
                  <div class="inline error"><p><strong><?php _e('Paga Checkout is disabled ', 'paga-checkout'); ?></strong>: <?php echo $this->msg; ?></p></div>

                  <?php 
              }

           

          }

          /**
           * Initialise Paga Checkout Settings Form Fields.
           */
          public function init_form_fields()
          {
              $form_fields = array(
                  'enabled'                  =>  array(
                      'title'       => __('Enable/Disable', 'paga-checkout'),
                      'label'       => __('Enable Paga Checkout', 'paga-checkout'),
                      'type'        => 'checkbox',
                      'description' => __('Enable Paga Checkout as a payment option on the checkout page', 'paga-checkout'),
                      'default'     =>'no',
                      'desc_tip'    => true
                  ),
                  'description'                   => array(
                      'title'       => __('Description', 'paga-checkout'),
                      'type'        => 'textarea',
                      'description' => __('This gives the user information about the payment methods available during checkout', 'paga-checkout'),
                      'default'     => __('Make payments using Bank accounts, Cards and Paga', 'paga-checkout'),
                      'desc_tip'    => true,
                  ),

                  'testmode'                     => array(
                      'title'       => __('Test mode', 'paga-checkout'),
                      'label'       => __('Enable Test Mode', 'paga-checkout'),
                      'type'        => 'checkbox',
                      'description' => __('Test mode enables you to test payments before going live.', 'paga-checkout'),
                      'default'     => 'yes',
                      'desc_tip'    => true,

                  ),

                  'test_public_key'             => array(
                      'title'       => __('Test Public key', 'paga-checkout'),
                      'type'        => 'text',
                      'description' => __('Enter your Test Public Key here.', 'paga-checkout'),
                      'default'     => ''
                  ),

                  'test_secret_key'             => array(
                    'title'       => __('Test Secret key', 'paga-checkout'),
                    'type'        => 'text',
                    'description' => __('Enter your Test Secret Key here.', 'paga-checkout'),
                    'default'     => ''
                ),
                'live_public_key'             => array(
                    'title'       => __('Live Public key', 'paga-checkout'),
                    'type'        => 'text',
                    'description' => __('Enter your Live Public Key here.', 'paga-checkout'),
                    'default'     => ''
                ),

                'live_secret_key'             => array(
                  'title'       => __('Test Live key', 'paga-checkout'),
                  'type'        => 'text',
                  'description' => __('Enter your Live Secret Key here.', 'paga-checkout'),
                  'default'     => ''
              ),
                  );

                  $this->form_fields=$form_fields;
          } 


          public function generate_paga_checkout_widget() {
              if (!is_checkout_pay_page()) {
                  return;
              }

              if ($this->enabled=== 'no') {
                  return;
              }

              $order_key = urldecode($_GET['key']);
              $order_id = absint(get_query_var('order-pay'));
              $order = wc_get_order($order_id);
            //   print_r ($order);

            //   wp_enqueue_script('jquery');

            $paga_checkout_params = array(
                'public_key' => $this->public_key,
            );


            if (is_checkout_pay_page() && get_query_var('order-pay')) {
                $email      = method_exists($order,'get_billing_email') ? $order->get_billing_email() : $order->billing_email;
                $amount     = method_exists($order,'get_total') ?  $order->get_total():$order->order_total;
                $txnref     = $order_id .'_' .time();
                $the_order_id  = method_exists( $order, 'get_id' ) ? $order->get_id() : $order->id;
                $the_order_key = method_exists( $order, 'get_order_key' ) ? $order->get_order_key() : $order->order_key;
            }

            if ($the_order_id == $order_id && $the_order_key == $order_key) {
                $paga_checkout_params['email'] = $email;
                $paga_checkout_params['amount']= $amount;
                $paga_checkout_params['txn_ref']= $txnref;
                $paystack_params['currency']     = get_woocommerce_currency();

            }
            

            if($this->testmode) {
                $paga_checkout_params['checkout']  = esc_url_raw('https://beta.mypaga.com/checkout/');
            } else {
                $paga_checkout_params['checkout']  = esc_url_raw('https://www.mypaga.com/checkout/');
            }
            
            wp_localize_script('wc_paga_checkout', 'wc_paga_checkout_params', $paga_checkout_params);
            
            ?>
            <form action='<?php echo WC()->api_request_url('WC_Paga_Checkout'); ?>' method="POST">
            <script type='text/javascript' src='<?php echo $paga_checkout_params['checkout'];?>'
            data-public_key='<?php echo $paga_checkout_params['public_key']; ?>'
            data-amount='<?php echo $paga_checkout_params['amount'];?>'
            data-currency='<?php echo $paystack_params['currency'];?>'
            data-payment-reference='<?php echo $paga_checkout_params['txn_ref']; ?>'
            data-charge_url='<?php WC()->api_request_url('WC_Paga_Checkout'); ?>'
            
            ></script>
            </form>
            <?php
           
          }


          /**
           * Process the payment and return the result
           * 
           * @param int $order_id
           * 
           * @return array|void
           */

           public function process_payment($order_id)
           {
               $order = wc_get_order($order_id);

               return array(
                   'result' => 'success',
                   'redirect' => $order->get_checkout_payment_url(true)
               );
           }

           /**
            * Display the payment page
            * @param $order_id
            */
           public function receipt_page($order_id) {
               $order = wc_get_order($order_id);
               echo '<p>'.__('Thank you for your order, please click the button to pay with Paga.', 'paga-checkout');
               echo $this->generate_paga_checkout_widget($order);
           }


           /**
            * Verify Paga Checkout  payment
            */

           public function verify_paga_checkout_transaction() {
            @ob_clean();

			if ( isset( $_REQUEST['paga_checkout_txnref'] ) ){

				if($this->testmode) {
					$verify_url = 'https://beta.mypaga.com/checkout/transaction/verify'; 
				} else {
					$verify_url = esc_url('https://www.mypaga.com/checkout/transaction/verify'); 
				}
				
				$paga_trans_ref =  empty($_REQUEST['paga_checkout_txnref']) ? '' : $_REQUEST['paga_checkout_txnref'];

				$order_details 	= explode( '_',  $paga_trans_ref);

				$order_id 		= (int) $order_details[0];

		        $order 			= wc_get_order( $order_id );

				$body = array('publicKey' => $this->public_key,
	            	'secretKey' => $this->secret_key,
	            	'paymentReference' => $paga_trans_ref,
	            	'amount' => $order->order_total,
	            	'currency' => get_woocommerce_currency());
	        	$body = json_encode($body);

				$headers = array(
					'Content-Type' => 'application/json'
				);

				$args = array(
					'method'	=> 'POST',
					'headers'	=> $headers,
					'body'		=> $body,
					'timeout'	=> 60
				);

				$request = wp_remote_post( esc_url($verify_url), $args );

		        if ( ! is_wp_error( $request ) && 200 == wp_remote_retrieve_response_code( $request ) ) {

	            	$paga_api_response = json_decode( wp_remote_retrieve_body( $request ), true );

					if (array_key_exists('status_code', $paga_api_response) && ($paga_api_response['status_code'])) {

				        if ( in_array( $order->get_status(), array( 'processing', 'completed', 'on-hold' ) ) ) {

				        	wp_redirect( $this->get_return_url( $order ) );

							exit;
				        }

						$order->payment_complete( $paga_trans_ref );

						$order->add_order_note( sprintf( 'Payment via Paga successful (Transaction Reference: %s)', $paga_trans_ref ) );

						wc_empty_cart();

					} else {

						$order->update_status( 'failed', 'Payment was declined.' );
					}
		        }

				wp_redirect( $this->get_return_url( $order ) );

				exit;
			}

			wp_redirect( wc_get_page_permalink( 'cart' ) );

			exit;

		}
           


}
