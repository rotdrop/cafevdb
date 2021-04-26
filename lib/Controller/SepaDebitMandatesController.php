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
use OCP\AppFramework\Http\TemplateResponse;

use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Service\RequestParameterService;
use OCA\CAFEVDB\Service\ProjectService;
use OCA\CAFEVDB\Service\Finance\FinanceService;
use OCA\CAFEVDB\Service\FuzzyInputService;
use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Database\Doctrine\Util as DBUtil;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types;

use OCA\CAFEVDB\Common\Util;

class SepaDebitMandatesController extends Controller {
  use \OCA\CAFEVDB\Traits\ResponseTrait;
  use \OCA\CAFEVDB\Traits\ConfigTrait;
  use \OCA\CAFEVDB\Traits\EntityManagerTrait;

  /** @var ReqeuestParameterService */
  private $parameterService;

  /** @var FinanceService */
  private $financeService;

  /** @var ProjectService */
  private $projectService;

  /** @var FuzzyInputService */
  private $fuzzyInputService;

  public function __construct(
    $appName
    , IRequest $request
    , RequestParameterService $parameterService
    , ConfigService $configService
    , EntityManager $entityManager
    , FinanceService $financeService
    , ProjectService $projectService
    , FuzzyInputService $fuzzyInputService
  ) {
    parent::__construct($appName, $request);
    $this->parameterService = $parameterService;
    $this->configService = $configService;
    $this->entityManager = $entityManager;
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
    $nonRecurring = $this->parameterService['nonRecurring'];
    $nonRecurring = filter_var($nonRecurring, FILTER_VALIDATE_BOOLEAN, ['flags' => FILTER_NULL_ON_FAILURE]);

    $this->logDebug('NON RECUR '.$nonRecurring.' '.(!!$nonRecurring));

    $memberProjectId = $this->getConfigValue('memberProjectId', -1);
    $sequenceType = 'permanent';


    $IBAN = $this->parameterService['bankAccountIBAN'];
    $BLZ  = $this->parameterService['bankAccountBLZ'];
    $BIC  = $this->parameterService['bankAccountBIC'];
    $owner = $this->parameterService['bankAccountOwner'];

    $changed = $this->parameterService['changed'];
    $value = $this->parameterService[$changed];

    $validations[] = [
      'changed' => $changed,
      'value' => $value,
      'initiator' => null,
    ];

    $feedback = [];
    $message = [];
    $result = [];

    while (($validation = array_pop($validations)) !== null) {

      $changedPrev = $changed;
      $changed = $validation['changed'];
      $value = $validation['value'];
      $initiator = $validation['initiator'];

      $newValidations = [];
      switch ($changed) {
      case 'projectId':
        $newValidations[] = [
          'changed' => 'orchestraMember',
          'value' => ($projectId === $memberProjectId) ? 'member' : '',
        ];
        $newValidations[] = [
          'changed' => 'musicianId',
          'value' => $musicianId,
        ];
        break;
      case 'orchestraMember':
        // tricky, for now just generate a new reference
        // @todo This has be made foolproof!
        $newProject = ($value === 'member') ? $memberProjectId : $projectId;
        $mandate = $this->financeService->fetchSepaMandate($newProject, $musicianId);
        if (!empty($mandate)) {
          $reference = $mandate['mandateReference'];
          $IBAN = $mandate['IBAN'];
          $BLZ = $mandate['BLZ'];
          $BIC = $mandate['BIC'];
          $message[] = $this->l->t('Found exisiting mandate with reference "%s"', $reference);
        } else if (!empty($newProject) && !empty($musicianId)) {
          $reference = $this->financeService->generateSepaMandateReference($newProject, $musicianId);
          $message[] = $this->l->t('Generated new reference "%s"', $reference);
        }
        $mandateProjectId = $newProject;
        $newValidations[] = [
          'changed' => 'nonRecurring',
          'value' => $value != 'member',
        ];
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
      case 'nonRecurring':
        $nonRecurring = $value;
        break;
      case 'mandateDate':
        // Whatever the user likes ;)
        // The date-picker does some validation on its own, so just live with it.
        return self::dataResponse([
          'message' => $this->l->t('Value for `%s\' set to `%s\'.', [ $changed, $value ]),
          'suggestions' => '',
          'value' => $value,
        ]);
      case 'musicianId':
        if (empty($musicianId)) {
          $newValidations[] = [
            'changed' => 'bankAccountOwner',
            'value' => '',
          ];
          break;
        }
        if (empty($projectId)) {
          break;
        }
        $participant = $this->projectService->findParticipant($projectId, $musicianId);
        if (empty($participant)) {
          return self::grumble(
            $this->l->t('Participant %d not found in project %d.', [ $musicianId, $projectId ]));
        }
        $newOwner = $participant['musician']['surName'].', '.$participant['musician']['firstName'];
        if (!empty($projectId)) {
          $newValidations[] = [
            'changed' => 'projectId',
            'value' => $projectId,
          ];
        }
        if (true || empty($owner)) {
          $newValidations[] = [
            'changed' => 'bankAccountOwner',
            'value' => $newOwner,
          ];
        } else {
          $feedback['owner'] = $this->l->t('Set bank-account owner to musician\'s name?');
        }
        break;
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
          // $this->logInfo('Try Alternatives');
          foreach ($iban->MistranscriptionSuggestions() as $alternative) {
            // $this->logInfo('ALTERNATIVE '.$alternative);
            if ($iban->Verify($alternative)) {
              $alternative = $iban->MachineFormat($alternative);
              $alternative = $iban->HumanFormat($alternative);
              $suggestions[] = $alternative;
            }
          }
          if (empty($suggestions)) {
            $suggestions = $this->fuzzyInputService->transposition($value, function($input) use ($iban) {
              return $iban->Verify($input);
            });
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
        $newValidations[] = [
          'changed' => 'bankAccountIBAN',
          'value' => $IBAN,
        ];
        break;
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
          $this->l->t(
            'Unknown Request: %s / %s / %s',
            [
              'validate',
              print_r($changed, true),
              print_r($value, true),
            ]));
      }

      $message[] = $this->l->t(
        'Value for `%s\' set to `%s\'.', [ $changed, $value ]);
      $result[$changed] = $value;

      foreach ($newValidations as $validation) {
        if ($initiator == $validation['changed']) {
          // $this->logInfo('SKIP INITIATOR '.$initiator);
          // avoid first-level recursion
          continue;
        }
        $validation['initiator'] = $changed;
        $validations[] = $validation;
      }

      if (!empty($validations)) {
        continue;
      }

      // return with all the sanitized and canonicalized values for the
      // bank-account

      return self::dataResponse(
        [
          'message' => $message,
          'suggestions' => '',
          'mandateProjectId' => $mandateProjectId,
          'reference' => $reference,
          'value' => $result,
          'iban' => $IBAN,
          'blz' => $BLZ,
          'bic' => $BIC,
          'owner' => $owner,
          'feedback' => $feedback,
          'nonRecurring' => $nonRecurring,
        ]);

    } // validation loop
  }

