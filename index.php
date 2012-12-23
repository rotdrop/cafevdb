<?php

// Check if we are a user
OCP\User::checkLoggedIn();

CAFEVDB\Config::init();

$group = \OC_AppConfig::getValue('cafevdb', 'usergroup', '');
$user  = OCP\USER::getUser();

$l = OC_L10N::get('cafevdb');
trim($l->t('blah')); /* necessary, but why? */

if (!OC_Group::inGroup($user, $group)) {
  $tmpl = new OCP\Template( 'cafevdb', 'not-a-member', 'user' );
  return $tmpl->printPage();
}

CAFEVDB\Events::unregister(9, 106);

$expertmode = OCP\Config::getUserValue(OCP\USER::getUser(),'cafevdb','expertmode','');
$tooltips   = OCP\Config::getUserValue(OCP\USER::getUser(),'cafevdb','tooltips','');
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
OCP\Util::addStyle('cafevdb', 'events');
OCP\Util::addStyle('cafevdb', 'email');
OCP\Util::addStyle('cafevdb', 'jscal/jscal2');
OCP\Util::addStyle('cafevdb', 'jscal/border-radius');
OCP\Util::addStyle('cafevdb', 'jscal/gold/gold');

OCP\Util::addScript('cafevdb', 'cafevdb');
OCP\Util::addScript('cafevdb', 'tinymce/jscripts/tiny_mce/tiny_mce');
OCP\Util::addScript('cafevdb', 'tinymceinit');
OCP\Util::addScript('cafevdb', 'jscal/src/js/jscal2');
OCP\Util::addScript('cafevdb', 'jscal/src/js/lang/en');

/* Special hack to determine if the email-form was requested through the pme-miscinfo button. */
$op = CAFEVDB\Util::cgiValue('PME_sys_operation');
if ($op == "Em@il") {
  $tmplname = 'email';
} else {
  $tmplname = CAFEVDB\Util::cgiValue('Template','projects');
}

$tmpl = new OCP\Template( 'cafevdb', $tmplname, 'user' );

// Calendar event hacks

OC_Util::addScript('','oc-vcategories');
OCP\Util::addscript('3rdparty/fullcalendar', 'fullcalendar');
OCP\Util::addStyle('3rdparty/fullcalendar', 'fullcalendar');
OCP\Util::addscript('3rdparty/timepicker', 'jquery.ui.timepicker');
OCP\Util::addscript('', 'jquery.multiselect');
OCP\Util::addscript('contacts','jquery.multi-autocomplete');
OCP\Util::addScript('cafevdb', 'calendar');
$categories = json_encode(OC_Calendar_App::getCategoryOptions());

$jsscript .= "
var eventSources = '';
var categories = '$categories';
var missing_field = '".addslashes($l->t('Missing or invalid fields'))."';
var missing_field_title = '".addslashes($l->t('Title'))."';
var missing_field_calendar = '".addslashes($l->t('Calendar'))."';
var missing_field_fromdate = '".addslashes($l->t('From Date'))."';
var missing_field_fromtime = '".addslashes($l->t('From Time'))."';
var missing_field_todate = '".addslashes($l->t('To Date'))."';
var missing_field_totime = '".addslashes($l->t('To Time'))."';
var missing_field_startsbeforeends = '".addslashes($l->t('The event ends before it starts'))."';
var missing_field_dberror = '".addslashes($l->t('There was a database fail'))."';
";

// end event hacks

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

