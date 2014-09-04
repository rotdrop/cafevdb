<?php
/**Orchestra member, musician and project management application.
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

/**@file
 *
 * The purpose of this AJAX callback is validate that a change of a
 * musician's project instrument. The submitted data is compared
 * against the list of known instruments. A warning will be issued if
 * the project-instrument is not contained in the list of instruments
 * known by the musician. Etc.
 */

use CAFEVDB\L;
use CAFEVDB\Config;
use CAFEVDB\Util;
use CAFEVDB\Error;
use CAFEVDB\Projects;
use CAFEVDB\Instruments;
use CAFEVDB\Instrumentation;
use CAFEVDB\mySQL;


\OCP\JSON::checkLoggedIn();
\OCP\JSON::checkAppEnabled('cafevdb');
\OCP\JSON::callCheck();
  
try {

  ob_start();
  
  Error::exceptions(true);
  
  $_GET = array();

  $debugText = '';
  $messageText = '';
  $notice ='';

  $recordId = Util::cgiValue('recordId', -1); // Index into Besetzungen
  $projectId = Util::cgiValue('projectId', -1);
  $projectInstrument = Util::cgiValue('instrumentValues', false);
  
  if (Util::debugMode('request')) {
    $debugText .= '$_POST[] = '.print_r($_POST, true);
  }

  $musRow = Instrumentation::fetchMusicianData($recordId, $projectId);
  if ($musRow === false) {
    $debugText .= ob_get_contents();
    @ob_end_clean();

    OCP\JSON::error(
      array(
        'data' => array('error' => L::t('data base error'),
                        'message' => L::t('Failed to fetch musician\'s data'),
                        'debug' => $debugText)));
    return false;
  }

  $musicianId = $musRow['Id'];
  $musicianInstruments = explode(',', $musRow['Instrumente']);
  $oldProjectInstrument = $musRow['ProjektInstrument'];
  $haveOld = array_search($oldProjectInstrument, $musicianInstruments) !== false;
  $haveNew = array_search($projectInstrument, $musicianInstruments) !== false;  

  if (!$haveNew) {
    // Auto-add?
    $notice = L::t("Please consider to add the registered project instrument `%s' to %s's ".
                   "list of instruments (or possibly change the project instrument).",
                   array($projectInstrument, $musRow['Vorname']));
  }
  
  $debugText .= ob_get_contents();
  @ob_end_clean();

  OCP\JSON::success(
    array(
      'data' => array(
        'message' => L::t("Instrument choice for %s seems ok.",
                          array($musRow['Vorname'].' '.$musRow['Name'])),
        'notice' => $notice,
        'debug' => $debugText)));
  
  return true;

} catch (\Exception $e) {

  if ($handle !== false) {
    mySQL::close($handle);
  }
  $debugText .= ob_get_contents();
  @ob_end_clean();

  // For whatever reason we need to entify quotes, otherwise jquery throws an error.
  OCP\JSON::error(
    array(
      'data' => array(
        'error' => 'exception',
        'message' => L::t('Error, caught an exception'),
        'debug' => $debugText,
        'exception' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
        'debug' => $debugText)));

  return false;

}

?>
