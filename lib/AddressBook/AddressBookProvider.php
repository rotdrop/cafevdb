<?php
declare(strict_types=1);
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2021 Claus-Justus Heine <himself@claus-justus-heine.de>
 *
 * This file based on ldap_contacts_backend, copyright 2020 Arthur Schiwon
 * <blizzz@arthur-schiwon.de>
 *
 * @license GNU AGPL version 3 or any later version
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
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\CAFEVDB\AddressBook;

use OCA\DAV\CardDAV\Integration\ExternalAddressBook;
use OCA\DAV\CardDAV\Integration\IAddressBookProvider;

use OCA\CAFEVDB\Service\ConfigService;

class AddressBookProvider implements IAddressBookProvider
{
  use \OCA\CAFEVDB\Traits\ConfigTrait;

  /** @var AddressBook */
  private static $addressBook = null;

  /** @var ContactsAddressBook */
  private static $contactsAddressBook = null;

  /** @var MusicianCardBackend */
  private $cardBackend;

  public function __construct(
    ConfigService $configService
    , MusicianCardBackend $cardBackend
  ) {
    $this->configService = $configService;
    $this->l = $this->l10n();
    $this->cardBackend = $cardBackend;
  }

  /**
   * Provides the appId of the plugin
   *
   * @return string AppId
   */
  public function getAppId(): string
  {
    return $this->appName();
  }

  /**
   * Fetches all address books for a given principal uri
   *
   * @param string $principalUri E.g. principals/users/user1
   * @return ExternalAddressBook[] Array of all address books
   */
  public function fetchAllForAddressBookHome(string $principalUri): array
  {
    return [ $this->getAddressBookInAddressBookHome($principalUri, $this->addressBookUri()) ];
  }

  /**
   * Checks whether plugin has an address book for a given principalUri and URI
   *
   * This returns true if the current user belongs to the orchestra group.
   *
   * @since 19.0.0
   * @param string $principalUri E.g. principals/users/user1
   * @param string $uri E.g. personal
   * @return bool True if address book for principalUri and URI exists, false otherwise
   */
  public function hasAddressBookInAddressBookHome(string $principalUri, string $uri): bool
  {
    $this->logInfo('in group '.($this->inGroup() ? 'yes' : 'no'));
    return $this->inGroup();
  }

  /**
   * Fetches an address book for a given principalUri and URI
   * Returns null if address book does not exist
   *
   * @param string $principalUri E.g. principals/users/user1
   * @param string $uri E.g. personal
   *
   * @return ExternalAddressBook|null address book if it exists, null otherwise
   */
  public function getAddressBookInAddressBookHome(string $principalUri, string $uri): ?ExternalAddressBook
  {
    if ($uri !== $this->addressBookUri()) {
      return null;
    }
    if (empty(self::$addressBook)) {
      self::$addressBook = new AddressBook($this->configService, $this->cardBackend, $principalUri);
    }
    return self::$addressBook;
  }

  private function addressBookUri():string
  {
    return $this->cardBackend->getURI();
  }

  /**
   * Return a cloud-address-book suitable for registration with the
  * \OCP\IContactsManager.
   */
  public function getContactsAddressBook():ContactsAddressBook
  {
    if (empty(self::$contactsAddressBook)) {
      $addressBook = self::$addressBook?:(new AddressBook($this->configService, $this->cardBackend, ''));
      $uri = $addressBook->getName();
      self::$contactsAddressBook = new ContactsAddressBook($this->configService, $this->cardBackend, $uri);
    }
    return self::$contactsAddressBook;
  }
}
