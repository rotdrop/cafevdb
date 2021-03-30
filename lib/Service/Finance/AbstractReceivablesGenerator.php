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

  /** @var Entities\ProjectExtraField */
  protected $serviceFeeField;

  public function __construct(EntityManager $entityManager) {
    $this->entityManager = $entityManager;
  }

  /**
   * Bind this instance to the given entity. The idea is to have a
   * constructor which allowes for dependency injection. This,
   * however, means that the DB entities must not be passed through
   * the constructor.
   */
  public function bind(Entities\ProjectExtraField $serviceFeeField)
  {
    $this->serviceFeeField = $serviceFeeField;
  }

  /**
   * {@inheritdoc}
   */
  public function updateAll()
  {
    foreach ($this->serviceFeeField->dataOptions as $receivable) {
      $this->updateReceivable($receivable);
    }
  }
}
