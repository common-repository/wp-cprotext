<?php
if ( ! defined( 'ABSPATH' ) ) exit;

require_once(__DIR__."/../nonces/assetsnonce.php");

function cptx_postFont(){
  global $wpdb;

  $action=filter_input(INPUT_GET,'action');
  if($action!=="cptxFont"){
    header("HTTP/1.1 403 Forbidden");
    exit;
  }

  $nonce=filter_input(INPUT_GET,CPTX_WP_ASSETSNONCE_VAR,FILTER_VALIDATE_REGEXP,
    array("options"=>array("regexp"=>"/^[0-9a-f]+$/")));

  add_filter("nonce_life", "cptx_setAssetsNonceLifeSpan");
  if(empty($nonce) ||
    !check_ajax_referer(CPTX_WP_FONTNONCE_ACTION,CPTX_WP_ASSETSNONCE_VAR,false)
  ){
    header("HTTP/1.1 403 Forbidden");
    exit;
  }
  remove_filter("nonce_life", "cptx_setAssetsNonceLifeSpan");

  cptx_checkInputs(array(
    "_GET"=>array(CPTX_WP_ASSETSNONCE_VAR,"id","t","f"),
    "_POST"=>array(),
    "_FILES"=>array(),
    "_COOKIE"=>null
  ));

  $f=filter_input(INPUT_GET,"f",FILTER_VALIDATE_REGEXP,
    array("options"=>array(
      "regexp"=>"/^(eot|ttf|woff)$/",
      "default"=>"eot"))); // since this is currently useless, default to eot

  $t=filter_input(INPUT_GET,"t",FILTER_VALIDATE_REGEXP,
    array("options"=>array("regexp"=>"/^(e|s)$/")));

  $id=filter_input(INPUT_GET,"id",FILTER_VALIDATE_INT);

  if(!$f || !$t || !$id ){
    header("HTTP/1.1 404 Not Found");
    exit();
  }

  $result=$wpdb->get_var($wpdb->prepare("select ".$f.$t." from ".$wpdb->prefix."cptx where id=%d",$id));
  header('Content-Type: application/vnd.ms-fontobject');
  header('Content-Disposition: attachment; filename=\'cptx'.$id.$t.'.'.$f.'\'');
  header('Pragma: public');
  header('Cache-Control: public, must-revalidate');
  header('Content-Transfer-Encoding: binary');

  echo $result;
  die();
}
?>
