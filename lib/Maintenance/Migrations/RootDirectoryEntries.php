<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2022 Claus-Justus Heine <himself@claus-justus-heine.de>
 * @license AGPL-3.0-or-later
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU GENERAL PUBLIC LICENSE
 * License as published by the Free Software Foundation; either
 * version 3 of the License, or any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU AFFERO GENERAL PUBLIC LICENSE for more details.
 *
 * You should have received a copy of the GNU Lesser General Public
 * License along with this library.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace OCA\CAFEVDB\Maintenance\Migrations;

use Throwable;

use DateTimeImmutable;
use DateTimeInterface;

use OCP\ILogger;
use OCP\IL10N;
use OCP\AppFramework\IAppContainer;

use OCA\CAFEVDB\Wrapped\Doctrine\DBAL\Connection as DatabaseConnection;

use OCA\CAFEVDB\Wrapped\Doctrine\DBAL\Exception\InvalidFieldNameException;
use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Storage\Database as Storage;
use OCA\CAFEVDB\Storage\Database\DirectoryNode as MigrationDirectoryNode;
use OCA\CAFEVDB\Service;
use OCA\CAFEVDB\Exceptions;
use OCA\CAFEVDB\Constants;

/**
 * Remember the id of a mailing list.
 */
class RootDirectoryEntries extends AbstractMigration
{
  use \OCA\CAFEVDB\Traits\TimeStampTrait;
  use \OCA\CAFEVDB\Traits\DateTimeTrait;

  /** @var IAppContainer */
  protected $appContainer;

