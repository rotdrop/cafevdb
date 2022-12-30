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

use OCA\CAFEVDB\Wrapped\Doctrine\ORM\Query;

/** Repository for extra fields data. */
class ProjectParticipantFieldDataRepository extends EntityRepository
{
  const ALIAS = 'pexfd';

  /**
   * Fetch all values stored for the given participant-field, e.g. in order
   * to recover or generate select boxes.
   *
   * @param int|Entities\ProjectParticipantField $field
   *
   * @return mixed
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
