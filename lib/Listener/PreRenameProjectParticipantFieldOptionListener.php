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

use OCP\AppFramework\IAppContainer;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use Psr\Log\LoggerInterface as ILogger;
use OCP\IL10N;

use OCA\CAFEVDB\Events\PreRenameProjectParticipantFieldOption as HandledEvent;
use OCA\CAFEVDB\Service\ProjectParticipantFieldsService;

/** Rename file-system nodes if the field refers to file-attachments. */
class PreRenameProjectParticipantFieldOptionListener implements IEventListener
{
  use \OCA\RotDrop\Toolkit\Traits\LoggerTrait;

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
    /** @var HandledEvent $event */
    if (!($event instanceof HandledEvent)) {
      return;
    }

    // only lookup when this is "our" event
    $this->logger = $this->appContainer->get(ILogger::class);
    $this->l = $this->appContainer->get(IL10N::class);

    $oldLabel = $event->getOldLabel();
    $newLabel = $event->getNewLabel();

    $this->logInfo('OLD / NEW: ' . $oldLabel . ' / ' . $newLabel);

    if ($oldLabel == $newLabel) {
      $this->logInfo('Cowardly refusing to handle rename to same label: ' . $oldLabel);
      return;
    }

    /** @var ProjectParticipantFieldsService $participantFieldsService */
    $participantFieldsService = $this->appContainer->get(ProjectParticipantFieldsService::class);

    $participantFieldsService->handleRenameOption($event->getOption(), $oldLabel, $newLabel);
  }
}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