  protected static $sql = [
    self::STRUCTURAL => [
      'CREATE TABLE IF NOT EXISTS DatabaseStorages (
  id INT AUTO_INCREMENT NOT NULL,
  root_id INT DEFAULT NULL,
  storage_id VARCHAR(512) NOT NULL,
  UNIQUE INDEX UNIQ_3594ED235CC5DB90 (storage_id),
  UNIQUE INDEX UNIQ_3594ED2379066886 (root_id),
  PRIMARY KEY(id)
)',
      'ALTER TABLE DatabaseStorages ADD CONSTRAINT FK_3594ED2379066886 FOREIGN KEY IF NOT EXISTS(root_id) REFERENCES DatabaseStorageDirEntries (id)',
      //
      'ALTER TABLE ProjectParticipants ADD COLUMN IF NOT EXISTS database_documents_id INT DEFAULT NULL',
      'ALTER TABLE ProjectParticipants ADD CONSTRAINT FK_D9AE987BC6073910 FOREIGN KEY IF NOT EXISTS (database_documents_id) REFERENCES DatabaseStorages (id)',
      'CREATE UNIQUE INDEX IF NOT EXISTS UNIQ_D9AE987BC6073910 ON ProjectParticipants (database_documents_id)',
      //
      'ALTER TABLE Projects ADD COLUMN IF NOT EXISTS financial_balance_documents_storage_id INT DEFAULT NULL',
      'ALTER TABLE Projects ADD CONSTRAINT FK_A5E5D1F214CA24B1 FOREIGN KEY IF NOT EXISTS (financial_balance_documents_storage_id) REFERENCES DatabaseStorages (id)',
      'CREATE UNIQUE INDEX IF NOT EXISTS UNIQ_A5E5D1F214CA24B1 ON Projects (financial_balance_documents_storage_id)',
      //
      // make sure that the column exists s.t. the migration does not fail
      "ALTER TABLE SepaBulkTransactions ADD COLUMN IF NOT EXISTS sepa_transaction_data_changed DATETIME(6) DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)'",
      'ALTER TABLE Files ADD COLUMN IF NOT EXISTS original_file_name VARCHAR(512) DEFAULT NULL',
      "ALTER TABLE ProjectParticipants ADD COLUMN IF NOT EXISTS participant_fields_data_changed DATETIME(6) DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)'",
      "ALTER TABLE SepaBulkTransactions ADD COLUMN IF NOT EXISTS sepa_transaction_data_changed DATETIME(6) DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)'",
      "ALTER TABLE Musicians ADD COLUMN IF NOT EXISTS sepa_debit_mandates_changed DATETIME(6) DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)'",
      "ALTER TABLE Musicians ADD COLUMN IF NOT EXISTS sepa_debit_mandates_changed DATETIME(6) DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', ADD COLUMN IF NOT EXISTS payments_changed DATETIME(6) DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)'",
      "ALTER TABLE Projects ADD COLUMN IF NOT EXISTS financial_balance_supporting_documents_changed DATETIME(6) DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)'",
    ],
    self::TRANSACTIONAL => [
      'UPDATE SepaBulkTransactions SET updated = GREATEST(updated, sepa_transaction_data_changed)
WHERE sepa_transaction_data_changed IS NOT NULL',
      'UPDATE SepaBulkTransactions SET sepa_transaction_data_changed = updated
WHERE sepa_transaction_data_changed IS NULL',
      'UPDATE Files SET file_name = original_file_name
WHERE original_file_name IS NOT NULL AND file_name <> original_file_name',
    ],
  ];

  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct(
    IAppContainer $appContainer,
    ILogger $logger,
    IL10N $l10n,
    EntityManager $entityManager,
  ) {
    $this->appContainer = $appContainer;
    $this->logger = $logger;
    $this->l = $l10n;
    $this->entityManager = $entityManager;
  }
  // phpcs:enable

  /** {@inheritdoc} */
  public function description():string
  {
    return $this->l->t('Add a table which holds root directory entries');
  }

  /** {@inheritdoc} */
  public function execute():bool
  {
    if (!parent::execute()) {
      return false;
    }

    // determine potential conflicts caused by changing the target entity of
    // join columns

    // check on which side of the migrations we are
    $migrationState = $this->determineDirEntryMigrationState();

    $this->logInfo('MIGRATION STATE ' . $migrationState);

    if ($migrationState == 0) {
      throw new Exceptions\DatabaseMigrationException('Inconsistent database state');
    }

    if ($migrationState == -1) {
      $connection = $this->entityManager->getConnection();
      $sql = "SELECT f.id AS file_id, d.id AS dir_entry_id FROM Files f INNER JOIN DatabaseStorageDirEntries d ON f.id = d.id AND f.type = 'encrypted'";
      $stmt = $connection->prepare($sql);
      $conflictIds = $stmt->executeQuery()->fetchFirstColumn();
      $stmt->closeCursor();
      $this->logInfo('CONFLICTS ' . print_r($conflictIds, true));

      if (!empty($conflictIds)) {
        // as document reference are still EncryptedFile ids it is comparatively
        // safe to resolve the conflicts by simply relocating the directory
        // entries. If the directory entry is a file, we need only to adjust its
        // id. If it is a directory, we need to additionally adjust the parent
        // references of all children.
        //
        // If the conflicting node is a root node, we need also to adjust the
        // DatabaseStorages table.
        $sql = 'SELECT d.id AS dirEntryId, d.type AS dirEntryType, p.id AS parentId, s.id AS storageId
FROM DatabaseStorageDirEntries d
LEFT JOIN DatabaseStorageDirEntries p
ON d.parent_id = p.id
LEFT JOIN DatabaseStorages s
ON d.id = s.root_id
WHERE d.id IN (' . implode(',', $conflictIds) . ')';
        $stmt = $connection->prepare($sql);
        $conflicts = $stmt->executeQuery()->fetchAllAssociative();
        $stmt->closeCursor();
        $this->logInfo('CONFLICT IDS ' . print_r($conflicts, true));

        $sql = 'SELECT MAX(t.id) FROM (SELECT id
FROM DatabaseStorageDirEntries d
UNION
SELECT id FROM Files f) t';
        $stmt = $connection->prepare($sql);
        $maxReferenceId = $stmt->executeQuery()->fetchOne();
        $stmt->closeCursor();
        $this->logInfo('MAX ID ' . $maxReferenceId);
        $nextReferenceId = $maxReferenceId + 1;

        $connection->beginTransaction();
        try {
          foreach ($conflicts as $conflict) {
            $oldReferenceId = $conflict['dirEntryId'];
            $this->relocateDirectoryEntry($connection, $oldReferenceId, $nextReferenceId);
            ++$nextReferenceId;
          }
          $connection->commit();
        } catch (Throwable $t) {
          if ($connection->isTransactionActive()) {
            $connection->rollBack();
          }
          $this->logException($t);
          throw new Exceptions\DatabaseMigrationException($this->l->t('Exception during relocation of directory entries.'), 0, $t);
        }
      }
    }

    $this->entityManager->beginTransaction();
    try {

      $baseStorage = $this->appContainer->get(Storage\Storage::class);
      $baseId = $baseStorage->getId();

      $projectsRepository = $this->getDatabaseRepository(Entities\Project::class);
      $storagesRepository = $this->getDatabaseRepository(Entities\DatabaseStorage::class);
      $dirEntriesRepository = $this->getDatabaseRepository(Entities\DatabaseStorageFolder::class);

      $configService = $this->appContainer->get(Service\ConfigService::class);

      /** @var Entities\Project $project */
      foreach ($projectsRepository->findAll() as $project) {
        /** @var Storage\ProjectBalanceSupportingDocumentsStorage $storage */
        $storage = new Storage\ProjectBalanceSupportingDocumentsStorage([
          'configService' => $configService,
          'project' => $project
        ]);
        $this->logInfo('STORAGE ID ' . $storage->getId());

        // Finding the balance folder can no longer work this way, as the
        // method return the root folder from the storage.
        // $balanceFolder = $project->getFinancialBalanceDocumentsFolder();
        //
        /** @var Entities\DatabaseStorageFolder $balanceFolderChild */
        $balanceFolderChild = $dirEntriesRepository->findOneBy([
          'name' => $project->getName() . '-%',
          'parent.parent' => null,
        ]);

        if (!empty($balanceFolderChild)) {
          $balanceFolder = $balanceFolderChild->getParent();
          $storageId = $storage->getId();
          if (!str_starts_with($storageId, $baseId)) {
            throw new Exceptions\DatabaseMigrationException(
              $this->l->t('Storage id "%1$s" does not start with base storage id "%2$s".', [
                $storageId, $baseId
              ])
            );
          }
          $storageId = substr($storage->getId(), strlen($baseId));

          /** @var Entities\DatabaseStorage $storageEntity */
          $storageEntity = $storagesRepository->findOneBy([ 'storageId' => $storageId ]);
          if (empty($storageEntity)) {
            $storageEntity = (new Entities\DatabaseStorage)
              ->setStorageId($storageId)
              ->setRoot($balanceFolder);
            $this->persist($storageEntity);
          } elseif ($storageEntity->getRoot() != $balanceFolder) {
            $storageEntity->setRoot($balanceFolder);
          }
          $project->setFinancialBalanceDocumentsStorage($storageEntity);
        }

        // Add entries for the participant documents folders as necessary
        /** @var Entities\ProjectParticipant $participant */
        foreach ($project->getParticipants() as $participant) {
          /** @var Storage\ProjectParticipantsStorage $storage */
          $storage = new Storage\ProjectParticipantsStorage([
            'configService' => $configService,
            'participant' => $participant,
          ]);

          $rootListing = $storage->findFilesForMigration('');

          if (count($rootListing) == 1 && isset($rootListing['.'])) {
            // $this->logInfo('FOLDER FOR ' . $project->getName() . ': ' . $participant->getMusician()->getUserIdSlug() . ' IS EMPTY');
            continue; // skip essentially empty folders
          }

          $storageId = $storageId = substr($storage->getId(), strlen($baseId));
          $this->logInfo($storageId . ' ' . print_r(array_keys($rootListing), true));
          $storageEntity = $storagesRepository->findOneBy([ 'storageId' => $storageId ]);
          if (empty($storageEntity)) {
            $rootFolder = (new Entities\DatabaseStorageFolder)
              ->setName('')
              ->setParent(null)
              ->setUpdated('@1')
              ->setCreated('@1');
            $this->persist($rootFolder);

            $storageEntity = (new Entities\DatabaseStorage)
              ->setStorageId($storageId)
              ->setRoot($rootFolder);
            $this->persist($storageEntity);
          } else {
            $rootFolder = $storageEntity->getRoot();
            $rootFolder
              ->setUpdated('@1')
              ->setCreated('@1');
          }
          $participant->setDatabaseDocuments($storageEntity);

          $walkFileSystem = function($path, $folderListing, $folderEntity) use (&$walkFileSystem, $storage, $storageId) {
            foreach ($folderListing as $nodeName => $node) {
              if ($nodeName == '.') {
                $folderEntity->setUpdated(max($folderEntity->getUpdated(), $node->minimalModificationTime));
                continue;
              }
              $nodePath = rtrim($path, Constants::PATH_SEP) . Constants::PATH_SEP . $nodeName;
              $this->logInfo('PATH ' . $nodePath);
              $nodeName = trim($nodeName . Constants::PATH_SEP);

              if ($node instanceof Entities\EncryptedFile) {
                /** @var Entities\EncryptedFile $node */
                $dirEntry = $folderEntity->getFileByName($nodeName);
                if (empty($dirEntry)) {
                  $dirEntry = $folderEntity->addDocument($node, $nodeName, replace: true);
                  $this->persist($dirEntry);
                  $dirEntry
                    ->setUpdated($node->getUpdated())
                    ->setCreated($node->getCreated());
                  $folderEntity
                    ->setCreated(min($folderEntity->getCreated(), $node->getCreated()));
                }
              } elseif ($node instanceof MigrationDirectoryNode) {
                $dirEntry = $folderEntity->getFolderByName($nodeName);
                if (empty($dirEntry)) {
                  $dirEntry = $folderEntity->addSubFolder($nodeName);
                  $dirEntry->setCreated($dirEntry->getUpdated());
                }

                $nodeListing = $storage->findFilesForMigration($nodePath);

                $walkFileSystem($nodePath, $nodeListing, $dirEntry);

                $dirEntry->setUpdated($storage->getDirectoryModificationTimeForMigration($nodePath));
              }
            }
          };

          $walkFileSystem('', $rootListing, $rootFolder);

          $rootFolder->setUpdated($storage->getDirectoryModificationTimeForMigration(''));
        }

      }

      // throw new \Exception('STOP');

      $storage = $this->appContainer->get(Storage\BankTransactionsStorage::class);
      $storageId = $storage->getId();
      if (!str_starts_with($storageId, $baseId)) {
        throw new Exceptions\DatabaseMigrationException(
          $this->l->t('Storage id "%1$s" does not start with base storage id "%2$s".', [
            $storageId, $baseId
          ])
        );
      }
      $storageId = substr($storage->getId(), strlen($baseId));
      $storageEntity = $storagesRepository->findOneBy([ 'storageId' => $storageId ]);
      if (empty($storageEntity)) {
        $rootFolder = (new Entities\DatabaseStorageFolder)
          ->setName('')
          ->setParent(null);
        $this->persist($rootFolder);

        $storageEntity = (new Entities\DatabaseStorage)
          ->setStorageId($storageId)
          ->setRoot($rootFolder);
        $this->persist($storageEntity);
      }

      // also generate all directory entries for the transactions
      $transactionsRepository = $this->getDatabaseRepository(Entities\SepaBulkTransaction::class);
      /** @var Entities\SepaBulkTransaction $transaction */
      foreach ($transactionsRepository->findAll() as $transaction) {

        $modificationTime = $this->getSepaTransactionDataChanged($transaction->getId());

        $transactionData = [];
        try {
          // As in a later migration the file references are converted to
          // directory entry references we have to fetch the file entities with
          // a plain SQL query as the ORM mapping already refer to the new scheme.
          $transactionId = $transaction->getId();
          $column = 'encrypted_file_id';
          $sql = 'SELECT jt.' . $column . '
FROM SepaBulkTransactionData jt
WHERE jt.sepa_bulk_transaction_id = ' . $transactionId;
          $connection = $this->entityManager->getConnection();
          $stmt = $connection->prepare($sql);
          $fileIds = $stmt->executeQuery()->fetchFirstColumn();
          $stmt->closeCursor();
          foreach ($fileIds as $fileId) {
            $transactionData[] = $this->entityManager->find(Entities\EncryptedFile::class, $fileId);
          }
        } catch (InvalidFieldNameException $e) {
          $this->logException($e, sprintf('Column "%s" does not exist, migration probably has already been applied.', $column));
          continue;
        }

        /** @var Entities\EncryptedFile $file */
        foreach ($transactionData as $file) {
          $root = null;
          /** @var Entities\DatabaseStorageFile $dirEntry */
          foreach ($file->getDatabaseStorageDirEntries() as $dirEntry) {
            $root = $dirEntry->getParent();
            while (!empty($root->getParent())) {
              $root = $root->getParent();
            }
            if ($root === $storageEntity->getRoot()) {
              break;
            }
            $root = null;
          }
          if (empty($root)) {
            $year = $transaction->getCreated()->format('Y');
            $root = $storageEntity->getRoot();
            $yearFolder = $root->getFolderByName($year);
            if (empty($yearFolder)) {
              $yearFolder = $root->addSubFolder($year);
              $yearFolder
                ->setUpdated($file->getUpdated())
                ->setCreated($file->getCreated());
              $this->persist($yearFolder);
            }
            $document = $yearFolder->addDocument($file);
            $document
              ->setCreated($file->getCreated())
              ->setUpdated($file->getUpdated());
            $this->persist($document);

            $yearFolder
              ->setCreated(min($file->getCreated(), $yearFolder->getCreated()))
              ->setUpdated(max($file->getUpdated(), $yearFolder->getUpdated(), $modificationTime));
          }

          $root->setUpdated(max($root->getUpdated(), $modificationTime));
        }
      }

      // now migrate the projects in order to link to the storages, not the root-folder
      //
      // This needs SQL as the class member is gone.

      $storagesRepository = $this->getDatabaseRepository(Entities\DatabaseStorage::class);

      $connection = $this->entityManager->getConnection();
      $column = 'financial_balance_documents_folder_id';
      $sql = 'SELECT dbs.id FROM Projects p
LEFT JOIN DatabaseStorages dbs
ON p.' . $column . ' = dbs.root_id
WHERE p.id = ?';
      $stmt = $connection->prepare($sql);

      /** @var Entities\Project $project */
      foreach ($projectsRepository->findAll() as $project) {
        // We need to use plain DQL/SQL here as the old column should vanish
        $stmt->bindValue(1, $project->getId());
        try {
          $storageId = $stmt->executeQuery()->fetchOne();
          $stmt->closeCursor();
        } catch (InvalidFieldNameException $t) {
          $this->logException($t, sprintf('Column "%s" does not exist, migration probably has already been applied.', $column));
          continue;
        }
        if (empty($storageId)) {
          $this->logInfo('NO STORAGE FOR PROJECT ' . $project->getName());
          continue;
        }
        $this->logInfo('STORAGE ID: ' . print_r($storageId, true));
        $storage = $storagesRepository->find($storageId);
        $project->setFinancialBalanceDocumentsStorage($storage);
      }

      $this->flush();

      $this->entityManager->commit();

    } catch (Throwable $t) {
      if ($this->entityManager->isTransactionActive()) {
        $this->entityManager->rollback();
      }
      $this->logException($t);
      throw new Exceptions\DatabaseMigrationException($this->l->t('Exception during transactional part of the migration.'), 0, $t);
    }

    self::$sql = [
      self::STRUCTURAL => [
        'ALTER TABLE Projects DROP FOREIGN KEY IF EXISTS FK_A5E5D1F2ABE5D3E5',
        'DROP INDEX IF EXISTS UNIQ_A5E5D1F2ABE5D3E5 ON Projects',
        'ALTER TABLE Projects DROP COLUMN IF EXISTS financial_balance_documents_folder_id',
        'ALTER TABLE Projects DROP COLUMN IF EXISTS financial_balance_supporting_documents_changed',
        //
        'ALTER TABLE SepaBulkTransactions DROP COLUMN IF EXISTS sepa_transaction_data_changed',
        //
        'ALTER TABLE Musicians DROP COLUMN IF EXISTS sepa_debit_mandates_changed',
        'ALTER TABLE Musicians DROP COLUMN IF EXISTS  payments_changed',
        //
        'ALTER TABLE ProjectParticipants DROP COLUMN IF EXISTS participant_fields_data_changed',
        //
        'ALTER TABLE Files DROP COLUMN IF EXISTS number_of_links',
        'ALTER TABLE Files DROP COLUMN IF EXISTS original_file_name',
        //
        'ALTER TABLE CompositePayments DROP FOREIGN KEY IF EXISTS FK_65D9920C166D1F9C6A022FD1',
        'ALTER TABLE CompositePayments DROP COLUMN IF EXISTS balance_document_sequence',
        'DROP INDEX IF EXISTS IDX_65D9920C166D1F9C6A022FD1 ON CompositePayments',
        //
        'ALTER TABLE ProjectPayments DROP FOREIGN KEY IF EXISTS FK_F6372AE2166D1F9C6A022FD1',
        'ALTER TABLE ProjectPayments DROP COLUMN IF EXISTS balance_document_sequence',
        'DROP INDEX IF EXISTS IDX_F6372AE2166D1F9C6A022FD1 ON ProjectPayments',
        //
        'DROP TABLE IF EXISTS project_balance_supporting_document_encrypted_file',
        'DROP TABLE IF EXISTS ProjectBalanceSupportingDocuments',
      ],
      self::TRANSACTIONAL => [
      ],
    ];

    // pray that it worked out.
    return parent::execute();
  }

  /**
   * @return int 1 if file references are already dir entries, -1 if
   * references are still files, 0 if this cannot be determined.
   */
  private function determineDirEntryMigrationState():int
  {
    $state = $this->fieldDataSupportingDocumentsDirEntryState()
      + $this->debitMandatesDirEntryState()
      + $this->fieldDataOptionValuesDirEntryState()
      + $this->compositePaymentsDirEntryState()
      + $this->transactionDataDirEntryState();

    return $state == 5 ? 1 : ($state == -5 ? -1 : 0);
  }

  /**
   * @return int 1 if supporting documents references are already dir-entries,
   * -1 if references are still files, 0 if this cannot be determined.
   */
  private function fieldDataSupportingDocumentsDirEntryState():int
  {
    $connection = $this->entityManager->getConnection();
    // check on which side of the migrations we are
    $sql = 'SELECT COUNT(*)
FROM ProjectParticipantFieldsData fd
INNER JOIN DatabaseStorageDirEntries d
ON fd.supporting_document_id = d.id AND d.type = "file"';
    $stmt = $connection->prepare($sql);
    $dirEntriesCount = $stmt->executeQuery()->fetchOne();
    $stmt->closeCursor();

    $this->logInfo('#DIRENTRIES ' . $dirEntriesCount);

    $sql = 'SELECT COUNT(*)
FROM ProjectParticipantFieldsData fd
INNER JOIN Files f
ON fd.supporting_document_id = f.id AND f.type = "encrypted"';
    $stmt = $connection->prepare($sql);
    $filesCount = $stmt->executeQuery()->fetchOne();
    $stmt->closeCursor();

    $this->logInfo('#FILES ' . $filesCount);

    $sql = 'SELECT COUNT(*) FROM ProjectParticipantFieldsData fd WHERE fd.supporting_document_id > 0';
    $stmt = $connection->prepare($sql);
    $referencesCount = $stmt->executeQuery()->fetchOne();
    $stmt->closeCursor();

    if ($filesCount == $referencesCount && $dirEntriesCount < $referencesCount) {
      return -1; // pre dir-entry migration
    } elseif ($dirEntriesCount == $referencesCount && $filesCount < $referencesCount) {
      return 1; // post dir-entry migration
    }
    return 0;
  }

  /**
   * @return int 1 if option-value file references are already dir-entries, -1
   * if references are still files, 0 if this cannot be determined.
   */
  private function fieldDataOptionValuesDirEntryState():int
  {
    $connection = $this->entityManager->getConnection();
    // check on which side of the migrations we are
    $sql = 'SELECT COUNT(*)
FROM ProjectParticipantFieldsData ppfd
LEFT JOIN ProjectParticipantFields ppf
ON ppfd.field_id = ppf.id
INNER JOIN DatabaseStorageDirEntries d
ON ppfd.option_value = d.id AND d.type = "file"
WHERE ppf.data_type = "db-file"';
    $stmt = $connection->prepare($sql);
    $dirEntriesCount = $stmt->executeQuery()->fetchOne();
    $stmt->closeCursor();

    $this->logInfo('#DIRENTRIES ' . $dirEntriesCount);

    $sql = 'SELECT COUNT(*)
FROM ProjectParticipantFieldsData ppfd
LEFT JOIN ProjectParticipantFields ppf
ON ppfd.field_id = ppf.id
INNER JOIN Files f
ON ppfd.option_value = f.id AND f.type = "encrypted"
WHERE ppf.data_type = "db-file"';
    $stmt = $connection->prepare($sql);
    $filesCount = $stmt->executeQuery()->fetchOne();
    $stmt->closeCursor();

    $this->logInfo('#FILES ' . $filesCount);

    $sql = 'SELECT COUNT(*) FROM ProjectParticipantFieldsData ppfd
LEFT JOIN ProjectParticipantFields ppf
ON ppfd.field_id = ppf.id
WHERE ppf.data_type = "db-file"
AND ppfd.option_value > 0';
    $stmt = $connection->prepare($sql);
    $referencesCount = $stmt->executeQuery()->fetchOne();
    $stmt->closeCursor();

    if ($filesCount == $referencesCount && $dirEntriesCount < $referencesCount) {
      return -1; // pre dir-entry migration
    } elseif ($dirEntriesCount == $referencesCount && $filesCount < $referencesCount) {
      return 1; // post dir-entry migration
    }
    return 0;
  }

  /**
   * @return int 1 if written mandate references are already dir-entries, -1
   * if references are still files, 0 if this cannot be determined.
   */
  private function debitMandatesDirEntryState():int
  {
    $connection = $this->entityManager->getConnection();
    // check on which side of the migrations we are
    $sql = 'SELECT COUNT(*)
FROM SepaDebitMandates m
INNER JOIN DatabaseStorageDirEntries d
ON m.written_mandate_id = d.id AND d.type = "file"';
    $stmt = $connection->prepare($sql);
    $dirEntriesCount = $stmt->executeQuery()->fetchOne();
    $stmt->closeCursor();

    $this->logInfo('#DIRENTRIES ' . $dirEntriesCount);

    $sql = 'SELECT COUNT(*)
FROM SepaDebitMandates m
INNER JOIN Files f
ON m.written_mandate_id = f.id AND f.type = "encrypted"';
    $stmt = $connection->prepare($sql);
    $filesCount = $stmt->executeQuery()->fetchOne();
    $stmt->closeCursor();

    $this->logInfo('#FILES ' . $filesCount);

    $sql = 'SELECT COUNT(*)
FROM SepaDebitMandates m
WHERE m.written_mandate_id > 0';
    $stmt = $connection->prepare($sql);
    $referencesCount = $stmt->executeQuery()->fetchOne();
    $stmt->closeCursor();

    if ($filesCount == $referencesCount && $dirEntriesCount < $referencesCount) {
      return -1; // pre dir-entry migration
    } elseif ($dirEntriesCount == $referencesCount && $filesCount < $referencesCount) {
      return 1; // post dir-entry migration
    }
    return 0;
  }

  /**
   * @return int 1 if written mandate references are already dir-entries, -1
   * if references are still files, 0 if this cannot be determined.
   */
  private function compositePaymentsDirEntryState():int
  {
    $connection = $this->entityManager->getConnection();
    // check on which side of the migrations we are
    $sql = 'SELECT COUNT(*)
FROM CompositePayments p
INNER JOIN DatabaseStorageDirEntries d
ON p.supporting_document_id = d.id AND d.type = "file"';
    $stmt = $connection->prepare($sql);
    $dirEntriesCount = $stmt->executeQuery()->fetchOne();
    $stmt->closeCursor();

    $this->logInfo('#DIRENTRIES ' . $dirEntriesCount);

    $sql = 'SELECT COUNT(*)
FROM CompositePayments p
INNER JOIN Files f
ON p.supporting_document_id = f.id AND f.type = "encrypted"';
    $stmt = $connection->prepare($sql);
    $filesCount = $stmt->executeQuery()->fetchOne();
    $stmt->closeCursor();

    $this->logInfo('#FILES ' . $filesCount);

    $sql = 'SELECT COUNT(*)
FROM CompositePayments p
WHERE p.supporting_document_id > 0';
    $stmt = $connection->prepare($sql);
    $referencesCount = $stmt->executeQuery()->fetchOne();
    $stmt->closeCursor();

    if ($filesCount == $referencesCount && $dirEntriesCount < $referencesCount) {
      return -1; // pre dir-entry migration
    } elseif ($dirEntriesCount == $referencesCount && $filesCount < $referencesCount) {
      return 1; // post dir-entry migration
    }
    return 0;
  }

  /**
   * @return int 1 if written mandate references are already dir-entries, -1
   * if references are still files, 0 if this cannot be determined.
   */
  private function transactionDataDirEntryState():int
  {
    $connection = $this->entityManager->getConnection();
    $columns = [ 'encrypted_file_id', 'database_storage_file_id', ];
    $fileReferenceColumn = null;
    foreach ($columns as $column) {
      // determine the column name
      try {
        $sql = 'SELECT t.' . $column . ' FROM SepaBulkTransactionData t';
        $stmt = $connection->prepare($sql);
        $stmt->executeQuery()->fetchOne();
        $fileReferenceColumn = $column;
      } catch (InvalidFieldNameException $e) {
        // ignore
      }
    }
    if ($fileReferenceColumn === null) {
      return 0; // broken
    }

    // check on which side of the migrations we are
    $sql = 'SELECT COUNT(*)
FROM SepaBulkTransactionData btd
INNER JOIN DatabaseStorageDirEntries d
ON btd.' . $fileReferenceColumn . ' = d.id AND d.type = "file"';
    $stmt = $connection->prepare($sql);
    $dirEntriesCount = $stmt->executeQuery()->fetchOne();
    $stmt->closeCursor();

    $this->logInfo('#DIRENTRIES ' . $dirEntriesCount);

    $sql = 'SELECT COUNT(*)
FROM SepaBulkTransactionData btd
INNER JOIN Files f
ON btd.' . $fileReferenceColumn . ' = f.id AND f.type = "encrypted"';
    $stmt = $connection->prepare($sql);
    $filesCount = $stmt->executeQuery()->fetchOne();
    $stmt->closeCursor();

    $this->logInfo('#FILES ' . $filesCount);

    $sql = 'SELECT COUNT(*)
FROM SepaBulkTransactionData btd
WHERE btd.' . $fileReferenceColumn . ' > 0';
    $stmt = $connection->prepare($sql);
    $referencesCount = $stmt->executeQuery()->fetchOne();
    $stmt->closeCursor();

    if ($filesCount == $referencesCount && $dirEntriesCount < $referencesCount) {
      return -1; // pre dir-entry migration
    } elseif ($dirEntriesCount == $referencesCount && $filesCount < $referencesCount) {
      return 1; // post dir-entry migration
    }
    return 0;
  }

  /**
   * Relocate the given directory entry to a new id in order to resolve conflicts.
   *
   * @param DatabaseConnection $connection
   *
   * @param int $oldReferenceId
   *
   * @param int $newReferenceId
   *
   * @return void
   */
  private function relocateDirectoryEntry(DatabaseConnection $connection, int $oldReferenceId, int $newReferenceId):void
  {
    $sql = 'SET FOREIGN_KEY_CHECKS = 0;
UPDATE DatabaseStorageDirEntries d
  SET d.id = ' . $newReferenceId . '
  WHERE d.id = ' . $oldReferenceId . ';
UPDATE DatabaseStorages s
  SET s.root_id = ' . $newReferenceId . '
  WHERE s.root_id = ' . $oldReferenceId . ';
UPDATE DatabaseStorageDirEntries d
  SET d.parent_id = ' . $newReferenceId . '
  WHERE d.parent_id = ' . $oldReferenceId;
    $this->logInfo('SQL ' . $sql);
    $stmt = $connection->prepare($sql);
    $stmt->executeQuery();
    $stmt->closeCursor();
  }

  /**
   * @param int $id
   *
   * @return null|DateTimeInterface
   */
  private function getSepaTransactionDataChanged(int $id):?DateTimeInterface
  {
    $connection = $this->entityManager->getConnection();
    $sql = 'SELECT t.sepa_transaction_data_changed
FROM SepaBulkTransactions t
WHERE t.id = ?';
    $stmt = $connection->prepare($sql);
    $stmt->bindValue(1, $id);
    try {
      $value = $stmt->executeQuery()->fetchOne();
    } catch (InvalidFieldNameException $t) {
      $this->logException($t, 'Column does not exist, migration probably has already been applied.');
      return self::ensureDate(null);
    }
    return self::convertToDateTime($value);
  }
}
