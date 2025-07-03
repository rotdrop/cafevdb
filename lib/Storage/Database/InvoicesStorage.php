<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine  <himself@claus-justus-heine.de>
 * @copyright 2025 Claus-Justus Heine <himself@claus-justus-heine.de>
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

use DateTimeImmutable;
use DateTimeInterface;
use Throwable;

use OCP\EventDispatcher\IEventDispatcher;

use OCA\CAFEVDB\Common\Util;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities\DatabaseStorageFolder;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities\Invoice as Entity;
use OCA\CAFEVDB\Events;
use OCA\CAFEVDB\Exceptions;

/**
 * Storage implementation for data-base storage, including access to
 * encrypted entities.
 */
class InvoicesStorage extends Storage
{
  use \OCA\CAFEVDB\Toolkit\Traits\DateTimeTrait;
  use DatabaseStorageNodeNameTrait;

  /** @var \OCA\CAFEVDB\Database\Doctrine\ORM\Repositories\InvoicesRepository */
  private $entityRepository;

  /** {@inheritdoc} */
  public function __construct($params)
  {
    parent::__construct($params);

    $this->getRootFolder(create: false);
    $this->entityRepository = $this->getDatabaseRepository(Entity::class);

    /** @var IEventDispatcher $eventDispatcher */
    $eventDispatcher = $this->di(IEventDispatcher::class);
    $eventDispatcher->addListener(Events\EntityManagerBoundEvent::class, function(Events\EntityManagerBoundEvent $event) {
      $this->getRootFolder(create: false);
      $this->entityRepository = $this->getDatabaseRepository(Entity::class);
    });
  }

  /**
   * @param Entity $entity
   *
   * @return DateTimeInterface
   */
  protected function getBirthTimeFromEntity(Entity $entity):DateTimeInterface
  {
    return $entity->getInvoiceDate();
  }

  /**
   * Add a new document to the storage.
   *
   * @param Entity $entity
   *
   * @param Entities\EncryptedFile $file
   *
   * @param bool $flush Whether to flush the changes to the db.
   *
   * @param string $conflict Conflict resolution, fail, rename, replace.
   *
   * @return Entities\DatabaseStorageFile
   */
  public function addDocument(
    Entity $entity,
    Entities\EncryptedFile $file,
    bool $flush = true,
    string $conflict = DatabaseStorageFolder::ADD_DOCUMENT_CONFLICT_FAIL,
  ):Entities\DatabaseStorageFile {
    $mimeType = $file->getMimeType();
    $extension = Util::fileExtensionFromMimeType($mimeType);
    if (empty($extension) && !empty($file->getFileName())) {
      $extension = strtolower(pathinfo($file->getFileName(), PATHINFO_EXTENSION));
    }
    $this->logInfo('MIME AND EXTENSION ' . $mimeType . ' "' . $extension . '"');
    $folderName = $this->getInvoiceFileName($entity);
    $fileName = $folderName . ($extension ? '.' . $extension : '');
    $folderName = substr($folderName, strlen($this->getAppL10n()->t('invoice')) + 1);

    if ($flush) {
      $this->entityManager->beginTransaction();
    }
    try {
      // search for the folder
      $rootFolder = $this->getRootFolder(create: true);
      if (empty($rootFolder)) {
        throw new UnexpectedValueException($this->l->t('Root-folder does not exist.'));
      }
      $year = $this->getBirthTimeFromEntity($entity)->format('Y');
      $yearFolder = $this->rootFolder->getFolderByName($year);
      if (empty($yearFolder)) {
        $yearFolder = $this->rootFolder->addSubFolder($year)
          ->setUpdated($file->getUpdated())
          ->setCreated($file->getCreated());
        $this->persist($yearFolder);
      }
      $invoiceFolder = $yearFolder->getFolderByName($folderName);
      if (empty($invoiceFolder)) {
        $invoiceFolder = $yearFolder->addSubFolder($folderName)
          ->setUpdated($file->getUpdated())
          ->setCreated($file->getCreated());
        $this->persist($invoiceFolder);
      }

      $document = $invoiceFolder->addDocument($file, $fileName, conflict: $conflict)
        ->setCreated($file->getCreated())
        ->setUpdated($file->getUpdated());
      $this->persist($document);
      $invoiceFolder
        ->setCreated(min($file->getCreated(), $yearFolder->getCreated()))
        ->setUpdated(max($file->getUpdated(), $yearFolder->getUpdated()));
      $yearFolder
        ->setCreated(min($invoiceFolder->getCreated(), $invoiceFolder->getCreated()))
        ->setUpdated(max($invoiceFolder->getUpdated(), $invoiceFolder->getUpdated()));

      if ($flush) {
        $this->flush();
        $this->entityManager->commit();
      }

    } catch (Throwable $t) {
      if ($this->entityManager->isTransactionActive()) {
        $this->entityManager->rollback();
      }
      throw new Exceptions\Exception($this->l->t('Unable to add new document "%s".', $file->getFileName()), previous: $t);
    }

    return $document;
  }

