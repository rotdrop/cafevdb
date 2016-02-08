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

  \OCP\JSON::checkLoggedIn();
  \OCP\JSON::checkAppEnabled('cafevdb');
  \OCP\JSON::callCheck();

  Error::exceptions(true);
  $debugText = '';

  ob_start();

  try {

    $_GET = array(); // disable GET

    $requiredKeys = array('ProjectId', 'MusicianId', 'mandateReference');
    foreach ($requiredKeys as $required) {
      if (!Util::cgiValue($required, null, false)) {
        \OC_JSON::error(
          array("data" => array(
                  "message" => L::t("Required information `%s' not provided.", array($required)).print_r($_POST, true))));
        return false;
      }
    }

    $musicianId = Util::cgiValue('MusicianId');
    $projectId = Util::cgiValue('ProjectId');
    $reference = Util::cgiValue('mandateReference');

    if (Finance::deleteSepaMandate($reference)) {
      \OC_JSON::success(
        array("data" => array(
                'message' => L::t('SEPA debit mandate deleted from data-base.'))));
      return true;
    } else {
      \OC_JSON::error(
        array("data" => array(
                'message' => L::t('Unable to delete SEPA debit mandate from data-base.'))));
      return false;
    }
  } catch (\Exception $e) {
    $debugText .= ob_get_contents();
    @ob_end_clean();

    $exceptionText = $e->getFile().'('.$e->getLine().'): '.$e->getMessage();
    $trace = $e->getTraceAsString();

    $admin = Config::adminContact();

    $mailto = $admin['email'].
      '?subject='.rawurlencode('[CAFEVDB-Exception] Exceptions deleting SEPA mandate').
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