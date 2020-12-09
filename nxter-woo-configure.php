<?php
  /*
   * add javascript to wordpress
   */

  // Load javascript for qrcode like in nxtbridge-ledger
  add_action('wp_enqueue_scripts', 'nxter_woo_scripts');

  function nxter_woo_scripts() {
    global $nxter_woo_version;
    wp_register_script('qrcode', plugins_url('/js/jquery.qrcode.min.js', __FILE__), array('jquery'), $nxter_woo_version, true);
    wp_enqueue_script('qrcode');
  }

  // send POST request with JSON data
  function sendJsonPOST($url, $data) {
    $cc = curl_init();

    $data = json_encode($data);

    $headers = array(
      "Content-Type: application/json"
    );


    curl_setopt($cc, CURLOPT_POST, true);
    curl_setopt($cc, CURLOPT_HTTPHEADER, $headers );
    curl_setopt($cc, CURLOPT_URL, $url);
    curl_setopt($cc, CURLOPT_POSTFIELDS, $data);
    curl_setopt($cc, CURLOPT_RETURNTRANSFER, true);

    try {
      $response = curl_exec($cc);
    } catch (Exception $e ) {
      return False;
    }

    curl_close($cc);

    return $response;
  }


  // Register custom order status 
 
?>
