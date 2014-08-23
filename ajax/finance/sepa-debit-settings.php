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

$projectId  = Util::cgiValue('ProjectId');
$musicianId = Util::cgiValue('MusicianId');
$reference  = Util::cgiValue('mandateReference');

$projectName = Projects::fetchName($projectId);
$members = Config::getSetting('memberTable', L::t('ClubMembers'));
$nonrecurring = $projectName !== $members;

$IBAN = Util::cgiValue('bankAccountIBAN');
$BLZ  = Util::cgiValue('bankAccountBLZ');
$BIC  = Util::cgiValue('bankAccountBIC');

$changed = Util::cgiValue('changed');
$value = Util::cgiValue($changed);

switch ($changed) {
case 'bankAccountIBAN':
  if ($value == '') {
    $IBAN = '';
    break;
  }
  $value = preg_replace('/\s+/', '', $value); // eliminate space
  $iban = new \IBAN($value);
  if (!$iban->Verify() && is_numeric($value)) {
    // maybe simply the bank account number, if we have a BLZ,
    // then compute the IBAN
    $blz = $BLZ;
    $bav = new \malkusch\bav\BAV;
    if ($bav->isValidBank($blz)) {
      $value = Finance::makeIBAN($blz, $value);
    }
  }
  $iban = new \IBAN($value);
  if ($iban->Verify()) {
    $value = $iban->MachineFormat();
    $IBAN = $value;
    
    // Compute as well the BLZ and the BIC
    $blz = $iban->Bank();
    $bav = new \malkusch\bav\BAV;
    if ($bav->isValidBank($blz)) {
      $BLZ = $blz;
      $BIX = $bav->getMainAgency($blz)->getBIC();
    }
  } else {
    $message = L::t("Invalid IBAN: `%s'.", array($value));
    $suggestion = '';
    $suggestions = $iban->MistranscriptionSuggestions();
    $intro = L::t("Perhaps you meant");
    while (count($suggestions) > 0) {
      $alternative = array_shift($suggestions);
      if ($iban->Verify($alternative)) {
        $alternative = $iban->MachineFormat($alternative);
        $alternative = $iban->HumanFormat($alternative);
        $suggestion .= $intro . " `".$alternative."'";
        $into = L::t("or");
      }
    }
    OC_JSON::error(
      array("data" => array('message' => $message,
                            'suggestion' => $suggestion)));
    return false;
  }
  break;
case 'bankAccountBLZ':
  if ($value == '') {
    $BLZ = '';
    break;
  }
  $value = preg_replace('/\s+/', '', $value);
  $bav = new \malkusch\bav\BAV;
  if ($bav->isValidBank($value)) {
    // set also the BIC
    $BLZ = $value;
    $agency = $bav->getMainAgency($value);
    $bic = $agency->getBIC();
    if (Finance::validateSWIFT($bic)) {
      $BIC = $bic;
    }
  } else {
    OC_JSON::error(
      array("data" => array(
              'message' => L::t('Value for `%s\' invalid: `%s\'.',
                                array($changed, $value)),
              'suggestion' => '')));
    return false;
  }
  break;
case 'bankAccountBIC':
  if ($value == '') {
    $BIC = '';
    break;
  }
  $value = preg_replace('/\s+/', '', $value);
  if (!Finance::validateSWIFT($value)) {
    // maybe a BLZ
    $bav = new \malkusch\bav\BAV;
    if ($bav->isValidBank($value)) {
      $BLZ = $value;
      $agency = $bav->getMainAgency($value);
      $value = $agency->getBIC();
      // Set also the BLZ
    }
  }
  if (Finance::validateSWIFT($value)) {
    $BIC = $value;
  } else {
    OC_JSON::error(
      array("data" => array(
              'message' => L::t('Value for `%s\' invalid: `%s\'.',
                                array($changed, $value)),
              'suggestion' => '')));
    return false;
  }
  break;
}

// If we reach here then the bank-account stuff survived the
// consistency checks.

$mandate = array('mandateReference' => $reference,
                 'musicianId' => $musicianId,
                 'projectId' => $projectId,
                 'nonrecurring' => $nonrecurring ? 1 : 0,
                 'IBAN' => $IBAN,
                 'BIC' => $BIC);

switch ($changed) {
case 'bankAccountOwner':
case 'mandateDate':
case 'lastUsedDate':
  $value = Util::cgiValue($changed);
  $mandate[$changed] = $value;
  break;
}

if (Finance::storeSepaMandate($mandate)) {
  OC_JSON::success(
    array("data" => array(
            'message' => L::t('Value for `%s\' set to `%s\'.', array($changed, $value)).print_r($_POST, true),
            'value' => $value,
            'iban' => $IBAN,
            'blz' => $BLZ,
            'bic' => $BIC)));
  return true;  
} else {
  OC_JSON::error(
    array("data" => array(
            'message' => L::t('Failed setting `%s\' to `%s\'.',
                              array($changed, $value)),
            'suggestion' => '')));
  return false;
}

OC_JSON::error(
  array("data" => array(
          "message" => L::t("Unhandled request:")." ".print_r($_POST, true))));

return false;

?>