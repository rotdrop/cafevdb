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
    if (empty($projectOrId) || (int)$projectOrId < 0) {
      return null;
    }
    if (!($projectOrId instanceof Entities\Project)) {
      return $this->entityManager->getReference(Entities\Project::class, [ 'id' => $projectOrId, ]);
    } else {
      return $projectOrId;
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
    if (empty($musicianOrId) || (int)$musicianOrId < 0) {
        return null;
    }
    if (!($musicianOrId instanceof Entities\Musician)) {
      return $this->entityManager->getReference(Entities\Musician::class, [ 'id' => $musicianOrId, ]);
    } else {
      return $musicianOrId;
    }
  }
}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
