<?php
/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2022, 2023 Claus-Justus Heine
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

use OCA\CAFEVDB\Enums\EnumDirEntryType;
use OCA\CAFEVDB\Constants;

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
 * @ORM\DiscriminatorColumn(name="type", enumType="EnumDirEntryType", length=32)
 * @ORM\DiscriminatorMap({"generic"="DatabaseStorageDirEntry", "file"="DatabaseStorageFile", "folder"="DatabaseStorageFolder"})
 * @ORM\Entity(repositoryClass="\OCA\CAFEVDB\Database\Doctrine\ORM\Repositories\DatabaseStorageDirEntriesRepository")
 */
class DatabaseStorageDirEntry implements \ArrayAccess
{
  use CAFEVDB\Traits\ArrayTrait;
  use CAFEVDB\Traits\UpdatedAtEntity;
  use CAFEVDB\Traits\CreatedAtEntity;

  /** @var string */
  protected static $type = EnumDirEntryType::GENERIC;

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
   * @ORM\ManyToOne(targetEntity="DatabaseStorageFolder", inversedBy="directoryEntries", cascade={"persist"})
   * @Gedmo\Timestampable(on={"update","create","delete"}, timestampField="updated")
   */
  protected $parent;

  /** {@inheritdoc} */
  public function __construct()
  {
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
      $this->parent->getDirectoryEntries()->removeElement($this);
    }

    $this->parent = $parent;

    if (!empty($this->parent)) {
      $this->parent->getDirectoryEntries()->add($this);
    }

    return $this;
  }

  /**
   * @param DatabaseStorageFolder $parent
   *
   * @return DatabaseStorageDirEntry $this
   */
  public function link(DatabaseStorageFolder $parent):DatabaseStorageDirEntry
  {
    if ($parent !== $this->parent) {
      $this->setParent($parent);
    }

    return $this;
  }

  /**
   * @return DatabaseStorageDirEntry $this
   */
  public function unlink():DatabaseStorageDirEntry
  {
    return $this->setParent(null);
  }

  /**
   * Fetch the entire path up to the root node. This will result in database
   * queries if the parent elements are not already in memory.
   *
   * @return string Full path excluding leadin slash.
   */
  public function getPathName():string
  {
    $path = $this->name;
    $node = $this->parent;
    while (!empty($node)) {
      $path = $node->getName() . Constants::PATH_SEP . $path;
      $node = $node->getParent();
    }
    return $path;
  }

  /**
   * @return DatabaseStorageFolder The root directory.
   */
  public function getRoot():DatabaseStorageFolder
  {
    for ($root = $this, $parent = $root->getParent(); !empty($parent); $root = $parent, $parent = $root->getParent());
    return $root;
  }

  /** {@inheritdoc} */
  public function __toString():string
  {
    return static::$type . ':' . $this->name;
  }
}
