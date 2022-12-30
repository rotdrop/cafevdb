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

use OCP\User\Events\UserLoggedInEvent as Event1;
use OCP\User\Events\UserLoggedInWithCookieEvent as Event2;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\AppFramework\IAppContainer;
use OCP\IGroupManager;
use OCP\IConfig;
use OCP\ILogger;
use OCP\IL10N;

use OCA\CAFEVDB\Service\EncryptionService;
use OCA\CAFEVDB\Service\AuthorizationService;
use OCA\CAFEVDB\Crypto\AsymmetricKeyService;

/**
 * Do early initialization of the encryption service.
 */
class UserLoggedInEventListener implements IEventListener
{
  use \OCA\CAFEVDB\Traits\LoggerTrait;

  const EVENT = [ Event1::class, Event2::class ];

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
  public function handle(Event $event): void
  {
    if (!($event instanceof Event1) && !($event instanceof Event2)) {
      return;
    }

    // initialize only now in order to keep the overhead for unhandled events small
    $this->appName = $this->appContainer->get('appName');
    $this->logger = $this->appContainer->get(ILogger::class);
    $this->l = $this->appContainer->get(IL10N::class);

    $userId = $event->getUser()->getUID();

    // for every user ensure a private/public key-pair
    try {
      /** @var AsymmetricKeyService $keyService */
      $keyService = $this->appContainer->get(AsymmetricKeyService::class);
      $keyService->initEncryptionKeyPair($userId, $event->getPassword());
    } catch (\Throwable $t) {
      $this->logException($t, $this->l->t('Unable to initialize asymmetric key-pair for user "%s".', $userId));
    }

    // the listener should not throw ...
    try {
      /** @var AuthorizationService $authorization */
      $authorization = $this->appContainer->get(AuthorizationService::class);
      if (!$authorization->authorized($userId)) {
        return;
      }

      /** @var EncryptionService $encryptionService */
      $encryptionService = $this->appContainer->get(EncryptionService::class);
      if (!$encryptionService->bound()) {
        $encryptionService->bind($userId, $event->getPassword());
      }
    } catch (\Throwable $t) {
      $this->logException($t, $this->l->t('Unable to bind encryption service for user "%s".', $userId));
    }
  }
}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
