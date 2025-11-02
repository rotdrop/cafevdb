<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2025 Claus-Justus Heine
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

use OCP\AppFramework\IAppContainer;
use OCP\Config\BeforePreferenceDeletedEvent;
use OCP\Config\BeforePreferenceSetEvent;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;

/**
 * Perform necessary tasks at logout time.
 *
 * @todo Handle simple user preferences only by this listener and clean up the
 * preferences controllers.
 */
class BeforePreferenceMutatedEventListener implements IEventListener
{
  use \OCA\CAFEVDB\Toolkit\Traits\LoggerTrait;

  private const ALLOWED_SETTINGS = [
    'defaultEmailFromAddress',
  ];

  const EVENT = [ BeforePreferenceDeletedEvent::class, BeforePreferenceSetEvent::class ];

  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct(
    protected IAppContainer $appContainer,
  ) {
  }
  // phpcs:enable

  /** {@inheritdoc} */
  public function handle(Event $event):void
  {
    if (!in_array(get_class($event), self::EVENT)) {
      return;
    }

    $this->logger = $this->appContainer->get(\Psr\Log\LoggerInterface::class);

    $appName = $this->appContainer->get('appName');

    /** @var BeforePreferenceSetEvent $event */
    $appId = $event->getAppId();
    if ($appId !== $appName) {
      return;
    }
    $key = $event->getConfigKey();
    if (in_array($key, self::ALLOWED_SETTINGS)) {
      $event->setValid(true);
    }

    return;
  }
}
