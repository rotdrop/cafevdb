<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2011-2014, 2016, 2020, 2021, 2023, 2024, 2025 Claus-Justus Heine
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

namespace OCA\CAFEVDB\Service;

use Exception;

use Sabre\DAV\PropPatch as SabrePropPatch;

use OCP\AppFramework\IAppContainer;
use OCP\Contacts\IManager as AddressBookManager;
use OCP\IL10N;
use OCP\IAddressBook;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

use OCA\DAV\CardDAV\AddressBook;
use OCA\DAV\CardDAV\CardDavBackend;

/**
 * @todo: replace the stuff below by more persistent APIs. As it
 * shows (Sep. 2020) the only option would be http calls to the dav
 * service. Even the perhaps-forthcoming writable addressBook API does
 * not allow the creation of addressBooks or altering shring options.
 * missing: move/delete addressBook
 */
class CardDavService
{
  use \OCA\CAFEVDB\Traits\GetUserTrait;
  use \OCA\CAFEVDB\Toolkit\Traits\LoggerTrait;

  /** @var null|string */
  private ?string $addressBookUserId;

  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct(
    private AddressBookManager $addressBookManager,
    private CardDavBackend $cardDavBackend,
    protected IL10N $l,
    protected LoggerInterface $logger,
    protected IUserSession $userSession,
  ) {
    $this->addressBookUserId = $this->getUserId();
  }
  // phpcs:enable

  /**
   * Get or create a addressBook.
   *
   * @param string $uri Relative URI.
   *
   * @param null|string $displayName Display-name of the addressBook.
   *
   * @param null|string $userId part of the principal name.
   *
   * @return int AddressBook id.
   */
  public function createAddressBook(string $uri, ?string $displayName = null, ?string $userId = null)
  {
    empty($userId) && ($userId = $this->getUserId());
    empty($displayName) && ($displayName = $uri);
    $principal = "principals/users/$userId";

    $addressBook = $this->cardDavBackend->getAddressBooskByUri($principal, $uri);
    if (!empty($addressBook)) {
      $this->logError("Got addressbook " . print_r($addressBook, true));
      return $addressBook['id'];
    } else {
      try {
        $addressBookId = $this->cardDavBackend->createAddressBook($principal, $uri, [
          '{DAV:}displayname' => $displayName,
        ]);
        $this->logError("Created addressbook with id " . $addressBookId);
        $this->refreshAddressBookManager();
        return $addressBookId;
      } catch (\Exception $e) {
        $this->logError("Exception " . $e->getMessage . " trace " . $e->stackTraceAsString());
      }
    }
    return -1;
  }

  /**
   * Delete the addressBook with the given id.
   *
   * @param int $id
   *
   * @return void
   */
  public function deleteAddressBook(int $id):void
  {
    $this->cardDavBackend->deleteAddressBook($id);
    $this->refreshAddressBookManager();
  }

  /**
   * @param int $addressBookId
   *
   * @param string $groupId
   *
   * @param bool $readOnly
   *
   * @return bool
   */
  public function groupShareAddressBook(int $addressBookId, string $groupId, bool $readOnly = false):bool
  {
    $share = [
      'href' => 'principal:principals/groups/'.$groupId,
      'commonName' => '',
      'summary' => '',
      'readOnly' => $readOnly,
    ];
    $addressBookInfo = $this->cardDavBackend->getAddressBookById($addressBookId);
    //$addressBookInfo = $this->addressBookById($addressBookId);
    if (empty($addressBookInfo)) {
      return false;
    }
    //$this->logError("AddressBook: " . print_r($addressBookInfo, true));
    // convert to ISharable
    $addressBook = new AddressBook($this->cardDavBackend, $addressBookInfo, $this->l10n(), $this->appConfig());
    $this->cardDavBackend->updateShares($addressBook, [$share], []);
    $shares = $this->cardDavBackend->getShares($addressBookId);
    foreach ($shares as $share) {
      if ($share['href'] === $share['href'] && $share['readOnly'] == $readOnly) {
        return true;
      }
    }
    return false;
  }

