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

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes;
use PHPUnit\Framework\MockObject\MockObject;

use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Database\Doctrine\ORM\Repositories\MusiciansRepository as EntityRepository;

/** Provide a mock for the instruments repository. */
trait MockMusiciansRepositoryTrait
{
  use EntityGeneratorTrait;

  /** @return InstrumentsRepository */
  public function getMusiciansRepositoryMock(): EntityRepository
  {
    $repository = $this->getMockBuilder(EntityRepository::class)
      ->disableOriginalConstructor()
      ->getMock();
    $repository->method('findBy')->willReturnCallback(
      function(array $criteria) {
        // print_r($criteria);
        $projectId = null;
        foreach ($criteria as $index => $criterium) {
          $this->assertEquals(1, count($criterium));
          $key = array_keys($criterium)[0];
          $value = $criterium[$key];
          if ($key == 'projectParticipation.project' || $key == '(&projectParticipation.project') {
            if (($value === null && $this->musician->getProjectParticipation()->count() > 0)
                ||
                ($value !== null
                 &&
                 $this->musician->getProjectParticipation()->forAll(
                   fn(int $id, Entities\ProjectParticipant $participant)
                   =>
                   $participant->getProject()->getId() != $value,
                 ))) {
              return [];
            }
            $projectId = $value;
            unset($criteria[$index]);
            break;
          }
        }

        if ($projectId > 0) {
          foreach ($criteria as $criterium) {
            $this->assertEquals(1, count($criterium));
            $key = array_keys($criterium)[0];
            $value = $criterium[$key];
            if ($key == '!projectParticipation.participationStatus') {
              if (is_array($value)
                    && in_array($this->musician->getProjectParticipation()->get($projectId)->getParticipationStatus(), $value)) {
                return [];
              }
              return [ $this->musician ];
            }
          }
        }
        foreach ($criteria as $criterium) {
          $this->assertEquals(1, count($criterium));
          $key = array_keys($criterium)[0];
          $value = $criterium[$key];
          if ($key == '!projectParticipation.participationStatus') {
            if (is_array($value)
                && in_array($this->musician->getDefaultParticipationStatus(), $value)) {
              return [];
            }
            return [ $this->musician ];
          }
        }

        $ids = $criteria['id'] ?? [];
        if (in_array($this->musician->getId(), $ids)) {
          return [ $this->musician ];
        }
        $pattern = $criteria['displayName'] ?? 'this will not match';
        $pattern = trim($pattern, '%');
        if (str_contains($this->musician->getPublicName(), $pattern)) {
          return [ $this->musician ];
        }
        // do not care about deleted.
        return [];
      }
    );
    $repository->method('find')->willReturnCallback(function(mixed $id) {
      if (is_array($id)) {
        $id = $id['id'];
      }
      $id = (int)$id;
      if ($this->musician->getId() == $id) {
        return $this->musician;
      }
      if (isset($this->entities[Entities\Musician::class])) {
        return $this->entities[Entities\Musician::class]->get($id);
      }
      return null;
    });
    $repository->expects($this->never())->method('createQueryBuilder');

    return $repository;
  }
}
