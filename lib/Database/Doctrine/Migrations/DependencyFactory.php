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

namespace OCA\CAFEVDB\Database\Doctrine\Migrations;

use Psr\Log\LoggerInterface;
use OCP\AppFramework\IAppContainer;

use OCA\CAFEVDB\Wrapped\Doctrine\Migrations\DependencyFactory as VanillaDependencyFactory;
use OCA\CAFEVDB\Wrapped\Doctrine\Migrations\Version\MigrationFactory;
use OCA\CAFEVDB\Wrapped\Doctrine\Migrations\AbstractMigration;
use OCA\CAFEVDB\Wrapped\Doctrine\Migrations\Configuration\Migration\ConfigurationLoader;
use OCA\CAFEVDB\Wrapped\Doctrine\Migrations\Configuration\EntityManager\EntityManagerLoader;

/**
 * Augment the stock dependecy factory partly by NC dependency injection. So
 * far only the migration instances are affected.
 */
class DependencyFactory extends VanillaDependencyFactory
{
  protected IAppContainer $appContainer;

  /** {@inheritdoc} */
  public static function fromEntityManager(
    ConfigurationLoader $configurationLoader,
    EntityManagerLoader $emLoader,
    ?LoggerInterface $logger = null,
    ?IAppContainer $appContainer = null,
  ): self {
    $dependencyFactory = parent::fromEntityManager(
      configurationLoader: $configurationLoader,
      emLoader: $emLoader,
      logger: $logger,
    );
    $dependencyFactory->appContainer = $appContainer;

    return $dependencyFactory;
  }

  /** {@inheritdoc} */
  public function getMigrationFactory(): MigrationFactory
  {
    return $this->getDependency(
      MigrationFactory::class,
      fn (): MigrationFactory => new class($this->appContainer) implements MigrationFactory {
        /**
         * @param IAppContainer $appContainer
         */
        public function __construct(
          protected IAppContainer $appContainer,
        ) {
        }

        /**
         * @param string $migrationClassName
         *
         * @return AbstractStructuralMigration|AbstractTransactionalMigration
         */
        public function createVersion(string $migrationClassName): AbstractStructuralMigration|AbstractTransactionalMigration
        {
          return $this->appContainer->resolve($migrationClassName);
        }
      }
    );
  }
}
