<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2022 Claus-Justus Heine
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

namespace OCA\CAFEVDB\Listener;

use OCA\CAFEVDB\Wrapped\Doctrine\ORM\Event as ORMEvent;

use OCP\IL10N;
use OCP\ILogger;
use OCP\AppFramework\IAppContainer;

use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Storage\Database\BankTransactionsStorage;
use OCA\CAFEVDB\Service\Finance\SepaBulkTransactionService;

/**
 * An entity listener. The task is to update the export data of the
 * bulk-transaction.
 */
class SepaBulkTransactionEntityListener
{
  use \OCA\CAFEVDB\Traits\LoggerTrait;
  use \OCA\CAFEVDB\Traits\EntityManagerTrait;

  /** @var IL10N */
  protected $l;

  /** @var SepaBulkTransactionService */
  protected $service;

  /** @var BankTransactionsStorage */
  protected $storage;

  /** @var array */
  protected $lock = [];

  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct(
    ILogger $logger,
    IL10N $l10n,
    EntityManager $entityManager,
    BankTransactionsStorage $storage,
    SepaBulkTransactionService $service,
    IAppContainer $appContainer,
  ) {
    $this->l = $l10n;
    $this->logger = $logger;
    $this->entityManager = $entityManager;
    $this->service = $service;
    $this->storage = $storage;
  }
  // phpcs:enable

  /**
   * Protect against re-entrance when the preRemove() handler causes changes to the entity.
   *
   * @param Entities\SepaBulkTransaction $transaction
   *
   * @return bool
   */
  private function lockEntity(Entities\SepaBulkTransaction $transaction):bool
  {
    $transactionId = $transaction->getId();
    if (!empty($this->lock[$transactionId])) {
      return false;
    }
    $this->lock[$transactionId] = true;
    return true;
  }

  /**
   * Protect against re-entrance when the preRemove() handler causes changes to the entity.
   *
   * @param Entities\SepaBulkTransaction $transaction
   *
   * @return void
   */
  private function unlockEntity(Entities\SepaBulkTransaction $transaction):void
  {
    unset($this->lock[$transaction->getId()]);
  }

  /**
   * {@inheritdoc}
   */
  public function preUpdate(Entities\SepaBulkTransaction $transaction, ORMEvent\PreUpdateEventArgs $eventArgs)
  {
    if (!$this->lockEntity($transaction)) {
      return;
    }
    $this->prePersist($transaction, $eventArgs);
    $this->unlockEntity($transaction);
  }

  /**
   * {@inheritdoc}
   */
  public function prePersist(Entities\SepaBulkTransaction $transaction, ORMEvent\LifecycleEventArgs $eventArgs)
  {
    $this->entityManager->registerPreCommitAction(function() use ($transaction) {
      foreach (SepaBulkTransactionService::EXPORTERS as $format) {
        $this->service->generateTransactionData($transaction, format: $format);
      }
      foreach ($transaction->getSepaTransactionData() as $file) {
        $this->storage->addDocument($transaction, $file);
      }
    });
  }

  /**
   * {@inheritdoc}
   */
  public function preRemove(Entities\SepaBulkTransaction $transaction, ORMEvent\LifecycleEventArgs $eventArgs)
  {
    if (!$this->lockEntity($transaction)) {
      return;
    }
    /** @var Entities\EncryptedFile $file */
    foreach ($transaction->getSepaTransactionData() as $file) {
      $transaction->removeTransactionData($file);
      $this->storage->removeDocument($transaction, $file, flush: false);
    }
    $this->flush();
    $this->unlockEntity($transaction);
  }
}
