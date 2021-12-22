<?php
/**
 * Orchestra member, musician and project management application.
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

namespace OCA\CAFEVDB\Events;

use OCP\EventDispatcher\Event;
use OCA\CAFEVDB\Database\EntityManager;

/**
 * Can be used to regenerate cached entities after an entity-manager
 * shoot-down.
 */
class EntityManagerBoundEvent extends Event
{
  /** @var EntityManager */
  private $entityManager;

  public function __construct(EntityManager $entityManager)
  {
    parent::__construct();
    $this->entityManager = $entityManager;
  }

  public function getEntityManager():EntityManager
  {
    return $this->entityManager;
  }
}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
