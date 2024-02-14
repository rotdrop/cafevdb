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

use OCP\Group\Events\GroupCreatedEvent;
use OCP\Group\Events\GroupChangedEvent;
use OCP\IGroup;

use OCP\AppFramework\IAppContainer;
use Psr\Log\LoggerInterface as ILogger;

use OCA\CAFEVDB\Service\CloudAccountsService;

/**
 * Make sure that the orchestra management groups also exist in the Db backend
 * in order to make sure that any user can added to them, even if it does not
 * exist in the orchestras preferred user backend.
 *
 * The listener makes also sure that the primary orchestra group exists in the
 * primary user backend.
 *
 * We also promote any display-name change to all backends.
 *
 * Deletion is ok, the core tries to delete the group in all backends. Just
 * that it stops with the first backend that succeeds upon creation, chaging
 * display name and adding users.
 */
class GroupListener implements IEventListener
{
  use \OCA\CAFEVDB\Toolkit\Traits\LoggerTrait;

  const EVENT = [ GroupCreatedEvent::class, GroupChangedEvent::class ];

  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct(
    private IAppContainer $appContainer,
  ) {
  }
  // phpcs:enable

  /** {@inheritdoc} */
  public function handle(Event $event):void
  {
    $eventClass = get_class($event);
    if (!in_array($eventClass, self::EVENT)) {
      return;
    }

    if ($eventClass === GroupChangedEvent::class && $event->getFeature != 'displayName') {
      return;
    }

    $appName = $this->appContainer->get('appName');

    /** @var ICloudConfig $cloudConfig */
    $cloudConfig = $this->appContainer->get(ICloudConfig::class);
    $orchestraGroupId = $cloudConfig->getAppValue($appName, ConfigService::USER_GROUP_KEY);

    if (empty($orchestraGroupId)) {
      return;
    }

    /** @var IGroup $group */
    $group = $event->getGroup();

    $gid = $group->getGID();
    if (!str_starts_with($gid, $orchestraGroupId)) {
      // not for us, leave it alone.
      return;
    }

    /** @var CloudAccountsService $cloudAccountsService */
    $cloudAccountsService = $this->appContainer->get(CloudAccountsService::class);

    switch (true) {
      case ($event instanceof GroupChangedEvent):
        $cloudAccountsService->promoteGroupDisplayName($group);
        break;
      case ($event instanceof GroupCreatedEvent):
        $cloudAccountsService->ensureGroupBackends($group);
        break;
    }
    return;
  }
}
