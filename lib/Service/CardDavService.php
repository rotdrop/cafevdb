<?php
/* Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2014, 2016, 2020, 2021 Claus-Justus Heine <himself@claus-justus-heine.de>
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

// @@todo: replace the stuff below by more persistent APIs. As it
// shows (Sep. 2020) the only option would be http calls to the dav
// service. Even the perhaps-forthcoming writable addressBook API does
// not allow the creation of addressBooks or altering shring options.

// missing: move/delete addressBook

use OCA\DAV\CardDAV\CardDavBackend;
use OCA\DAV\CardDAV\AddressBook;

class CardDavService
{
  use \OCA\CAFEVDB\Traits\ConfigTrait;

  /** @var CardDavBackend */
  private $cardDavBackend;

  /** @var \OCP\AddressBook\IManager */
  private $addressBookManager;

  /** @var int */
  private $contactsUserId;

  public function __construct(
    ConfigService $configService,
    \OCP\Contacts\IManager $addressBookManager,
    CardDavBackend $cardDavBackend
  )
  {
    $this->configService = $configService;
    $this->addressBookManager = $addressBookManager;
    $this->cardDavBackend = $cardDavBackend;
    $this->contactsUserId = $this->userId();
  }

  /**Get or create a addressBook.
   *
   * @param $uri Relative URI
   *
   * @param $userId part of the principal name.
   *
   * @param $displayName Display-name of the addressBook.
   *
   * @return int AddressBook id.
   */
  public function createAddressBook($uri, $displayName = null, $userId = null) {
    empty($userId) && ($userId = $this->userId());
    empty($displayName) && ($displayName = $uri);
    $principal = "principals/users/$userId";

    $addressBook = $this->cardDavBackend->getAddressBooksByUri($principal, $uri);
    if (!empty($addressBook))  {
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
      } catch(\Exception $e) {
        $this->logError("Exception " . $e->getMessage . " trace " . $e->stackTraceAsString());
      }
    }
    return -1;
  }

  /**Delete the addressBook with the given id */
  public function deleteAddressBook($id) {
    $this->cardDavBackend->deleteAddressBook($id);
    $this->refreshAddressBookManager();
  }

  public function groupShareAddressBook($addressBookId, $groupId, $readOnly = false) {
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
    foreach($shares as $share) {
      if ($share['href'] === $share['href'] && $share['readOnly'] == $readOnly) {
        return true;
      }
    }
    return false;
  }

  public function displayName($addressBookId, $displayName)
  {
    try {
      $propPatch = new \Sabre\DAV\PropPatch(['{DAV:}displayname' => $displayName]);
      $this->cardDavBackend->updateAddressBook($addressBookId, $propPatch);
      $propPatch->commit();
    } catch(\Exception $e) {
      $this->logError("Exception " . $e->getMessage . " trace " . $e->stackTraceAsString());
      return false;
    }
    return true;
  }

  /** Get a addressBook with the given display name. */
  public function addressBookByName($displayName)
  {
    if ($this->contactsUserId != $this->userId()) {
      $this->refreshAddressBookManager();
    }
    foreach($this->addressBookManager->getUserAddressBooks() as $addressBook) {
      if ($displayName === $addressBook->getDisplayName()) {
        return $addressBook;
      }
    }
    return null;
  }

  /** Get a addressBook with the given its id. */
  public function addressBookById($id)
  {
    if ($this->contactsUserId != $this->userId()) {
      $this->refreshAddressBookManager();
    }
    foreach($this->addressBookManager->getUserAddressBooks() as $addressBook) {
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
   */
  private function refreshAddressBookManager()
  {
    $this->addressBookManager->clear();
    $urlGenerator = \OC::$server->getURLGenerator();
    \OC::$server->query(\OCA\DAV\CardDAV\ContactsManager::class)->setupContactsProvider(
      $this->addressBookManager, $this->userId(), $urlGenerator);
    $this->contactsUserId = $this->userId();
  }

}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
