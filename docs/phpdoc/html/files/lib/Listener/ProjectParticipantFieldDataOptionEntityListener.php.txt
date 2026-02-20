<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2022, 2023 Claus-Justus Heine
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

use OCA\CAFEVDB\Wrapped\Doctrine\ORM\Event;

use Psr\Log\LoggerInterface as ILogger;
use OCP\AppFramework\IAppContainer;

use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Service\ProjectParticipantFieldsService;

/**
 * An Doctrine\Orm entity listener. The task is to handle rename events.
 */
class ProjectParticipantFieldDataOptionEntityListener
{
  use \OCA\CAFEVDB\Toolkit\Traits\LoggerTrait;

  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct(
    protected ILogger $logger,
    protected IAppContainer $appContainer,
    protected EntityManager $entityManager,
  ) {
  }
  // phpcs:enable

  /**
   * {@inheritdoc}
   */
  public function preUpdate(Entities\ProjectParticipantFieldDataOption $option, Event\PreUpdateEventArgs $event)
  {
    $field = 'label';
    if (!$event->hasChangedField($field)) {
      return;
    }
    $oldLabel = $event->getOldValue($field);
    $newLabel = $event->getNewValue($field);

    $this->logInfo('OLD / NEW: ' . $oldLabel . ' / ' . $newLabel);

    if ($oldLabel == $newLabel) {
      $this->logInfo('Cowardly refusing to handle rename to same label: ' . $oldLabel);
      return;
    }

     /** @var ProjectParticipantFieldsService $participantFieldsService */
    $participantFieldsService = $this->appContainer->get(ProjectParticipantFieldsService::class);

    $participantFieldsService->handleRenameOption($option, $oldLabel, $newLabel);
  }
}