  /**
   * @param int $addressBookId
   *
   * @param string $displayName
   *
   * @return bool
   */
  public function displayName(int $addressBookId, string $displayName):bool
  {
    try {
      $propPatch = new SabrePropPatch(['{DAV:}displayname' => $displayName]);
      $this->cardDavBackend->updateAddressBook($addressBookId, $propPatch);
      $propPatch->commit();
    } catch (Exception $e) {
      $this->logError("Exception " . $e->getMessage . " trace " . $e->stackTraceAsString());
      return false;
    }
    return true;
  }

  /**
   * Get a addressBook with the given display name.
   *
   * @param string $displayName
   *
   * @return null|IAddressBook
   */
  public function addressBookByName(string $displayName):?IAddressBook
  {
    if ($this->addressBookUserId != $this->getUserId()) {
      $this->refreshAddressBookManager();
    }
    foreach ($this->addressBookManager->getUserAddressBooks() as $addressBook) {
      if ($displayName === $addressBook->getDisplayName()) {
        return $addressBook;
      }
    }
    return null;
  }

  /**
   * Get a addressBook with the given its id.
   *
   * @param int $id
   *
   * @return null|IAddressBook
   */
  public function addressBookById(int $id):?IAddressBook
  {
    if ($this->addressBookUserId != $this->getUserId()) {
      $this->refreshAddressBookManager();
    }
    foreach ($this->addressBookManager->getUserAddressBooks() as $addressBook) {
      if ((int)$id === (int)$addressBook->getKey()) {
        return $addressBook;
      }
    }
    return null;
  }

  /**
   * Get the uri of the original addressbook.
   *
   * @param int $id Numeric calendar id.
   *
   * @return null|string Calendar URI.
   */
  public function addressBookPrincipalUri(int $id):?string
  {
    $addressBookInfo = $this->cardDavBackend->getAddressBookById($id);
    if (!empty($addressBookInfo)) {
      return $addressBookInfo['principaluri'];
    }
    return null;
  }

  /**
   * Get principal, shared and original uri from the addressBook id, as well as
   * the owner-user-id.
   *
   * @param int $id Numeric addressBook id.
   *
   * @return null|array
   * ```
   * [
   *   'principaluri' => principals/users/OWNER_ID,
   *   'owneruri' => URI_AS_SEEN_BY_OWNER,
   *   'shareuri' => URI_AS_SEEN_BY_CURRENT_USER,
   *   'ownerid' => OWNER_USER_ID,
   *   'userid' => CURRENT_USER_ID,
   * ]
   * ```
   *
   * @bug Users inernal APIs. The NC PHP API is just too incomplete.
   */
  public function addressBookUris(int $id):?array
  {
    $addressBookInfo = $this->cardDavBackend->getAddressBookById($id);
    if (!empty($addressBookInfo)) {
      [,,$ownerId] = explode('/', $addressBookInfo['principaluri']);
      $userUri = ($ownerId != $this->addressBookUserId)
        ? $addressBookInfo['uri'] . '_shared_by_' . $ownerId
        : $addressBookInfo['uri'];
      return [
        'principaluri' => $addressBookInfo['principaluri'],
        'owneruri' => $addressBookInfo['uri'],
        'shareuri' => $userUri,
        'ownerid' => $ownerId,
        'userid' => $this->addressBookUserId,
      ];
    }
    return null;
  }

  /**
   * Force OCP\Contacts\IManager to be refreshed.
   *
   * @bug This function uses internal APIs.
   *
   * @return void
   */
  private function refreshAddressBookManager():void
  {
    $this->addressBookManager->clear();
    $urlGenerator = \OC::$server->getURLGenerator();
    \OC::$server->query(\OCA\DAV\CardDAV\ContactsManager::class)->setupContactsProvider(
      $this->addressBookManager, $this->getUserId(), $urlGenerator);
    $this->addressBookUserId = $this->getUserId();
  }
}
