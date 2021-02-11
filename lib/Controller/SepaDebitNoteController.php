<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2020, 2021 Claus-Justus Heine <himself@claus-justus-heine.de>
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

namespace OCA\CAFEVDB\Controller;

use \PHP_IBAN\IBAN;

use OCP\AppFramework\Controller;
use OCP\IRequest;
use OCP\AppFramework\Http;

use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Service\RequestParameterService;
use OCA\CAFEVDB\Service\ProjectService;
use OCA\CAFEVDB\Service\FinanceService;
use OCA\CAFEVDB\Service\FuzzyInputService;

use OCA\CAFEVDB\Common\Util;

class SepaDebitNoteController extends Controller {
  use \OCA\CAFEVDB\Traits\ResponseTrait;
  use \OCA\CAFEVDB\Traits\ConfigTrait;

  /** @var \OCA\CAFEVDB\Service\ParameterService */
  private $parameterService;

  /** @var \OCA\CAFEVDB\Service\FinanceService */
  private $financeService;

  /** @var \OCA\CAFEVDB\Service\ProjectService */
  private $projectService;

  /** @var \OCA\CAFEVDB\Service\FuzzyInputService */
  private $fuzzyInputService;

  public function __construct(
    $appName
    , IRequest $request
    , RequestParameterService $parameterService
    , ConfigService $configService
    , FinanceService $financeService
    , ProjectService $projectService
    , FuzzyInputService $fuzzyInputService
  ) {
    parent::__construct($appName, $request);
    $this->parameterService = $parameterService;
    $this->configService = $configService;
    $this->financeService = $financeService;
    $this->projectService = $projectService;
    $this->fuzzyInputService = $fuzzyInputService;
    $this->l = $this->l10N();
  }

