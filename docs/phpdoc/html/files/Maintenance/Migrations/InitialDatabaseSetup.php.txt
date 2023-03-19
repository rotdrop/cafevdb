<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2020-2023 Claus-Justus Heine
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

namespace OCA\CAFEVDB\Maintenance\Migrations;

use Psr\Log\LoggerInterface as ILogger;
use OCP\IL10N;

use OCA\CAFEVDB\Maintenance\IMigration;
use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;

/**
 * The initial data-base structure. This just generates the table
 * structure. We assume that the data-base is empty.
 *
 * @todo Should probably use a custom schema-provider and generate a
 * DBAL schema from PHP statements. Unfortunately, there does not seem
 * to be an automated tool for such code-generation.
 */
class InitialDatabaseSetup implements IMigration
{
  use \OCA\CAFEVDB\Toolkit\Traits\LoggerTrait;
  use \OCA\CAFEVDB\Traits\EntityManagerTrait;

  private const INITIAL_SQL = __DIR__ . '/../../../appinfo/database/doctrine-orm-initial.sql';

  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct(
    ILogger $logger,
    IL10N $l10n,
    EntityManager $entityManager,
  ) {
    $this->logger = $logger;
    $this->l = $l10n;
    $this->entityManager = $entityManager;
  }
  // phpcs:enable

  /** {@inheritdoc} */
  public function description():string
  {
    return $this->l->t('Initial database setup.');
  }

  /** {@inheritdoc} */
  public function execute():bool
  {
    $connection = $this->entityManager->getConnection();
    $schemaSql = file_get_contents(self::INITIAL_SQL);
    $schemaStatement = $connection->prepare($schemaSql);
    $schemaStatement->execute();
    return true;
  }
}
