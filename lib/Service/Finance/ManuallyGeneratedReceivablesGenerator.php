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

use OCP\ILogger;
use OCP\IL10N;

use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Wrapped\Doctrine\Common\Collections\Collection;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Service\ProgressStatusService;
use OCA\CAFEVDB\Service\ToolTipsService;
use OCA\CAFEVDB\Common\Uuid;

/**
 * Do nothing implementation to have something implementing
 * the interface. Would rather belong to a test-suite.
 */
class ManuallyGeneratedReceivablesGenerator extends AbstractReceivablesGenerator
{
  use \OCA\CAFEVDB\Traits\LoggerTrait;

  /** @var ToolTipsService */
  protected $toolTipsService;

  public function __construct(
    EntityManager $entityManager
    , ProgressStatusService $progressStatusService
    , ToolTipsService $toolTipsService
    , ILogger $logger
    , IL10N $l10n
  ) {
    parent::__construct($entityManager, $progressStatusService);
    $this->toolTipsService = $toolTipsService;
    $this->logger = $logger;
    $this->l = $l10n;
  }

  /**
   * {@inheritdoc}
   */
  static public function slug():string
  {
    return self::t('manually');
  }

  /**
   * {@inheritdoc}
   */
  static public function updateStrategyChoices():array
  {
    return [ self::UPDATE_STRATEGY_SKIP ];
  }

  /**
   * {@inheritdoc}
   */
  static public function operationLabels(?string $slug = null)
  {
    // t('blah')
    $labels = [
      self::OPERATION_OPTION_REGENERATE => false,
      self::OPERATION_OPTION_REGENERATE_ALL => self::t('New Input-Option'),
      self::OPERATION_GENERATOR_RUN => true,
      self::OPERATION_GENERATOR_REGENERATE => false,
    ];
    return $slug === null ? $labels : $labels[$slug]??null;
  }

  /**
   * {@inheritdoc}
   */
  public function generateReceivables():Collection
  {
    // Strategy: For all participants subscribe exactly one empty
    // option. I.e. there is always a list of subscribed used options + one
    // more empty option which then can be edited in the per-musician input masks.

    /** @var Entities\ProjectParticipant $participant */
    foreach ($this->serviceFeeField->getProject()->getParticipants() as $participant) {
      $this->updateParticipant($participant, null);
    }

    // This is the dummy implementation, just do nothing.
    return $this->serviceFeeField->getSelectableOptions();
  }

