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

use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface as ILogger;

use OCA\CAFEVDB\Common\GenericUndoable;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Events\PostChangeRegistrationConfirmation as HandledEvent;

/**
 * Enable the cloud user account when a club-member has been added and confirmed.
 */
class CloudUserRegistrationConfirmationListener implements IEventListener
{
  use \OCA\CAFEVDB\Toolkit\Traits\LoggerTrait;
  use \OCA\CAFEVDB\Traits\EntityManagerTrait;

  const EVENT = HandledEvent::class;

  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct(
    protected ContainerInterface $appContainer,
  ) {
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

    /** @var Entities\ProjectParticipant $participant */
    $participant = $event->getProjectParticipant();
    $musician = $participant->getMusician();

    /** @var EncryptionService $encryptionService */
    $encryptionService = $this->appContainer->get(EncryptionService::class);

    $clubMembersProjectId = $encryptionService->getConfigValue(ConfigConstants::CLUB_MEMBER_PROJECT_ID_KEY, -1);
    if ($participant->getProject()->getId() == $clubMembersProjectId
        && $participant->getRegistration() == true
        && $event->getOldRegistration() != $participant->getRegistration()
        && $musician->getCloudAccountDisabled() == true
    ) {
      /** @var EntityManager $entityManager */
      $entityManager = $this->appContainer->get(EntityManager::class);

      $entityManager->registerPostCommitAction(
        new GenericUndoable(
          function() use ($musician) { $musician->setCloudAccountDisabled(false); },
          function() use ($musician) { $musician->setCloudAccountDisabled(true); },
        ),
      );
    }
  }
}
