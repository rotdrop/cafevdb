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

/**
 * Generic directory entry for a database-backed file.
 *
 * @ORM\Table(
 *   name="DatabaseStorageDirectories",
 *   uniqueConstraints={
 *     @ORM\UniqueConstraint(columns={"storage_id", "parent_id", "name"})
 *   },
 * )
 * @ORM\Entity
 */
class DatabaseStorageDirectory implements \ArrayAccess
{
  use CAFEVDB\Traits\ArrayTrait;
  use CAFEVDB\Traits\UpdatedAtEntity;

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
   * @ORM\Column(type="string", length=64, nullable=true)
   */
  protected $storageId;

  /**
   * @var string
   *
   * @ORM\Column(type="string", length=256)
   */
  protected $name;

  /**
   * @var null|DatabaseStorageDirectory
   *
   * @ORM\ManyToOne(targetEntity="DatabaseStorageDirectory", inversedBy="databaseStorageDirectories")
   * @Gedmo\Timestampable(on={"update","create","delete"}, timestampField="updated")
   */
  protected $parent;

  /**
   * @var Collection
   *
   * @ORM\OneToMany(targetEntity="DatabaseStorageDirectory", mappedBy="parent")
   */
  protected $databaseStorageDirectories;

  /**
   * @var Collection
   *
   * @ORM\ManyToMany(targetEntity="EncryptedFile", inversedBy="databaseStorageDirectories", cascade={"persist"}, indexBy="id", fetch="EXTRA_LAZY")
   * @ORM\JoinTable
   */
  protected $documents;

  /** {@inheritdoc} */
  public function __construct()
  {
    $this->databaseStorageDirectories = new ArrayCollection;
    $this->documents = new ArrayCollection;
  }

  /** @return null|int */
  public function getId():?int
  {
    return $this->id;
  }

  /**
   * @param null|int $id
   *
   * @return DatabaseStorageDirectory
   */
  public function setId(?int $id):DatabaseStorageDirectory
  {
    $this->id = $id;

    return $this;
  }

  /** @return null|string */
  public function getStorageId():?string
  {
    return $this->storageId;
  }

  /**
   * @param null|string $storageId
   *
   * @return DatabaseStorageDirectory
   */
  public function setStorageId(?string $storageId):DatabaseStorageDirectory
  {
    $this->storageId = $storageId;

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
   * @return DatabaseStorageDirectory
   */
  public function setName(?string $name):DatabaseStorageDirectory
  {
    $this->name = $name;

    return $this;
  }

  /** @return null|DatabaseStorageDirectory */
  public function getParent():?DatabaseStorageDirectory
  {
    return $this->parent;
  }

  /**
   * @param null|DatabaseStorageDirectory $parent
   *
   * @return DatabaseStorageDirectory
   */
  public function setParent(?DatabaseStorageDirectory $parent):DatabaseStorageDirectory
  {
    $this->parent = $parent;

    return $this;
  }

  /** @return Collection */
  public function getDatabaseStorageDirectories():Collection
  {
    return $this->databaseStorageDirectories;
  }

  /**
   * @param Collection $databaseStorageDirectories
   *
   * @return DatabaseStorageDirectory
   */
  public function setDatabaseStorageDirectories(Collection $databaseStorageDirectories):DatabaseStorageDirectory
  {
    $this->databaseStorageDirectories = $databaseStorageDirectories;

    return $this;
  }

  /** @return Collection */
  public function getDocuments():Collection
  {
    return $this->documents;
  }

  /**
   * @param Collection $documents
   *
   * @return DatabaseStorageDirectory
   */
  public function setDocuments(Collection $documents):DatabaseStorageDirectory
  {
    $this->documents = $documents;

    return $this;
  }

  /**
   * Add the given file to the list of supporting documents if not already present.
   *
   * This increases the link-count of the file and add this entity to the
   * container collection of the encrypted file.
   *
   * @param EncryptedFile $file
   *
   * @return ProjectBalanceSupportingDocument
   */
  public function addDocument(EncryptedFile $file):DatabaseStorageDirectory
  {
    if (empty($file->getId())) {
      throw new RuntimeException('The supporting document does not have an id.');
    }
    if (!$this->documents->containsKey($file->getId())) {
      $file->link();
      $file->addDatabaseStorageDirectory($this);
      $this->documents->set($file->getId(), $file);
    }
    return $this;
  }

  /**
   * Remove the given file from the list of supporting documents.
   *
   * This also decrements the link count of the file and removes $this from
   * the collection of document containes of the encrypted file entity.
   *
   * @param EncryptedFile $file
   *
   * @return ProjectBalanceSupportingDocument
   */
  public function removeDocument(EncryptedFile $file):DatabaseStorageDirectory
  {
    if (empty($file->getId())) {
      throw new RuntimeException('The supporting document does not have an id.');
    }
    if ($this->documents->containsKey($file->getId())) {
      $this->documents->remove($file->getId());
      $file->removeDatabaseStorageDirectory($this);
      $file->unlink();
    }
    return $this;
  }
}
