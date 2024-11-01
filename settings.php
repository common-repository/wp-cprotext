<?php

function cptx_initSettings($settings){
  if(!$settings['version']){
    $settings=array(
      'version'=>CPTX_PLUGIN_VERSION,
      'dbversion'=>CPTX_DB_VERSION,
      'popup'=>1,
      'authtok'=>'',
      'hsync'=>false,
      'hfontlist'=>'',
      'font'=>'',
      'notice'=>true,
    );

  }else{
    switch(CPTX_PLUGIN_VERSION){
    case '2.0.0':
      if(count($settings)>3){
        break;
      }
      $settings=array(
        'version'=>CPTX_PLUGIN_VERSION,
        'dbversion'=>CPTX_DB_VERSION,
        'popup'=>get_option('cptx_popup'),
        'msgs'=>get_option('cptx_msgs'),
        'authtok'=>'',
        'hsync'=>false,
        'hfontlist'=>get_option('cptx_fonts'),
        'font'=>get_option('cptx_font'),
        'notice'=>get_option('cptx_notice')<3?true:false,
      );
      delete_option('cptx_version');
      delete_option('cptx_db_version');
      delete_option('cptx_popup');
      delete_option('cptx_msgs');
      delete_option('cptx_fonts');
      delete_option('cptx_font');
      delete_option('cptx_ie8');
      delete_option('cptx_notice');
      break;
    default:
      break;
    }

    if(file_exists(__DIR__.'/tpl/release_'.CPTX_PLUGIN_VERSION.'.en_US.html')){
      $settings['popup']=2;
    }else{
      $settings['popup']=0;
    }
  }

  return update_option('cprotext',$settings);

}

/**
 * cptx_menu
 *
 * add CPROTEXT options in settings menu
 *
 * @access public
 * @return void
 */
function cptx_menu(){
  add_options_page( __('CPROTEXT Options',CPTX_I18N_DOMAIN), 'CPROTEXT', 'manage_options', 'cprotext', 'cptx_options' );
}

/**
 * cptx_options
 *
 * display CPROTEXT options page
 *
 * @access public
 * @return void
 */
function cptx_options(){
  if ( !current_user_can( 'manage_options' ) )  {
    wp_die( __( 'You do not have sufficient permissions to access this page.',CPTX_I18N_DOMAIN ) );
  }
  echo '<div class="wrap">';
  echo '<h1>'.__('CPROTEXT options',CPTX_I18N_DOMAIN).'</h1>';
  settings_errors();
  echo '<form id="cptx_settings" action="options.php" method="post">';
  echo '<input type="hidden" name="cprotext[version]" value="'.CPTX_PLUGIN_VERSION.'"/>';
  echo '<input type="hidden" name="cprotext[dbversion]" value="'.CPTX_DB_VERSION.'"/>';
  echo '<input type="hidden" name="cprotext[popup]" value="0"/>';
  settings_fields('cprotext');
  do_settings_sections('cprotext');
  submit_button();
  echo '</form>';
  echo '</div>';
}

function cptx_sanitize($input){
  $cleanInput=array();

  foreach($input as $k=>$v){
    switch($k){
    case 'version':
      if($input[$k]!==CPTX_PLUGIN_VERSION){
        add_settings_error('cprotext',$k,__('Plugin version mismatch',CPTX_I18N_DOMAIN),'error');
      }else{
        $cleanInput[$k]=$input[$k];
      }
      break;
    case 'dbversion':
      if($input[$k]!==CPTX_DB_VERSION){
        add_settings_error('cprotext',$k,__('Database version mismatch',CPTX_I18N_DOMAIN),'error');
      }else{
        $cleanInput[$k]=$input[$k];
      }
      break;
    case 'popup':
      $popup=filter_var($input[$k],FILTER_VALIDATE_INT,
        array('options'=>array('min_range'=>0,'max_range'=>4)));
      if($popup === false){
        add_settings_error('cprotext',$k,__('Internal "popup" value invalid',CPTX_I18N_DOMAIN),'error');
      }
      $cleanInput[$k]=(int)$popup;
      break;
    case 'authtok':
      $token=filter_var($input[$k],FILTER_VALIDATE_REGEXP,
        array('options'=>array('regexp'=>'/^([A-Za-z0-9-_=]+\.){2}[A-Za-z0-9-_=]+$/')));
      if($token===false){
        add_settings_error('cprotext',$k,__('Invalid authentication token',CPTX_I18N_DOMAIN),'error');
        $cleanInput[$k]='';
      }else{
        $cleanInput[$k]=$token;
      }
      break;
    case 'hsync':
    case 'notice':
      $cleanInput[$k]=filter_var($input[$k],FILTER_VALIDATE_BOOLEAN);
      break;
    case 'hfontlist':
      $json=filter_var($input['hfontlist']);
      if(!empty($json) && !json_decode($json)){
        add_settings_error('cprotext',$k,__('Malformed font list ',CPTX_I18N_DOMAIN),'error');
        break;
      }
      $cleanInput['hfontlist']=$json;
      break;
    case 'font':
      $cleanInput[$k]=filter_var($input[$k]);
      break;
    case 'msgs':
      $cleanInput[$k]=$input[$k];
      break;
    default:
      break;
    }

  }

  if(empty($cleanInput['authtok'])){
    $cleanInput['hfontlist']='';
    $cleanInput['font']='';
  }

  return $cleanInput;
}