  /**
   * @NoAdminRequired
   */
  public function mandateForm(
    $projectId
    , $musicianId
    , $bankAccountSequence
    , $mandateSequence
    ) {

    if (empty($musicianId)) {
      return self::grumble($this->l->t('Parameter musicianId must be set, but is empty.'));
    }

    /** @var Entities\Musician $musician */
    $musician = $this->getDatabaseRepository(Entities\Musician::class)->find($musicianId);

    if ($projectId > 0) {
      /** @var Entities\Project $project */
      $project = $this->getDatabaseRepository(Entities\Project::class)->find($projectId);
    }

    $this->logInfo('CALLED WITH '.print_r([$projectId, $musicianId, $bankAccountSequence, $mandateSequence], true));

    if (!empty($mandateSequence)) {
      /** @var Entities\SepaDebitMandate $mandate */
      $mandate = $this->getDatabaseRepository(Entities\SepaDebitMandate::class)->find([
        'musician' => $musicianId,
        'sequence' => $mandateSequence,
      ]);

      if (empty($mandate)) {
        return self::grumble($this->l->t('Unable to load SEPA debit mandate for musician %s/%d, sequence count %d',
                                         [$musician->getPublicName(), $musician->getId(), $mandateSequence]));
      }

      /** @var Entities\SepaBankAccount $bankAccount */
      $bankAccount = $mandate->getSepaBankAccount();
    } else if (!empty($bankAccountSequence)) {
      /** @var Entities\SepaBankAccount $bankAccount */
      $bankAccount = $this->getDatabaseRepository(Entities\SepaBankAccount::class)->find([
        'musician' => $musicianId,
        'sequence' => $bankAccountSequence,
      ]);
      $mandate = null;
    } else {
      $bankAccount = null;
      $mandate = null;
    }

    if (empty($mandate)) {
      $mandate = (new Entities\SepaDebitMandate)
               ->setNonRecurring(!empty($project))
               ->setMandateDate(new \DateTimeImmutable)
               ->setSequence(0);

      if (empty($bankAccount)) {
        $bankAccount = (new Entities\SepaBankAccount)
                     ->setBankAccountOwner($musician->getPublicName())
                     ->setSequence(0);
      }
    }

    // If we have a valid IBAN, compute BLZ and BIC
    $iban = $bankAccount->getIban();
    $blz  = '';
    $bic  = $bankAccount->getBic();

    $ibanValidator = new IBAN($iban);
    if ($ibanValidator->Verify()) {
      $blz = $ibanValidator->Bank();
      $bav = new \malkusch\bav\BAV;
      if ($bav->isValidBank($blz)) {
        $bic = $bav->getMainAgency($blz)->getBIC();
      }
      $iban = $ibanValidator->MachineFormat();
    }

    $memberProjectId = $this->getConfigValue('memberProjectId', 0);
    $isClubMember = ($memberProjectid == $projectId) || $musician->isMemberOf($memberProjectId);

    $projectOptions = [];
    if (empty($project)) {
      /** @var Entities\ProjectParticipant $participant */
      foreach ($musician->getProjectParticipation() as $participant) {
        $participantProject = $participant->getProject();
        $tempory = Types\EnumProjectTemporalType::TEMPORARY();
        if ($participantProject->getType() == $tempory) {
          $name = $participantProject['name'];
          $year = $participantProject['year'];
          $shortName = str_replace($year, '', $name);
          $projectOptions[] = [
            'value' => $participantProject['id'],
            'name' => $name,
            'label' => $shortName,
            'group' => $year,
          ];
        }
      }
      if (count($projectOptions) <= 5) {
        foreach ($projectOptions as &$option) {
          unset($option['label']);
          unset($option['group']);
        }
      }
    } else if ($projectId != $memberProjectId) {
      $projectOptions[] = [
        'value' => $projectId,
        'name' => $project->getName(),
      ];
    }

    $templateParameters = [
      'projectId' => $projectId,
      'projectName' => $project ? $project->getName() : null,

      'musicianId' => $musicianId,
      'musicianName' => $musician->getPublicName(),

      'mandateProjectId' => $mandate->getProject() ? $mandate->getProject()->getId() : 0,
      'mandateProjectName' => $mandate->getProject() ? $mandate->getProject()->getName() : null,

      // members are not allowed to give per-project mandates
      'memberProjectId' => $memberProjectId,
      'isClubMember' => !empty($clubMember),

      'projectOptions' => $projectOptions,

      'cssClass' => 'sepadebitmandate',

      'mandateSequence' => $mandate->getSequence(),
      'mandateReference' => $mandate->getMandateReference(),
      'mandateExpired' => $mandateExpired,
      'mandateDate' => $mandate->getMandateDate(),
      'lastUsedDate' => $mandate->getLastUsedDate(),
      'sequenceType' => $mandate->getNonRecurring() ? 'once' : 'permanent',
      'nonRecurring' => $mandate->getNonRecurring(),
      'mandateInUse' => $mandate->inUse(),

      'bankAccountSequence' => $bankAccount->getSequence(),
      'bankAccountOwner' => $bankAccount->getBankAccountOwner(),

      'bankAccountIBAN' => $iban,
      'bankAccountBLZ' => $blz,
      'bankAccountBIC' => $bic,
      'bankAccountInUse' => $bankAccount->inUse(),

      'dateTimeFormatter' => \OC::$server->query(\OCP\IDateTimeFormatter::class),
    ];

    $tmpl = new TemplateResponse($this->appName, 'sepa-debit-mandate', $templateParameters, 'blank');
    $html = $tmpl->render();

    $responseData = [
      'contents' => $html,
      'projectId' => $projectId,
      'musicianId' => $musicianId,
      'bankAccountSequence' => $bankAccount->getSequence(),
      'mandateSequence' => $mandate->getSequence(),
      'mandateReference' => $mandate->getMandateReference(),
    ];

    return self::dataResponse($responseData);
  }

