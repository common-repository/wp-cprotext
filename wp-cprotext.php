<?php
/*
 * Plugin Name: wp-cprotext
 * Plugin URI: https://www.cprotext.com/en/tools.html
 * Description: Integration of the CPROTEXT.COM online service for copy protection of contents, including texts and images, published on the web
 * Text Domain: wp-cprotext
 * Domain path: /lang/
 * Version: 2.0.0
 * Author: ASKADICE
 * Author: https://www.cprotext.com
 * License: GPLv2.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define('CPTX_PLUGIN_VERSION','2.0.0');

define('CPTX_DB_VERSION','2');

define('CPTX_I18N_DOMAIN','wp-cprotext');



define('CPTX_DEBUG',false);

define('CPTX_DEBUG_QUERIES',false);

define('CPTX_STATUS_NOT_PUBLISHED',0);
define('CPTX_STATUS_PUBLISHED',1);
define('CPTX_STATUS_CHANGED',2);
define('CPTX_STATUS_NOT_CHANGED',0);
define('CPTX_STATUS_CPROTEXTED',4);
define('CPTX_STATUS_NOT_CPROTEXTED',0);
define('CPTX_STATUS_UPDATE_TITLE',8);
define('CPTX_STATUS_UPDATE_CONTENT',16);
define('CPTX_STATUS_WPUPDATES',
  CPTX_STATUS_UPDATE_TITLE+
  CPTX_STATUS_UPDATE_CONTENT);
define('CPTX_STATUS_UPDATE_FONT',32);
define('CPTX_STATUS_UPDATE_PLH',64);
define('CPTX_STATUS_UPDATE_KW',128);
define('CPTX_STATUS_UPDATES',
  CPTX_STATUS_WPUPDATES+
  CPTX_STATUS_UPDATE_FONT+
  CPTX_STATUS_UPDATE_PLH+
  CPTX_STATUS_UPDATE_KW);
define('CPTX_STATUS_PROCESSING',256);

require_once(__DIR__.'/settings.php');
require_once(__DIR__.'/msg.php');
require_once(__DIR__.'/nonces/proxynonce.php');
require_once(__DIR__.'/nonces/assetsnonce.php');

require_once(__DIR__.'/backends/apiproxy.php');
require_once(__DIR__.'/backends/css.php');
require_once(__DIR__.'/backends/font.php');

/**
 * cptx_updateCheck
 *
 * When required, create or update the plugin DB schema
 * and initialize plugin option:
 *
 *      cprotext[authtok] : authentication token
 *
 *      cprotext[hsync] : wordpress settings have been synchronized
 *                        with account settings
 *                      values: 0 => false
 *                              1 => true
 *
 *      cprotext[hfontlist]: list of available fonts in user CPROTEXT account
 *
 *      cprotext[font]: default font to use with protexted text
 *
 *      cprotext[notice]: set protection notification
 *                   values: 0 => false
 *                           1 =>true
  *
 * @access public
 * @return void
 */
function cptx_updateCheck(){
  global $wpdb;

  $settings=get_option('cprotext');
  if(!$settings){
    // transition from before 2.0.0
    $settings=array();
    $settings['version']=get_option('cptx_version');
    $settings['dbversion']=get_option('cptx_db_version');
    $settings['popup']=get_option('cptx_popup');
  }

  if($settings['version'] === CPTX_PLUGIN_VERSION){
    // no action required
    return;
  }

  if ($settings['dbversion'] != CPTX_DB_VERSION){
    $cptxTableName=$wpdb->prefix.'cptx';
    $cptimgTableName=$wpdb->prefix.'cptimg';
    for($v=$settings['dbversion']+1; $v<=CPTX_DB_VERSION; $v++){
      $sql=trim(file_get_contents(__DIR__.'/sql/cprotextdb_'.$v.'.sql'));
      $sql=str_replace('__CPTXTABLENAME__',$cptxTableName,$sql);
      $sql=str_replace('__CPTIMGTABLENAME__',$cptimgTableName,$sql);
      if(strrpos($sql,';')===(strlen($sql)-1)){
        $sql=substr($sql,0,-1);
      }
      $queries=explode(';',$sql);
      foreach($queries as $q=>$query){
        $result=$wpdb->query($query);
        if(!$result){
          break 2;
        }
      }
    }
    if($v != (CPTX_DB_VERSION+1)){
      if(!$q){
        $v--;
      }
      for(;$v>$settings['dbversion'];$v--){
        $sql=trim(file_get_contents(__DIR__.'/sql/cprotextdb_'.$v.'.rollback.sql'));
        $sql=str_replace('__CPTXTABLENAME__',$cptxTableName,$sql);
        $sql=str_replace('__CPTIMGTABLENAME__',$cptimgTableName,$sql);
        if(strrpos($sql,';')===(strlen($sql)-1)){
          $sql=substr($sql,0,-1);
        }
        $rollbacks=explode(';',$sql);
        for($r=count($rollbacks)-$q-1;$r>=0; $r--){
          $result=$wpdb->query($rollbacks[$r]);
          if(!$result){
            break 2;
          }
        }
      }
      if($v == $settings['dbversion']){
        // successful rollback
        $settings['popup']=3;
      }else{
        // failed rollback
        $settings['popup']=4;
      }
      return false;
    }
    $settings['dbversion'] = CPTX_DB_VERSION;
  }

  cptx_initSettings($settings);

  return true;
}

/**
 * cptx_getTemplate
 *
 * @param string $tpl template name
 * @access public
 * @return string the content of the template file or null if it does not
 *                exist
 */
function cptx_getTemplate($tpl){
  global $locale;

  $tplPath=__DIR__.'/tpl/'.$tpl.'.'.(empty($locale)?'en_US':$locale).'.html';
  if(!file_exists($tplPath)){
    $tplPath=__DIR__.'/tpl/'.$tpl.'.en_US.html';
    if(!file_exists($tplPath)){
      return null;
    }
  }
  return file_get_contents($tplPath);
}

/**
 * cptx_popup
 *
 * Display a popup with information about updates
 *
 * @access public
 * @return void
 */
function cptx_popup() {
  $settings=get_option('cprotext');

  switch($settings['popup']){
  case 1:
    $content=cptx_getTemplate('firstinstall');
    break;
  case 2:
    $content=cptx_getTemplate('release_'.CPTX_PLUGIN_VERSION);
    break;
  case 3:
    $content=cptx_getTemplate('rollback');
    break;
  case 4:
    $content=cptx_getTemplate('fatal');
    break;
  default:
    return;
  };

  echo '<div id="cptxPopUp" style="display:none">';
  echo $content;
  echo '</div>';
  $settings['popup']=0;
  update_option('cprotext',$settings);
}

/**
 * cptx_post
 *
 * Add CPROTEXT meta box to post and page editor
 *
 * @access public
 * @return void
 */
function cptx_post(){
  add_meta_box('cptx_box','CPROTEXT','cptx_metabox','post');
  add_meta_box('cptx_box','CPROTEXT','cptx_metabox','page');
}

/**
 * cptx_adminInserts
 *
 * Add CSS and javascript to relevant admin pages
 *
 * @param string $page
 * @access public
 * @return void
 */
