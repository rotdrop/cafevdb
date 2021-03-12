<?php
/* Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016, 2020, 2021 Claus-Justus Heine <himself@claus-justus-heine.de>
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

use OCP\User\Events\PasswordUpdatedEvent as HandledEvent;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\IGroupManager;
use OCP\ILogger;
use OCP\IL10N;
use OCA\CAFEVDB\Service\EncryptionService;

class PasswordUpdatedEventListener implements IEventListener
{
  use \OCA\CAFEVDB\Traits\LoggerTrait;

  const EVENT = HandledEvent::class;

  /** @var ISubAdmin */
  private $groupManager;

  /** @var EncryptionService */
  private $encryptionService;

  public function __construct(
    $appName
    , IGroupManager $groupManager
    , EncryptionService $encryptionService
    , ILogger $logger
    , IL10N $l10n
  ) {
    $this->appName = $appName;
    $this->groupManager = $groupManager;
    $this->encryptionService = $encryptionService;
    $this->logger = $logger;
    $this->l = $l10n;
  }

  public function handle(Event $event): void {
    if (!($event instanceOf HandledEvent)) {
      return;
    }

    $groupId = $this->encryptionService->getConfigValue('usergroup');
    $user = $event->getUser();
    $userId = $user->getUID();
    if (empty($groupId) || !$this->groupManager->isInGroup($userId, $groupId)) {
      return;
    }
    if ($userId != $this->encryptionService->getUserId()) {
      $this->logError('Mismatching users: '.$userId.' / '.$this->encryptionService->getUserId());
      return;
    }
    $this->encryptionService->recryptSharedPrivateValues($event->getPassword());
  }
}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
