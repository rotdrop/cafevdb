<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
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
use OCA\CAFEVDB\Service\OrganizationalRolesService;
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
  use \OCA\CAFEVDB\Traits\DateTimeTrait;
  use DatabaseStorageNodeNameTrait;

  /** @var Entities\Musician */
  private $musician;

  /** @var Entities\Project */
  private $project;

  /** @var Entities\ProjectParticipant */
  private $participant;

  /** @var ProjectService */
  private $projectService;

  /** @var bool Whether the current user belongs to the treasurer group. */
  private $isTreasurer;

  /** {@inheritdoc} */
  public function __construct($params)
  {
    parent::__construct($params);
    $this->participant = $params['participant'];
    $this->project = $this->participant->getProject();
    $this->musician = $this->participant->getMusician();
    $this->projectService = $this->di(ProjectService::class);

    $this->getRootFolder(create: false);

    $organizationalRolesService = $this->di(OrganizationalRolesService::class);
    $userId = $this->entityManager->getUserId();
    $this->isTreasurer = $organizationalRolesService->isTreasurer($userId, allowGroupAccess: true);
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
        $this->project->getName(), 'participants', $this->participant->getMusician()->getUserIdSlug(),
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
        if ($dirEntry->getFile()->getId() == $file->getId()) {
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
   * type FieldType::SERVICE_FEE.
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
      if ($this->entityManager->isTransactionActive()) {
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
   * type FieldType::SERVICE_FEE.
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

      foreach ($components as $component) {
        $folderEntity = $folderEntity->getFolderByName($component);
        if (empty($folderEntity)) {
          throw new UnexpectedValueException($this->l->t('Folder "%s" does not exist.', $dirName));
        }
      }

      /** @var Entities\DatabaseStorageFile $dirEntry */
      foreach ($folderEntity->getDocuments() as $dirEntry) {
        if ($dirEntry->getFile()->getId() == $file->getId()) {
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
      throw new Exceptions\DatabaseException($this->l->t('Unable to remove file for field-datum "%s".', [
        (string)$fieldDatum->getProjectParticipant()
      ]));
    }
  }

  /**
   * Helper functino in order to migrate to DatabaseStorageDirEntry.
   *
   * @param int $id
   *
   * @return null|DateTimeInterface
   */
  private function getSepaDebitMandatesChangedForMigration(int $id):?DateTimeInterface
  {
    $connection = $this->entityManager->getConnection();
    $sql = 'SELECT t.sepa_debit_mandates_changed
FROM Musicians t
WHERE t.id = ?';
    $stmt = $connection->prepare($sql);
    $stmt->bindValue(1, $id);
    try {
      $value = $stmt->executeQuery()->fetchOne();
      $stmt->closeCursor();
    } catch (InvalidFieldNameException $t) {
      $this->logException($t, 'Column does not exist, migration probably has already been applied.');
      return self::ensureDate(null);
    }
    return self::convertToDateTime($value);
  }

  /**
   * As the entities are already changed to contain directory entries but the
   * data-base tables still may contain file ids we need to carefully allow both.
   *
   * This function fetches the file referenced by $id either directly from the
   * Files table, or if a dir-entry is found, checks whether its root belongs
   * to our storage and then uses the directory entry's file.
   *
   * @param null|int $fileId Either a file id or a directory entry id.
   *
   * @return null|Entities\EncryptedFile
   */
  private function getFileForMigration(?int $fileId):?Entities\EncryptedFile
  {
    if (empty($fileId)) {
      return null;
    }
    $connection = $this->entityManager->getConnection();
    try {
      // determine if it exists in either table
      $sql = 'SELECT COUNT(*)
FROM Files t
WHERE t.id = ' . $fileId;
      $stmt = $connection->prepare($sql);
      $numFiles = $stmt->executeQuery()->fetchOne();
      $stmt->closeCursor();

      $sql = 'SELECT COUNT(*)
FROM DatabaseStorageDirEntries t
WHERE t.id = ' . $fileId;
      $stmt = $connection->prepare($sql);
      $numDirEntries = $stmt->executeQuery()->fetchOne();
      $stmt->closeCursor();

      $this->logInfo('#FILES / #DIR_ENTRIES ' . $numFiles . ' / ' . $numDirEntries);

      if ($numDirEntries != 0) {
        /** @var Entities\DatabaseStorageFile $dirEntry */
        $dirEntry = $this->entityManager->find(Entities\DatabaseStorageFile::class, $fileId);

        // traverse the entry up to the root in order to determine if it is
        // the correct entry
        /** @var Entities\DatabaseStorage $storage */
        $storage = $this->getDatabaseRepository(Entities\DatabaseStorage::class)->getStorage($dirEntry);
        if (empty($storage)) {
          throw new UnexpectedValueException($this->l->t('Directory entry "%s" has no associated storage.', (string)$dirEntry));
        }

        if ($storage->getStorageId() == $this->getShortId()) {
          return $dirEntry->getFile();
        }
      }

      if ($numFiles != 0) {
        $file = $this->getDatabaseRepository(Entities\EncryptedFile::class)->findOneBy([
          'id' => $fileId
        ]);
        return $file;
      }

    } catch (Throwable $t) {
      // empty
    }
    return null;
  }

  /**
   * Fetch the file linked to a field datum. After changing to general
   * directory entries the stored value may now be either the id of an
   * EncryptedFile entitiy or the id of a directory entry.
   *
   * @param Entities\ProjectParticipantFieldDatum $fieldDatum
   *
   * @return null|Entities\EncryptedFile
   */
  private function getFieldDatumFileForMigration(Entities\ProjectParticipantFieldDatum $fieldDatum):?Entities\EncryptedFile
  {
    if ($fieldDatum->getField()->getDataType() != FieldType::DB_FILE) {
      return null;
    }
    $fileId = $fieldDatum->getOptionValue();
    return $this->getFileForMigration($fileId);
  }

  /**
   * The value stored in the debit-mandate entity may now either be a
   * reference to a directory entry or a file ...
   *
   * @param Entities\SepaDebitMandate $mandate
   *
   * @return null|Entities\EncryptedFile
   */
  private function getWrittenMandateForMigration(Entities\SepaDebitMandate $mandate):?Entities\EncryptedFile
  {
    $connection = $this->entityManager->getConnection();
    // first fetch the raw id from the mandate's table
    $sql = 'SELECT t.written_mandate_id
FROM SepaDebitMandates t
WHERE t.musician_id = ? AND t.sequence = ?';
    $stmt = $connection->prepare($sql);
    $stmt->bindValue(1, $mandate->getMusician()->getId());
    $stmt->bindValue(2, $mandate->getSequence());
    try {
      $writtenMandateId = $stmt->executeQuery()->fetchOne();
      $stmt->closeCursor();
      return $this->getFileForMigration($writtenMandateId);
    } catch (Throwable $t) {
      // empty
    }
    return null;
  }

  /**
   * The value stored in the payment-entity may now either be a
   * reference to a directory entry or a file ...
   *
   * @param Entities\CompositePayment $payment
   *
   * @return null|Entities\EncryptedFile
   */
  private function getPaymentSupportingDocumentForMigration(Entities\CompositePayment $payment):?Entities\EncryptedFile
  {
    $connection = $this->entityManager->getConnection();
    // first fetch the raw id from the mandate's table
    $sql = 'SELECT t.supporting_document_id
FROM CompositePayments t
WHERE t.id = ' . $payment->getId();
    $stmt = $connection->prepare($sql);
    try {
      $fileId = $stmt->executeQuery()->fetchOne();
      $stmt->closeCursor();
      return $this->getFileForMigration($fileId);
    } catch (Throwable $t) {
      // empty
    }
    return null;
  }

  /**
   * The value stored in the payment-entity may now either be a
   * reference to a directory entry or a file ...
   *
   * @param Entities\ProjectParticipantFieldDatum $fieldDatum
   *
   * @return null|Entities\EncryptedFile
   */
  private function getFieldDatumSupportingDocumentForMigration(Entities\ProjectParticipantFieldDatum $fieldDatum):?Entities\EncryptedFile
  {
    $connection = $this->entityManager->getConnection();
    // first fetch the raw id from the mandate's table
    $sql = 'SELECT t.supporting_document_id
FROM ProjectParticipantFieldsData t
WHERE t.field_id = ?
AND t.musician_id = ?
AND t.option_key = ?';
    $stmt = $connection->prepare($sql);
    $stmt->bindValue(1, $fieldDatum->getField()->getId());
    $stmt->bindValue(2, $fieldDatum->getMusician()->getId());
    $stmt->bindValue(3, $fieldDatum->getOptionKey(), 'uuid_binary');
    try {
      $fileId = $stmt->executeQuery()->fetchOne();
      $stmt->closeCursor();
      return $this->getFileForMigration($fileId);
    } catch (Throwable $t) {
      // empty
    }
    return null;
  }

  /**
   * Helper functino in order to migrate to DatabaseStorageDirEntry.
   *
   * @param int $id
   *
   * @return null|DateTimeInterface
   */
  private function getPaymentsChangedForMigration(int $id):?DateTimeInterface
  {
    $connection = $this->entityManager->getConnection();
    $sql = 'SELECT t.payments_changed
FROM Musicians t
WHERE t.id = ?';
    $stmt = $connection->prepare($sql);
    $stmt->bindValue(1, $id);
    try {
      $value = $stmt->executeQuery()->fetchOne();
      $stmt->closeCursor();
    } catch (InvalidFieldNameException $t) {
      $this->logException($t, 'Column does not exist, migration probably has already been applied.');
      return self::ensureDate(null);
    }
    return self::convertToDateTime($value);
  }

  /**
   * Helper functino in order to migrate to DatabaseStorageDirEntry.
   *
   * @param int $projectId
   *
   * @param int $musicianId
   *
   * @return null|DateTimeInterface
   */
  private function getParticipantFieldsDataChangedForMigration(int $projectId, int $musicianId):?DateTimeInterface
  {
    $connection = $this->entityManager->getConnection();
    $sql = 'SELECT t.participant_fields_data_changed
FROM ProjectParticipants t
WHERE t.project_id = ? AND t.musician_id = ?';
    $stmt = $connection->prepare($sql);
    $stmt->bindValue(1, $projectId);
    $stmt->bindValue(2, $musicianId);
    try {
      $value = $stmt->executeQuery()->fetchOne();
      $stmt->closeCursor();
    } catch (InvalidFieldNameException $t) {
      $this->logException($t, 'Column does not exist, migration probably has already been applied.');
      return self::ensureDate(null);
    }
    return self::convertToDateTime($value);
  }

  /**
   * These are now only used to migrate the stuff to the new
   * DatabaseStorageDirEntries tables.
   *
   * @return array
   */
  protected function getListingGeneratorsForMigration():array
  {
    // Arguably, these should be classes, but as PHP does not support multiple
    // inheritance this really would produce a lot of boiler-plate-code.
    return [
      // Supporting documents of Entities\ProjectParticipantFieldDatum
      new ParticipantsStorageGenerator([
        'skipDepthIfOther' => -1,
        'pathChain' => [
          $this->getSupportingDocumentsFolderName(),
          $this->getReceivablesFolderName(),
        ],
        'parentModificationTime' => fn() => $this->getParticipantFieldsDataChangedForMigration($this->project->getId(), $this->musician->getId()),
        'hasLeafNodes' => fn() => !$this->participant->getParticipantFieldsData()->forAll(
          fn($key, Entities\ProjectParticipantFieldDatum $fieldDatum)
          =>
          empty($this->getFieldDatumSupportingDocumentForMigration($fieldDatum))
        ),
        'createLeafNodes' => function($dirName, $subDirectoryPath) {
          $modificationTime = $this->getParticipantFieldsDataChangedForMigration($this->project->getId(), $this->musician->getId());
          $activeFieldData = $this->participant->getParticipantFieldsData()->filter(
            fn(Entities\ProjectParticipantFieldDatum $fieldDatum)
            =>
            !empty($this->getFieldDatumSupportingDocumentForMigration($fieldDatum))
          );

          /** @var Entities\ProjectParticipantFieldDatum $fieldDatum */
          foreach ($activeFieldData as $fieldDatum) {
            $file = $this->getFieldDatumSupportingDocumentForMigration($fieldDatum);
            $fileInfo = $this->projectService->participantFileInfo($fieldDatum, newFile: $file, includeDeleted: true);
            if (empty($fileInfo)) {
              continue; // should not happen here because of ->filter().
            }
            $fileName = $this->buildPath($fileInfo['pathName']);

            list('dirname' => $fileDirName, 'basename' => $baseName) = self::pathInfo($fileName);
            if ($fileDirName == $dirName) {
              $this->files[$dirName][$baseName] = $fileInfo['file'];
            } elseif (strpos($fileDirName, $dirName) === 0) {
              list($baseName) = explode(self::PATH_SEPARATOR, substr($fileDirName, strlen($dirName)), 1);
              $this->files[$dirName][$baseName] = new DirectoryNode($baseName, $modificationTime);
            }
          }
        },
      ]),
      // supporting documents of Entities\CompositePayment
      new ParticipantsStorageGenerator([
        'skipDepthIfOther' => -1,
        'pathChain' => [
          $this->getSupportingDocumentsFolderName(),
          $this->getBankTransactionsFolderName(),
        ],
        'parentModificationTime' => fn() => $this->getPaymentsChangedForMigration($this->musician->getId()),
        'hasLeafNodes' => fn() => $this->isTreasurer && !$this->musician->getPayments()->forAll(
          fn($key, Entities\CompositePayment $compositePayment) => (
            $compositePayment->getProjectPayments()->matching(
              DBUtil::criteriaWhere([ 'project' => $this->project ])
            )->count() == 0
            || empty($this->getPaymentSupportingDocumentForMigration($compositePayment))
          )
        ),
        'createLeafNodes' => function($dirName, $subDirectoryPath) {
          /** @var Entities\CompositePayment $compositePayment */
          foreach ($this->musician->getPayments() as $compositePayment) {
            $projectPayments = $compositePayment->getProjectPayments()->matching(
              DBUtil::criteriaWhere([ 'project' => $this->project ])
            );
            if ($projectPayments->count() == 0) {
              continue;
            }
            $file = $this->getPaymentSupportingDocumentForMigration($compositePayment);
            if (empty($file)) {
              continue;
            }
            // enforce the "correct" file-name
            $dbFileName = $file->getFileName();
            $baseName = $this->getPaymentRecordFileName($compositePayment) . '.' . pathinfo($dbFileName, PATHINFO_EXTENSION);
            $fileName = $this->buildPath($subDirectoryPath . self::PATH_SEPARATOR . $baseName);
            list('basename' => $baseName) = self::pathInfo($fileName);
            $this->files[$dirName][$baseName] = $file;
          }
        },
      ]),
      // debit mandates
      new ParticipantsStorageGenerator([
        'skipDepthIfOther' => -1,
        'pathChain' => [
          $this->getDebitMandatesFolderName(),
        ],
        'parentModificationTime' => function() {
          $modificationTime = $this->getSepaDebitMandatesChangedForMigration($this->musician->getId());
          /** @var Entities\SepaDebitMandate $debitMandate */
          foreach ($this->musician->getSepaDebitMandates() as $debitMandate) {

            $writtenMandate = $this->getWrittenMandateForMigration($debitMandate);
            if (!empty($writtenMandate)) {
              $modificationTime = max($modificationTime, self::ensureDate($writtenMandate->getUpdated()));
            }
          }
          return $modificationTime;
        },
        'hasLeafNodes' => function() {
          if (!$this->isTreasurer) {
            return false;
          }
          $membersProjectId = $this->getClubMembersProjectId();
          $projectId = $this->project->getId();
          return !$this->musician->getSepaDebitMandates()->forAll(
            function($key, Entities\SepaDebitMandate $debitMandate) use ($membersProjectId, $projectId) {
              $mandateProjectId = $debitMandate->getProject()->getId();
              return $mandateProjectId != $membersProjectId && $mandateProjectId != $projectId && empty($this->getWrittenMandateForMigration($debitMandate));
            }
          );
        },
        'createLeafNodes' => function($dirName, $subDirectoryPath) {
          $projectId = $this->project->getId();
          $membersProjectId = $this->getClubMembersProjectId();
          /** @var Entities\SepaDebitMandate $debitMandate */
          $projectMandates = $this->musician->getSepaDebitMandates()->filter(
            function($debitMandate) use ($membersProjectId, $projectId) {
              $mandateProjectId = $debitMandate->getProject()->getId();
              return $mandateProjectId === $membersProjectId || $mandateProjectId === $projectId;
            });
          foreach ($projectMandates as $debitMandate) {
            $file = $this->getWrittenMandateForMigration($debitMandate);
            if (empty($file)) {
              continue;
            }
            // enforce the "correct" file-name
            $extension = '.' . pathinfo($file->getFileName(), PATHINFO_EXTENSION);
            $baseName = $this->getDebitMandateFileName($debitMandate) . $extension;
            $fileName = $this->buildPath($subDirectoryPath . self::PATH_SEPARATOR . $baseName);
            list('basename' => $baseName) = self::pathInfo($fileName);
            $this->files[$dirName][$baseName] = $file;
          }
        },
      ]),
      // FieldType::DB_FILE
      new ParticipantsStorageGenerator([
        'skipDepthIfOther' => 1,
        'pathChain' => [],
        'parentModificationTime' => fn() => $this->getParticipantFieldsDataChangedForMigration($this->project->getId(), $this->musician->getId()),
        'hasLeafNodes' => fn() => $this->participant->getParticipantFieldsData()->filter(
          function(Entities\ProjectParticipantFieldDatum $fieldDatum) {
            if ($fieldDatum->getField()->getDataType() == FieldType::SERVICE_FEE) {
              return false;
            }
            $file = $this->getFieldDatumFileForMigration($fieldDatum);
            if (empty($file)) {
              return false;
            }
            if (empty($this->projectService->participantFileInfo($fieldDatum, newFile: $file, includeDeleted: true))) {
              return false;
            }
            return true;
          })->count() > 0,
        'createLeafNodes' => function($dirName, $subDirectoryPath) {

          $modificationTime = $this->getParticipantFieldsDataChangedForMigration($this->project->getId(), $this->musician->getId());

          /** @var Entities\ProjectParticipantFieldDatum $fieldDatum */
          foreach ($this->participant->getParticipantFieldsData() as $fieldDatum) {

            if ($fieldDatum->getField()->getDataType() != FieldType::DB_FILE) {
              continue;
            }

            $file = $this->getFieldDatumFileForMigration($fieldDatum);

            $fileInfo = $this->projectService->participantFileInfo($fieldDatum, newFile: $file, includeDeleted: true);
            if (empty($fileInfo)) {
              continue;
            }

            $fileName = $this->buildPath($fileInfo['pathName']);

            list('dirname' => $fileDirName, 'basename' => $baseName) = self::pathInfo($fileName);
            if ($fileDirName == $dirName) {
              $this->files[$dirName][$baseName] = $fileInfo['file'];
            } elseif (strpos($fileDirName, $dirName) === 0) {
              list($baseName) = explode(self::PATH_SEPARATOR, substr($fileDirName, strlen($dirName)), 1);
              $this->files[$dirName][$baseName] = new DirectoryNode($baseName, $modificationTime);
            }
          }
        },
      ]),
    ];
  }

  /**
   * In order to aid database migration processes this funtction will remain
   * here until the base layout of the database is changed.
   *
   * @param string $dirName
   *
   * @return DateTimeInterface
   *
   * @see \OCA\CAFEVDB\Maintenance\Migrations\RootDirectoryEntries
   */
  public function getDirectoryModificationTimeForMigration(string $dirName):DateTimeInterface
  {
    $isTreasurer = $this->isTreasurer;
    $this->isTreasurer = true;

    $directory = $this->fileFromFileName($dirName);
    if ($directory instanceof DirectoryNode) {
      $date = $directory->minimalModificationTime ?? (new DateTimeImmutable('@1'));
    } elseif ($directory instanceof Entities\DatabaseStorageFolder) {
      $date = $directory->getUpdated();
    } else {
      $date = new DateTimeImmutable('@1');
    }

    // maybe we should skip the read-dir for performance reasons.
    /** @var Entities\File $node */
    foreach ($this->findFilesForMigration($dirName) as $nodeName => $node) {
      if ($node instanceof Entities\File) {
        $updated = $node->getUpdated();
      } elseif ($node instanceof DirectoryNode) {
        $updated = $node->minimalModificationTime ?? (new DateTimeImmutable('@1'));
        if ($nodeName != '.') {
          $recursiveModificationTime = $this->getDirectoryModificationTimeForMigration($dirName . self::PATH_SEPARATOR . $nodeName);
          if ($recursiveModificationTime > $updated) {
            $updated = $recursiveModificationTime;
          }
        }
      } elseif ($node instanceof Entities\DatabaseStorageDirEntry) {
        $updated = $node->getUpdated();
        if ($nodeName != '.' && $node instanceof Entities\DatabaseStorageFolder) {
          $recursiveModificationTime = $this->getDirectoryModificationTimeForMigration($dirName . self::PATH_SEPARATOR . $nodeName);
          if ($recursiveModificationTime > $updated) {
            $updated = $recursiveModificationTime;
          }
        }
      } else {
        $this->logError('Unknown directory entry in ' . $dirName);
        $updated = new DateTimeImmutable('@1');
      }
      if ($updated > $date) {
        $date = $updated;
      }
    }

    $this->isTreasurer = $isTreasurer;

    return $date;
  }

  /**
   * In order to aid database migration processes this funtction will remain
   * here until the base layout of the database is changed.
   *
   * @param string $dirName
   *
   * @return array
   *
   * @see \OCA\CAFEVDB\Maintenance\Migrations\RootDirectoryEntries
   */
  public function findFilesForMigration(string $dirName):array
  {
    $dirName = self::normalizeDirectoryName($dirName);
    if (false && !empty($this->files[$dirName])) {
      return $this->files[$dirName];
    }

    $this->files[$dirName] = [
      '.' => new DirectoryNode('.', new DateTimeImmutable('@1')),
    ];

    // the mount provider currently disables soft-deleteable filter ...
    $filterState = $this->disableFilter(EntityManager::SOFT_DELETEABLE_FILTER);

    $dirInfos = $this->getListingGeneratorsForMigration();

    $depth = count(Util::explode(self::PATH_SEPARATOR, $dirName));
    $subDirMatch = false;
    /** @var ParticipantsStorageGenerator $dirInfo */
    foreach ($dirInfos as $dirInfo) {
      $pathChain = $dirInfo->pathChain();
      $subDirectoryPath = '';
      while (strpos($dirName, $subDirectoryPath) === 0 && !empty($pathChain)) {
        $subDirectoryPath .= self::PATH_SEPARATOR . array_shift($pathChain);
        list('dirname' => $subDirectoryPath) = self::pathInfo($this->buildPath($subDirectoryPath . self::PATH_SEPARATOR . '_'));
      }
      if (strpos($dirName, $subDirectoryPath) === 0 && empty($pathChain)) {
        if ($subDirMatch && $dirInfo->skipDepthIfOther() > 0 && $depth >= $dirInfo->skipDepthIfOther()) {
          continue;
        }
        // create leaf entries
        $dirInfo->createLeafNodes($dirName, $subDirectoryPath);
      } elseif (strpos($subDirectoryPath, $dirName) === 0) {
        // create parent
        $modificationTime = $dirInfo->parentModificationTime();
        $hasLeafNodes = $dirInfo->hasLeafNodes();
        if (!empty($modificationTime) && !$hasLeafNodes) {
          // just update the time-stamp of the parent in order to trigger
          // update after deleting records.
          $this->files[$dirName]['.']->updateModificationTime($modificationTime);
        } elseif (!empty($modificationTime) || $hasLeafNodes) {
          // add a directory entry
          list($baseName) = explode(self::PATH_SEPARATOR, substr($subDirectoryPath, strlen($dirName)), 1);
          if (empty($this->files[$dirName][$baseName])) {
            $this->files[$dirName][$baseName] = new DirectoryNode($baseName, $modificationTime);
          } else {
            $this->files[$dirName][$baseName]->updateModificationTime($modificationTime);
          }
        }
        $subDirMatch = true;
      }
    }

    if (!empty($modificationTime)) {
      // update the time-stamp of the parent.
      $this->files[$dirName]['.']->updateModificationTime($modificationTime);
    }

    $filterState && $this->enableFilter(EntityManager::SOFT_DELETEABLE_FILTER);

    return $this->files[$dirName];
  }
}
