<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine  <himself@claus-justus-heine.de>
 * @copyright 2024, Claus-Justus Heine <himself@claus-justus-heine.de>
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
    if (empty($extension) && !empty($file['name'])) {
      $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
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
  public function isUpdatable($path)
  {
    $result = $this->file_exists($path);
    return $result;
  }

   /** {@inheritdoc} */
  public function unlink($path)
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

  /**
   * Parse the filename of the given path and extract the tax type and the
   * assessment period.
   *
   * @param string $path
   *
   * @return null|array
   * ```
   * [ 'taxType' => TYPE, 'assessmentPeriodStart' => YYYY, 'assessmentPeriodEnd' => YYYY ]
   * ```
   * \null is returned if $path does not conform to the legal naming scheme.
   */
  protected function parsePath(string $path):?array
  {
    $path = StorageUtil::uploadBasename($path);
    $fileName = pathinfo($path, PATHINFO_FILENAME);
    $parts = explode('-', $fileName);
    if (count($parts) != 4) {
      return null;
    }
    $l10nTaxType = $parts[1];
    $assessmentPeriodStart = $parts[2];
    $assessmentPeriodEnd = $parts[3];
    $taxType = null;
    foreach (array_values(EnumTaxType::values()) as $taxType) {
      if ($this->appL10n()->t((string)$taxType) == $l10nTaxType) {
        break;
      }
      $taxType = null;
    }
    if (empty($taxType)) {
      return null;
    }
    if ($fileName != $this->getLegacyTaxExemptionNoticeFileName($taxType, $assessmentPeriodStart, $assessmentPeriodEnd)) {
      return null;
    }
    return compact(['taxType', 'assessmentPeriodStart', 'assessmentPeriodEnd']);
  }

  /**
   * {@inheritdoc}
   *
   * Allow rename if the change of the name respects the implied naming
   * scheme. In this case also the underlying entity is updated.
   */
  public function rename($path1, $path2)
  {
    /** @var Entities\DatabaseStorageFile $dirEntry */
    $dirEntry = $this->fileFromFileName($path1);
    if (empty($dirEntry)) {
      throw new Exceptions\DatabaseStorageException(
        $this->l->t('Unable to find database entity for path "%s".', $path1)
      );
    }
    if ($dirEntry instanceof Entities\DatabaseStorageFolder) {
      throw new Exceptions\DatabaseStorageException(
        $this->l->t('Path "%s" is a directory.', $path1)
      );
    }
    if ($dirEntry instanceof InMemoryFileNode) {
      $dirEntry = $this->persistInMemoryFileNode($dirEntry);
    }

    if (!$this->isReadMe($path2)) {
      $newValues = $this->parsePath($path2);
      if (empty($newValues)) {
        return false;
      }

      /** @var Repositories\CompositePaymentsRepository $repository */
      $repository = $this->entityManager->getRepository(Entity::class);
      $entity = $repository->findOneBy([
        'writtenNotice' => $dirEntry,
      ]);
    }

    $this->entityManager->beginTransaction();
    try {
      if (!empty($entity)) {
        foreach ($newValues as $key => $value) {
          $entity[$key] = $value;
        }

        $extension = pathinfo($path2, PATHINFO_EXTENSION);
        $newName = $this->getTaxExemptionNoticeFileName($entity, $extension);

        if ($newName != basename($path2)) {
          throw new UnexpectedValueException($this->l->t('Generated name "%1$s" differs from target name "%2$s" after updating entity "%3$s".', [
            $newName, basename($path2), (string)$entity,
          ]));
        }
      }

      $dirEntry->setName(basename($path2));

      $this->flush();
      $this->entityManager->commit();

      // local in-memory file cache
      $this->setFileNameCache($path2, $dirEntry);
      $this->unsetFileNameCache($path1);
    } catch (Throwable $t) {
      if ($this->entityManager->isTransactionActive()) {
        $this->entityManager->rollback();
      }
      throw new Exceptions\DatabaseException($this->l->t('Unable to update "%$1s" from value-set "%2$s".', (string)$dirEntry, print_r($newValues, true)));
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

    $this->entityManager->beginTransaction();
    try {
      /** @var Entities\DatabaseStorageFile $dirEntry */
      $dirEntry = $this->fileFromFileName($path);
      if (empty($dirEntry)) {
        if (!$this->isReadMe($path)) {
          $newValues = $this->parsePath($path);
          if (empty($newValues)) {
            throw new UnexpectedValueException($this->l->t('Path "%s" seems to be invalid.', $path));
          }

          /** @var Repositories\CompositePaymentsRepository $repository */
          $repository = $this->entityManager->getRepository(Entity::class);
          $entity = $repository->findOneBy($newValues);
          if (empty($entity)) {
            // The entity must exist before hand
            throw new Exceptions\DatabaseEntityNotFoundException($this->l->t('Unable to find a matching entity for the given path "%s".', $path));
          }
        }
        $file = new Entities\EncryptedFile(basename($path), '', '');
        if ($mtime !== null) {
          $file->setCreated($mtime);
        }
        $this->persist($file);
        $this->flush();
        // search for the folder
        $rootFolder = $this->getRootFolder(create: true);
        if (empty($rootFolder)) {
          throw new UnexpectedValueException($this->l->t('Root-folder does not exist.'));
        }
        $dirEntry = $rootFolder->addDocument($file, basename($path));
        if ($mtime !== null) {
          $dirEntry->setCreated($mtime);
        }
        if (!empty($entity)) {
          $entity->setWrittenNotice($dirEntry);
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
