<?php
declare(strict_types=1);
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2021, 2022, 2023 Claus-Justus Heine
 * @license AGPL-3.0-or-later
 *
 * This file based on ldap_contacts_backend, copyright 2020 Arthur Schiwon
 * <blizzz@arthur-schiwon.de>
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
 */

namespace OCA\CAFEVDB\AddressBook;

use OCA\DAV\CardDAV\Integration\ExternalAddressBook;
use OCA\DAV\CardDAV\Integration\IAddressBookProvider;

use OCA\CAFEVDB\Service\AuthorizationService;
use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Service\ContactsService;

/** Kind of factory for our musicians address book. */
class AddressBookProvider implements IAddressBookProvider
{
  use \OCA\CAFEVDB\Traits\ConfigTrait;

  /** @var AuthorizationService */
  private $authorizationService;

  /** @var AddressBook */
  private static $addressBook = null;

  /** @var ContactsAddressBook */
  private static $contactsAddressBook = null;

  /** @var MusicianCardBackend */
  private $cardBackend;

  /** @var ContactsService */
  private $contactsService;

  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct(
    ConfigService $configService,
    AuthorizationService $authorizationService,
    ContactsService $contactsService,
    MusicianCardBackend $cardBackend,
  ) {
    $this->configService = $configService;
    $this->authorizationService = $authorizationService;
    $this->contactsService = $contactsService;
    $this->l = $this->l10n();
    $this->cardBackend = $cardBackend;
  }
  // phpcs:enable

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
   * @param string $principalUri E.g. principals/users/user1.
   *
   * @return ExternalAddressBook[] Array of all address books
   */
  public function fetchAllForAddressBookHome(string $principalUri): array
  {
    $addressBook = $this->getAddressBookInAddressBookHome($principalUri, $this->addressBookUri());
    return empty($addressBook) ? [] : [ $addressBook ];
  }

  /**
   * Checks whether plugin has an address book for a given principalUri and URI
   *
   * This returns true if the current user belongs to the orchestra group.
   *
   * @param string $principalUri E.g. principals/users/user1.
   *
   * @param string $uri E.g. personal.
   *
   * @return bool True if address book for principalUri and URI exists, false otherwise.
   */
  public function hasAddressBookInAddressBookHome(string $principalUri, string $uri): bool
  {
    list(,,$userId) = explode('/', $principalUri . '//');
    // $this->logInfo('in group ' . $userId . ' ' . ($this->authorizationService->authorized($userId, AuthorizationService::PERMISSION_ADDRESSBOOK) ? 'yes' : 'no'));
    return $this->authorizationService->authorized($userId, AuthorizationService::PERMISSION_ADDRESSBOOK);
  }

  /**
   * Fetches an address book for a given principalUri and URI
   * Returns null if address book does not exist
   *
   * @param string $principalUri E.g. principals/users/user1.
   *
   * @param string $uri E.g. personal.
   *
   * @return ExternalAddressBook|null address book if it exists, null otherwise.
   */
  public function getAddressBookInAddressBookHome(string $principalUri, string $uri): ?ExternalAddressBook
  {
    list(,,$userId) = explode('/', $principalUri . '//');
    // $this->logInfo('in group ' . $userId . ' ' . ($this->authorizationService->authorized($userId, AuthorizationService::PERMISSION_ADDRESSBOOK) ? 'yes' : 'no'));

    if (!$this->authorizationService->authorized($userId, AuthorizationService::PERMISSION_ADDRESSBOOK)) {
      return null;
    }
    if ($uri !== $this->addressBookUri()) {
      return null;
    }
    if (empty(self::$addressBook)) {
      self::$addressBook = new AddressBook($this->configService, $this->cardBackend, $principalUri);
    }
    return self::$addressBook;
  }

  /** @return string */
  private function addressBookUri():string
  {
    return $this->cardBackend->getURI();
  }

  /**
   * Return a cloud-address-book suitable for registration with the
   * \OCP\IContactsManager.
   *
   * @return null|ContactsAddressBook
   */
  public function getContactsAddressBook():?ContactsAddressBook
  {
    // $this->logInfo('in group ' . ($this->authorizationService->authorized(null, AuthorizationService::PERMISSION_ADDRESSBOOK) ? 'yes' : 'no'));
    if (!$this->authorizationService->authorized(null, AuthorizationService::PERMISSION_ADDRESSBOOK)) {
      // disallow access to non-group members
      return null;
    }
    if (empty(self::$contactsAddressBook)) {
      $addressBook = self::$addressBook?:(new AddressBook($this->configService, $this->cardBackend, ''));
      $uri = $addressBook->getName();
      self::$contactsAddressBook = new ContactsAddressBook($this->configService, $this->contactsService, $this->cardBackend, $uri);
    }
    return self::$contactsAddressBook;
  }
}
