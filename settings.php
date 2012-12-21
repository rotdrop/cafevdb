<?php

// Check if we are a user
OCP\User::checkLoggedIn();

CAFEVDB\Config::init();

$l = OC_L10N::get('cafevdb');

$group = \OC_AppConfig::getValue('cafevdb', 'usergroup', '');
$user  = OCP\USER::getUser();

if (!OC_Group::inGroup($user, $group)) {
  $tmpl = new OCP\Template( 'cafevdb', 'not-a-member', 'user' );
  return $tmpl->printPage();
}

$tmpl = new OCP\Template( 'cafevdb', 'settings');

$expertmode  = OCP\Config::getUserValue($user, 'cafevdb','expertmode','');
$tooltips    = OCP\Config::getUserValue($user, 'cafevdb','tooltips','');
$exampletext = OCP\Config::getUserValue($user, 'cafevdb','exampletext','');
$encrkey     = CAFEVDB\Config::getEncryptionKey();

$jsscript = 'var toolTips = '.($tooltips == 'on' ? 'true' : 'false').';
';

$tmpl->assign('expertmode', $expertmode);
$tmpl->assign('tooltips', $tooltips);
$tmpl->assign('encryptionkey', $encrkey);
$tmpl->assign('exampletext', $exampletext);
$tmpl->assign('jsscript', $jsscript);

OCP\Util::addStyle('cafevdb', 'cafevdb');
OCP\Util::addScript("cafevdb", "settings");

$result = $tmpl->printPage();

if (CAFEVDB\Config::encryptionKeyValid() &&
    ($cafevgroup = \OC_AppConfig::getValue('cafevdb', 'usergroup', '')) != '' &&
    OC_SubAdmin::isGroupAccessible($user, $cafevgroup)) {
    
    $tmpl = new OCP\Template( 'cafevdb', 'app-settings');

    $tmpl->assign('dbserver', CAFEVDB\Config::getValue('dbserver'));
    $tmpl->assign('dbname', CAFEVDB\Config::getValue('dbname'));
    $tmpl->assign('dbuser', CAFEVDB\Config::getValue('dbuser'));
    $tmpl->assign('dbpassword', CAFEVDB\Config::getValue('dbpassword'));
    $tmpl->assign('encryptionkey', CAFEVDB\Config::getValue('encryptionkey'));

    $tmpl->assign('calendaruser', CAFEVDB\Config::getSetting('calendaruser', CAFEVDB\Config::getValue('dbuser')));
    $tmpl->assign('concertscalendar', CAFEVDB\Config::getSetting('concertscalendar', $l->t('concerts')));
    $tmpl->assign('rehearsalscalendar', CAFEVDB\Config::getSetting('rehearsalscalendar', $l->t('rehearsals')));
    $tmpl->assign('othercalendar', CAFEVDB\Config::getSetting('othercalendar', $l->t('other')));
    $tmpl->assign('eventduration', CAFEVDB\Config::getSetting('eventduration', '180'));

    $result = $result && $tmpl->printPage();
}

return $result;

?>
));
    $tmpl->assign('concertcalendar',  \OC_AppConfig::getValue('cafevdb', 'concertcalendar', $l->t('concerts')));
    $tmpl->assign('rehearsalcalendar',  \OC_AppConfig::getValue('cafevdb', 'rehearsalcalendar', $l->t('rehearsals')));
    $tmpl->assign('othercalendar',  \OC_AppConfig::getValue('cafevdb', 'othercalendar', $l->t('other')));
    $tmpl->assign('eventduration', \OC_AppConfig::getValue('cafevdb', 'eventduration', 180));

    $result = $result && $tmpl->printPage();
}

return $result;

?>
