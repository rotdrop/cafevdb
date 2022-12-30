<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2011-2016, 2020, 2021, 2022 Claus-Justus Heine
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

use OCP\Group\Events\SubAdminAddedEvent as AddedEvent;
use OCP\Group\Events\SubAdminRemovedEvent as RemovedEvent;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\AppFramework\IAppContainer;
use OCP\IGroupManager;
use OCP\IConfig;
use OCP\ILogger;
use OCP\IL10N;

use OCA\CAFEVDB\Service\CloudUserConnectorService;
use OCA\CAFEVDB\Service\ConfigService;

/**
 * Track changes in sub-admins and act accordingly
 */
class SubAdminEventListener implements IEventListener
{
  use \OCA\CAFEVDB\Traits\LoggerTrait;

  const EVENT = [ AddedEvent::class, RemoteEvent::class ];

  /** @var IAppContainer */
  private $appContainer;

  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct(IAppContainer $appContainer)
  {
    $this->appContainer = $appContainer;
  }
  // phpcs:enable

  /** {@inheritdoc} */
  public function handle(Event $event): void
  {
    /** @var AddedEvent $event */
    if (!($event instanceof AddedEvent) && !($event instanceof RemovedEvent)) {
      return;
    }

    $group = $event->getGroup();

    /** @var IConfig $cloudConfig */
    $cloudConfig = $this->appContainer->get(IConfig::class);
    $appName = $this->appContainer->get('appName');
    $adminGroupId = $cloudConfig->getAppValue($appName, ConfigService::USER_GROUP_KEY, null);

    if ($group->getGID() != $adminGroupId) {
      return; // not for us
    }

    // ok, a new sub-admin has been added for the orchestra group, add it also
    // to the admin-group and as sub-admin to the catch-all group of the
    // user_sql backend.

    $adminUser = $event->getUser();

    // initialize only now in order to keep the overhead for unhandled events small
    $this->logger = $this->appContainer->get(ILogger::class);
    $this->l = $this->appContainer->get(IL10N::class);

    /** @var ConfigService $configService */
    $configService = $this->appContainer->get(ConfigService::class);

    /** @var \OCP\IGroup $orchestraGroup */
    $orchestraGroup = $configService->getGroup();

    if (empty($orchestraGroup)) {
      return; // no point in continuing
    }

    $subAdminGroup = $configService->getSubAdminGroup();
    if (empty($subAdminGroup)) {
      // create it
      $subAdminGroupId = $configService->getSubAdminGroupId();
      $subAdminGroup = $configService->getGroupManager()->createGroup($subAdminGroupId);
    }

    /** @var CloudUserConnectorService $cloudUserConnector */
    $cloudUserConnector = $this->appContainer->get(CloudUserConnectorService::class);
    if ($cloudUserConnector->haveCloudUserBackendConfig()) {
      $cloudUserCatchAllGroup = $configService->getGroup(CloudUserConnectorService::CLOUD_USER_GROUP_ID);
    }
    $subAdminManager = $configService->getSubAdminManager();

    if ($event instanceof AddedEvent) {
      $subAdminGroup->addUser($adminUser);
      if (!empty($cloudUserCatchAllGroup)
          && !$subAdminManager->isSubAdminOfGroup($adminUser, $cloudUserCatchAllGroup)) {
        $subAdminManager->createSubAdmin($adminUser, $cloudUserCatchAllGroup);
      }
    } else {
      $subAdminGroup->removeUser($adminUser);
      if (!empty($cloudUserCatchAllGroup)
          && $subAdminManager->isSubAdminOfGroup($adminUser, $cloudUserCatchAllGroup)) {
        $subAdminManager->deleteSubAdmin($adminUser, $cloudUserCatchAllGroup);
      }
    }
  }
}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
