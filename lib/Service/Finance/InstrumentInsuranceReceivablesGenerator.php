<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2011-2016, 2020, 2021, 2022 Claus-Justus Heine
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

use \RuntimeException;
use \DateTimeImmutable as DateTime;

use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Wrapped\Doctrine\Common\Collections\Collection;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Database\Doctrine\ORM\Repositories;
use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Service\ToolTipsService;
use OCA\CAFEVDB\Service\ProgressStatusService;
use OCA\CAFEVDB\Common\Uuid;
use OCA\CAFEVDB\Common\Util;
use OCA\CAFEVDB\Exceptions;

use OCA\CAFEVDB\Common\Functions;
use OCA\CAFEVDB\Database\Doctrine\Util as DBUtil;

use OCA\CAFEVDB\Storage\Database\Factory as StorageFactory;
use OCA\CAFEVDB\Storage\Database\ProjectParticipantsStorage;

/**
 * Do nothing implementation to have something implementing
 * the interface. Would rather belong to a test-suite.
 */
class InstrumentInsuranceReceivablesGenerator extends AbstractReceivablesGenerator
{
  use \OCA\CAFEVDB\Traits\ConfigTrait;
  use \OCA\CAFEVDB\Traits\EntityTranslationTrait;

  /** @var InstrumentInsuranceService */
  private $insuranceService;

  /** @var Repositories\InstrumentInsurancesRepository */
  private $insurancesRepository;

  /** @var StorageFactory */
  private $storageFactory;

  /** @var ToolTipsService */
  protected $toolTipsService;

  /** @var \DateTimeZone */
  private $timeZone;

  /** {@inheritdoc} */
  public function __construct(
    ConfigService $configService,
    InstrumentInsuranceService $insuranceService,
    ToolTipsService $toolTipsService,
    EntityManager $entityManager,
    ProgressStatusService $progressStatusService,
    StorageFactory $storageFactory,
  ) {
    parent::__construct($entityManager, $progressStatusService);

    $this->insuranceService = $insuranceService;
    $this->configService = $configService;
    $this->storageFactory = $storageFactory;
    $this->l = $this->l10n();
    $this->toolTipsService = $toolTipsService;

    $this->insurancesRepository = $this->getDatabaseRepository(Entities\InstrumentInsurance::class);
    $this->timeZone = $this->getDateTimeZone();
  }

  /**
   * {@inheritdoc}
   */
  public static function slug():string
  {
    return self::t('insurance');
  }

  /**
   * {@inheritdoc}
   */
  public function generateReceivables():Collection
  {
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
      array_merge([0], range($startingYear, $endingYear)));

    foreach ($years as $year) {
      if ($year == '0000') {
        $labelText = $this->l->t($labelTemplate = 'Opening Balance');
        $tooltipTemplate = $this->toolTipsService['instrument-insurance:opening-balance']??'';
        $tooltipText = $this->l->t($tooltipTemplate);
      } else {
        $labelText = $this->l->t($labelTemplate = 'Insurance Fee %d', $year);
        $tooltipTemplate = $this->toolTipsService['instrument-insurance:annual-service-fee']??'';
        $tooltipText = $this->l->t($tooltipTemplate);
      }
      $yearReceivables = $receivableOptions->matching(self::criteriaWhere(['data' => (string)$year]));
      if ($yearReceivables->isEmpty()) {
        // add a new option
        $receivable = (new Entities\ProjectParticipantFieldDataOption)
                    ->setField($this->serviceFeeField)
                    ->setKey(Uuid::create())
                    ->setLabel($labelText)
                    ->setToolTip($tooltipText)
                    ->setData($year) // may change in the future
                    ->setLimit(null); // may change in the future
        $receivableOptions->set($receivable->getKey()->getBytes(), $receivable);
      } else {
        // update display things, but keep the essential data untouched
        /** @var Entities\ProjectParticipantFieldDataOption $receivable */
        $receivable = $yearReceivables->first();
        $receivable->setLabel($labelText)
                   ->setTooltip($tooltipText);
      }
      $this->translate($receivable, 'label', null, sprintf($labelTemplate, $year))
           ->translate($receivable, 'tooltip', null, $tooltipTemplate);
    }
    return $this->serviceFeeField->getSelectableOptions();
  }

  /**
   * {@inheritdoc}
   */
  protected function updateOne(Entities\ProjectParticipantFieldDataOption $receivable, Entities\ProjectParticipant $participant, $updateStrategy = self::UPDATE_STRATEGY_EXCEPTION):array
  {
    // cook-book:
    // * find list of insurance years
    // * walk years from start until now
    //   - add missing items if insurance fee != 0
    //   - remove items without payment when insurance fee == 0
    //   - update all existing items with newly computed insurance sum

    $year = $receivable->getData();

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
      $fee = $this->insuranceService->insuranceFee($musician, $referenceDate, $dueInterval);

      // Generate the overview letter as supporting document
      // @todo: use new OpenDocument stuff
      $overview = $this->insuranceService->musicianOverview($musician, $referenceDate);
      $overviewFilename = $this->insuranceService->musicianOverviewFileName($overview);
      $overviewLetter = $this->insuranceService->musicianOverviewLetter($overview);
    } else {
      if (0 == count($this->insuranceService->billableInsurances($musician))) {
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
    if (empty($datum)) {
      if ($openingBalance || $fee != 0.0) {
        // add a new option
        /** @var Entities\ProjectParticipantFieldDatum $datum */
        $datum = (new Entities\ProjectParticipantFieldDatum)
               ->setDataOption($receivable)
               ->setProjectParticipant($participant)
               ->setOptionValue($fee);

        if (!$openingBalance) {
          // store overview letter
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
      $optionValue = (float)$datum->getOptionValue();
      if (!$datum->isDeleted() && $fee != $optionValue) {
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
        if ($fee == 0.0) {
          // remove current option
          $this->remove($datum);
          $this->remove($datum);
          $participantFieldsData->removeElement($datum);
          $musician->getProjectParticipantFieldsData()->removeElement($datum);
          $receivable->getFieldData()->removeElement($datum);
          $project->getParticipantFieldsData()->removeElement($datum);
          $removed = true;
        } else {
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
          } elseif (true || $fee != $datum->getOptionValue()) {
            // @todo FIXME: only update letter if fee changes?
            $supportingDocument
              ->setName($overviewFilename)
              ->getFile()
              ->setFileName($overviewFilename)
              ->setMimeType('application/pdf')
              ->setSize(strlen($overviewLetter))
              ->getFileData()->setData($overviewLetter);
          }
          // just update current data to the computed value
          if ($datum->isDeleted()) {
            $datum->setDeleted(null);
            $datum->setOptionValue($fee);
            $added = true;
          } elseif ($fee != $datum->getOptionValue()) {
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

    /**
   * {@inheritdoc}
   */
  public function dueDate(?Entities\ProjectParticipantFieldDataOption $receivable = null):?\DateTimeInterface
  {
    $timeZone = $this->getDateTimeZone();
    if ($receivable === null) {
      $year = (int)(new DateTime)->setTimezone($timeZone)->format('Y');
    } else {
      $year = (int)$receivable->getData();
      if ($year == 0) {
        return null;
      }
    }
    $dueDate = $this->serviceFeeField->getDueDate()->setTimezone($timeZone);
    $dueYear = (int)$dueDate->format('Y');
    return $dueDate->modify('+'.($year - $dueYear).' years');
  }
}
