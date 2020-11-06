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

class InstrumentFamiliesRepository extends EntityRepository
{
  /**Find an instrument-family by its name.*/
  public function findByName(string $name)
  {
    return $this->findOneBy([ 'family' => $name ]);
  }

  /**Sort by configured sorting column and omit disabled entries.
   */
  public function findAll()
  {
    return $this->findBy(['disabled' => false]);
  }

  /**This is essentially a single value table, just return the values as plain
   * array.
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
                   ->where('if.disabled = :disabled')
                   ->orderBy('if.family', 'ASC')
                   ->setParameter('disabled', false)
                   ->getQuery()
                   ->getResult('COLUMN_HYDRATOR');

    return $values;
  }
}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
