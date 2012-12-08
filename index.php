<?php

// Check if we are a user
OCP\User::checkLoggedIn();

$somesetting = OCP\Config::getSystemValue( "somesetting", '' );

OCP\App::setActiveNavigationEntry( 'cafevdb' );

//OCP\Util::addScript('cafevdb', 'cafevdb');
OCP\Util::addStyle('cafevdb', 'cafevdb');
OCP\Util::addStyle('cafevdb', 'email');

/* Special hack to determine if the email-form was requested through the pme-miscinfo button. */
$op = CAFEVDB\Util::cgiValue('PME_sys_operation');
if ($op == "Em@il") {
  $tmplname = 'email';
} else {
  $tmplname = CAFEVDB\Util::cgiValue('Template','projects');
}

$tmpl = new OCP\Template( 'cafevdb', $tmplname, 'user' );

$tmpl->assign( 'somesetting', $somesetting );

$tmpl->printPage();

