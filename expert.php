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

OCP\Util::addStyle('cafevdb', 'cafevdb');
OCP\Util::addStyle('cafevdb', 'tipsy');

if (!OC_Group::inGroup($user, $group)) {
  $tmpl = new OCP\Template( 'cafevdb', 'errorpage', 'user' );
  $tmpl->assign('error', 'notamember');
  return $tmpl->printPage();
}

try {

  Error::exceptions(true);

  $tmpl = new OCP\Template( 'cafevdb', 'expertmode');

  $expertmode = OCP\Config::getUserValue(OCP\USER::getUser(),'cafevdb','expertmode','');
  $tooltips = OCP\Config::getUserValue(OCP\USER::getUser(),'cafevdb','tooltips','');

  $tmpl->assign('expertmode', $expertmode);
  $tmpl->assign('tooltips', $tooltips );

  $links = array('phpmyadmin',
                 'phpmyadminoc',
                 'sourcecode',
                 'sourcedocs',
                 'ownclouddev');
  foreach ($links as $link) {
    $tmpl->assign($link, Config::getValue($link));
  }
  
  return $tmpl->printPage();

} catch (Exception $e) {
  $tmpl = new OCP\Template( 'cafevdb', 'errorpage', 'user' );
  $tmpl->assign('error', 'exception');
  $tmpl->assign('exception', $e->getMessage());
  $tmpl->assign('trace', $e->getTraceAsString());
  $tmpl->assign('debug', true);
  return $tmpl->printPage();
}

?>

