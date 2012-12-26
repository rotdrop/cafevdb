<?php

use CAFEVDB\L;
use CAFEVDB\Config;

// Check if we are a user and the needed apps are enabled.
OCP\User::checkLoggedIn();
OCP\JSON::checkAppEnabled('cafevdb');
OCP\JSON::checkAppEnabled('calendar');

Config::init();

$group = \OC_AppConfig::getValue('cafevdb', 'usergroup', '');
$user  = OCP\USER::getUser();

if (!OC_Group::inGroup($user, $group)) {
  $tmpl = new OCP\Template( 'cafevdb', 'not-a-member', 'user' );
  return $tmpl->printPage();
}

$tmpl = new OCP\Template( 'cafevdb', 'settings');

$tooltips    = OCP\Config::getUserValue($user, 'cafevdb', 'tooltips','');
$expertmode  = OCP\Config::getUserValue($user, 'cafevdb', 'expertmode','');
$debugmode   = OCP\Config::getUserValue($user, 'cafevdb', 'debugmode','');
$exampletext = OCP\Config::getUserValue($user, 'cafevdb', 'exampletext','');
$encrkey     = Config::getEncryptionKey();

$jsscript = 'var toolTips = '.($tooltips == 'on' ? 'true' : 'false').';
';

$tmpl->assign('debugmode', $debugmode);
$tmpl->assign('expertmode', $expertmode);
$tmpl->assign('tooltips', $tooltips);
$tmpl->assign('encryptionkey', $encrkey);
$tmpl->assign('exampletext', $exampletext);
$tmpl->assign('jsscript', $jsscript);
$tmpl->assign('adminsettings', false);

OCP\Util::addStyle('cafevdb', 'cafevdb');
OCP\Util::addStyle('cafevdb', 'settings');
OCP\Util::addScript("cafevdb", "settings");

if (Config::encryptionKeyValid() &&
    ($cafevgroup = \OC_AppConfig::getValue('cafevdb', 'usergroup', '')) != '' &&
    OC_SubAdmin::isGroupAccessible($user, $cafevgroup)) {

  $tmpl->assign('adminsettings', true);

  $tmpl->assign('dbserver', Config::getValue('dbserver'));
  $tmpl->assign('dbname', Config::getValue('dbname'));
  $tmpl->assign('dbuser', Config::getValue('dbuser'));
  $tmpl->assign('dbpassword', Config::getValue('dbpassword'));
  $tmpl->assign('encryptionkey', Config::getValue('encryptionkey'));

  $tmpl->assign('shareowner', Config::getSetting('shareowner', ''));
  $tmpl->assign('concertscalendar', Config::getSetting('concertscalendar', L::t('concerts')));
  $tmpl->assign('rehearsalscalendar', Config::getSetting('rehearsalscalendar', L::t('rehearsals')));
  $tmpl->assign('othercalendar', Config::getSetting('othercalendar', L::t('other')));
  $tmpl->assign('managementcalendar', Config::getSetting('managementcalendar', L::t('management')));
  $tmpl->assign('eventduration', Config::getSetting('eventduration', '180'));  
}

$result = $tmpl->printPage();

return $result;

?>
