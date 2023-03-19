<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2021, 2022 Claus-Justus Heine
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

use DateTimeImmutable;

use OCA\CAFEVDB\Wrapped\Doctrine\ORM\Mapping as ORM;

/** Helper for Gedmo soft-deleteable entities. */
trait SoftDeleteableEntity
{
  use UnusedTrait;
  use \OCA\CAFEVDB\Toolkit\Traits\DateTimeTrait;

  /**
   * @ORM\Column(type="datetime_immutable", nullable=true)
   *
   * @var DateTimeImmutable|null
   */
  protected $deleted;

  /**
   * Set or clear the deleted at timestamp.
   *
   * @param string|int|\DateTimeInterface $deleted
   *
   * @return self
   */
  public function setDeleted(mixed $deleted = null):self
  {
    $this->deleted = self::convertToDateTime($deleted);
    return $this;
  }

  /**
   * Get the deleted at timestamp value. Will return null if
   * the entity has not been soft deleted.
   *
   * @return \DateTimeImmutable|null
   */
  public function getDeleted()
  {
    return $this->deleted;
  }

  /**
   * Check if the entity has been soft deleted.
   *
   * @return bool
   */
  public function isDeleted():bool
  {
    return null !== $this->deleted;
  }

  /**
   * Return whether this object is expired and is about to be deleted on the
   * next call to delete.
   *
   * @return bool
   */
  public function isExpired():bool
  {
    return $this->isDeleted() && $this->deleted <= (new DateTimeImmutable) && $this->unused();
  }
}
