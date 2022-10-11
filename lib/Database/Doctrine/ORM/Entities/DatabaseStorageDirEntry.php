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
 * Generic directory entry for a database-backed file.
 *
 * @ORM\Table(
 *   name="DatabaseStorageDirEntries",
 *   uniqueConstraints={
 *     @ORM\UniqueConstraint(columns={"parent_id", "name"})
 *   },
 * )
 * @ORM\InheritanceType("SINGLE_TABLE")
 * @ORM\DiscriminatorColumn(name="type", type="EnumDirEntryType")
 * @ORM\DiscriminatorMap({"generic"="DatabaseStorageDirEntry", "file"="DatabaseStorageFile", "folder"="DatabaseStorageFolder"})
 * @ORM\Entity(repositoryClass="\OCA\CAFEVDB\Database\Doctrine\ORM\Repositories\DatabaseStorageDirEntriesRepository")
 */
class DatabaseStorageDirEntry implements \ArrayAccess
{
  use CAFEVDB\Traits\ArrayTrait;
  use CAFEVDB\Traits\UpdatedAtEntity;
  use CAFEVDB\Traits\CreatedAtEntity;

  /** @var string */
  protected static $type = DirEntryType::GENERIC;

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
   * @ORM\Column(type="string", length=256)
   */
  protected $name;

  /**
   * @var DatabaseStorageFolder
   *
   * @ORM\ManyToOne(targetEntity="DatabaseStorageFolder", inversedBy="directoryEntries")
   * @Gedmo\Timestampable(on={"update","create","delete"}, timestampField="updated")
   */
  protected $parent;

  /**
   * @var Collection
   *
   * @ORM\OneToMany(targetEntity="DatabaseStorageDirEntry", mappedBy="parent")
   */
  protected $directoryEntries;

  /** {@inheritdoc} */
  public function __construct()
  {
    $this->directoryEntries = new ArrayCollection;
  }

  /** @return null|int */
  public function getId():?int
  {
    return $this->id;
  }

  /**
   * @param null|int $id
   *
   * @return DatabaseStorageDirEntry
   */
  public function setId(?int $id):DatabaseStorageDirEntry
  {
    $this->id = $id;

    return $this;
  }

  /** @return null|string */
  public function getName():?string
  {
    return $this->name;
  }

  /**
   * @param null|string $name
   *
   * @return DatabaseStorageDirEntry
   */
  public function setName(?string $name):DatabaseStorageDirEntry
  {
    $this->name = $name;

    return $this;
  }

  /** @return null|DatabaseStorageDirEntry */
  public function getParent():?DatabaseStorageFolder
  {
    return $this->parent;
  }

  /**
   * @param null|DatabaseStorageDirEntry $parent
   *
   * @return DatabaseStorageFolder
   */
  public function setParent(?DatabaseStorageFolder $parent):DatabaseStorageDirEntry
  {
    if (!empty($this->parent)) {
      $this->parent->directoryEntries->removeElement($this);
    }

    $this->parent = $parent;

    if (!empty($this->parent)) {
      $this->parent->directoryEntries->add($this);
    }

    return $this;
  }

  /** @return Collection */
  public function getDirectoryEntries():Collection
  {
    return $this->databaseStorageDirectories;
  }

  /**
   * @param Collection $databaseStorageDirectories
   *
   * @return DatabaseStorageDirEntry
   */
  public function setDirectoryEntries(Collection $databaseStorageDirectories):DatabaseStorageDirEntry
  {
    $this->databaseStorageDirectories = $databaseStorageDirectories;

    return $this;
  }

  /** {@inheritdoc} */
  public function __toString():string
  {
    return static::$type . ':' . $this->name;
  }
}
