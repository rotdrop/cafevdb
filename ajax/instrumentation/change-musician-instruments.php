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

/**@file
 *
 * The purpose of this AJAX callback is validate that a change of a
 * musician's instrument list makes sense. This is a "project-mode"
 * thing, i.e. we do not allow to remove the instrument the musician
 * is supposed to play in the current project. Besides that the new
 * list of instruments is compared with the list of known
 * instruments. If it is a subset, the musician's data-record is
 * updated. Note that we only take the current project into account;
 * it is "legal" to remove instruments the musician formerly was able
 * to play. Don't know whether this makes sense, but so what.
 *
 */

namespace CAFEVDB {

  \OCP\JSON::checkLoggedIn();
  \OCP\JSON::checkAppEnabled('cafevdb');
  \OCP\JSON::callCheck();

  $handle = false;

  Error::exceptions(true);

  try {

    ob_start();

    Config::init();
    $handle = mySQL::connect(Config::$pmeopts);

    $_GET = array();

    $debugText = '';
    $messageText = '';
    $notice ='';

    $recordId = Util::cgiValue('recordId', -1); // Index into Besetzungen
    $projectId = Util::cgiValue('projectId', -1);
    $musicianInstruments = Util::cgiValue('instrumentValues', false);

    if ($musicianInstruments === '') {
      $musicianInstruments = array(); // this is legal.
    }

    if (Util::debugMode('request')) {
      $debugText .= '$_POST[] = '.print_r($_POST, true);
    }

    $musRow = Instrumentation::fetchMusicianData($recordId, $projectId, $handle);
    if ($musRow === false) {
      mySQL::close($handle);
      $debugText .= ob_get_contents();
      @ob_end_clean();

      \OCP\JSON::error(
        array(
          'data' => array('error' => L::t('data base error'),
                          'message' => L::t('Failed to fetch musician\'s data'),
                          'debug' => $debugText)));
      return false;
    }

    /* fetch all known instruments to check for valid instrument names
     * and verify the new project instruments against the known names
     */
    $allInstruments = Instruments::fetchInfo($handle);
    $allInstrumentIds = array_keys($allInstruments['byId']);
    $instrumentDiff = array_diff($musicianInstruments, $allInstrumentIds);
    if (count($instrumentDiff) != 0) {
      mySQL::close($handle);
      $debugText .= ob_get_contents();
      @ob_end_clean();

      $clearText = array();
      foreach($instrumentDiff as $id) {
        $clearText[] = $allInstruments['byId'][$id];
      }

      \OCP\JSON::error(
        array(
          'data' => array('error' => L::t('invalid arguments'),
                          'message' => L::t('Unknown instruments in list: %s',
                                            array(implode(', ', $clearText))),
                          'debug' => $debugText)));
      return false;
    }

    $musicianId = $musRow['Id'];
    $oldMusicianInstruments = Util::explode(',', $musRow['MusicianInstrumentIds']);
    $projectInstruments = Util::explode(',', $musRow['ProjectInstrumentIds']);
    $numOld = count(array_intersect($projectInstruments, $oldMusicianInstruments));
    $numNew = count(array_intersect($projectInstruments, $musicianInstruments));

    if (!empty($projectInstruments)) {
      if ($numOld > $numNew) {
        /* We disallow removing the current project instrument. Does not
         * seem to make much sense ...
         */

        mySQL::close($handle);
        $debugText .= ob_get_contents();
        @ob_end_clean();

        \OCP\JSON::error(
          array(
            'data' => array('error' => L::t('invalid arguments'),
                            'instruments' => $oldMusicianInstruments,
                            'message' => L::t('Attempt to remove the instrument the musician is supposed to play.'),
                            'debug' => $debugText)));
        return false;
      }


      if ($numNew === 0) {
        $clearText = array();
        foreach($projectInstruments as $id) {
          $clearText[] = $allInstruments['byId'][$id];
        }
        // Auto-add?
        $notice = L::t("Please consider to add the registered project instrument `%s' to %s's ".
                       "list of instruments (or possibly change the project instrument).",
                       array(implode(',', $clearText), $musRow['Vorname']));
      }
    }

    mySQL::close($handle);
    $debugText .= ob_get_contents();
    @ob_end_clean();

    \OCP\JSON::success(
      array(
        'data' => array(
          'instruments' => $musicianInstruments,
          'message' => ($notice == ''
                        ? '' // don't annoy the user with success messages.
                        : L::t("Changing the instrument list for the musician `%s' was probably successful.",
                               array($musRow['Vorname'].' '.$musRow['Name']))),
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

} // namespace

?>
