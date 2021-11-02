<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2021 Claus-Justus Heine <himself@claus-justus-heine.de>
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

namespace OCA\CAFEVDB\Common;

use OCP\IDBConnection;
use OCP\AppFramework\Db\Entity;
use OCP\AppFramework\Db;
use OCP\ILogger;
use OCP\IL10N;
use OCP\AppFramework\Db\DoesNotExistException;

use OCA\CAFEVDB\Database\Cloud\Mapper;
use OCA\CAFEVDB\Database\Cloud\Entities;
use OCA\CAFEVDB\Common\IProgressStatus;
use OCA\CAFEVDB\Exceptions;

class DatabaseProgressStatus extends AbstractProgressStatus
{
  use \OCA\CAFEVDB\Traits\LoggerTrait;

  const ENTITY_NAME = Entities\ProgressStatus::class;

  /** @var Mapper\ProgressStatusMapper */
  protected $mapper;

  /** @var Entities\ProgressStatus */
  protected $entity;

  /**
   * Find the given or create a new Entities\ProgressStatus entity.
   */
  public function __construct(
    $appName
    , ILogger $logger
    , IL10N $l10n
    , IDBConnection $db
  ) {

    $this->logger = $logger;
    $this->l = $l10n;
    $this->mapper = new Mapper\ProgressStatusMapper($db, $appName);
  }

  /** @{inheritdoc} */
  public function delete()
  {
    if (!empty($this->entity)) {
      $this->mapper->delete($this->entity);
      $this->entity = null;
    }
  }

  /** @{inheritdoc} */
  public function bind($id)
  {
    if (!empty($this->entity) && $this->entity->getId() == $id) {
      $this->sync();
      return;
    }

    if (!empty($this->entity)) {
      try {
        $this->mapper->delete($this->entity);
      } catch (\Throwable $t) {
        $this->logException($t);
      }
      $this->entity = null;
    }

    if (!empty($id)) {
      try {
        $this->entity = $this->mapper->find($id);
      } catch (\Throwable $t) {
        // $this->logException($t);
        throw (new Exceptions\ProgressStatusNotFoundException(
          $this->l->t('Unable to find progress status for job id "%s"', $id),
          $t->getCode(),
          $t))->setId($i);
      }
    } else {
      $this->entity = new Entities\ProgressStatus;
      $this->entity->setCurrent(0);
      $this->entity->setTarget(0);
      $this->entity->setData(null);
      $this->mapper->insert($this->entity);
    }
  }

  /** @{inheritdoc} */
  public function getId()
  {
    return $this->entity->getId();
  }

  /** @{inheritdoc} */
  public function update(int $current, ?int $target = null, ?array $data = null)
  {
    $this->entity->setCurrent($current);
    if (!empty($target)) {
      $this->entity->setTarget($target);
    }
    if (!empty($data)) {
      $this->entity->setData(json_encode($data));
    }
    $this->mapper->update($this->entity);
  }

  /** @{inheritdoc} */
  public function sync()
  {
    $this->entity = $this->mapper->find($this->entity->getId());
  }

  /** @{inheritdoc} */
  public function getCurrent():int
  {
    return $this->entity->getCurrent();
  }

  /** @{inheritdoc} */
  public function getTarget():int
  {
    return $this->entity->getTarget();
  }

  /** @{inheritdoc} */
  public function getLastModified():\DateTimeinterface
  {
    return (new \DateTimeImmutable)->setTimestamp($this->entity()->getLastModified());
  }

  /** @{inheritdoc} */
  public function getData():?array
  {
    $dbData = $this->entity->getData();
    return empty($dbData) ? $dbData : json_decode($dbData, true);
  }

}
