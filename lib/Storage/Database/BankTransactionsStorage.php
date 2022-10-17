<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine  <himself@claus-justus-heine.de>
 * @copyright 2011-2014, 2016, 2020, 2021, 2022, Claus-Justus Heine <himself@claus-justus-heine.de>
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

namespace OCA\CAFEVDB\Storage\Database;

use \DateTimeImmutable;

// F I X M E: those are not public, but ...
use OC\Files\Storage\Common as AbstractStorage;
use OC\Files\Storage\PolyFill\CopyDirectory;
use OCP\EventDispatcher\IEventDispatcher;

use Icewind\Streams\CallbackWrapper;
use Icewind\Streams\CountWrapper;
use Icewind\Streams\IteratorDirectory;

use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Database\Doctrine\Util as DBUtil;
use OCA\CAFEVDB\Common\Util;
use OCA\CAFEVDB\Exceptions;

/**
 * Storage implementation for data-base storage, including access to
 * encrypted entities.
 */
class BankTransactionsStorage extends Storage
{
  use \OCA\CAFEVDB\Traits\DateTimeTrait;

  /** @var \OCA\CAFEVDB\Database\Doctrine\ORM\Repositories\SepaBulkTransactionsRepository */
  private $transactionsRepository;

  /** @var Entities\DatabaseStorageFolder */
  private $rootFolder;

  /** @var array */
  private $files = [];

  /** {@inheritdoc} */
  public function __construct($params)
  {
    parent::__construct($params);

    $shortId = $this->getShortId();
    $rootStorage = $this->getDatabaseRepository(Entities\DatabaseStorage::class)->findOneBy([ 'storageId' => $shortId ]);
    if (!empty($rootStorage)) {
      $this->rootFolder = $rootStorage->getRoot();
    }
    $this->transactionsRepository = $this->getDatabaseRepository(Entities\SepaBulkTransaction::class);

    /** @var IEventDispatcher $eventDispatcher */
    $eventDispatcher = $this->di(IEventDispatcher::class);
    $eventDispatcher->addListener(Events\EntityManagerBoundEvent::class, function(Events\EntityManagerBoundEvent $event) {
      $this->logDebug('Entity-manager shoot down, re-fetching cached entities.');
      $this->clearDatabaseRepository();

      $shortId = $this->getShortId();
      $rootStorage = $this->getDatabaseRepository(Entities\DatabaseStorage::class)->findOneBy([ 'storageId' => $shortId ]);
      if (!empty($rootStorage)) {
        $this->rootFolder = $rootStorage->getRoot();
      }
      $this->transactionsRepository = $this->getDatabaseRepository(Entities\SepaBulkTransaction::class);
    });
  }

  /**
   * Find an existing directory entry for the given file.
   *
   * @param Entities\SepaBulkTransaction $transaction
   *
   * @param Entities\EncryptedFile $file
   *
   * @return null|Entities\DatabaseStorageFile
   */
  public function findDocument(Entities\SepaBulkTransaction $transaction, Entities\EncryptedFile $file):?Entities\DatabaseStorageFile
  {
    $year = $transaction->getCreated()->format('Y');

    /** @var Entities\DatabaseStorageFile $dirEntry */
    foreach ($file->getDatabaseStorageDirEntries() as $dirEntry) {
      $parent = $dirEntry->getParent();
      if (empty($parent)) {
        continue;
      }
      $grandParent = $parent->getParent();
      if ($grandParent === $this->rootFolder && $parent->getName() == $year) {
        return $dirEntry;
      }
    }

    return null;
  }

  /**
   * Add a new document to the storage.
   *
   * @param Entities\SepaBulkTransaction $transaction
   *
   * @param Entities\EncryptedFile $file
   *
   * @param bool $flush Whether to flush the changes to the db.
   *
   * @return Entities\DatabaseStorageFile
   */
  public function addDocument(
    Entities\SepaBulkTransaction $transaction,
    Entities\EncryptedFile $file,
    bool $flush = true,
  ):Entities\DatabaseStorageFile {
    $document = $this->findDocument($transaction, $file);
    if (!empty($document)) {
      return $document;
    }

    if ($flush) {
      $this->entityManager->beginTransaction();
    }
    try {
      $year = $transaction->getCreated()->format('Y');
      $yearFolder = $this->rootFolder->getFolderByName($year);
      if (empty($yearFolder)) {
        $yearFolder = $this->rootFolder->addSubFolder($year)
          ->setUpdated($file->getUpdated())
          ->setCreated($file->getCreated());
        $this->persist($yearFolder);
      }

      $document = $yearFolder->addDocument($file)
        ->setCreated($file->getCreated())
        ->setUpdated($file->getUpdated());
      $this->persist($document);
      $yearFolder
        ->setCreated(min($file->getCreated(), $yearFolder->getCreated()))
        ->setUpdated(max($file->getUpdated(), $yearFolder->getUpdated()));

      if ($flush) {
        $this->flush();
        $this->entityManager->commit();
      }

    } catch (Throwable $t) {
      if ($this->entityManager->isTransactionActive()) {
        $this->entityManager->rollback();
      }
      throw new Exceptions\Exception($this->l->t('Unable to add new document "%s".', $file->getFileName()));
    }

    return $document;
  }

