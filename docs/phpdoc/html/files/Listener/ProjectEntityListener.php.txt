<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2022, 2023, 2024 Claus-Justus Heine
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

use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Common\IUndoable;
use OCA\CAFEVDB\Common\GenericUndoable;

use OCA\CAFEVDB\Service\ProjectService;
use OCA\CAFEVDB\Service\EventsService;
use OCA\CAFEVDB\Service\CalDavService;

use OCA\CAFEVDB\Common\Util;

/**
 * An entity listener. The task is to manage events related to registration
 * deadlines:
 * - project soft or hard deleted: remove deadline event
 * - project soft undeleted: restore or add deadline event
 * - deadline date erased: remove deadline event
 * - deadline date set: create deadline event
 * - deadline date modified: modify deadline event
 */
class ProjectEntityListener
{
  use \OCA\CAFEVDB\Toolkit\Traits\LoggerTrait;

  /** @var EventsService */
  protected $eventsService;

  /** @var IUndoable[] */
  protected $preCommitActions;

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
  public function postPersist(Entities\Project $project, ORMEvent\PostPersistEventArgs $eventArgs)
  {
    $this->registerPreCommitAction($project);
  }

  /**
   * {@inheritdoc}
   */
  public function postUpdate(Entities\Project $project, ORMEvent\PostUpdateEventArgs $eventArgs)
  {
    $this->registerPreCommitAction($project);
  }

  /**
   * {@inheritdoc}
   */
  public function postRemove(Entities\Project $project, ORMEvent\PostRemoveEventArgs $eventArgs)
  {
    $this->registerPreCommitAction($project);
 }

  /**
   * @param Entities\Project $project
   *
   * @return void
   */
  private function registerPreCommitAction(Entities\Project $project):void
  {
    if (!empty($this->preCommitActions[$project->getId()])) {
      return;
    }
    if (empty($this->eventsService)) {
      $this->eventsService = $this->appContainer->get(EventsService::class);
    }
    $this->preCommitActions[$project->getId()] = new GenericUndoable(
      function() use ($project) {
        $oldRegistrationEvent = $this->eventsService->findProjectRegistrationEvent($project);
        if (!empty($oldRegistrationEvent)) {
          $oldRegistrationEvent = Util::cloneArray($oldRegistrationEvent);
        }
        /** @var EventsService $eventsService */
        $this->eventsService->ensureProjectRegistrationEvent($project);
        return $oldRegistrationEvent;
      },
      function(?array $oldRegistrationEvent) {
        /** @var CalDavService $calDavService */
        $calDavService = $this->appContainer->get(CalDavService::class);
        if (!empty($oldRegistrationEvent)) {
          try {
            $calDavService->restoreCalendarObject(object: $oldRegistrationEvent);
          } catch (Throwable $t) {
            $this->logException($t, 'Cannot restore ' . $oldRegistrationEvent['uri'] . '.');
            try {
              $calendarId = $oldRegistrationEvent['calendarid'];
              $localUri = $oldRegistrationEvent['uri'];
              $data = $oldRegistrationEvent['calendardata'];
              $calDavService->createCalendarObject($calendarId, $localUri, $data);
            } catch (Throwable $t) {
              $this->logException($t, 'Cannot recreate ' . $oldRegistrationEvent['uri'] . '.');
            }
          }
        } else {
          $registrationEvent = $this->eventsService->findProjectRegistrationEvent($project);
          if (!empty($registrationEvent)) {
            try {
              $calDavService->deleteCalendarObject(
                $registrationEvent['calendarid'],
                $registrationEvent['uri'],
              );
            } catch (Throwable $t) {
              $this->logException($t, 'Cannot delete ' . $registrationEvent['uri'] . '.');
            }
          }
        }
      }
    );
    $this->entityManager->registerPreCommitAction($this->preCommitActions[$project->getId()]);
  }
}
