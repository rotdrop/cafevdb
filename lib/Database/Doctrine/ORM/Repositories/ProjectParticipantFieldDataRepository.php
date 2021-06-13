<?php
/**
 * Orchestra member, musician and project management application.
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

use OCA\CAFEVDB\Wrapped\Doctrine\ORM\Query;

class ProjectParticipantFieldDataRepository extends EntityRepository
{
  const ALIAS = 'pexfd';

  /**
   * Fetch all ids which already have data associated to it.
   *
   * @param int|Entities\Project $project Project id or entity
   *
   * @param int|Entities\ProjectParticipantfield $field Project participant-field
   * entity or id.
   *
   * @return array Flat array of id's
   */
  public function usedFields($project = null, $field = null)
  {

  //     $query = "SELECT DISTINCT d.`FieldId`
  // FROM `".self::DATA_TABLE."` d
  // LEFT JOIN `Besetzungen` b
  // ON d.`BesetzungenId` = b.`Id`
  // WHERE
  //   d.`FieldValue` > ''";
  //     if ($projectId > 0) {
  //       $query .= "AND b.`ProjektId` = ".$projectId;
  //     }
  //     if ($fieldId > 0) {
  //       $query .= "AND d.`FieldId` = ".$fieldId;
  //     }

    $qb = $this->createQueryBuilder(self::ALIAS)
               ->select('identity('.self::ALIAS.'.field)')
               ->leftJoin(self::ALIAS.'.projectParticipant', 'p')
               ->where(self::ALIAS.".fieldValue > ''");
    if ($projectId > 0) {
      $qb->andWhere('p.project = :project')
         ->setParameter('project', $project);
    }
    if (!empty($filed)) {
      $qb->andWhere(self::ALIAS.'.field = :field')
         ->setParameter('field', $field);
    }
    return $qb->distinct()
              ->getQuery()
              ->getResult('COLUMN_HYDRATOR');
  }


  /**
   * Fetch all values stored for the given participant-field, e.g. in order
   * to recover or generate select boxes.
   *
   * @param int|Entities\ProjectParticipantField
   */
  public function fieldValues($field)
  {
    $qb = $this->createQueryBuilder(self::ALIAS)
               ->select(self::ALIAS.'.optionValue')
               ->where(self::ALIAS.'.field = :field')
               ->setParameter('field', $field);
    return $qb->getQuery()->getResult('COLUMN_HYDRATOR');
  }

  /**
   * Fetch all values stored for the given participant-field, e.g. in order
   * to recover or generate select boxes.
   *
   * @param int|Entities\ProjectParticipantField
   */
  public function optionKeys($field)
  {
    $qb = $this->createQueryBuilder(self::ALIAS)
               ->select(self::ALIAS.'.optionKey')
               ->where(self::ALIAS.'.field = :field')
               ->setParameter('field', $field);
    return $qb->getQuery()->getResult('COLUMN_HYDRATOR');
  }

}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
