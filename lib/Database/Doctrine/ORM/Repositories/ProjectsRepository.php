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
  /**Sort by configured sorting column. */
  public function findAll()
  {
    return $this->findBy([], [
      'year' => 'DESC',
      'name' => 'ASC'
    ]);
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
   * @end code
   *
   * nameByName is used by PME in order to construct select options etc.
   *
   * @TODO Could make this a custom hydrator.
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

}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
