<?php




$cc = curl_init();

$url = "https://random.nxter.org/api/v2/woo/send/aeur";

$data = array(
  "senderRS"      => "ARDOR-ZZZZ-48G3-9F9W-4CLJZ",
  "recipientRS"   => "ARDOR-FRNZ-PDJF-2CQT-DQ4WQ",
  "message"       => "603ef4a9e7048a97a3cd",
  "amount"        => "30"
);

$data = json_encode($data);


$headers = array(
  "Content-Type: application/json"
);

curl_setopt($cc, CURLOPT_POST, true);
curl_setopt($cc, CURLOPT_HTTPHEADER, $headers );
curl_setopt($cc, CURLOPT_URL, $url);
curl_setopt($cc, CURLOPT_POSTFIELDS, $data);
curl_setopt($cc, CURLOPT_RETURNTRANSFER, true);


$response = curl_exec($cc);

var_dump($response);

?>
