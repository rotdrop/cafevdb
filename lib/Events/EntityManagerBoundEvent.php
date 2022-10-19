<?php
/**
 * Orchestra member, musician and project management application.
 *
 * @author Claus-Justus Heine
 * @copyright 2021 Claus-Justus Heine <himself@claus-justus-heine.de>
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