  /**
   * Add or replace a directory entry for the given entity and file.
   *
   * @param Entity $entity Database entity.
   *
   * @param Entities\EncryptedFile $file
   *
   * @param bool $flush
   *
   * @return null|Entities\DatabaseStorageFile
   */
  public function replaceDocument(
    Entity $entity,
    Entities\EncryptedFile $file,
    bool $flush = true,
  ):?Entities\DatabaseStorageFile {
    return $this->addDocument($entity, $file, $flush, conflict: DatabaseStorageFolder::ADD_DOCUMENT_CONFLICT_REPLACE);
  }

   /** {@inheritdoc} */
  public function rmdir($path)
  {
    // Allow rmdir for cleanup purposes. We can rely on the databse
    // constraints which prevent accidental deletion.

    /** @var Entities\DatabaseStorageFolder $dirEntry */
    $dirEntry = $this->fileFromFileName($path);
    if (empty($dirEntry)) {
      return false;
    }

    $this->entityManager->beginTransaction();
    try {
      $dirEntry->setParent(null);
      $this->entityManager->remove($dirEntry);
      $this->flush();

      $this->entityManager->commit();

    } catch (Throwable $t) {
      $this->logException($t);
      if ($this->entityManager->isTransactionActive()) {
        $this->entityManager->rollback();
      }
      return false;
    }

    // update the local cache
    $this->unsetFileNameCache($path);

    return true;
  }

  /**
   * {@inheritdoc}
   */
  protected function findFiles(string $dirName, bool $ignored = false):array
  {
    return parent::findFiles($dirName, rootIsMandatory: false);
  }

  /** {@inheritdoc} */
  protected function getStorageModificationDateTime():?\DateTimeInterface
  {
    return self::ensureDate(empty($this->rootFolder) ? null : $this->rootFolder->getUpdated());
  }

  /** {@inheritdoc} */
  public function getShortId():string
  {
    return implode(
      self::PATH_SEPARATOR, [
        'finance', 'invoices',
      ])
      . self::PATH_SEPARATOR;
  }

  /**  {@inheritdoc} */
  public function isUpdatable(string $path): bool
  {
    if (!$this->file_exists($path)) {
      return false;
    }
    list('basename' => $baseName, 'dirname' => $dirName) = self::pathinfo($path);

    /** @var Entity $invoice */
    $invoice = $this->entityRepository->findInvoiceByFileName($dirName);
    if ($invoice && $invoice->getNotificationEmail()) {
      return in_array($baseName, $this->getReadMeFactory()->getReadMeFileNames());
    }

    return true;
  }

  /** {@inheritdoc} */
  public function touch($path, $mtime = null)
  {
    if ($this->is_dir($path)) {
      // $this->logInfo('IS DIR ' . $path);
      return false;
    }
    list('basename' => $baseName, 'dirname' => $dirName) = self::pathinfo($path);

    $this->logInfo('EXPLODED PATH ' . print_r(explode(self::PATH_SEPARATOR, $path), true));

    // Do not allow files with the same name as the year directories
    if (preg_match('|^/?(\d{4})/?$|', $baseName)) {
      return false;
    }

    /** @var Entity $invoice */
    $invoice = $this->entityRepository->findInvoiceByFileName(self::pathInfo($dirName, PATHINFO_BASENAME));
    if ($invoice && $invoice->getNotificationEmail() && !str_contains($baseName, 'ocTransferId')) {
      // Do not allow adding further documents except adding a folder
      // documentation. The final rename will fail in addition if this is
      // something else but a readme.
      return false;
    }

    $this->entityManager->beginTransaction();
    try {
      $this->getRootFolder();

      /** @var Entities\DatabaseStorageFile $dirEntry */
      $dirEntry = $this->fileFromFileName($path);
      if (empty($dirEntry)) {
        $parent = $this->fileFromFileName($dirName);
        if (empty($parent)) {
          return false;
        }
        $file = new Entities\EncryptedFile($baseName, '', '');
        if ($mtime !== null) {
          $file->setCreated($mtime);
        }
        $this->persist($file);
        $this->flush();
        $dirEntry = $parent->addDocument($file, $baseName);
        $this->persist($dirEntry);
        if ($mtime !== null) {
          $dirEntry->setCreated($mtime);
        }
      }
      if ($dirEntry instanceof InMemoryFileNode) {
        $dirEntry = $this->persistInMemoryFileNode($dirEntry);
      }
      if ($mtime !== null) {
        $dirEntry->setUpdated($mtime);
        $dirEntry->getFile()->setUpdated($mtime);
      }
      $this->flush();

      $this->entityManager->commit();

      $this->setFileNameCache($path, $dirEntry);

    } catch (Throwable $t) {
      $this->logException($t);
      if ($this->entityManager->isTransactionActive()) {
        $this->entityManager->rollback();
      }
      return false;
    }

    return true;
  }