function account_cb(){
  echo __('Get your authentication token from the API tab of',CPTX_I18N_DOMAIN);
  echo ' <a target="_blank" href="https://www.cprotext.com/en/account/profile/">';
  echo __('your account',CPTX_I18N_DOMAIN);
  echo '</a> ';
  echo __('and paste it below to access the cprotext API from this WordPress site:',CPTX_I18N_DOMAIN);
}

function plugin_cb(){
}

function cptx_cb($args){
  $id='cptx_'.$args['name'];
  $name='cprotext['.$args['name'].']';
  $value=$args['value'];

  switch($args['name']){
  case 'authtok':
    $class='';
    if(empty($value)){
      $class='class="redborders"';
    }
    echo '<textarea id="'.$id.'" name="'.$name.'" '.$class.'>';
    echo $value;
    echo '</textarea>';
    echo '<input type="hidden" id="cptx_hauthtok" name="cprotext[hauthtok]" value="'.$value.'"/>';
    break;
  case 'hsync':
    $class='secondary';
    if($value){
      $class.=' redborders';
    }
    submit_button('Synchronize',$class,'cprotext[submit]',false,
      array(
        'id'=>'cptx_sync',
        'value'=>'cptx_fupd'
      )
    );
    echo '<input type="hidden" id="'.$id.'" name="'.$name.'" value="';
    if($value){
      echo '1';
    }else{
      echo '0';
    }
    echo '"/>';
    break;
  case 'font':
    $fontList=json_decode(stripslashes($args['hfontlist']),true);
    echo '<select id="'.$id.'" name="'.$name.'"';
    if(empty($fontList)){
      echo ' disabled="disabled">';
      echo '<option title=".">';
      echo __('no font available',CPTX_I18N_DOMAIN);
      echo '</option>';
    }else{
      if(!$args['hsync']){
        echo ' disabled="disabled"';
      }
      echo '>';
      foreach($fontList as $font){
        echo '<option value="'.$font[0].'"';
        if($font[0]===$value){
          echo ' selected="selected"';
        }
        echo '>'.$font[1].'</option>';
      }
      echo '</select>';
    }
    echo '<input type="hidden" id="cptx_hfontlist" name="cprotext[hfontlist]" value="'.htmlentities($args['hfontlist'],ENT_QUOTES,'UTF-8').'"/>';
    break;
  case 'newfont':
    echo '<input id="'.$id.'" type="file" name="'.$id.'"';
    if(!$args['hsync']){
      echo ' disabled="disabled"';
    }
    echo '/>';
    echo '<div class="progress">';
    echo '<div class="bar"></div>';
    echo '<div class="percent"></div>';
    echo '</div>';
    echo '<div class="status"></div>';
    break;
    echo '<label>';
    echo '<input type="checkbox" value="1" name="'.$name.'"';
    if($value){
      echo ' checked="checked"';
    }
    echo '/>';
    echo __('enabled by default',CPTX_I18N_DOMAIN);
    echo '</label>';
    break;
  case 'notice':
    echo '<label>';
    echo '<input type="checkbox" value="1" name="'.$name.'"';
    if($value){
      echo ' checked="checked"';
    }
    echo '/>';
    echo __('disabled',CPTX_I18N_DOMAIN);
    echo '</label>';
    break;
  default:
    break;
  }
}

function cptx_adminInit(){
  $settings=get_option('cprotext');

  register_setting('cprotext','cprotext','cptx_sanitize');

  $options=array(
    'account'=>array(
      'title'=>'A/ CPROTEXT Account',
      'settings'=>array(
        'authtok'=>'Token',
        'hsync'=>'Import your account settings',
        'font'=>array(
          'title'=>'Default font',
          'args'=>array(
            'hfontlist'=>$settings['hfontlist'],
            'hsync'=>$settings['hsync']
          )
        ),
        'newfont'=>array(
          'title'=>'Upload new font',
          'args'=>array(
            'hsync'=>$settings['hsync']
          )
        )
      )
    ),
    'plugin'=>array(
      'title'=>'B/ Plugin preferences',
      'settings'=>array(
        'notice'=>'Protection notification only visible to web crawlers'
      )
    )
  );

  foreach($options as $section=>$sectionOptions){
    add_settings_section($section,$sectionOptions['title'],$section.'_cb','cprotext');
    foreach($sectionOptions['settings'] as $option=>$title){
      $args=array(
        'name'=>$option,
        'value'=>(isset($settings[$option])?$settings[$option]:null)
      );

      if(is_array($title)){
        $args=array_merge($args,$title['args']);
        $title=$title['title'];
      }

      add_settings_field($option,$title,'cptx_cb','cprotext',$section,$args);
    }
  }
}


?>
