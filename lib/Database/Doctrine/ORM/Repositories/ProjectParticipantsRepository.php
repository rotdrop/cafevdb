<?php
/* Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2020, 2021 Claus-Justus Heine <himself@claus-justus-heine.de>
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
use OCA\CAFEVBD\Common\Util;

use Doctrine\ORM\Query;
use Doctrine\ORM\EntityRepository;

class ProjectParticipantsRepository extends EntityRepository
{
  use \OCA\CAFEVDB\Database\Doctrine\ORM\Traits\LogTrait;

  /**
   * findBy() which optionally allows where and orderBy with the two
   * "principal" associaions "project" and "musician".
   *
   * Syntax:
   * ```
   * findBy([ 'musician.name => 'blah' ], [ 'project.name' => 'DESC' ]);
   * ```
   */
  public function findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
  {
    $orderBy = $orderBy?:[];
    $joinEntities = [];
    foreach ([ 'project', 'musician' ] as $foreignKey) {
      foreach ($criteria as $key => $value) {
        if (preg_match('/^'.$foreignKey.'[.]/', $key)) {
          $joinEntities[$foreignKey] = true;
        }
      }
      foreach ($orderBy as $key => $value) {
        if (preg_match('/^'.$foreignKey.'[.]/', $key)) {
          $joinEntities[$foreignKey] = true;
        }
      }
    }
    if (empty($joinEntities)) {
      return parent::findBy($criteria, $orderBy, $limit, $offset);
    }
    $qb = $this->createQueryBuilder('pp');
    foreach (array_keys($joinEntities) as $foreignKey) {
      $qb->join('pp.'.$foreignKey, $foreignKey);
    }
    $andX = $qb->expr()->andX();
    foreach (array_keys($criteria) as $key) {
      $param = str_replace('.', '_', $key);
      $andX->add($qb->expr()->eq($key, ':'.$param));
    }
    $qb->where($andX);
    foreach ($criteria as $key => $value) {
      $param = str_replace('.', '_', $key);
      $qb->setParameter($param, $value);
    }
    foreach ($orderBy as $key => $dir) {
      $qb->addOrderBy($key, $dir);
    }
    if (!empty($limit)) {
      $qb->setMaxResults($limit);
    }
    if (!empty($offset)) {
      $qb->setFirstResult($offset);
    }
    return $qb->getQuery()->execute();
  }

  /**
   * Find all the participant names of the given project.  Handy for
   * building select options for the web interface.
   *
   * @param int $projectId
   *
   * @return array
   */
  public function fetchParticipantNames($projectId)
  {
    $qb = $this->createQueryBuilder('pp');

    return $qb->leftJoin('pp.musician', 'm', null, null, 'm.id')
              ->leftJoin('pp.project', 'p')
              ->select('m.id as musicianId', 'm.firstName AS firstName', 'm.surName AS surName')
              ->orderBy('m.surName', 'ASC')
              ->addOrderBy('m.firstName', 'ASC')
              ->where($qb->expr()->eq('p.id', ':projectId'))
              ->setParameter('projectId', $projectId)
              ->getQuery()
              ->getResult();
  }

}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
