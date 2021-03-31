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

use OCA\CAFEVDB\Database\EntityManager;
use Doctrine\Common\Collections\Collection;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;

/**
 * Do nothing implementation to have something implementing
 * the interface. Would rather belong to a test-suite.
 */
class DoNothingReceivablesGenerator extends AbstractReceivablesGenerator
{
  public function __construct(EntityManager $entityManager) {
    parent::__construct($entityManager);
  }

  /**
   * {@inheritdoc}
   */
  public function generateReceivables():Collection
  {
    // This is the dummy implementation, just do nothing.
    return $this->serviceFeeField->getDataOptions();
  }

  /**
   * {@inheritdoc}
   */
  public function updateReceivable(Entities\ProjectExtraFieldDataOption $receivable, ?Entities\ProjectParticipant $participant = NULL):Entities\ProjectExtraFieldDataOption
  {
    // This is the dummy implementation, just do nothing.
    return $receivable;
  }
}
