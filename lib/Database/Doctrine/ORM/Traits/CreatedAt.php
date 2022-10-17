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

/** Field $created and setter/getter. */
trait CreatedAt
{
  use \OCA\CAFEVDB\Traits\DateTimeTrait;

  /**
   * @var \DateTimeImmutable
   */
   protected $created;

  /**
   * Sets created.
   *
   * @param string|int|\DateTimeInterface $created
   *
   * @return self
   */
  public function setCreated($created)
  {
    $this->created = self::convertToDateTime($created);
    return $this;
  }

  /**
   * Returns created.
   *
   * @return \DateTimeImmutable
   */
  public function getCreated()
  {
    return $this->created;
  }
}
