<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2022, 2023 Claus-Justus Heine
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

use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Service\ProjectService;
use OCA\CAFEVDB\Common\GenericUndoable;

/**
 * An entity listener. The task is to manage changes in user-id and email
 * addresses.
 *
 * When the user-id changes:
 * - rename all file-system entries
 * - update the RowAccessToken entity
 *
 * When the email adresses change
 * - update the mailing-list subscriptions to use the current principal email
 *   address
 * - add new email addresses as passive subscriptions (no message delivery)
 * - remove all obsolete email addresses from the mailing list subscriptions
 * -
 */
class MusicianEntityListener
{
  use \OCA\CAFEVDB\Toolkit\Traits\LoggerTrait;
  use \OCA\CAFEVDB\Traits\EntityManagerTrait;

  /** @var IL10N */
  protected $l;

  /** @var IAppContainer */
  protected $appContainer;

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
    IAppContainer $appContainer,
    EntityManager $entityManager,
  ) {
    $this->logger = $logger;
    $this->appContainer = $appContainer;
    $this->entityManager = $entityManager;
  }
  // phpcs:enable

  /**
   * {@inheritdoc}
   */
  public function preUpdate(Entities\Musician $musician, ORMEvent\PreUpdateEventArgs $event)
  {
    $musicianId = $musician->getId();
    $field = 'userIdSlug';
    if ($event->hasChangedField($field)) {
      $oldSlug = $event->getOldValue($field);
      $newSlug = $event->getNewValue($field);

      /** @var ProjectService $projectService */
      $projectService = $this->appContainer->get(ProjectService::class);
      $projectService->renameParticipantFolders($musician, $oldSlug, $newSlug);

      $this->entityManager->registerPreCommitAction(
        new GenericUndoable(
          function() use ($musician, $newSlug) {
            $rowAccessToken = $musician->getRowAccessToken();
            if (!empty($rowAccessToken)) {
              $rowAccessToken->setUserId($newSlug);
              $this->flush();
            }
          },
        )
      );
    }
    $field = 'email';
    if ($event->hasChangedField($field)) {
      $this->preUpdateValues[$musicianId] = array_merge(
        $this->preUpdateValues[$musicianId] ?? [],
        [ $field => $event->getOldValue($field) ],
      );
    }
  }

  /**
   * {@inheritdoc}
   */
  public function postUpdate(Entities\Musician $musician, ORMEvent\PostUpdateEventArgs $event)
  {
    $musicianId = $musician->getId();
    $field = 'email';
    if (array_key_exists($field, $this->preUpdateValues[$musicianId])) {
      $currentValue= $musician->getPrincipalEmailAddress();
      $this->entityManager->dispatchEvent(new Events\PostChangeMusicianEmail($this->preUpdateValues[$musicianId][$field], $currentValue));
      unset($this->preUpdateValues[$musicianId][$field]);
    }
  }
}
