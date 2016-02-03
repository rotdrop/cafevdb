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
    $_GET = array(); // only post is allowed

    if (Util::debugMode('request')) {
      $debugText .= '$_POST[] = '.print_r($_POST, true);
    }

    $requiredKeys = array('MandateProjectId', 'ProjectId', 'MusicianId', 'mandateReference');
    foreach ($requiredKeys as $required) {
      if (!Util::cgiValue($required, null, false)) {
        $debugText .= ob_get_contents();
        @ob_end_clean();

        \OC_JSON::error(
          array("data" => array(
                  'message' => L::t("Required information `%s' not provided.", array($required)),
                  'suggestions' => '',
                  'debug' => $debugText)));
        return false;
      }
    }

    $projectId  = Util::cgiValue('ProjectId');
    $musicianId = Util::cgiValue('MusicianId');
    $reference  = Util::cgiValue('mandateReference');
    $mandateProjectId  = Util::cgiValue('MandateProjectId');

    $projectName = Projects::fetchName($projectId);
    if ($projectId != $mandateProjectId) {
      $mandateProjectName = Projects::fetchName($mandateProjectId);
    } else {
      $mandateProjectName = $projectName;
    }
    $members = Config::getSetting('memberTableId', L::t('ClubMembers'));

    $sequenceType = 'permanent'; // $projectName !== $members ? 'once' : 'permanent';

    $IBAN = Util::cgiValue('bankAccountIBAN');
    $BLZ  = Util::cgiValue('bankAccountBLZ', '');
    $BIC  = Util::cgiValue('bankAccountBIC');

    $changed = Util::cgiValue('changed');
    $value = Util::cgiValue($changed);

    switch ($changed) {
    case 'orchestraMember':
      // tricky, for now just generate a new reference
      if ($value === 'member') {
        $reference = Finance::generateSepaMandateReference($members, $musicianId);
        $mandateProjectId = $members;
        $mandateProjectName = Projects::fetchName($members);
      } else {
        $reference = Finance::generateSepaMandateReference($projectId, $musicianId);
        $mandateProjectId = $projectId;
        $mandateProjectName = $projectName;
      }
      break;
    case 'lastUsedDate':
      // Store the lastUsedDate immediately, if other fields are disabled
      if (Util::cgiValue('mandateDate', false) === false) {
        $mandate = array('mandateReference' => $reference,
                         'musicianId' => $musicianId,
                         'projectId' => $mandateProjectId,
                         'lastUsedDate' => $value);
        if (!Finance::storeSepaMandate($mandate)) {
          $debugText .= ob_get_contents();
          @ob_end_clean();

          \OC_JSON::error(
            array("data" => array(
                    'message' => L::t('Failed setting `%s\' to `%s\'.',
                                      array($changed, $value)),
                    'suggestions' => '',
                    'debug' => $debugText)));
          return false;
        }
      }
    case 'mandateDate':
      // Whatever the user likes ;)
      // The date-picker does some validation on its own, so just live with it.
      \OC_JSON::success(
        array("data" => array(
                'message' => L::t('Value for `%s\' set to `%s\'.',
                                  array($changed, $value)),
                'suggestions' =>'',
                'value' => $value)));
      return true;
    case 'bankAccountOwner':
      $value = Finance::sepaTranslit($value);
      if (!Finance::validateSepaString($value)) {
        $debugText .= ob_get_contents();
        @ob_end_clean();

        \OC_JSON::error(
          array("data" => array(
                  'message' => L::t("Account owner contains invalid characters: %s",
                                    array($value)),
                  'suggestions' => '',
                  'debug' => $debugText)));
        return false;
      }
      \OC_JSON::success(
        array("data" => array(
                'message' => L::t('Value for `%s\' set to `%s\'.',
                                  array($changed, $value)),
                'suggestions' =>'',
                'value' => $value)));
      return true;
      break;
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

        if ($BLZ == '') {
          $debugText .= ob_get_contents();
          @ob_end_clean();

          \OC_JSON::error(
            array("data" => array('message' => L::T('BLZ not given, cannot validate the bank account.'),
                                  'suggestions' => '',
                                  'debug' => $debugText)));
          return false;
        }

        // First validate the BLZ
        if (!$bav->isValidBank($blz)) {
          if (strlen($blz) != 8 || !is_numeric($blz)) {
            $message = L::t('A German bank id consists of exactly 8 digits: %s.',
                            array($blz));
            $suggestions = '';
          } else {
            $suggestions = FuzzyInput::transposition($blz, function($input) use($bav) {
                return $bav->isValidBank($input);
              });
            $message = L::t("Invalid German(?) bank id %s.",
                            array($blz));
            $suggestions = implode(', ', $suggestions);
          }

          $debugText .= ob_get_contents();
          @ob_end_clean();

          \OC_JSON::error(
            array("data" => array('message' => $message,
                                  'suggestions' => $suggestions,
                                  'debug' => $debugText)));
          return false;
        }

        // BLZ is valid -- or at least appears to be valid

        // assume this is a bank account number and validate it with BAV
        if ($bav->isValidAccount($value)) {
          $value = Finance::makeIBAN($blz, $value);
        } else {
          $message = L::t("Invalid German(?) bank account number %s @ %s.",
                          array($value, $blz));
          $suggestions = FuzzyInput::transposition($value, function($input) use ($bav) {
              return $bav->isValidAccount($input);
            });
          $suggestions = implode(', ', $suggestions);

          $debugText .= ob_get_contents();
          @ob_end_clean();

          \OC_JSON::error(
            array("data" => array(
                    'message' => $message,
                    'suggestions' => $suggestions,
                    'blz' => $blz,
                    'debug' => $debugText)));
          return false;
        }
      }
      $iban = new \IBAN($value);
      if ($iban->Verify()) {
        // Still this may be a valid "hand" generated IBAN but with the
        // wrong bank-account number. If this is a German IBAN, then also
        // check the bank account number with BAV.
        if ($iban->Country() == 'DE') {
          $ktnr = $iban->Account();
          $blz = $iban->Bank();
          $bav = new \malkusch\bav\BAV;
          if (!$bav->isValidBank($blz)) {
            $suggestions = FuzzyInput::transposition($blz, function($input) use($bav) {
                return $bav->isValidBank($input);
              });
            $message = L::t("Invalid German(?) bank id %s.",
                            array($blz));
            $suggestions = implode(', ', $suggestions);

            $debugText .= ob_get_contents();
            @ob_end_clean();

            \OC_JSON::error(
              array("data" => array('message' => $message,
                                    'suggestions' => $suggestions,
                                    'debug' => $debugText)));
            return false;
          }

          // BLZ is valid after this point

          if (!$bav->isValidAccount($ktnr)) {
            $message = L::t("Invalid German(?) bank account number %s @ %s.",
                            array($ktnr, $blz));
            $suggestions = FuzzyInput::transposition($ktnr, function($input) use ($bav) {
                return $bav->isValidAccount($input);
              });
            $suggestions = implode(', ', $suggestions);

            $debugText .= ob_get_contents();
            @ob_end_clean();

            \OC_JSON::error(
              array("data" => array('message' => $message,
                                    'suggestions' => $suggestions,
                                    'blz' => $blz,
                                    'debug' => $debugText)));
            return false;
          }
        }

        $value = $iban->MachineFormat();
        $IBAN = $value;

        // Compute as well the BLZ and the BIC
        $blz = $iban->Bank();
        $bav = new \malkusch\bav\BAV;
        if ($bav->isValidBank($blz)) {
          $BLZ = $blz;
          $BIC = $bav->getMainAgency($blz)->getBIC();
        }
      } else {
        $message = L::t("Invalid IBAN: `%s'.", array($value));
        $suggestions = array();
        foreach ($iban->MistranscriptionSuggestions() as $alternative) {
          if ($iban->Verify($alternative)) {
            $alternative = $iban->MachineFormat($alternative);
            $alternative = $iban->HumanFormat($alternative);
            $suggestions[] = $alternative;
          }
        }
        $suggestions = implode(', ', $suggestions);

        $debugText .= ob_get_contents();
        @ob_end_clean();

        \OC_JSON::error(
          array("data" => array('message' => $message,
                                'suggestions' => $suggestions,
                                'debug' => $debugText)));

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

        $debugText .= ob_get_contents();
        @ob_end_clean();

        \OC_JSON::error(
          array("data" => array(
                  'message' => L::t('Value for `%s\' invalid: `%s\'.',
                                    array($changed, $value)),
                  'suggestions' => '',
                  'debug' => $debugText)));
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

        $debugText .= ob_get_contents();
        @ob_end_clean();

        \OC_JSON::error(
          array("data" => array(
                  'message' => L::t('Value for `%s\' invalid: `%s\'.',
                                    array($changed, $value)),
                  'suggestions' => '',
                  'debug' => $debugText)));
        return false;
      }
      break;
    default:

      $debugText .= ob_get_contents();
      @ob_end_clean();

      \OC_JSON::error(
        array("data" => array(
                'message' => L::t("Unhandled request:"),
                'suggestions' => '',
                'debug' => $debugText)));
      return false;
    }

    // return with all the sanitized and canonicalized values for the
    // bank-account

    \OC_JSON::success(
      array("data" => array(
              'message' => L::t('Value for `%s\' set to `%s\'.', array($changed, $value)),
              'suggestions' => '',
              'mandateProjectId' => $mandateProjectId,
              'mandateProjectName' => $mandateProjectName, // needed?
              'reference' => $reference,
              'value' => $value,
              'iban' => $IBAN,
              'blz' => $BLZ,
              'bic' => $BIC)));
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

} //namespace CAFVDB

?>