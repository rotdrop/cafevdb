<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2020, 2021, 2022 Claus-Justus Heine <himself@claus-justus-heine.de>
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
use OCA\CAFEVDB\Wrapped\Doctrine\DBAL\Exception\UniqueConstraintViolationException;

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
use OCA\CAFEVDB\Database\Doctrine\ORM\Repositories;
use OCA\CAFEVDB\Database\Doctrine\Util as DBUtil;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types;
use OCA\CAFEVDB\Storage\AppStorage;
use OCP\Files\SimpleFS\ISimpleFile;

use OCA\CAFEVDB\Common\Util;

class SepaDebitMandatesController extends Controller {
  use \OCA\CAFEVDB\Traits\ResponseTrait;
  use \OCA\CAFEVDB\Traits\ConfigTrait;
  use \OCA\CAFEVDB\Traits\EntityManagerTrait;
  use \OCA\CAFEVDB\Traits\DateTimeTrait;
  use \OCA\CAFEVDB\Controller\FileUploadRowTrait;

  public const HARDCOPY_ACTION_UPLOAD = 'upload';
  public const HARDCOPY_ACTION_DELETE = 'delete';

  /** @var ReqeuestParameterService */
  private $parameterService;

  /** @var FinanceService */
  private $financeService;

  /** @var ProjectService */
  private $projectService;

  /** @var FuzzyInputService */
  private $fuzzyInputService;

  /** @var Repositories\SepaBankAccountsRepository */
  private $bankAccountsRepository;

  /** @var Repositories\SepaDebitMandatesRepository */
  private $debitMandatesRepository;

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

    $this->bankAccountsRepository = $this->getDatabaseRepository(Entities\SepaBankAccount::class);
    $this->debitMandatesRepository = $this->getDatabaseRepository(Entities\SepaDebitMandate::class);
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
      'bankAccountSequence',
      'mandateSequence',
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
    $mandateNonRecurring = $this->parameterService['mandateNonRecurring'];
    $mandateNonRecurring = filter_var($mandateNonRecurring, FILTER_VALIDATE_BOOLEAN, ['flags' => FILTER_NULL_ON_FAILURE]);

    $this->logDebug('NON RECUR '.$mandateNonRecurring.' '.(!!$mandateNonRecurring));

    $memberProjectId = $this->getConfigValue('memberProjectId', null);

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

