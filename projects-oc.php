<?php

// Check if we are a user
OCP\User::checkLoggedIn();

$somesetting = OCP\Config::getSystemValue( "somesetting", '' );

OCP\App::setActiveNavigationEntry( 'cafevdb' );

//OCP\Util::addScript('cafevdb', 'cafevdb');
OCP\Util::addStyle('cafevdb', 'cafevdb');

$tmplname = isset( $_GET['Template'] ) ? $_GET['Template'] : 'projects';

$tmpl = new OCP\Template( 'cafevdb', $tmplname, 'user' );

$tmpl->assign( 'somesetting', $somesetting );

$tmpl->printPage();
