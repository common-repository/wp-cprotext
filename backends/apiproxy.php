<?php
if ( ! defined( 'ABSPATH' ) ) exit;

define('CPTX_API_PROTOCOL','https');
define('CPTX_API_HOST','www.cprotext.com');
define('CPTX_API_VERSION','1.1');
define('CPTX_API_PATH','/api/'.CPTX_API_VERSION.'/');

require_once(__DIR__.'/../nonces/proxynonce.php');

function cptx_fileTransferError($file){
  if($file['error']===UPLOAD_ERR_OK){
    return false;
  }

  if(isset($file['tmp_name']) && @file_exists($file['tmp_name'])){
    @unlink($file['tmp_name']);
  }

  $error=__('WordPress hosting server issue',CPTX_I18N_DOMAIN).': ';

  switch($file['error']){
  case UPLOAD_ERR_INI_SIZE:
    $error.=sprintf(__('File size exceeds PHP configured limit (%s).',CPTX_I18N_DOMAIN),
      ini_get('upload_max_filesize'));
    break;
  case UPLOAD_ERR_PARTIAL:
    $error.=__('Upload interrupted.',CPTX_I18N_DOMAIN);
    break;
  case UPLOAD_ERR_NO_FILE:
    $error.=__('No file submitted.',CPTX_I18N_DOMAIN);
    break;
  case UPLOAD_ERR_NO_TMP_DIR:
    $error.=__('Temporary folder unreachable.',CPTX_I18N_DOMAIN);
    break;
  case UPLOAD_ERR_CANT_WRITE:
    $error.=__('Can\'t write file in temporary folder.',CPTX_I18N_DOMAIN);
    break;
  case UPLOAD_ERR_EXTENSION:
    $error.=__('A PHP extension stopped the file upload.',CPTX_I18N_DOMAIN);
    break;
  default:
    $error.=__('Unidentified issue.',CPTX_I18N_DOMAIN);
    break;
  }
  return $error;
}

// Modified from comment posted by user CertaiN here:
// http://php.net/manual/en/class.curlfile.php#115161
function cptx_customPostFields(array $assoc = array(), array $files = array()) {
  // invalid characters for 'name' and 'filename'
  static $disallow = array('\0', '\'', "\r", "\n");

  // build normal parameters
  foreach ($assoc as $k => $v) {
    $k = str_replace($disallow, '_', $k);
    $body[] = implode("\r\n", array(
      'Content-Disposition: form-data; name="'.$k.'"',
      '',
      filter_var($v),
    ));
  }

  // build file parameters
  foreach ($files as $k => $v) {
    switch (true) {
    case false === $v = realpath($v):
    case !is_file($v):
    case !is_readable($v):
      continue; // or return false, throw new InvalidArgumentException
    }
    $data = file_get_contents($v);
    $v = call_user_func('end', explode(DIRECTORY_SEPARATOR, $v));
    $k = str_replace($disallow, '_', $k);
    $v = str_replace($disallow, '_', $v);
    $body[] = implode("\r\n", array(
      'Content-Disposition: form-data; name="'.$k.'"; filename="'.$v.'"',
      'Content-Type: application/octet-stream',
      '',
      $data,
    ));
  }

  // generate safe boundary
  do {
    $boundary = '---------------------' . md5(mt_rand() . microtime());
  } while (preg_grep('/'.$boundary.'/', $body));

  // add boundary for each parameters
  array_walk($body, function (&$part) use ($boundary) {
    $part = '--'.$boundary."\r\n".$part;
  });

  // add final boundary
  $body[] = '--'.$boundary.'--';
  $body[] = '';

  return array(implode("\r\n", $body),$boundary);
}

function cptx_getUrlWP($url,$siteToken=null){
  $headers=array();
  if(!is_null($siteToken)){
    $headers['Authorization']='Bearer '.$siteToken;
  }

  if(isset($_FILES['cptx_newfont'])){
    $file=$_FILES['cptx_newfont'];

    $error=cptx_fileTransferError($file);
    if($error!==false){
      throw new Exception($error);
    }

    unset($_POST['cprotext']);

    list($content,$boundary)=
      cptx_customPostFields($_POST,array('fontFile'=>$file['tmp_name']));

    $headers['content-type']='multipart/form-data; boundary='.$boundary;
  }else{
    global $shortcode_tags;
    $postContent=filter_input(INPUT_POST,'c');
    if(!is_null($postContent) &&
      !empty($shortcode_tags) &&
      is_array($shortcode_tags) &&
      strpos($postContent,'[')!==false
    ){
      $cptxOff='<!--cprotextOff-->';
      $cptxOn='<!--cprotextOn-->';
      preg_match_all( '/' . get_shortcode_regex() . '/',
        $postContent, $matches, PREG_SET_ORDER  );

      foreach($matches as $shortcode){
        if(!in_array($shortcode[2],array_keys($shortcode_tags))){
          continue;
        }
        if(isset($shortcode[5]) && !empty($shortcode[5])){
          // enclosing shortcodes
          $shortcodeContent=str_replace(
            $shortcode[5],
            $cptxOn.$shortcode[5].$cptxOff,
            $shortcode[0]
          );
        }else{
          // self-closing shortcodes
          $shortcodeContent=$shortcode[0];
        }
        $postContent=str_replace(
          $shortcode[0],
          $cptxOff.$shortcodeContent.$cptxOn,
          $postContent);
      }
      $_POST['c']=$postContent;
    }

    $content=http_build_query($_POST);
    // http_build_query add slashes in front of various characters before
    // building the query. Those need to be removed
    $content=str_replace(array('%5C%5C','%5C','\\'),array('\\','','%5C'),$content);
  }

  $result=wp_remote_post($url,array('headers'=>$headers,'body'=>$content));

  //file_put_contents('/tmp/log',var_export($url,true).PHP_EOL,FILE_APPEND);
  //file_put_contents('/tmp/log',var_export($headers,true).PHP_EOL,FILE_APPEND);
  //file_put_contents('/tmp/log',var_export($content,true).PHP_EOL,FILE_APPEND);
  //file_put_contents('/tmp/log',var_export($result,true).PHP_EOL,FILE_APPEND);
  if(is_wp_error($result)){
    throw new Exception($result->get_error_message());
  }
  if($result['response']['code']!==200){
    throw new Exception($result['response']['message']);
  }
  return trim($result['body']);
}

