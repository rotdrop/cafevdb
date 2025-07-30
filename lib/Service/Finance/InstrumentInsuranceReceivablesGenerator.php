<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2011-2016, 2020-2025 Claus-Justus Heine
 * @license AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

namespace OCA\CAFEVDB\Service\Finance;

use DateTimeImmutable as DateTime;
use RuntimeException;
use UnexpectedValueException;

use OCA\CAFEVDB\Common\Functions;
use OCA\CAFEVDB\Common\RationalNumber;
use OCA\CAFEVDB\Common\Util;
use OCA\CAFEVDB\Common\Uuid;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Database\Doctrine\ORM\Repositories;
use OCA\CAFEVDB\Database\Doctrine\Util as DBUtil;
use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Exceptions;
use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Service\ProgressStatusService;
use OCA\CAFEVDB\Service\ToolTipsService;
use OCA\CAFEVDB\Settings\Admin as AdminSettings;
use OCA\CAFEVDB\Storage\Database\Factory as StorageFactory;
use OCA\CAFEVDB\Storage\Database\ProjectParticipantsStorage;
use OCA\CAFEVDB\Wrapped\Doctrine\Common\Collections\Collection;

/**
 * Do nothing implementation to have something implementing
 * the interface. Would rather belong to a test-suite.
 */
class InstrumentInsuranceReceivablesGenerator extends AbstractReceivablesGenerator
{
  use \OCA\CAFEVDB\Toolkit\Traits\BracedPlaceholderTrait;
  use \OCA\CAFEVDB\Traits\ConfigTrait;
  use \OCA\CAFEVDB\Traits\EntityTranslationTrait;

  /** @var Repositories\InstrumentInsurancesRepository */
  private $insurancesRepository;

  /** @var \DateTimeZone */
  private $timeZone;

  /** {@inheritdoc} */
  public function __construct(
    protected ConfigService $configService,
    private InstrumentInsuranceService $insuranceService,
    protected ToolTipsService $toolTipsService,
    private StorageFactory $storageFactory,
    EntityManager $entityManager,
    ProgressStatusService $progressStatusService,
  ) {
    parent::__construct($entityManager, $progressStatusService);

    $this->l = $this->l10n();

    $this->insurancesRepository = $this->getDatabaseRepository(Entities\InstrumentInsurance::class);
    $this->timeZone = $this->getDateTimeZone();
  }

  /** {@inheritdoc} */
  public static function slug():string
  {
    return self::t('insurance');
  }

  /** {@inheritdoc} */
  public static function balancingAccountSlug():?string
  {
    // TRANSLATORS: This is a slug, please keep it in camel-case without spaces.
    return self::t('InstrumentInsurances');
  }

