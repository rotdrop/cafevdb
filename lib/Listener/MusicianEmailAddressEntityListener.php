<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2024 Claus-Justus Heine
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

namespace OCA\CAFEVDB\Listener;

use OCA\CAFEVDB\Wrapped\Doctrine\ORM\Event as ORMEvent;

use Psr\Log\LoggerInterface as ILogger;
use OCAP\IL10N;

use OCA\CAFEVDB\Database\Doctrine\ORM\Entities\MusicianEmailAddress as Entity;
use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Exceptions;
use OCA\CAFEVDB\Service\EmailAddressService;

/**
 * Entity listener for musician email address records
 */
class MusicianEmailAddressEntityListener
{
  use \OCA\CAFEVDB\Toolkit\Traits\LoggerTrait;
  use \OCA\CAFEVDB\Traits\EntityManagerTrait;

  /**
   * @var array
   *
   * Array of email address needing a "pre flush remove" event.
   */
  private $doTriggerPreFlushRemoveEvent = false;

  /**
   * @param ILogger $logger
   *
   * @param EntityManager $entityManager
   *
   * @param EmailAddressService $emailAddressService
   */
  public function __construct(
    protected ILogger $logger,
    protected IL10N $l,
    protected EntityManager $entityManager,
    private EmailAddressService $emailAddressService,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public function preRemove(Entity $entity, ORMEvent\PreRemoveEventArgs $event)
  {
    $address = $entity->getAddress();
    $entity->getMusician()->getEmailAddresses()->remove($address);
    $this->doTriggerPreFlushRemoveEvent[$address] = true;
  }

  /**
   * {@inheritdoc}
   */
  public function preFlush(Entity $entity, ORMEvent\PreFlushEventArgs $event)
  {
    $address = $entity->getAddress();
    if ($this->doTriggerPreFlushRemoveEvent[$address] ?? false) {
      $this->entityManager->dispatchEvent(new Events\PreRemoveMusicianEmail($entity));
      unset($this->doTriggerPreFlushRemoveEvent[$address]);
    }
  }

  /**
   * {@inheritdoc}
   *
   * @throws Exceptions\EnduserNotificationException
   */
  public function prePersist(Entity $entity, ORMEvent\PrePersistEventArgs $event)
  {
    $addressList = $this->emailAddressService->parseAddressString($entity->getAddress());
    if (count($addressList) != 1) {
      throw new Exceptions\EnduserNotificationException(
        $this->l->t('More than one or no email-address: "%s".', $entity->getAddress())
      );
    }
    $entity->setAddress(array_key_first($addressList));
    $entity->getMusician()>getEmailAddresses()->set($entity->getAddress(), $entity);
    $this->entityManager->dispatchEvent(new Events\PrePersistMusicianEmail($entity));
  }

  /**
   * {@inheritdoc}
   */
  public function postRemove(Entity $entity, ORMEvent\PostRemoveEventArgs $event)
  {
    $this->entityManager->dispatchEvent(new Events\PostRemoveMusicianEmail($entity));
  }

  /**
   * {@inheritdoc}
   */
  public function postPersist(Entity $entity, ORMEvent\PostPersistEventArgs $event)
  {
    $this->entityManager->dispatchEvent(new Events\PostPersistMusicianEmail($entity));
  }
}
