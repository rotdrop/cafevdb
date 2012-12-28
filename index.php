<?php

// Check if we are a user
OCP\User::checkLoggedIn();

CAFEVDB\Config::init();

$group = \OC_AppConfig::getValue('cafevdb', 'usergroup', '');
$user  = OCP\USER::getUser();

use CAFEVDB\L;

if (!OC_Group::inGroup($user, $group)) {
  $tmpl = new OCP\Template( 'cafevdb', 'not-a-member', 'user' );
  return $tmpl->printPage();
}

$expertmode = OCP\Config::getUserValue(OCP\USER::getUser(),'cafevdb', 'expertmode','');
$debugmode  = OCP\Config::getUserValue(OCP\USER::getUser(),'cafevdb', 'debugmode','');
$tooltips   = OCP\Config::getUserValue(OCP\USER::getUser(),'cafevdb', 'tooltips','');
$encrkey    = CAFEVDB\Config::getEncryptionKey();

$jsscript = 'var toolTips = '.($tooltips == 'on' ? 'true' : 'false').';
';
$jsscript .=<<<__EOT__
  if (toolTips) {
    $.fn.tipsy.enable();
  } else {
    $.fn.tipsy.disable();
  }

__EOT__;

OCP\App::setActiveNavigationEntry( 'cafevdb' );

OCP\Util::addStyle('cafevdb', 'cafevdb');
OCP\Util::addStyle('cafevdb', 'settings');
OCP\Util::addStyle('cafevdb', 'events');
OCP\Util::addStyle('cafevdb', 'email');

OCP\Util::addScript('cafevdb', 'cafevdb');
OCP\Util::addScript('cafevdb', 'transpose');
OCP\Util::addScript('cafevdb', 'pme-helper');
OCP\Util::addScript('cafevdb', 'events');
OCP\Util::addScript('cafevdb', 'tinymce/jscripts/tiny_mce/tiny_mce');
OCP\Util::addScript('cafevdb', 'tinymceinit');

/* Special hack to determine if the email-form was requested through the pme-miscinfo button. */
$op = CAFEVDB\Util::cgiValue('PME_sys_operation');
if ($op == "Em@il") {
  $tmplname = 'email';
} else {
  $tmplname = CAFEVDB\Util::cgiValue('Template','projects');
}

// Calendar event hacks

OCP\Util::addscript('3rdparty/fullcalendar', 'fullcalendar');
OCP\Util::addscript('3rdparty/timepicker', 'jquery.ui.timepicker');
OCP\Util::addStyle('3rdparty/timepicker', 'jquery.ui.timepicker');
OCP\Util::addscript('', 'jquery.multiselect');
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


$tmpl = new OCP\Template( 'cafevdb', $tmplname, 'user' );

$tmpl->assign('debugmode', $debugmode);
$tmpl->assign('expertmode', $expertmode);
$tmpl->assign('tooltips', $tooltips);
$tmpl->assign('encryptionkey', $encrkey);
$tmpl->assign('jsscript', $jsscript, false);

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

$tmpl->assign('settingscontrols', $buttons);

$tmpl->printPage();

