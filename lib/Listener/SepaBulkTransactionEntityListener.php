<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2022-2024 Claus-Justus Heine
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
use Psr\Log\LoggerInterface as ILogger;
use OCP\AppFramework\IAppContainer;

use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities\SepaBulkTransaction as Entity;
use OCA\CAFEVDB\Storage\Database\BankTransactionsStorage;
use OCA\CAFEVDB\Service\Finance\SepaBulkTransactionService;

/**
 * An entity listener. The task is to update the export data of the
 * bulk-transaction.
 */
class SepaBulkTransactionEntityListener
{
  use \OCA\CAFEVDB\Toolkit\Traits\LoggerTrait;
  use \OCA\CAFEVDB\Traits\EntityManagerTrait;

  /** @var IL10N */
  protected IL10N $l;

  /** @var SepaBulkTransactionService */
  protected $service;

  /** @var BankTransactionsStorage */
  protected $storage;

  /** @var array */
  protected $lock = [];

  /**
   * @var array
   * Array of the pre-update values, indexed by musician id. Currently only
   * needed for the principal email address.
   */
  private array $preUpdateValues = [];

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
   * @param Entity $entity
   *
   * @return bool
   */
  private function lockEntity(Entity $entity):bool
  {
    $entityId = $entity->getId();
    if (!empty($this->lock[$entityId])) {
      return false;
    }
    $this->lock[$entityId] = true;
    return true;
  }

  /**
   * Protect against re-entrance when the preRemove() handler causes changes to the entity.
   *
   * @param Entity $entity
   *
   * @return void
   */
  private function unlockEntity(Entity $entity):void
  {
    unset($this->lock[$entity->getId()]);
  }

  /**
   * {@inheritdoc}
   */
  public function preUpdate(Entity $entity, ORMEvent\PreUpdateEventArgs $eventArgs)
  {
    $field = 'submitDate';
    if ($eventArgs->hasChangedField($field)) {
      $oldValue = $eventArgs->getOldValue($field);
      $entityId = $entity->getId();
      $this->preUpdateValues[$entityId] = array_merge(
        $this->preUpdateValues[$entityId] ?? [],
        [ $field => $oldValue, ],
      );
    }

    if (!$this->lockEntity($entity)) {
      return;
    }
    $this->prePersist($entity, $eventArgs);
    $this->unlockEntity($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function postUpdate(Entity $entity, ORMEvent\PostUpdateEventArgs $eventArgs)
  {
    $entityId = $entity->getId();
    $field = 'submitDate';
    if (array_key_exists($field, $this->preUpdateValues[$entityId] ?? [])) {
      $this->entityManager->dispatchEvent(
        new Events\PostChangeSepaBulkTransactionSubmitDate(
          $this->entityManager,
          $entity,
          $this->preUpdateValues[$entityId][$field],
        )
      );
      unset($this->preUpdateValues[$entityId][$field]);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function prePersist(Entity $entity, ORMEvent\LifecycleEventArgs $eventArgs)
  {
    if ($entity->getSubmitDate() != null) {
      // we should not change transactions which already have been submitted
      // to the bank.
      return;
    }
    $this->entityManager->registerPreCommitAction(function() use ($entity) {
      foreach (SepaBulkTransactionService::EXPORTERS as $format) {
        $this->service->generateTransactionData($entity, format: $format);
      }
    });
  }

  /**
   * {@inheritdoc}
   */
  public function preRemove(Entity $entity, ORMEvent\LifecycleEventArgs $eventArgs)
  {
    if (!$this->lockEntity($entity)) {
      return;
    }
    /** @var Entities\DatabaseStorageFile $dirEntry */
    foreach ($entity->getSepaTransactionData() as $dirEntry) {
      $entity->removeTransactionData($dirEntry);
      $this->remove($dirEntry);
      // $this->storage->removeDocument($entity, $file, flush: false);
    }
    $this->flush();
    $this->unlockEntity($entity);
  }
}
