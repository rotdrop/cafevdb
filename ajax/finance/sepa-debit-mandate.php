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

use CAFEVDB\L;
use CAFEVDB\Config;
use CAFEVDB\Util;
use CAFEVDB\Error;
use CAFEVDB\Projects;
use CAFEVDB\Musicians;
use CAFEVDB\Finance;

if(!OCP\User::isLoggedIn()) {
	die('<script type="text/javascript">document.location = oc_webroot;</script>');
}

OCP\JSON::checkAppEnabled(Config::APP_NAME);
OCP\JSON::checkAppEnabled('calendar');

try {

  Error::exceptions(true);
  
  Config::init();

  $debugmode = Config::getUserValue('debugmode', '') == 'on';
  $debugtext = $debugmode ? '<PRE>'.print_r($_POST, true).'</PRE>' : '';
  $msg = '';

  $projectId   = Util::cgiValue('ProjectId', -1);
  $projectName = Util::cgiValue('ProjectName', '');
  $musicianId   = Util::cgiValue('MusicianId', -1);
  $musicianName = Util::cgiValue('MusicianName', '');

  if ($projectId < 0 ||
      ($projectName == '' &&
       ($projectName = Projects::fetchName($projectId)) == '')) {
    OCP\JSON::error(
      array(
        'data' => array('error' => 'arguments',
                        'message' => L::t('Project-id and/or name not set'),
                        'debug' => $debugtext)));
    return false;
  }

  if ($musicianId < 0 ||
      ($musicianName == '' &&
       ($musicianName = Musicians::fetchName($musicianId)) === false)) {
    OCP\JSON::error(
      array(
        'data' => array('error' => 'arguments',
                        'message' => L::t('Musician-id and/or name not set'),
                        'debug' => $debugtext)));
    return false;
  }
  if (is_array($musicianName)) {
    $musicianName = $musicianName['lastName'].', '.$musicianName['firstName'];
  }

  // check for an existing mandate, otherwise generate a new Id.
  $mandate = Finance::fetchSepaMandate($projectId, $musicianId);
  if ($mandate === false) {
    $ref = Finance::generateSepaMandateReference($projectId, $musicianId);
    $members = Config::getSetting('memberTable', L::t('ClubMembers'));
    $nonrecurring = $projectName !== $members;
    $mandate = array('id' => -1,
                     'mandateReference' => $ref,
                     'mandateDate' => '',
                     'lastUsedDate' =>'',
                     'musicianId' => $musicianId,
                     'projectId' => $projectId,
                     'nonrecurring' => $nonrecurring,
                     'IBAN' => '',
                     'BIC' => '',
                     'BLZ' => '',
                     'bankAccountOwner' => $musicianName);
  }

  $tmpl = new OCP\Template('cafevdb', 'sepa-debit-mandate');
  
  $tmpl->assign('ProjectName', $projectName);
  $tmpl->assign('ProjectId', $projectId);
  $tmpl->assign('MusicianName', $musicianName);
  $tmpl->assign('MusicianId', $musicianId);
  $tmpl->assign('CSSClass', 'sepadebitmandate');

  $tmpl->assign('mandateId', $mandate['id']);
  $tmpl->assign('mandateReference', $mandate['mandateReference']);
  $tmpl->assign('mandateDate', $mandate['mandateDate']);
  $tmpl->assign('lastUsedDate', $mandate['lastUsedDate']);
  $tmpl->assign('nonrecurring', $mandate['nonrecurring']);

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

  OCP\JSON::success(
    array('data' => array('contents' => $html,
                          'projectId' => $projectId,
                          'projectName' => $projectName,
                          'musicianId' => $musicianId,
                          'musicianName' => $musicianName,
                          'mandateReference' => $mandate['mandateReference'],
                          'mandateId' => $mandate['id'],
                          'debug' => $debugtext)));

  return true;

} catch (\Exception $e) {
  OCP\JSON::error(
    array(
      'data' => array(
        'error' => 'exception',
        'exception' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
        'message' => L::t('Error, caught an exception'))));
  return false;
}

?>
