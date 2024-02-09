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

use Throwable;

use OCA\CAFEVDB\Wrapped\Doctrine\ORM\Event as ORMEvent;

use OCP\IL10N;
use Psr\Log\LoggerInterface as ILogger;
use OCP\AppFramework\IAppContainer;
use OCP\IConfig;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\IUserManager;

use OCA\CAFEVDB\Database\Doctrine\ORM\Entities\ProjectParticipant as Entity;
use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Common\GenericUndoable;
use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Service\EncryptionService;
use OCA\CAFEVDB\Service\AuthorizationService;

/**
 * Entity listener for project participation.
 *
 * - fire a cloud event if the registration value changes
 *
 * - fire a cloud event if an entity is created, deleted, disabled
 */
class ProjectParticipantEntityListener
{
  use \OCA\CAFEVDB\Toolkit\Traits\LoggerTrait;

  private const ID_SEP = ':';

  /**
   * @var array
   * Array of the pre-update values, indexed by project- and
   * musician-id. Currently we only need the pre-update value of the
   * registration member.
   */
  private array $preUpdateValues = [];

  /** @var IUndoable[] */
  protected $preCommitActions;

  /** @var IGroupManager */
  protected IGroupManager $groupManager;

  /** @var IUserManager */
  protected IUserManager $userManager;

  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct(
    protected ILogger $logger,
    protected IAppContainer $appContainer,
    protected EntityManager $entityManager,
  ) {
  }
  // phpcs:enable

  /**
   * {@inheritdoc}
   */
  public function preUpdate(Entity $entity, ORMEvent\PreUpdateEventArgs $event)
  {
    $field = 'registration';
    if ($event->hasChangedField($field)) {
      $oldValue = $event->getOldValue($field);
      $this->entityManager->dispatchEvent(new Events\PreChangeRegistrationConfirmation($this, !empty($oldValue), !empty($event->getNewValue($field))));
      $key = self::entityId($entity, $field);
      $this->preUpdateValues[$key] = $oldValue;
    }
    $field = 'deleted';
    if ($event->hasChangedField($field)) {
      $oldValue = $event->getOldValue($field);
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
    $entityId = self::entityId($entity);
    $key = self::entityId($entityId, $field);
    if (array_key_exists($key, $this->preUpdateValues)) {
      $this->entityManager->dispatchEvent(new Events\PostChangeRegistrationConfirmation($this, !empty($this->preUpdateValue[$field])));
      unset($this->preUpdateValues[$key]);
    }
    $field = 'deleted';
    $key = self::entityId($entityId, $field);
    if (array_key_exists($key, $this->preUpdateValues)) {
      // This is just like persist and remove with the respective actions to perform
      if (empty($this->preUpdateValues[$key]) && !$entity->isDeleted()) {
        $this->registerPreCommitAction($entity, remove: false);
      } elseif ($entity->isDeleted() && empty($this->preUpdateValues[$key])) {
        $this->registerPreCommitAction($entity, remove: true);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function postPersist(Entity $entity, ORMEvent\PostPersistEventArgs $eventArgs)
  {
    // Maybe register a pre-commit hook with undo ...
    // Move person to ldap user backend
    // Add person to orchestra user group
    // Add person to management user group
    $this->registerPreCommitAction($entity, remove: false);
  }

  /**
   * {@inheritdoc}
   */
  public function postRemove(Entity $entity, ORMEvent\PostRemoveEventArgs $eventArgs)
  {
    // Maybe register a pre-commit hook with undo ...
    // Remove person from ldap user backend? Or just keep it there ...
    // Remove person from orchestra user group
    // Remove person from management user group
    $this->registerPreCommitAction($entity, remove: true);
  }

  /**
   * Register a pre-commit action with undo in order to add / remove the
   * cloud-account of a newly registered executive board member to the
   * respective cloud user groups.
   *
   * @param Entity $entity
   *
   * @param bool $remove IF true the this originated from a postRemove or
   * soft-deletion event. Otherwise from postPersist or (soft-)undeletion.
   *
   * @return void
   */
  private function registerPreCommitAction(Entity $entity, bool $remove):void
  {
    $entityId = self::entityId($entity);
    if (!empty($this->preCommitActions[$entityId])) {
      return;
    }
    /** @var IConfig $cloudConfig */
    $cloudConfig = $this->appContainer->get(IConfig::class);

    $appName = $this->appContainer->get('appName');
    $orchestraGroupId = $cloudConfig->getAppValue($appName, ConfigService::USER_GROUP_KEY);

    if (empty($orchestraGroupId)) {
      return;
    }

    /** @var EncryptionService $encryptionService */
    $encryptionService = $this->appContainer->get(EncryptionService::class);
    $executiveBoardProjectId = $encryptionService->getConfigValue(ConfigService::EXECUTIVE_BOARD_PROJECT_ID_KEY, -1);
    if ($entity->getProject->getId() != $executiveBoardProjectId) {
      // nothing to do
      return;
    }

    /** @var IGroupManager $groupManager */
    $groupManager = $this->appContainer->get(IGroupManager::class);

    $managementGroupId = $orchestraGroupId . AuthorizationService::MANAGEMENT_GROUP_SUFFIX;
    /** @var IGroup $managementGroup */
    $managementGroup = $groupManager->get($managementGroupId);
    /** @var IGroup $orchestraGroup */
    $orchestraGroup = $groupManager->get($orchestraGroupId);

    /** @var IUserManager $userManager */
    $userManager = $this->appContainer->get(IUserManager::class);
    $userId = $entity->getMusician()->getUserIdSlug();
    $user = $userManager->get($userId);

    $closureArguments = compact([
      'userId',
      'user',
      'managementGroup',
      'orchestraGroup',
      'groupManager',
      'userManager',
    ]);

    $this->preCommitActions[$entityId] = new GenericUndoable(
      function() use ($closureArguments) {
        extract($closureArguments);
        $previousStatus = [
          'orchestraGroup' => $orchestraGroup->inGroup($user),
          'managementGroup' => $managementGroup->inGroup($user),
        ];
        if ($remove) {
          $managementGroup->removeUser($user);
        } else {
          $managementGroup->addUser($user);
          $orchestraGroup->addUser($user);
        }
        return $previousStatus;
      },
      function(array $previousStatus) use ($closureArguments) {
        extract($closureArguments);
        foreach ($previousStatus as $groupKey => $status) {
          /** @var IGroup $group */
          $group = $closureArguments[$groupKey];
          if ($status && !$group->inGroup($user)) {
            $group->addUser($user);
          }
          if (!$status && $group->inGroup($user)) {
            $group->removeUser($user);
          }
        }
      },
    );
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
