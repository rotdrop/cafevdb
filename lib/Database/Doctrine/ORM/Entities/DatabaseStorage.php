<?php
/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2022 Claus-Justus Heine
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
use OCA\CAFEVDB\Wrapped\Doctrine\ORM\Mapping as ORM;
use OCA\CAFEVDB\Wrapped\Gedmo\Mapping\Annotation as Gedmo;
use OCA\CAFEVDB\Wrapped\Doctrine\Common\Collections\Collection;
use OCA\CAFEVDB\Wrapped\Doctrine\Common\Collections\ArrayCollection;

use OCA\CAFEVDB\Database\Doctrine\DBAL\Types\EnumDirEntryType as DirEntryType;

/**
 * "Join table" which connects storage names to their root directory entry.
 *
 * @ORM\Table(
 *  name="DatabaseStorages",
 *  uniqueConstraints={
 *     @ORM\UniqueConstraint(columns={"storage_id"}),
 *     @ORM\UniqueConstraint(columns={"root_id"})
 *   }
 * )
 * @ORM\Entity
 */
class DatabaseStorage implements \ArrayAccess
{
  use CAFEVDB\Traits\ArrayTrait;

  /**
   * @var int
   *
   * @ORM\Column(type="integer", nullable=false)
   * @ORM\Id
   * @ORM\GeneratedValue(strategy="IDENTITY")
   */
  protected $id;

  /**
   * @var string The storage id as string, with the common prefix
   * OCA\CAFEVDB\Storage\Database\Storage::STORAGE_ID_TAG. This uses the
   * string-id. The cloud table oc_storages uses md5 when the storage-id is
   * larger than 64 bytes.
   *
   * @see OCA\CAFEVDB\Storage\Database\Storage
   * @see OCA\CAFEVDB\Storage\Database\MountProvider
   *
   * @ORM\Column(type="string", length=512, nullable=false)
   */
  protected $storageId;

  /**
   * @var DatabaseStorageFolder The root-node of the directory tree.
   *
   * @ORM\OneToOne(targetEntity="DatabaseStorageFolder")
   */
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