function cptx_adminInserts($page){
  $settings=get_option('cprotext');

  if($settings['popup']){
    wp_enqueue_style('wp-jquery-ui-dialog');

    wp_register_style('wp-cprotext-admin',plugins_url('wp-cprotext/css/cptx'.(CPTX_DEBUG?'':'.min').'.css'));
    wp_enqueue_style('wp-cprotext-admin');
    wp_add_inline_style('wp-cprotext-admin',
      '.cptxDialog .ui-dialog-title:before{ content: url("'.
      plugins_url('wp-cprotext/images/icon16.png').
      '")}'
    );

    wp_register_style('wp-cprotext-popup',plugins_url('wp-cprotext/css/cptxpopup'.(CPTX_DEBUG?'':'.min').'.css'));
    wp_enqueue_style('wp-cprotext-popup');

    wp_enqueue_script('ajax-script',plugins_url('wp-cprotext/js/popup'.(CPTX_DEBUG?'':'.min').'.js'),array('jquery','jquery-ui-dialog'));

    wp_localize_script('ajax-script','cprotext',array(
      'L10N'=>array(
        'closeButton'=>__('Close',CPTX_I18N_DOMAIN)
      )));
    return;
  }

  if('settings_page_cprotext'!=$page &&
    'post-new.php'!=$page &&
    'post.php'!=$page
  ){
    return;
  }

  global $wp_version;

  wp_enqueue_style('wp-jquery-ui-dialog');

  wp_register_style('wp-cprotext-admin',plugins_url('wp-cprotext/css/cptx'.(CPTX_DEBUG?'':'.min').'.css'));
  wp_enqueue_style('wp-cprotext-admin');
  wp_add_inline_style('wp-cprotext-admin',
    '.cptxDialog .ui-dialog-title:before{ content: url("'.
    plugins_url('wp-cprotext/images/icon16.png').
    '")}'.
    '.cptxDialog .dary{background-image: url("'.
    plugins_url('wp-cprotext/images/waitforit.gif').
    '")}'.
    '.cptxDialog .wait:before{ content: url("'.
    plugins_url('wp-cprotext/images/wait.gif').
    '")}'
  );

  wp_enqueue_script('ajax-script',
    plugins_url('wp-cprotext/js/cptx'.(CPTX_DEBUG?'':'.min').'.js'),
    array('jquery','jquery-ui-dialog','jquery-form'));

  add_filter('nonce_life', 'cptx_setInitialProxyNonceLifeSpan');
  $initialNonce=wp_create_nonce(CPTX_WP_PROXYNONCE_ACTION);
  remove_filter('nonce_life', 'cptx_setInitialProxyNonceLifeSpan');

  wp_localize_script('ajax-script','cprotext',array(
    'API'=>array(
      'URL'=> admin_url('admin-ajax.php').'?action=cptxProxy&',
      'TOKEN'=>$settings['authtok'],
      'NONCE_VAR'=> CPTX_WP_PROXYNONCE_VAR,
      'INITIAL_NONCE'=> $initialNonce,
    ),
    'STATUS'=>array(
      'NOT_PUBLISHED'=>CPTX_STATUS_NOT_PUBLISHED,
      'PUBLISHED'=>CPTX_STATUS_PUBLISHED,
      'CHANGED'=>CPTX_STATUS_CHANGED,
      'NOT_CHANGED'=>CPTX_STATUS_NOT_CHANGED,
      'CPROTEXTED'=>CPTX_STATUS_CPROTEXTED,
      'NOT_CPROTEXTED'=>CPTX_STATUS_NOT_CPROTEXTED,
      'UPDATE_TITLE'=>CPTX_STATUS_UPDATE_TITLE,
      'UPDATE_CONTENT'=>CPTX_STATUS_UPDATE_CONTENT,
      'WPUPDATES'=>CPTX_STATUS_WPUPDATES,
      'UPDATE_FONT'=>CPTX_STATUS_UPDATE_FONT,
      'UPDATE_PLH'=>CPTX_STATUS_UPDATE_PLH,
      'UPDATE_KW'=>CPTX_STATUS_UPDATE_KW,
      'UPDATES'=>CPTX_STATUS_UPDATES,
      'PROCESSING'=>CPTX_STATUS_PROCESSING
    ),
    'L10N'=>array(
      'bugReportRequired'=>__('This case should not happen ! Please, disable CPROTEXT for this post and report this bug to wp-dev@cprotext.com .',CPTX_I18N_DOMAIN),
      'resume'=>__('CPROTEXT protection process has been initiated for this text and needs to be resumed.',CPTX_I18N_DOMAIN),
      'commitUpdateContent'=>__('Content',CPTX_I18N_DOMAIN),
      'commitUpdateTitle'=>__('Title',CPTX_I18N_DOMAIN),
      'commitUpdateFont'=>__('Font',CPTX_I18N_DOMAIN),
      'commitUpdatePlh'=>__('Placeholder',CPTX_I18N_DOMAIN),
      'commitUpdateKw'=>__('Keywords',CPTX_I18N_DOMAIN),
      'unpublished'=>__('This text is going to be unpublished.',CPTX_I18N_DOMAIN),
      'attachedToRevision'=>__('The CPROTEXT data associated with this revision will remain available in case it has to be published again.',CPTX_I18N_DOMAIN),
      'detachedFromRevision'=>__('Modifications were made to the protected revision. This new revision will not be protected, and will require a new submission to CPROTEXT service if it happens to be published.',CPTX_I18N_DOMAIN),
      'uselessProtection'=> __('This text is not published: protection seems useless.',CPTX_I18N_DOMAIN),
      'closeButton'=>__('Close',CPTX_I18N_DOMAIN),
      'authentication'=>__('Attempting authentication',CPTX_I18N_DOMAIN),
      'authenticationRequired'=> __('You need to connect to your CPROTEXT account to commit the following modifications:',CPTX_I18N_DOMAIN),
      'importAccountSettings'=>__('Importing account settings ...'),
      'login'=>__('Fill in your CPROTEXT credentials',CPTX_I18N_DOMAIN),
      'email'=>__('Email',CPTX_I18N_DOMAIN),
      'password'=>__('Password',CPTX_I18N_DOMAIN),
      'identifyButton'=>__('Identify',CPTX_I18N_DOMAIN),
      'identifyFail'=>__('Identification failed',CPTX_I18N_DOMAIN),
      'fontlistFail'=>__('Can\'t retrieve font list.',CPTX_I18N_DOMAIN),
      'fontfile1'=>__('Font',CPTX_I18N_DOMAIN),
      'fontfile2'=>__('successfully uploaded.',CPTX_I18N_DOMAIN),
      'creditsFail'=>__('Can\'t get current credits.',CPTX_I18N_DOMAIN),
      'credits1'=>__('Your account has currently ',CPTX_I18N_DOMAIN),
      'credits2'=>__('credits.',CPTX_I18N_DOMAIN),
      'credits3'=>__('You are about to use %1 credit%2 for this text.',CPTX_I18N_DOMAIN),
      'credits4'=>__('Do you confirm willing to use CPROTEXT service to protect this text ?',CPTX_I18N_DOMAIN),
      'returnButton'=>__('I\'ll come back later',CPTX_I18N_DOMAIN),
      'noButton'=>__('No',CPTX_I18N_DOMAIN),
      'yesButton'=>__('Yes',CPTX_I18N_DOMAIN),
      'cancellation'=>__('Operation cancelled',CPTX_I18N_DOMAIN),
      'submitFail'=>__('Error while submitting',CPTX_I18N_DOMAIN),
      'updateFail'=>__('Error while updating',CPTX_I18N_DOMAIN),
      'submitButton'=>__('Resume WordPress publishing process',CPTX_I18N_DOMAIN),
      'statusFail'=>__('Error while getting status',CPTX_I18N_DOMAIN),
      'statusUnknown'=>__('unknown status',CPTX_I18N_DOMAIN),
      'failed'=>__('Failed',CPTX_I18N_DOMAIN),
      'statusError0'=>__('Unknown reason',CPTX_I18N_DOMAIN),
      'statusError1'=>__('Database connection failed',CPTX_I18N_DOMAIN),
      'statusError2'=>__('Internal error: issue has been reported upstream',CPTX_I18N_DOMAIN),
      'statusError3'=>__('font file storage failed',CPTX_I18N_DOMAIN),
      'statusError41'=>__('EOT font generation failed with error code',CPTX_I18N_DOMAIN),
      'statusError42'=>__('Font generation failed with error code',CPTX_I18N_DOMAIN),
      'statusError51'=>__('Can\'t generate EOT fonts',CPTX_I18N_DOMAIN),
      'statusError52'=>__('Can\'t generate fonts',CPTX_I18N_DOMAIN),
      'statusError6'=>__('Database access failed',CPTX_I18N_DOMAIN),
      'statusError7'=>__('Font not found',CPTX_I18N_DOMAIN),
      'processing'=>__('Processing',CPTX_I18N_DOMAIN),
      'step'=>__('step',CPTX_I18N_DOMAIN),
      'statusProcess1'=>__('destructuration',CPTX_I18N_DOMAIN),
      'statusProcess2'=>__('disruption',CPTX_I18N_DOMAIN),
      'statusProcess3'=>__('compilation',CPTX_I18N_DOMAIN),
      'statusWaiting'=>__('Waiting to be queued',CPTX_I18N_DOMAIN),
      'statusQueueing'=>__('Queueing',CPTX_I18N_DOMAIN),
      'statusRemaining'=>__('texts to protect before yours.',CPTX_I18N_DOMAIN),
      'statusDone'=>__('Operation succeeded',CPTX_I18N_DOMAIN),
      'pwyw'=>__('This text is entitled to our Pay-What-You-Want policy. Check your account \'Texts\' panel, and set the price you consider appropriate for this service. Once done, come back here.',CPTX_I18N_DOMAIN),
      'getInit'=>__('Downloading your protected text',CPTX_I18N_DOMAIN),
      'getSuccess1'=>__('Your text has been successfully protected.',CPTX_I18N_DOMAIN),
      'getSuccess2'=>__('It is now ready to be published.',CPTX_I18N_DOMAIN),
      'getFail'=>__('Error while getting CPROTEXT data',CPTX_I18N_DOMAIN),
      'cancelButton'=>__('Cancel',CPTX_I18N_DOMAIN),
      'okButton'=>__('Ok',CPTX_I18N_DOMAIN)
    )));
}

/**
 * cptx_metabox
 *
 * Display the CPROTEXT meta box
 *
 * @param WP_Post $post
 * @access public
 * @return void
 */
function cptx_metabox($post){
  global $wpdb;

  $settings=get_option('cprotext');

  if($settings['dbversion']!=CPTX_DB_VERSION ||
    empty($settings['hfontlist'])
  ){
    echo '<div style="color:red">';
    if($settings['dbversion']!=CPTX_DB_VERSION){
      echo __('Disabled: database version mismatch',CPTX_I18N_DOMAIN);
    }elseif(empty($settings['hfontlist'])){
      echo __('This plugin requires that you first synchronize your'
        .' CPROTEXT.COM account settings with the '
        .'<a href="./options-general.php?page=cprotext">WordPress CPROTEXT Settings</a>'
        ,CPTX_I18N_DOMAIN);
    }
    echo '</div>';
    return;
  }

  $cptx_fonts=json_decode(stripslashes($settings['hfontlist']),true);
  $cptx_font=$settings['font'];

  $version=0;
  $textId='';
  $cptx_fontsel=$cptx_font;
  $plh='';
  $kw='';
  $statusChange='';

  if($revisions=wp_get_post_revisions($post->ID)){
    // grab the last revision, but not an autosave
    foreach($revisions as $revision){
      if (false!==strpos($revision->post_name,$revision->post_parent.'-revision')){
        $last_revision=$revision;
        break;
      }
    }
    $cptxed=$wpdb->get_var('select enabled,font,textId,version,plh from '.$wpdb->prefix.'cptx '.
      'where id='.$last_revision->ID);
    if(!is_null($cptxed)){
      $values=array_values(get_object_vars($wpdb->last_result[0]));
      $cptx_fontsel=$values[1];
      $textId=$values[2];
      $version=$values[3];
      $plh=$values[4];
      unset($values);
      $kw=get_post_meta($last_revision->ID,'cptx_kw',true);
    }

    $meta=get_post_meta($last_revision->ID,'cptx_waiting',true);
    if(!empty($meta)){
      $cptxed=true;
      $textId=$meta[0];
      $cptx_fontsel=$meta[1];
      $plh=$meta[2];
      $kw=$meta[3];
      $version=0;
      $statusChange=$meta[4];
    }

  }else{
    $cptxed=null;
  }

  $disabled=(!$cptxed?' disabled="disabled"':'');

  $checked=($cptxed?' checked="checked"':'');
  echo '<label>';
  echo '<input id="cptx_check" name="cptx_check" type="checkbox"';
  echo $checked.'/>&nbsp;';
  echo __('Enable CPROTEXT');
  echo '</label>';

  echo '<p><label>';
  echo __('Text Font',CPTX_I18N_DOMAIN).':';
  echo '<select id="cptx_font" name="cptx_font"'.$disabled.'>';
  foreach($cptx_fonts as $font){
    $selected=($font[0]===$cptx_font?' selected="selected"':'');
    echo '<option value="'.$font[0].'"';
    echo $selected.$disabled;
    echo '>'.$font[1].'</option>';
  }
  if(empty($cptx_fonts)){
    echo '<option title=".">';
    echo __('no font available',CPTX_I18N_DOMAIN);
    echo '</option>';
  }
  echo '</select></label></p>';

  echo '<div>';
  echo __(sprintf('In order for search engines to reference your %s,'
    .'you can add relevant information that won&#39;t be visible in the rendered page,'
    .'but available to any one looking at the HTML code',$post->post_type)
    ,CPTX_I18N_DOMAIN);
  echo '<br/>';

  echo '<label>'.__('Placeholder',CPTX_I18N_DOMAIN).':<br/>';
  echo '<textarea style="width: 100%" id="cptx_plh" name="cptx_plh"'.$disabled.'>';
  echo (is_null($cptxed)?'':$plh);
  echo '</textarea>';
  echo '</label>';

  echo '<label>'.__('Keywords (comma separated)',CPTX_I18N_DOMAIN).':<br/>';
  echo '<textarea style="width: 100%" id="cptx_kw" name="cptx_kw"'.$disabled.'>';
  echo (is_null($cptxed)?'':$kw);
  echo '</textarea>';
  echo '</label>';
  echo '</div>';

  echo '<input type="hidden" id="cptxed" name="cptxed"'.$disabled
    .' value="'.(string)(!is_null($cptxed)).'"/>';
  echo '<input type="hidden" id="cptx_contentVer" name="cptx_contentVer"'.$disabled.'/>';
  echo '<input type="hidden" id="cptx_contentCSS" name="cptx_contentCSS"'.$disabled.'/>';
  echo '<input type="hidden" id="cptx_contentHTML" name="cptx_contentHTML"'.$disabled.'/>';
  echo '<input type="hidden" id="cptx_contentEOTE" name="cptx_contentEOTE"'.$disabled.'/>';
  echo '<input type="hidden" id="cptx_contentEOTS" name="cptx_contentEOTS"'.$disabled.'/>';
  echo '<input type="hidden" id="cptx_contentId" name="cptx_contentId"'.$disabled
    .' value="'.($cptxed?(!$version?'W':'').$textId:(is_null($cptxed)?'':$textid)).'"/>';
  echo '<input type="hidden" id="cptx_statusChange" name="cptx_statusChange"'.$disabled
    .' value="'.($cptxed?$statusChange:'').'"/>';
}

