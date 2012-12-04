<?php

OCP\User::checkAdminUser();

OCP\Util::addScript( "cafevdb", "admin" );

$tmpl = new OCP\Template( 'cafevdb', 'settings');

$tmpl->assign('url', OCP\Config::getSystemValue( "somesetting", '' ));

return $tmpl->fetchPage();
