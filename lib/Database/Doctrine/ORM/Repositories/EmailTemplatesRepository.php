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

/**
 * @todo The sole purpose of this repository is to map tag ->
 * name. Otherwise we just could use the stock "findAll()".
 */
class EmailTemplatesRepository extends EntityRepository
{
  /** @return array */
  public function list()
  {
    return $this->createQueryBuilder('et')
      ->select([
        'et.id AS id',
        'et.tag AS name',
        'et.updated AS updated',
        'et.created AS created',
        'et.updatedBy as updatedBy',
        'et.createdBy as createdBy',
      ])
      ->orderBy('et.tag', 'ASC')
      ->addOrderBy('et.updated', 'DESC')
      ->getQuery()
      ->execute();
  }
}
