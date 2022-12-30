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

namespace OCA\CAFEVDB\Database\Cloud\Mapper;

use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\AppFramework\Db\QBMapper;

/** Common mapper base class. */
abstract class Mapper extends QBMapper
{
  use \OCA\CAFEVDB\Database\Cloud\Traits\EntityTableNameTrait;

  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct(IDBConnection $db, $appName, string $entityClass = null, string $tableName = null)
  {
    if ($entityClass === null) {
      $entityClass = $this->makeEntityClass();
    }
    if ($tableName === null) {
      $tableName = $this->makeTableName($appName, $entityClass);
    }
    parent::__construct($db, $tableName, $entityClass);
  }
  // phpcs:enable

  /**
   * @param int $id
   *
   * @return mixed
   *
   * @throws \OCP\AppFramework\Db\DoesNotExistException If not found.
   * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException If more than one result.
   */
  public function find(int $id)
  {
    $qb = $this->db->getQueryBuilder();

    $qb->select('*')
       ->from($this->tableName)
       ->where(
         $qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT))
       );

    return $this->findEntity($qb);
  }

  /**
   * @param null|int $limit
   *
   * @param null|int $offset
   *
   * @return array
   *
   * @todo Maybe unused.
   */
  public function findAll($limit = null, $offset = null)
  {
    $qb = $this->db->getQueryBuilder();

    $qb->select('*')
       ->from($this->tableName)
       ->setMaxResults($limit)
       ->setFirstResult($offset);

    return $this->findEntities($qb);
  }
}
