<?php

// Init owncloud

use CAFEVDB\L;
use CAFEVDB\Util;
use CAFEVDB\Config;

// Check if we are a user
OCP\JSON::checkLoggedIn();
OCP\JSON::checkAppEnabled('cafevdb');
OCP\JSON::callCheck();

$_GET = array();

$debugModes = Util::cgiValue('debugModes', array());

foreach ($debugModes as $value) {
  Config::$debug['value'] = true;
}

$debug = implode(',', $debugModes);

Config::setUserValue('debug', $debug);

OCP\JSON::success(
  array(
    'data' => array( 'message' => L::t("Debug-modes changed to `%s'", array($debug)))));
return true;

?>
