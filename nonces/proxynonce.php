<?php
define("CPTX_WP_PROXYNONCE_VAR","wpcptx");
define("CPTX_WP_PROXYNONCE_ACTION","wp-cprotext-proxy");

function cptx_setInitialProxyNonceLifeSpan() {
  // first CPROTEXT API call must happen at most 2h after admin page is generated 
  return 7200;
}

function cptx_setProxyNonceLifeSpan(){
  // subsequent CPROTEXT API calls must happen at most 5min after the previous one
  // this limit the idle time between WP-CPROTEXT UI interactions to 5 minutes
  return 300;
}

function cptx_insertWPNonce($result){
  $result=json_decode($result,true);
  add_filter("nonce_life","cptx_setProxyNonceLifeSpan");
  $result[CPTX_WP_PROXYNONCE_VAR]=wp_create_nonce(CPTX_WP_PROXYNONCE_ACTION);
  remove_filter("nonce_life","cptx_setProxyNonceLifeSpan");
  $result=json_encode($result);
  return $result;
}


?>
