<?php

// Check if we are a user
OCP\User::checkLoggedIn();

OCP\Util::addScript( "cafevdb", "settings" );

$tmpl = new OCP\Template( 'cafevdb', 'settings');

$tmpl->assign('expertmode', OCP\Config::getUserValue( "expertmode", '' ));

return $tmpl->printPage();
