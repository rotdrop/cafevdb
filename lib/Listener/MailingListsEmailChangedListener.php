<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright , 2021, 2022,  Claus-Justus Heine <himself@claus-justus-heine.de>
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

namespace OCA\CAFEVDB\Listener;

use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\AppFramework\IAppContainer;
use OCP\ILogger;

use OCA\CAFEVDB\Service\MailingListsService;
use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Events\PostChangeMusicianEmail as HandledEvent;
use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Database\Doctrine\ORM\Repositories;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;

/**
 * Listen to renamed and deleted events in order to keep the
 * configured document-templates synchronized with the cloud
 * file-system.
 */
class MailingListsEmailChangedListener implements IEventListener
{
  use \OCA\CAFEVDB\Traits\LoggerTrait;
  use \OCA\CAFEVDB\Traits\EntityManagerTrait;

  const EVENT = HandledEvent::class;

  /** @var IAppContainer */
  private $appContainer;

  public function __construct(IAppContainer $appContainer)
  {
    $this->appContainer = $appContainer;
  }

  public function handle(Event $event): void {
    if (!($event instanceOf HandledEvent)) {
      return;
    }
    /** @var HandledEvent $event */

    $oldEmail = $event->getOldEmail();
    $newEmail = $event->getMusician()->getEmailAddress();

    if (empty($oldEmail) && empty($newEmail)) {
      return;
    }

    /** @var ConfigService $configService */
    $configService = $this->appContainer->get(ConfigService::class);
    if (empty($configService)) {
      return;
    }

    $this->entityManager = $this->appContainer->get(EntityManager::class);
    /** @var Repositories\ProjectsRepository $projectsRepository */
    $projectsRepository = $this->getDatabaseRepository(Entities\Project::class);
    $listIds = $projectsRepository->fetchMailingListIds();

    $listIds[] = $configService->getConfigValue('announcementsMailingList');
    $listIds = array_filter($listIds);

    if (empty($listIds)) {
      return;
    }

    /** @var MailingListsService $listsService */
    $listsService = $this->appContainer->get(MailingListsService::class);
    if (empty($listsService)) {
      return;
    }

    $this->logger = $this->appContainer->get(ILogger::class);

    $displayName = null;
    $subscribeNewEmail = false;

    foreach ($listIds as $listId) {
      $subscribeNewEmail = false;
      if (!empty($oldEmail)) {
        try {
          $subscription = $listsService->getSubscription($listId, $oldEmail);
          if (!empty($subscription[MailingListsService::ROLE_MEMBER])) {
            $subscription = $subscription[MailingListsService::ROLE_MEMBER];
            $subscribeNewEmail = true;
            $listsService->unsubscribe($listId, $oldEmail);
            $displayName = $subscription['display_name'];
          }
        } catch (\Throwable $t) {
          $this->logException($t, 'Unsubscribing from old email failed.');
          continue; // avoid duplicating subscriptions
        }
      }

      if ($subscribeNewEmail && !empty($newEmail)) {
        // subscribe
        try {
          $listsService->subscribe($listId, $newEmail, $displayName);
        } catch (\Throwable $t) {
          $this->logException($t, 'Subscribing to new email failed.');
        }
      }
    }

  }
}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
