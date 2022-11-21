<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2011-2014, 2016, 2020, 2021, 2022 Claus-Justus Heine
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

namespace OCA\CAFEVDB\Events;

use \DateTimeInterface;

use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;

use OCP\EventDispatcher\Event;

/**
 * Event fired by the Musician entity through a life-cycle hook after the
 * bulkTransaction address is changed.
 *
 * @SuppressWarnings(PHPMD.LongClassName)
 */
class PostChangeCompositePaymentNotificationMessage extends Event
{
  /** @var EntityManager */
  private $entityManager;

  /** @var Entities\CompositePayment */
  private $entity;

  /** @var Entities\SentEmail */
  private $oldValue;

  /**
   * @param EntityManager $entityManager Decorated ORM entity manager.
   *
   * @param Entities\CompositePayment $entity Current entity with updated values.
   *
   * @param null|string $oldValue The old pre-update value.
   */
  public function __construct(
    EntityManager $entityManager,
    Entities\CompositePayment $entity,
    ?Entities\SentEmail $oldValue,
  ) {
    $this->entityManager = $entityManager;
    $this->entity = $entity;
    $this->oldValue = $oldValue;
  }

  /**
   * Getter for the entity-manager.
   *
   * @return EntityManager
   */
  public function getEntityManager():EntityManager
  {
    return $this->entityManager;
  }

  /**
   * Getter for the entity.
   *
   * @return Entities\SepaTransaction
   */
  public function getEntity():Entities\CompositePayment
  {
    return $this->entity;
  }

  /**
   * Getter for the old value.
   *
   * @return null|string
   */
  public function getOldValue():?Entities\SentEmail
  {
    return $this->oldValue;
  }

  /**
   * Getter for the new value.
   *
   * @return null|string
   */
  public function getNewValue():?Entities\SentEmail
  {
    return $this->entity->getNotificationMessage();
  }
}
