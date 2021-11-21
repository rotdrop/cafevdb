<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2020, 2021 Claus-Justus Heine <himself@claus-justus-heine.de>
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

use OCP\AppFramework\IAppContainer;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\ILogger;
use OCP\IL10N;

use OCA\CAFEVDB\Events\PreRenameProjectParticipantField as HandledEvent;
use OCA\CAFEVDB\Service\ProjectParticipantFieldsService;

class PreRenameProjectParticipantFieldListener implements IEventListener
{
  use \OCA\CAFEVDB\Traits\LoggerTrait;

  const EVENT = HandledEvent::class;

  /** @var IAppContainer */
  private $appContainer;

  public function __construct(
    IAppContainer $appContainer
    , ILogger $logger
    , IL10N $l10n
  ) {
    $this->appContainer = $appContainer;
    $this->logger = $logger;
    $this->l = $l10n;
  }

  public function handle(Event $event): void {
    /** @var HandledEvent $event */
    if (!($event instanceOf HandledEvent)) {
      return;
    }

    $this->logInfo('OLD / NEW: ' . $event->getOldName() . ' / ' . $event->getNewName());

    /** @var ProjectParticipantFieldsService $participantFieldsService */
    $participantFieldsService = $this->appContainer->get(ProjectParticipantFieldsService::class);

    $participantFieldsService->handleRenameField($event->getField(), $event->getOldName(), $event->getNewName());
  }
}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
