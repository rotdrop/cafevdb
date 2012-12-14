<?php

OCP\User::checkAdminUser();

OCP\Util::addScript( "cafevdb", "admin-settings" );

$tmpl = new OCP\Template( 'cafevdb', 'admin-settings');

$tmpl->assign('CAFEVgroup', OCP\Config::getSystemValue( "CAFEVgroup", '' ));
$tmpl->assign('CAFEVdbserver', OCP\Config::getSystemValue( "CAFEVdbserver", '' ));
$tmpl->assign('CAFEVdbname', OCP\Config::getSystemValue( "CAFEVdbname", '' ));
$tmpl->assign('CAFEVdbuser', OCP\Config::getSystemValue( "CAFEVdbuser", '' ));
$tmpl->assign('CAFEVdbpasswd', OCP\Config::getSystemValue( "CAFEVdbpasswd", '' ));

return $tmpl->fetchPage();
