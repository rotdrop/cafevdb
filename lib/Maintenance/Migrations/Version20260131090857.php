<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2026 Claus-Justus Heine
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

declare(strict_types=1);

namespace OCA\CAFEVDB\Maintenance\Migrations;

use OCA\CAFEVDB\Database\Doctrine\Migrations\AbstractTransactionalMigration;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Service\EventsService;
use OCA\CAFEVDB\Wrapped\Doctrine\DBAL\Schema\Schema;

/**
 * Ensure the ProjectEvent entities for registration calendar events are
 * linked to the project.
 */
final class Version20260131090857 extends AbstractTransactionalMigration
{
  /** {@inheritdoc} */
  public function getDescription(): string
  {
    return $this->l->t('Ensure the ProjectEvent entities for the project registration calendar events are linked to the project.');
  }

  /** {@inheritdoc} */
  public function up(Schema $schema): void
  {
    // this up() migration is auto-generated, please modify it to your needs
  }

  /** {@inheritdoc} */
  public function down(Schema $schema): void
  {
    // this down() migration is auto-generated, please modify it to your needs
  }

  /** {@inheritdoc} */
  public function preUp(Schema $schema): void
  {
    /** @var EventsService $eventsService */
    $eventsService = $this->appContainer->get(EventsService::class);
    $oldSoftDeleteable = $this->entityManager->setFilterEnabled(EntityManager::SOFT_DELETEABLE_FILTER, state: false);
    $projects = $this->entityManager->getRepository(Entities\Project::class)->findAll();
    /** @var Entities\Project $project */
    foreach ($projects as $project) {
      $eventsService->ensureProjectRegistrationEvent($project);
    }

    $this->entityManager->setFilterEnabled(EntityManager::SOFT_DELETEABLE_FILTER, state: $oldSoftDeleteable);
  }

  /** {@inheritdoc} */
  public function postDown(Schema $schema): void
  {
    // cannot be easily undown because the various event listeners will
    // enforce consistency: as long as the calendar events exist the project
    // events will be regenerated and the registrationevent association will
    // be established.
  }
}