  /**
   * {@inheritdoc}
   *
   * This will also make sure that there is always one extra emtpy field
   * available for the respective participant. If $receivable is non-null,
   * only this receivable-data will be removed if no longer needed.
   */
  public function updateParticipant(Entities\ProjectParticipant $participant, ?Entities\ProjectParticipantFieldDataOption $receivable, $updateStrategy = self::UPDATE_STRATEGY_EXCEPTION):array
  {
    $fieldDataOptions = $this->serviceFeeField->getDataOptions();
    $participantFieldsData = $participant->getParticipantFieldsData();
    $emptyFieldData = $participantFieldsData->matching(self::criteriaWhere([
      'field' => $this->serviceFeeField,
      '&(|optionValue' => null,
      'optionValue' => '',
      ')' => null,
    ]));

    $added = false;
    $removed = 0;
    if (!empty($receivable)) {
      // dedicated receivables update ... just update this one

      /** @var Entities\ProjectParticipantFieldDatum $fieldDatum */
      $fieldData = $receivable->getMusicianFieldData($participant->getMusician());
      foreach ($fieldData as $fieldDatum) {
        if (empty($fieldDatum->getOptionValue()) && $emptyFieldData->count() > 1) {
          $this->remove($fieldDatum);
          $this->remove($fieldDatum);
          $participantFieldsData->removeElement($fieldDatum);
          $participant->getMusician()->getProjectParticipantFieldsData()->removeElement($fieldDatum);
          $fieldDatum->getDataOption()->getParticipnatFieldsData()->removeElement($fieldDatum);
          $emptyFieldData->removeElement($fieldDatum);
          ++$removed;
        }
      }

      return [
        'added' => (int)$added,
        'removed' => $removed,
        'changed' => 0, // never
        'skipped' => 0,
        'notices' => [],
      ];
    }

    // then remove all but one empty option
    while ($emptyFieldData->count() > 1) {
      /** @var Entities\ProjectParticipantFieldDatum $fieldDatum */
      $fieldDatum = $emptyFieldData->first;
      $this->remove($fieldDatum);
      $this->remove($fieldDatum);
      $participantFieldsData->removeElement($fieldDatum);
      $participant->getMusician()->getProjectParticipantFieldsData()->removeElement($fieldDatum);
      $fieldDatum->getDataOption()->getParticipnatFieldsData()->removeElement($fieldDatum);
      $emptyFieldData->removeElement($fieldDatum);
      ++$removed;
    }

    $this->logInfo('Empty field data count: ' . $emptyFieldData->count());

    if ($emptyFieldData->isEmpty()) {

      $this->logInfo('No empty option data, generate one empty item');

      $emptyFieldOption = null;

      // if no empty option is there, add one
      //
      // loop over field options
      //  - check if field option data is set for musician
      //  - otherwise use it and break, DONE
      // if not DONE, create a new option and add it to the musician.
      /** @var Entities\ProjectParticipantFieldDataOption $fieldOption */
      foreach ($fieldDataOptions as $fieldOption) {
        if ($fieldOption->getKey() == Uuid::NIL) {
          // skip the generator
          continue;
        }
        $this->logInfo('Check option ' . (string)$fieldOption->getKey());
        if ($fieldOption->getMusicianFieldData($participant->getMusician())->isEmpty()) {
          $this->logInfo('Found unbound option ' . (string)$fieldOption->getKey() . ' for musician ' . $participant->getMusician()->getPublicName());
          $emptyFieldOption = $fieldOption;
          break;
        } else {
          $this->logInfo('Option ' . (string)$fieldOption->getKey() . ' already used by musician ' . $participant->getMusician()->getPublicName());
        }
      }

      if (empty($emptyFieldOption)) {
        $this->logInfo('No unbound option found, generate one');
        $emptyFieldOption = (new Entities\ProjectParticipantFieldDataOption)
          ->setField($this->serviceFeeField)
          ->setKey(Uuid::create())
          ->setLabel(null /*$this->l->t('Manual Data')*/)
          ->setToolTip(null /*$this->toolTipsService['recurring-recevables:manually-generated']*/)
          ->setData(null)
          ->setLimit(null);
        $fieldDataOptions->set($emptyFieldOption->getKey()->getBytes(), $emptyFieldOption);
        $this->logInfo('Number of field-options ' . $fieldDataOptions->count());
        $this->persist($emptyFieldOption);
        $this->flush();
      }

      // use it, add empty data to musician
      $datum = (new Entities\ProjectParticipantFieldDatum)
        ->setField($this->serviceFeeField)
        ->setMusician($participant->getMusician())
        ->setProject($participant->getProject())
        ->setOptionKey($emptyFieldOption->getKey());
      $participantFieldsData->set($datum->getOptionKey()->getBytes(), $datum);
      $emptyFieldOption->getFieldData()->add($datum);
      $participant->getMusician()->getProjectParticipantFieldsData()->add($datum);
      $participant->getProject()->getParticipantFieldsData()->add($datum);
      $added = true;
    }

    return [
      'added' => (int)$added,
      'removed' => $removed,
      'changed' => 0, // never
      'skipped' => 0,
      'notices' => [],
    ];
  }

  /**
   * {@inheritdoc}
   *
   * This actually will only cleanup unused (empty) field-values.
   */
  protected function updateOne(Entities\ProjectParticipantFieldDataOption $receivable, Entities\ProjectParticipant $participant, $updateStrategy = self::UPDATE_STRATEGY_EXCEPTION):array
  {
    return $this->updateParticipant($participant, $receivable, $updateStrategy);
  }
}
