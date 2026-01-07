<?php
/**
 * Orchestra member, musicion and project management application.
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

namespace OCA\CAFEVDB\Maintenance\Migrations\Legacy;

use DateTimeImmutable;
use UnexpectedValueException;
use Throwable;

use OCP\IL10N;
use Psr\Log\LoggerInterface;

use OCA\CAFEVDB\Database\Doctrine\Migrations as DoctrineMigrations;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Exceptions;
use OCA\CAFEVDB\Service\DoctrineMigrationsService;

/**
 * Finally move to Doctrine Migrations.
 */
class CreateTableDoctrineMigrationsVersions extends AbstractMigration
{
  protected static $sql = [
    self::STRUCTURAL => [
      "CREATE TABLE IF NOT EXISTS DoctrineMigrationsVersions (
  version VARCHAR(191) NOT NULL,
  executed_at DATETIME(6) DEFAULT NULL,
  execution_time INT DEFAULT NULL,
  PRIMARY KEY (version)
)",
    ],
  ];

  /** {@inheritdoc} */
  public function __construct(
    LoggerInterface $logger,
    IL10N $l,
    EntityManager $entityManager,
    protected DoctrineMigrationsService $doctrineMigrationsService,
  ) {
    parent::__construct(logger: $logger, l: $l, entityManager: $entityManager);
  }

  /** {@inheritdoc} */
  public function description():string
  {
    return $this->l->t('Create the Doctrine Migrations Table and pretend the initial setup migration has been run already.');
  }

  /** {@inheritdoc} */
  public function execute(): bool
  {
    $result = parent::execute();
    if (!$result) {
      $this->logError('Parent execute returned \\false');
      return $result;
    }
    $applied = $this->doctrineMigrationsService->getApplied();
    if (!empty($applied)) {
      // nothing more to do, we assume that the initial migration is just the
      // one setting up the db, so if any Doctrine migration has been run then
      // the legacy code has nothing more to do.
      return true;
    }
    $unapplied = $this->doctrineMigrationsService->getUnapplied();
    if (empty($unapplied)) {
      throw new UnexpectedValueException(
        $this->l->t('Unable to switch to Doctrine migrations as the initial database migration does not seem to be available.'),
      );
    }
    $versionString = array_keys($unapplied)[0];
    $this->entityManager->beginTransaction();
    try {
      $setupVersion = new Entities\DoctrineMigrationsVersion()
        ->setVersion(DoctrineMigrations::class . '\\Version' . $versionString)
        ->setExecutedAt(new DateTimeImmutable)
        ->setExecutionTime(0)
        ;
      $this->entityManager->persist($setupVersion);
      $this->entityManager->flush();
      $this->entityManager->commit();
      $this->logInfo('NEW ENTITY ' . print_r($setupVersion, true));
    } catch (Throwable $t) {
      if ($this->entityManager->isTransactionActive()) {
        try {
          $this->entityManager->rollBack();
        } catch (Throwable $t2) {
          $t = new Exceptions\DatabaseMigrationException(
            $this->l->t('Rollback of Migration "%s" failed.', $this->description()),
            previous: $t,
          );
        }
      }
      throw new Exceptions\DatabaseMigrationException(
        $this->l->t('Transactional part of Migration "%s" failed.', $this->description()),
        previous: $t,
      );
    }
    $this->doctrineMigrationsService->clearCache();
    $applied = $this->doctrineMigrationsService->getApplied();
    if (empty($applied[$versionString])) {
      throw new Exceptions\DatabaseMigrationException(
        $this->l->t('Injecting the initial setup migration into the Doctrine migrations version table seems to have failed.'),
      );
    }
    return true;
  }
}
