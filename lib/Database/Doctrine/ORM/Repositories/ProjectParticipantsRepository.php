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
  use \OCA\CAFEVDB\Database\Doctrine\ORM\Traits\FindLikeTrait;
  use \OCA\CAFEVDB\Database\Doctrine\ORM\Traits\LogTrait;

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
