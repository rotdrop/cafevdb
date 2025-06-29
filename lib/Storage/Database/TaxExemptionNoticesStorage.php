<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine  <himself@claus-justus-heine.de>
 * @copyright 2024, 2025, Claus-Justus Heine <himself@claus-justus-heine.de>
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
use UnexpectedValueException;

use OCP\EventDispatcher\IEventDispatcher;

use OCA\CAFEVDB\Common\Util;
use OCA\CAFEVDB\Constants;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types\EnumTaxType;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities\TaxExemptionNotice as Entity;
use OCA\CAFEVDB\Events;
use OCA\CAFEVDB\Exceptions;
use OCA\CAFEVDB\Storage\StorageUtil;

/**
 * Storage implementation for data-base storage, including access to
 * encrypted entities.
 */
class TaxExemptionNoticesStorage extends Storage
{
  use \OCA\CAFEVDB\Toolkit\Traits\DateTimeTrait;
  use DatabaseStorageNodeNameTrait;

  /** @var \OCA\CAFEVDB\Database\Doctrine\ORM\Repositories\EntityRepository */
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
      $this->logDebug('Entity-manager shoot down, re-fetching cached entities.');

      $this->getRootFolder(create: false);
      $this->entityRepository = $this->getDatabaseRepository(Entity::class);
    });
  }

  /**
   * Find an existing directory entry for the given file.
   *
   * @param Entity $entity
   *
   * @param Entities\EncryptedFile $file
   *
   * @return null|Entities\DatabaseStorageFile
   */
  public function findDocument(Entity $entity, Entities\EncryptedFile $file):?Entities\DatabaseStorageFile
  {
    /** @var Entities\DatabaseStorageFile $dirEntry */
    foreach ($file->getDatabaseStorageDirEntries() as $dirEntry) {
      if ($dirEntry === $this->rootFolder) {
        return $dirEntry;
      }
    }
    return null;
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
   * @param bool $replace If \true replace the existing file references by the
   * given file. Otherwise it is an error if an entry already exists and
   * points to another file.
   *
   * @return Entities\DatabaseStorageFile
   */
  public function addDocument(
    Entity $entity,
    Entities\EncryptedFile $file,
    bool $flush = true,
    bool $replace = false,
  ):Entities\DatabaseStorageFile {
    $mimeType = $file->getMimeType();
    $extension = Util::fileExtensionFromMimeType($mimeType);
    if (empty($extension) && !empty($file->getFileName())) {
      $extension = strtolower(pathinfo($file->getFileName(), PATHINFO_EXTENSION));
    }
    $fileName = $this->getTaxExemptionNoticeFileName($entity, $extension);

    if ($flush) {
      $this->entityManager->beginTransaction();
    }
    try {
      // search for the folder
      $rootFolder = $this->getRootFolder(create: true);
      if (empty($rootFolder)) {
        throw new UnexpectedValueException($this->l->t('Root-folder does not exist.'));
      }
      $documentEntity = $rootFolder->addDocument($file, $fileName, replace: $replace);
      $this->persist($documentEntity);

      if ($flush) {
        $this->flush();
        $this->entityManager->commit();
      }

    } catch (Throwable $t) {
      if ($this->entityManager->isTransactionActive()) {
        $this->entityManager->rollback();
      }
      throw new Exceptions\DatabaseException($this->l->t('Unable to add written tax exemption notice for "%s".', (string)$entity));
    }

    return $documentEntity;
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
    return $this->addDocument($entity, $file, $flush, replace: true);
  }

  /**
   * Remove the directory entry for the given entity from the storage.
   *
   * @param Entity $entity
   *
   * @param bool $flush
   *
   * @return void
   *
   * @todo Seems to be unused.
   */
  public function removeDocument(Entity $entity, bool $flush = true):void
  {
    $file = $entity->getWrittenNotice();
    if (empty($file)) {
      throw new UnexpectedValueException($this->l->t('Tax exemption notice "%s" has no hard-copy attached.', (string)$entity));
    }
    $fileId = $file->getFile()->getId();

    if ($flush) {
      $this->entityManager->beginTransaction();
    }
    try {
      // search for the folder
      $rootFolder = $this->getRootFolder(create: false);
      if (empty($rootFolder)) {
        throw new UnexpectedValueException($this->l->t('Root-folder does not exist.'));
      }
      /** @var Entities\DatabaseStorageFile $dirEntry */
      foreach ($rootFolder->getDocuments() as $dirEntry) {
        if ($dirEntry->getFile()->getId() == $fileId) {
          $this->entityManager->remove($dirEntry);
        }
      }

      if ($flush) {
        $this->flush();
        $this->entityManager->commit();
      }

    } catch (Throwable $t) {
      if ($this->entityManager->isTransactionActive()) {
        $this->entityManager->rollback();
      }
      throw new Exceptions\DatabaseException($this->l->t('Unable to remove debit-mandate "%s".', $entity->getMandateDate()));
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function findFiles(string $dirName, bool $ignored = false):array
  {
    return parent::findFiles($dirName, rootIsMandatory: false);
  }

  /**  {@inheritdoc} */
  public function isUpdatable(string $path): bool
  {
    $result = $this->file_exists($path);
    return $result;
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

    /** @var Repositories\CompositePaymentsRepository $repository */
    $repository = $this->entityManager->getRepository(Entity::class);
    $entities = $repository->findBy([
      'writtenNotice' => $dirEntry,
    ]);

    $this->entityManager->beginTransaction();
    try {

      /** @var Entity $entity */
      foreach ($entities as $entity) {
        $entity->setWrittenNotice(null);
      }

      $dirEntry->unlink();
      $dirEntry->setFile(null);
      $this->entityManager->remove($dirEntry);

      $this->flush();
      $this->entityManager->commit();

      // update our local files cache
      $this->unsetFileNameCache($path);
    } catch (\Throwable $t) {
      $this->logException($t);
      if ($this->entityManager->isTransactionActive()) {
        $this->entityManager->rollback();
      }
      return false;
    }
    return true;
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
        'finance', 'tax-exemption-notices',
      ])
      . self::PATH_SEPARATOR;
  }
}