  /**
   * {@inheritdoc}
   *
   * This is needed in order to
   *
   * - add custom folder descriptions
   *
   * - add attachments
   *
   * We disallow renaming of
   *
   * - anything not at the leaf-level with the exception of renaming temporary files to ReadMe files
   *
   * So now: first allow, code late and restrict.
   *
   * @return bool
   */
  public function rename(string $path1, string $path2): bool
  {
    $this->logInfo('RENAME ARGS ' . $path1 . ' || ' . $path2);

    $path1 = $this->buildPath($path1);
    $path2 = $this->buildPath($path2);
    list('dirname' => $dirName1, 'basename' => $baseName1) = self::pathinfo($path1);
    list('dirname' => $dirName2, 'basename' => $baseName2) = self::pathinfo($path2);

    /** @var Entities\DatabaseStorageDirEntry $dirEntry */
    $dirEntry = $this->fileFromFileName($path1);
    if (empty($dirEntry)) {
      // $this->logInfo('NO DIR ENTRY FOR ' . $path1);
      return false;
    } elseif ($dirEntry instanceof Entities\DatabaseStorageFolder) {
      // nope, we do not allow renaming folders
      return false;
    }

    /** @var Entity $invoice */
    $invoice = $this->entityRepository->findInvoiceByFileName($baseName1);
    if ($invoice) {
      // keep it
      return false;
    }

    if ($dirEntry instanceof InMemoryFileNode) {
      $dirEntry = $this->persistInMemoryFileNode($dirEntry);
    }

    if ($dirName1 != $dirName2) {
      $parent2 = $this->fileFromFileName($dirName2);
      if (empty($parent2)) {
        // $this->logInfo('NO PARENT2 for ' . $path2);
        return false;
      }
    }

    $this->entityManager->beginTransaction();
    try {
      $dirEntry->setName($baseName2);
      if (!empty($parent2)) {
        $dirEntry->setParent($parent2);
      }

      $this->flush();

      $this->entityManager->commit();

      // update our local files cache
      $this->setFileNameCache($path2, $dirEntry);
      $this->unsetFileNameCache($path1);
    } catch (Throwable $t) {
      $this->logException($t);
      if ($this->entityManager->isTransactionActive()) {
        $this->entityManager->rollback();
      }
      return false;
    }

    return true;
  }

  /** {@inheritdoc} */
  public function unlink(string $path): bool
  {
    if ($this->is_dir($path)) {
      return false;
    }
    /** @var Entities\DatabaseStorageFile $dirEntry */
    $dirEntry = $this->fileFromFileName($path);
    if (empty($dirEntry)) {
      throw new Exceptions\DatabaseStorageException(
        $this->l->t('Unable to find database entity for path "%s".', $path)
      );
    }
    if ($dirEntry instanceof Entities\DatabaseStorageFolder) {
      throw new Exceptions\DatabaseStorageException(
        $this->l->t('Path "%s" is a directory.', $path)
      );
    }

    $parent = $dirEntry->getParent();
    if (empty($parent)) {
      throw new Exceptions\DatabaseStorageException(
        $this->l->t('Unable to find document container for path "%s".', $path)
      );
    }
    $file = $dirEntry->getFile();
    if (empty($file)) {
      throw new Exceptions\DatabaseStorageException(
        $this->l->t('The directory entry "%s" is not linked to a file.', $path)
      );
    }

    $this->entityManager->beginTransaction();
    try {
      // @todo implement side-effects

      $dirEntry->setParent(null);
      $dirEntry->setFile(null);
      $this->entityManager->remove($dirEntry);

      $this->flush();

      $this->entityManager->commit();

      $this->unsetFileNameCache($path);
    } catch (Throwable $t) {
      $this->logException($t);
      if ($this->entityManager->isTransactionActive()) {
        $this->entityManager->rollback();
      }
      return false;
    }

    return true;
  }
}
