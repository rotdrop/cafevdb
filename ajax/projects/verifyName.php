<?php

use CAFEVDB\L;
use CAFEVDB\Config;
use CAFEVDB\Util;
use CAFEVDB\Error;

if(!OCP\User::isLoggedIn()) {
	die('<script type="text/javascript">document.location = oc_webroot;</script>');
}

OCP\JSON::checkAppEnabled(Config::APP_NAME);

try {

  Error::exceptions(true);
  
  Config::init();

  $debugText = print_r($_POST, true);

  // We simply expect to get the entire project form here.
  $projectValues = Util::getPrefixCGIData();

  $required = array('Jahr' => L::t("project-year"), 'Name' => L::t("project-name"));
  
  $message = "";
  foreach ($required as $key => $subject) {
    if (!isset($projectValues[$key]) || $projectValues[$key] == "") {
      $message .= L::t("Error: the %s must not be empty.\n", array($subject));
    }
  }
  if ($message != '') {
    OCP\JSON::error(
      array(
        'data' => array('error' => L::t("missing arguments"),
                        'message' => $message,
                        'debug' => $debugText)));
    return false;
  }
  

  OCP\JSON::success(
    array('data' => array('projectYear' => "2011",
                          'projectName' => "Willi",
                          'debug' => $debugText)));

  return true;

} catch (\Exception $e) {
  OCP\JSON::error(
    array(
      'data' => array(
        'error' => 'exception',
        'exception' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
        'message' => L::t('Error, caught an exception'))));
  return false;
}

?>