/**
 * cptx_getInputData
 *
 * Grab, test and validate data from $_POST
 *
 * @param bool $check check if data are valid
 * @access public
 * @return array
 */
function cptx_getInputData($check=true){
  $posted_contentId=filter_input(INPUT_POST,'cptx_contentId',
    FILTER_VALIDATE_REGEXP,array('options'=>array('regexp'=>'/^[0-9a-f]+$/')));
  if($check && !$posted_contentId){
    cptx_debug('getInputData:  wrong contentId ('.
      ($posted_contentId===false?'invalid':'missing').')');
    return false;
  }

  $posted_contentVer=filter_input(INPUT_POST,'cptx_contentVer',
    FILTER_VALIDATE_INT);
  if($check && !$posted_contentVer){
    cptx_debug('getInputData:  wrong contentVer ('.
      ($posted_contentVer===false?'invalid':'missing').')',__LINE__);
    return false;
  }

  $posted_font=filter_input(INPUT_POST,'cptx_font',
    FILTER_VALIDATE_REGEXP,array('options'=>array('regexp'=>'/^[0-9a-f]+$/')));
  if($check && !$posted_font){
    cptx_debug('getInputData:  wrong fontsel ('.
      ($posted_font===false?'invalid':'missing').')',__LINE__);
    return false;
  }

  $posted_contentCSS=filter_input(INPUT_POST,'cptx_contentCSS');
  if($check && !$posted_contentCSS){
    cptx_debug('getInputData:  wrong contentCSS ('.
      ($posted_contentCSS===false?'invalid':'missing').')',__LINE__);
    return false;
  }

  $posted_plh=filter_input(INPUT_POST,'cptx_plh');
  if($posted_plh===false){
    cptx_debug('getInputData:  invalid plh');
    return false;
  }

  $posted_contentHTML=filter_input(INPUT_POST,'cptx_contentHTML');
  if($check && !$posted_contentHTML){
    cptx_debug('getInputData:  wrong contentHTML ('.
      ($posted_contentHTML===false?'invalid':'missing').')',__LINE__);
    return false;
  }

  $posted_contentEOTE=filter_input(INPUT_POST,'cptx_contentEOTE');
  if($check && !$posted_contentEOTE){
    if($posted_contentEOTE===false){
      cptx_debug('getInputData:  wrong contentEOTE (invalid)',__LINE__);
      return false;
    }
    cptx_debug('getInputData:  wrong contentEOTE (missing)',__LINE__);
  }
  if(!empty($posted_contentEOTE)){
    $contentEOTE=base64_decode($posted_contentEOTE,true);
    if($check && $contentEOTE===false){
      cptx_debug('getInputData:  wrong contentEOTE (corrupted)');
      return false;
    }
  }else{
    $contentEOTE='NULL';
  }

  $posted_contentEOTS=filter_input(INPUT_POST,'cptx_contentEOTS');
  if($check && !$posted_contentEOTS){
    if($posted_contentEOTS===false){
      cptx_debug('getInputData:  wrong contentEOTS (invalid)',__LINE__);
      return false;
    }
    cptx_debug('getInputData:  wrong contentEOTS (missing)',__LINE__);
  }
  if(!empty($posted_contentEOTS)){
    $contentEOTS=base64_decode($posted_contentEOTS,true);
    if($check && $contentEOTS===false){
      cptx_debug('getInputData:  wrong contentEOTS (corrupted)');
      return false;
    }
  }else{
    $contentEOTS='NULL';
  }


  return array(
    $posted_contentId,
    $posted_contentVer,
    $posted_font,
    $posted_plh,
    $posted_contentCSS,
    $posted_contentHTML,
    $contentEOTE,
    $contentEOTS);

}

/**
 * cptx_db_null_value
 *
 * Replace the quoted string 'NULL' by the non quoted string NULL
 * This allows wordpress to set a field to NULL in a SQL statement;
 *
 * @param string $query
 * @access public
 * @return string
 */
function cptx_db_null_value($query){
  return str_replace('\'NULL\'','NULL',$query);
}

/**
 * cptx_upsertdb
 *
 * Update or insert a DB wp_cptx row
 *
 * @param int $postId
 * @param int $parentId
 * @param bool $insert
 * @access public
 * @return bool
 */
function cptx_upsertdb($postId,$parentId,$insert=false){
  global $wpdb;

  $inputs=cptx_getInputData();
  if(!$inputs){
    cptx_error(__('Invalid input data'));
    return false;
  }

  list(
    $posted_contentId,
    $posted_contentVer,
    $posted_font,
    $posted_plh,
    $posted_contentCSS,
    $posted_contentHTML,
    $contentEOTE,
    $contentEOTS)=$inputs;

  $inserted=$updated=true;
  add_filter('query','cptx_db_null_value');
  if($insert){
    $inserted=$wpdb->insert($wpdb->prefix.'cptx',
    array(
      'id'=>$postId,
      'enabled'=>true,
      'parentId'=>$parentId,
      'version'=>$posted_contentVer,
      'font'=>$posted_font,
      'plh'=>$posted_plh,
      'css'=>preg_replace('/cprotext[0-9a-f]{8}/','wpcxX',
      str_replace('\\','\\\\',$posted_contentCSS)),
      'html'=>preg_replace('/cprotext[0-9a-f]{8}-/','wpcxX-',
      str_replace('\\','\\\\',$posted_contentHTML)),
      'eote'=>$contentEOTE,
      'eots'=>$contentEOTS,
      'textId'=>$posted_contentId));
  }else{
    $updated=$wpdb->update($wpdb->prefix.'cptx',
      array(
        'id'=>$postId,
        'enabled'=>true,
        'parentId'=>$parentId,
        'version'=>$posted_contentVer,
        'font'=>$posted_font,
        'plh'=>$posted_plh,
        'css'=>preg_replace('/cprotext[0-9a-f]{8}/','wpcxX',
        str_replace('\\','\\\\',$posted_contentCSS)),
        'html'=>preg_replace('/cprotext[0-9a-f]{8}-/','wpcxX-',
        str_replace('\\','\\\\',$posted_contentHTML)),
        'eote'=>$contentEOTE,
        'eots'=>$contentEOTS,
        'textId'=>$posted_contentId),
      array('id'=>$postId));
  }
  remove_filter('query','cptx_db_null_value');

  if($inserted===false || !$updated){
    $lasterror=$wpdb->last_error;
    if($insert){
      cptx_error(__('Failing to insert CPROTEXT data for post id',CPTX_I18N_DOMAIN).' '.$postId,
      __LINE__);
    }else{
      if($updated===false){
        cptx_error(__('Failing to update CPROTEXT data for post id',CPTX_I18N_DOMAIN).' '.$postId,
          __LINE__);
      }else{
        cptx_warning(__('Nothing to update CPROTEXT data for post id',CPTX_I18N_DOMAIN).' '.$postId,
          __LINE__);
      }
    }
    cptx_debug('wpdb error: '.$lasterror,__LINE__);
    return false;
  }

  if($insert){
    cptx_debug('Successfully insert CPROTEXT data for post id '.$postId,__LINE__);
  }else{
    cptx_debug('Successfully update CPROTEXT data for post id '.$postId,__LINE__);
  }

  $meta=get_post_meta($postId,'cptx_waiting',true);
  if(!empty($meta)){
    if(!delete_metadata('post',$postId,'cptx_waiting')){
      cptx_error(__('Failing to delete waiting metadata for',CPTX_I18N_DOMAIN).' '.$postId,
        __LINE__);
      return false;
    }
    cptx_debug('Successfuly delete waiting metadata for '.$postId.'.',__LINE__);
  }else{
    cptx_debug('No waiting metadata to delete',__LINE__);
  }

  return true;
}

/**
 * cptx_updateKeywords
 *
 * update meta data keywords
 *
 * @param int $postId
 * @access public
 * @return bool
 */
function cptx_updateKeywords($postId){
  $backtrace=debug_backtrace();
  if($backtrace[1]['function']==='cptx_updatePost'){
    $line=$backtrace[1]['line'];
  }else{
    $line=$backtrace[0]['line'];
  }

  $posted_kw=filter_input(INPUT_POST,'cptx_kw');
  if($posted_kw===false){
    cptx_debug('SAVEPOST: invalid kw',$line);
  }

  if($posted_kw===get_post_meta($postId,'cptx_kw',true) ||
    // because update_metadata return false when newdata==olddata
    update_metadata('post',$postId,'cptx_kw',$posted_kw)
  ){
    cptx_debug('Save keywords for '.$postId,$line);
  }else{
    cptx_error(__('Failing to save keywords for',CPTX_I18N_DOMAIN).$postId,$line);
    return false;
  }
  return true;
}

/**
 * cptx_preUpdatePost
 *
 * Save data prior when an asynchronous update occurs
 *
 * @param mixed $postId
 * @param mixed $parentId
 * @param mixed $posted_statusChange
 * @access public
 * @return bool
 */
