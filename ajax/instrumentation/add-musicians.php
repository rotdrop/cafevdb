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
use CAFEVDB\Musicians;
use CAFEVDB\Instruments;
use CAFEVDB\Instrumentation;
use CAFEVDB\mySQL;


\OCP\JSON::checkLoggedIn();
\OCP\JSON::checkAppEnabled('cafevdb');
\OCP\JSON::callCheck();
  
$handle = false;

Error::exceptions(true);

ob_start();

try {
  $_GET = array();

  Config::init();

  $debugText = '';
  $messageText = '';
  $notice ='';

  $projectId = Util::cgiValue('ProjectId', -1);
  $projectName = Util::cgiValue('ProjectName', -1);
  $musicianId = Util::cgiValue('MusicianId', false);

  $musiciansIds = array();
  if ($musicianId !== false) {
    $musiciansIds[] = $musicianId;
  } else {
    $pmepfx       = Config::$pmeopts['cgi']['prefix']['sys'];
    $musiciansKey = $pmepfx.'mrecs';
    $musiciansIds = Util::cgiValue($musiciansKey, array());
  }
  $numRecords   = count($musiciansIds);

  if ($numRecords == 0) {
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

  $projectInstruments = Projects::fetchInstrumentation($projectId, $handle);
  if ($projectInstruments === false) {
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

  $failedMusicians = array();
  $addedMusicians = array();
  foreach ($musiciansIds as $musicianId) {
    $musRow = Musicians::fetchMusicianPersonalData($musicianId, $handle);
    if ($musRow === false) {
      $failedMusicians[] = array('id' => $musicianId,
                                 'caption' => L::t('Data Base error'),
                                 'message' => L::t('Unable to fetch musician\'s personal information for id %d, data-base error: %s',
                                                   array($musicianId, mySQL::error())));
      continue;
    }
    $musInstruments = explode(',', $musRow['Instrumente']);

    $fullName = $musRow['Vorname']." ".$musRow['Name'];

    $both = array_intersect($projectInstruments, $musInstruments);
    if (!empty($both)) {
      $musInstrument = $both[0];
    } else if (!empty($musInstruments)) {
      $musInstrument = $musInstruments[0];
      $notice .= L::t("None of the instruments known by %s are mentioned in the "
                      ."instrumentation-list for the project. "
                      ."The musician is added nevertheless to the project with the instrument `%s'",
                      array($fullName, $musInstrument));
    } else {
      $musInstrument = null;
      $notice .= L::t("The musician %s doesn't seem to play any instrument ...",
                      array($fullName));
    }

    $query = "INSERT INTO `Besetzungen` (`MusikerId`,`ProjektId`,`Instrument`)
 VALUES ('$musicianId','$projectId','$musInstrument')";

    $instrumentationId = -1;
    if (mySQL::query($query, $handle) === false) {
      $failedMusicians[] = array('id' => $musicianId,
                                 'caption' => L::t('Adding %s (id = %d) failed.',
                                                   array($fullName, $musicianId)),
                                 'message' => mySQL::error());
      continue;
    }
    $numRows = mySQL::changedRows($handle);
    $instrumentationId = mySQL::newestIndex($handle);
    if ($instrumentationId === false || $instrumentationId === 0) {
      $failedMusicians[] = array('id' => $musicianId,
                                 'caption' => L::t('Unable to get the new id for %s (id = %d)',
                                                   array($fullName, $musicianId)),
                                 'message' => mySQL::error());
      continue;
    }

    $addedMusicians[] = array('musicianId' => $musicianId,
                              'rows' => $numRows,
                              'instrumentationId' => $instrumentationId);
  }
  
  if ($numRecords == count($failedMusicians)) {
    mySQL::close($handle);
    $debugText .= ob_get_contents();
    @ob_end_clean();
    
    $message = L::t('No musician could be added to the projecti, #failures: %d.',
                    count($failedMusicians));

    foreach ($failedMusicians as $failure) {
      $message .= ' '.$failure['caption'].' '.print_r($failure['message'], true);
    }

    OCP\JSON::error(
      array(
        'data' => array('caption' => L::t('Operation failed'),
                        'message' => $message,
                        'debug' => $debugText)));
    return false;
  } else {
    OCP\JSON::success(
      array(
        'data' => array(
          'musicians' => $addedMusicians,
          'message' => ($notice == ''
                        ? '' // don't annoy the user with success messages.
                        : L::t("Operation succeeded with the following notifications:")),
          'notice' => $notice,
          'debug' => $debugText)));
    return true;
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