  /** {@inheritdoc} */
  public function generateReceivables():Collection
  {
    $balancingAccountTemplate = $this->getAppValue(AdminSettings::GNU_CASH_INSTRUMENT_INSURANCE_BALANCING_ACCOUNT_KEY);
    $templateKeys = [
      // TRANSLATORS: This is a text substitution placeholder. If the target
      // TRANSLATORS: language knows the concept of casing, then please use
      // TRANSLATORS: only uppercase letters in the translation. Otherwise
      // TRANSLATORS: please use whatever else convention "usually" applies to
      // TRANSLATORS: placeholder keywords in the target language.
      'BROKER' => $this->l->t('BROKER'),
      // TRANSLATORS: This is a text substitution placeholder. If the target
      // TRANSLATORS: language knows the concept of casing, then please use
      // TRANSLATORS: only uppercase letters in the translation. Otherwise
      // TRANSLATORS: please use whatever else convention "usually" applies to
      // TRANSLATORS: placeholder keywords in the target language.
      'YEAR' => $this->l->t('YEAR'),
    ];

    $receivableOptions = $this->serviceFeeField->getDataOptions();

    /** @var Entities\ProjectParticipantFieldDataOption $managementOption */
    $managementOption = $this->serviceFeeField->getManagementOption();
    if (empty($managementOption)) {
      throw new RuntimeException(
        $this->l->t(
          'Unable to find management option for participant field "%s".',
          $this->serviceFeeField->getName()
        ));
    }
    $startingDate = $this->insurancesRepository->startOfInsurances();
    $managementDate = Util::convertToDateTime($managementOption->getLimit());

    if (!empty($managementDate) && !empty($startingDate)) {
      if ($managementDate->getTimestamp() < $startingDate->getTimestamp()) {
        $startingDate = $managementDate;
      }
    } elseif (!empty($managementDate)) {
      $startingDate = $managementDate;
    } elseif (empty($startingDate)) {
      $startingDate = new DateTime;
    }
    $startingDate = $startingDate->setTimezone($this->timeZone);
    $managementOption->setLimit($startingDate->getTimestamp());

    $startingYear = $startingDate->format('Y');
    $endingYear   = (new DateTime)->setTimezone($this->timeZone)->format('Y');

    // We (mis-)use year 0 for the initial value, if any
    $years = array_map(
      function($value) {
        return sprintf('%04d', $value);
      },
      array_merge([0], range($startingYear, $endingYear)),
    );

    // Split receivables by insurance broker
    $brokers = $this->getDatabaseRepository(Entities\InsuranceBroker::class)->findAll();

    foreach ($years as $year) {
      $legacy = false;
      /** @var Entities\InsuranceBroker $broker */
      foreach ($brokers as $broker) {
        $brokerShortName = $broker->getShortName();
        if ($year == '0000') {
          // TRANSLATORS: Parameter is a name.
          $labelText = $this->l->t($labelTemplate = 'Opening Balance: %s', $brokerShortName);
          $tooltipTemplate = $this->toolTipsService['instrument-insurance:opening-balance'] ?? '';
          $tooltipText = $this->l->t($tooltipTemplate);
        } else {
          // TRANSLATORS: First parameter is a year YYYY, second parameter a name.
          $labelText = $this->l->t($labelTemplate = 'Insurance Fee %1$d: %2$s', [ $year, $brokerShortName ]);
          $tooltipTemplate = $this->toolTipsService['instrument-insurance:annual-service-fee'] ?? '';
          $tooltipText = $this->l->t($tooltipTemplate);
        }
        // new style data with year and broker
        $data = '{"year":"' . $year . '","broker":"' . $brokerShortName . '"}';
        $yearReceivables = $receivableOptions->matching(self::criteriaWhere([
          '(|data' => $year,
          // {"year":"1234","broker":"blahblub"}
          'data' => $data,
        ]));
        if ($yearReceivables->isEmpty()) {
          // add a new option
          $receivable = (new Entities\ProjectParticipantFieldDataOption);
          $receivable->setField($this->serviceFeeField)
                     ->setKey(Uuid::create())
                     ->setLabel($labelText)
                     ->setToolTip($tooltipText)
                     ->setData($data)
                     ->setLimit(null); // may change in the future
          $receivableOptions->set($receivable->getKey()->getBytes(), $receivable);
        } else {
          if ($yearReceivables->count() > 1) {
            throw new UnexpectedValueException(
              $this->l->t(
                'Multiple insurance fee options for year "%1$s" and broker "%2$s".', [
                  $year, $brokerShortName,
                ]),
            );
          }
          // update display things and balancing account, but keep the
          // essential data untouched
          /** @var Entities\ProjectParticipantFieldDataOption $receivable */
          $receivable = $yearReceivables->first();
          if (!str_starts_with($receivable->getData(), '{')) {
            // Legacy option
            $legacy = true;
            if ($year == '0000') {
              $labelText = $this->l->t($labelTemplate = 'Opening Balance');
              $tooltipTemplate = $this->toolTipsService['instrument-insurance:opening-balance']??'';
              $tooltipText = $this->l->t($tooltipTemplate);
            } else {
              $labelText = $this->l->t($labelTemplate = 'Insurance Fee %d', $year);
              $tooltipTemplate = $this->toolTipsService['instrument-insurance:annual-service-fee']??'';
              $tooltipText = $this->l->t($tooltipTemplate);
            }
          }
          $receivable->setLabel($labelText)
                     ->setTooltip($tooltipText);
          if ($legacy) {
            $this->translate($receivable, 'label', null, sprintf($labelTemplate, $year))
                 ->translate($receivable, 'tooltip', null, $tooltipTemplate);
            break; // no need to iterate further over brokers.
          }
          if (!empty($balancingAccountTemplate)) {
            $balancingAccount = $this->replaceBracedPlaceholders(
              $balancingAccountTemplate, [
                'BROKER' => $brokerShortName,
                'YEAR' => $year,
              ],
              $templateKeys,
            );
            $receivable->setBalancingAccount($balancingAccount);
          }
          $this->translate($receivable, 'label', null, sprintf($labelTemplate, $year, $brokerShortName))
               ->translate($receivable, 'tooltip', null, $tooltipTemplate);
        }
      }
    }
    return $this->serviceFeeField->getSelectableOptions();
  }

