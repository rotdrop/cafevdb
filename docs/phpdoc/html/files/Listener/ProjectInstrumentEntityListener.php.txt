<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2024, 2025 Claus-Justus Heine
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

use OCA\CAFEVDB\Wrapped\Doctrine\ORM\Event as ORMEvent;

use Psr\Log\LoggerInterface as ILogger;
use OCP\AppFramework\IAppContainer;

use OCA\CAFEVDB\Database\Doctrine\DBAL\Types\EnumParticipationStatus as ParticipationStatus;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities\ProjectInstrument as Entity;
use OCA\CAFEVDB\Database\EntityManager;

/**
 * Entity listener for project participation.
 *
 * - fire a cloud event if the registration value changes
 *
 * - fire a cloud event if an entity is created, deleted, disabled
 */
class ProjectInstrumentEntityListener
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
   *
   * Adjust the participation status if necessary.
   *
   * @todo Check that this works.
   */
  public function prePersist($entity, ORMEvent\PrePersistEventArgs $event)
  {
    $participant = $entity->getProjectParticipant();
    if (!$entity->isNotAnInstrument()
        && $participant->getParticipationStatus() == ParticipationStatus::ASSOCIATED) {
      $status = $participant->getMusician()->getDefaultParticipationStatus();
      if ($status == ParticipationStatus::ASSOCIATED) {
        $status = ParticipationStatus::REGULAR;
      }
      $participant->setParticipationStatus($status);
    }
  }

  /**
   * {@inheritdoc}
   *
   * Possibly adjust the participation status.
   */
  public function preRemove($entity, ORMEvent\PreRemoveEventArgs $event)
  {
    if (!$entity->isNotAnInstrument()) {
      $participant = $entity->getProjectParticipant();
      $realInstruments = $participant->getRealInstruments();
      $nonInstruments = $participant->getNonInstruments();
      if (($realInstruments->isEmpty() || $realInstruments->first() == $entity)
          && !$nonInstruments->isEmpty() && !$nonInstruments->first() == $entity) {
        $participant->setParticipationStatus(ParticipationStatus::ASSOCIATED);
      }
    }
  }
}
