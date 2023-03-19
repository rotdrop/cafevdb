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

namespace OCA\CAFEVDB\Database\Cloud;

use BadFunctionCallException;

use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db;
use Psr\Log\LoggerInterface as ILogger;

/**
 * A synchronized is a tuple of an entity and a mapper which writes
 * all data straight through to the database.
 */
abstract class Synchronized extends Db\QBMapper
{
  use \OCA\CAFEVDB\Database\Cloud\Traits\EntityTableNameTrait;

  /** @var bool */
  protected $attached;

  /** @var Entity */
  protected $entity;

  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct(
    IDBConnection $db,
    string $appName,
    int $id = null,
    string $entityClass = null,
    string $tableName = null,
  ) {
    if ($entityClass === null) {
      $entityClass = $this->makeEntityClass();
    }
    if ($tableName === null) {
      $tableName = $this->makeTableName($appName, $entityClass);
    }

    parent::__construct($db, $tableName, $entityClass);

    $this->entity = new $entityClass();
    $this->entity->setId($id);

    $this->attach();
  }
  // phpcs:enable

  /** @return bool */
  public function isAttached():bool
  {
    return $this->attached;
  }

  /** @return mixed */
  public function entity()
  {
    return $this->entity;
  }

  /**
   * Obtain object from database if it is "clean", if it is dirty or
   * new (no id) then create or update the database entry.
   *
   * @return void
   */
  public function attach()
  {
    if ($this->entity->getId() !== null) {
      try {
        $this->find($this->entity->getId()); // will throw if not found
        $this->entity = parent::update($this->entity);
      } catch (DoesNotExistException $e) {
        $this->entity = $this->insert($this->entity);
      }
    } else {
      $this->entity = $this->insert($this->entity);
    }
    $this->attached = true;
  }

  /**
   * Remove the wrapped entity from the database.
   *
   * @return void
   */
  public function detach()
  {
    $this->delete($this->entity);
    $this->entity->setId(null);
    $this->entity->resetUpdatedFields();
    $this->attached = false;
  }

  /**
   * Update multiple properties and write them through to the database if we
   * are in $attached state. If called with no arguments read the entity back
   * from the database, possibly attaching it first.
   *
   * @param array $params
   *
   * @return void
   */
  public function merge(array $params = [])
  {
    if (!empty($params)) { // write
      foreach ($params as $key => $value) {
        $method = 'set'.(ucfirst($key));
        $this->entity->$method($value);
      }
      if ($this->attached) {
        $this->entity = parent::update($this->entity);
      }
    } else {
      $this->entity->resetUpdatedFields();
      $this->attach();
    }
  }

  /** {@inheritdoc} */
  public function __call($methodName, $args)
  {
    if (strpos($methodName, 'set') === 0) {
      try {
        $this->entity->$methodName($args);
        if ($this->attached) {
          $this->entity = parent::update($this->entity);
        }
      } catch (\Throwable $t) {
        throw $t;
      }
    } elseif (strpos($methodName, 'get') === 0) {
      if ($this->attached) {
        $this->entity = $this->find($this->entity->getId());
      }
      return $this->entity->$methodName();
    } else {
      throw new BadFunctionCallException($methodName . ' does not exist');
    }
  }

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
}
