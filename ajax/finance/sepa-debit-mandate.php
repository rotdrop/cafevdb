<?php
/* Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016 Claus-Justus Heine <himself@claus-justus-heine.de>
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

    Config::init();

    if (Util::debugMode('request')) {
      $debugText .= '$_POST = '.print_r($_POST, true);
    }

    $reference          = Util::cgiValue('MandateReference', false);
    $expired            = (bool)Util::cgiValue('MandateExpired', false);
    //throw new \Exception('expired: '.' '.(int)$expired.' '.(bool)$expired.' '.(string)$expired);
    $projectId          = Util::cgiValue('ProjectId', -1);
    $mandateProjectId   = Util::cgiValue('MandateProjectId', -1);
    $projectName        = Util::cgiValue('ProjectName', '');
    $mandateProjectName = Util::cgiValue('MandateProjectName', '');
    $musicianId         = Util::cgiValue('MusicianId', -1);
    $musicianName       = Util::cgiValue('MusicianName', '');

    if ($projectId < 0 ||
        ($projectName == '' &&
         ($projectName = Projects::fetchName($projectId)) == '')) {

      $debugText .= ob_get_contents();
      @ob_end_clean();

      \OCP\JSON::error(
        array(
          'data' => array('error' => 'arguments',
                          'message' => L::t('Project-id and/or name not set'),
                          'debug' => $debugText)));
      return false;
    }

    if ($mandateProjectId < 0 ||
        ($mandateProjectName == '' &&
         ($mandateProjectName = Projects::fetchName($mandateProjectId)) == '')) {

      $debugText .= ob_get_contents();
      @ob_end_clean();

      \OCP\JSON::error(
        array(
          'data' => array('error' => 'arguments',
                          'message' => L::t('Mandate project-id and/or name not set'),
                          'debug' => $debugText)));
      return false;
    }

    Config::init();
    $handle = mySQL::connect(Config::$pmeopts);

    if ($musicianId < 0 ||
        ($musicianName == '' &&
         ($musicianName = Musicians::fetchName($musicianId, $handle)) === false)) {

      $debugText .= ob_get_contents();
      @ob_end_clean();

      \OCP\JSON::error(
        array(
          'data' => array('error' => 'arguments',
                          'message' => L::t('Musician-id and/or name not set'),
                          'debug' => $debugText)));
      mySQL::close($handle);
      return false;
    }
    if (is_array($musicianName)) {
      $musicianName = $musicianName['lastName'].', '.$musicianName['firstName'];
    }

    // check for an existing mandate, otherwise generate a new Id.
    $mandate = Finance::fetchSepaMandate($mandateProjectId, $musicianId, $handle, true, $expired);
    if ($mandate === false) {
      $ref = Finance::generateSepaMandateReference($projectId, $musicianId, false, $handle);
      $members = Config::getSetting('memberTable', L::t('ClubMembers'));
      $sequenceType = 'permanent'; //$projectName !== $members ? 'once' : 'permanent';
      $mandate = array('id' => -1,
                       'mandateReference' => $ref,
                       'mandateDate' => '01-'.date('m-Y'),
                       'lastUsedDate' =>'',
                       'musicianId' => $musicianId,
                       'projectId' => $mandateProjectId,
                       'sequenceType' => $sequenceType,
                       'IBAN' => '',
                       'BIC' => '',
                       'BLZ' => '',
                       'bankAccountOwner' => Finance::sepaTranslit($musicianName));
    } else {
      $usage = Finance::mandateReferenceUsage($mandate['mandateReference'], true, $handle);
      !empty($usage['LastUsed']) && $mandate['lastUsedDate'] = $usage['LastUsed'];
      //error_log(print_r($usage, true));
    }

    mySQL::close($handle);

    $tmpl = new \OCP\Template('cafevdb', 'sepa-debit-mandate');

    $tmpl->assign('ProjectName', $projectName);
    $tmpl->assign('ProjectId', $projectId);
    $tmpl->assign('MandateProjectId', $mandateProjectId);
    $tmpl->assign('MusicianName', $musicianName);
    $tmpl->assign('MusicianId', $musicianId);

    $tmpl->assign('CSSClass', 'sepadebitmandate');

    $tmpl->assign('mandateId', $mandate['id']);
    $tmpl->assign('mandateReference', $mandate['mandateReference']);
    $tmpl->assign('mandateExpired', $expired);
    $tmpl->assign('mandateDate', date('d.m.Y', strtotime($mandate['mandateDate'])));
    if ($mandate['lastUsedDate']) {
      $tmpl->assign('lastUsedDate', date('d.m.Y', strtotime($mandate['lastUsedDate'])));
    } else {
      $tmpl->assign('lastUsedDate', '');
    }
    $tmpl->assign('sequenceType', $mandate['sequenceType']);

    $tmpl->assign('bankAccountOwner', $mandate['bankAccountOwner']);

    // If we have a valid IBAN, compute BLZ and BIC
    $iban = $mandate['IBAN'];
    $blz  = '';
    $bic  = $mandate['BIC'];
    $tmpl->assign('bankAccountIBAN', $iban);

    $iban = new \IBAN($iban);
    if ($iban->Verify()) {
      $blz = $iban->Bank();
      $bav = new \malkusch\bav\BAV;
      if ($bav->isValidBank($blz)) {
        $bic = $bav->getMainAgency($blz)->getBIC();
      }
    }
    $tmpl->assign('bankAccountBLZ', $blz);
    $tmpl->assign('bankAccountBIC', $bic);

    $html = $tmpl->fetchPage();

    \OCP\JSON::success(
      array('data' => array('contents' => $html,
                            'projectId' => $projectId,
                            'projectName' => $projectName,
                            'musicianId' => $musicianId,
                            'musicianName' => $musicianName,
                            'mandateReference' => $mandate['mandateReference'],
                            'mandateId' => $mandate['id'],
                            'debug' => $debugText)));

    return true;

  } catch (\Exception $e) {
    $debugText .= ob_get_contents();
    @ob_end_clean();

    $exceptionText = $e->getFile().'('.$e->getLine().'): '.$e->getMessage();
    $trace = $e->getTraceAsString();

    $admin = Config::adminContact();

    $mailto = $admin['email'].
      '?subject='.rawurlencode('[CAFEVDB-Exception] Exceptions from Email-Form').
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

} // namespace CAFEVDB

?>
