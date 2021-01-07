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

namespace OCA\CAFEVDB\Database\Doctrine\ORM\Traits;

use Doctrine\ORM\Mapping as ORM;

use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

trait UuidTrait
{
  /**
   * @var \Ramsey\Uuid\UuidInterface
   *
   * @ORM\Column(name="UUID", type="uuid_binary", unique=true)
   */
  private $uuid;

  /**
   * Set uuid.
   *
   * @param string|\Ramsey\Uuid\UuidInterface $uuid
   *
   * @return Musiker
   */
  public function setUuid($uuid)
  {
    if (is_string($uuid)) {
      if (strlen($uuid) == 36) {
        $uuid = Uuid::fromString($uuid);
      } else if (strlen($uuid) == 16) {
        $uuid = Uuid::fromBytes($uuid);
      } else {
        throw new \Exception("UUID DATA: ".$uuid);
      }
    }
    $this->uuid = $uuid;

    return $this;
  }

  /**
   * Get uuid.
   *
   * @return \Ramsey\Uuid\UuidInterface
   */
  public function getUuid():UuidInterface
  {
    return $this->uuid;
  }

  /** @ORM\prePersist */
  public function prePersistUuid()
  {
    $this->ensureUuid();
  }

  /** @ORM\preUpdate */
  public function preUpdateUuid()
  {
    $this->ensureUuid();
  }

  private function ensureUuid()
  {
    if (empty($this->getUuid())) {
      $this->uuid = Uuid::uuid4();
    }
  }
}
