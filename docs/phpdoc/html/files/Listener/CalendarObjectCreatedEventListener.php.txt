<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2020, 2021, 2022 Claus-Justus Heine
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

use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use Psr\Log\LoggerInterface as ILogger;
use OCP\IL10N;

use OCA\DAV\Events\CalendarObjectCreatedEvent as HandledEvent;

use OCA\CAFEVDB\Service\EventsService;

/**
 * Act on newly created events and tasks.
 *
 * @todo Make the CTOR less expensive.
 */
class CalendarObjectCreatedEventListener implements IEventListener
{
  use \OCA\CAFEVDB\Toolkit\Traits\LoggerTrait;

  const EVENT = HandledEvent::class;

  /** @var EventsService */
  private $eventsService;

  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct(
    EventsService $eventsService,
    ILogger $logger,
    IL10N $l10n,
  ) {
    $this->eventsService = $eventsService;
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
    $this->eventsService->onCalendarObjectCreated($event);
  }
}
