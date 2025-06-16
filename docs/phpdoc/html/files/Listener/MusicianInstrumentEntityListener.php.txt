<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2024, 2025 Claus-Justus Heine
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

use OCP\AppFramework\IAppContainer;
use OCP\IAddressBook;
use OCP\IL10N;
use Psr\Log\LoggerInterface as ILogger;

use OCA\CAFEVDB\Database\Doctrine\ORM\Entities\MusicianInstrument as Entity;
use OCA\CAFEVDB\Service\ContactsService;
use OCA\CAFEVDB\Wrapped\Doctrine\ORM\Mapping as ORM;
use OCA\CAFEVDB\Wrapped\Doctrine\Persistence\Event\LifecycleEventArgs;

/**
 * Addressbook integration: the instrument name is also a category in the
 * addressbook entry.
 */
class MusicianInstrumentEntityListener
{
  use \OCA\CAFEVDB\Toolkit\Traits\LoggerTrait;

  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct(
    protected ILogger $logger,
    protected IAppContainer $appContainer,
  ) {
  }
  // phpcs:enable

  /** {@inheritdoc} */
  #[ORM\PrePersist]
  #[ORM\PreRemove]
  #[ORM\PreUpdate]
  public function synchronizeContact(Entity $entity, LifecycleEventArgs $eventArgs)
  {
    $musician = $entity->getMusician();
    if (empty($musician)) {
      $this->logError('Musician is NULL.');
      return;
    }
    if (empty($musician->getAddressBookUri())) {
      return;
    }
    /** @var ContactsService $contactsService */
    $contactsService = $this->appContainer->get(ContactsService::class);
    if (!$contactsService->registerContactSynchronization($musician)) {
      $this->logError('Contacts-synchronization could not be registered for ' . $musician->getPublicName());
    }
  }
}
