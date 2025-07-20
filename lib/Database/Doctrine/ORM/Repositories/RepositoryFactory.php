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

namespace OCA\CAFEVDB\Database\Doctrine\ORM\Repositories;

use Psr\Log\LoggerInterface;

use OCP\AppFramework\IAppContainer;

use OCA\CAFEVDB\Database\Doctrine\ORM\Repositories\EntityRepository as DerivedEntityRepository;
use OCA\CAFEVDB\Database\EntityManager as DecoratedEntityManager;
use OCA\CAFEVDB\Wrapped\Doctrine\ORM\EntityManagerInterface;
use OCA\CAFEVDB\Wrapped\Doctrine\ORM\EntityRepository;
use OCA\CAFEVDB\Wrapped\Doctrine\ORM\Repository\RepositoryFactory as RepositoryFactoryInterface;
use OCA\CAFEVDB\Wrapped\Doctrine\Persistence\ObjectRepository;

/**
 * Custom repository factory which hooks into the dependency injection
 * machinery of Nextclou. The Default factory is unfortunately labbeled
 * "final", so we cannot inherit from it and just wrap it.
 */
class RepositoryFactory implements RepositoryFactoryInterface
{
  use \OCA\CAFEVDB\Toolkit\Traits\LoggerTrait;

  /**
   * The list of EntityRepository instances.
   *
   * @var ObjectRepository[]
   * @phpstan-var array<string, EntityRepository>
   */
  private array $repositoryList = [];

  /** {@inheritdoc} */
  public function __construct(
    protected IAppContainer $appContainer,
    protected LoggerInterface $logger,
  ) {
  }

  /** {@inheritdoc} */
  public function getRepository(EntityManagerInterface $entityManager, string $entityName): EntityRepository
  {
    $repositoryHash = $entityManager->getClassMetadata($entityName)->getName() . spl_object_id($entityManager);

    return $this->repositoryList[$repositoryHash] ??= $this->createRepository($entityManager, $entityName);
  }

  /**
   * Create a new repository instance for an entity class.
   *
   * @param EntityManagerInterface $entityManager The EntityManager instance.
   * @param string                 $entityName    The name of the entity.
   */
  private function createRepository(
    EntityManagerInterface $entityManager,
    string $entityName,
  ): EntityRepository {
    $decoratedEntityManager = $this->appContainer->get(DecoratedEntityManager::class);
    if ($decoratedEntityManager->getWrappedObject() === $entityManager) {
      $entityManager = $decoratedEntityManager;
    }

    $metadata            = $entityManager->getClassMetadata($entityName);
    $repositoryClassName = $metadata->customRepositoryClassName
      ?: $entityManager->getConfiguration()->getDefaultRepositoryClassName();

    if ($entityManager === $decoratedEntityManager) {
      if (is_a($repositoryClassName, DerivedEntityRepository::class, true)) {
        return new $repositoryClassName($entityManager, $metadata, $this->appContainer);
      }
      $metadata = $metadata->getWrappedObject();
    }
    return new $repositoryClassName($entityManager, $metadata);
  }
}
