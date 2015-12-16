<?php
/* Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2014 Claus-Justus Heine <himself@claus-justus-heine.de>
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU GENERAL PUBLIC LICENSE
 * License as published by the Free Software Foundation; either
 * version 3 of the License, or any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU AFFERO GENERAL PUBLIC LICENSE for more details.
 *
 * You should have received a copy of the GNU Lesser General Public
 * License along with this library.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace CAFEVDB {

  if(!\OCP\User::isLoggedIn()) {
    die('<script type="text/javascript">document.location = oc_webroot;</script>');
  }

  \OCP\JSON::checkAppEnabled(Config::APP_NAME);

  $debugText = print_r($_POST, true);

  try {

    ob_start();

    Error::exceptions(true);

    Config::init();

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
    $attachYear  = Util::cgiValue("yearattach") === "on" ? true : false;

    $errorMessage = "";
    $infoMessage = "";
    switch ($control) {
    case "submit":

    case "name":
      // No whitespace, s.v.p., and CamelCase
      $origName = $projectName;
      $projectName = ucwords($projectName);
      $projectName = preg_replace("/[^[:alnum:][:space:]]/u", '', $projectName);
      //$projectName = preg_replace('/\s+/', '', $projectName);
      if ($origName != $projectName) {
        $infoMessage .= L::t("The project name has been simplified.");
      }
      $matches = array();
      // Get the year from the name, if set
      if (preg_match('/^(.*\D)?(\d{4})$/', $projectName, $matches) == 1) {
        $projectName = $matches[1];
        if ($control != "submit" && $attachYear) {
          // the year-control wins when submitting the form
          $projectYear = $matches[2];
        }
        if ($projectName == "") {
          $errorMessage .= L::t("The project-name must not only consist of the year-number.");
        }
      } else if ($projectName == "") {
        $errorMessage .= L::t("No project-name given.");
      }
      if (mb_strlen($projectName) > 20) {
        $errorMessage .= L::t("The project-name is too long, ".
                              "please use something less than 20 characters ".
                              "(excluding the attached year). Thanks");
      }
    case "year":
      if ($projectYear == "") {
        $errorMessage .= L::t("No project-year given.");
      } else if (preg_match('/^\d{4}$/', $projectYear) !== 1) {
        $errorMessage .= L::t("The project-year has to consist of four digits, e.g. ``1984''.");
      } else {
        // Strip the year from the name and replace with the given year
        $projectName = preg_replace('/\d+$/', "", $projectName);
        if ($attachYear) {
          $projectName .= $projectYear;
        }
      }
      // Project name may be empty at this point. Why not
      break;
    default:
      $errorMessage = L::t("Internal error: unknown request");
      break;
    }

    if ($errorMessage != '') {
      $debugText .= ob_get_contents();
      @ob_end_clean();
      \OCP\JSON::error(
        array(
          'data' => array('error' => L::t("missing arguments"),
                          'message' => $errorMessage,
                          'debug' => $debugText)));
      return false;
    }

    // Now check also that no project of the same name exists (in this year)
    $yearProjects = Projects::fetchProjects(false, true);
    foreach ($yearProjects as $id => $nameYear) {
      if ($id != $projectId && $nameYear['Name'] == $projectName && $nameYear['Jahr'] == $projectYear) {
        $debugText .= ob_get_contents();
        @ob_end_clean();
        \OCP\JSON::error(
          array(
            'data' => array('error' => L::t("already existent"),
                            'message' => L::t("A project with the name ``%s'' already exists in the year %s.\n".
                                              "Please choose a different name or year.",
                                              array($projectName,
                                                    $projectYear)),
                            'debug' => $debugText)));
        return false;
      }
    }

    $debugText .= ob_get_contents();
    @ob_end_clean();

    \OCP\JSON::success(
      array('data' => array('projectYear' => $projectYear,
                            'projectName' => $projectName,
                            'message' => $infoMessage,
                            'debug' => $debugText)));

    return true;

  } catch (\Exception $e) {

    $debugText .= ob_get_contents();
    @ob_end_clean();

    \OCP\JSON::error(
      array(
        'data' => array(
          'error' => 'exception',
          'exception' =>  $e->getMessage(),
          'trace' => $e->getTraceAsString(),
          'message' => L::t('Error, caught an exception'),
          'debug' => $debugText)));

    return false;
  }

} // namespace CAFEVDB

?>