  /**
   * @NoAdminRequired
   */
  public function mandateValidate($changed)
  {
    $requiredKeys = [
      'mandateProjectId',
      'projectId',
      'musicianId',
      'mandateReference',
    ];
    foreach ($requiredKeys as $required) {
      $missing = [];
      if ($this->parameterService->getParam($required, false) === false) {
        $missing[] = $required;
      }
      if (!empty($missing)) {
        return self::grumble(
          $this->l->t('Required information %s not provided.', implode(', ', $missing)));
      }
    }

    $projectId  = $this->parameterService['projectId'];
    $musicianId = $this->parameterService['musicianId'];
    $reference  = $this->parameterService['mandateReference'];
    $mandateProjectId  = $this->parameterService['mandateProjectId'];

    $memberProjectId = $this->getConfigValue('memberProjectId', -1);
    $sequenceType = 'permanent';

    $IBAN = $this->parameterService['bankAccountIBAN'];
    $BLZ  = $this->parameterService['bankAccountBLZ'];
    $BIC  = $this->parameterService['bankAccountBIC'];
    $owner = $this->parameterService['bankAccountOwner'];

    $changed = $this->parameterService['changed'];
    $value = $this->parameterService[$changed];

    $feedback = [];

    while (true) {

      switch ($changed) {
      case 'projectId':
        if ($projectId === $memberProjectId) {
          $value = 'member';
        }
      case 'orchestraMember':
        // tricky, for now just generate a new reference
        $newProject = ($value === 'member') ? $memberProjectId : $projectId;
        $mandate = $this->financeService->fetchSepaMandate($newProject, $musicianId);
        if (!empty($mandate)) {
          $reference = $mandate['mandateReference'];
          $IBAN = $mandate['IBAN'];
          $BLZ = $mandate['BLZ'];
          $BIC = $mandate['BIC'];
        } else {
          $reference = $this->financeService->generateSepaMandateReference($newProject, $musicianId);
        }
        $mandateProjectId = $newProject;
        break;
      case 'lastUsedDate':
        // Store the lastUsedDate immediately, if other fields are disabled
        if (empty($this->parameterService['mandateDate'])) {
          $mandate = [
            'mandateReference' => $reference,
            'musicianId' => $musicianId,
            'projectId' => $mandateProjectId,
            'lastUsedDate' => $value,
          ];
          if (!$this->financeService->storeSepaMandate($mandate)) {
            return self::grumble(
              $this->l->t('Failed setting `%s\' to `%s\'.', [ $changed, $value, ]));
          }
        }
      case 'mandateDate':
        // Whatever the user likes ;)
        // The date-picker does some validation on its own, so just live with it.
        return self::dataResponse([
          'message' => $this->l->t('Value for `%s\' set to `%s\'.', [ $changed, $value ]),
          'suggestions' => '',
          'value' => $value,
        ]);
      case 'musicianId':
        $participant = $this->projectService->findParticipant($projectId, $musicianId);
        if (empty($participant)) {
          return self::grumble(
            $this->l->t('Participant %d not found in project %d.', [ $musicianId, $projectId ]));
        }
        $this->logInfo('PART '.get_class($participant['musician']));
        $newOwner = $participant['musician']['surName'].', '.$participant['musician']['firstName'];
        if (empty($owner)) {
          $changed = 'bankAccountOwner';
          $value = $newOwner;
          continue 2;
        } else {
          $feedback['owner'] = $this->l->t('Set bank-account owner to musician\'s name?');
          break;
        }
        // fallthrough
      case 'bankAccountOwner':
        $value = $this->financeService->sepaTranslit($value);
        if (!$this->financeService->validateSepaString($value)) {
          return self::grumble(
            $this->l->t('Account owner contains invalid characters: "%s"', $value));
        }
        $owner = $value;
        break;
      case 'bankAccountIBAN':
        if (empty($value)) {
          $IBAN = '';
          break;
        }
        $value = Util::removeSpaces($value);
        $iban = new IBAN($value);
        if (!$iban->Verify() && is_numeric($value)) {
          // maybe simply the bank account number, if we have a BLZ,
          // then compute the IBAN
          $blz = $BLZ;
          $bav = new \malkusch\bav\BAV;

          if (empty($BLZ)) {
            return self::grumble(
              $this->l->t('BLZ not given, cannot validate the bank account.'));
          }

          // First validate the BLZ
          if (!$bav->isValidBank($blz)) {
            if (strlen($blz) != 8 || !is_numeric($blz)) {
              return self::grumble(
                $this->l->t('A German bank id consists of exactly 8 digits: %s.', [ $blz ]));
            }

            $suggestions = $this->fuzzyInputService->transposition($blz, function($input) use($bav) {
              return $bav->isValidBank($input);
            });

            return self::dataResponse(
              [
                'message' => $this->l->t('Invalid German(?) bank id "%s".', [ $blz    ]),
                'suggestions' => implode(', ', $suggestions),
              ],
              Http::STATUS_BAD_REQUEST);
          }

          // BLZ is valid -- or at least appears to be valid

          // assume this is a bank account number and validate it with BAV
          if (!$bav->isValidAccount($value)) {
            $message = $this->l->t('Invalid German(?) bank account number %s @ %s.',
                                   [ $value, $blz ]);
            $suggestions = $this->fuzzyInputService->transposition($value, function($input) use ($bav) {
              return $bav->isValidAccount($input);
            });
            $suggestions = implode(', ', $suggestions);

            return self::dataResponse(
              [
                'message' => $message,
                'suggestions' => $suggestions,
                'blz' => $blz,
              ], Http::STATUS_BAD_REQUEST);
          }
          $value = $this->financeService->makeIBAN($blz, $value);
        }
        $iban = new IBAN($value);
        if (!$iban->Verify()) {
          $message = $this->l->t("Invalid IBAN: `%s'.", $value);
          $suggestions = [];
          foreach ($iban->MistranscriptionSuggestions() as $alternative) {
            if ($iban->Verify($alternative)) {
              $alternative = $iban->MachineFormat($alternative);
              $alternative = $iban->HumanFormat($alternative);
              $suggestions[] = $alternative;
            }
          }
          $suggestions = implode(', ', $suggestions);

          return self::dataResponse(
            [
              'message' => $message,
              'suggestions' => $suggestions,
            ], Http::STATUS_BAD_REQUEST);
        }

        // Still this may be a valid "hand" generated IBAN but with the
        // wrong bank-account number. If this is a German IBAN, then also
        // check the bank account number with BAV.
        if ($iban->Country() == 'DE') {
          $ktnr = $iban->Account();
          $blz = $iban->Bank();
          $bav = new \malkusch\bav\BAV;
          if (!$bav->isValidBank($blz)) {
            $suggestions = $this->fuzzyInputService->transposition($blz, function($input) use($bav) {
              return $bav->isValidBank($input);
            });
            $message = $this->l->t('Invalid German(?) bank id "%s".', [ $blz ]);
            $suggestions = implode(', ', $suggestions);

            return self::dataResponse(
              [
                'message' => $message,
                'suggestions' => $suggestions,
              ], Http::STATUS_BAD_REQUEST);
          }

          // BLZ is valid after this point

          if (!$bav->isValidAccount($ktnr)) {
            $message = $this->l->t('Invalid German(?) bank account number %s @ %s.',
                                   [ $ktnr, $blz ]);
            $suggestions = $this->fuzzyInputService->transposition($ktnr, function($input) use ($bav) {
              return $bav->isValidAccount($input);
            });
            $suggestions = implode(', ', $suggestions);

            return self::dataResponse(
              [
                'message' => $message,
                'suggestions' => $suggestions,
                'blz' => $blz,
              ], Http::STATUS_BAD_REQUEST);
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
        break;
      case 'bankAccountBLZ':
        if ($value == '') {
          $BLZ = '';
          break;
        }
        $value = Util::removeSpaces($value);
        $bav = new \malkusch\bav\BAV;
        if (!$bav->isValidBank($value)) {
          return self::grumble(
            $this->l->t('Value for `%s\' invalid: `%s\'.', [ $changed, $value ]));
        }
        // set also the BIC
        $BLZ = $value;
        $agency = $bav->getMainAgency($value);
        $bic = $agency->getBIC();
        if ($this->financeService->validateSWIFT($bic)) {
          $BIC = $bic;
        }

        // re-run the IBAN validation
        $changed = 'bankAccountIBAN';
        $value = $IBAN;
        continue 2;
      case 'bankAccountBIC':
        if ($value == '') {
          $BIC = '';
          break;
        }
        $value = Util::removeSpaces($value);
        if (!$this->financeService->validateSWIFT($value)) {
          // maybe a BLZ
          $bav = new \malkusch\bav\BAV;
          if ($bav->isValidBank($value)) {
            $BLZ = $value;
            $agency = $bav->getMainAgency($value);
            $value = $agency->getBIC();
            // Set also the BLZ
          }
        }
        if (!$this->financeService->validateSWIFT($value)) {
          return self::grumble(
            $this->l->t('Value for `%s\' invalid: `%s\'.', [ $changed, $value ]));
        }
        $BIC = $value;
        break;
      default:
        return self::grumble(
          $this->l->t('Unknown Request: %s / %s', [ 'validate', print_r($changed, true) ]));
      }

      // return with all the sanitized and canonicalized values for the
      // bank-account

      return self::dataResponse(
        [
          'message' => $this->l->t('Value for `%s\' set to `%s\'.', [ $changed, $value ]),
          'suggestions' => '',
          'mandateProjectId' => $mandateProjectId,
          'reference' => $reference,
          'value' => $value,
          'iban' => $IBAN,
          'blz' => $BLZ,
          'bic' => $BIC,
          'owner' => $owner,
          'feedback' => $feedback,
        ]);

    } // validation loop
  }

}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