  /**
   * Remove a document from the storage. Note that this does not remove the
   * file from the transaction entity.
   *
   * @param Entities\SepaBulkTransaction $transaction
   *
   * @param Entities\EncryptedFile $file
   *
   * @param bool $flush Whether to actually flush the operations to th db.
   *
   * @return void
   */
  public function removeDocument(
    Entities\SepaBulkTransaction $transaction,
    Entities\EncryptedFile $file,
    bool $flush = true,
  ):void {
    $document = $this->findDocument($transaction, $file);
    if (empty($document)) {
      $this->logInfo('DOCUMENT NOT FOUND, CANNOT REMOVE ' . $file->getFileName());
      return;
    }

    if ($flush) {
      $this->entityManager->beginTransaction();
    }
    try {
      $parent = $document->getParent();
      $file = $document->getFile();
      $document->setFile(null);
      $document->setParent(null);
      $this->entityManager->remove($document);
      if ($flush) {
        $this->flush();
      }
      if ($parent->isEmpty()) {
        $parent->setParent(null);
        $this->entityManager->remove($parent);
        if ($flush) {
          $this->flush();
        }
      }
      if ($file->getNumberOfLinks() == 0) {
        if ($flush) {
          $this->flush();
        }
        $this->entityManager->remove($file);
      }

      if ($flush) {
        $this->flush();
        $this->entityManager->commit();
      }

    } catch (Throwable $t) {
      if ($this->entityManager->isTransactionActive()) {
        $this->entityManager->rollback();
      }
      throw new Exceptions\DatabaseException($this->l->t('Unable to remove document "%s".', $file->getFileName()));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function fileFromFileName(string $name)
  {
    $name = $this->buildPath($name);
    if ($name == self::PATH_SEPARATOR) {
      $dirName = '';
      $baseName = '.';
    } else {
      list('basename' => $baseName, 'dirname' => $dirName) = self::pathInfo($name);
    }

    if (empty($this->files[$dirName])) {
      $this->findFiles($dirName);
    }

    return ($this->files[$dirName][$baseName]
            ?? ($this->files[$dirName][$baseName . self::PATH_SEPARATOR]
                ?? null));
  }

  /**
   * {@inheritdoc}
   */
  protected function findFiles(string $dirName):array
  {
    $dirName = self::normalizeDirectoryName($dirName);
    if (!empty($this->files[$dirName])) {
      return $this->files[$dirName];
    }

    $dirComponents = Util::explode(self::PATH_SEPARATOR, $dirName);

    /** @var Entities\DatabaseStorageFolder $folderDirEntry */
    $folderDirEntry = $this->rootFolder;
    foreach ($dirComponents as $component) {
      $folderDirEntry = $folderDirEntry->getFolderByName($component);
      if (empty($folderDirEntry)) {
        throw new Exceptions\DatabaseEntityNotFoundException($this->l->t(
          'Unable to find directory entry for folder "%s".', $dirName
        ));
      }
    }

    $this->files[$dirName] = [ '.' => $folderDirEntry ];

    $dirEntries = $folderDirEntry->getDirectoryEntries();

    /** @var Entities\DatabaseStorageDirEntry $dirEntry */
    foreach ($dirEntries as $dirEntry) {

      $baseName = $dirEntry->getName();
      $fileName = $this->buildPath($dirName . self::PATH_SEPARATOR . $baseName);
      list('basename' => $baseName) = self::pathInfo($fileName);

      if ($dirEntry instanceof Entities\DatabaseStorageFolder) {
        /** @var Entities\DatabaseStorageFolder $dirEntry */
        // add a directory entry
        $baseName .= self::PATH_SEPARATOR;
        $this->files[$dirName][$baseName] = $dirEntry;
      } else {
        /** @var Entities\DatabaseStorageFile $dirEntry */
        $this->files[$dirName][$baseName] = $dirEntry;
      }
    }

    return $this->files[$dirName];
  }

  /** {@inheritdoc} */
  protected function getStorageModificationDateTime():?\DateTimeInterface
  {
    return self::ensureDate(empty($this->rootFolder) ? null : $this->rootFolder->getUpdated());
  }

  /** {@inheritdoc} */
  public function getShortId()
  {
    return implode(
      self::PATH_SEPARATOR, [
        'finance', 'transactions',
      ])
      . self::PATH_SEPARATOR;
  }
}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
