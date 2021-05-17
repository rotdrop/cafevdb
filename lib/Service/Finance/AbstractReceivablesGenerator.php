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

abstract class AbstractReceivablesGenerator implements IRecurringReceivablesGenerator
{
  use \OCA\CAFEVDB\Traits\EntityManagerTrait;

  /** @var Entities\ProjectParticipantField */
  protected $serviceFeeField;

  public function __construct(EntityManager $entityManager) {
    $this->entityManager = $entityManager;
  }

  /**
   * {@inheritdoc}
   */
  public function bind(Entities\ProjectParticipantField $serviceFeeField)
  {
    $this->serviceFeeField = $serviceFeeField;
  }

  /**
   * {@inheritdoc}
   */
  public function updateAll($updateStrategy = self::UPDATE_STRATEGY_EXCEPTION):array
  {
    $added = $removed = $changed = 0;
    foreach ($this->serviceFeeField->getSelectableOptions() as $receivable) {
      list('added' => $a, 'removed' => $r, 'changed' => $c) =
                   $this->updateReceivable($receivable);
      $added += $a;
      $removed += $r;
      $changed += $c;
    }
    return [ 'added' => $added, 'removed' => $removed, 'changed' => $changed ];
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
  public function updateReceivable(Entities\ProjectParticipantFieldDataOption $receivable, ?Entities\ProjectParticipant $participant = null, $updateStrategy = self::UPDATE_STRATEGY_EXCEPTION):array
  {
    if (!empty($participant)) {
      list('added' => $added, 'removed' => $removed, 'changed' => $changed) =
                   $this->updateOne($receivable, $participant, $updateStrategy);
    } else {
      $participants = $receivable->getField()->getProject()->getParticipants();
      $added = $removed = $changed = 0;
      /** @var Entities\ProjectParticipant $participant */
      foreach ($participants as $participant) {
        list('added' => $a, 'removed' => $r, 'changed' => $c) =
                     $this->updateOne($receivable, $participant, $updateStrategy);
        $added += $a;
        $removed += $r;
        $changed += $c;
      }
    }
    return [ 'added' => $added, 'removed' => $removed, 'changed' => $changed ];
  }

  /**
   * {@inheritdoc}
   */
  public function updateParticipant(Entities\ProjectParticipant $participant, ?Entities\ProjectParticipantFieldDataOption $receivable, $updateStrategy = self::UPDATE_STRATEGY_EXCEPTION):array
  {
    if (!empty($receivable)) {
      list('added' => $added, 'removed' => $removed, 'changed' => $changed) =
                   $this->updateOne($receivable, $participant, $updateStrategy);
    } else {
      $added = $removed = $changed = 0;
      foreach ($this->serviceFeeField->getSelectableOptions() as $receivable) {
        list('added' => $a, 'removed' => $r, 'changed' => $c) =
                     $this->updateOne($receivable, $participant, $updateStrategy);
        $added += $a;
        $removed += $r;
        $changed += $c;
      }
    }
    return [ 'added' => $added, 'removed' => $removed, 'changed' => $changed ];
  }

}
