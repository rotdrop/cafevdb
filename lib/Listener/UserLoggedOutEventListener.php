<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2011-2016, 2020, 2022 Claus-Justus Heine
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

use OCP\User\Events\UserLoggedOutEvent as HandledEvent;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\IGroupManager;
use Psr\Log\LoggerInterface as ILogger;
use OCP\IL10N;

/**
 * Perform necessary tasks at logout time.
 *
 * @todo Make the CTOR less costly.
 * @todo This currently does nothing. Remove?
 */
class UserLoggedOutEventListener implements IEventListener
{
  use \OCA\CAFEVDB\Toolkit\Traits\LoggerTrait;

  const EVENT = HandledEvent::class;

  /** @var ISubAdmin */
  private $groupManager;

  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct(
    IGroupManager $groupManager,
    ILogger $logger,
    IL10N $l10n,
  ) {
    $this->groupManager = $groupManager;
    $this->logger = $logger;
    $this->l = $l10n;
  }
  // phpcs:enable

  /** {@inheritdoc} */
  public function handle(Event $event):void
  {
    if (!($event instanceof HandledEvent)) {
      return;
    }

    // $this->logInfo("Hello Logout-Handler!");
    return;
  }
}
