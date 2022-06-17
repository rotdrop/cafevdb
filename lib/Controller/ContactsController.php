<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2022 Claus-Justus Heine <himself@claus-justus-heine.de>
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
/**
 * @file Handle various requests associated with asymmetric encryption
 */

namespace OCA\CAFEVDB\Controller;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\Response;
use OCP\AppFramework\IAppContainer;
use OCP\IRequest;
use OCP\ILogger;
use OCP\IL10N;

use OCP\Contacts\IManager as IContactsManager;
use OCP\IAddressBook;

/**
 * Make the stored personal data accessible for the web-interface. This is
 * meant for newer parts of the web-interface in contrast to the legacy PME
 * stuff.
 */
class ContactsController extends Controller
{
  use \OCA\CAFEVDB\Traits\LoggerTrait;
  use \OCA\CAFEVDB\Traits\ResponseTrait;
  use \OCA\CAFEVDB\Traits\ContactsTrait;

  /** @var IContactsManager */
  private $contactsManager;

  public function __construct(
    string $appName
    , IRequest $request
    , $userId
    , IL10N $l10n
    , ILogger $logger
    , IContactsManager $contactsManager
  ) {
    parent::__construct($appName, $request);
    $this->l = $l10n;
    $this->logger = $logger;
    $this->contactsManager = $contactsManager;
  }

  /**
   * @NoAdminRequired
   *
   * Get all the data of the given musician. This mess removes "circular"
   * associations as we are really only interested into the data for this
   * single person.
   *
   * @param int $contactUid
   *
   * @return DataResponse
   */
  public function get(int $contactUid):Response
  {
    return self::grumble($this->l->t('UNIMPLEMENTED'));
  }

  /**
   * @NoAdminRequired
   *
   * Search by user-id and names. Pattern may contain wildcards (* and %).
   */
  public function search(string $pattern, ?int $limit = null, ?int $offset = null, array $groupIds = [], array $contactUids = [], array $onlyAddressBooks = []):Response
  {

    // $this->logInfo('SEARCH: ' . $pattern . ' / ' . print_r(array_filter(compact('limit', 'offset')), true));
    $searchProperties = [ 'FN', 'EMAIL' ];
    $searchOptions = array_filter(compact('limit', 'offset'));
    $searchOptions['types'] = true;

    $addressBookUris = $onlyAddressBooks;
    if (!empty($addressBookUris)) {

      // $this->logInfo('URIS ' . print_r($addressBookUris, true));

      $result = [];
      $addressBooks = $this->contactsManager->getUserAddressBooks();
      /** @var IAddressBook $addressBook */
      foreach ($addressBooks as $addressBook) {
        $key = $addressBook->getKey();
        $uri = $addressBook->getUri();
        if (($addressBookUris[$key] ?? null) != $uri) {
          continue;
        }
        $addressBookResults = $addressBook->search(
          $pattern,
          searchProperties: $searchProperties,
          options: $searchOptions);
        foreach ($addressBookResults as $contact) {
          $contact['addressbook-key'] = $addressBook->getKey();
          $result[] = $contact;
        }
      }
    } else {
      $result = $this->contactsManager->search(
        $pattern,
        searchProperties: $searchProperties,
        options: $searchOptions);
    }

    return self::dataResponse($result);
  }

  /**
   * @NoAdminRequired
   *
   * Just return the list of addressbooks. Could also be made an "initial state".
   */
  public function getAddressBooks()
  {
    $addressBooks = $this->contactsManager->getUserAddressBooks();
    $result = self::flattenAdressBooks($addressBooks);
    return self::dataResponse($result);
  }
}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
