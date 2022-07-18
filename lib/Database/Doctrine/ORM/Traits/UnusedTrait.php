<?php
/* Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2020, 2021, 2022 Claus-Justus Heine <himself@claus-justus-heine.de>
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

/**
 * Simple trait that adds two convenience methods provided the
 * underlying class has a "usage()" method where a return value > 0
 * indicates the number of items using the class.
 */
trait UnusedTrait
{
  /**
   * Return the number of other items still depending on this entity
   *
   * @return int
   */
  public function usage()
  {
    return 0;
  }

  /**
   * Return a boolean to indicate that this entity is no longer used.
   */
  public function unused():bool
  {
    return $this->usage() == 0;
  }

  /**
   * Return a boolean to indicate that this field is used.
   */
  public function inUse():bool
  {
    return !$this->unused();
  }
}
