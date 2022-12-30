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
use OCP\ILogger;
use OCP\IL10N;

use OCA\CAFEVDB\Events\PreChangeUserIdSlug as HandledEvent;
use OCA\CAFEVDB\Service\ProjectService;

/** Perform renaming action when the user-id slug changes. */
class PreChangeUserIdSlugListener implements IEventListener
{
  use \OCA\CAFEVDB\Traits\LoggerTrait;

  const EVENT = HandledEvent::class;

  /** @var IAppContainer */
  private $appContainer;

  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct(
    IAppContainer $appContainer,
    ILogger $logger,
    IL10N $l10n,
  ) {
    $this->appContainer = $appContainer;
    $this->logger = $logger;
    $this->l = $l10n;
  }
  // phpcs:enable

  /** {@inheritdoc} */
  public function handle(Event $event):void
  {
    /** @var HandledEvent $event */
    if (!($event instanceof HandledEvent)) {
      return;
    }

    $oldSlug = $event->getOldSlug();
    $newSlug = $event->getNewSlug();

    $this->logInfo('OLD / NEW: ' . $oldSlug . ' / ' . $newSlug);

    if ($oldSlug === $newSlug) {
      $this->logWarn('Cowardly refusing to handle rename to same slug: ' . $oldSlug);
      return;
    }

    /** @var ProjectService $projectService */
    $projectService = $this->appContainer->get(ProjectService::class);

    $projectService->renameParticipantFolders($event->getMusician(), $oldSlug, $newSlug);
  }
}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
