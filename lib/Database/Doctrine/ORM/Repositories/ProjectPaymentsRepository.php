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

use Doctrine\ORM\Query;
use Doctrine\ORM\EntityRepository;

class ProjectPaymentsRepository extends EntityRepository
{
  const ALIAS = 'pay';
  const PARTICIPANT_ALIAS = 'part';

  /**
   * Finds entities by a set of criteria. Criteria may include
   * project-id and musician-id where internally a join-query is
   * performed.
   *
   * @param array      $criteria
   * @param array|null $orderBy
   * @param int|null   $limit
   * @param int|null   $offset
   *
   * @return array The objects.
   */
  public function findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
  {
    if (isset($criteria['projectId']) || issset($citeria['musicianId'])) {
      $qb = $this->createQueryBuilder(self::ALIAS)
                 ->join(self::ALIAS.'.projectParticipant', 'part');
      $andX = $qb->expr()->andX();
      foreach (array_keys($criteria) as $key) {
        $andX->add($qb->expr()->eq($this->fqcn($key), ':'.$key));
      }
      $qb->where($andX);
      foreach ($criteria as $key => $value) {
        $qb->setParameter($key, $value_);
      }
      foreach ($orderBy as $key => $dir) {
        $qb->addOrderBy($this->fqcn($key), $dir);
      }
      if (!empty($limit)) {
        $qb->setMaxResults($limit);
      }
      if (!empty($offset)) {
        $qb->setFirstResult($offset);
      }
      return $qb->getQuery()->execute();
    } else {
      return parent::findBy($criteria, $orderBy, $limit, $offset);
    }
  }

  private function fqcn($column)
  {
    switch ($column) {
    case 'projectId':
    case 'musicianId':
      return self::PARTICIPANT_ALIAS.'.'.$column;
    default:
      return self::ALIAS.'.'.$column;
    }
  }
}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
