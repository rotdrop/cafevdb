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

use OCP\User\Events\PasswordUpdatedEvent as HandledEvent;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\AppFramework\IAppContainer;
use Psr\Log\LoggerInterface as ILogger;
use OCP\IL10N;

use OCA\CAFEVDB\Crypto\AsymmetricKeyService;
use OCA\CAFEVDB\Service\AuthorizationService;

/** Perform re-recryption action when the user password has been updated. */
class PasswordUpdatedEventListener implements IEventListener
{
  use \OCA\CAFEVDB\Toolkit\Traits\LoggerTrait;

  const EVENT = HandledEvent::class;

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

    $user = $event->getUser();
    $userId = $user->getUID();

    $this->logger = $this->appContainer->get(ILogger::class);
    $this->l = $this->appContainer->get(IL10N::class);

    try {
      /** @var AsymmetricKeyService $keyService */
      $keyService = $this->appContainer->get(AsymmetricKeyService::class);
      $keyService->initEncryptionKeyPair($userId, $event->getPassword(), forceNewKeyPair: true);
    } catch (\Throwable $t) {
      $this->logException($t, $this->l->t('Unable to initialize asymmetric key-pair for user "%s".', $userId));
    }
  }
}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
