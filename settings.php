<?php

use CAFEVDB\L;
use CAFEVDB\Util;
use CAFEVDB\Config;
use CAFEVDB\Error;

// Check if we are a user and the needed apps are enabled.
OCP\User::checkLoggedIn();
OCP\JSON::checkAppEnabled('cafevdb');
OCP\JSON::checkAppEnabled('calendar');

Config::init();

$group = \OC_AppConfig::getValue('cafevdb', 'usergroup', '');
$user  = OCP\USER::getUser();

if (!OC_Group::inGroup($user, $group)) {
  $tmpl = new OCP\Template( 'cafevdb', 'errorpage', 'user' );
  $tmpl->assign('error', 'notamember');
  return $tmpl->printPage();
}

try {

  Error::exceptions(true);

  $tmpl = new OCP\Template( 'cafevdb', 'settings');

  $tooltips    = OCP\Config::getUserValue($user, 'cafevdb', 'tooltips','');

  $expertmode  = OCP\Config::getUserValue($user, 'cafevdb', 'expertmode','');
  $debugmode   = OCP\Config::getUserValue($user, 'cafevdb', 'debugmode','');
  $exampletext = OCP\Config::getUserValue($user, 'cafevdb', 'exampletext','');
  $encrkey     = Config::getEncryptionKey();

  $tmpl->assign('debugmode', $debugmode);
  $tmpl->assign('expertmode', $expertmode);
  $tmpl->assign('tooltips', $tooltips);
  $tmpl->assign('encryptionkey', $encrkey);
  $tmpl->assign('exampletext', $exampletext);
  $tmpl->assign('adminsettings', false);

  OCP\Util::addStyle('cafevdb', 'cafevdb');
  OCP\Util::addStyle('cafevdb', 'tipsy');
  OCP\Util::addStyle('cafevdb', 'settings');

  OCP\Util::addStyle("cafevdb/3rdparty", "chosen/chosen");

  if (Config::encryptionKeyValid() &&
      ($cafevgroup = \OC_AppConfig::getValue('cafevdb', 'usergroup', '')) != '' &&
      OC_SubAdmin::isGroupAccessible($user, $cafevgroup)) {

    $admin = true;
    $tmpl->assign('adminsettings', $admin);

    $tmpl->assign('orchestra', Config::getValue('orchestra'));

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

    $tmpl->assign('sharedfolder', Config::getSetting('sharedfolder',''));

    foreach (array('smtp', 'imap') as $proto) {
      foreach (array('server', 'port', 'secure') as $key) {
        $tmpl->assign($proto.$key, Config::getValue($proto.$key));
      }
    }
    foreach (array('user', 'password', 'fromname', 'fromaddress', 'testaddress', 'testmode') as $key) {
      $tmpl->assign('email'.$key, Config::getValue('email'.$key));
    }

    $links = array('phpmyadmin',
                   'phpmyadminoc',
                   'sourcecode',
                   'sourcedocs',
                   'ownclouddev');
    foreach ($links as $link) {
      $tmpl->assign($link, Config::getValue($link));
    }
  }

  $result = $tmpl->printPage();

  return $result;

} catch (Exception $e) {
  $tmpl = new OCP\Template( 'cafevdb', 'errorpage', 'user' );
  $tmpl->assign('error', 'exception');
  $tmpl->assign('exception', $e->getMessage());
  $tmpl->assign('trace', $e->getTraceAsString());
  $tmpl->assign('debug', true);
  return $tmpl->printPage();
}

?>
