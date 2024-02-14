<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2020, 2021, 2022, 2023 Claus-Justus Heine
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

use \InvalidArgumentException;

use OCA\CAFEVDB\Wrapped\Doctrine\ORM\Mapping as ORM;

use OCA\CAFEVDB\Wrapped\Ramsey\Uuid\UuidInterface;
use OCA\CAFEVDB\Common\Uuid;

/**
 * Support trait for entities with an UUID field.
 *
 * @see GetByUuidTrait
 */
trait UuidTrait
{
  /**
   * @var \OCA\CAFEVDB\Wrapped\Ramsey\Uuid\UuidInterface
   *
   * @ORM\Column(type="uuid_binary", unique=true)
   */
  private $uuid;

  /**
   * Set uuid.
   *
   * @param string|\Ramsey\Uuid\UuidInterface $uuid
   *
   * @return Musiker
   */
  public function setUuid($uuid):self
  {
    if ($uuid !== null) {
      $uuid = Uuid::asUuid($uuid);
      if (empty($uuid)) {
        throw new InvalidArgumentException("UUID DATA: ".$uuid);
      }
    }
    $this->uuid = $uuid;

    return $this;
  }

  /**
   * Get uuid.
   *
   * @return \OCA\CAFEVDB\Wrapped\Ramsey\Uuid\UuidInterface
   */
  public function getUuid():UuidInterface
  {
    return $this->uuid;
  }

  /**
   * {@inheritdoc}
   *
   * @ORM\PrePersist
   */
  public function prePersistUuid():void
  {
    $this->ensureUuid();
  }

  /**
   * {@inheritdoc}
   *
   * @ORM\PreUpdate
   */
  public function preUpdateUuid():void
  {
    $this->ensureUuid();
  }

  /**
   * {@inheritdoc}
   *
   * Support function which ensures that the uuid-field is set. It generates a
   * new UUID if $this->uuid is empty.
   *
   * @return void
   */
  private function ensureUuid():void
  {
    if (empty($this->uuid)) {
      $this->uuid = Uuid::create();
    }
  }
}
