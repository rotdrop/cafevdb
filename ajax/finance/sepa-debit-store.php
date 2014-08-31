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

\OCP\JSON::checkLoggedIn();
\OCP\JSON::checkAppEnabled('cafevdb');
\OCP\JSON::callCheck();

use \CAFEVDB\L;
use \CAFEVDB\Config;
use \CAFEVDB\Util;
use \CAFEVDB\Projects;
use \CAFEVDB\Finance;

$_GET = array(); // disable GET

$requiredKeys = array('ProjectId', 'MusicianId', 'mandateReference');
foreach ($requiredKeys as $required) {
  if (!Util::cgiValue($required, null, false)) {
    OC_JSON::error(
      array("data" => array(
              "message" => L::t("Required information `%s' not provided.", array($required)).print_r($_POST, true))));
    return false;
  }
}

// Compose the mandate
$mandate = array('mandateReference' => Util::cgiValue('mandateReference'),
                 'nonrecurring' => Util::cgiValue('nonrecurring'),
                 'musicianId' => Util::cgiValue('MusicianId'),
                 'projectId' => Util::cgiValue('ProjectId'),
                 'mandateDate' => Util::cgiValue('mandateDate'),
                 'lastUsedDate' => Util::cgiValue('lastUsedDate'),
                 'IBAN' => Util::cgiValue('bankAccountIBAN'),
                 'BIC' => Util::cgiValue('bankAccountBIC'),
                 'BLZ' => Util::cgiValue('bankAccountBLZ'),
                 'bankAccountOwner' => Util::cgiValue('bankAccountOwner'));

// Verify IBAN and BIC if non-empty
$IBAN = $mandate['IBAN'];
if ($IBAN != '') {
  $iban = new \IBAN($IBAN);
  if (!$iban->Verify()) {
    OC_JSON::error(
      array("data" => array(
              'message' => L::t('Value for `%s\' invalid: `%s\'.',
                                array('IBAN', $value)),
              'suggestion' => '')));
    return false;
  }
}

$BIC = $mandate['BIC'];
if ($BIC != '' && !Finance::validateSWIFT($BIC)) {
  OC_JSON::error(
    array("data" => array(
            'message' => L::t('Value for `%s\' invalid: `%s\'.',
                              array('BIC', $value)),
            'suggestion' => '')));
  return false;
}

if (Finance::storeSepaMandate($mandate)) {
  OC_JSON::success(
    array("data" => array(
            'message' => L::t('SEPA debit mandate stored in data-base.'))));
  return true;
} else {
  OC_JSON::error(
    array("data" => array(
            'message' => L::t('Unable to store SEPA debit mandate in data-base.'))));
  return false;
}

?>