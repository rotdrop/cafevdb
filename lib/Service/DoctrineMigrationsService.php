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

namespace OCA\CAFEVDB\Service;

use OCA\CAFEVDB\Wrapped\Doctrine\Migrations\DependencyFactory;
use OCA\CAFEVDB\Wrapped\Doctrine\Migrations\Configuration\EntityManager\ExistingEntityManager;
use OCA\CAFEVDB\Wrapped\Doctrine\Migrations\Configuration\Migration\JsonFile;
use OCA\CAFEVDB\Database\EntityManager;

/** Manage doctrine database migrations. */
class DoctrineMigrationsService
{
  use \OCA\CAFEVDB\Toolkit\Traits\LoggerTrait;

  // phpcs:disabled Squiz.Commenting.FunctionComment.Missing
  public function __construct(
    protected ILogger $logger,
    protected EntityManager $entityManager,
  ) {

    // $configurationLoader = new JsonFile(__DIR__ . '/../appinfo/migrations.json');
    // $dependencyFactory = DependencyFactory::fromEntityManager(
    //   $configurationLoader,
    //   new ExistingEntityManager($entityManager),
  }
  // phpcs:enable

  /** @return ?string The latest applied migration. null if none has been applied yet. */
  public function getLatest(): ?string
  {
    $aliasResolver = $this->getDependencyFactory()->getVersionAliasResolver();
    $version = (string)$aliasResolver->resolveVersionAlias('current');
    if ($version === '0') {
      return null;
    }
    return $version;
  }
}
