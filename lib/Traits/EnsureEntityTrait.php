<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016, 2020, 2021 Claus-Justus Heine <himself@claus-justus-heine.de>
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

namespace OCA\CAFEVDB\Traits;

use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;

/**
 * Convert ids to entities for some entities
 */
trait EnsureEntityTrait
{
  /**
   * Just return the argument if it is already a project entity,
   * otherwise fetch the project, repectively generate a reference.
   *
   * @param int|Entities\Project $projectOrId
   *
   * @return null|Entities\Project
   */
  protected function ensureProject($projectOrId):?Entities\Project
  {
    if ($projectOrId instanceof Entities\Project) {
      return $projectOrId;
    } else if (empty($projectOrId) || (int)$projectOrId < 0) {
      return null;
    } else {
      return $this->entityManager->getReference(Entities\Project::class, [ 'id' => $projectOrId, ]);
    }
  }

  /**
   * Just return the argument if it is already a musician entity,
   * otherwise fetch the musician, repectively generate a reference.
   *
   * @param int|Entities\Musician $musicianOrId
   *
   * @return null|Entities\Musician
   */
  protected function ensureMusician($musicianOrId):?Entities\Musician
  {
    if ($musicianOrId instanceof Entities\Musician) {
      return $musicianOrId;
    } else if (empty($musicianOrId) || (int)$musicianOrId < 0) {
      return null;
    } else {
      return $this->entityManager->getReference(Entities\Musician::class, [ 'id' => $musicianOrId, ]);
    }
  }
}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
