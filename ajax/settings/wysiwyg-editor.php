<?php

// Init owncloud

use CAFEVDB\L;
use CAFEVDB\Config;
use CAFEVDB\Util;

// Check if we are a user
OCP\JSON::checkLoggedIn();
OCP\JSON::checkAppEnabled('cafevdb');
OCP\JSON::callCheck();

$key = 'wysiwygEditor';
$value = Util::cgiValue($key, 'tinymce');

if (isset(Config::$wysiwygEditors[$value])) {
  Config::setUserValue($key, $value);
  OCP\JSON::success(
    array('data' => array('message' => L::t("Setting `%s' to `%s'", array($key, $value)),
                          'value' => $value)));
  return true;
} else {
  OCP\JSON::error(
    array('data' => array('message' => L::t("Unknown WYSIWYG-Editor: `%s'", array($value)))));
  return false;
}

?>
