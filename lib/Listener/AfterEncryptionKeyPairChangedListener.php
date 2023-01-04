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
 * License along with this library.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace OCA\CAFEVDB\Listener;

use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\AppFramework\IAppContainer;
use OCP\Notification\IManager as NotificationManager;
use Psr\Log\LoggerInterface as ILogger;
use OCP\IL10N;

use OCA\CAFEVDB\Events\AfterEncryptionKeyPairChanged as HandledEvent;
use OCA\CAFEVDB\Crypto\AsymmetricKeyService;
use OCA\CAFEVDB\Service\OrganizationalRolesService;
use OCA\CAFEVDB\Notifications\Notifier;

/** Perform re-encryption tasks after encryption keys have been changed. */
class AfterEncryptionKeyPairChangedListener implements IEventListener
{
  use \OCA\RotDrop\Toolkit\Traits\LoggerTrait;

  const EVENT = HandledEvent::class;

  /** @var string */
  private $appName;

  /** @var IAppContainer */
  private $appContainer;

  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct(IAppContainer $appContainer)
  {
    $this->appContainer = $appContainer;
  }
  // phpcs:enable

  /** {@inheritdoc} */
  public function handle(Event $event):void
  {
    if (!($event instanceof HandledEvent)) {
      return;
    }
    /** @var HandledEvent $event */

    // initialize only now in order to keep the overhead for unhandled events small
    $this->appName = $this->appContainer->get('appName');
    $this->logger = $this->appContainer->get(ILogger::class);
    $this->l = $this->appContainer->get(IL10N::class);

    $ownerId = $event->getOwnerId();
    $oldKeyPair = $event->getOldKeyPair();
    $newKeyPair = $event->getNewKeyPair();

    /** @var AsymmetricKeyService $keyService */
    $keyService = $this->appContainer->get(AsymmetricKeyService::class);

    if (empty($oldKeyPair[AsymmetricKeyService::PRIVATE_ENCRYPTION_KEY_CONFIG])) {
      // this cannot be helped, remove the old values. In the future we may
      // want to enqueue a restore request if this happened
      $keyService->removeSharedPrivateData($ownerId);
    } else {
      $keyService->recryptSharedPrivateData($ownerId, $oldKeyPair, $newKeyPair);
    }

    // enqueue a recryption request to the admins
    $keyService->pushRecryptionRequestNotification($ownerId);
  }
}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
