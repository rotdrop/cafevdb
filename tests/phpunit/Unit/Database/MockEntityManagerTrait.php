<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2025, 2026 Claus-Justus Heine <himself@claus-justus-heine.de>
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

namespace OCA\CAFEVDB\Tests\Unit\Database;

use Throwable;

use OCA\CAFEVDB\Database\Doctrine\ORM\Repositories\EntityRepository;
use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Tests\MockProvider;
use OCA\CAFEVDB\Wrapped\Doctrine\Common\Collections\ArrayCollection;

/** Mock the entity manager. */
trait MockEntityManagerTrait
{
  private EntityManager $entityManager;

  private MockProvider $mockProvider;

  private array $entityRepositories = [];

  private array $entities = [];

  private array $transactionExceptions = [];

  /**
   * Generate a mock for the EntityManager class with limited support for
   * persist, flush, contains.
   *
   * @return EntityManager
   */
  public function getEntityManagerMock(): EntityManager
  {
    $this->entityManager = $this->getMockBuilder(EntityManager::class)
      ->disableOriginalConstructor()
      ->getMock();
    $this->entityManager->method('getWrappedObject')->willReturn($this->entityManager);
    $this->entityManager->method('pushTransactionException')->willReturnCallback(
      function(Throwable $t) {
        $this->transactionExceptions[] = $t;
      });
    $this->entityManager->method('getRepository')->willReturnCallback(
      function(string $className) {
        $repository = $this->entityRepositories[$className] ?? null;
        if ($repository == null) {
          $repository = $this->getMockBuilder(EntityRepository::class)
          ->disableOriginalConstructor()
            ->getMock();
          $this->entityRepositories[$className] = $repository;
        } else {
          return $repository;
        }
        $repository->method('getEntityManager')->willReturn($this->entityManager);
        $repository->expects($this->never())?->method('createQueryBuilder');
        if (method_exists($className, 'getId')) {
          if (!isset($this->entities[$className])) {
            $this->entities[$className] = new ArrayCollection;
          }
          $repository->method('find')->willReturnCallback(
            function(int|array $id) use ($className) {
              $id = is_array($id) ? $id['id'] : $id;
              return $this->entities[$className]->get($id);
            }
          );
        }
        return $repository;
      },
    );
    $this->entityManager->method('persist')->willReturnCallback(
      function(mixed $entity) {
        if (!method_exists($entity, 'getId')) {
          // give up for now
          return;
        }
        $class = get_class($entity);
        if (!isset($this->entities[$class])) {
          $this->entities[$class] = new ArrayCollection;
        }
        $givenId = $entity->getId();
        if ($givenId !== null) {
          $oldEntity = $this->entities[$class]->get($givenId);
          if ($oldEntity) {
            $this->assertEquals($entity, $oldEntity);
            return;
          }
          $this->entities[$class]->set($givenId, $entity);
          return;
        }
        $newId = \max(0, 0, ...$this->entities[$class]->getKeys()) + 1;
        $this->entities[$class]->set($newId, $entity);
      },
    );
    $this->entityManager->method('flush')->willReturnCallback(
      function() {
        foreach ($this->entities as $entities) {
          foreach ($entities as $id => $entity) {
            $entity->setId($id);
          }
        }
      },
    );
    $this->entityManager->method('contains')->willReturnCallback(
      fn(mixed $entity) => !!($this->entities[get_class($entity)] ?? null)?->contains($entity),
    );
    $this->mockProvider = $this->mockProvider ?? MockProvider::create($this);
    $this->mockProvider->registerClassInstance(EntityManager::class, $this->entityManager, global: true);

    return $this->entityManager;
  }
}
