<?php
  /*
   *
   * configure wp-cron to run every 5 min and check all orders 
   *
   */

  // https://wordpress.stackexchange.com/questions/208135/how-to-run-a-function-every-5-minutes

  function nxter_woo_schedules( $schedules ) {
    if ( !isset( $schedules["5min"] ) ) {
      $schedules["1min"] = array(
        'interval'      => 1*60,
        'display'       => __('Once every 1 minute')
      );
      $schedules["5min"] = array(
        'interval'      => 5*60,
        'display'       => __('Once every 5 minutes')
      );
    }
    return $schedules;
  }

  add_filter( 'cron_schedules', 'nxter_woo_schedules' );


  /*
   * start my cron event
   *
   */
  /*
   * function for check all orders
   *
   */
  // http://80.211.156.19:8080/wp-cron.php?doing_wp_cron


  function nxter_woo_exec_scheduler() {
    global $nxtbridge_api;

    error_log('Exec nxter_woo_check_orders');
    // generate URL
    $url = $nxtbridge_api . "/woo/check" ;

    //filter only our orders in on-hold status
    $args = array( 
      'payment_method'    => 'nxter_woo',
      'status'            => 'on-hold'
    );

    $orders = wc_get_orders( $args );

    foreach ( $orders as $order ) {
      //error_log( print_r( $order->get_id(), true ) );
      //error_log( print_r( $order->get_status(), true ) );

      $merchantRS     = get_post_meta( $order->get_id(), 'merchantRS', true);
      $order_message  = get_post_meta( $order->get_id(), 'order_message', true);
      $order_summ     = $order->get_total();
      $order_testnet  = get_post_meta( $order->get_id(), 'order_testnet', true);

      $tmp = array(
        'recipientRS' => $merchantRS,
        'message'     => $order_message,
        'amount'      => $order_summ,
        'testnet'     => $order_testnet 
      );
      
      $is_payed = sendJsonPOST($url, $tmp);

      try {
        $is_payed_json = json_decode($is_payed, true);

        if ( $is_payed_json['fullHash'] != null ) {
          error_log( print_r( $is_payed_json, true ) );
          // should update order status and save Tx
          update_post_meta( $order->get_id(), 'order_tx_number', $is_payed_json['fullHash'] );
          // change status
          $order->update_status('processing', __('Recieved AEUR payment.', 'wc-ardor-gateway') . " Transaction FullHash is: " . $is_payed_json['fullHash'] );
        }
      } catch ( Exception $e ) {
        error_log( print_r( $tmp, true ) );
        error_log( print_r( $e, true ) );
         
      }
      
      //error_log( print_r( $is_payed, true ) );

    }

    //error_log( print_r($orders, true) );

  }

  add_action('nxter_woo_check_orders', 'nxter_woo_exec_scheduler');



?>
