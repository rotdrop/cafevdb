<?php

// Check if we are a user
OCP\User::checkLoggedIn();

CAFEVDB\Config::init();

$group = \OC_AppConfig::getValue('cafevdb', 'usergroup', '');
$user  = OCP\USER::getUser();

$l = OC_L10N::get('cafevdb');

if (!OC_Group::inGroup($user, $group)) {
  $tmpl = new OCP\Template( 'cafevdb', 'not-a-member', 'user' );
  return $tmpl->printPage();
}

$expertmode = OCP\Config::getUserValue(OCP\USER::getUser(),'cafevdb','expertmode','');
$tooltips   = OCP\Config::getUserValue(OCP\USER::getUser(),'cafevdb','tooltips','');
$encrkey    = CAFEVDB\Config::getEncryptionKey();

$jsscript = 'var toolTips = '.($tooltips == 'on' ? 'true' : 'false').';
';

OCP\App::setActiveNavigationEntry( 'cafevdb' );

OCP\Util::addStyle('cafevdb', 'cafevdb');
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

$tmpl->assign('expertmode', $expertmode);
$tmpl->assign('tooltips', $tooltips);
$tmpl->assign('encryptionkey', $encrkey);
$tmpl->assign('jsscript', $jsscript);

// Calendar event hacks

OCP\Util::addScript('calendar', 'calendar');
OCP\Util::addscript('3rdparty/fullcalendar', 'fullcalendar');
OCP\Util::addStyle('3rdparty/fullcalendar', 'fullcalendar');
OCP\Util::addscript('3rdparty/timepicker', 'jquery.ui.timepicker');
OCP\Util::addscript('', 'jquery.multiselect');
OCP\Util::addscript('contacts','jquery.multi-autocomplete');
$categories = OC_Calendar_App::getCategoryOptions();
$tmpl->assign('categories', $categories, false);

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

$tmpl->assign('settingscontrols', $buttons);

$tmpl->printPage();

