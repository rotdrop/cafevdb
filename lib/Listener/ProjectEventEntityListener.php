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

use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Common\IUndoable;
use OCA\CAFEVDB\Common\GenericUndoable;

use OCA\CAFEVDB\Service\ProjectService;
use OCA\CAFEVDB\Service\EventsService;
use OCA\CAFEVDB\Service\CalDavService;

use OCA\CAFEVDB\Common\Util;

/**
 * An entity listener for Entities\ProjectEvent. The task is to remove the
 * absence category from the associated calendar event when removing the
 * absence field entity from the event entity. The other way round (adding the
 * absence field category when the absence field is set) need not be handled
 * as the absence field is set as a reaction to setting the "record absence"
 * category. However, the absence field itself may be removed through the UI
 * in which case the absenceField property of the project-event is set to
 * null. In this case we want also remove the absence category from the
 * associated calendar event.
 */
class ProjectEventEntityListener
{
  use \OCA\CAFEVDB\Toolkit\Traits\LoggerTrait;

  /** @var IL10N */
  protected $l;

  /** @var IAppContainer */
  protected $appContainer;

  /** @var EventsService */
  protected $eventsService;

  /** @var EntityManager */
  protected $entityManager;

  /** @var IUndoable[] */
  protected $preCommitActions;

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
  public function postPersist(Entities\ProjectEvent $project, ORMEvent\PostPersistEventArgs $eventArgs)
  {
    $this->registerPreCommitAction($project);
  }

  /**
   * {@inheritdoc}
   */
  public function preUpdate(Entities\ProjectEvent $project, ORMEvent\PreUpdateEventArgs $eventArgs)
  {
    if ($eventArgs->hasChangedField('absenceField')) {
      $this->registerPreCommitAction($project);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function postRemove(Entities\ProjectEvent $project, ORMEvent\PostRemoveEventArgs $eventArgs)
  {
    $this->registerPreCommitAction($project);
  }

  /**
   * @param Entities\ProjectEvent $projectEvent
   *
   * @return void
   */
  private function registerPreCommitAction(Entities\ProjectEvent $projectEvent):void
  {
    if (!empty($this->preCommitActions[$projectEvent->getId()])) {
      return;
    }
    if (empty($this->eventsService)) {
      $this->eventsService = $this->appContainer->get(EventsService::class);
    }
    $recordAbsenceCategory = $this->eventsService->getRecordAbsenceCategory();
    $this->preCommitActions[$projectEvent->getId()] = new GenericUndoable(
      function() use ($projectEvent, $recordAbsenceCategory) {
        if (empty($projectEvent->getAbsenceField())) {
          $additions = [];
          $removals[] = $recordAbsenceCategory;
        } else {
          $additions[] = $recordAbsenceCategory;
          $removals = [];
        }
        $changed = $this->eventsService->changeCategories(
          $projectEvent->getCalendarId(),
          $projectEvent->getEventUri(),
          $projectEvent->getRecurrenceId(),
          $additions,
          $removals,
        );
        return $changed;
      },
      function(bool $changed) use ($projectEvent, $recordAbsenceCategory) {
        if (!$changed) {
          return;
        }
        // just exchange additions and removals
        if (!empty($projectEvent->getAbsenceField())) {
          $additions = [];
          $removals[] = $recordAbsenceCategory;
        } else {
          $additions[] = $recordAbsenceCategory;
          $removals = [];
        }
        $this->eventsService->changeCategories(
          $projectEvent->getCalendarId(),
          $projectEvent->getEventUri(),
          $projectEvent->getRecurrenceId(),
          $additions,
          $removals,
        );
      },
    );
    $this->entityManager->registerPreCommitAction($this->preCommitActions[$projectEvent->getId()]);
  }
}
