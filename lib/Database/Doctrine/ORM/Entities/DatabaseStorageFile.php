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

use DateTimeInterface;

use OCA\CAFEVDB\Database\Doctrine\ORM as CAFEVDB;
use OCA\CAFEVDB\Wrapped\Doctrine\ORM\Mapping as ORM;
use OCA\CAFEVDB\Wrapped\Gedmo\Mapping\Annotation as Gedmo;
use OCA\CAFEVDB\Wrapped\Doctrine\Common\Collections\Collection;
use OCA\CAFEVDB\Wrapped\Doctrine\Common\Collections\ArrayCollection;
use OCA\CAFEVDB\Wrapped\Doctrine\ORM\Event;

use OCA\CAFEVDB\Enums\EnumDirEntryType as DirEntryType;

use OCA\CAFEVDB\Exceptions;
use OCA\CAFEVDB\Constants;

/**
 * File-name entry for a database-backed file.
 *
 * @ORM\Entity(repositoryClass="\OCA\CAFEVDB\Database\Doctrine\ORM\Repositories\DatabaseStorageFilesRepository")
 * @ORM\EntityListeners({"\OCA\CAFEVDB\Listener\DatabaseStorageFileEntityListener"})
 * @ORM\HasLifecycleCallbacks
 */
class DatabaseStorageFile extends DatabaseStorageDirEntry
{
  /** @var string */
  protected static $type = DirEntryType::FILE;

  /**
   * @var array<int, EncryptedFile>
   *
   * Array of potentially orphaned files which are no longer linked to
   * dir-entries. The array is indexed by spl_object_id().
   *
   * The actual work-horse for the orphans list is an entity listener which
   * post-pones actual cleanup of orphans to a pre-commit handler of the
   * decorated entity manager.
   */
  protected static $orphans = [];

  /**
   * @var EncryptedFile
   *
   * @ORM\ManyToOne(targetEntity="EncryptedFile", inversedBy="databaseStorageDirEntries", cascade={"persist"})
   * @ORM\JoinColumn(nullable="false")
   */
  protected $file;

  /** {@inheritdoc} */
  public function __construct()
  {
    parent::__construct();
  }

  /** @return null|EncryptedFile */
  public function getFile():?EncryptedFile
  {
    return $this->file;
  }

  /**
   * Install a file entity
   *
   * @param null|EncryptedFile $file
   *
   * @return DatabaseStorageFile
   */
  public function setFile(?EncryptedFile $file):DatabaseStorageFile
  {
    if (!empty($this->file)) {
      $this->file->removeDatabaseStorageDirEntry($this);
      if ($this->file->getNumberOfLinks() == 0) {
        self::$orphans[spl_object_id($this->file)] = $this->file;
      }
    }

    $this->file = $file;

    if (!empty($this->file)) {
      $this->file->addDatabaseStorageDirEntry($this);
      unset(self::$orphans[spl_object_id($this->file)]);
    }

    return $this;
  }

  /** @return DateTimeInterface */
  public function getUpdated():?DateTimeInterface
  {
    if (empty($this->file)) {
      return self::ensureDate($this->updated);
    }
    return max(self::ensureDate($this->updated), self::ensureDate($this->file->getUpdated()));
  }

  const FILE_METHODS = [
    'setSize',
    'getSize',
    'setFileData',
    'getFileData',
    'getMimeType',
    'setMimeType',
    'getNumberOfLinks',
  ];

  /**
   * {@inheritdoc}
   *
   * Pass through to wrapped file.
   */
  public function __call($method, $args)
  {
    if (array_search($method, self::FILE_METHODS) !== false
        && is_callable([ $this->file, $method ])) {
      return call_user_func_array([ $this->file, $method ], $args);
    }
    throw new Exceptions\DatabaseException('Undefined method - ' . __CLASS__ . '::' . $method);
  }

  /** @return null|string */
  public function getFileName():?string
  {
    return $this->getName();
  }

  /**
   * Set only the extension.
   *
   * @param string $extension
   *
   * @return File
   */
  public function setExtension(string $extension):File
  {
    $pathInfo = pathinfo($this->name ?? '');
    $this->name = $pathInfo['filename'] . '.' . $extension;
    if ($pathInfo['dirname'] != '.') {
      $this->name = $pathInfo['dirname'] . self::PATH_SEPARATOR . $this->name;
    }

    return $this;
  }

  /**
   * Get the extension-part of the file-name.
   *
   * @param null|string $extension
   *
   * @return null|string
   */
  public function getExtension(?string $extension = null):?string
  {
    return is_string($this->name) ? pathinfo($this->name, PATHINFO_EXTENSION) : null;
  }

  /**
   * {@inheritdoc}
   *
   * Update the inverse side of the file-association and mark the file as
   * orphan when it is no longer owned by other directory entries.
   *
   * @ORM\PreRemove
   */
  public function preRemove(Event\LifecycleEventArgs $event)
  {
    if (empty($this->file)) {
      return;
    }
    $this->file->removeDatabaseStorageDirEntry($this); // update the inverse side
    if ($this->file->getNumberOfLinks() == 0) {
      self::$orphans[spl_object_id($this->file)] = $this->file; // schedule for later removal
    }
  }

  /**
   * {@inheritdoc}
   *
   * Remove the associated file from the orphans list. May not be necessary ...
   *
   * @ORM\PostLoad
   * @ORM\PostPersist
   * @ORM\PostUpdate
   */
  public function cleanupOrphans(Event\LifecycleEventArgs $event)
  {
    if (empty($this->file)) {
      return;
    }
    unset(self::$orphans[spl_object_id($this->file)]);
  }

  /** @return array */
  public static function getOrphans():array
  {
    return self::$orphans;
  }

  /** @return void */
  public static function clearOrphans():void
  {
    self::$orphans = [];
  }

  /**
   * @param array $orphans
   *
   * @return void
   */
  public static function restoreOrphans(array $orphans):void
  {
    self::$orphans = $orphans;
  }
}
