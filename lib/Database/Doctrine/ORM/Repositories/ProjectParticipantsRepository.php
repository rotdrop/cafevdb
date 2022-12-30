<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2020, 2021, 2022 Claus-Justus Heine
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
use OCA\CAFEVBD\Common\Util;

use OCA\CAFEVDB\Wrapped\Doctrine\ORM\Query;

/** Repository for project participants. */
class ProjectParticipantsRepository extends EntityRepository
{
  use \OCA\CAFEVDB\Database\Doctrine\ORM\Traits\LogTrait;

  /**
   * Find all the participant names of the given project.  Handy for
   * building select options for the web interface.
   *
   * @param int $projectId
   *
   * @param null|array $orderBy
   *
   * @return array
   */
  public function fetchParticipantNames(int $projectId, ?array $orderBy = null)
  {
    if (empty($orderBy)) {
      $orderBy = [
        'surName' => 'ASC',
        'firstName' => 'ASC',
      ];
    }
    $qb = $this->createQueryBuilder('pp');

    $qb->leftJoin('pp.musician', 'm', null, null, 'm.id')
      ->leftJoin('pp.project', 'p')
      ->select(
        'm.id as musicianId',
        'm.firstName AS firstName',
        'm.surName AS surName',
        //"COALESCE(m.displayName, CONCAT(m.surName, ', ', COALESCE(m.nickName, m.firstName))) AS displayName",
        "CASE WHEN m.displayName IS NULL OR m.displayName = ''
  THEN
    CONCAT(m.surName, CASE WHEN m.nickName IS NULL OR m.nickName = '' THEN m.firstName ELSE m.nickName END)
  ELSE
    m.displayName
  END
AS displayName",
        // "COALESCE(m.nickName, m.firstName) AS nickName",
        "CASE WHEN m.nickName IS NULL OR m.nickName = '' THEN m.firstName ELSE m.nickName END AS nickName",
      );
    foreach ($orderBy as $field => $dir) {
      $qb->addOrderBy('m.'.$field, $dir);
    }
    return $qb->where($qb->expr()->eq('p.id', ':projectId'))
      ->setParameter('projectId', $projectId)
      ->getQuery()
      ->getResult();
  }
}
