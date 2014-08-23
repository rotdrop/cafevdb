<?php
/**
 * Copyright (c) 2011-2014 Claus-Justus Heine <himself@claus-justus-heine.de>
 *
 * This file is licensed under the Affero General Public License version 3 or later.
 * See the COPYING-README file.
 *
 * BUG: this file is just too long.
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