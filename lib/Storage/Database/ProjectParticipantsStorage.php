<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2011-2014, 2016, 2020, 2021, 2022, 2023, 2024, Claus-Justus Heine <himself@claus-justus-heine.de>
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

use UnexpectedValueException;
use DateTimeImmutable;
use DateTimeInterface;

use OCP\EventDispatcher\IEventDispatcher;

// F I X M E: those are not public, but ...
use OC\Files\Storage\Common as AbstractStorage;
use OC\Files\Storage\PolyFill\CopyDirectory;

use Icewind\Streams\CallbackWrapper;
use Icewind\Streams\CountWrapper;
use Icewind\Streams\IteratorDirectory;

use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Service\ProjectService;
use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types\EnumParticipantFieldDataType as FieldType;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types\EnumParticipantFieldMultiplicity as FieldMultiplicity;
use OCA\CAFEVDB\Database\Doctrine\Util as DBUtil;
use OCA\CAFEVDB\Common\Util;
use OCA\CAFEVDB\Exceptions;
use OCA\CAFEVDB\Events;

/**
 * Storage implementation for data-base storage, including access to
 * encrypted entities.
 */
class ProjectParticipantsStorage extends Storage
{
  use \OCA\CAFEVDB\Toolkit\Traits\DateTimeTrait;
  use DatabaseStorageNodeNameTrait;

  /** @var Entities\Musician */
  private $musician;

  /** @var Entities\Project */
  private $project;

  /** @var Entities\ProjectParticipant */
  private $participant;

  /** @var ProjectService */
  private $projectService;

  /** {@inheritdoc} */
  public function __construct($params)
  {
    parent::__construct($params);
    $this->participant = $params['participant'];
    $this->project = $this->participant->getProject();
    $this->musician = $this->participant->getMusician();
    $this->projectService = $this->di(ProjectService::class);

    $this->getRootFolder(create: false);

    /** @var IEventDispatcher $eventDispatcher */
    $eventDispatcher = $this->di(IEventDispatcher::class);
    $eventDispatcher->addListener(Events\EntityManagerBoundEvent::class, function(Events\EntityManagerBoundEvent $event) {
      $this->logDebug('Entity-manager shoot down, re-fetching cached entities.');
      // the mount provider currently disables soft-deleteable filter ...
      $filterState = $this->disableFilter(EntityManager::SOFT_DELETEABLE_FILTER);
      try {
        $projectId = $this->participant->getProject()->getId();
        $musicianId = $this->participant->getMusician()->getId();
        $this->clearDatabaseRepository();

        $this->getRootFolder(create: false);

        $this->participant = $this->getDatabaseRepository(Entities\ProjectParticipant::class)
          ->find([
            'project' => $projectId,
            'musician' => $musicianId,
          ]);
        $this->project = $this->participant->getProject();
        $this->musician = $this->participant->getMusician();
      } catch (\Throwable $t) {
        $this->logException($t);
      }
      $filterState && $this->enableFilter(EntityManager::SOFT_DELETEABLE_FILTER);
    });
  }

  /**
   * {@inheritdoc}
   *
   * We expose all found documents in the projectParticipantFieldsData(),
   * payments() and the debitMandates(). Changes including deletions are
   * tracked in dedicated fields of the ProjectParticipant and Musician
   * entity.
   */
  protected function findFiles(string $dirName, bool $ignored = false):array
  {
    return parent::findFiles($dirName, rootIsMandatory: false);
  }


  /**
   * {@inheritdoc}
   */
  protected function getStorageModificationDateTime():DateTimeInterface
  {
    return self::ensureDate(empty($this->rootFolder) ? null : $this->rootFolder->getUpdated());
  }

  /** {@inheritdoc} */
  public function getShortId():string
  {
    // the mount provider currently disables soft-deleteable filter ...
    $filterState = $this->disableFilter(EntityManager::SOFT_DELETEABLE_FILTER);
    $result = implode(
      self::PATH_SEPARATOR, [
        'project',
        'participant',
        $this->project->getName(),
        $this->participant->getMusician()->getUserIdSlug(),
      ])
      . self::PATH_SEPARATOR;
    $filterState && $this->enableFilter(EntityManager::SOFT_DELETEABLE_FILTER);
    return $result;
  }