  /**
   * Try to generate a balancing account for a legacy item without broker information.
   *
   * @param Entities\ProjectParticipantFieldDatum $receivable
   *
   * @return null|string
   */
  public function generateLegacyBalancingAccount(Entities\ProjectParticipantFieldDatum $receivable):?string
  {
    $field = $receivable->getField();
    $generatorOption = $field->getManagementOption();
    if ($generatorOption->getData() != __CLASS__) {
      return null;
    }
    $balancingAccountTemplate = $this->getAppValue(AdminSettings::GNU_CASH_INSTRUMENT_INSURANCE_BALANCING_ACCOUNT_KEY);
    if (empty($balancingAccountTemplate)) {
      return null;
    }
    $templateKeys = [
      // TRANSLATORS: This is a text substitution placeholder. If the target
      // TRANSLATORS: language knows the concept of casing, then please use
      // TRANSLATORS: only uppercase letters in the translation. Otherwise
      // TRANSLATORS: please use whatever else convention "usually" applies to
      // TRANSLATORS: placeholder keywords in the target language.
      'BROKER' => $this->l->t('BROKER'),
      // TRANSLATORS: This is a text substitution placeholder. If the target
      // TRANSLATORS: language knows the concept of casing, then please use
      // TRANSLATORS: only uppercase letters in the translation. Otherwise
      // TRANSLATORS: please use whatever else convention "usually" applies to
      // TRANSLATORS: placeholder keywords in the target language.
      'YEAR' => $this->l->t('YEAR'),
    ];
    $option = $receivable->getDataOption();
    $data = $option->getData();
    if (str_starts_with($data, '{')) {
      // non legacy case
      list('year' => $year, 'broker' => $brokerShortName) = json_decode($data, true);
    } else {
      $year = $data;
      // Just take the first insurance found in order to have some valid account. Must be cleaned up later then.
      $softDeleteableState = $this->disableFilter(EntityManager::SOFT_DELETEABLE_FILTER);
      $musician = $receivable->getMusician();
      $insurances = $musician->getInstrumentInsurances();
      $this->logInfo('NOT MY CLASS ' . __CLASS__ . ' <-> ' . $generatorOption->getData());
      if (!$insurances || $insurances->count() <= 0) {
        return null;
      }
      $brokerShortName = $insurances->first()->getBroker()->getShortName();
    }
    if (empty($year) || empty($brokerShortName)) {
      return null;
    }
    return $this->replaceBracedPlaceholders(
      $balancingAccountTemplate, [
        'YEAR' => $year,
        'BROKER' => $brokerShortName,
      ],
      $templateKeys,
    );
  }

