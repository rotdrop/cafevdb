<?php
/* Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2020 Claus-Justus Heine <himself@claus-justus-heine.de>
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
use OCP\ILogger;
use OCP\IL10N;

use OCA\DAV\Events\CalendarObjectCreatedEvent as HandledEvent;

use OCA\CAFEVDB\Service\EventsService;

class CalendarObjectCreatedEventListener implements IEventListener
{
  use \OCA\CAFEVDB\Traits\LoggerTrait;

  const EVENT = HandledEvent::class;

  /** @var EventsService */
  private $eventsService;

  public function __construct(
    EventsService $eventsService
    , ILogger $logger
    , IL10N $l10n
  ) {
    $this->eventsService = $eventsService;
    $this->logger = $logger;
    $this->l = $l10n;
  }

  public function handle(Event $event): void {
    if (!($event instanceOf HandledEvent)) {
      return;
    }
    $this->logInfo("Hello World");
    $this->eventsService->onCalendarObjectCreated($event);
  }
}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
