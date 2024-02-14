<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2011-2014, 2016, 2020, 2021, 2023, 2024 Claus-Justus Heine
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

use \Sabre\DAV\PropPatch as SabrePropPatch;

use OCP\IAddressBook;

use OCA\DAV\CardDAV\CardDavBackend;
use OCA\DAV\CardDAV\AddressBook;

/**
 * @todo: replace the stuff below by more persistent APIs. As it
 * shows (Sep. 2020) the only option would be http calls to the dav
 * service. Even the perhaps-forthcoming writable addressBook API does
 * not allow the creation of addressBooks or altering shring options.
 * missing: move/delete addressBook
 */
class CardDavService
{
  use \OCA\CAFEVDB\Traits\ConfigTrait;

  /** @var int */
  private $contactsUserId;

  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct(
    protected ConfigService $configService,
    private \OCP\Contacts\IManager $addressBookManager,
    private CardDavBackend $cardDavBackend,
  ) {
    $this->contactsUserId = $this->userId();
    $this->l = $this->l10n();
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
    empty($userId) && ($userId = $this->userId());
    empty($displayName) && ($displayName = $uri);
    $principal = "principals/users/$userId";

    $addressBook = $this->cardDavBackend->getAddressBooksByUri($principal, $uri);
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
    if ($this->contactsUserId != $this->userId()) {
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
    if ($this->contactsUserId != $this->userId()) {
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
      $this->addressBookManager, $this->userId(), $urlGenerator);
    $this->contactsUserId = $this->userId();
  }
}
