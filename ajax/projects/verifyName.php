<?php

use CAFEVDB\L;
use CAFEVDB\Config;
use CAFEVDB\Util;
use CAFEVDB\Error;
use CAFEVDB\Projects;

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
      $message .= L::t("The %s must not be empty.\n", array($subject));
    }
  }

  //trigger_error(print_r($_POST, true), E_USER_NOTICE);

  $control     = Util::cgiValue("control", "name");
  $projectId   = Util::cgiValue(Config::$pmeopts['cgi']['prefix']['sys']."rec");
  $projectName = isset($projectValues['Name']) ? $projectValues['Name'] : "";
  $projectYear = isset($projectValues['Jahr']) ? $projectValues['Jahr'] : "";

  $errorMessage = "";
  $infoMessage = "";
  switch ($control) {
  case "submit":
  case "name":
    // No whitespace, s.v.p., and CamelCase
    $whiteSpace = false;
    $projectName = preg_replace_callback('/\s+(\S)(\S*)(\s*$)?/', function ($matches) {
        $whiteSpace = true;
        return strtoupper($matches[1]).$matches[2];
      }, $projectName);
    if ($whiteSpace) {
      $infoMessage .= "White-space has been removed from the project-name.";
    }
    $matches = array();
    // Get the year from the name, if set
    if (preg_match('/^(.*\D)?(\d{4})$/', $projectName, $matches) == 1) {
      $projectName = $matches[1];
      if ($control != "submit") {
        // the year-control wins when submitting the form
        $projectYear = $matches[2];
      }
      if ($projectName == "") {
        $errorMessage .= L::t("The project-name must not only consist of the year-number.");
      }
    } else if ($projectName == "") {
      $errorMessage .= L::t("No project-name given.");
    }
  case "year":
    if ($projectYear == "") {
      $errorMessage .= L::t("No project-year given.");
    } else if (preg_match('/^\d{4}$/', $projectYear) !== 1) {
      $errorMessage .= L::t("The project-year has to consist of four digits, e.g. ``1984''.");
    } else {
      // Strip the year from the name and replace with the given year
      $projectName = preg_replace('/\d+$/', "", $projectName);
      $projectName .= $projectYear;
    }
    // Project name may be empty at this point. Why not
    break;
  default:
    $errorMessage = L::t("Internal error: unknown request");
    break;
  }
  if ($errorMessage != '') {
    OCP\JSON::error(
      array(
        'data' => array('error' => L::t("missing arguments"),
                        'message' => Util::entifyString($errorMessage),
                        'debug' => $debugText)));
    return false;
  }

  // Now check also that no project of the same name exists (in this year)
  $yearProjects = Projects::fetchProjects(false, true);
  foreach ($yearProjects as $id => $nameYear) {
    if ($id != $projectId && $nameYear['Name'] == $projectName && $nameYear['Jahr'] == $projectYear) {
      OCP\JSON::error(
        array(
          'data' => array('error' => L::t("already existent"),
                          'message' => Util::entifyString(
                            L::t("A project with the name ``%s'' already exists in the year %s.\n".
                                 "Please choose a different name or year.",
                                 array($projectName,
                                       $projectYear))),
                          'debug' => $debugText)));
      return false;
    }
  }

  OCP\JSON::success(
    array('data' => array('projectYear' => $projectYear,
                          'projectName' => $projectName,
                          'message' => $infoMessage,
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
        'message' => entifyString(L::t('Error, caught an exception'), ENT_QUOTES|ENT_XHTML, 'UTF-8'))));
  return false;
}

?>