function cptx_preUpdatePost($postId,$parentId,$posted_statusChange){
  if(!($posted_statusChange & CPTX_STATUS_PROCESSING)){
    cptx_debug('PreUpdating not required');
    return false;
  }

  $inputs=cptx_getInputData(false);
  if(!$inputs){
    cptx_error(__('Invalid input data'));
    return false;
  }

  list(
    $posted_contentId,
    $posted_contentVer,
    $posted_font,
    $posted_plh,
    $posted_contentCSS,
    $posted_contentHTML,
    $contentEOTE,
    $contentEOTS)=$inputs;

  if(!$posted_contentId){
    cptx_debug('cptx_preUpdatePost:  wrong contentId ('.
      ($posted_contentId===false?'invalid':'missing').')',__LINE__);
    return false;
  }

  if(!$posted_font){
    cptx_debug('cptx_preUpdatePost:  wrong fontsel ('.
      ($posted_font===false?'invalid':'missing').')',__LINE__);
    return false;
  }

  if($posted_plh===false){
    cptx_debug('cptx_preUpdatePost:  invalid plh',__LINE__);
    return false;
  }

  $posted_kw=filter_input(INPUT_POST,'cptx_kw');
  if($posted_kw===false){
    cptx_debug('cptx_preUpdatePost: invalid kw',__LINE__);
    return false;;
  }

  // When a protection process has been initiated and asynchronized, the relevant
  // data are stored in the cpxt_waiting metadata so that on the next visit
  // into the corresponding revision, the CPROTEXT form is correctly filled in.
  if(array($posted_contentId,$posted_font,$posted_plh,$posted_kw,$posted_statusChange)!==
    get_post_meta($postId,'cptx_waiting',true) &&
    // because update_metadata return false when newdata==olddata
    !update_metadata('post',$postId,'cptx_waiting',array(
      $posted_contentId,
      $posted_font,
      $posted_plh,
      $posted_kw,
      $posted_statusChange))
  ){
    cptx_error(__('Failing to save waiting metadata for',CPTX_I18N_DOMAIN).' '.$postId,
      __LINE__);
    return false;
  }

  cptx_debug('Successfully save waiting metadata for '.$postId,__LINE__);
  return true;
}

/**
 * cptx_updatePost
 *
 * @param int $postId
 * @param int $parentId
 * @param bool $kw
 * @param bool $insert
 * @access public
 * @return bool
 */
function cptx_updatePost($postId,$parentId,$kw=false,$insert=false){
  $posted_statusChange=filter_input(INPUT_POST,'cptx_statusChange',
    FILTER_VALIDATE_INT,array('options'=>array('min_range'=>0,'max_range'=>511)));

  if(is_null($posted_statusChange)){
    cptx_debug('StatusChange undefined, protection not requested',__LINE__);
    // this revision was not submitted for cprotextion
    return false;
  }

  $result=cptx_upsertdb($postId,$parentId,$insert,$posted_statusChange);
  $backtrace=debug_backtrace();
  if($result){
    if($posted_statusChange & CPTX_STATUS_PROCESSING){
      cptx_debug('Successful initial submission:'.$postId,$backtrace[0]['line']);
    }else{
      cptx_debug('Successfully resumed submission:'.$postId,$backtrace[0]['line']);
    }
  }else{
    cptx_error(__('Failing to update CPROTEXT data for post id',
      CPTX_I18N_DOMAIN).' '.$postId,$backtrace[0]['line']);
    return false;
  }

  if($kw){
    return cptx_updateKeywords($postId);
  }

  return true;
}

/**
 * cptx_setProtectionStatus
 *
 * @param int $id
 * @param bool $enabled
 * @access public
 * @return bool
 */
function cptx_setProtectionStatus($id,$enabled){
  global $wpdb;

  if($enabled){
    $status='enabled';
  }else{
    $status='disabled';
  }

  $updated=$wpdb->update($wpdb->prefix.'cptx',
    array('enabled'=>$enabled),
    array('id'=>$id));

  $backtrace=debug_backtrace();
  if($updated===false){
    cptx_error(__('Failing to update CPROTEXT data for ',
      CPTX_I18N_DOMAIN).$id,$backtrace[0]['line']);
    return false;
  }else{
    if(!$updated){
      cptx_debug('CPROTEXT data were already '.$status.' for '.$id,
        $backtrace[0]['line']);
    }else{
      cptx_debug('CPROTEXT data successfully '.$status.' for '.$id,
        $backtrace[0]['line']);
    }
  }
  return true;
}

/**
 * cptx_transferPost
 *
 * @param int $fromId
 * @param int $toId
 * @access public
 * @return bool
 */
function cptx_transferPost($fromId,$toId){
  global $wpdb;

  $updated=$wpdb->update($wpdb->prefix.'cptx',
    array(
      'id'=>$toId,
      'enabled'=>true),
    array('id'=>$fromId));
  $backtrace=debug_backtrace();
  if($updated===false){
    cptx_error(__('Failing to update CPROTEXT data for ',
      CPTX_I18N_DOMAIN).$fromId,$backtrace[0]['line']);
    return false;
  }else{
    if(!$updated){
      cptx_debug('CPROTEXT were already transfered and enabled to '.
        $toId,$backtrace[0]['line']);
    }else{
      cptx_debug('CPROTEXT data successfully transfered and enabled from '.
        $fromId.' to '.$toId.'.',$backtrace[0]['line']);
    }
  }
  if(update_metadata('post',$fromId,'cptx_id',$toId)){
    cptx_debug('Associate '.$fromId.' with CPROTEXT data from '.$toId.'.',
     $backtrace[0]['line']);
  }else{
    cptx_error(sprintf(__('Failing to associate %u with CPROTEXT data from %u.',
      CPTX_I18N_DOMAIN),$fromId,$toId),$backtrace[0]['line']);
    return false;
  }

  $updated=$wpdb->update($wpdb->prefix.'postmeta',
    array('meta_value'=>$toId),
    array(
      'meta_key'=>'cptx_id',
      'meta_value'=>$fromId
    ));
  $backtrace=debug_backtrace();
  if($updated===false){
    cptx_error(sprintf(
      __('Failing to transfer other posts associated with %u CPROTEXT data to %u',
      CPTX_I18N_DOMAIN),$fromId,$toId),$backtrace[0]['line']);
    return false;
  }else{
    if(!$updated){
      cptx_debug('No other associated posts to transfer to '.
        $toId,$backtrace[0]['line']);
    }else{
      cptx_debug('Other associated posts successfully transfered to '.$toId,
        $backtrace[0]['line']);
    }
  }
  return true;
}

/**
 * cptx_savePost
 *
 * Associate or dissociate a posted text with its protected equivalent
 * generated by CPROTEXT, depending on its publication status
 *
 * @param int $postId
 * @param int $post
 * @access public
 * @return void
 */
