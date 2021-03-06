<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2020, 2021 Claus-Justus Heine <himself@claus-justus-heine.de>
 *
 * This library se Doctrine\ORM\Tools\Setup;is free software; you can redistribute it and/or
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
  protected function getByUuid(Collection $collection, $key, $keyField)
  {
    if (empty($key = Uuid::asUuid($key))) {
      return null;
    }
    $bytes = $key->getBytes();
    $datum = $collection->get($bytes);
    if (!empty($datum)) {
      return $datum;
    }

    // Unfortunately, the "Selectable" interface is ***really***
    // immature and unstable and does not work reliably. Don't use it
    // anymore.
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
