<?php
/* Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2020, 2021, 2022 Claus-Justus Heine <himself@claus-justus-heine.de>
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

use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;

class ProjectsRepository extends EntityRepository
{
  // use \OCA\CAFEVDB\Database\Doctrine\ORM\Traits\LogTrait;

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
    // $this->log(print_r($projectOrId, true));
    if (isset($projectOrId['id'])) { // allow plain array with id
      $projectId = $projectOrId['id'];
    } else {
      $projectId = $projectOrId;
    }
    return $this->findOneBy([
      'id' => $projectId,
      'deleted' => null,
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
  public function ensureProject($projectOrId):?Entities\Project
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
   * Fetch a flat array of mailing list ids associated with the matching projects
   */
  public function fetchMailingListIds(array $criteria = [])
  {
    $criteria['!mailingListId'] = null;
    $queryParts = $this->prepareFindBy($criteria, [
      'mailingListId' => 'ASC',
    ]);

    /** @var ORM\QueryBuilder */
    $qb = $this->generateFindBySelect($queryParts, [ 'mainTable.mailingListId' ]);
    $qb = $this->generateFindByWhere($qb, $queryParts);

    $query = $qb->getQuery();

    return $query->getResult('COLUMN_HYDRATOR');
  }
}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
