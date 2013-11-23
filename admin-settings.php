<?php

OCP\User::checkAdminUser();

//OCP\Util::addStyle('cafevdb', 'cafevdb');
//OCP\Util::addStyle('cafevdb', 'tipsy');
OCP\Util::addScript( "cafevdb", "admin-settings" );

$tmpl = new OCP\Template( 'cafevdb', 'admin-settings');

CAFEVDB\Config::init();
$strict = !CAFEVDB\Config::encryptionKeyValid();

$tmpl->assign('usergroup', \OC_AppConfig::getValue('cafevdb', 'usergroup', ''));

return $tmpl->fetchPage();