function cptx_getUrl($url){
  $result=array('error'=>'No authorization token sent');

  if(isset($_SERVER['HTTP_AUTHORIZATION'])){
    list($siteTokenStr)=sscanf($_SERVER['HTTP_AUTHORIZATION'],'Bearer %s');
    if(!empty($siteTokenStr)){
      try {
        $result=cptx_getUrlWP($url,$siteTokenStr);
      }catch(Exception $e){
        $result=array('error'=>$e->getMessage());
      }
    }
  }

  return $result;
}

function cptx_buildProxyOutput($callback,$result){
  $output='';

  if(!empty($callback)){
    $output.=$callback.'(';
  }

  $output.=$result;

  if(!empty($callback)){
    $output.=')';
  }

  return $output;
}

function cptx_APIProxy(){

  $action=filter_input(INPUT_GET,'action');
  if($action!=='cptxProxy'){
    header('HTTP/1.1 403 Forbidden');
    exit;
  }

  $nonce=filter_input(INPUT_GET,CPTX_WP_PROXYNONCE_VAR,FILTER_VALIDATE_REGEXP,
    array('options'=>array('regexp'=>'/^[0-9a-f]+$/')));

  $function=filter_input(INPUT_GET,'f');
  $next=filter_input(INPUT_GET,'n');

  if($function==='token'){
    $nonceFilter='cptx_setInitialProxyNonceLifeSpan';
  }else{
    $nonceFilter='cptx_setProxyNonceLifeSpan';
  }

  add_filter('nonce_life',$nonceFilter);
  if((empty($nonce) ||
    !check_ajax_referer(CPTX_WP_PROXYNONCE_ACTION,CPTX_WP_PROXYNONCE_VAR,false))
  ){
    header('HTTP/1.1 403 Forbidden');
    exit;
  }
  remove_filter('nonce_life',$nonceFilter);

  if(!current_user_can('edit_posts')){
    header('HTTP/1.1 403 Forbidden');
    exit;
  }

  cptx_checkInputs(array(
    '_GET'=>array('callback',CPTX_WP_PROXYNONCE_VAR,'_','f','n','e','p','t','tid','ft','ie'),
    '_POST'=>array('c','ti','plh','cprotext'),
    '_FILES'=>array('cptx_newfont'),
    '_COOKIE'=>null
  ));

  $callback=filter_input(INPUT_GET,'callback');
  $jsonStart=strlen($callback.'(');

  $queryString=str_replace(CPTX_WP_PROXYNONCE_VAR.'='.$nonce,'',$_SERVER['QUERY_STRING']);
  $queryString=str_replace('action=cptxProxy','',$queryString);
  $queryString=str_replace('&&','&',$queryString);

  // Detect IE version: this is plain wrong and ugly, but unavoidable
  // in order to support font upload for IE 9
  // source: https://github.com/malsup/form/issues/302
  preg_match('/MSIE (.*?);/', $_SERVER['HTTP_USER_AGENT'], $matches);
  $ieVersion=0;
  if(count($matches)>1){
    $ieVersion=$matches[1];
  }

  if($ieVersion && $ieVersion<=9){
    header('Content-Type: text/plain; charset=utf-8');
  }else{
    header('Content-Type: text/javascript; charset=utf-8');
  }

  $url = CPTX_API_PROTOCOL.'://'.CPTX_API_HOST.CPTX_API_PATH.'?'.$queryString;

  $result=cptx_getUrl($url);
  if(is_array($result) && isset($result['error'])){
    $result=json_encode($result);
  }else{
    if(!empty($callback)){
      $result=substr($result,$jsonStart,-1);
    }
    if(!empty($next)){
      $result=cptx_insertWPNonce($result);
    }
  }
  echo cptx_buildProxyOutput($callback,$result);
  die();
}
?>
