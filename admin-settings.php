<?php

OCP\User::checkAdminUser();

OCP\Util::addStyle('cafevdb', 'cafevdb');
OCP\Util::addScript( "cafevdb", "admin-settings" );

$tmpl = new OCP\Template( 'cafevdb', 'admin-settings');

$strict = !CAFEVDB\Config::encryptionKeyValid();

$tmpl->assign('usergroup', CAFEVDB\Config::getValue('usergroup', $strict));
$tmpl->assign('dbserver', CAFEVDB\Config::getValue('dbserver', $strict));
$tmpl->assign('dbname', CAFEVDB\Config::getValue('dbname', $strict));
$tmpl->assign('dbuser', CAFEVDB\Config::getValue('dbuser', $strict));
$tmpl->assign('dbpassword', CAFEVDB\Config::getValue('dbpassword', $strict));
$tmpl->assign('encryptionkey', CAFEVDB\Config::getValue('encryptionkey', $strict));

return $tmpl->fetchPage();
