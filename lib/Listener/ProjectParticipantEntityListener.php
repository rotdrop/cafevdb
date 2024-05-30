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
use OCA\CAFEVDB\Events;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities\ProjectParticipant as Entity;

/**
 * An entity listener for ProjectParticipant entities.
 */
class ProjectParticipantEntityListener
{
  use \OCA\CAFEVDB\Toolkit\Traits\LoggerTrait;
  use \OCA\CAFEVDB\Traits\EntityManagerTrait;

  private const ID_SEP = ':';

  /** @var EntityManager */
  protected $entityManager;

  /**
   * @var array
   * Array of the pre-update values, indexed by musician id. Currently only
   * needed for the principal email address.
   */
  private array $preUpdateValues = [];

  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct(
    ILogger $logger,
    EntityManager $entityManager,
  ) {
    $this->logger = $logger;
    $this->entityManager = $entityManager;
  }
  // phpcs:enable

  /**
   * {@inheritdoc}
   */
  public function preUpdate(Entity $entity, ORMEvent\PreUpdateEventArgs $eventArgs)
  {
    $field = 'registration';
    if ($eventArgs->hasChangedField($field)) {
      $oldValue = $eventArgs->getOldValue($field);

      $this->entityManager->dispatchEvent(
        new Events\PreChangeRegistrationConfirmation(
          $entity,
          !empty($oldValue),
          !empty($eventArgs->getNewValue($field)),
        )
      );

      $key = self::entityId($entity, $field);
      $this->preUpdateValues[$key] = $oldValue;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function postUpdate(Entity $entity, ORMEvent\PostUpdateEventArgs $eventArgs)
  {
    $field = 'registration';
    $key = self::entityId($entity, $field);
    if (array_key_exists($key, $this->preUpdateValues)) {
      $this->entityManager->dispatchEvent(
        new Events\PostChangeRegistrationConfirmation(
          $entity,
          !empty($this->preUpdateValues[$key]),
        )
      );
      unset($this->preUpdateValues[$key]);
    }
  }

  /**
   * Generate a flattened id for the purpose of indexing PHP arrays.
   *
   * @param string|Entity $entity
   *
   * @param null|string $suffix Additional string to append.
   *
   * @return string
   */
  private static function entityId(string|Entity $entity, ?string $suffix):string
  {
    return (is_string($entity)
            ? $entity
            : ($entity->getProject()->getId() . self::ID_SEP . $entity->getMusician()->getId()))
            . ($suffix ? self::ID_SEP . $suffix : '');
  }
}
