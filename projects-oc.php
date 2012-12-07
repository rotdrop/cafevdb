<?php

// Check if we are a user
OCP\User::checkLoggedIn();

$somesetting = OCP\Config::getSystemValue( "somesetting", '' );

OCP\App::setActiveNavigationEntry( 'cafevdb' );

//OCP\Util::addScript('cafevdb', 'cafevdb');
OCP\Util::addStyle('cafevdb', 'cafevdb');

$tmpl = new OCP\Template( 'cafevdb', 'projects', 'user' );

$tmpl->assign( 'somesetting', $somesetting );

$tmpl->printPage();
