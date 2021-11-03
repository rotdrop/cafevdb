<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016, 2020, 2021 Claus-Justus Heine <himself@claus-justus-heine.de>
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

namespace OCA\CAFEVDB\Service\Finance;

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

  /** @var ToolTipsService */
  protected $toolTipsService;

  /** @var \DateTimeZone */
  private $timeZone;

  public function __construct(
    ConfigService $configService
    , InstrumentInsuranceService $insuranceService
    , ToolTipsService $toolTipsService
    , EntityManager $entityManager
    , ProgressStatusService $progressStatusService
  ) {
    parent::__construct($entityManager, $progressStatusService);

    $this->insuranceService = $insuranceService;
    $this->configService = $configService;
    $this->l = $this->l10n();
    $this->toolTipsService = $toolTipsService;

    $this->insurancesRepository = $this->getDatabaseRepository(Entities\InstrumentInsurance::class);
    $this->timeZone = $this->getDateTimeZone();
  }

  /**
   * {@inheritdoc}
   */
  static public function slug():string
  {
    return 'insurance';
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
      throw new \RuntimeException(
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
    } else if (!empty($managementDate)) {
      $startingDate = $managementDate;
    } else if (empty($startingDate)) {
      $startingDate = new \DateTimeImmutable;
    }
    $startingDate = $startingDate->setTimezone($this->timeZone);
    $managementOption->setLimit($startingDate->getTimestamp());

    $startingYear = $startingDate->format('Y');
    $endingYear   = (new DateTime)->setTimezone($this->timeZone)->format('Y');

    for ($year = $startingYear; $year <= $endingYear; ++$year) {
      $labelText = $this->l->t($labelTemplate = 'Insurance Fee %d', $year);
      $tooltipTemplate = $this->toolTipsService['instrument-insurance-annual-service-fee']??'';
      $tooltipText = $this->l->t($tooltipTemplate);
      $yearReceivables = $receivableOptions->matching(self::criteriaWhere(['data' => (string)$year]));
      if ($yearReceivables->count() == 0) {
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
    /** @var Entities\Musician $musician */
    $musician = $participant->getMusician();
    /** @var Entities\Project $project */
    $project = $participant->getProject();

    // "now" should in principle just do ...
    $referenceDate = new \DateTimeImmutable($year.'-06-01');

    // Compute the actual fee
    $fee = $this->insuranceService->insuranceFee($musician, $referenceDate);

    // Generate the overview letter as supporting document
    // @todo: use new OpenDocument stuff
    $overview = $this->insuranceService->musicianOverview($musician, $referenceDate);
    $overviewFilename = $this->insuranceService->musicianOverviewFileName($overview);
    $overviewLetter = $this->insuranceService->musicianOverviewLetter($overview, $overviewFilename);

    $removed = false;
    $added = false;
    $changed = false;
    $skipped = false;
    $notices = [];

    $participantFieldsData = $participant->getParticipantFieldsData();
    $optionKey = $receivable->getKey();
    $datum = $participant->getParticipantFieldsDatum($optionKey);
    if (empty($datum)) {
      if ($fee != 0.0) {
        // add a new option
        /** @var Entities\ProjectParticipantFieldDatum $datum */
        $datum = (new Entities\ProjectParticipantFieldDatum)
               ->setField($receivable->getField())
               ->setMusician($musician)
               ->setProject($participant->getProject())
               ->setOptionKey($receivable->getKey())
               ->setOptionValue($fee);

        // store overview letter
        $supportingDocument = new Entities\EncryptedFile(
          $overviewFilename, $overviewLetter, 'application/pdf');
        $datum->setSupportingDocument($supportingDocument);

        // @todo Too much connectivity
        $participantFieldsData->set($optionKey->getBytes(), $datum);
        $musician->getProjectParticipantFieldsData()->set($optionKey->getBytes(), $datum);
        $receivable->getFieldData()->set($musician->getId(), $datum);
        $project->getParticipantFieldsData()->add($datum);
        $added = true;
      }
    } else { // !empty($datum)
      if (!$datum->isDeleted() && $fee != $datum->getOptionValue()) {
        $notices[] = $this->l->t('Data inconsistency for musician %s in year %d: old fee %s, new fee %s.', [
          $musician->getPublicName(true),
          $year,
          $this->moneyValue((float)$datum->getOptionValue()),
          $this->moneyValue($fee),
        ]);
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
          throw new \RuntimeException($this->l->t('Unknonw update strategy: "%s".', $updateStrategy));
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
          /** @var Entities\EncryptedFile $supportingDocument */
          $supportingDocument = $datum->getSupportingDocument();
          if (empty($supportingDocument)) {
            // create overview letter
            $supportingDocument = new Entities\EncryptedFile(
              $overviewFilename, $overviewLetter, 'application/pdf');
            $datum->setSupportingDocument($supportingDocument);
          } else if (true || $fee != $datum->getOptionValue()) {
            // @todo FIXME: only update letter if fee changes?
            $fileData = $supportingDocument->setFileName($overviewFilename)
                                           ->setMimeType('application/pdf')
                                           ->setSize(strlen($overviewLetter))
                                           ->getFileData();
            if (empty($fileData)) {
              $fileData = (new Entities\EncryptedFileData)->setFile($supportingDocument);
              $supportingDocument->setFileData($fileData);
            }
            $fileData->setData($overviewLetter);
          }
          // just update current data to the computed value
          if ($datum->isDeleted()) {
            $datum->setDeleted(null);
            $datum->setOptionValue($fee);
            $added = true;
          } else if ($fee != $datum->getOptionValue()) {
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
}
