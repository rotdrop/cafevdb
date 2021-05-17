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

use Doctrine\Common\Collections\Collection;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use \DateTimeImmutable as DateTime;

/**
 * Generate recurring receivables and manifest them as recurring
 * Entities\ProjectParticipantField entity. The two prominent examples are
 * membership fees and instrument insurances.
 */
interface IRecurringReceivablesGenerator
{
  const GENERATOR_LABEL = '__generator__';

  /**
   * @var During update of receivables just replace any old value by the
   * newly computed value.
   */
  const UPDATE_STRATEGY_REPLACE = 'replace';

  /**
   * @var During update of receivables compare with the newly computed
   * value and throw an exception if the values differ. This is the
   * default.
   */
  const UPDATE_STRATEGY_EXCEPTION = 'exception';

  /**
   * Bind this instance to the given entity. The idea is to have a
   * constructor which allowes for dependency injection. This,
   * however, means that the DB entities must not be passed through
   * the constructor.
   */
  public function bind(Entities\ProjectParticipantField $serviceFeeField);

  /**
   * Update the list of receivables for the bound service-fee field,
   * for example by generating fields up to the current date. The link
   * to the actual Entities\ProjectParticipantField entity is
   * established by a prior call to $this->bind($serviceFeeField).
   * The generated receivables may not yet have been persisted.
   *
   * @return Collection Collection of
   * Entities\ProjectParticipantFieldDataOption entities covering all
   * relevant receivables.
   */
  public function generateReceivables():Collection;

  /**
   * Update the amount to invoice for bound service-fee field.
   *
   * @param Entities\ProjectParticipantFieldDataOption $receivable
   *   The option to update/recompute.
   *
   * @param null|Entities\ProjectParticipant $participant
   *   The musician to update the service claim for. If null, the
   *   values for all affected musicians have to be recomputed.
   *
   * @return array<string, int>
   * ```
   * [ 'added' => #ADDED, 'removed' => #REMOVED, 'changed' => #CHANGED ]
   * ```
   * where the numbers reflect the respective actions performed on the
   * field-data for the given option.
   */
  public function updateReceivable(Entities\ProjectParticipantFieldDataOption $receivable, ?Entities\ProjectParticipant $participant = null, $updateStrategy = self::UPDATE_STRATEGY_EXCEPTION):array;

  /**
   * Update the amount to invoice for the bound service-fee field.
   *
   * @param Entities\ProjectParticipant $participant
   *   The musician to update the service claim for.
   *
   * @param null|Entities\ProjectParticipantFieldDataOption $receivable
   *   The option to update/recompute. If null all options have to be updated.
   *
   * @return array<string, int>
   * ```
   * [ 'added' => #ADDED, 'removed' => #REMOVED, 'changed' => #CHANGED ]
   * ```
   * where the numbers reflect the respective actions performed on the
   * field-data for the participant.
   */
  public function updateParticipant(Entities\ProjectParticipant $participant, ?Entities\ProjectParticipantFieldDataOption $receivable, $updateStrategy = self::UPDATE_STRATEGY_EXCEPTION):array;

  /**
   * Compute the amounts to invoice for all relevant musicians and
   * existing receivables. New receivables are not added.
   *
   * @return array<string, int>
   * ```
   * [ 'added' => #ADDED, 'removed' => #REMOVED, 'changed' => #CHANGED ]
   * ```
   * where the number reflect the respective actions performed on the
   * field-data for all participants.
   */
  public function updateAll($updateStrategy = self::UPDATE_STRATEGY_EXCEPTION):array;
}
