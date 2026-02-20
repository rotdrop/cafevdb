<?php
/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2022, 2023, 2024, 2025, 2026 Claus-Justus Heine
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

namespace OCA\CAFEVDB\Database\Doctrine\ORM\Entities;

use OCA\CAFEVDB\Database\Doctrine\ORM as CAFEVDB;
use OCA\CAFEVDB\PageRenderer\DatabaseTables;
use OCA\CAFEVDB\Wrapped\Doctrine\ORM\Mapping as ORM;

/**
 * "Join table" which connects storage names to their root directory entry.
 */
#[ORM\Table(name: DatabaseTables::DATABASE_STORAGES_TABLE)]
#[ORM\UniqueConstraint(columns: ['storage_id'])]
#[ORM\UniqueConstraint(columns: ['root_id'])]
#[ORM\Entity(repositoryClass: \OCA\CAFEVDB\Database\Doctrine\ORM\Repositories\DatabaseStoragesRepository::class)]
#[ORM\HasLifecycleCallbacks]
class DatabaseStorage implements \ArrayAccess
{
  use CAFEVDB\Traits\ArrayTrait;
  use CAFEVDB\Traits\AutoIncrementTrait;

  /**
   * @var string The storage id as string, without the common prefix
   * OCA\CAFEVDB\Storage\Database\Storage::STORAGE_ID_TAG. This uses the
   * string-id. The cloud table oc_storages uses md5 when the storage-id is
   * larger than 64 bytes.
   *
   * @see OCA\CAFEVDB\Storage\Database\Storage
   * @see OCA\CAFEVDB\Storage\Database\MountProvider
   */
  #[ORM\Column(type: 'string', length: 512, nullable: false)]
  protected $storageId;

  /**
   * @var DatabaseStorageFolder The root-node of the directory tree.
   */
  #[ORM\OneToOne(targetEntity: DatabaseStorageFolder::class, inversedBy: 'storage')]
  protected $root;

  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct()
  {
  }
  // phpcs:enable

  /** @return null|string */
  public function getStorageId():?string
  {
    return $this->storageId;
  }

  /**
   * @param null|string $storageId
   *
   * @return DatabaseStorage
   */
  public function setStorageId(?string $storageId):DatabaseStorage
  {
    $this->storageId = $storageId;

    return $this;
  }

  /** @return null|DatabaseStorageFolder */
  public function getRoot():?DatabaseStorageFolder
  {
    return $this->root;
  }

  /**
   * @param null|DatabaseStorageFolder $root
   *
   * @return DatabaseStorage
   */
  public function setRoot(?DatabaseStorageFolder $root):DatabaseStorage
  {
    $this->root = $root;

    return $this;
  }

  /** {@inheritdoc} */
  public function __toString():string
  {
    return $this->storageId;
  }
}