function cptx_savePost($postId,$post){
  global $wpdb;

  $posted_statusChange=filter_input(INPUT_POST,'cptx_statusChange',
    FILTER_VALIDATE_INT,array('options'=>array('min_range'=>0,'max_range'=>2047)));

  if(is_null($posted_statusChange)){
    cptx_debug('StatusChange undefined, protection not requested',__LINE__);
    // this revision was not submitted for cprotextion
    return;
  }

  cptx_debug('StatusChange '.$posted_statusChange.' for id '.$postId,__LINE__);

  if($posted_statusChange===false){
    cptx_debug('StatusChange: invalid',__LINE__);
    // this revision was not submitted for cprotextion
    return;
  }

  cptx_debug('We have the job !',__LINE__);

  $parentId=null;
  switch($posted_statusChange &
    (CPTX_STATUS_PUBLISHED + CPTX_STATUS_CHANGED + CPTX_STATUS_CPROTEXTED)){
  case (CPTX_STATUS_PUBLISHED + CPTX_STATUS_NOT_CHANGED + CPTX_STATUS_CPROTEXTED):
    cptx_debug('Publish => Protected Publish',__LINE__);

    if($parentId=wp_is_post_revision($postId)){
      // WordPress sends the modified revision of a post
      if(!($posted_statusChange&CPTX_STATUS_WPUPDATES)){
        // Stop everything before apocalypse !
        cptx_warning(__('This should be a modified revision, but it seems it\'s not !',
          CPTX_I18N_DOMAIN),__LINE__);
        cptx_warning(__('CPROTEXT data integration cancelled.',
          CPTX_I18N_DOMAIN),__LINE__);
        break;
      }

      cptx_debug(sprintf('Revision %u for parent %u',$postId,$parentId),
        __LINE__);

      // If exists, store the previously protected revision id for this post,
      // otherwise, store null
      $prevRevId=$wpdb->get_var('select id from '.$wpdb->prefix.'cptx '.
        'where parentId='.$parentId.' ORDER BY id DESC');
      if(is_null($prevRevId)){
        cptx_debug('Parent '.$parentId.' has no previously protected revision.',
          __LINE__);
      }else{
        $prevRevId=(int)$prevRevId;
        cptx_debug('Parent '.$parentId.' previous protected revision is '.$prevRevId,
          __LINE__);
      }

      if(!update_post_meta($parentId,'cptx_prevProtectedRev',$prevRevId)){
        cptx_error(sprintf(
          __('Failing to save previously protected revision %u for parent %u',
          CPTX_I18N_DOMAIN),$prevRevId,$parentId),
        __LINE__);
        break;
      }

      $last_revision=null;
      if($revisions=wp_get_post_revisions($parentId)){
        // grab the previous revision before the one currently submitted,
        // but not an autosave
        $previous=false;
        foreach($revisions as $revision){
          if (false!==strpos($revision->post_name,$revision->post_parent.'-revision')){
            if($previous){
              $last_revision=$revision;
              break;
            }else{
              $previous=true;
            }
          }
        }
      }else{
        cptx_warning(__('This post does not have any revision: not good !',
          CPTX_I18N_DOMAIN),__LINE__);
        break;
      }

      if(is_null($last_revision)){
        cptx_warning(sprintf(__('Can\'t find Parent %u previous revision',
          CPTX_I18N_DOMAIN),$parentId),
        __LINE__);
        break;
      }else{
        cptx_debug('Parent '.$parentId.' previous revision is '.$last_revision->ID,
          __LINE__);
      }

      if(!update_post_meta($parentId,'cptx_prevRevProtected',
        ($prevRevId===$last_revision->ID)?1:0)
      ){
        cptx_error(sprintf(
          __('Failing to save previously protected state of previous revision for parent %u',
          CPTX_I18N_DOMAIN),$parentId),
        __LINE__);
        break;
      }
      break;
    }else{
      // WordPress updates the parent revision of a post
      // So, if we've been through the above block before, it's a modified
      // revision of a post;
      // otherwise, it's the same revision of a post that may or may not be
      // associated with CPROTEXT data which may have to be updated
      $parentId=$postId;
      if($revisions=wp_get_post_revisions($parentId)){
        // grab the last revision, but not an autosave
        foreach($revisions as $revision){
          if (false!==strpos($revision->post_name,$revision->post_parent.'-revision')){
            $last_revision=$revision;
            break;
          }
        }
      }else{
        cptx_warning(__('This post does not have any revision: not good !',
          CPTX_I18N_DOMAIN),__LINE__);
        break;
      }
      $postId=$last_revision->ID;

      // set single to false so that we can differentiate a non existing value
      // (empty array) from an empty value (array with one empty element)
      $prevProtectedRevId=get_post_meta($parentId,'cptx_prevProtectedRev',false);
      if(empty($prevProtectedRevId)){
        $prevProtectedRevId=null;
      }else{
        if(is_null($prevProtectedRevId[0])){
          $prevProtectedRevId=false;
        }else{
          $prevProtectedRevId=(int)$prevProtectedRevId[0];
        }
        if(!($posted_statusChange & CPTX_STATUS_PROCESSING) &&
          !delete_post_meta($parentId,'cptx_prevProtectedRev')
        ){
          cptx_error(__('Failing to delete previous protected revision id metadata',
            CPTX_I18N_DOMAIN),__LINE__);
          break;
        }
      }

      // set single to false so that we can differentiate a non existing value
      // (empty array) from an empty value (array with one empty element)
      $prevRevProtected=get_post_meta($parentId,'cptx_prevRevProtected',false);
      if(empty($prevRevProtected)){
        $prevRevProtected=null;
      }else{
        $prevRevProtected=((int)$prevRevProtected[0]===1?true:false);
        if(!($posted_statusChange & CPTX_STATUS_PROCESSING) &&
          !delete_post_meta($parentId,'cptx_prevRevProtected')
        ){
          cptx_error(__('Failing to delete protected state of previous revision',
            CPTX_I18N_DOMAIN),__LINE__);
          break;
        }
      }

      // find this post last revision associated with CPROTEXT data
      $protectedPostId=$wpdb->get_var('select id from '.$wpdb->prefix.'cptx '.
        'where parentId='.$parentId.' ORDER BY id DESC');
      if(!is_null($protectedPostId)){
        $protectedPostId=(int)$protectedPostId;
      }

      /*
       * postId = current revision
       *
       * prevRevProtected = is previous revision to the current one associated
       *                    with CPROTEXT data or not ?
       *                    if prevRevProtected===null
       *                      => same revision of published revision
       *                         with or without CPROTEXT data [A|B]
       *                    if prevRevProtected===true
       *                      => modified revision of published revision
       *                         with CPROTEXT data [C]
       *                    if prevRevProtected===false
       *                      => modified revision of published revision
       *                         without CPROTEXT data [D]
       *
       * prevProtectedRevId = previous revision with protection before dealing
       *                      with the current revision
       *                      if prevProtectedRevId===null
       *                        => same revision of published revision
       *                           with or without CPROTEXT data [A|B]
       *                      if prevProtectedRevId===false
       *                        => modified revision of published version
       *                           without CPROTEXT data [D]
       *                      if prevProtectedRevId===id
       *                        => modified revision of a published revision
       *                           with or without CPROTEXT data [C|D]
       *
       * protectedPostId = last revision with protection after dealing with
       *                   the current revision
       *                  if protectedPostId === postId
       *                    => same revision of published revision
       *                       with CPROTEXT data or
       *                       modified revision of published revision
       *                       with or without CPROTEXT data [A|C|D]
       *                  if protectedPostId === null
       *                    => same revision of published revision
       *                       without CPROTEXT data [B]
       *                  if protectedPostId !== null && protectedPostId !== postId
       *                    => same revision of published revision
       *                       without CPROTEXT data [B]
       *
       * case A : update CPROTEXT data with posted CPROTEXT data
       * case B : insert posted CPROTEXT data
       * case C : if CPTX_STATUS_UPDATE_CONTENT
       *            => insert posted CPROTEXT data
       *          if CPTX_STATUS_UPDATE_TITLE && !CPTX_STATUS_UPDATE_CONTENT
       *            => attach previous CPROTEXT data to new revision
       * case D : insert CPROTEXT data
       */

      cptx_debug(sprintf('Parent %u for revision %u',$parentId,$postId),
        __LINE__);

      if(!is_null($prevRevProtected)){
        assert(!is_null($prevProtectedRevId));
        assert($protectedPostId === $postId);
        if($prevRevProtected){
          // case C
          assert($prevProtectedRevId!==false);
          if($posted_statusChange & CPTX_STATUS_UPDATE_CONTENT ||
            // new content means new contentCSS and contentEOTS
            $posted_statusChange&CPTX_STATUS_UPDATE_FONT ||
            // new font means new contentCSS and contentEOTS)
            $posted_statusChange&CPTX_STATUS_UPDATE_PLH
            // new PLH means new contentCSS and contentEOTE
          ){
            if($posted_statusChange & CPTX_STATUS_PROCESSING){
              cptx_preUpdatePost($postId,$parentId,$posted_statusChange);
              break;
            }else{
              if(!cptx_setProtectionStatus($prevProtectedRevId,false)){
                break;
              }
              if(!cptx_updatePost($postId,$parentId,true,true)){
                break;
              }
            }
          }else{
            assert($posted_statusChange & CPTX_STATUS_UPDATE_TITLE);
            // The CPROTEXT data must be associated with the currently protected
            // revision when it exists, or the last protected revision otherwise.
            // Since this modified revision seems to have only a change in the
            // title and potentially in CPROTEXT keywords, the CPROTEXT data must be
            // associated with it and the previous revision annotated in
            // order to keep a trace of its association with the same CPROTEXT data.
            // Moreover CPROTEXT data are enabled whatever its previous state.

            if(!cptx_transferPost($prevProtectedRevId,$postId)){
              break;
            }

            if(!cptx_updateKeywords($postId)){
              break;
            }
          }
        }else{
          // case D
          assert($prevProtectedRevId===false);
          if($posted_statusChange & CPTX_STATUS_PROCESSING){
            cptx_preUpdatePost($postId,$parentId,$posted_statusChange);
          }else{
            cptx_updatePost($postId,$parentId,true,true);
          }
          break;
        }
      }else{
        //case A|B
        assert(is_null($prevProtectedRevId));
        if($protectedPostId === $postId){
          // case A
          if($posted_statusChange&CPTX_STATUS_UPDATE_FONT ||
            // new font means new contentCSS and contentEOTS)
            $posted_statusChange&CPTX_STATUS_UPDATE_PLH
            // new PLH means new contentCSS and contentEOTE
          ){
            if($posted_statusChange & CPTX_STATUS_PROCESSING){
              cptx_preUpdatePost($postId,$parentId,$posted_statusChange);
            }else{
              cptx_updatePost($postId,$parentId,true,false);
            }
            break;
          }else{
            if(!cptx_setProtectionStatus($postId,true)){
              break;
            }
            if(!cptx_updateKeywords($postId)){
              break;
            }
          }
        }else{
          // case B
          if($posted_statusChange & CPTX_STATUS_PROCESSING){
            cptx_preUpdatePost($postId,$parentId,$posted_statusChange);
          }else{
            if(!cptx_updatePost($postId,$parentId,true,true)){
              break;
            }
            if(!cptx_updateKeywords($postId)){
              break;
            }
          }
          break;
        }
      }
    }
    break;
  case (CPTX_STATUS_NOT_PUBLISHED + CPTX_STATUS_CHANGED + CPTX_STATUS_CPROTEXTED):
    cptx_debug('Not published => Protected Publish',__LINE__);

    if($parentId=wp_is_post_revision($postId)){
      // WordPress sends the modified revision of a post
      if(!($posted_statusChange&CPTX_STATUS_WPUPDATES)){
        // Stop everything before apocalypse !
        cptx_warning(__('This should be a modified revision, but it seems it\'s not !',
          CPTX_I18N_DOMAIN),__LINE__);
        cptx_warning(__('CPROTEXT data integration cancelled.',
          CPTX_I18N_DOMAIN),__LINE__);
        break;
      }

      cptx_debug(sprintf('Revision %u for parent %u',$postId,$parentId),
        __LINE__);

      // if this new revision alter the text content, CPROTEXT data is saved
      if($posted_statusChange & CPTX_STATUS_UPDATE_CONTENT){
        if($posted_statusChange & CPTX_STATUS_PROCESSING){
          cptx_preUpdatePost($postId,$parentId,$posted_statusChange);
        }else{
          cptx_updatePost($postId,$parentId,false,true);
        }
      }else{
        cptx_debug('Title change only.',__LINE__);
      }
      break;
    }else{
      // WordPress updates the parent revision of a post
      // So, if we've been through the above block before, it's a modified
      // revision of a post;
      // otherwise, it's the same revision of a post that may or may not be
      // associated with CPROTEXT data which may have to be updated

      $parentId=$postId;
      if($revisions=wp_get_post_revisions($parentId)){
        // grab the last revision, but not an autosave
        foreach($revisions as $revision){
          if (false!==strpos($revision->post_name,$revision->post_parent.'-revision')){
            $last_revision=$revision;
            break;
          }
        }
      }else{
        cptx_warning(__('This post does not have any revision: not good !',
          CPTX_I18N_DOMAIN),__LINE__);
        break;
      }

      $postId=$last_revision->ID;

      if(is_null($postId)){
        cptx_error(__('Failing to find revision id for post',
          CPTX_I18N_DOMAIN).' '.$parentId,__LINE__);
        break;
      }

      cptx_debug(sprintf('Parent %u for revision %s',$parentId,$postId),
        __LINE__);

      // check if we are resuming an asynchronous submission of a modified revision
      $meta=get_post_meta($last_revision->ID,'cptx_waiting',true);
      if(!($posted_statusChange & CPTX_STATUS_PROCESSING) &&
        !empty($meta) &&
        ($posted_statusChange & CPTX_STATUS_UPDATE_CONTENT) &&
        // yes we do, so let's associate this post with CPROTEXT data
        !cptx_updatePost($postId,$parentId,false,true)
      ){
        break;
      }

      $enabled=$wpdb->get_var('select enabled from '.$wpdb->prefix.'cptx '.'
        where id='.$postId);

      if($posted_statusChange & CPTX_STATUS_PROCESSING &&
        !empty($meta)
      ){
        $enabled=true;
      }

      if(is_null($enabled)){
        // No CPROTEXT data associated with this revision,
        // therefore this does not occur after the
        // 'WordPress sends the modified revision of a post' block
        // and we can conclude it is the same revision of a post that is asked
        // to be protected [case D],
        // or it does and we can conclude that it is a modified revision because
        // of a changed title that is asked to be protected, so if previous revision
        // was associated with CPROTEXT data, and none of them has been
        // modified, we transfer the CPROTEXT data [case E], otherwise, we insert new
        // CPROTEXT data [case F and G]
        if($posted_statusChange&CPTX_STATUS_UPDATE_CONTENT){
          cptx_error(__('This should have the same content, but it seems it does not !',
            CPTX_I18N_DOMAIN),__LINE__);
          break;
        }

        if($posted_statusChange&CPTX_STATUS_UPDATE_TITLE){
          // case E F or G
          cptx_debug('Modified revision, add protection',__LINE__);
          $last_revision=null;
          if($revisions=wp_get_post_revisions($parentId)){
            // grab the previous revision before the one currently submitted,
            // but not an autosave
            $previous=false;
            foreach($revisions as $revision){
              if (false!==strpos($revision->post_name,$revision->post_parent.'-revision')){
                if($previous){
                  $last_revision=$revision;
                  break;
                }else{
                  $previous=true;
                }
              }
            }
          }else{
            cptx_warning(__('This post does not have any revision: not good !',
              CPTX_I18N_DOMAIN),__LINE__);
            break;
          }

          if(is_null($last_revision)){
            cptx_warning(sprintf(__('Can\'t find Parent %u previous revision',
              CPTX_I18N_DOMAIN),$parentId),
            __LINE__);
            //todo: what if this is a new post with only a title? this correpond
            //to this case, doesnt it ? then if no content, protection request must be
            //denied !
            break;
          }

          cptx_debug('Parent '.$parentId.' previous revision is '.$last_revision->ID,
            __LINE__);

          $enabled=$wpdb->get_var('select enabled from '.$wpdb->prefix.'cptx '.'
            where id='.$last_revision->ID);
          if(is_null($enabled)){
            // case G
            cptx_debug('No previous protected data, add protection',__LINE__);
            if($posted_statusChange & CPTX_STATUS_PROCESSING){
              cptx_preUpdatePost($postId,$parentId,$posted_statusChange);
            }else{
              cptx_updatePost($postId,$parentId,true,true);
            }
            break;
          }else{
            cptx_debug('Previous protected data exists',__LINE__);
            assert(!$enabled);
            if($posted_statusChange&CPTX_STATUS_UPDATE_FONT ||
              // new font means new contentCSS and contentEOTS)
              $posted_statusChange&CPTX_STATUS_UPDATE_PLH
              // new PLH means new contentCSS and contentEOTE
            ){
              // case F
              if($posted_statusChange & CPTX_STATUS_PROCESSING){
                cptx_preUpdatePost($postId,$parentId,$posted_statusChange);
              }else{
                if($posted_statusChange&CPTX_STATUS_UPDATE_FONT){
                  // case F1
                  cptx_updatePost($postId,$parentId,true,true);
                }else{
                  // case F2
                  if(!cptx_transferPost($last_revision->ID,$postId)){
                    break;
                  }
                  cptx_updatePost($postId,$parentId,true,false);
                }
              }
              break;
            }else{
              // case E
              cptx_debug('Same CPROTEXT data, transfer and enable protection',__LINE__);
              if(!cptx_updateKeywords($postId)){
                break;
              }
            }
          }
        }else{
          // case D
          cptx_debug('Same revision, add protection',__LINE__);
          if($posted_statusChange & CPTX_STATUS_PROCESSING){
            cptx_preUpdatePost($postId,$parentId,$posted_statusChange);
          }else{
            cptx_updatePost($postId,$parentId,true,true);
          }
          break;
        }
      }else{
        // CPROTEXT data are associated with this revision,
        // therefore there are 3 possibilities for a draft asked to be
        // published:
        // A/ data have been inserted in the 'WordPress sends the modified
        //    revision of a post' block asynchronously or not
        // B/ data went through the sequence:
        //         Published/Protected -> Draft/Unprotected ->
        //    and are now submitted to be Published/protected for the same
        //    revision and potentially different CPROTEXT data
        //
        // C/ the draft about to be published is protected, which should not be allowed
        if(!$enabled){
          if($posted_statusChange&CPTX_STATUS_UPDATES){
            if(!($posted_statusChange&CPTX_STATUS_WPUPDATES)){
              // case B requiring CPROTEXT data update
              cptx_debug('Same revision, with different CPROTEXT data: '.
              'update CPROTEXT data and enable protection.',__LINE__);
            }else{
              // This is not any of case A, B or C, but it should not happen !
              // Stop everything before apocalypse !
              cptx_warning(__('This should be the same revision, '.
                'but it does not seem to be so !',
                CPTX_I18N_DOMAIN),__LINE__);
              cptx_warning(__('CPROTEXT data integration cancelled.'),__LINE__);
              break;
            }
          }else{
            // case B
            cptx_debug('Same revision, same CPROTEXT data: enable protection',__LINE__);
          }

          if($posted_statusChange&CPTX_STATUS_UPDATE_FONT ||
            // new font means new contentCSS and contentEOTS)
            $posted_statusChange&CPTX_STATUS_UPDATE_PLH
            // new PLH means new contentCSS and contentEOTE
          ){
            // update CPROTEXT data and enable protection
            if($posted_statusChange & CPTX_STATUS_PROCESSING){
              cptx_preUpdatePost($postId,$parentId,$posted_statusChange);
            }else{
              cptx_updatePost($postId,$parentId,true,false);
            }
            break;
          }else{
            // enable protection
            if(!cptx_updateKeywords($postId)){
              break;
            }
          }
        }else{
          if(!($posted_statusChange&CPTX_STATUS_WPUPDATES)){
            //case C
            // Stop everything before apocalypse !
            cptx_warning(__('How can this about-to-be-published draft be protected ?',
              CPTX_I18N_DOMAIN),__LINE__);
            cptx_warning(__('CPROTEXT data integration cancelled.',
              CPTX_I18N_DOMAIN),__LINE__);
            break;
          }

          // case A
          cptx_debug('This seems to be a modified revision.',__LINE__);

          if(!cptx_updateKeywords($postId)){
            break;
          }

          cptx_debug('Job done:'.$postId,__LINE__);
          break;
        }
      }
    }
    break;
  case (CPTX_STATUS_PUBLISHED + CPTX_STATUS_NOT_CHANGED + CPTX_STATUS_NOT_CPROTEXTED):
    cptx_debug('Protected Publish => Publish',__LINE__);
    $announced=true;
  case (CPTX_STATUS_PUBLISHED + CPTX_STATUS_CHANGED + CPTX_STATUS_NOT_CPROTEXTED):
  case (CPTX_STATUS_PUBLISHED + CPTX_STATUS_CHANGED + CPTX_STATUS_CPROTEXTED):
    if(!$announced){
      cptx_debug('Protected Publish => Draft or Pending',__LINE__);
    }

    if($parentId=wp_is_post_revision($postId)){
      // Modified revision
      cptx_debug(sprintf('Revision %u for parent %u',$postId,$parentId),
        __LINE__);
      $updated=$wpdb->update($wpdb->prefix.'cptx',
        array('enabled'=>false),
        array('parentId'=>$parentId));
      if($updated===false){
        cptx_error(__('Failing to disable CPROTEXT data for parent id',
          CPTX_I18N_DOMAIN).' '.$parentId,__LINE__);
        break;
      }else{
        if(!$updated){
          cptx_debug('CPROTEXT data were already disabled for parent id '.$parentId,
            __LINE__);
        }else{
          cptx_debug('CPROTEXT data were successfully disabled for parent id '.$parentId,
            __LINE__);
        }
      }
    }else{
      // Modified revision if we've been through the above block before
      // Same revision otherwise
      $parentId=$postId;

      if($revisions=wp_get_post_revisions($parentId)){
        // grab the last revision, but not an autosave
        foreach($revisions as $revision){
          if (false!==strpos($revision->post_name,$revision->post_parent.'-revision')){
            $last_revision=$revision;
            break;
          }
        }
      }else{
        cptx_warning(__('This post does not have any revision: not good !',
          CPTX_I18N_DOMAIN),__LINE__);
        break;
      }

      $postId=$last_revision->ID;

      if(is_null($postId)){
        cptx_error(__('Failing to find revision id for post',
          CPTX_I18N_DOMAIN).' '.$parentId,__LINE__);
        break;
      }

      cptx_debug(sprintf('Parent %u for revision %u',$parentId,$postId),
        __LINE__);

      $enabled=$wpdb->get_var('select enabled from '.$wpdb->prefix.'cptx '.'
        where id='.$postId);

      if(is_null($enabled)){
        cptx_debug('Modified revision, no protection',__LINE__);
      }else{
        cptx_debug('Same revision, disable protection',__LINE__);
        if(!cptx_setProtectionStatus($postId,false)){
          break;
        }
      }
    }
    break;
  }
}

/**
 * cptx_delete
 *
 * Delete DB wp_cptx row
 *
 * @param mixed $postId
 * @access public
 * @return void
 */
function cptx_delete($postId){
  global $wpdb;
  $wpdb->delete($wpdb->prefix.'cptx',array('parentId'=>$postId));
}

/**
 * cptx_postsJoin
 *
 * Alter the join part of the wp_query so that protected version of the
 * corresponding text can be appended to the results
 *
 * @param string $join
 * @access public
 * @return string
 */
function cptx_postsJoin($join){
  global $wpdb;
  $tableName=$wpdb->prefix.'cptx';
  $join.=' LEFT JOIN '.$tableName.' ON '.$wpdb->posts.'.ID = '.$tableName.'.parentId ';
  $join.=' AND '.$tableName.'.enabled=1 ';
  return $join;
}

/**
 * cptx_postsRequest
 *
 * Display queries when CPTX_DEBUG_QUERIES is true
 *
 * @param string $request
 * @access public
 * @return string
 */
function cptx_postsRequest($request){
  if(CPTX_DEBUG_QUERIES){
    print_r($request);
  }
  return $request;
}

/**
 * cptx_postClass
 *
 * Append cprotexted class to single post pageviews when appropriate
 *
 * @param string[] $classes
 * @access public
 * @return string[]
 */
function cptx_postClass($classes=array()){
  global $post;
  if(empty($post->cptx_html)){
    return $classes;
  }
  $classes[]='cprotexted';
  return $classes;
}

/**
 * cptx_postsFields
 *
 * Alter the wp_query so that protected version data of the
 * corresponding text are appended to the results
 *
 * @param string $fields
 * @access public
 * @return string
 */
function cptx_postsFields($fields){
  global $wpdb;
  $tableName=$wpdb->prefix.'cptx';
  $fields.=', `'.$tableName.'`.id as cptx_id ';
  $fields.=', `'.$tableName.'`.enabled as cptx_enabled ';
  $fields.=', `'.$tableName.'`.html as cptx_html ';
  $fields.=', `'.$tableName.'`.css as cptx_css ';
  return $fields;
}

/**
 * cptx_content
 *
 * Display the HTML of the protected version of a content instead of the
 * original text when appropriate
 *
 * @param string $content
 * @access public
 * @return string
 */
function cptx_content($content){
  global $post;
  global $posts;

  if(!$post->cptx_enabled){
    return $content;
  }

  $postid=-1;
  foreach ($posts as $i=>$p){
    if($posts[$i]->ID===$post->ID){
      $postid=$i;
      break;
    }
  }
  if($postid===-1){
    return $content;
  }

  $result=str_replace('cxX-','cx'.$postid.'-',stripslashes($post->cptx_html));

  $settings=get_option('cprotext');
  if($settings['notice']){
    $result.='<span class="wpcx'.$postid.'-c">[ ';
    $result.='Copyrighted text protected by ';
    $result.='<a href="https://www.cprotext.com">CPROTEXT</a>';
    $result.=' ]</span>';
  }
  return $result;
}

/**
 * Source: https://wpscholar.com/blog/get-attachment-id-from-wp-image-url/
 * Get an attachment ID given a URL.
 *
 * @param string $url
 *
 * @return int Attachment ID on success, 0 on failure
 */
function get_attachment_id( $url ) {
  $attachment_id = 0;
  $dir = wp_upload_dir();
  if ( false !== strpos( $url, $dir['baseurl'] . '/' ) ) { // Is URL in uploads directory?
    $file = basename( $url );
    $query_args = array(
      'post_type'   => 'attachment',
      'post_status' => 'inherit',
      'fields'      => 'ids',
      'meta_query'  => array(
        array(
          'value'   => $file,
          'compare' => 'LIKE',
          'key'     => '_wp_attachment_metadata',
        ),
      )
    );
    $query = new WP_Query( $query_args );
    if ( $query->have_posts() ) {
      foreach ( $query->posts as $post_id ) {
        $meta = wp_get_attachment_metadata( $post_id );
        $original_file       = basename( $meta['file'] );
        $cropped_image_files = wp_list_pluck( $meta['sizes'], 'file' );
        if ( $original_file === $file || in_array( $file, $cropped_image_files ) ) {
          $attachment_id = $post_id;
          break;
        }
      }
    }
  }
  return $attachment_id;
}

function cprotimg_content($content){
  global $wpdb;
  $uploads=wp_upload_dir(null,false);
  $targets=array();
  $replacements=array();
  $postContentLen=strlen($content);
  $classStart=stripos($content,'wp-image-');
  while($classStart!==false){
    $classStart=strripos($content,'"',$classStart-$postContentLen);
    $classEnd=stripos($content,'"',$classStart+1);
    $class=substr($content,$classStart,$classEnd-$classStart);

    $imgIdStart=stripos($class,'wp-image-')+strlen('wp-image-');
    $imgIdEnd=stripos($class,' ',$imgIdStart);
    if($imgIdEnd===false){
      $imgId=substr($class,$imgIdStart);
    }else{
      $imgId=substr($class,$imgIdStart,$imgIdEnd-$imgIdStart);
    }

    if(!get_post_meta($imgId, 'cprotimg', true)){
      $classStart=stripos($content,'wp-image-',$classEnd);
      continue;
    }

    $imgSizeStart=strpos($class,'size-')+strlen('size-');
    $imgSizeEnd=strpos($class,' ',$imgSizeStart);
    if($imgSizeEnd===false){
      $imgSize=substr($class,$imgSizeStart);
    }else{
      $imgSize=substr($class,$imgSizeStart,$imgSizeEnd-$imgSizeStart);
    }

    $imgStart=strripos($content,'<img ',$classStart-$postContentLen);
    if($imgStart===false){
      $classStart=stripos($content,'wp-image-'.$imgId,
        $classStart+strlen('wp-image-'.$imgId));
      continue;
    }
    $imgEnd=stripos($content,'>',$imgStart);
    if($imgEnd===false){
      $classStart=false;
      continue;
    }
    $img=substr($content,$imgStart,($imgEnd+1)-$imgStart);


    $matchCount=preg_match_all(
      '/([a-z]+)="([^"]+)"/i',
      $img,$matches,PREG_SET_ORDER
    );
    $attrs=array();
    if($matchCount){
      foreach($matches as $match){
        $attrs[$match[1]]=htmlentities($match[2],ENT_QUOTES,'UTF-8',false);
      }
    }
    $src=$attrs['src'];
    unset($attrs['src']);

    $path=str_replace($uploads['baseurl'].'/','',$src);
    $data=$wpdb->get_row(
      'select child.html, parent.html as phtml, parent.srcpath as psrcpath '
      .'from '.$wpdb->prefix.'cptimg as child '
      .'left outer join '.$wpdb->prefix.'cptimg as parent '
      .'on parent.id=child.parentId '
      .'where child.srcpath="'.$path.'"',
      ARRAY_A
    );

    $targets[]=$img;

    $replacement=$data['html'];
    if(empty($data['html'])){
      // same image but different scale
      $replacement=$data['phtml'];
      if(empty($data['phtml'])){
        // same image, different scale and different source file name
        $data=$wpdb->get_row(
          'select parent.html as phtml '
          .'from '.$wpdb->prefix.'cptimg as child '
          .'left outer join '.$wpdb->prefix.'cptimg as parent '
          .'on parent.id=child.parentId '
          .'where child.srcpath="'.$data['psrcpath'].'"',
          ARRAY_A
        );
        $replacement=$data['phtml'];
      }
    }

    if(isset($attrs['srcset'])){
      // TODO: deal with srcset to conserve original design
      //       for now css backend generate a media query that is clearly not
      //       satisfactory
      unset($attrs['srcset']);
      unset($attrs['sizes']);
    }

    $replacement=str_replace('class="cpx_#_"','class="cpx_#_ '.$attrs['class'].'"',$replacement);
    $attrsStr='';
    foreach($attrs as $name=>$value){
      $attrsStr.=' '.$name.'="'.$value.'"';
    }

    $replacement=str_replace('_ATTRS_=""',$attrsStr,$replacement);

    $replacement=str_replace('cpx_#_','cpx'.hash('crc32b',$imgId.$imgSize),$replacement);

    $replacements[]=$replacement;
    $classStart=stripos($content,'wp-image-',$classEnd);
  }

  $urlStart=stripos($content,$uploads['baseurl'].'/');
  $fileStart=strlen($uploads['baseurl'].'/');
  while($urlStart!==false){
    $urlEnd=stripos($content,'"',$urlStart);
    $url=substr($content,$urlStart,$urlEnd-$urlStart);
    $file=substr($url,$fileStart);
    $result=$wpdb->get_var($wpdb->prepare(
      'select imgId '
      .'from '.$wpdb->prefix.'cptimg '
      .'where srcpath="%s"',$file
    ));
    if($result && get_post_meta($result, 'cprotimg', true)){
      $targets[]=$url;
      $replacements[]='';
    }
    $urlStart=stripos($content,$uploads['baseurl'].'/',$urlEnd);
  }

  $newContent=str_replace($targets,$replacements,$content);


  return $newContent;
}

/**
 * cptx_pageInserts
 *
 * Insert header related data of the protected texts to display
 *
 * @access public
 * @return void
 */
function cptx_pageInserts(){
  global $post,$wp_version,$wpdb;
  $keywords='';
  $postid=0;
  $uploads=wp_upload_dir(null,false);
  $images=array();
  if (have_posts()){
    while (have_posts()){
      the_post();

      // Deal with protected texts
      if(!empty($post->cptx_css)){
        echo '<link rel="stylesheet" type="text/css" ';
        add_filter('nonce_life', 'cptx_setAssetsNonceLifeSpan');
        $url=wp_nonce_url(
          admin_url('admin-ajax.php').'?action=cptxCSS',
          CPTX_WP_CSSNONCE_ACTION,CPTX_WP_ASSETSNONCE_VAR).
          '&amp;id='.$post->cptx_id.'&amp;o='.$postid;
        remove_filter('nonce_life', 'cptx_setAssetsNonceLifeSpan');

        echo 'href="'.$url.'"/>';
      }
      $kw=get_post_meta($post->cptx_id,'cptx_kw',true);
      if(!empty($kw)){
        $keywords.=(!empty($keywords)?',':'').$kw;
      }
      $postid++;

      // Deal with protected images
      $postContentLen=strlen($post->post_content);
      $classStart=stripos($post->post_content,'wp-image-');
      while($classStart!==false){
        $classStart=strripos($post->post_content,'"',$classStart-$postContentLen);
        $classEnd=stripos($post->post_content,'"',$classStart+1);
        $class=substr($post->post_content,$classStart,$classEnd-$classStart);

        $imgIdStart=stripos($class,'wp-image-')+strlen('wp-image-');
        $imgIdEnd=stripos($class,' ',$imgIdStart);
        if($imgIdEnd===false){
          $imgId=substr($class,$imgIdStart);
        }else{
          $imgId=substr($class,$imgIdStart,$imgIdEnd-$imgIdStart);
        }

        $imgSizeStart=strpos($class,'size-')+strlen('size-');
        $imgSizeEnd=strpos($class,' ',$imgSizeStart);
        if($imgSizeEnd===false){
          $imgSize=substr($class,$imgSizeStart);
        }else{
          $imgSize=substr($class,$imgSizeStart,$imgSizeEnd-$imgSizeStart);
        }

        $imgStart=strripos($post->post_content,'<img ',$classStart-$postContentLen);
        if($imgStart===false){
          $classStart=stripos($post->post_content,'wp-image-'.$imgId,
            $classStart+strlen('wp-image-'.$imgId));
          continue;
        }
        $imgEnd=stripos($post->post_content,'>',$imgStart);
        if($imgEnd===false){
          $classStart=false;
          continue;
        }
        $img=substr($post->post_content,$imgStart,($imgEnd+1)-$imgStart);

        if(!isset($images[$imgId][$imgSize])){
          if(!isset($images[$imgId])){
            $images[$imgId]=array();
          }
          $images[$imgId][$imgSize]=true;
          add_filter('nonce_life', 'cptx_setAssetsNonceLifeSpan');
          $url=wp_nonce_url(
            admin_url('admin-ajax.php').'?action=cptxCSS',
            CPTX_WP_CSSNONCE_ACTION,CPTX_WP_ASSETSNONCE_VAR).
            '&amp;id='.$imgId.'&amp;s='.$imgSize;
          remove_filter('nonce_life', 'cptx_setAssetsNonceLifeSpan');
          echo '<link rel="stylesheet" type="text/css" href="'.$url.'"/>';
        }


        $classStart=stripos($post->post_content,'wp-image-',$classEnd);
      }
    }
    echo '<!--[if IE 8]>';
    echo '<script type="text/javascript">';
    @readfile(__DIR__.'/js/cptxie8fix'.(CPTX_DEBUG?'':'.min').'.js');
    echo '</script>';
    echo '<![endif]-->';
    rewind_posts();
    if(is_single() && !empty($keywords)){
      echo '<meta name="keywords" content="'.$keywords.'"/>';
    }
  }
}

/**
 * cptx_checkInputs
 *
 * check that only white listed inputs are received
 *
 * @param mixed $allowedInputs
 * @access public
 * @return void
 */
function cptx_checkInputs($allowedInputs){
  // add input required by wordpress
  if(!in_array('action',$allowedInputs['_GET'])){
    $allowedInputs['_GET'][]='action';
  }

  foreach(array_keys($allowedInputs) as $inputType){
    if(is_null($allowedInputs[$inputType])){
      continue;
    }
    foreach(array_keys($GLOBALS[$inputType]) as $var){
      if(!in_array($var,$allowedInputs[$inputType])){
        echo $var;
        header('HTTP/1.1 404 Not Found');
        exit;
      }
    }
  }
}

function cptimg_scramble($id,$src){
  global $wpdb;

  $uploads=wp_upload_dir(null,false);
  $homeUrl=get_home_url();
  $imgs=array();
  $duplicates=array();
  $querypart='';
  foreach($src as $width=>$heights){
    foreach($heights as $height=>$files){
      $files=array_unique($files);
      $file=$files[0];
      $path=$uploads['basedir'].'/'.$file;

      $scrambledDir=dirname($path).'/cprotimg';
      if(file_exists($scrambledDir.'/'.sha1($file).'.jpg')){
        continue;
      }
      if(!file_exists($scrambledDir)){
        $created=mkdir($scrambledDir,0777,true);
        if(!$created){
          cptx_error(
            __('Can not create directory "'.$scrambledDir.'"',CPTX_I18N_DOMAIN),
            __LINE__);
          return false;
        }
      }

      $imgs[]=array(
        'w'=>$width,
        'h'=>$height,
        'p'=>$file,
        'sp'=>dirname($file).'/cprotimg/'.sha1($file).'.jpg',
        'su'=>$uploads['baseurl'].'/'.dirname($file).'/cprotimg/'.sha1($file).'.jpg'
      );
      $querypart.='&w[]='.urlencode($width)
       .'&h[]='.urlencode($height)
        .'&ha[]='.urlencode(sha1_file($uploads['basedir'].'/'.$file))
        .'&t[]='.urlencode($file);
      foreach($files as $i=>$file){
        if(!$i){
          continue;
        }
        $duplicates[$file]=count($imgs)-1;
      }
    }
  }

  if(empty($querypart)){
    return false;
  }
  $querypart='&s='.urlencode($homeUrl).$querypart;

  $settings=get_option('cprotext');
  $url = CPTX_API_PROTOCOL.'://'.CPTX_API_HOST.CPTX_API_PATH.'?';
  $queries=array(
    array('method'=>'get','query'=>'f=token&n=credits'),
    array('method'=>'get','query'=>'f=credits&n=img'),
    array('method'=>'file','query'=>'f=img'.$querypart)
  );

  $result=array('token'=>$settings['authtok']);

  foreach($queries as $query){
    $headers=array('Authorization'=>'Bearer '.$result['token']);
    switch($query['method']){
    case 'get':
      $result=wp_remote_get($url.$query['query'],array('headers'=>$headers));
      break;
    case 'file':
      $files=array();
      foreach($imgs as $i=>$img){
        $files['imgs['.$i.']']=$uploads['basedir'].'/'.$img['p'];
      }
      list($content,$boundary)=cptx_customPostFields(array(),$files);
      $headers['content-type']='multipart/form-data; boundary='.$boundary;
      $result=wp_remote_post($url.$query['query'],array('headers'=>$headers,'body'=>$content));
      break;
    }
    if(is_wp_error($result)){
      throw new Exception($result->get_error_message());
    }
    if($result['response']['code']!==200){
      throw new Exception($result['response']['message']);
    }
    $result=json_decode($result['body'],true);
    if(isset($result['error'])){
      throw new Exception($result['error']);
    }
  }

  foreach($result as $i=>$cprotimg){
    file_put_contents(
      $uploads['basedir'].'/'.$imgs[$i]['sp'],
      base64_decode($cprotimg['data'])
    );

    $scrambledCss=str_replace('_IMGURL_',$imgs[$i]['su'],$cprotimg['css']);

    $scrambledHtml=$cprotimg['html'];

    $scrambledHtml=str_replace(
      '<img ></img>',
      '<img src="'.plugins_url('wp-cprotext/images/t.gif').'" _ATTRS_=""></img>',
      $scrambledHtml
    );

    $row=array(
      'imgId'=>$id,
      'parentId'=>(is_int($cprotimg['html'])?$imgs[$cprotimg['html']]['id']:null),
      'srcpath'=>$imgs[$i]['p'],
      'destpath'=>$imgs[$i]['sp'],
      'css'=>$scrambledCss,
      'html'=>(is_int($cprotimg['html'])?null:$scrambledHtml)
    );

    $inserted=$wpdb->insert($wpdb->prefix.'cptimg',$row);

    if($inserted===false){
      $lasterror=$wpdb->last_error;
      cptx_error(
        __('Failing to insert CPROTIMG data for',CPTX_I18N_DOMAIN).' '.$imgs[$i]['p'],
        __LINE__
      );
      cptx_debug('wpdb error: '.$lasterror,__LINE__);
      return false;
    }

    $imgs[$i]['id']=$wpdb->insert_id;
  }

  foreach($duplicates as $file=>$imgId){
    $row=array(
      'imgId'=>$id,
      'parentId'=>$imgs[$imgId]['id'],
      'srcpath'=>$file,
      'destpath'=>null,
      'css'=>null,
      'html'=>null
    );

    $inserted=$wpdb->insert($wpdb->prefix.'cptimg',$row);

    if($inserted===false){
      $lasterror=$wpdb->last_error;
      cptx_error(
        __('Failing to insert CPROTIMG data for',CPTX_I18N_DOMAIN).' '.$imgs[$i]['p'],
        __LINE__
      );
      cptx_debug('wpdb error: '.$lasterror,__LINE__);
      return false;
    }
  }

  return true;
}

function cprotimg_attachmentFieldsEdit($fields,$post){
  $value = get_post_meta( $post->ID, 'cprotimg', true  );
  $fields['cprotimg']=array(
    'label'=>__('Protected',CPTX_I18N_DOMAIN),
    'input'=>'html',
    'html'=>
    '<input type="checkbox" name="attachments['.$post->ID.'][cprotimg]" '
    .'id="attachments['.$post->ID.'][cprotimg]" value="1" '
    .($value?'checked="checked"':'').'/>',
    'helps'=>__('by CPROTEXT.COM',CPTX_I18N_DOMAIN)
  );
  return $fields;
}

function cprotimg_attachmentFieldsSave($post,$attachment){
  $postMeta=array();
  if(substr($post['post_mime_type'],0,5)==='image'){
    if(isset($attachment['cprotimg'])){
      $metadata=wp_get_attachment_metadata($post['ID']);
      $imgs[$metadata['width']][$metadata['height']]=array();
      $imgs[$metadata['width']][$metadata['height']][]=$metadata['file'];
      $dir=dirname($metadata['file']);
      foreach($metadata['sizes'] as $img){
        if(!isset($imgs[$img['width']][$img['height']])){
          $imgs[$img['width']][$img['height']]=array();
        }
        $imgs[$img['width']][$img['height']][]=$dir.'/'.$img['file'];
      }
      cptimg_scramble($post['ID'],$imgs);
      update_post_meta($post['ID'], 'cprotimg', true);
    }else{
      if(in_array('cprotimg',get_post_custom_keys($post['ID']))){
        update_post_meta($post['ID'], 'cprotimg', false);
      }
    }
  }
  return $post;
}

function cprotimg_delete($postId){
  global $wpdb;

  $data=wp_get_attachment_metadata($postId);
  $uploads=wp_upload_dir(null,false);
  $srcpath=$data['file'];
  $srcpaths=array('"'.$srcpath.'"');
  $srcpath=dirname($srcpath);
  foreach($data['sizes'] as $size){
    $srcpaths[]='"'.$srcpath.'/'.$size['file'].'"';
  }
  $results=$wpdb->get_results(
    'SELECT id,parentId,destpath '
    .'FROM '.$wpdb->prefix.'cptimg '
    .'WHERE srcpath in ('.implode(',',$srcpaths).') '
    .'ORDER BY parentId DESC;',
      ARRAY_A
  );
  foreach($results as $result){
    if(!empty($result['destpath'])){
      $done=unlink($uploads['basedir'].'/'.$result['destpath']);
      if(!$done){
        cptx_error('Can not delete file "'.$uploads['basedir'].'/'.$result['destpath'].'".',__LINE__);
        continue;
      }
    }
    $done=$wpdb->delete($wpdb->prefix.'cptimg',array('id'=>$result['id']),array('%d'));
    if(!$done){
      cptx_error(
        'Can not delete database entry for "'.$result['destpath'].'" '
        .'in table '.$wpdb->prefix.'cptimg .',
        __LINE__
      );
    }
  }
  return;
}

function cptimg_attachmentRedirect() {
  global $post;
  if(is_attachment() && get_post_meta($post->ID, 'cprotimg', true) &&
    isset($post->post_parent) && is_numeric($post->post_parent)
  ){
    if ($post->post_parent != 0) {
      wp_redirect(get_permalink($post->post_parent), 301);
      exit;
    }else{
      wp_redirect(get_bloginfo('wpurl'), 302);
      exit;
    }
  }
}

/**
 * cptx_init
 *
 * Initialize plugin localisation
 *
 * @access public
 * @return void
 */
function cptx_init() {
  $plugin_dir = dirname(plugin_basename(__FILE__));
  load_plugin_textdomain(CPTX_I18N_DOMAIN, false, $plugin_dir.'/lang/' );
}

add_action('plugins_loaded', 'cptx_init');

register_activation_hook(__FILE__,'cptx_updateCheck');
add_action( 'admin_menu', 'cptx_menu' );
add_action('admin_init','cptx_adminInit');
add_action('admin_enqueue_scripts','cptx_adminInserts');
add_action('add_meta_boxes','cptx_post');
add_action('save_post','cptx_savePost',10,2);
add_action('edit_form_top','cptx_messagesHandler');
add_action('admin_footer', 'cptx_popup');
add_filter('posts_fields','cptx_postsFields');
add_filter('posts_join','cptx_postsJoin');
add_filter('posts_request','cptx_postsRequest');
add_filter('post_class','cptx_postClass');
add_action('wp_enqueue_scripts','cptx_pageInserts');
add_action('deleted_post','cptx_delete');
add_action('delete_attachment','cprotimg_delete');
add_filter('the_content','cptx_content');
add_filter('the_content','cprotimg_content',99);
add_filter('attachment_fields_to_edit','cprotimg_attachmentFieldsEdit', 10, 2 );
add_filter('attachment_fields_to_save','cprotimg_attachmentFieldsSave', 10, 2 );
add_action('wp_ajax_cptxProxy','cptx_APIProxy');
add_action('wp_ajax_nopriv_cptxCSS','cptx_postCSS');
add_action('wp_ajax_cptxCSS','cptx_postCSS');
add_action('wp_ajax_nopriv_cptxFont','cptx_postFont');
add_action('wp_ajax_cptxFont','cptx_postFont');
add_action('template_redirect', 'cptimg_attachmentRedirect',1);
?>
