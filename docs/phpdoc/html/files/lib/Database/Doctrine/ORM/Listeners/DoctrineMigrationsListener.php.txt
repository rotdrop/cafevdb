<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2024, 2026 Claus-Justus Heine
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

namespace OCA\CAFEVDB\Database\Doctrine\ORM\Listeners;

use OCA\CAFEVDB\Common\ConsoleLogger;
use OCA\CAFEVDB\Wrapped\Doctrine\Common\EventSubscriber;
use OCA\CAFEVDB\Wrapped\Doctrine\Migrations\Event\MigrationsEventArgs;
use OCA\CAFEVDB\Wrapped\Doctrine\Migrations\Event\MigrationsVersionEventArgs;
use OCA\CAFEVDB\Wrapped\Doctrine\Migrations\Events;

/**
 * Listener for DoctrineMigrations events.
 */
class DoctrineMigrationsListener implements EventSubscriber
{
  use \OCA\CAFEVDB\Toolkit\Traits\LoggerTrait;

  /**
   * @param LoggerInterface $logger
   *
   * @param bool $isCLI
   */
  public function __construct(
    ConsoleLogger $logger,
    private bool $isCLI,
  ) {
    $this->logger = $logger;
  }

  /** {@inheritdoc} */
  public function getSubscribedEvents(): array
  {
    return [
      Events::onMigrationsMigrating,
      Events::onMigrationsMigrated,
      Events::onMigrationsVersionExecuting,
      Events::onMigrationsVersionExecuted,
      Events::onMigrationsVersionSkipped,
    ];
  }

  /** {@inheritdoc} */
  public function onMigrationsMigrating(MigrationsEventArgs $args): void
  {
    $this->logInfo('');
  }
  /** {@inheritdoc} */
  public function onMigrationsMigrated(MigrationsEventArgs $args): void
  {
    $this->logInfo('');
  }
  /** {@inheritdoc} */
  public function onMigrationsVersionExecuting(MigrationsVersionEventArgs $args): void
  {
    $this->logInfo('');
  }
  /** {@inheritdoc} */
  public function onMigrationsVersionExecuted(MigrationsVersionEventArgs $args): void
  {
    $this->logInfo('');
  }
  /** {@inheritdoc} */
  public function onMigrationsVersionSkipped(MigrationsVersionEventArgs $args): void
  {
    $this->logInfo('');
  }
}
