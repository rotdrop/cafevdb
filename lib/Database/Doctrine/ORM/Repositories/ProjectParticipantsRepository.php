<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2020, 2021, 2022, 2025 Claus-Justus Heine
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
   * @param bool $includeDeleted
   *
   * @param mixed $onlyStatus
   *
   * @param mixed $excludeStatus
   *
   * @return array
   */
  public function fetchParticipantNames(
    int $projectId,
    ?array $orderBy = null,
    bool $includeDeleted = false,
    mixed $onlyStatus = null,
    mixed $excludeStatus = null,
  ):array {
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
      $qb->addOrderBy($field, $dir);
    }
    $qb->where($qb->expr()->eq('p.id', ':projectId'));
    if (!$includeDeleted) {
      $qb->andWhere($qb->expr()->isNull('pp.deleted'));
    }
    if ($onlyStatus !== null) {
      if (!is_array($onlyStatus)) {
        $onlyStatus = [$onlyStatus];
      }
      $qb->andWhere($qb->expr()->in('pp.participationStatus', array_map(fn(mixed $any) => (string)$any, $onlyStatus)));
    }
    if ($excludeStatus !== null) {
      if (!is_array($excludeStatus)) {
        $excludeStatus = [$excludeStatus];
      }
      $qb->andWhere($qb->expr()->notIn('pp.participationStatus', array_map(fn(mixed $any) => (string)$any, $excludeStatus)));
    }
    return $qb
      ->setParameter('projectId', $projectId)
      ->getQuery()
      ->getResult();
  }
}
