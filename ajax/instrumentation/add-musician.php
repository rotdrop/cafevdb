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

/**@file
 *
 * Add one new musician to a project. This is in principle a very
 * simple "INSERT INTO" query.
 *
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
  
$handle = false;

try {

  ob_start();
  
  Error::exceptions(true);
  
  $_GET = array();

  $debugText = '';
  $messageText = '';
  $notice ='';

  $musicianId = Util::cgiValue('musicianId', -1);
  $projectId = Util::cgiValue('projectId', -1);

  $musiciansIds = array();
  $pmepfx       = $this->opts['cgi']['prefix']['sys'];
  $musiciansKey = $pmepfx.'mrecs';

  if ($musicianId == -1) {
    $debugText .= ob_get_contents();
    @ob_end_clean();

    OCP\JSON::error(
      array(
        'data' => array('error' => L::t('Invalid Argument'),
                        'message' => L::t('Missing Musician Id'),
                        'debug' => $debugText)));
    return false;
  }
  
  if ($projectId == -1) {
    $debugText .= ob_get_contents();
    @ob_end_clean();

    OCP\JSON::error(
      array(
        'data' => array('error' => L::t('Invalid Argument'),
                        'message' => L::t('Missing Project Id'),
                        'debug' => $debugText)));
    return false;
  }

  Config::init();
  $handle = mySQL::connect(Config::$pmeopts);  

  $musRow = Instrumentation::fetchMusicianData($musicianId, $projectId, $handle);
  if ($musRow === false) {
    mySQL::close($handle);
    $debugText .= ob_get_contents();
    @ob_end_clean();

    OCP\JSON::error(
      array(
        'data' => array('error' => L::t('Data Base Error'),
                        'message' => L::t('Failed to fetch musician\'s data'),
                        'debug' => $debugText)));
    return false;
  }
  $musInstruments = $musRow['Instrumente'];

  $instrumentation = Projects::fetchInstrumentation($projectId, $handle);
  if ($instrumentation === false) {
    mySQL::close($handle);
    $debugText .= ob_get_contents();
    @ob_end_clean();

    OCP\JSON::error(
      array(
        'data' => array('error' => L::t('Data Base Error'),
                        'message' => L::t('Failed to fetch the project\'s instrumentation'),
                        'debug' => $debugText)));
    return false;
  }

  if (!is_array($instrumentation)) {
    $instrumentation = array();
  }
  if (!is_array($musInstruments)) {
    $musInstruments = array();
  }

  $both = array_intersect($instrumentation, $musInstruments);
  if (!empty($both)) {
    $musInstrument = $both[0];
  } else if (!empty($musInstruments)) {
    $musInstrument = $musInstruments[0];
    $notice = L::t("None of the instruments known by %s are mentioned in the "
                   ."instrumentation-list for the project. "
                   ."The musician is added nevertheless to the project with the instrument `%s'",
                   array($musrow['Vorname']." ".$musrow['Name'], $musInstrument));
  } else {
    $musInstrument = null;
    $notice = L::t("The musician %s doesn't seem to play any instrument ...",
                   array($musrow['Vorname']." ".$musrow['Name']));
  }

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
        'exception' => $e->getFile().'('.$e->getLine().'): '.$e->getMessage(),
        'trace' => $e->getTraceAsString(),
        'debug' => $debugText)));

  return false;

}

?>