  /**
   * @NoAdminRequired
   */
  public function mandateStore(
    $mandateReference
    , $sequenceType
    , $musicianId
    , $projectId
    , $mandateProjectId
    , $mandateDate
    , $lastUsedDate
    , $bankAccountIBAN
    , $bankAccountBIC
    , $bankAccountBLZ
    , $bankAccountOwner
  )
  {
    $requiredKeys = ['mandateProjectId', 'projectId', 'musicianId', 'mandateReference'];
    foreach ($requiredKeys as $required) {
      if (empty(${$required})) {
        return self::grumble($this->l->t("Required information `%s' not provided.", $required));
      }
    }

    // Compose the mandate
    $mandate = [
      'mandateReference' => $mandateReference,
      'sequenceType' => $sequenceType,
      'musicianId' => $musicianId,
      'projectId' => $mandateProjectId,
      'mandateDate' => $mandateDate,
      'lastUsedDate' => $lastUsedDate,
      'IBAN' => $bankAccountIBAN,
      'BIC' => $bankAccountBIC,
      'BLZ' => $bankAccountBLZ,
      'bankAccountOwner' => $bankAccountOwner,
    ];

    // this will throw on error
    $this->financeService->validateSepaMandate($mandate);

    $mandate = $this->financeService->storeSepaMandate($mandate);

    if (empty($mandate)) {
      return self::grumble($this->l_>t('Unable to store SEPA debit mandate in data-base.'));
    }

    return self::response($this->l->t('SEPA debit mandate stored in data-base.'));
  }

  /**
   * @NoAdminRequired
   */
  public function mandateDelete($musicianId, $projectId, $mandateReference)
  {
    $requiredKeys = [ 'projectId', 'musicianId', 'mandateReference' ];
    foreach ($requiredKeys as $required) {
      if (empty(${$required})) {
        return self::grumble($this->l->t("Required information `%s' not provided.", $required));
      }
    }

    if ($this->financeService->deleteSepaMandate($reference)) {
      return self::response($this->l->t('SEPA debit mandate deleted from data-base.'));
    } else {
      return self::grumble($this->l->t('Unable to delete SEPA debit mandate from data-base.'));
    }
  }

}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
