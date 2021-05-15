<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2021 Claus-Justus Heine <himself@claus-justus-heine.de>
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

use Doctrine\ORM\Mapping as ORM;

trait SoftDeleteableEntity
{
  use \OCA\CAFEVDB\Traits\DateTimeTrait;

  /**
   * @ORM\Column(type="datetime_immutable", nullable=true)
   *
   * @var DateTimeImmutable|null
   */
  protected $deleted;

  /**
   * Set or clear the deleted at timestamp.
   *
   * @param string|int|\DateTimeInterface $mandateDate
   *
   * @return self
   */
  public function setDeleted($deleted = null)
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
  public function isDeleted()
  {
    return null !== $this->deleted;
  }
}
