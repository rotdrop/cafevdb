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
 * Be nasty to the average DAU and refuse to add duplicate musicians.
 */
namespace CAFEVDB {

  \OCP\JSON::checkLoggedIn();
  \OCP\JSON::checkAppEnabled('cafevdb');
  \OCP\JSON::callCheck();

  Error::exceptions(true);
  $debugText = '';

  ob_start();

  try {
    $_GET = array(); // only post is allowed

    if (Util::debugMode('request')) {
      $debugText .= '$_POST[] = '.print_r($_POST, true);
    }

    Config::init();

    $dataPfx = Config::$pmeopts['cgi']['prefix']['data'];

    $surName   = Util::cgiValue($dataPfx.'Name', '');
    $firstName = Util::cgiValue($dataPfx.'Vorname', '');

    $musicians = Musicians::musiciansByName($firstName, $surName);

    $duplicates = array();
    $duplicateNames = '';
    foreach ($musicians as $record) {
      $duplicateNames .= $record['Vorname'].' '.$record['Name']." (Id = ".$record['Id'].")"."\n";
      $duplicates[$record['Id']] = $record['Vorname'].' '.$record['Name'];
    }
    $message = '';
    if (count($duplicates) > 0) {
      $message =
        L::t('Musician(s) with the same first and sur-name already exist:').
        "\n".
        $duplicateNames.
        "\n".
        L::t(
'Please do not add duplicates to the data-base. If you do, then others
will have to clean up after you.

Please continue if you know what you are doing. Please use the search
facilities before adding new musicians. Thanks.');
    }

    \OCP\JSON::success(
      array('data' => array('message' => nl2br($message),
                            'duplicates' => $duplicates,
                            'debugText' => $debugText)));

    return true;

  } catch (\Exception $e) {

    $debugText .= ob_get_contents();
    @ob_end_clean();

    $exceptionText = $e->getFile().'('.$e->getLine().'): '.$e->getMessage();
    $trace = $e->getTraceAsString();

    $admin = Config::adminContact();

    $mailto = $admin['email'].
      '?subject='.rawurlencode('[CAFEVDB-Exception] Exceptions from Musician Form').
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

}

?>