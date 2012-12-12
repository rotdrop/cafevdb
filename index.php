<?php

// Check if we are a user
OCP\User::checkLoggedIn();

$expertmode = OCP\Config::getUserValue(OCP\USER::getUser(),'cafevdb','expertmode','');

OCP\App::setActiveNavigationEntry( 'cafevdb' );

OCP\Util::addStyle('cafevdb', 'cafevdb');
OCP\Util::addStyle('cafevdb', 'email');
OCP\Util::addStyle('cafevdb', 'jscal/jscal2');
OCP\Util::addStyle('cafevdb', 'jscal/border-radius');
OCP\Util::addStyle('cafevdb', 'jscal/gold/gold');

OCP\Util::addScript('cafevdb', 'cafevdb');
OCP\Util::addScript('cafevdb', 'tinymce/jscripts/tiny_mce/tiny_mce');
OCP\Util::addScript('cafevdb', 'tinymceinit');
OCP\Util::addScript('cafevdb', 'jscal/src/js/jscal2');
OCP\Util::addScript('cafevdb', 'jscal/src/js/lang/en');

/* Special hack to determine if the email-form was requested through the pme-miscinfo button. */
$op = CAFEVDB\Util::cgiValue('PME_sys_operation');
if ($op == "Em@il") {
  $tmplname = 'email';
} else {
  $tmplname = CAFEVDB\Util::cgiValue('Template','projects');
}

$tmpl = new OCP\Template( 'cafevdb', $tmplname, 'user' );

$tmpl->assign( 'expertmode', $expertmode );

$tmpl->printPage();

