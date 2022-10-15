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

use OCP\ILogger;
use OCP\IL10N;
use OCP\AppFramework\IAppContainer;

use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Storage\Database as Storage;
use OCA\CAFEVDB\Service;
use OCA\CAFEVDB\Exceptions;

/**
 * Remember the id of a mailing list.
 */
class RootDirectoryEntries extends AbstractMigration
{
  /** @var IAppContainer */
  private $appContainer;

  protected static $sql = [
    self::STRUCTURAL => [
      'CREATE TABLE IF NOT EXISTS DatabaseStorages (storage_id VARCHAR(512) NOT NULL, root_id INT DEFAULT NULL, INDEX IDX_3594ED2379066886 (root_id), PRIMARY KEY(storage_id))',
      'ALTER TABLE DatabaseStorages
  ADD CONSTRAINT FK_3594ED2379066886
  FOREIGN KEY IF NOT EXISTS (root_id)
 REFERENCES DatabaseStorageDirEntries (id)',
    ],
    self::TRANSACTIONAL => [
      'UPDATE SepaBulkTransactions SET updated = GREATEST(updated, sepa_transaction_data_changed)
WHERE sepa_transaction_data_changed IS NOT NULL',
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

      /** @var Entities\Project $project */
      foreach ($projectsRepository->findAll() as $project) {
        $balanceFolder = $project->getFinancialBalanceDocumentsFolder();
        if (!empty($balanceFolder)) {
          /** @var Storage\ProjectBalanceSupportingDocumentsStorage $storage */
          $storage = new Storage\ProjectBalanceSupportingDocumentsStorage([
            'configService' => $this->appContainer->get(Service\ConfigService::class),
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
          $storageEntity = $storagesRepository->find($storageId);
          if (empty($storageEntity)) {
            $storageEntity = (new Entities\DatabaseStorage)
              ->setStorageId($storageId)
              ->setRoot($balanceFolder);
            $this->persist($storageEntity);
          } elseif ($storageEntity->getRoot() != $balanceFolder) {
            $storageEntity->setRoot($balanceFolder);
          }
        }
      }

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
      $storageEntity = $storagesRepository->find($storageId);
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
              ->setUpdated(max($file->getUpdated(), $yearFolder->getUpdated()));
          }
        }
      }

      $this->flush();

      $this->entityManager->commit();

    } catch (Throwable $t) {
      if ($this->entityManager->isTransactionActive()) {
        $this->entityManager->rollback();
      }
      throw new Exceptions\DatabaseMigrationException($this->l->t('Exception during transactional part of the migration.'), 0, $t);
    }

    return true;
  }
}
