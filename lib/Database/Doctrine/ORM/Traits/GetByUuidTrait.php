<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2020, 2021, 2022 Claus-Justus Heine
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

namespace OCA\CAFEVDB\Database\Doctrine\ORM\Traits;

use OCA\CAFEVDB\Wrapped\Doctrine\ORM\Mapping as ORM;

use OCA\CAFEVDB\Wrapped\Doctrine\Common\Collections\Collection;
use OCA\CAFEVDB\Wrapped\Ramsey\Uuid\UuidInterface;
use OCA\CAFEVDB\Common\Uuid;
use OCA\CAFEVDB\Database\Doctrine\Util as DBUtil;

/**
 * Select or search an element by its uuid
 */
trait GetByUuidTrait
{
  /**
   * Try to fetch one element indexed by $key, if not found, search
   * through the collection for an entry matching the key.
   *
   * @param Collection $collection
   *
   * @param mixed $key Everything which can be converted to an UUID by
   * Uuid::asUuid().
   *
   * @param string $keyField The name of the key-field in the entities
   * contained in the collection.
   *
   * @return null|mixed The single indexed or first found entity, or
   * null if no entity is found.
   */
  protected function getByUuid(Collection $collection, mixed $key, string $keyField)
  {
    $key = Uuid::asUuid($key);
    if (empty($key)) {
      return null;
    }
    $bytes = $key->getBytes();
    $datum = $collection->get($bytes);
    if (!empty($datum)) {
      return $datum;
    }

    // The "Selectable" interface always matches stricliy (===, !==) which we
    // do not want here.
    //
    // $matching = $collection->matching(DBUtil::criteriaWhere([$keyField => $key]));
    // if ($matching->count() == 1) {
    //   return $matching->first();
    // }

    foreach ($collection as $datum) {
      if ($datum[$keyField]->getBytes() == $bytes) {
        return $datum;
      }
    }

    return null;
  }
}
