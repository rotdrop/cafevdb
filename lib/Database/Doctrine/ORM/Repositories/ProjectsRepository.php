<?php
/* Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2020 Claus-Justus Heine <himself@claus-justus-heine.de>
 *
 * This library se Doctrine\ORM\Tools\Setup;is free software; you can redistribute it and/or
 * modify it under the terms of the GNU GENERAL PUBLIC LICENSE
 * License as published by the Free Software Foundation; either
 * version 3 of the License, or any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU AFFERO GENERAL PUBLIC LICENSE for more details.
 *
 * You should have received a copy of the GNU Lesser General Public
 * License along with this library.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace OCA\CAFEVDB\Database\Doctrine\ORM\Repositories;

use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;

use Doctrine\ORM\EntityRepository;

class ProjectsRepository extends EntityRepository
{
  const ALIAS = 'proj';

  /**Sort by configured sorting column. */
  public function findAll()
  {
    return $this->findBy([], [
      'year' => 'DESC',
      'name' => 'ASC'
    ]);
  }

  /**
   * Find a project by its Id.
   *
   * @param array|int $projectOrId This may either be an integer --
   * the plain id -- or "something" array-like with an 'id' index.
   *
   * @return null|Entities\Project
   */
  public function findById($projectOrId):?Entities\Project
  {
    if (!empty($projectOrId['id'])) { // allow plain array with id
      $projectId = $projectId['id'];
    } else {
      $projectId = $projectOrId;
    }
    return $this->findOneBy([
      'id' => $projectId,
      'disabled' => false,
    ]);
  }

  /**
   * Convenience function: just return the argument if it is already a
   * project entity, otherwise fetch the project, repectively generate
   * a reference.
   *
   * @param int|Entities\Project $projectOrId
   *
   * @return null|Entities\Project
   */
  public function ensureProject($projectOrId):? Entities\Project
  {
    if (!($projectOrId instanceof Entities\Project)) {
      //return $this->entityManager->getReference(Entities\Project::class, [ 'id' => $projectOrId, ]);
      return $this->findById($projectOrId);
    } else {
      return $projectOrId;
    }
  }

  /**
   * Fetch a short description for all projects.
   *
   * @return array
   *
   * @code
   * [
   *   'projects' => [ ID => [ 'name' => NAME, 'year' => YEAR ], ... ],
   *   'nameByName' => [ NAME => NAME, ... ],
   *   'yearByName' => [ NAME => YEAR, ... ],
   * ]
   * @endcode
   *
   * nameByName is used by PME in order to construct select options etc.
   *
   * @todo Could make this a custom hydrator.
   */
  public function shortDescription()
  {
    $byId = []; $nameByName = []; $yearByName = [];
    foreach ($this->findAll() as $entity) {
      $name = $entity['Name'];
      $year = $entity['Year'];
      $byId[$entity['Id']] = [ 'name' => $name, 'year' => $year, ];
      $nameByName[$name] = $name;
      $yearByName[$name] = $year;
    }
    return [
      'projects' => $byId,
      'nameByName' => $nameByName,
      'yearByName' => $yearByName,
    ];
  }

  /**
   * Return minimum and maximum year of all projects.
   *
   * @return array<string, int>
   *
   * ```php
   * [
   *   'min' => MIN_YEAR,
   *   'max' => MAX_YEAR,
   * ]
   * ```
   */
  public function findYearRange()
  {
    $range = $this->createQueryBuilder('p')
           ->select('MIN(p.year) AS min, MAX(p.year) AS max')
           ->getQuery()
           ->getResult();
    return $range[0]; // ????
  }

  /**
   * Disable the given entity by settings its "disable" flag.
   *
   * @param mixed $entityOrId The entity or entity id.
   *
   * @param bool $disable Whether to enable or disable
   */
  public function disable($entityOrId, bool $disable = true)
  {
    $entityManager = $this->getEntityManager();
    if ($entityOrId instanceof Entities\Project) {
      $entity = $entityOrId;
      $entity->setDisabled($disabled);
      $getEntityManager()->flush();
    } else {
      $entityId = $entityOrId;
      $qb = $entitiyManager->createQueryBuilder()
                           ->update($this->getEntityName(), self::ALIAS)
                           ->set(self::ALIAS.'.disabled', true)
                           ->where(self::ALIAS.'.id = :entityId')
                           ->setParameter('entityId', $fieldId);
      $qb->getQuery()->execute();
    }
  }

  public function enable(bool $enable = true)
  {
    return $this->disabled(!$enable);
  }
}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
