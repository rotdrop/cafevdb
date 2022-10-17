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

use DateTimeImmutable;
use DateTimeInterface;

use OCP\ILogger;
use OCP\IL10N;
use OCP\AppFramework\IAppContainer;

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
      'CREATE TABLE IF NOT EXISTS DatabaseStorages (storage_id VARCHAR(512) NOT NULL, root_id INT DEFAULT NULL, INDEX IDX_3594ED2379066886 (root_id), PRIMARY KEY(storage_id))',
      'ALTER TABLE DatabaseStorages
  ADD CONSTRAINT FK_3594ED2379066886
  FOREIGN KEY IF NOT EXISTS (root_id)
 REFERENCES DatabaseStorageDirEntries (id)',
      'ALTER TABLE DatabaseStorages DROP INDEX IF EXISTS IDX_3594ED2379066886, ADD UNIQUE INDEX IF NOT EXISTS UNIQ_3594ED2379066886 (root_id)',
      'ALTER TABLE DatabaseStorages ADD COLUMN IF NOT EXISTS id INT NOT NULL AUTO_INCREMENT FIRST',
      'ALTER TABLE `DatabaseStorages` ADD PRIMARY KEY IF NOT EXISTS (id)',
      'CREATE UNIQUE INDEX IF NOT EXISTS UNIQ_3594ED235CC5DB90 ON DatabaseStorages (storage_id)',
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

    $this->entityManager->beginTransaction();
    try {

      $baseStorage = $this->appContainer->get(Storage\Storage::class);
      $baseId = $baseStorage->getId();

      $projectsRepository = $this->getDatabaseRepository(Entities\Project::class);
      $storagesRepository = $this->getDatabaseRepository(Entities\DatabaseStorage::class);

      $configService = $this->appContainer->get(Service\ConfigService::class);

      /** @var Entities\Project $project */
      foreach ($projectsRepository->findAll() as $project) {
        $balanceFolder = $project->getFinancialBalanceDocumentsFolder();
        if (!empty($balanceFolder)) {
          /** @var Storage\ProjectBalanceSupportingDocumentsStorage $storage */
          $storage = new Storage\ProjectBalanceSupportingDocumentsStorage([
            'configService' => $configService,
            'project' => $project
          ]);
          $this->logInfo('STORAGE ID ' . $storage->getId());
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

          $walkFileSystem = function($path, $folderListing, $folderEntity) use (&$walkFileSystem, $storage) {
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
                  $dirEntry = $folderEntity->addDocument($node, $nodeName);
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

        /** @var Entities\EncryptedFile $file */
        foreach ($transaction->getSepaTransactionData() as $file) {
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
      $sql = 'SELECT dbs.id FROM Projects p
LEFT JOIN DatabaseStorages dbs
ON p.financial_balance_documents_folder_id = dbs.root_id
WHERE p.id = ?';
      $stmt = $connection->prepare($sql);

      /** @var Entities\Project $project */
      foreach ($projectsRepository->findAll() as $project) {
        // We need to use plain DQL/SQL here as the old column should vanish
        $stmt->bindValue(1, $project->getId());
        try {
          $storageId = $stmt->executeQuery()->fetchOne();
        } catch (InvalidFieldNameException $t) {
          $this->logException($t, 'Column does not exist, migration probably has already been applied.');
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
