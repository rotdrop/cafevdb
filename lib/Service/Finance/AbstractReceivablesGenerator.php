<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2021 Claus-Justus Heine <himself@claus-justus-heine.de>
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

use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Service\ProgressStatusService;
use OCA\CAFEVDB\Common\IProgressStatus;
use OCA\CAFEVDB\Common\DoNothingProgressStatus;

abstract class AbstractReceivablesGenerator implements IRecurringReceivablesGenerator
{
  use \OCA\CAFEVDB\Traits\EntityManagerTrait;

  /** @var Entities\ProjectParticipantField */
  protected $serviceFeeField;

  /** @var ProgressStatusService */
  protected $progressStatusService;

  /** @var IProgressStatus */
  protected $progressStatus;

  /** @var array */
  protected $progressData;

  public function __construct(
    EntityManager $entityManager
    , ProgressStatusService $progressStatusService
  ) {
    $this->entityManager = $entityManager;
    $this->progressStatusService = $progressStatusService;
    $this->progressStatus = new DoNothingProgressStatus;
    $this->progressData = [
      'field' => null,
      'musician' => null,
      'receivable' => null,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function bind(Entities\ProjectParticipantField $serviceFeeField, $progressToken = null)
  {
    $this->serviceFeeField = $serviceFeeField;
    if (!empty($progressToken)) {
      $this->progressStatus = $this->progressStatusService->get($progressToken);
    } else {
      $this->progressStatus = new DoNothingProgressStatus;
    }
    $this->progressData['field'] = $serviceFeeField->getName();
    $this->progressStatus->update(-1, -1, $this->progressData);
  }

  /**
   * {@inheritdoc}
   */
  public function updateAll($updateStrategy = self::UPDATE_STRATEGY_EXCEPTION):array
  {
    $added = $removed = $changed = $skipped = 0;
    $notices = [];
    $receivables = $this->serviceFeeField->getSelectableOptions();
    $participants = $this->serviceFeeField->getProject()->getParticipants();
    $totals = $receivables->count() * $participants->count();
    $this->progressStatus->update(0, $totals);
    /** @var Entities\ProjectParticipantFieldDataOption $receivable */
    foreach ($receivables as $receivable) {
      list(
        'added' => $a,
        'removed' => $r,
        'changed' => $c,
        'skipped' => $s,
        'notices' => $n
      ) = $this->updateReceivable($receivable, null, $updateStrategy);
      $added += $a;
      $removed += $r;
      $changed += $c;
      $skipped += $s;
      $notices = array_merge($notices, $n);
    }
    return [
      'added' => $added,
      'removed' => $removed,
      'changed' => $changed,
      'skipped' => $skipped,
      'notices' => $notices,
    ];
  }

  /**
   * Update just the given receivable and participant.
   *
   * @param Entities\ProjectParticipantFieldDataOption $receivable
   *   The option to update/recompute.
   *
   * @param Entities\ProjectParticipant $participant
   *   The musician to update the service fee claim for.
   *
   * @return array<string, int>
   * ```
   * [ 'added' => #ADDED, 'removed' => #REMOVED, 'changed' => #CHANGED ]
   * ```
   * where of course each component is either 0 or 1.
   *
   * @throws \RuntimeException depending on $updateStrategy.
   */
  protected abstract function updateOne(Entities\ProjectParticipantFieldDataOption $receivable, Entities\ProjectParticipant $participant, $updateStrategy = self::UPDATE_STRATEGY_EXCEPTION):array;

  /**
   * {@inheritdoc}
   */
  public function updateReceivable(
    Entities\ProjectParticipantFieldDataOption $receivable
    , ?Entities\ProjectParticipant $participant = null
    , $updateStrategy = self::UPDATE_STRATEGY_EXCEPTION):array
  {
    $added = $removed = $changed = $skipped = 0;
    $notices = [];
    $this->progressData['receivable'] = $receivable->getLabel();
    if (!empty($participant)) {
      $this->progressData['musician'] = $participant->getMusician()->getPublicName(true);
      $this->progressStatus->update(0, 1, $this->progressData);
      list(
        'added' => $added,
        'removed' => $removed,
        'changed' => $changed,
        'skipped' => $skipped,
        'notices' => $notices,
      ) = $this->updateOne($receivable, $participant, $updateStrategy);
      $this->progressStatus->increment();
    } else {
      $participants = $receivable->getField()->getProject()->getParticipants();
      if ($this->progressStatus->getTarget() <= 0) {
        $this->progressStatus->update(0, $participants->count());
      }
      /** @var Entities\ProjectParticipant $participant */
      foreach ($participants as $participant) {
        $this->progressData['musician'] = $participant->getMusician()->getPublicName(true);
        $this->progressStatus->setData($this->progressData);
        list(
          'added' => $a,
          'removed' => $r,
          'changed' => $c,
          'skipped' => $s,
          'notices' => $n,
        ) = $this->updateOne($receivable, $participant, $updateStrategy);
        $this->progressStatus->increment();
        $added += $a;
        $removed += $r;
        $changed += $c;
        $skipped += $s;
        $notices = array_merge($notices, $n);
      }
    }
    return [
      'added' => $added,
      'removed' => $removed,
      'changed' => $changed,
      'skipped' => $skipped,
      'notices' => $notices,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function updateParticipant(Entities\ProjectParticipant $participant, ?Entities\ProjectParticipantFieldDataOption $receivable, $updateStrategy = self::UPDATE_STRATEGY_EXCEPTION):array
  {
    $this->progressData['musician'] = $participant->getMusician()->getPublicName(true);
    $added = $removed = $changed = $skipped = 0;
    $notices = [];
    if (!empty($receivable)) {
      $this->progressData['receivable'] = $receivable->getLabel();
      $this->progressStatus->update(0, 1, $this->progressData);
      list(
        'added' => $added,
        'removed' => $removed,
        'changed' => $changed,
        'skipped' => $skipped,
        'notices' => $notices) = $this->updateOne($receivable, $participant, $updateStrategy);
      $this->progressStatus->increment();
    } else {
      $receivables = $this->serviceFeeField->getSelectableOptions();
      if ($this->progressStatus->getTarget() <= 0) {
        $this->progressStatus->update(0, $receivables->count());
      }
      foreach ($receivables as $receivable) {
        $this->progressData['receivable'] = $receivable->getLabel();
        $this->progressStatus->setData($this->progressData);
        list(
          'added' => $a,
          'removed' => $r,
          'changed' => $c,
          'skipped' => $s,
          'notices' => $n,
        ) = $this->updateOne($receivable, $participant, $updateStrategy);
        $this->progressStatus->increment();
        $added += $a;
        $removed += $r;
        $changed += $c;
        $skipped += $s;
        $notices = array_merge($notices, $n);
      }
    }
    return [
      'added' => $added,
      'removed' => $removed,
      'changed' => $changed,
      'skipped' => $skipped,
      'notices' => $notices,
    ];
  }

}