  /** {@inheritdoc} */
  protected function getRootFolder(bool $create = false):?Entities\DatabaseStorageFolder
  {
    $rootFolder = parent::getRootFolder($create);

    if ($create
        && !empty($rootFolder)
        && $this->participant->getDatabaseDocuments() != $this->storageEntity
    ) {
      $this->participant->setDatabaseDocuments($this->storageEntity);
      $this->flush();
    }

    return $this->rootFolder;
  }

  /**
   * Add an directory entry for the given debit-mandate and file.
   *
   * @param Entities\SepaDebitMandate $debitMandate
   *
   * @param Entities\EncryptedFile $file
   *
   * @param bool $flush
   *
   * @param bool $replace If \true replace the existing file references by the
   * given file. Otherwise it is an error if an entry already exists and
   * points to another file.
   *
   * @return null|Entities\DatabaseStorageFile
   */
  public function addDebitMandate(
    Entities\SepaDebitMandate $debitMandate,
    Entities\EncryptedFile $file,
    bool $flush = true,
    bool $replace = false,
  ):?Entities\DatabaseStorageFile {
    $mimeType = $file->getMimeType();
    $extension = Util::fileExtensionFromMimeType($mimeType);
    if (empty($extension) && !empty($file['name'])) {
      $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    }
    $fileName = $this->getDebitMandateFileName($debitMandate, $extension);

    if ($flush) {
      $this->entityManager->beginTransaction();
    }
    try {
      // search for the folder
      $rootFolder = $this->getRootFolder(create: true);
      $folderName = $this->getDebitMandatesFolderName();
      $folderEntity = $rootFolder->getFolderByName($folderName);

      if (empty($folderEntity)) {
        $folderEntity = $rootFolder->addSubFolder($folderName);
        $this->persist($folderEntity);
      }

      $documentEntity = $folderEntity->addDocument($file, $fileName, replace: $replace);
      $this->persist($documentEntity);

      if ($flush) {
        $this->flush();
        $this->entityManager->commit();
      }

    } catch (Throwable $t) {
      if ($this->entityManager->isTransactionActive()) {
        $this->entityManager->rollback();
      }
      throw new Exceptions\DatabaseException($this->l->t('Unable to add debit-mandate "%s".', $debitMandate->getMandateDate()));
    }

    return $documentEntity;
  }

  /**
   * Add or replace a directory entry for the given debit-mandate and file.
   *
   * @param Entities\SepaDebitMandate $debitMandate
   *
   * @param Entities\EncryptedFile $file
   *
   * @param bool $flush
   *
   * @param bool $replace
   *
   * @return null|Entities\DatabaseStorageFile
   */
  public function replaceDebitMandate(
    Entities\SepaDebitMandate $debitMandate,
    Entities\EncryptedFile $file,
    bool $flush = true,
    bool $replace = false,
  ):?Entities\DatabaseStorageFile {
    return $this->addDebitMandate($debitMandate, $file, $flush, replace: true);
  }

