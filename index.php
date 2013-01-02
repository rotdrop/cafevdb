<?php

use CAFEVDB\L;
use CAFEVDB\Config;
use CAFEVDB\Util;

// Check if we are a user
OCP\User::checkLoggedIn();

Config::init();

$group = \OC_AppConfig::getValue('cafevdb', 'usergroup', '');
$user  = OCP\USER::getUser();


if (!OC_Group::inGroup($user, $group)) {
  $tmpl = new OCP\Template( 'cafevdb', 'not-a-member', 'user' );
  return $tmpl->printPage();
}

$expertmode = OCP\Config::getUserValue(OCP\USER::getUser(),'cafevdb', 'expertmode','');
$debugmode  = OCP\Config::getUserValue(OCP\USER::getUser(),'cafevdb', 'debugmode','');
$tooltips   = OCP\Config::getUserValue(OCP\USER::getUser(),'cafevdb', 'tooltips','');
$encrkey    = Config::getEncryptionKey();

$headervisibility = Util::cgiValue('headervisibility', 'expanded');

$jsscript = 'var toolTips = '.($tooltips == 'on' ? 'true' : 'false').';
';
$jsscript .=<<<__EOT__
$(document).ready(function(){
  if (toolTips) {
    $.fn.tipsy.enable();
  } else {
    $.fn.tipsy.disable();
  }
})

var headervisibility = '$headervisibility';
__EOT__;

OCP\App::setActiveNavigationEntry( 'cafevdb' );

OCP\Util::addStyle('cafevdb', 'cafevdb');
OCP\Util::addStyle('cafevdb', 'settings');
OCP\Util::addStyle('cafevdb', 'events');
OCP\Util::addStyle('cafevdb', 'email');

OCP\Util::addScript('cafevdb', 'cafevdb');
OCP\Util::addScript('cafevdb', 'transpose');
OCP\Util::addScript('cafevdb', 'pme-helper');
OCP\Util::addScript('cafevdb', 'email');
OCP\Util::addScript('cafevdb', 'events');
OCP\Util::addScript('cafevdb/3rdparty', 'tinymce/jscripts/tiny_mce/tiny_mce');
OCP\Util::addScript('cafevdb/3rdparty', 'tinymceinit');

/* Special hack to determine if the email-form was requested through the pme-miscinfo button. */
$op = Util::cgiValue('PME_sys_operation');
if ($op == "Em@il") {
  $tmplname = 'email';
} else {
  $tmplname = Util::cgiValue('Template','projects');
}

// Calendar event hacks

OCP\Util::addscript('3rdparty/fullcalendar', 'fullcalendar');
OCP\Util::addscript('3rdparty/timepicker', 'jquery.ui.timepicker');
OCP\Util::addStyle('3rdparty/timepicker', 'jquery.ui.timepicker');
OCP\Util::addscript('', 'jquery.multiselect');
OCP\Util::addStyle('', 'jquery.multiselect');
OCP\Util::addscript('contacts','jquery.multi-autocomplete');
OC_Util::addScript('','oc-vcategories');
OCP\Util::addScript('cafevdb', 'calendar');
$categories = json_encode(OC_Calendar_App::getCategoryOptions());

$jsscript .= "
var eventSources = '';
var categories = '$categories';
var missing_field = '".addslashes(L::t('Missing or invalid fields'))."';
var missing_field_title = '".addslashes(L::t('Title'))."';
var missing_field_calendar = '".addslashes(L::t('Calendar'))."';
var missing_field_fromdate = '".addslashes(L::t('From Date'))."';
var missing_field_fromtime = '".addslashes(L::t('From Time'))."';
var missing_field_todate = '".addslashes(L::t('To Date'))."';
var missing_field_totime = '".addslashes(L::t('To Time'))."';
var missing_field_startsbeforeends = '".addslashes(L::t('The event ends before it starts'))."';
var missing_field_dberror = '".addslashes(L::t('There was a database fail'))."';
var confirm_text = new Object();
confirm_text['delete'] = '".addslashes(L::t('Do you really want to delete this event?'))."';
confirm_text['detach'] = '".addslashes(L::t('Do you really want to detach this event from the current project?'))."';
confirm_text['select'] = '';
confirm_text['deselect'] = '';
";

// end event hacks


$buttons = array();
$buttons['expert'] =
  array('name' => 'Expert Operations',
        'title' => 'Expert Operations like recreating views etc.',
        'image' => OCP\Util::imagePath('core', 'actions/rename.svg'),
        'class' => 'settings expert',
        'id' => 'expertbutton');
if ($expertmode != 'on') {
  $buttons['expert']['style'] = 'display:none;';
}
$buttons['settings'] =
  array('name' => 'Settings',
        'title' => 'Personal Settings.',
        'image' => OCP\Util::imagePath('core', 'actions/settings.svg'),
        'class' => 'settings generalsettings',
        'id' => 'settingsbutton');

$viewbutton = array(
  'viewtoggle' => array('name' => 'Toggle Visibility',
                        'title' => 'Minimize or maximize the containing block.',
                        'image' => OCP\Util::imagePath('core', 'actions/delete.svg'),
                        'class' => 'viewtoggle',
                        'id' => 'viewtoggle'));

$tmpl = new OCP\Template( 'cafevdb', $tmplname, 'user' );

$tmpl->assign('debugmode', $debugmode);
$tmpl->assign('expertmode', $expertmode);
$tmpl->assign('tooltips', $tooltips);
$tmpl->assign('encryptionkey', $encrkey);
$tmpl->assign('jsscript', $jsscript, false);
$tmpl->assign('settingscontrols', $buttons);
$tmpl->assign('viewtoggle', $viewbutton);
$tmpl->assign('uploadMaxFilesize', Util::maxUploadSize(), false);
$tmpl->assign('uploadMaxHumanFilesize',
              OCP\Util::humanFileSize(Util::maxUploadSize()), false);
$tmpl->assign('Locale', Util::getLocale());

$tmpl->printPage();

