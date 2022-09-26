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

/**
 * Generic directory entry for a database-backed file.
 *
 * @ORM\Table(
 *   name="DatabaseStorageFileEntries",
 *   uniqueConstraints={
 *     @ORM\UniqueConstraint(columns={"storage_id", "dir_name", "base_name"})
 *   }
 * )
 * @ORM\Entity
 */
class DatabaseStorageFileEntry implements \ArrayAccess
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
   * @var string
   *
   * This is just the Nextcloud storage id.
   *
   * @ORM\Column(type="string", length=64)
   */
  private $storageId;

  /**
   * @var string
   *
   * @ORM\Column(type="string", length=256)
   */
  private $baseName;

  /**
   * @var string
   *
   * @ORM\Column(type="string", length=4000)
   */
  private $dirName;

  /**
   * @var EncryptedFile
   *
   * @ORM\ManyToOne(targetEntity="EncryptedFile")
   */
  private $file;

  /**
   * @param null|int $id The id of this entity.
   *
   * @return DatabaseStorageFileEntry
   */
  public function setId(?int $id):DatabaseStorageFileEntry
  {
    $this->id = $id;

    return $this;
  }

  /**
   * @return null|string
   */
  public function getId():?int
  {
    return $this->id;
  }

  /**
   * @param null|string $storageId Copy of the Nextcloud storage id.
   *
   * @return DatabaseStorageFileEntry
   */
  public function setStorageId(?string $storageId):DatabaseStorageFileEntry
  {
    $this->storageId = $storageId;

    return $this;
  }

  /**
   * @return null|string
   */
  public function getStorageId():?string
  {
    return $this->storageId;
  }

  /**
   * @param null|string $dirName Directory part of the local path inside the storage.
   *
   * @return DatabaseStorageFileEntry
   */
  public function setDirName(?string $dirName):DatabaseStorageFileEntry
  {
    $this->dirName = $dirName;

    return $this;
  }

  /**
   * @return null|string
   */
  public function getDirName():?string
  {
    return $this->dirName;
  }

  /**
   * @param null|string $baseName Basename part of the local path inside the storeage.
   *
   * @return DatabaseStorageFileEntry
   */
  public function setBaseName(?string $baseName):DatabaseStorageFileEntry
  {
    $this->baseName = $baseName;

    return $this;
  }

  /**
   * @return null|string
   */
  public function getBaseName():?string
  {
    return $this->baseName;
  }

  /**
   * @param null|EncryptedFile $file The actual file for this directory entry.
   *
   * @return DatabaseStorageFileEntry
   */
  public function setFile(?EncryptedFile $file):DatabaseStorageFileEntry
  {
    $this->file = $file;

    return $this;
  }

  /**
   * @return null|EncryptedFile
   */
  public function getFile():?EncryptedFile
  {
    return $this->file;
  }
}
