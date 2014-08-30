<?php

use CAFEVDB\L;
use CAFEVDB\Config;
use CAFEVDB\Util;
use CAFEVDB\Error;
use CAFEVDB\Projects;
use CAFEVDB\Instruments;
use CAFEVDB\mySQL;


\OCP\JSON::checkLoggedIn();
\OCP\JSON::checkAppEnabled('cafevdb');
\OCP\JSON::callCheck();

try {

  Error::exceptions(true);
  
  Config::init();

  unset($_GET);

  $debugText = '';
  $messageText = '';

  $projectId = Util::cgiValue('projectId', -1);
  $projectInstruments = Util::cgiValue('projectInstruments', false);

  if (Util::debugMode('request')) {
    $debugText .= '$_POST[] = '.print_r($_POST, true);
  }
  
  if ($projectId < 0) {
    OCP\JSON::error(
      array(
        'data' => array('error' => L::t("missing arguments"),
                        'message' => L::t("No project id submitted."),
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
  
  // instrument list should be an array
  if ($projectInstruments === false || !is_array($projectInstruments)) {
    OCP\JSON::error(
      array(
        'data' => array('error' => L::t('invalid arguments'),
                        'message' => L::t('No instrument list submitted'),
                        'debug' => $debugText)));
    return false;
  }

  // fetch all known instruments to check for valid instrument names
  // and verify the new project instruments against the known names
  $allInstruments = Instruments::fetch($handle);
  $instrumentDiff = array_diff($projectInstruments, $allInstruments);
  if (count($instrumentDiff) != 0) {
    OCP\JSON::error(
      array(
        'data' => array('error' => L::t('invalid arguments'),
                        'message' => L::t('Unknown instruments in list: %s',
                                          array(explode(', ', $instrumentDiff))),
                        'debug' => $debugText)));
    return false;
  }
  
  // ok, we have a valid project, a valid intrument list, let it go    
  $query = "UPDATE `Projekte` SET `Besetzung`='".implode(',',$projectInstruments)."' WHERE `Id` = $projectId";
  if (mySQL::query($query, $handle) === false) {
    OCP\JSON::error(
      array(
        'data' => array('error' => L::t('data base error'),
                        'message' => L::t('Failed to update in project instrumentation'),
                        'debug' => $debugText)));
    return false;
  } else {
    OCP\JSON::success(
      array(
        'data' => array(
          'message' => L::t("Changing the instrumentation for project `%s' was probably successful.",
                            array($projectName)),
          'debug' => $debugText)));

    return true;
  }

} catch (\Exception $e) {

  // For whatever reason we need to entify quotes, otherwise jquery throws an error.
  OCP\JSON::error(
    array(
      'data' => array(
        'error' => 'exception',
        'message' => L::t('Error, caught an exception'),
        'debug' => $debugText,
        'exception' => $e->getMessage(),
        'trace' => $e->getTraceAsString())));
  return false;

}

?>