  /** {@inheritdoc} */
  protected function updateOne(
    Entities\ProjectParticipantFieldDataOption $receivable,
    Entities\ProjectParticipant $participant,
    string $updateStrategy = self::UPDATE_STRATEGY_EXCEPTION,
  ):array {
    // cook-book:
    // * find list of insurance years
    // * walk years from start until now
    //   - add missing items if insurance fee != 0
    //   - remove items without payment when insurance fee == 0
    //   - update all existing items with newly computed insurance sum

    $data = $receivable->getData();
    if (!str_starts_with($data, '{')) {
      // legacy item
      $year = $data;
      $brokerShortName = null;
    } else {
      list('year' => $year, 'broker' => $brokerShortName) = json_decode($data, true);
    }

    $openingBalance = $year === '0000';

    $removed = false;
    $added = false;
    $changed = false;
    $skipped = false;
    $notices = [];

    /** @var Entities\Musician $musician */
    $musician = $participant->getMusician();
    /** @var Entities\Project $project */
    $project = $participant->getProject();
    /** @var ProjectParticipantsStorage $fileSystemStorage */
    $fileSystemStorage = $this->storageFactory->getProjectParticipantsStorage($participant);

    if (!$openingBalance) {
      // "now" should in principle just do ...
      $referenceDate = new DateTime($year.'-06-01');

      // Compute the actual fee
      $dueInterval = null;
      $fee = $this->insuranceService->insuranceFee($musician, $brokerShortName, $referenceDate, $dueInterval);

      // Generate the overview letter as supporting document
      $overview = $this->insuranceService->musicianOverview($musician, $brokerShortName, $referenceDate);
    } else {
      if (0 == count($this->insuranceService->billableInsurances($musician, $brokerShortName))) {
        // bail out early, DO NOT ADD an opening balance
        return [
          'added' => 0,
          'removed' => 0,
          'changed' => 0,
          'skipped' => 1,
          'notices' => [], // no insurance, no message
        ];
      }
      $fee = null;
      $updateStrategy = self::UPDATE_STRATEGY_SKIP; // set only manually
    }

    $participantFieldsData = $participant->getParticipantFieldsData();
    $optionKey = $receivable->getKey();
    $datum = $participant->getParticipantFieldsDatum($optionKey);
    /** @var RationalNumber $fee */
    if (empty($datum)) {
      if ($openingBalance || !$fee->equals(0)) {
        // add a new option
        /** @var Entities\ProjectParticipantFieldDatum $datum */
        $datum = (new Entities\ProjectParticipantFieldDatum)
               ->setDataOption($receivable)
               ->setProjectParticipant($participant)
               ->setOptionValue($fee);

        if (!$openingBalance) {
          // store overview letter
          $overviewFilename = $this->insuranceService->musicianOverviewFileName($overview);
          $overviewLetter = $this->insuranceService->musicianOverviewLetter($overview);
          $supportingDocumentFile = new Entities\EncryptedFile(
            $overviewFilename, $overviewLetter, 'application/pdf', $musician);
          $supportingDocument = $fileSystemStorage->addFieldDatumDocument($datum, $supportingDocumentFile, flush: false);
          $datum->setSupportingDocument($supportingDocument);
        }

        // @todo Too much connectivity
        $participantFieldsData->set($optionKey->getBytes(), $datum);
        $musician->getProjectParticipantFieldsData()->set($optionKey->getBytes(), $datum);
        $receivable->getFieldData()->set($musician->getId(), $datum);
        $project->getParticipantFieldsData()->add($datum);
        $added = true;
      }
    } else { // !empty($datum)
      $optionValue = $datum->getOptionValue();
      if (!$datum->isDeleted() && (!$fee || $fee->toDecimal(2) != $optionValue)) {
        if ($openingBalance) {
          $notices[] = $this->l->t('Keeping opening balance of %s.', $this->moneyValue($optionValue));
        } else {
          $notices[] = $this->l->t('Data inconsistency for musician %s in year %d: old fee %s, new fee %s.', [
            $musician->getPublicName(true),
            $year,
            $this->moneyValue($optionValue),
            $this->moneyValue($fee),
          ]);
        }
        switch ($updateStrategy) {
          case self::UPDATE_STRATEGY_REPLACE:
            break;
          case self::UPDATE_STRATEGY_EXCEPTION:
            throw new Exceptions\EnduserNotificationException(end($notices));
            break;
          case self::UPDATE_STRATEGY_SKIP:
            $skipped = true;
            break;
          default:
            throw new RuntimeException($this->l->t('Unknonw update strategy: "%s".', $updateStrategy));
        }
      }
      if (!$skipped) {
        if (!$openingBalance && $fee->equals(0)) {
          // remove current option
          $this->remove($datum);
          $this->remove($datum);
          $participantFieldsData->removeElement($datum);
          $musician->getProjectParticipantFieldsData()->removeElement($datum);
          $receivable->getFieldData()->removeElement($datum);
          $project->getParticipantFieldsData()->removeElement($datum);
          $removed = true;
        } else {
          if (!$openingBalance) {
            $overviewFilename = $this->insuranceService->musicianOverviewFileName($overview);
            $overviewLetter = $this->insuranceService->musicianOverviewLetter($overview);
            $this->logInfo('OVERVIEW ' . print_r($overview, true));
            /** @var Entities\DatabaseStorageFile $supportingDocument */
            $supportingDocument = $datum->getSupportingDocument();
            if (empty($supportingDocument)) {
              // create overview letter
              $supportingDocumentFile = new Entities\EncryptedFile(
                fileName: $overviewFilename,
                data: $overviewLetter,
                mimeType: 'application/pdf',
                owner: $musician
              );
              $supportingDocument = $fileSystemStorage->addFieldDatumDocument($datum, $supportingDocumentFile, flush: false);
              $datum->setSupportingDocument($supportingDocument);
            } elseif ($updateStrategy == self::UPDATE_STRATEGY_REPLACE || $fee->toDecimal(2) != $datum->getOptionValue()) {
              $supportingDocument
                ->setName($overviewFilename)
                ->getFile()
                ->setFileName($overviewFilename)
                ->setMimeType('application/pdf')
                ->setSize(strlen($overviewLetter))
                ->getFileData()->setData($overviewLetter);
            }
          }
          // just update current data to the computed value
          if ($datum->isDeleted()) {
            $datum->setDeleted(null);
            $datum->setOptionValue($fee);
            $added = true;
          } elseif ($fee->toDecimal(2) != $datum->getOptionValue()) {
            $datum->setOptionValue($fee);
            $changed = true;
          }
        }
      }
    }
    return [
      'added' => (int)$added,
      'removed' => (int)$removed,
      'changed' => (int)$changed,
      'skipped' => (int)$skipped,
      'notices' => $notices,
    ];
  }

  /** {@inheritdoc} */
  public function dueDate(?Entities\ProjectParticipantFieldDataOption $receivable = null):?\DateTimeInterface
  {
    $timeZone = $this->getDateTimeZone();
    if ($receivable === null) {
      $year = (int)(new DateTime)->setTimezone($timeZone)->format('Y');
    } else {
      $data = $receivable->getData();
      if (!str_starts_with($data, '{')) {
        // legacy item
        $year = $data;
      } else {
        list('year' => $year) = json_decode($data, true);
      }
      if ($year == 0) {
        return null;
      }
    }
    $dueDate = $this->serviceFeeField->getDueDate()->setTimezone($timeZone);
    $dueYear = (int)$dueDate->format('Y');
    return $dueDate->modify('+' . ($year - $dueYear) . ' years');
  }
}
