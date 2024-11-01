<?php
define('CPTX_MSG_ERROR',1);
define('CPTX_MSG_WARNING',2);
define('CPTX_MSG_DEBUG',3);

/**
 * cptx_msg
 *
 * Register messages to display after text submission
 * and store it in wordpress option cptx_msgs
 *
 * @param int $type
 * @param string $msg
 * @param int $line
 * @access public
 * @return void
 */
function cptx_msg($type,$msg,$line){
  $settings=get_option('cprotext');
  if(!isset($settings['msgs'])){
    $settings['msgs']=array();
  }
  $settings['msgs'][]=array($type,$msg,$line);
  update_option('cprotext',$settings);
}

/**
 * cptx_error
 *
 * Register error messages
 *
 * @param string $msg
 * @param int $line
 * @access public
 * @return void
 */
function cptx_error($msg,$line){
  cptx_msg(CPTX_MSG_ERROR,$msg,$line);
}

/**
 * cptx_warning
 *
 * Register warning messages
 *
 * @param string $msg
 * @param int $line
 * @access public
 * @return void
 */
function cptx_warning($msg,$line){
  cptx_msg(CPTX_MSG_WARNING,$msg,$line);
}

/**
 * cptx_debug
 *
 * Register debug messages
 *
 * @param string $msg
 * @param int $line
 * @access public
 * @return void
 */
function cptx_debug($msg,$line){
  if(CPTX_DEBUG){
    cptx_msg(CPTX_MSG_DEBUG,$msg,$line);
  }
}

/**
 * cptx_messagesHandler
 *
 * Display registered messages and flush cptx_msgs option
 *
 * @access public
 * @return void
 */
function cptx_messagesHandler(){
  $settings=get_option('cprotext');
  if(!isset($settings['msgs'])){
    $settings['msgs']=array();
  }
  foreach($settings['msgs'] as $message){
    switch($message[0]){
    case CPTX_MSG_ERROR: $class='error'; break;
    case CPTX_MSG_WARNING: $class='warning'; break;
    case CPTX_MSG_DEBUG: $class='debug'; break;
    }
    echo '<div class="cptxmsg cptx'.$class.'">';
    echo '<p>';
    echo 'WP-CPROTEXT ['.$class.']: '.$message[1].' (L'.$message[2].')';
    echo '</p></div>';
  }
  $settings['msgs']=array();
  update_option('cprotext',$settings);
}


?>
