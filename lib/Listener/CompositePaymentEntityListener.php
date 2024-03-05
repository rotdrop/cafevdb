<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2024 Claus-Justus Heine
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

use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities\CompositePayment as Entity;

/**
 * An entity listener for CompositePayment entities.
 */
class CompositePaymentEntityListener
{
  use \OCA\CAFEVDB\Toolkit\Traits\LoggerTrait;
  use \OCA\CAFEVDB\Traits\EntityManagerTrait;

  /**
   * @var array
   * Array of the pre-update values, indexed by musician id. Currently only
   * needed for the principal email address.
   */
  private array $preUpdateValues = [];

  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct(
    protected ILogger $logger,
    protected EntityManager $entityManager,
  ) {
  }
  // phpcs:enable

  /**
   * {@inheritdoc}
   */
  public function preUpdate(Entity $entity, ORMEvent\PreUpdateEventArgs $eventArgs)
  {
    $field = 'notificationMessageId';
    if ($eventArgs->hasChangedField($field)) {
      $oldValue = $eventArgs->getOldValue($field);
      // $entityManager->dispatchEvent(new Events\PreChangeUserIdSlug($entityManager, $this, $oldValue, $eventArgs->getNewValue($field)));
      $entityId = $entity->getId();
      $this->preUpdateValues[$entityId] = array_merge(
        $this->preUpdateValues[$entityId] ?? [],
        [ $field => $oldValue, ],
      );
    }
  }

  /**
   * {@inheritdoc}
   */
  public function postUpdate(Entity $entity, ORMEvent\PostUpdateEventArgs $eventArgs)
  {
    $entityId = $entity->getId();
    $field = 'notificationMessageId';
    if (array_key_exists($field, $this->preUpdateValues[$entityId] ?? [])) {
      $this->entityManager->dispatchEvent(
        new Events\PostChangeCompositePaymentNotificationMessageId(
          $this->entityManager,
          $entity,
          $this->preUpdateValues[$entityId][$field],
        )
      );
      unset($this->preUpdateValues[$entityId][$field]);
    }
  }
}
