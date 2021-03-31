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
 * Entities\ProjectExtraField entity. The two prominent examples are
 * membership fees and instrument insurances.
 */
interface IRecurringReceivablesGenerator
{
  const GENERATOR_LABEL = '__generator__';

  /**
   * Update the list of receivables, for example by generating fields
   * up to the current date. The link to the actual
   * Entities\ProjectExtraField entity has to be implemented by other
   * means, e.g. in the constructor. The generated receivables may not
   * yet have been persisted.
   *
   * @return Collection Collection of
   * Entities\ProjectExtraFieldDataOption entities covering all
   * relevant receivables.
   */
  public function generateReceivables():Collection;

  /**
   * Update the amount to invoice for the given receivable.
   *
   * @param Entities\ProjectExtraFieldDataOption $receivable
   *   The option to update/recompute.
   *
   * @param null|Entities\Musician $musician
   *   The musician to update the service claim for. If null, the
   *   values for all affected musicians have to be recomputed.
   */
  public function updateReceivable(Entities\ProjectExtraFieldDataOption $receivable, ?Entities\ProjectParticipant $participant = null):Entities\ProjectExtraFieldDataOption;

  /**
   * Compute the amounts to invoice for all relevant musicians and
   * existing receivables. New receivables are not added.
   */
  public function updateAll();
}
