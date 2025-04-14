<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine  <himself@claus-justus-heine.de>
 * @copyright 2024 Claus-Justus Heine <himself@claus-justus-heine.de>
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

use OCP\EventDispatcher\IEventDispatcher;

use OCA\CAFEVDB\Common\Util;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities\DonationReceipt as Entity;
use OCA\CAFEVDB\Events;
use OCA\CAFEVDB\Exceptions;

/**
 * Storage implementation for data-base storage, including access to
 * encrypted entities.
 */
class DonationReceiptsStorage extends Storage
{
  use \OCA\CAFEVDB\Toolkit\Traits\DateTimeTrait;
  use DatabaseStorageNodeNameTrait;

  /** @var \OCA\CAFEVDB\Database\Doctrine\ORM\Repositories\SepaBulkTransactionsRepository */
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
    return $entity->getDateOfReceipt();
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
    $year = $this->getBirthTimeFromEntity($entity)->format('Y');

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
    $fileName = $this->getDonationReceiptFileName($entity, $extension);

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

      $document = $yearFolder->addDocument($file, $fileName, replace: $replace)
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
   * Remove a document from the storage. Note that this does not remove the
   * file from the transaction entity.
   *
   * @param Entity $entity
   *
   * @param Entities\EncryptedFile $file
   *
   * @param bool $flush Whether to actually flush the operations to th db.
   *
   * @return void
   */
  public function removeDocument(
    Entity $entity,
    Entities\EncryptedFile $file,
    bool $flush = true,
  ):void {
    $document = $this->findDocument($entity, $file);
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
        'finance', 'receipts', 'donations',
      ])
      . self::PATH_SEPARATOR;
  }
}
