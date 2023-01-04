<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright , 2021, 2022,  Claus-Justus Heine
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
use OCP\AppFramework\IAppContainer;
use Psr\Log\LoggerInterface as ILogger;

use OCA\CAFEVDB\Service\ProjectService;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;

use OCA\CAFEVDB\Events\PostChangeRegistrationConfirmation as HandledEvent;

/**
 * Listen to renamed and deleted events in order to keep the
 * configured document-templates synchronized with the cloud
 * file-system.
 */
class MailingListsRegistrationConfirmationListener implements IEventListener
{
  use \OCA\RotDrop\Toolkit\Traits\LoggerTrait;
  use \OCA\CAFEVDB\Traits\EntityManagerTrait;

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
    /** @var HandledEvent $event */

    $this->logger = $this->appContainer->get(ILogger::class);
    if (empty($this->logger)) {
      return;
    }
    $this->logInfo('EVENT');

    /** @var Entities\ProjectParticipant $participant */
    $participant = $event->getProjectParticipant();
    $email = $participant->getMusician()->getEmail();
    $listId = $participant->getProject()->getMailingListId();

    if (empty($email) || empty($listId)) {
      return;
    }

    /** @var ProjectService $projectService */
    $projectService = $this->appContainer->get(ProjectService::class);
    if (empty($projectService)) {
      return;
    }

    try {
      if ($participant->getRegistration()) {
        $this->logInfo('Ensure mailing list subscription');
        $projectService->ensureMailingListSubscription($participant);
      } else {
        $this->logInfo('Ensure mailing list unsubscription');
        $projectService->ensureMailingListUnsubscription($participant);
      }
    } catch (\Throwable $t) {
      $this->logException($t, 'Unable to change mailing list subscription after changing registration confirmation.');
    }
  }
}
