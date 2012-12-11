<?php

OCP\User::checkAdminUser();

OCP\Util::addScript( "cafevdb", "admin" );

$tmpl = new OCP\Template( 'cafevdb', 'admin-settings');

$tmpl->assign('url', OCP\Config::getSystemValue( "mysqlserver", '' ));

return $tmpl->fetchPage();
