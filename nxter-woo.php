<?php

/*
 *  Plugin Name: Nxter-Woo
 *  Plugin URI: https://www.nxter.org/nxter-woo
 *  Version: 0.0.3
 *  Author: scor2k
 *  Description: New payment method for Woo
 *  License: GPLv2 or later
 *  @class    WC_Gateway_Ardor
 *  @extends  WC_Payment_Gateway
 *  @package  WooCommerce/Classes/Payment
 */
  $nxter_woo_version  = "0.0.3";
  $ardor_node         = "https://random.nxter.org";
  $ardor_prefix       = "tstardor";
  $nxtbridge_api      = "https://random.nxter.org/api/v2";

  // Make sure WooCommerce is active
  if ( ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) return;

  /*
   * activate cron
   */

  register_activation_hook(__FILE__, 'nxter_woo_activation');
  function nxter_woo_activation() {
    if ( ! wp_get_schedule( 'nxter_woo_check_orders' ) ) {
      wp_schedule_event( time(), '5min', 'nxter_woo_check_orders' );
    }
    error_log('Nxter-woo plugin activated');
  }

  /*
   * deactivate cron
   */

  register_deactivation_hook(__FILE__, 'nxter_woo_deactivation');
  function nxter_woo_deactivation() {
    wp_clear_scheduled_hook('nxter_woo_check_orders');
    error_log('Nxter-woo plugin DEactivated');
  }


  include 'nxter-woo-configure.php';
  include 'inc/nxter-woo-cron.php';
  //include 'inc/nxter-woo-custom-status.php';

  // Example: https://stackoverflow.com/questions/17081483/custom-payment-method-in-woocommerce

  // https://www.skyverge.com/blog/how-to-create-a-simple-woocommerce-payment-gateway/
  // API : https://docs.woocommerce.com/document/payment-gateway-api/



  add_action( 'plugins_loaded', 'wc_ardor_gateway_init', 11 );
  
  function wc_ardor_gateway_init() {
    
    class WC_Gateway_Ardor extends WC_Payment_Gateway {
      function __construct() {

        $this->id                   = 'nxter_woo';
        $this->icon                 = plugin_dir_url( __FILE__ ) . "img/logo.png";

        // set field fro this payment method
        $this->has_fields           = true;

        $this->method_name          = 'Ardor AEUR';
        // for WooCommerce -> Settings -> Checkout page
        $this->method_title         = 'AEUR (ARDOR)';
        $this->method_description   = 'Any buyer can pay to you via AEUR child chain ARDOR cryptocurrency.';

        $this->init_form_fields();
        $this->init_settings();

        // load main or test net from settiongs
        $this->is_testnet           = $this->get_option('testnet');

        //error_log( $this->is_testnet );

        // icon and title for checkout page. You can change it in settings.
        $this->title                = $this->get_option('title');

        // action for saving settings
        add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array ( $this, 'process_admin_options') );
        // action for custom thank you page
        add_action( 'woocommerce_thankyou_custom', array( $this, 'thankyou_page') );
        // action for custom emails 
        add_action( 'woocommerce_email_before_order_table', array( $this, 'email_instructions'), 10, 3 );


      }

      public function payment_fields() {
        // https://stackoverflow.com/questions/43911247/how-to-get-order-id-in-payment-fields-in-woocommerce-payment-gateway-class
        global $woocommerce;

        $merchant_accountRS = $this->get_option('accountRS');
        $order_testnet      = $this->get_option('testnet');

        if ( ! isset( $_COOKIE['nxter-woo-order-id'] ) ) {
          $order_msg = bin2hex(openssl_random_pseudo_bytes(10));
          setcookie( 'nxter-woo-order-id', $order_msg, time()+3600); 
        } else {
          $order_msg = $_COOKIE['nxter-woo-order-id'];
        }

        if ( isset( $_COOKIE['NXTBridgeLedger'] ) ) {
          $buyer_from_cookie = $_COOKIE['NXTBridgeLedger'];
        } else { 
          $buyer_from_cookie = '';
        }

        if ( isset( $_COOKIE['nxter-woo-buyer-accountRS'] ) ) {
          $buyer_from_cookie = $_COOKIE['nxter-woo-buyer-accountRS'];
        }
        ?>

        <div id='nxter-woo-form'>
          <input type='hidden' name='merchant_accountRS' value='<?php echo esc_attr( $merchant_accountRS );  ?>' />
          <input type='hidden' name='order_sum' value='<?php echo esc_attr( $woocommerce->cart->total );  ?>' />
          <input type='hidden' name='order_msg' value='<?php echo esc_attr( $order_msg );  ?>' />
          <input type='hidden' name='order_testnet' value='<?php echo esc_attr( $order_testnet );  ?>' /> 

          <p class='form-row form-row-wide'>
          <p class='form-row form-row-wide'>
            <label for='buyer_accountRS' class=''>Your AEUR account: </label>
            <input type='text' id='buyer_accountRS' name='buyer_accountRS' value='<?php echo $buyer_from_cookie; ?>' placeholder='Your AccountRS' style='width: 100%;' />
          </p>
          <p class='form-row form-row-wide' style='color: darkred; font-size: 75%;'>
            <strong>You will not be able to continue if your account doesn’t have enough AEUR</strong>
          </p>
        </div>

        <?php

      } // end function payment_fields() 

      public function init_form_fields() {
        $this->form_fields = apply_filters( 'wc_ardor_form_fields', array( 
          'enabled' => array (
            'title'   => __('Enable/Disable', 'wc-ardor-gateway'),
            'type'    => 'checkbox',
            'label'   => __('Enable AEUR (ARDOR) support', 'wc-ardor-gateway'),
            'default' => 'yes',
          ), 

          'title' => array (
            'title'       => __('Title', 'wc-ardor-gateway'),
            'type'        => 'text',
            'description' =>  __('This controls the title which the user sees during checkout', 'wc-ardor-gateway'),
            'default'     =>  __('AEUR Payment', 'wc-ardor-gateway'),
            'desc_tip'    => true,
          ),

          'description' => array (
            'title'       => __('Customer Message', 'wc-ardor-gateway'),
            'type'        => 'textarea',
            'default'     =>  __('AEUR Payment', 'wc-ardor-gateway'),
            'desc_tip'    => true,
          ), 

          'instructions' => array (
            'title'       => __('Instructions', 'wc-ardor-gateway'),
            'type'        => 'textarea',
            'description' =>  __('Description', 'wc-ardor-gateway'),
            'default'     =>  'Just pay via Nxter Mobile app!',
            'desc_tip'    => true,
          ),

          'accountRS' => array (
            'title'       => __('Merchant accountRS', 'wc-ardor-gateway'),
            'type'        => 'text',
            'description' =>  __('Enter your correct accountRS for the payments.', 'wc-ardor-gateway'),
            'default'     =>  __('ARDOR-...', 'wc-ardor-gateway'),
            'desc_tip'    => true,
          ),

          'success_message' => array (
            'title'       => __('Success message', 'wc-ardor-gateway'),
            'type'        => 'textarea',
            'description' =>  __('This message will be show in header after order received and client have enought AEUR on his account', 'wc-ardor-gateway'),
            'default'     =>  'Do not forget to pay your order!',
            'desc_tip'    => true,
          ),
          'testnet' => array (
            'title'   => __('Use test-net for AEUR', 'wc-ardor-gateway'),
            'type'    => 'checkbox',
            'label'   => __('Set checkbox and save changes for use Ardor test-net instead main-net.', 'wc-ardor-gateway'),
            'default' => 'yes',
          ), 


        ) );

      } // end function

      public function process_payment( $order_id ) {
        global $woocommerce;
        $order = new WC_Order( $order_id );

        // Mark on-hold 
        $order->update_status('on-hold', __('Awaiting AEUR payment.', 'wc-ardor-gateway') );

        // reduce stock levels 2.6 version
        //$order->reduce_order_stock();

        // reduce stock levels 3.0 version
        wc_reduce_stock_levels( $order->get_id() );

        // remove cart
        WC()->cart->empty_cart();

        // remove cookie
        setcookie( 'nxter-woo-order-id', '', time()-3600); 


        // return thankyou redirect
        return array( 
          'result'    => 'success',
          'redirect'  => $this->get_return_url($order)
        );

      } // end function


      public function thankyou_page() {
        /*
        if ( $this->instructions ) {
          echo wpautop( wptxturize( $this->instructions ) );
        }
        */
        echo wpautop( wptxturize("Thank you page will be there") );
      }


      public function email_instructions( $order, $sent_to_admin = true, $plain_text = false ) {
        if ( ! $sent_to_admin && 'nxter_woo' === $order->payment_method && $order->has_status( 'on-hold' ) ) {
          //echo wpautop( wptexturize( $this->instructions ) ) . PHP_EOL;
          echo wpautop( wptexturize( "Email instruction will be there" ) ) . PHP_EOL;
        }
      }



    } // end WC_Gateway_Ardor

  }


  function wc_ardor_add_to_gateways( $gateways ) {
    $gateways[] = 'WC_Gateway_Ardor';
    return $gateways;
  }

  add_filter( 'woocommerce_payment_gateways', 'wc_ardor_add_to_gateways' );


  function get_aeur_balance($accountRS) {
    global $ardor_prefix, $ardor_node;
    try {
      $url = $ardor_node . "/" . $ardor_prefix . "?requestType=getBalance&account=" . esc_attr($accountRS) . "&chain=AEUR"; 
      $bal = file_get_contents($url);
      // if null -> return 0
      if ($bal === false ) { return (float)0.0;  }
      // decode json 
      $bal_json = json_decode($bal, true);

      if ( $bal_json['balanceNQT'] > 0 ) {
        $bal_aeur = $bal_json['balanceNQT'] / pow(10,4);
        return (float)$bal_aeur;
      }

    } catch ( Exception $e ) {
      return (float)0.0;
    }
  }

  function get_aeur_publickey($accountRS) {
    global $ardor_prefix, $ardor_node;
    try {
      $url = $ardor_node . "/" . $ardor_prefix . "?requestType=getAccountPublicKey&account=" . esc_attr($accountRS); 
      $pub = file_get_contents($url);
      // if null -> return 0
      if ($pub === false ) { return False;  }
      // decode json 
      $pub_json = json_decode($pub, true);

      if ( $pub_json['publicKey'] ) {
        $publicKey = $pub_json['publicKey'];
        return $publicKey;
      } else {
        return False;
      }

    } catch ( Exception $e ) {
      return False;
    }

  }

  // move to wallet api
  function generate_transaction_v2($senderPubkey, $recipientRS, $amount, $order_message) {

    try {
      $url = "https://random.nxter.org/api/wallet/sendmoney";

      $data = array(
        "publicKey"     => $senderPubkey,
        "recipient"     => $recipientRS,
        "msg"           => $order_message,
        "amount"        => $amount,
        "currencie"     => "aeur",
        "fee"           => -1,
      );

      $res = sendJsonPOST($url, $data);

      $res_json = json_decode($res, true);
      return $res_json;


    } catch ( Exception $e ) {
      //return "generate_transaction Exception";
      var_dump($e);
      return false;
    }


  }



  function generate_transaction($senderRS, $recipientRS, $amount, $order_message) {
    global $nxtbridge_api;

    try {
      //TODO :update API
      $url = $nxtbridge_api . "/woo/send/aeur";

      $data = array(
        "senderRS"      => $senderRS,
        "recipientRS"   => $recipientRS,
        "message"       => $order_message,
        "amount"        => $amount
      );

      $res = sendJsonPOST($url, $data);

      $res_json = json_decode($res, true);
      return $res_json;


    } catch ( Exception $e ) {
      //return "generate_transaction Exception";
      var_dump($e);
      return false;
    }


  }
    

  // action for custom checkout process
  add_action( 'woocommerce_checkout_process', 'nxter_woo_checkout_process' );
  function nxter_woo_checkout_process() {
    global $nxtbridge_api;

    // check for buyer_accountRS, try to get balance
    if ( $_POST['payment_method'] != 'nxter_woo' ) 
      return;

    if ( ! $_POST['buyer_accountRS'] ) {
      wc_add_notice( __( 'You forgot to enter your AccountRS', 'wc-ardor-gateway' ), 'error' );
      return false;
    } 

    if ( ! $_POST['merchant_accountRS'] ) {
      wc_add_notice( __( 'Looks like Merchant forgot to set up time plugin correctly. Sorry :(', 'wc-ardor-gateway' ), 'error' );
      return false;
    }

    if ( ! $_POST['order_sum'] ) {
      wc_add_notice( __( 'Some problems while check AEUR balance (can not get ordger summary)', 'wc-ardor-gateway' ), 'error' );
      return false;
    }

    if ( ! $_POST['order_msg'] ) {
      wc_add_notice( __( 'Some problems while check order message', 'wc-ardor-gateway' ), 'error' );
      return false;
    }

    // check account RS balance 
    $buyer         = esc_attr( $_POST['buyer_accountRS'] );
    // check publicKey first
    $pubKey         = get_aeur_publickey( $buyer );
    
    if ( $pubKey == False ) {
      #wc_add_notice( __( 'Your account does not have public Key. Read <a href="https://www.nxter.org/" target=_blank>FAQ</a> for more information.' , 'wc-ardor-gateway' ), 'error' );
      wc_add_notice( $pubKey , 'error' );
      return false;
    }

    // check balance
    $balance        = get_aeur_balance( $buyer );

    if ( (float)$balance <= (float)$summary ) {
      wc_add_notice( __( 'Not enought AEUR on your account' , 'wc-ardor-gateway' ), 'error' );
      return false;
    }

    $merchant       = esc_attr( $_POST['merchant_accountRS'] );
    $summary        = esc_attr( $_POST['order_sum'] );
    $order_message  = esc_attr( $_POST['order_msg'] );
    $order_testnet  = esc_attr( $_POST['order_testnet'] );


    // try to generate Tx
    //$tx = generate_transaction( $buyer, $merchant, $summary, $order_message );

    // new version. using wallet api to prepare transaction 
    $tx = generate_transaction_v2( $pubKey, $merchant, $summary, $order_message );

    if ( $tx === false ) {
      // return false
      $_POST['payment_url'] = 'None';
      wc_add_notice( __( 'We can not create transaction for you. Try to pay via your wallet, please.', 'wc-ardor-gateway' ), 'notice' );
      return true;
    }

    // tx -> json with "tx" & "url"
    //var_dump($tx);

    try {
      $saved_url = $tx['url'];

    } catch ( Exception $e ) {
      $saved_url = false;
    }

    if ( $saved_url === false or strlen($saved_url) < 10 ) {
      $_POST['payment_url'] = 'None';
      wc_add_notice( __( 'We can not save transaction for you. Try to pay via your wallet, please.', 'wc-ardor-gateway' ), 'notice' );

      $_POST['payment_url'] = 'Error while save Tx into DB'; 

      return true;
    }

    $success_msg = get_option('success_message');
    //$final_url = $nxtbridge_api . "/tx/" . $saved_url;
    $final_url = $saved_url;

    $_POST['payment_url'] = $final_url;

    wc_add_notice( __( $success_msg , 'wc-ardor-gateway' ), 'success' );
    return true;
  }


  // action for additional fields in order
  add_action( 'woocommerce_checkout_update_order_meta', 'nxter_woo_update_order' );
  function nxter_woo_update_order( $order_id ) {
    if ( $_POST['payment_method'] != 'nxter_woo' ) 
      return;

    update_post_meta( $order_id, 'merchantRS', esc_attr($_POST['merchant_accountRS']) );
    update_post_meta( $order_id, 'accountRS', esc_attr($_POST['buyer_accountRS']) );
    update_post_meta( $order_id, 'order_message', esc_attr($_POST['order_msg']) );
    # empty transaction number 
    update_post_meta( $order_id, 'order_tx_number', '');
    # url for transaction, 
    update_post_meta( $order_id, 'payment_url', esc_attr($_POST['payment_url']) );
    update_post_meta( $order_id, 'order_testnet', esc_attr($_POST['order_testnet']) );

    // remove cookie
    setcookie( 'nxter-woo-order-id', '', time()-3600); 
  }

  // display fields in order page
  add_action( 'woocommerce_admin_order_data_after_billing_address', 'nxter_woo_show_custom_fields_order', 10, 1);
  function nxter_woo_show_custom_fields_order($order) {
    $method = get_post_meta( $order->id, '_payment_method', true );
    if ( $method != 'nxter_woo' )
      return;

    $merchantRS     = get_post_meta( $order->get_id(), 'merchantRS', true);
    $accountRS      = get_post_meta( $order->get_id(), 'accountRS', true);
    $order_message  = get_post_meta( $order->get_id(), 'order_message', true);
    $order_tx       = get_post_meta( $order->get_id(), 'order_tx_number', true);
    $payment_url    = get_post_meta( $order->get_id(), 'payment_url', true);
    $order_testnet  = get_post_meta( $order->get_id(), 'order_testnet', true);

    echo '<p><strong>Merchant accountRS:</strong> ' . $merchantRS . '</p>';
    echo '<p><strong>Is it test-net?</strong> ' . $order_testnet . '</p>';
    echo '<p><strong>Client AccountRS:</strong> ' . $accountRS . '</p>';
    echo '<p><strong>Order Message ID:</strong> ' . $order_message . '</p>';
    echo '<p><strong>Payment Transaction: </strong> ' . $order_tx . '</p>';
    echo '<p><strong>Payment URL: </strong> ' . $payment_url . '</p>';
  }

  // change order fields
  add_filter('woocommerce_order_details_after_order_table', 'nxter_woo_custom_fields_on_order_page');
  function nxter_woo_custom_fields_on_order_page($order) {

    $accountRS          = get_post_meta( $order->get_id(), 'accountRS', true);
    $order_message      = get_post_meta( $order->get_id(), 'order_message', true);
    $merchantRS         = get_post_meta( $order->get_id(), 'merchantRS', true);
    $payment_url        = get_post_meta( $order->get_id(), 'payment_url', true);

    $payment_button     = str_replace("https", "sigbro", $payment_url);

  ?>
  <style type="text/css">
    .nxter-woo-sigbro-button { 
      display:none; 
    }
    @media screen and (max-width: 991.98px) {
      .nxter-woo-sigbro-button { 
        display: table-row; 
      }
    }
  </style>

  <br> 
  <h2 class="woocommerce-order-details__title"><?php _e('Additional information for AEUR payment', 'wc-ardor-gateway'); ?></h4>
  <table class="woocommerce-table">
    <tr class="nxter-woo-order-message">
      <th scope="row"><?php _e('Order Message:','wc-ardor-gateway'); ?></th>
      <td><?php echo $order_message; ?></td>
    </tr>
    <tr class="nxter-woo-merchant-account">
      <th scope="row"><?php _e('Merchant account:','wc-ardor-gateway'); ?></th>
      <td><?php echo $merchantRS; ?></td>
    </tr>
    <tr class="nxter-woo-merchant-account">
      <th scope="row"><?php _e('Your account:','wc-ardor-gateway'); ?></th>
      <td><?php echo $accountRS; ?></td>
    </tr>
    <tr class="nxter-woo-payment-url">
      <th scope="row"><?php _e('URL for the payment:','wc-ardor-gateway'); ?></th>
      <td><div id='nxter-woo-qrcode'></div></td>
    </tr>
    <tr class="nxter-woo-payment-url nxter-woo-sigbro-button">
      <td colspan=2>
        <a href="<?php echo $payment_button; ?>" class="woocommerce-Button button" role="button" target=_blank style="width: 100%; height:3rem; text-align: center">OPEN IN SIGBRO MOBILE</a>
      </td>
    </tr>


  </table>

  <script>
    jQuery(document).ready(function($){
      $('#nxter-woo-qrcode').qrcode( { width:200, height: 200, text: '<?php echo $payment_url; ?>' });
    });
  </script>



  <?php
  }






?>