    if ($changed != 'bankAccountIBAN' && (!empty($IBAN) && (empty($BLZ)) || empty($BIC))) {
      // re-run the IBAN validation
      $validations[] = [
        'changed' => 'bankAccountIBAN',
        'value' => $IBAN,
      ];
    }

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
          $mandate = (new Entities\SepaDebitMandate)
            ->setProject($newProject)
            ->setMusician($musicianId);
          $reference = $this->financeService->generateSepaMandateReference($mandate);
          $message[] = $this->l->t('Generated new reference "%s"', $reference);
        } else if (empty($newProject)) {
          $reference = '';
          $message[] = $this->l->t('No project, delete mandate-reference.');
        }
        $mandateProjectId = $newProject;
        $newValidations[] = [
          'changed' => 'mandateNonRecurring',
          'value' => $value != 'member',
        ];
        break;
      case 'mandateLastUsedDate':
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
      case 'mandateNonRecurring':
        $mandateNonRecurring = $value;
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
          $BLZ = '';
          $BIC = '';
          $result['bankAccountBLZ'] = $BLZ;
          $result['bankAccountBIC'] = $BIC;
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
        $result['bankAccountBLZ'] = $BLZ;
        $result['bankAccountBIC'] = $BIC;
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
        'Value for "%s" set to "%s".', [ $changed, $value ]);
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
          'mandateNonRecurring' => $mandateNonRecurring,
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

    // @todo
    $mandateExpired = false;

    if (empty($musicianId)) {
      return self::grumble($this->l->t('Parameter musicianId must be set, but is empty.'));
    }

    // disable soft-deletion filter as we are fetching specific data.
    $this->disableFilter('soft-deleteable');

    /** @var Entities\Musician $musician */
    $musician = $this->getDatabaseRepository(Entities\Musician::class)->find($musicianId);

    if ($projectId > 0) {
      /** @var Entities\Project $project */
      $project = $this->getDatabaseRepository(Entities\Project::class)->find($projectId);
    }

    $this->logDebug('CALLED WITH '.print_r([$projectId, $musicianId, $bankAccountSequence, $mandateSequence], true));

    $mandateProjectId = null;
    if (!empty($mandateSequence)) {
      /** @var Entities\SepaDebitMandate $mandate */
      $mandate = $this->debitMandatesRepository->find([
        'musician' => $musicianId,
        'sequence' => $mandateSequence,
      ]);

      if (empty($mandate)) {
        return self::grumble($this->l->t('Unable to load SEPA debit mandate for musician %s/%d, sequence count %d',
                                         [$musician->getPublicName(), $musician->getId(), $mandateSequence]));
      }

      $mandateProjectId = $mandate->getProject()->getId();

      /** @var Entities\SepaBankAccount $bankAccount */
      $bankAccount = $mandate->getSepaBankAccount();
    } else if (!empty($bankAccountSequence)) {
      /** @var Entities\SepaBankAccount $bankAccount */
      $bankAccount = $this->bankAccountsRepository->find([
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
               ->setNonRecurring(false /* !empty($project) */)
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
    $isClubMember = ($memberProjectId == $projectId) || $musician->isMemberOf($memberProjectId);

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

    /** @var Entities\EncryptedFile $writtenMandate */
    if (!empty($writtenMandate = $mandate->getWrittenMandate())) {
      $writtenMandateId = $writtenMandate->getId();
      $writtenMandateDownloadLink = $this->urlGenerator()->linkToRoute($this->appName().'.downloads.get', [
        'section' => 'database',
        'object' => $writtenMandateId,
      ]);
      $writtenMandateFileName = $mandate->getMandateReference();
      $extension = Util::fileExtensionFromMimeType($writtenMandate->getMimeType());
      if (!empty($extension)) {
        $writtenMandateFileName .= '.' . $extension;
      }
      $writtenMandateDownloadLink = $writtenMandateDownloadLink
        . '?requesttoken=' . urlencode(\OCP\Util::callRegister())
                           . '&fileName=' . urlencode($writtenMandateFileName);
    }

    $mandateUsage = $this->debitMandatesRepository->usage($mandate, true);
    $lastUsedDate = self::convertToDateTime($mandateUsage['lastUsed']);

    $templateParameters = [
      'projectId' => $projectId,
      'projectName' => $project ? $project->getName() : null,

      'musicianId' => $musicianId,
      'musicianName' => $musician->getPublicName(),

      'mandateProjectId' => $mandate->getProject() ? $mandate->getProject()->getId() : 0,
      'mandateProjectName' => $mandate->getProject() ? $mandate->getProject()->getName() : null,

      // members are not allowed to give per-project mandates
      'memberProjectId' => $memberProjectId,
      'isClubMember' => $isClubMember,

      'projectOptions' => $projectOptions,

      'participantFolder' => empty($project) ? '' : $this->projectService->ensureParticipantFolder($project, $musician, dry: true),

      'cssClass' => 'sepadebitmandate',

      'mandateSequence' => $mandate->getSequence(),
      'mandateReference' => $mandate->getMandateReference(),
      'mandateExpired' => $mandateExpired, // @todo
      'mandateDate' => $mandate->getMandateDate(),
      'mandateLastUsedDate' => $lastUsedDate, // $mandate->getLastUsedDate(),
      'mandateNonRecurring' => $mandate->getNonRecurring(),
      'mandateInUse' => $mandate->inUse(),
      'mandateDeleted' => $mandate->getDeleted(),

      'bankAccountSequence' => $bankAccount->getSequence(),
      'bankAccountOwner' => $bankAccount->getBankAccountOwner(),

      'bankAccountIBAN' => $iban,
      'bankAccountBLZ' => $blz,
      'bankAccountBIC' => $bic,
      'bankAccountInUse' => $bankAccount->inUse(),
      'bankAccountDeleted' => $bankAccount->getDeleted(),

      'writtenMandateId' => $writtenMandateId??null,
      'writtenMandateDownloadLink' => $writtenMandateDownloadLink??null,
      'writtenMandateFileName' => $writtenMandateFileName??null,

      'dateTimeFormatter' => \OC::$server->query(\OCP\IDateTimeFormatter::class),
      'toolTips' => $this->toolTipsService(),
    ];

    $tmpl = new TemplateResponse($this->appName, 'sepa-debit-mandate', $templateParameters, 'blank');
    $html = $tmpl->render();

    $responseData = [
      'contents' => $html,
      'projectId' => $projectId,
      'musicianId' => $musicianId,
      'bankAccountSequence' => $bankAccount->getSequence(),
      'bankAccountDeleted' => !empty($bankAccount->getDeleted()),
      'mandateSequence' => $mandate->getSequence(),
      'mandateDeleted' => !empty($mandate->getDeleted()),
      'mandateReference' => $mandate->getMandateReference(),
    ];

    return self::dataResponse($responseData);
  }

  /**
   * @NoAdminRequired
   */
  public function mandateStore(
    $projectId
    // SEPA "id"
    , $musicianId
    , $bankAccountSequence
    , $mandateSequence
    // Bank account data
    , $bankAccountIBAN
    , $bankAccountBIC
    , $bankAccountBLZ
    , $bankAccountOwner
    // debit-mandate data
    , $mandateRegistration
    , $mandateBinding
    , $mandateProjectId
    , $mandateNonRecurring
    , $mandateDate
    , $mandateLastUsedDate
    , $writtenMandateId
    , $writtenMandateFileUpload
    , $mandateUploadLater
  )
  {
    $requiredKeys = [
      'musicianId',
      'bankAccountIBAN',
      'bankAccountBLZ', // @todo maybe get rid of it
      'bankAccountBIC', // @todo maybe get rid of it
      'bankAccountOwner',
    ];

    if (!empty($mandateSequence) || !empty($mandateRegistration)) {
      $requiredKeys = array_merge(
        $requiredKeys, [
          'mandateBinding',
          'mandateProjectId',
          'mandateDate',
        ]);

      $mandateNonRecurring = filter_var($mandateNonRecurring, FILTER_VALIDATE_BOOLEAN, ['flags' => FILTER_NULL_ON_FAILURE]);
      if ($mandateNonRecurring === null) {
        $requiredKeys[] = 'mandateNonRecurring';
      }
    }

    foreach ($requiredKeys as $required) {
      if (empty(${$required})) {
        return self::grumble($this->l->t("Required information `%s' not provided.", $required));
      }
    }

    // Aquire data-base Entities

    // First check the bank-account
    if (!empty($bankAccountSequence)) {
      /** @var Entities\SepaBankAccount $bankAccount */
      $bankAccount = $this->bankAccountsRepository->find([
        'musician' => $musicianId,
        'sequence' => $bankAccountSequence,
      ]);
      $musician = $bankAccount->getMusician();

    } else {
      $bankAccount = (new Entities\SepaBankAccount)
                   ->setMusician($musicianId);
    }

    if ($bankAccount->inUse() && $bankAccount->getIban() !== $bankAccountIBAN) {
      return self::grumble($this->l->t('The current bank account has already been used for payments or is bound to debit-mandates. Therefore the IBAN must not be changed. Please create a new account; you may disable the current account, at you option.'));
    }

    // set the new values
    $bankAccount->setIban($bankAccountIBAN)
                ->setBlz($bankAccountBLZ)
                ->setBic($bankAccountBIC)
                ->setBankAccountOwner($bankAccountOwner);

    try {
      $this->financeService->validateSepaAccount($bankAccount); // throws on error
    } catch (\InvalidArgumentException $e) {
      return self::grumble($this->l->t('Bank-account failed to validate: %s.', $e->getMessage()));
    }

    if ($bankAccount->getSequence() == null) {
      // as a unique constraint on IBAN and owner on the data-base level
      // is not possible (the data is stored encrypted), we check for
      // duplicates here. As there are not so many bank accounts per
      // musician this should be fairly fast. Of course, this does not
      // hack the problem when two different operators enter the same
      // account at the same time through the web-interface.

      $existingAccounts = $this->bankAccountsRepository->findBy([ 'musician' => $musicianId ]);
      /** @var Entities\SepaBankAccount $oldAccount */
      foreach ($existingAccounts as $oldAccount) {
        if ($oldAccount->getIban() == $bankAccountIBAN) {
          return self::grumble($this->l->t(
            'The IBAN %s, account-owner %s, has already been recorded for the musician %s.',
            [
              $bankAccountIBAN,
              $oldAccount->getBankAccountOwner(),
              $oldAccount->getMusician()->getPublicName(),
            ]));
        }
      }
    }

    do {
      try {
        // try persist with increasing sequence until we succeed
        $this->bankAccountsRepository->persist($bankAccount);
      } catch (UniqueConstraintViolationException $e) {
        if ($bankAccount->inUse()) {
          $this->logException($e);
          return self::grumble($this->l->t('Unable to modify already used bank-account.'));
        }
        $this->entityManager->reopen();
        $bankAccount->setSequence(null);
      }
    } while ($bankAccount->getSequence() === null);

    if (empty($mandateRegistration) && empty($mandateSequence)) {

      $responseData = [
        'message' => $this->l->t('Successfully stored the bank account with IBAN "%s" and owner "%s"',
                                 [ $bankAccount->getIban(), $bankAccount->getBankAccountOwner()]),
        'projectId' => $projectId,
        'musicianId' => $musicianId,
        'bankAccountSequence' => $bankAccount->getSequence(),
        'mandateSequence' => $mandateSequence,
      ];

      return self::dataResponse($responseData);
    }

    // Check for the debit-mandate.
    if (!empty($mandateSequence)) {
      /** @var Entities\SepaDebitMandate $debitMandate */
      $debitMandate = $this->debitMandatesRepository->find([
        'musician' => $musicianId,
        'sequence' => $mandateSequence,
      ]);

      if ($debitMandate->getSepaBankAccount() != $bankAccount) {
        // would be allowable if not in use, but does not fit the
        // layout of the UI: this cannot happen unless things are
        // garbled.
        return self::grumble(
          $this->l->t('The bank-account with IBAN %s bound to the existing mandate does not match the submitted bank-account with IBAN %s.', [
            $debitMandate->getSepaBankAccount()->getIban(),
            $bankAccountIBAN,
          ]));
      }
      $musician = $debitMandate->getMusician();
      // $mandateProject = $debitMandate->getProject();
    } else {
      $debitMandate = (new Entities\SepaDebitMandate)
                    ->setMusician($musicianId);
    }

    // @todo check if this works as expected
    // @todo validate, check for date-in-the-future
    $mandateDate = Util::dateTime($mandateDate);

    if ($debitMandate->inUse()) {
      if ($debitMandate->getMandateDate() != $mandateDate) {
        return self::grumble($this->l->t('The current debit-mandate already has been used for payments. Therefore the date of the debit-mandate must not be changed. Please create a new debit-mandate; you may disable the current mandate, at you option.'));
      }
      if ($debitMandate->getNonRecurring() != $mandateNonRecurring
          && $mandateNonRecurring && $debitMandate->usage() > 1) {
        return self::grumble($this->l->t('The current debit-mandate already has been used for more than a single payment. Therefore it can non longer be changed from "recurring" to "non-recurring".'));
      }
      if ($debitMandate->getProject()->getId() != $mandateProjectId) {
        return self::grumble($this->l->t('The current debit-mandate already has been used for payments. Therefore the project-binding can no longer be changed.'));
      }
      if ($debitMandate->getWrittenMandate() != null && (int)$writtenMandateId <= 0) {
        return self::grumble($this->l->t('The current debit-mandate alrady has been used for payments. Therefore the stored copy of the written mandate cannot be deleted.'));
      }
      // just make sure it does not change.
      $mandateReference = $debitMandate->getMandateReference();
    } else {
      $debitMandate->setProject($mandateProjectId);
      $mandateReference = $this->financeService->generateSepaMandateReference($debitMandate);
    }

    $uploadFile = null;
    $writtenMandate = $debitMandate->getWrittenMandate();
    if (!empty($writtenMandateFileUpload)) {
      /** @var AppStorage $appStorage */
      $appStorage = $this->di(AppStorage::class);

      /** @var \OCP\Files\IMimeTypeDetector $mimeTypeDetector */
      $mimeTypeDetector = $this->di(\OCP\Files\IMimeTypeDetector::class);

      /** @var ISimpleFile $uploadFile */
      $uploadFile = $appStorage->getUploadFile($writtenMandateFileUpload);

      if (empty($writtenMandate)) {
        $writtenMandate = new Entities\EncryptedFile;
        $fileData = new Entities\EncryptedFileData;
        $fileData->setFile($writtenMandate);
        $writtenMandate->setFileData($fileData);
      } else {
        $fileData = $writtenMandate->getFileData();
      }


      $fileContents = $uploadFile->getContent();
      $mimeType = $mimeTypeDetector->detectString($fileContents);
      $fileData->setData($fileContents);

      $writtenMandateFileName = $mandateReference;
      $extension = Util::fileExtensionFromMimeType($mimeType);
      if (!empty($extension)) {
        $writtenMandateFileName .= '.' . $extension;
      }

      $writtenMandate
        ->setMimeType($mimeType)
        ->setSize($uploadFile->getSize())
        ->setFileName($writtenMandateFileName);

      $this->persist($writtenMandate);
    }

    // set the new values
    $debitMandate->setSepaBankAccount($bankAccount)
                 ->setMandateDate($mandateDate)
                 ->setNonRecurring($mandateNonRecurring)
                 ->setMandateReference($mandateReference)
                 ->setLastUsedDate($mandateLastUsedDate)
                 ->setWrittenMandate($writtenMandate);

    do {
      try {
        // try persist with increasing sequence until we succeed
        $this->debitMandatesRepository->persist($debitMandate);
        $this->flush();
      } catch (UniqueConstraintViolationException $e) {
        if ($debitMandate->inUse()) {
          $this->logException($e);
          return self::grumble($this->l->t('Unable to modify already used debit-mandate.'));
        }
        $this->entityManager->reopen();
        $debitMandate->setSequence(null);
      }
    } while ($debitMandate->getSequence() === null);

    if (!empty($uploadFile)) {
      $uploadFile->delete();
    }

    if (!empty($writtenMandateFileUpload)) {
      // make sure the user-folder exists
      $this->projectService->ensureParticipantFolder($debitMandate->getProject(), $debitMandate->getMusician(), dry: false);
    }

    $responseData = [
      'message' => [
        $this->l->t('Successfully stored the bank-account with IBAN "%s" and owner "%s"',
                    [ $bankAccount->getIban(), $bankAccount->getBankAccountOwner()]),
        $this->l->t('Successfully stored the debit-mandate with reference "%s" for the IBAN "%s".',
                    [ $debitMandate->getMandateReference(), $bankAccount->getIban()]),
      ],
      'projectId' => $projectId,
      'musicianId' => $musicianId,
      'bankAccountSequence' => $bankAccount->getSequence(),
      'mandateSequence' => $debitMandate->getSequence(),
      'mandateReference' => $debitMandate->getMandateReference(),
    ];

    return self::dataResponse($responseData);
  }

  /**
   * Pre-fill the configured PDF-form with the values of the
   * form-element.
   *
   * @NoAdminRequired
   */
  public function preFilledMandateForm(
    $projectId
    , $musicianId
    , $bankAccountSequence
  ) {

    if (empty($projectId)) {
      /** @var Entities\Musician $musician */
      $musician = $this->getDatabaseRepository(Entities\Musician::class)->find($musicianId);
      $clubMembersProjectId = $this->getClubMembersProjectId();
      if ($musician->isMemberOf($clubMembersProjectId)) {
        $projectId = $clubMembersProjectId;
      } else {
        return self::grumble(
          $this->l->t(
            'General debit-mandate requested but musician "%s" is not a club member.',
            $musician->getPublicName()));
      }
    }

    list($formData, $mimeType, $filename) = $this->financeService->preFilledDebitMandateForm(
      $bankAccountSequence, $projectId, $musicianId, $bankAccountSequence);

    if (empty($formData)) {
      return self::grumble($this->l->t('Unable to find fillable debit mandate form.'));
    }

    return $this->dataDownloadResponse($formData, $fileName, $mimeType);
  }

  /**
   * @NoAdminRequired
   */
  public function mandateDelete($musicianId, $mandateSequence)
  {
    return $this->handleMandateRevocation($musicianId, $mandateSequence, 'delete');
  }

  /**
   * @NoAdminRequired
   */
  public function mandateDisable($musicianId, $mandateSequence)
  {
    return $this->handleMandateRevocation($musicianId, $mandateSequence, 'disable');
  }

  /**
   * @NoAdminRequired
   */
  public function mandateReactivate($musicianId, $mandateSequence)
  {
    return $this->handleMandateRevocation($musicianId, $mandateSequence, 'reactivate');
  }

  /**
   * @NoAdminRequired
   */
  public function mandateHardcopy($operation, $musicianId, $mandateSequence)
  {
    switch ($operation) {
      case self::HARDCOPY_ACTION_UPLOAD:
        // we mis-use the participant-data upload form, so the actual identifiers
        // are in the "data" parameter and have to be remapped.
        $data = $this->parameterService['data'];
        $uploadData = json_decode($data, true);
        $musicianId = $uploadData['fieldId'];
        $mandateSequence = $uploadData['optionKey'];
        $files = $this->parameterService['files'];
        break;
      case self::HARDCOPY_ACTION_DELETE:
        $mandateSequence = $this->parameterService['optionKey'];
        break;
    }

    $requiredKeys = [ 'musicianId', 'mandateSequence' ];
    foreach ($requiredKeys as $required) {
      if (empty(${$required})) {
        return self::grumble($this->l->t('Required information "%s" not provided.', $required));
      }
    }

    /** @var Entities\SepaDebitMandate $mandate */
    $debitMandate = $this->debitMandatesRepository->find([ 'musician' => $musicianId, 'sequence' => $mandateSequence ]);

    if (empty($debitMandate)) {
      return self::grumble($this->l->t('Unable to find mandate for musician id "%1$d" with sequence "%2$d".', [ $musicianId, $mandateSequence ]));
    }
    $mandateReference = $debitMandate->getMandateReference();

    switch ($operation) {
      case self::HARDCOPY_ACTION_UPLOAD:
        // the following should be made a service routine or Trait

        $files = $this->prepareUploadInfo($files, $mandateSequence, multiple: false);
        if ($files instanceof Http\Response) {
          // error generated
          return $files;
        }

        $file = array_shift($files); // only one
        if ($file['error'] != UPLOAD_ERR_OK) {
          return self::grumble($this->l->t('Upload error "%s".', $file['str_error']));
        }

        // Ok, got it, set or replace the hard-copy file
        $fileContent = $this->getUploadContent($file);

        /** @var \OCP\Files\IMimeTypeDetector $mimeTypeDetector */
        $mimeTypeDetector = $this->di(\OCP\Files\IMimeTypeDetector::class);

        $conflict = null;
        $writtenMandate = $debitMandate->getWrittenMandate();
        if (empty($writtenMandate)) {
          $writtenMandate = new Entities\EncryptedFile;
          $fileData = new Entities\EncryptedFileData;
          $fileData->setFile($writtenMandate);
          $writtenMandate->setFileData($fileData);
        } else {
          $conflict = 'replaced';
          $fileData = $writtenMandate->getFileData();
        }

        $mimeType = $mimeTypeDetector->detectString($fileContent);
        $fileData->setData($fileContent);

        $writtenMandateFileName = $mandateReference;
        $extension = Util::fileExtensionFromMimeType($mimeType);
        if (!empty($extension)) {
          $writtenMandateFileName .= '.' . $extension;
        }

        $writtenMandate
          ->setMimeType($mimeType)
          ->setSize(strlen($fileContent))
          ->setFileName($writtenMandateFileName);

        $this->entityManager->beginTransaction();
        try {
          $this->persist($writtenMandate);
          $debitMandate->setWrittenMandate($writtenMandate);
          $this->flush();

          $this->entityManager->commit();
        } catch (\Throwable $t) {
          $this->logException($t);
          $this->entityManager->rollback();
          $exceptionChain = $this->exceptionChainData($t);
          $exceptionChain['message'] =
            $this->l->t('Error, caught an exception. No changes were performed.');
          return self::grumble($exceptionChain);
        }

        $this->removeStashedFile($file);

        $downloadLink = $this->urlGenerator()->linkToRoute($this->appName().'.downloads.get', [
          'section' => 'database',
          'object' => $writtenMandate->getId(),
        ])
          . '?requesttoken=' . urlencode(\OCP\Util::callRegister())
          . '&fileName=' . urlencode($writtenMandateFileName);

        unset($file['tmp_name']);
        $file['message'] = $this->l->t('Upload of "%s" as "%s" successful.',
                                       [ $file['name'], $writtenMandateFileName ]);
        $file['name'] = $writtenMandateFileName;

        $pathInfo = pathinfo($writtenMandateFileName);

        $this->projectService->ensureParticipantFolder($debitMandate->getProject(), $debitMandate->getMusician(), dry: false);

        $file['meta'] = [
          'musicianId' => $musicianId,
          'projectId' => $debitMandate->getProject()->getId(),
          // 'pathChain' => $pathChain, ?? needed ??
          'dirName' => $pathInfo['dirname'],
          'baseName' => $pathInfo['basename'],
          'extension' => $pathInfo['extension']?:'',
          'fileName' => $pathInfo['filename'],
          'download' => $downloadLink,
          'conflict' => $conflict,
          'messages' => $file['message'],
        ];

        return self::dataResponse([ $file ]);
      case self::HARDCOPY_ACTION_DELETE:
        $writtenMandate = $debitMandate->getWrittenMandate();
        if (empty($writtenMandate)) {
          // ok, it is not there ...
          return self::response($this->l->t('We have no hard-copy of the written-mandate for "%1$s", so we cannot delete it.', $mandateReference));
        }
        if (!$debitMandate->unused()) {
          return self::grumble($this->l->t('The debit mandate "%1$s" is already in use, the hard-copy of the written-mandate may only be replaced, but not deleted.', $mandateReference));
        }

        // ok, delete it
        $debitMandate->setWrittenMandate(null);
        $this->remove($writtenMandate, flush: true);

        return self::response($this->l->t('Successfully deleted the hard-copy of the written-mandate for "%1$s", please upload a new one!', $mandateReference));
    }
    return self::grumble($this->l->t('UNIMPLEMENTED'));
  }

  private function handleMandateRevocation($musicianId, $mandateSequence, $operation)
  {
    $requiredKeys = [ 'musicianId', 'mandateSequence' ];
    foreach ($requiredKeys as $required) {
      if (empty(${$required})) {
        return self::grumble($this->l->t('Required information "%s" not provided.', $required));
      }
    }

    $this->disableFilter('soft-deleteable');
    /** @var Entities\SepaDebitMandate $mandate */
    $mandate = $this->debitMandatesRepository->find([ 'musician' => $musicianId, 'sequence' => $mandateSequence ]);
    $reference = $mandate->getMandateReference();

    switch ($operation) {
    case 'delete':
      $this->remove($mandate, true);
      break;
    case 'disable':
      if (!empty($mandate->getDeleted())) {
        return self::grumble($this->l->t('SEPA debit mandate with reference "%s" is already disabled.', $reference));
      }
      $mandate->setDeleted('now');
      $this->flush();
      break;
    case 'reactivate':
      if (empty($mandate->getDeleted())) {
        return self::grumble($this->l->t('SEPA debit mandate with reference "%s" is already active.', $reference));
      }
      $mandate->setDeleted(null);
      $this->flush();
      break;
    default:
      return self::grumble($this->l->t('Unknown revocation action: "%s".', $operation));
    }

    if ($this->entityManager->contains($mandate)) {
      if (!empty($mandate->getDeleted())) {
        $message = $this->l->t('SEPA debit mandate with reference "%s" has been invalidated.', $reference);
      } else {
        $message = $this->l->t('SEPA debit mandate with reference "%s" has been reactivated.', $reference);
      }
    } else {
      $message = $this->l->t('SEPA debit mandate with reference "%s" has been deleted.', $reference);
    }

    return self::response($message);
  }

  /**
   * @NoAdminRequired
   */
  public function accountDelete($musicianId, $bankAccountSequence)
  {
    return $this->handleAccountRevocation($musicianId, $bankAccountSequence, 'delete');
  }

  /**
   * @NoAdminRequired
   */
  public function accountDisable($musicianId, $bankAccountSequence)
  {
    return $this->handleAccountRevocation($musicianId, $bankAccountSequence, 'disable');
  }

  /**
   * @NoAdminRequired
   */
  public function accountReactivate($musicianId, $bankAccountSequence)
  {
    return $this->handleAccountRevocation($musicianId, $bankAccountSequence, 'reactivate');
  }

  private function handleAccountRevocation($musicianId, $bankAccountSequence, $action)
  {
    $requiredKeys = [ 'musicianId', 'bankAccountSequence' ];
    foreach ($requiredKeys as $required) {
      if (empty(${$required})) {
        return self::grumble($this->l->t("Required information `%s' not provided.", $required));
      }
    }

    $this->disableFilter('soft-deleteable');

    /** @var Entities\SepaBankAccount $account */
    $account = $this->bankAccountsRepository->find([ 'musician' => $musicianId, 'sequence' => $bankAccountSequence ]);
    $iban = $account->getIban();

    // enclose into a transaction as a "bunch" (one or two ...) mandates may be affected.
    $this->entityManager->beginTransaction();
    try {

      $affectedMandates = [];
      switch ($action) {
      case 'delete':
        /** @var Entities\SepaDebitMandate $mandate */
        foreach ($account->getSepaDebitMandates() as $mandate) {
          if (empty($mandate->getDeleted())) {
            $affectedMandates[] = $mandate->getMandateReference();
          }
        }
        if (!empty($affectedMandates)) {
          return self::grumble($this->l->t('The account with IBAN "%s" cannot be deleted as the following associated mandates are still active: %s.', [ $iban, implode(', ', $affectedMandates) ]));
        }
        // Ok, no active mandate, try to delete the deactivated mandates

        /** @var Entities\SepaDebitMandate $mandate */
        foreach ($account->getSepaDebitMandates() as $mandate) {
          $this->remove($mandate);
        }
        $this->remove($account);
        $this->flush();
        break;
      case 'disable':
        if (!empty($account->getDeleted())) {
          return self::grumble($this->l->t('Bank account with IBAN "%s" is already disabled.', $iban));
        }
        /** @var Entities\SepaDebitMandate $mandate */
        foreach ($account->getSepaDebitMandates() as $mandate) {
          if (empty($mandate->getDeleted())) {
            $affectedMandates[] = $mandate->getMandateReference();
          }
        }
        if (!empty($affectedMandates)) {
          return self::grumble($this->l->t('The account with IBAN "%s" cannot be disabled as the following associated mandates are still active: %s.', [ $iban, implode(', ', $affectedMandates) ]));
        }
        $account->setDeleted('now');
        $this->flush();
        break;
      case 'reactivate':
        if (empty($account->getDeleted())) {
          return self::grumble($this->l->t('Bank account with IBAN "%s" is already active.', $iban));
        }
        $account->setDeleted(null);
        $this->flush();
        break;
      default:
        return self::grumble($this->l->t('Unknown revocation action: "%s".', $action));
      }

      $this->entityManager->commit();
    } catch (\Throwable $t) {
      $this->logException($t);
      $this->entityManager->rollback();
      $exceptionChain = $this->exceptionChainData($t);
      $exceptionChain['message'] =
        $this->l->t('Error, caught an exception. No changes were performed.');
      return self::grumble($exceptionChain);
    }

    $messages = [];
    if ($this->entityManager->contains($account)) {
      if (!empty($account->getDeleted())) {
        $messages[] = $this->l->t('Bank account with IBAN "%s" has been invalidated.', $iban);
      } else {
        $messages[] = $this->l->t('Bank account with IBAN "%s" has been reactivated.', $iban);
        $messages[] = $this->l->t('Please note that associated debit-mandates need to be reactivated separately, they are still disabled.');
      }
    } else {
      $messages[] = $this->l->t('Bank account with IBAN "%s" has been deleted.', $iban);
    }

    return self::response($messages);
  }

}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
