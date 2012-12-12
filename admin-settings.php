<?php

OCP\User::checkAdminUser();

OCP\Util::addScript( "cafevdb", "admin-settings" );

$tmpl = new OCP\Template( 'cafevdb', 'admin-settings');

$tmpl->assign('dbserver', OCP\Config::getSystemValue( "dbserver", '' ));

return $tmpl->fetchPage();