  /**
   * Remove the given debit mandate.
   *
   * @param Entities\SepaDebitMandate $debitMandate
   *
   * @param bool $flush
   *
   * @return void
   */
  public function removeDebitMandate(Entities\SepaDebitMandate $debitMandate, bool $flush = true):void
  {
    $file = $debitMandate->getWrittenMandate();
    if (empty($file)) {
      throw new UnexpectedValueException($this->l->t('Debit-mandate "%s" has no hard-copy attached.', $debitMandate->getMandateReference()));
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
      $folderName = $this->getDebitMandatesFolderName();
      $folderEntity = $rootFolder->getFolderByName($folderName);
      /** @var Entities\DatabaseStorageFile $dirEntry */
      foreach ($folderEntity->getDocuments() as $dirEntry) {
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
      throw new Exceptions\DatabaseException($this->l->t('Unable to remove debit-mandate "%s".', $debitMandate->getMandateDate()));
    }
  }

  /**
   * Add an directory entry for a supporting document for the given payment.
   *
   * @param Entities\CompositePayment $compositePayment
   *
   * @param Entities\EncryptedFile $file
   *
   * @param bool $flush
   *
   * @return null|Entities\DatabaseStorageFile
   */
  public function addCompositePayment(
    Entities\CompositePayment $compositePayment,
    Entities\EncryptedFile $file,
    bool $flush = true,
  ):?Entities\DatabaseStorageFile {
    $mimeType = $file->getMimeType();
    $extension = Util::fileExtensionFromMimeType($mimeType);
    if (empty($extension) && !empty($file['name'])) {
      $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    }
    $fileName = $this->getPaymentRecordFileName($compositePayment, $extension);

    if ($flush) {
      $this->entityManager->beginTransaction();
    }
    try {
      // search for the folder
      $rootFolder = $this->getRootFolder(create: true);
      $folderEntity = $rootFolder->addSubFolder($this->getSupportingDocumentsFolderName());
      $this->persist($folderEntity);
      $folderEntity = $folderEntity->addSubFolder($this->getBankTransactionsFolderName());
      $this->persist($folderEntity);

      $documentEntity = $folderEntity->addDocument($file, $fileName);
      $this->persist($documentEntity);

      if ($flush) {
        $this->flush();
        $this->entityManager->commit();
      }

    } catch (Throwable $t) {
      if ($this->entityManager->isTransactionActive()) {
        $this->entityManager->rollback();
      }
      throw new Exceptions\DatabaseException($this->l->t('Unable to add composite payment "%s".', $compositePayment->getId()));
    }

    return $documentEntity;
  }

  /**
   * Add an directory entry for a ProjectParticipantFieldDatum of type
   * FieldType::DB_FILE or for the optional supporting document of fields of
   * type FieldType:RECEIVABLES, FieldType::LIABILITIES.
   *
   * Missing sub-folders will be created.
   *
   * @param Entities\ProjectParticipantFieldDatum $fieldDatum
   *
   * @param Entities\EncryptedFile $file
   *
   * @param bool $flush
   *
   * @return null|Entities\DatabaseStorageFile
   */
  public function addFieldDatumDocument(
    Entities\ProjectParticipantFieldDatum $fieldDatum,
    Entities\EncryptedFile $file,
    bool $flush = true,
  ):?Entities\DatabaseStorageFile {
    /** @var Entities\ProjectParticipantField $field */
    $fileInfo = $this->projectService->participantFileInfo($fieldDatum, newFile: $file, includeDeleted: true);

    if ($flush) {
      $this->entityManager->beginTransaction();
    }

    try {
      $file = $fileInfo['file'];
      $fileName  = $fileInfo['baseName'];
      $dirName = $fileInfo['dirName'];

      // $dirName may actually be a deep path:
      // Belege/Forderungen/Erstattungen

      $components = Util::explode(self::PATH_SEPARATOR, $dirName);

      $folderEntity = $this->getRootFolder(create: true);
      foreach ($components as $component) {
        $subFolder = $folderEntity->getFileByName($component);
        if (empty($subFolder)) {
          $subFolder = $folderEntity->addSubFolder($component);
          $this->persist($subFolder);
        }
        $folderEntity = $subFolder;
      }
      $documentEntity = $folderEntity->addDocument($file, $fileName);
      $this->persist($documentEntity);

      if ($flush) {
        $this->flush();
        $this->entityManager->commit();
      }

    } catch (Throwable $t) {
      if ($flush && $this->entityManager->isTransactionActive()) {
        $this->entityManager->rollback();
      }
      throw new Exceptions\DatabaseException($this->l->t('Unable to add file for field-datum "%s".', [
        (string)$fieldDatum->getProjectParticipant()
      ]));
    }

    return $documentEntity;
  }

  /**
   * Remove all directory entries for a ProjectParticipantFieldDatum of type
   * FieldType::DB_FILE or for the optional supporting document of fields of
   * type FieldType:RECEIVABLES, FieldType::LIABILITIES.
   *
   * Empty sub-folders will be deleted.
   *
   * @param Entities\ProjectParticipantFieldDatum $fieldDatum
   *
   * @param bool $flush
   *
   * @return void
   */
  public function removeFieldDatumDocument(Entities\ProjectParticipantFieldDatum $fieldDatum, bool $flush = true):void
  {
    /** @var Entities\ProjectParticipantField $field */
    $field = $fieldDatum->getField();
    $fileInfo = $this->projectService->participantFileInfo($fieldDatum, includeDeleted: true);
    if (empty($fileInfo)) {
      throw new UnexpectedValueException($this->l->t('The field datum for field "%s" has no file attached to it.', [
        (string)$field,
      ]));
    }

    if ($flush) {
      $this->entityManager->beginTransaction();
    }
    try {

      $file = $fileInfo['file'];
      $dirName = $fileInfo['dirName'];

      // $dirName may actually be a deep path:
      // Belege/Forderungen/Erstattungen

      $components = Util::explode(self::PATH_SEPARATOR, $dirName);

      $folderEntity = $this->getRootFolder(create: false);
      if (empty($folderEntity)) {
        throw new UnexpectedValueException($this->l->t('Root-folder does not exist.'));
      }

      /** @var Entities\DatabaseStorageFolder $folderEntity */
      foreach ($components as $component) {
        $folderEntity = $folderEntity->getFolderByName($component);
        if (empty($folderEntity)) {
          throw new UnexpectedValueException($this->l->t('Folder "%s" does not exist.', $dirName));
        }
      }

      /** @var Entities\DatabaseStorageFile $dirEntry */
      foreach ($folderEntity->getDocuments() as $dirEntry) {
        if ($dirEntry->getFile()->getId() == $file->getId()) {
          $dirEntry->unlink();
          $this->entityManager->remove($dirEntry);
        }
      }

      /** Remove empty parents up to the root. */
      while (!$folderEntity->isRootFolder() && $folderEntity->isEmpty()) {
        $parentFolder = $folderEntity->getParent();
        $folderEntity->unlink();
        $this->entityManager->remove($folderEntity);
        $folderEntity = $parentFolder;
      }

      if ($flush) {
        $this->flush();
        $this->entityManager->commit();
      }

    } catch (Throwable $t) {
      if ($flush && $this->entityManager->isTransactionActive()) {
        $this->entityManager->rollback();
      }
      throw new Exceptions\DatabaseException($this->l->t('Unable to remove file for field-datum "%s".', [
        (string)$fieldDatum->getProjectParticipant()
      ]));
    }
  }

  /**
   * Update an existing directory entry for a ProjectParticipantFieldDatum of
   * type FieldType::DB_FILE or for the optional supporting document of fields
   * of type FieldType:RECEIVABLES, FieldType::LIABILITIES.
   *
   * As the complete path is determined by the name of the fields and its
   * options the function also renames all parent directory entries.
   *
   * @param Entities\ProjectParticipantFieldDatum $fieldDatum
   *
   * @param bool $flush
   *
   * @return bool \true if anything has changed.
   */
  public function updateFieldDatumDocument(Entities\ProjectParticipantFieldDatum $fieldDatum, bool $flush = true):bool
  {
    // fetch the current file information based on the existing directory structure
    $currentFileInfo = $this->projectService->participantFileInfo($fieldDatum, newFile: null, includeDeleted: true);

    // fetch the target file information pretending it would be a new file
    $targetFileInfo = $this->projectService->participantFileInfo($fieldDatum, newFile: $currentFileInfo['file'], includeDeleted: true);

    $currentComponents = Util::explode(self::PATH_SEPARATOR, $currentFileInfo['pathName']);
    $targetComponents = Util::explode(self::PATH_SEPARATOR, $targetFileInfo['pathName']);

    $needsFlush = false;
    if ($flush) {
      $this->entityManager->beginTransaction();
    }
    try {

      if (count($currentComponents) != count($targetComponents)) {
        // two possibilities: remove and add or throw
        $this->logWarn('Current path "' . $currentFileInfo['pathName'] . '" and target path "' . $targetFileInfo['pathName'] . '" have different numbers of components.');
        // Use add-remove in order to first increase the number of links. As
        // the paths have different numbers of components source and target
        // directory entries must reside in different directories.
        $this->addFieldDatumDocument($fieldDatum, $currentFileInfo['file'], flush: false);
        $this->removeFieldDatumDocument($fieldDatum, flush: false);
        $needsFlush = true;
      } else {
        /** @var Entities\DatabaseStorageDirEntry $dirEntry */
        $dirEntry = $currentFileInfo['dirEntry'];
        while (!$dirEntry->isRootFolder()) {
          $targetName = array_pop($targetComponents);
          if ($dirEntry->getName() != $targetName) {
            $needsFlush = true;
            $dirEntry->setName($targetName);
          }
          $dirEntry = $dirEntry->getParent();
        }
      }

      if ($flush) {
        $this->flush();
        $this->entityManager->commit();
      }
    } catch (Throwable $t) {
      if ($flush && $this->entityManager->isTransactionActive()) {
        $this->entityManager->rollback();
      }
      throw new Exceptions\DatabaseException($this->l->t('Unable to update file-name for field-datum "%s".', [
        (string)$fieldDatum->getProjectParticipant()
      ]));
    }

    return $needsFlush;
  }

  /**
   * {@inheritdoc}
   */
  protected function persistInMemoryFileNode(InMemoryFileNode $memoryNode, ?Entities\Musician $owner = null):Entities\DatabaseStorageFile
  {
    return parent::persistInMemoryFileNode($memoryNode, $this->musician);
  }
}
