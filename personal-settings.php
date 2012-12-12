<?php

// Check if we are a user
OCP\User::checkLoggedIn();

OCP\Util::addScript( "cafevdb", "personal-settings" );

$tmpl = new OCP\Template( 'cafevdb', 'personal-settings');

$tmpl->assign('expertmode', OCP\Config::getSystemValue( "expertmode", '' ));

return $tmpl->printPage();
