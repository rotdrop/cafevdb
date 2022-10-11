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

use OCA\CAFEVDB\Wrapped\Doctrine\ORM\Mapping as ORM;
use OCA\CAFEVDB\Wrapped\Doctrine\Common\Collections\Collection;
use OCA\CAFEVDB\Wrapped\Doctrine\Common\Collections\ArrayCollection;

use OCA\CAFEVDB\Database\Doctrine\DBAL\Types\EnumDirEntryType as DirEntryType;
use OCA\CAFEVDB\Exceptions;

/**
 * Folder entry for a database-backed file.
 *
 * @ORM\Entity
 */
class DatabaseStorageFolder extends DatabaseStorageDirEntry
{
  /** @var string */
  protected static $type = DirEntryType::FOLDER;

  /**
   * @var Collection
   *
   * Optional linked project payments.
   *
   * @ORM\OneToMany(targetEntity="ProjectPayment", mappedBy="balanceDocumentsFolder", cascade={"persist"}, fetch="EXTRA_LAZY")
   */
  protected $projectPayments;

  /**
   * @var Collection
   *
   * Optional linked composite payments.
   *
   * @ORM\OneToMany(targetEntity="CompositePayment", mappedBy="balanceDocumentsFolder", cascade={"persist"}, fetch="EXTRA_LAZY")
   */
  protected $compositePayments;

  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct()
  {
    $this->projectPayments = new ArrayCollection();
    $this->compositePayments = new ArrayCollection();
  }
  // phpcs:enable

  /**
   * Add a new sub-folder. It is ok if the folder already exists.
   *
   * @param string $name
   *
   * @return DatabaseStorageFolder The new or existing folder.
   *
   * @throws Exceptions\DatabaseException It is an error if $name already
   * exists and is not a folder.
   */
  protected function addSubFolder(string $name):DatabaseStorageFolder
  {
    $existing = $this->directoryEntries->filter(
      fn(DatabaseStorageDirEntry $dirEntry) => $dirEntry->name === $name
    );
    if ($existing->count() > 1) {
      throw new Exceptions\DatabaseException('Directory entry "' . $name . '" already exists multiple times in directory ' . $this->id);
    }
    if ($existing->count() == 1) {
      /** @var DatabaseStorageFolder $dirEntry */
      $dirEntry = $existing->first();
      if (!($dirEntry instanceof DatabaseStorageFolder))  {
        throw new Exceptions\DatabaseException('Directory entry "' . $name . '" already exists in directory ' . $this->id . ' and the existing entry is not a directory.');
      }
      return $dirEntry;
    }

    $dirEntry = (new DatabaseStorageFolder)
      ->setName($name)
      ->setParent($this);
    $this->directoryEntries->add($dirEntry);

    return $dirEntry;
  }

  /**
   * Remove the named sub-folder. It is ok if the folder does not exist
   *
   * @param string $name
   *
   * @return DatabaseStorageFolder $this
   *
   * @throws Exceptions\DatabaseException It is an error if $name already
   * exists and is not a folder.
   */
  public function removeSubFolder(string $name):DatabaseStorageFolder
  {
    $existing = $this->directoryEntries->filter(
      fn(DatabaseStorageDirEntry $dirEntry) => $dirEntry->name === $name
    );
    if ($existing->count() > 1) {
      throw new Exceptions\DatabaseException('Directory entry "' . $name . '" already exists multiple times in directory ' . $this->id);
    }
    if ($existing->count() == 0) {
      return $this;
    }
    /** @var DatabaseStorageFolder $dirEntry */
    $dirEntry = $existing->first();
    if (!($dirEntry instanceof DatabaseStorageFolder))  {
      throw new Exceptions\DatabaseException('Directory entry "' . $name . '" exists in directory ' . $this->id . ' but the existing entry is not a directory.');
    }

    $dirEntry->parent = null;
    $this->directoryEntries->removeElement($dirEntry);

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
   * @param null|string $fileName
   *
   * @return null|DatabaseStorageDirEntry The new or existing
   */
  public function addDocument(EncryptedFile $file, ?string $fileName = null):?DatabaseStorageFile
  {
    if (empty($file->getId())) {
      throw new Exceptions\DatabaseException('The supporting document does not have an id.');
    }
    $fileId = $file->getId();
    $fileName = $fileName ?? $file->getFileName();
    $existing = $this->directoryEntries->filter(
      fn(DatabaseStorageDirEntry $dirEntry) => $dirEntry->name === $fileName
    );
    if ($existing->count() > 1) {
      throw new Exceptions\DatabaseException('Directory entry "' . $fileName . '" already exists multiple times in directory ' . $this->id);
    }
    if ($existing->count() == 1) {
      /** @var DatabaseStorageFile $dirEntry */
      $dirEntry = $existing->first();
      if (!($dirEntry instanceof DatabaseStorageFile) || $dirEntry->getFile()->getId() != $fileId)  {
        throw new Exceptions\DatabaseException('Directory entry "' . $fileName . '" already exists in directory ' . $this->id . ' and the existing entry does not point to the same file.');
      }
      return $dirEntry;
    }

    // need a new one
    $dirEntry = (new DatabaseStorageFile)
      ->setFile($file)
      ->setName($fileName)
      ->setParent($this);

    return $dirEntry;
  }

  /**
   * Remove the given file from the list of supporting documents.
   *
   * This also decrements the link count of the file and removes $this from
   * the collection of document containes of the encrypted file entity.
   *
   * @param EncryptedFile $file
   *
   * @param null|string $fileName
   *
   * @return DatabaseStorageFolder $this
   */
  public function removeDocument(EncryptedFile $file, ?string $fileName = null):DatabaseStorageFolder
  {
    if (empty($file->getId())) {
      throw new RuntimeException('The supporting document does not have an id.');
    }
    $fileId = $file->getId();
    $fileName = $fileName ?? $file->getFileName();
    $existing = $this->directoryEntries->filter(
      fn(DatabaseStorageDirEntry $dirEntry) => $dirEntry->name === $fileName
    );
    if ($existing->count() > 1) {
      throw new Exceptions\DatabaseException('Directory entry "' . $fileName . '" exists multiple times in directory ' . $this->id);
    }
    if ($existing->count() == 0) {
      return $this;
    }

    /** @var DatabaseStorageFile $dirEntry */
    $dirEntry = $existing->first();
    if (!($dirEntry instanceof DatabaseStorageFile) || $dirEntry->getFile()->getId() != $fileId)  {
      throw new Exceptions\DatabaseException('Directory entry "' . $fileName . '" already exists in directory ' . $this->id . ' and the existing entry does not point to the same file.');
    }

    $dirEntry->setParent(null);
    $dirEntry->setFile(null);

    return $this;
  }

  /** @return The directory entries which are files. */
  public function getDocuments():Collection
  {
    return $this->directoryEntries->filter(fn(DatabaseStorageDirEntry $dirEntry) => $dirEntry instanceof DatabaseStorageFile);
  }

  /** @return The directory entries which are directories. */
  public function getSubFolders():Collection
  {
    return $this->directoryEntries->filter(fn(DatabaseStorageDirEntry $dirEntry) => $dirEntry instanceof DatabaseStorageFolder);
  }
}
