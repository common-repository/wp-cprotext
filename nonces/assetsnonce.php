<?php
define("CPTX_WP_ASSETSNONCE_VAR","wpcptx");
define("CPTX_WP_CSSNONCE_ACTION","wp-cprotext-css");
define("CPTX_WP_FONTNONCE_ACTION","wp-cprotext-font");

function cptx_setAssetsNonceLifeSpan(){
  // Call to assets URL must happen at most 60s after the source page is generated
  return 60;
}

?>
