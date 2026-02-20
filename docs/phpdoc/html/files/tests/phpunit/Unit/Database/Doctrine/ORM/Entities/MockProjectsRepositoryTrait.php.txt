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

namespace OCA\CAFEVDB\Tests\Unit\Database\Doctrine\ORM\Entities;

use ReflectionClass;
use ReflectionMethod;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes;
use PHPUnit\Framework\MockObject\MockObject;

use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Database\Doctrine\ORM\Repositories\ProjectsRepository as EntityRepository;
use OCA\CAFEVDB\Tests\Unit\Database;
use OCA\CAFEVDB\Wrapped\Doctrine\Common\Collections\ArrayCollection;

/** Provide a mock for the ProjectsEntityRepository class. */
trait MockProjectsRepositoryTrait
{
  use Database\MockEntityManagerTrait;
  use EntityGeneratorTrait;

  /** @return EntityRepository */
  public function getProjectsRepositoryMock(): EntityRepository
  {
    if (!($this->project ?? null)) {
      $this->generateProjectParticipant(persist: false);
    }

    $this->entities[Entities\Project::class] = new ArrayCollection;
    $this->entities[Entities\Project::class]->set($this->project->getId(), $this->project);

    $allMethods = array_map(
      fn(ReflectionMethod $method) => $method->getName(),
      new ReflectionClass(EntityRepository::class)->getMethods(),
    );
    $wantedMethods = array_diff($allMethods, [
      'ensureProject',
      'findAll',
      'findById',
      'findByIdOrName',
      'findOneBy',
    ]);
    $repository = $this->getMockBuilder(EntityRepository::class)
      ->disableOriginalConstructor()
      ->onlyMethods($wantedMethods)
      ->getMock();
    $repository->method('findNames')->willReturn([]);
    $repository->method('find')->willReturnCallback(function(mixed $id) {
      if (is_array($id)) {
        $id = $id['id'];
      }
      $projectId = (int)$id;
      if ($this->project->getId() == $projectId) {
        return $this->project;
      }
      if (isset($this->entities[Entities\Project::class])) {
        return $this->entities[Entities\Project::class]->get($projectId);
      }
      return null;
    });
    $repository->method('findBy')->willReturnCallback(
      function(array $criteria, ?array $orderBy = null, ?int $limit = null, ?int $offset = null) {
        foreach ($criteria as $criterium) {
          $this->assertTrue(is_array($criterium));
          $this->assertEquals(1, count($criterium));
          $this->assertTrue(ctype_alpha(array_keys($criterium)[0]));
          $field = array_keys($criterium)[0];
          $method = 'get' . ucfirst($field);
          $this->assertTrue(method_exists(Entities\Project::class, $method));
        }
        $allEntities = ($this->entities[Entities\Project::class] ?? null)?->toArray() ?? [];
        $allEntities[$this->project->getId()] = $this->project;
        $entities = array_filter(
          $allEntities,
          function(Entities\Project $entity) use ($criteria) {
            foreach ($criteria as $criterium) {
              $field = array_keys($criterium)[0];
              $value = array_values($criterium)[0];
              $method = 'get' . ucfirst($field);
              if ($entity->$method() != $value) {
                return false;
              }
            }
            return true;
          },
        );
        if (!empty($orderBy)) {
          usort($entities, function(Entities\Project $a, Entities\Project $b) use ($orderBy) {
            $result = 0;
            foreach ($orderBy as $field => $direction) {
              $method = 'get' . ucfirst($field);
              $result = $a->$method() <=> $b->$method();
              if ($direction == 'DESC') {
                $result = -$result;
              }
              if ($result) {
                break;
              }
            }
            return $result;
          });
        }
        return array_slice($entities, $offset ?? 0, $limit);
      },
    );
    $repository->method('getEntityManager')->willReturn($this->entityManager);
    $repository->expects($this->never())->method('createQueryBuilder');
    $this->entityRepositories[Entities\Project::class] = $repository;

    return $repository;
  }
}
