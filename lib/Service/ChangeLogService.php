<?php
/* Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2014, 2016, 2020 Claus-Justus Heine <himself@claus-justus-heine.de>
 *
 * This library is free software; you can redistribute it and/or
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

namespace OCA\CAFEVDB\Service;

use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities\ChangeLog;

/**
 * Explicitly write to the phpMyEdit changelog in its private format.
 */
class ChangeLogService
{
  use \OCA\CAFEVDB\Traits\ConfigTrait;
  use \OCA\CAFEVDB\Traits\EntityManagerTrait;

  /** @var RequestParameterService */
  private $requestParameters;

  /** @var EntityManager */
  protected $entityManager;

  /** @var bool Flush the entries to the db */
  private $autoFlush;

  /** @var bool Flush the entries to the db */
  private $enabled;

  public function __construct(
    ConfigService $configService
    , EntityManager $entityManager
    , RequestParameterService $requestParameters
  ) {
    $this->configService = $configService;
    $this->entityManager = $entityManager;
    $this->requestParameters = $requestParameters;
    $this->setDatabaseRepository(Entities\Changelog::class);
    $this->autoFlush = false;
    $this->enabled = true;
  }

  public function setAutoFlush(bool $onOff)
  {
    $this->autoFlush = $onOff;
  }

  public function disable()
  {
    $this->enabled = false;
  }

  public function enable()
  {
    $this->enabled = true;
  }

  /**
   * Insert an "insert" entry into the changelog table. The function
   * will log the inserted data, the user name and the remote IP
   * address.
   *
   * @param $table The affected SQL table.
   *
   * @param $recId The row-key.
   *
   * @param $newValues An associative array where the keys are
   * the column names and the values are the respective values to be
   * inserted.
   *
   * @param $handle Data-base connection, as returned by
   * self::open(). If null, then a new connection is opened.
   *
   * @param $changeLog The name of the change-log table.
   *
   * @return true
   *
   */
  public function logInsert($table, $recId, $newValues)
  {
    if (!$this->enabled || empty($newValues)) {
      return;
    }

    if (is_array($recId)) {
      $recId = implode(',', $recIdColumn);
    }
    $this->logPersist($this->logCreate()
                           ->setOperation('insert')
                           ->setTab($table)
                           ->setRowkey($recId)
                           ->setNewval(serialize($newValues)));
  }

  /**
   * Insert a "delete" entry into the changelog table. The function
   * will log the inserted data, the user name and the remote IP
   * address.
   *
   * @param $table The affected SQL table.
   *
   * @param $recIdColumn The column name of the row-key.
   *
   * @param $oldValues An associative array where the keys are
   * the column names and the values are the respective old values
   * which will be removed. $oldValues[$recIdColumn] should be the
   * respective row-key which has been removed.
   *
   * @param $handle Data-base connection, as returned by
   * self::open(). If null, then a new connection is opened.
   *
   * @param $changeLog The name of the change-log table.
   *
   * @return true
   *
   */
  public function logDelete($table, $recIdColumn, $oldValues)
  {
    if (!$this->enabled || empty($oldValues)) {
      return;
    }

    if (is_array($recIdColumn)) {
      $recId = implode(',', $recIdColumn);
    } else {
      $recId = $oldValues[$recIdColumn];
    }
    $this->logPersist($this->logCreate()
                           ->setOperation('delete')
                           ->setTab($table)
                           ->setRowkey($recId)
                           ->setCol($recIdColumn)
                           ->setOldval(serialize($oldValues)));
  }

  /**
   * Insert an "update" entry into the changelog table. The function
   * will log the inserted data, the user name and the remote IP
   * address.
   *
   * @param $table The affected SQL table.
   *
   * @param $recIdColumn The column name of the row-key.
   *
   * @param $oldValues An associative array where the keys are
   * the column names and the values are the respective old values
   * which will be removed. $oldValues[$recIdColumn] should be the
   * respective row-key for the affected row.
   *
   * @param $newValues An associative array where the keys are
   * the column names and the values are the respective new values
   * which were injected into the table. The change-log entry will
   * only record changed values.
   *
   * @return true
   *
   */
  public function logUpdate($table, $recIdColumn, $oldValues, $newValues)
  {
    if (!$this->enabled || empty($newValues)) {
      return;
    }

    $changed = [];
    foreach($newValues as $col => $value) {
      if ($value instanceof \DateTime) {
        $value = $value->format('Y-m-d H:i:s');
      }
      if (isset($oldValues[$col]) && $oldValues[$col] == $value) {
        continue;
      }
      $changed[$col] = $value;
    }

    if (count($changed) == 0) {
      return true; // nothing changed
    }

    if (is_array($recIdColumn)) {
      $recId = implode(',', $recIdColumn);
    } else {
      $recId = $oldValues[$recIdColumn];
    }

    // log the result, but ignore any errors generated by the log query
    try {
      foreach ($changed as $key => $value) {
        $oldValue = isset($oldValues[$key]) ? $oldValues[$key] : '';
        $logEntity = $this->logCreate()
                          ->setOperation('update')
                          ->setTab($table)
                          ->setRowkey($recId)
                          ->setCol($key)
                          ->setOldval($oldValue)
                          ->setNewval($value);
        $this->persist($logEntity);
      }
      if ($this->autoFlush) {
        $this->flush();
      }
    } catch (\Throwable $t) {
      $this->logException($t); // but do not care otherwise
    }
  }

  private function logCreate()
  {
    return Changelog::create()
      ->setUpdated()
      ->setUser($this->userId())
      ->setHost($this->requestParameters->server['REMOTE_ADDR']);
  }

  private function logPersist(Changelog $logEntity)
  {
    try {
      $this->persist($logEntity);
      if ($this->autoFlush) {
        $this->flush($logEntity);
      }
    } catch (\Throwable $t) {
      $this->logException($t); // but do not care otherwise
    }
  }
}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
