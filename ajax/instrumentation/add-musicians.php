<?php
/* Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2015 Claus-Justus Heine <himself@claus-justus-heine.de>
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

namespace CAFEVDB {

  \OCP\JSON::checkLoggedIn();
  \OCP\JSON::checkAppEnabled('cafevdb');
  \OCP\JSON::callCheck();

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
    $musicianId = Util::cgiValue('MusicianId', -1);

    $musicianIds = array();
    if ($musicianId !== -1) {
      $musicianIds[] = $musicianId;
    } else {
      $pmepfx       = Config::$pmeopts['cgi']['prefix']['sys'];
      $musicianKeys = $pmepfx.'mrecs';
      $musicianIds  = Util::cgiValue($musicianKeys, array());
    }
    $numRecords   = count($musicianIds);

    if ($numRecords == 0) {
      $debugText .= ob_get_contents();
      @ob_end_clean();

      \OCP\JSON::error(
        array(
          'data' => array('error' => L::t('Invalid Argument'),
                          'message' => L::t('Missing Musician Id'),
                          'debug' => $debugText)));
      return false;
    }

    if ($projectId == -1) {
      $debugText .= ob_get_contents();
      @ob_end_clean();

      \OCP\JSON::error(
        array(
          'data' => array('error' => L::t('Invalid Argument'),
                          'message' => L::t('Missing Project Id'),
                          'debug' => $debugText)));
      return false;
    }

    $result = Instrumentation::addMusicians($musicianIds, $projectId);
    if ($result === false) {
      $debugText .= ob_get_contents();
      @ob_end_clean();

      \OCP\JSON::error(
        array(
          'data' => array('error' => L::t('Data Base Error'),
                          'message' => L::t('Early setup error.'),
                          'debug' => $debugText)));
      return false;
    }

    $failedMusicians = $result['failed'];
    $addedMusicians  = $result['added'];

    if ($numRecords == count($failedMusicians)) {
      $debugText .= ob_get_contents();
      @ob_end_clean();

      $message = L::t('No musician could be added to the projecti, #failures: %d.',
                      count($failedMusicians));

      foreach ($failedMusicians as $failure) {
        $message .= ' '.$failure['notice'].' '.'SQL-error: '.$faliure['sqlerror'];
      }

      \OCP\JSON::error(
        array(
          'data' => array('caption' => L::t('Operation failed'),
                          'message' => $message,
                          'debug' => $debugText)));
      return false;
    } else {
      $debugText .= ob_get_contents();
      @ob_end_clean();

      $notice = '';
      foreach ($addedMusicians as $newItem) {
        $notice .= $newItem['notice'];
      }

      \OCP\JSON::success(
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

    $debugText .= ob_get_contents();
    @ob_end_clean();

    $exceptionText = $e->getFile().'('.$e->getLine().'): '.$e->getMessage();
    $trace = $e->getTraceAsString();

    $admin = Config::adminContact();

    $mailto = $admin['email'].
      '?subject='.rawurlencode('[CAFEVDB-Exception] Exception while adding musicians').
      '&body='.rawurlencode($exceptionText."\r\n".$trace);
    $mailto = '<span class="error email"><a href="mailto:'.$mailto.'">'.$admin['name'].'</a></span>';

    \OCP\JSON::error(
      array(
        'data' => array(
          'caption' => L::t('PHP Exception Caught'),
          'error' => 'exception',
          'exception' => $exceptionText,
          'trace' => $trace,
          'message' => L::t('Error, caught an exception. '.
                            'Please copy the displayed text and send it by email to %s.',
                            array($mailto)),
          'debug' => htmlspecialchars($debugText))));

    return false;

  }

} // namespace

?>
