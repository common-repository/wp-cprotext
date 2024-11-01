<?php
if ( ! defined( 'ABSPATH' ) ) exit;

require_once(__DIR__."/../nonces/assetsnonce.php");

function cptx_postCSS(){
  global $wpdb;

  $action=filter_input(INPUT_GET,'action');
  if($action!=="cptxCSS"){
    header("HTTP/1.1 403 Forbidden");
    exit;
  }

  $nonce=filter_input(INPUT_GET,CPTX_WP_ASSETSNONCE_VAR,FILTER_VALIDATE_REGEXP,
    array("options"=>array("regexp"=>"/^[0-9a-f]+$/")));

  add_filter("nonce_life", "cptx_setAssetsNonceLifeSpan");
  if(empty($nonce) ||
    !check_ajax_referer(CPTX_WP_CSSNONCE_ACTION,CPTX_WP_ASSETSNONCE_VAR,false)
  ){
    header("HTTP/1.1 403 Forbidden");
    exit;
  }
  remove_filter("nonce_life", "cptx_setAssetsNonceLifeSpan");

  cptx_checkInputs(array(
    "_GET"=>array(CPTX_WP_ASSETSNONCE_VAR,"id","o",'s'),
    "_POST"=>array(),
    "_FILES"=>array(),
    "_COOKIE"=>null
  ));

  $id=filter_input(INPUT_GET,"id",FILTER_VALIDATE_INT);
  $order=filter_input(INPUT_GET,'o',FILTER_VALIDATE_INT);
  $size=filter_input(INPUT_GET,'s');
  $referer=filter_var($_SERVER['HTTP_REFERER'],FILTER_VALIDATE_URL);

  if($referer){
    $referer=parse_url($referer,PHP_URL_HOST);
  }

  if(is_null($id) || $id===false ||
    !((is_null($order) || $order===false) xor
    (is_null($size) || $size===false)) ||
    is_null($referer) || $referer===false){
    header("HTTP/1.1 404 Not Found");
    exit();
  }

  if(is_null($size) || $size===false){
    $sql=$wpdb->prepare(
      "SELECT css FROM ".
      $wpdb->prefix."cptx where id=%d",$id);
    $css=$wpdb->get_var($sql);
    $values=array_values(get_object_vars($wpdb->last_result[0]));
    $css=stripslashes($css);
    add_filter("nonce_life", "cptx_setAssetsNonceLifeSpan");
    $url=html_entity_decode(wp_nonce_url(
      admin_url("admin-ajax.php")."?action=cptxFont",
      CPTX_WP_FONTNONCE_ACTION,CPTX_WP_ASSETSNONCE_VAR));
    remove_filter("nonce_life", "cptx_setAssetsNonceLifeSpan");

    $css=str_replace("wpcxX-e.eot?",$url.'&id='.$id.'&t=e#',$css);
    $css=str_replace("wpcxX-s.eot?",$url.'&id='.$id.'&t=s#',$css);
    $css=str_replace("wpcxX","wpcx$order",$css);
  }else{
    $metadata=wp_get_attachment_metadata($id);

    $widths=array();
    $uploads=wp_upload_dir(null,false);
    if($size==='full'){
      $width=$metadata['width'];
      $ratio=round($metadata['width']/$metadata['height'],2);
    }else{
      $width=$metadata['sizes'][$size]['width'];
      $ratio=round($metadata['sizes'][$size]['width']/
        $metadata['sizes'][$size]['height'],2);
    }
    $dir=dirname($metadata['file']);
    foreach($metadata['sizes'] as $label=>$data){
      if($data['width']>$width ||
        round($data['width']/$data['height'],2)!==$ratio
      ){
        continue;
      }
      $path=$dir.'/'.$data['file'];
      $widths[$path]=$data['width'];
    }
    if(!in_array($width,$widths)){
      $img=$metadata['file'];
      $widths[$img]=$metadata['width'];
    }
    arsort($widths);

    $clause=implode(',',
      array_map(function($v){return '"'.$v.'"';},array_keys($widths))
    );
    $sql='SELECT child.srcpath as srcpath, child.css as css, parent.css as pcss '
      .'FROM '.$wpdb->prefix.'cptimg as child '
      .'LEFT OUTER JOIN '.$wpdb->prefix.'cptimg as parent '
      .'ON parent.id=child.parentId '
      .'WHERE child.srcpath IN ('.$clause.')';
    $sources=$wpdb->get_results($sql,ARRAY_A);
    $nsources=array();
    foreach($widths as $path=>$lwidth){
      foreach($sources as $source){
        if($source['srcpath']===$path){
          $nsources[]=$source;
          break;
        };
      };
    }
    $sources=$nsources;

    $css='';
    foreach($sources as $source){
      if(!isset($widths[$source['srcpath']])){
        continue;
      }
      if(empty($source['css'])){
        $source['css']=$source['pcss'];
      }
      $sourceCss=stripslashes($source['css']);
      if($widths[$source['srcpath']]!==$width){
        reset($widths);
        while(key($widths)!==$source['srcpath']){
          next($widths);
        }
        $sourceCss='@media (max-width: '
          .(($widths[$source['srcpath']]+prev($widths))>>1).'px){'
          .$sourceCss
          .'}';
      }
      $css.=$sourceCss;
    }
    $css=str_replace('cpx_#_','cpx'.hash('crc32b',$id.$size),$css);
  }
  header('Content-Type: text/css');
  echo $css;
  die();
}
?>
