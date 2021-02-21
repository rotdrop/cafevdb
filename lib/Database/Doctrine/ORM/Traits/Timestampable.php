<?php
/* Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2020 Claus-Justus Heine <himself@claus-justus-heine.de>
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
/**
 * Based on, with "At" removed from the names, i.e. updatedAt replaced
 * by updated etc.
 *
 * Timestampable Trait, usable with PHP >= 5.4
 *
 * @author Gediminas Morkevicius <gediminas.morkevicius@gmail.com>
 * @license MIT License (http://www.opensource.org/licenses/mit-license.php)
 */

namespace OCA\CAFEVDB\Database\Doctrine\ORM\Traits;
use Gedmo\Mapping\Annotation as Gedmo;

trait Timestampable
{
  /**
   * @var \DateTimeImmutable
   */
  private $created;

  /**
   * @var \DateTimeImmutable
   */
  private $updated;

  /**
   * Sets created.
   *
   * @param  \DateTimeImmutable $created
   * @return $this
   */
  public function setCreated(\DateTimeImmutable $created)
  {
    $this->created = $created;

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

  /**
   * Sets updated.
   *
   * @param  \DateTimeImmutable $updated
   * @return $this
   */
  public function setUpdated(\DateTimeImmutable $updated)
  {
    $this->updated = $updated;

    return $this;
  }

  /**
   * Returns updated.
   *
   * @return \DateTimeImmutable
   */
  public function getUpdated()
  {
    return $this->updated;
  }
}
