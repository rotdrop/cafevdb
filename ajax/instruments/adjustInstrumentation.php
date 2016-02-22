<?php
/* Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2014, 2016 Claus-Justus Heine <himself@claus-justus-heine.de>
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

  try {

    Error::exceptions(true);

    Config::init();

    $debugText = '$_POST[] = '.print_r($_POST, true);

    //throw new \Exception(print_r($_POST, true));

    // We only need the project-id
    $projectId = Util::cgiValue('ProjectId', -1);

    // Is it there?
    if ($projectId < 0) {
      \OCP\JSON::error(
        array(
          'data' => array('error' => L::t("missing arguments"),
                          'message' => Util::htmlEncode(L::t("No project id submitted.")),
                          'debug' => $debugText)));
      return false;
    }

    $handle = mySQL::connect(Config::$pmeopts);

    // Is it valid?
    $projectName = Projects::fetchName($projectId, $handle);
    if (!is_string($projectName)) {
      mySQL::close($handle);
      \OCP\JSON::error(
        array(
          'data' => array('error' => L::t("invalid project"),
                          'message' => Util::htmlEncode(
                            L::t("There doesn't seem to be a project associated with id %s.",
                                 array($projectId))),
                          'debug' => $debugText)));
      return false;
    }

    // Got it. Now adjust the instruments
    $result = Instruments::updateProjectInstrumentationFromMusicians($projectId, false, $handle);
    if ($result === false) {
      mySQL::close($handle);
      \OCP\JSON::error(
        array(
          'data' => array('error' => L::t("operation failed"),
                          'message' => L::t("Adjusting the instrumentation for project ``%s'' probably failed.",
                                            array($projectName)),
                          'debug' => $debugText)));
      return false;
    }

    mySQL::close($handle);

    \OCP\JSON::success(
      array(
        'data' => array(
          'message' => L::t("Adjusting the instrumentation for project `%s' was probably successful.",
                            array($projectName)),
          'debug' => $debugText)));
    return true;

  } catch (\Exception $e) {

    \OCP\JSON::error(
      array(
        'data' => array(
          'error' => 'exception',
          'exception' => $e->getFile().'('.$e->getLine().'): '.$e->getMessage(),
          'trace' => $e->getTraceAsString(),
          'message' => L::t('Error, caught an exception'))));
    return false;
  }

} // namespace CAFEVDB

?>
