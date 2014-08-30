<?php

use CAFEVDB\L;
use CAFEVDB\Config;
use CAFEVDB\Util;
use CAFEVDB\Error;
use CAFEVDB\Projects;
use CAFEVDB\Instruments;
use CAFEVDB\mySQL;

if(!OCP\User::isLoggedIn()) {
	die('<script type="text/javascript">document.location = oc_webroot;</script>');
}

OCP\JSON::checkAppEnabled(Config::APP_NAME);

try {

  Error::exceptions(true);
  
  Config::init();

  $debugText = '$_POST[] = '.print_r($_POST, true);

  // We only need the project-id
  $projectId = Util::cgiValue(Config::$pmeopts['cgi']['prefix']['sys']."rec", -1);
  
  // Is it there?
  if ($projectId < 0) {
    OCP\JSON::error(
      array(
        'data' => array('error' => L::t("missing arguments"),
                        'message' => Util::entifyString(L::t("No project id submitted.")),
                        'debug' => $debugText)));
    return false;
  }

  $handle = mySQL::connect(Config::$pmeopts);

  // Is it valid?
  $projectName = Projects::fetchName($projectId, $handle);
  if (!is_string($projectName)) {
    mySQL::close($handle);
    OCP\JSON::error(
      array(
        'data' => array('error' => L::t("invalid project"),
                        'message' => Util::entifyString(
                          L::t("There doesn't seem to be a project associated with id %s.",
                               array($projectId))),
                        'debug' => $debugText)));
    return false;
  }

  // Got it. Now adjust the instruments
  $instruments =
    Instruments::updateProjectInstrumentationFromMusicians($projectId, $handle, false);
  if (!is_array($instruments)) {
    mySQL::close($handle);
    OCP\JSON::error(
      array(
        'data' => array('error' => L::t("operation failed"),
                        'message' => L::t("Adjusting the instrumentation for project ``%s'' probably failed.",
                                          array($projectName)),
                        'debug' => $debugText)));
    return false;
  }

  mySQL::close($handle);

  OCP\JSON::success(
    array(
      'data' => array(
        'message' => L::t("Adjusting the instrumentation for project `%s' was probably successful.",
                          array($projectName)),
        'debug' => $debugText)));
  return true;

} catch (\Exception $e) {
  // For whatever reason we need to entify quotes, otherwise jquery throws an error.
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
