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

use OCA\CAFEVDB\Wrapped\Doctrine\ORM\Event as ORMEvent;

use OCP\IL10N;
use Psr\Log\LoggerInterface as ILogger;
use OCP\AppFramework\IAppContainer;
use OCP\IAddressBook;

use OCA\CAFEVDB\Database\Doctrine\DBAL\Types\EnumParticipationStatus as ParticipationStatus;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities\MusicianInstrument as Entity;
use OCA\CAFEVDB\Database\Doctrine\Util as DBUtil;
use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Common\Uuid;
use OCA\CAFEVDB\Service\CardDavService;

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
    protected EntityManager $entityManager,
  ) {
  }
  // phpcs:enable

  /**
   * {@inheritdoc}
   *
   * Inject the instrument name as vCard category if there is a link to an address-book.
   */
  public function postPersist($entity, ORMEvent\PostPersistEventArgs $event)
  {
    $musician = $entity->getMusician();
    $addressBookUri = $musician->getAddressBookUri();
    if ($addressBookUri == null) {
      return;
    }
    $contact = $this->findContact($addressBookUri, $musician->getUuid());
  }

  /**
   * {@inheritdoc}
   *
   * Possibly adjust the participation status.
   */
  public function postRemove($entity, ORMEvent\PreRemoveEventArgs $event)
  {
    if ($entity->getMusician()->getAddressBookUri() === null) {
      return;
    }
    // @todo: remove the instrument from the categories
  }

  /**
   * Search for a contact with UID == $uuid.
   *
   * @param string $addressBookUri
   *
   * @param string|Uuid $uuid
   *
   * @return null|array
   */
  private function findContact(string $addressBookUri, string|Uuid $uuid):?array
  {
    /** @var CardDavService $cardDavService */
    $cardDavService = $this->appContainer->get(CardDavService::class);
    /** @var IAddressBook $addressBook */
    $addressBook = $cardDavService->getAddressBooksByUri($addressBookUri);
    if ($addressBook === null) {
      return nujll;
    }
    $result = $addressBook->search(
      pattern: (string)$uuid,
      searchProperties: [ 'UID' ],
      options: [
        'types' => true,
        'wildcard' => false,
      ],
    );
    $this->logInfo('SEARCH CONTACT RESULTS ' . print_r($result, true));
    if (count($result) !== 1) {
      return null;
    }
    return $result[0];
  }
}
