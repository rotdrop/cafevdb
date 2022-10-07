<?php
/* Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2020, 2021 Claus-Justus Heine <himself@claus-justus-heine.de>
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

class InstrumentFamiliesRepository extends EntityRepository
{
  /**
   * Find an instrument-family by its name.
   *
   * @return Entities\InstrumentFamily
   */
  public function findByName(string $name)
  {
    return $this->findOneBy([ 'family' => $name ]);
  }

  /**
   * Sort by configured sorting column and omit disabled entries.
   *
   * @return array<int, Entities\InstrumentFamily>
   */
  public function findAll()
  {
    return $this->findBy(['deleted' => null]);
  }

  /**
   * This is essentially a single value table, just return the values
   * as plain array.
   *
   * @return array<int, string>
   */
  public function values()
  {
    // $values = $this
    //   ->_em
    //   ->createQuery('SELECT if.family FROM '
    //                 .Entities\InstrumentFamily::class
    //                 .' if ORDER BY if.family ASC')
    //   ->getResult('COLUMN_HYDRATOR');

    $values = $this->createQueryBuilder('if')
                   ->select('if.family')
                   ->where('if.deleted IS NULL')
                   ->orderBy('if.family', 'ASC')
                   ->getQuery()
                   ->getResult('COLUMN_HYDRATOR');

    return $values;
  }
}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
