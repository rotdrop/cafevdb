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

  \OCP\JSON::checkLoggedIn();
  \OCP\JSON::checkAppEnabled('cafevdb');
  \OCP\JSON::callCheck();
  
  $handle = false;

  try {

    ob_start();
  
    Error::exceptions(true);
  
    Config::init();
    $handle = mySQL::connect(Config::$pmeopts);
  
    $_GET = array();

    $debugText = '';
    $messageText = '';

    $recordId = Util::cgiValue('recordId', -1);
    $projectId = Util::cgiValue('projectId', -1);
    $projectInstruments = Util::cgiValue('projectInstruments', false);

    if (Util::debugMode('request')) {
      $debugText .= '$_POST[] = '.print_r($_POST, true);
    }

    $idPair = ProjectInstruments::fetchIdPair($recordId, $projectId);
    $recordId = $idPair['recordId'];
    $projectId = $idPair['projectId'];

    if ($projectId <= 0) {
      mySQL::close($handle);
      $debugText .= ob_get_contents();
      @ob_end_clean();

      \OCP\JSON::error(
        array(
          'data' => array('error' => L::t("missing arguments"),
                          'message' => L::t("No project id submitted."),
                          'debug' => $debugText)));
      return false;
    }

    // Is it valid?
    $projectName = Projects::fetchName($projectId, $handle);
    if (!is_string($projectName)) {
      mySQL::close($handle);
      $debugText .= ob_get_contents();
      @ob_end_clean();

      \OCP\JSON::error(
        array(
          'data' => array('error' => L::t("invalid project"),
                          'message' => Util::htmlEncode(
                            L::t("There doesn't seem to be a project associated with id %s.",
                                 array($projectId))),
                          'debug' => $debugText)));
      return false;
    }  
  
    // instrument list should be an array
    if ($projectInstruments === false || !is_array($projectInstruments)) {
      mySQL::close($handle);
      $debugText .= ob_get_contents();
      @ob_end_clean();

      \OCP\JSON::error(
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
      mySQL::close($handle);
      $debugText .= ob_get_contents();
      @ob_end_clean();

      \OCP\JSON::error(
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
      mySQL::close($handle);
      $debugText .= ob_get_contents();
      @ob_end_clean();

      \OCP\JSON::error(
        array(
          'data' => array('error' => L::t('data base error'),
                          'message' => L::t('Failed to update in project instrumentation'),
                          'debug' => $debugText.$query)));
      return false;
    } else {
      mySQL::close($handle);
      $debugText .= ob_get_contents();
      @ob_end_clean();

      \OCP\JSON::success(
        array(
          'data' => array(
            'message' => L::t("Changing the instrumentation for project `%s' was probably successful.",
                              array($projectName)),
            'debug' => $debugText)));

      return true;
    }

  } catch (\Exception $e) {

    if ($handle !== false) {
      mySQL::close($handle);
    }
    $debugText .= ob_get_contents();
    @ob_end_clean();

    \OCP\JSON::error(
      array(
        'data' => array(
          'error' => 'exception',
          'message' => L::t('Error, caught an exception'),
          'debug' => $debugText,
          'exception' => $e->getFile().'('.$e->getLine().'): '.$e->getMessage(),
          'trace' => $e->getTraceAsString(),
          'debug' => $debugText)));

    return false;

  }

} // namespace CAFEVDB

?>
