<?php

// Init owncloud

use CAFEVDB\L;
use CAFEVDB\Config;
use CAFEVDB\Util;

// Check if we are a user
OCP\JSON::checkLoggedIn();
OCP\JSON::checkAppEnabled('cafevdb');
OCP\JSON::callCheck();

$_GET = array();

$expertmode = Util::cgiValue('expertmode', 'off');
Config::setUserValue('expertmode', $expertmode);
OCP\JSON::success(array('data' => array('message' => L::t("Expertmode changed to `%s'",
                                                          array($expertmode)) )));
return true;

?>
