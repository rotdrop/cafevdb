<?php
declare(strict_types=1);
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2021, 2022, 2024 Claus-Justus Heine
 *
 * This file based on ldap_contacts_backend, copyright 2020 Arthur Schiwon
 * <blizzz@arthur-schiwon.de>
 *
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
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace OCA\CAFEVDB\AddressBook;

use Sabre\VObject\Component\VCard;
use Sabre\VObject\Property;

use OC\Security\CSRF\CsrfTokenManager;
use OCP\Constants;
use OCP\IAddressBook;
use OCP\IConfig;
use OCP\IURLGenerator;

use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Service\ContactsService;

/** Addressbook connection for musician database. */
class ContactsAddressBook implements IAddressBook
{
  use \OCA\CAFEVDB\Traits\ConfigTrait;

  public const DAV_PROPERTY_SOURCE = 'X-CAFEVDB_CONTACTS_ID';

  /** {@inheritdoc} */
  public function __construct(
    protected ConfigService $configService,
    private ContactsService $contactsService,
    private ICardBackend $cardBackend,
    private ?string $uri = null,
  ) {
    $this->uri = $uri ?? $this->cardBackend->getURI();
    $this->l = $this->l10n();
  }

  /** {@inheritdoc} */
  public function getKey()
  {
    return $this->cardBackend->getURI();
  }

  /** {@inheritdoc} */
  public function getUri():string
  {
    return $this->uri;
  }

  /** {@inheritdoc} */
  public function getDisplayName()
  {
    return $this->cardBackend->getDisplayName();
  }

  /** {@inheritdoc} */
  public function search($pattern, $searchProperties, $options)
  {
    if (empty($pattern) && $pattern !== '') {
      $pattern = '';
    }
    // searchProperties are ignored as we follow search attributes
    // options worth considering: types
    $vCards = $this->cardBackend->searchCards($pattern, $searchProperties);
    if (isset($options['offset'])) {
      $vCards = array_slice($vCards, (int)$options['offset']);
    }
    if (isset($options['limit'])) {
      $vCards = array_slice($vCards, 0, (int)$options['limit']);
    }

    $withTypes = \array_key_exists('types', $options) && $options['types'] === true;

    $result = [];
    foreach ($vCards as $card) {
      $record = $this->vCard2Array($card->getName(), $card->getVCard(), $withTypes);
      if (is_array($record['FN'])) {
        //FN field must be flattened for contacts menu
        $record['FN'] = array_pop($record['FN']);
      }
      // prevents linking to contacts
      $record['isLocalSystemBook'] = true;
      $record[self::DAV_PROPERTY_SOURCE] = $this->cardBackend->getURI();
      $result[] = $record;
    }
    return $result;
  }

  /**
   * The search() function has to return its result in a certain format,
   * generate that for a single VCard instance.
   *
   * @param string $cardUri Card id.
   *
   * @param VCard $vCard Sabre object.
   *
   * @param bool $withTypes Passed on to flattenVCard of contacts-service.
   *
   * @return array
   */
  private function vCard2Array(string $cardUri, VCard $vCard, bool $withTypes):array
  {
    $result = $this->contactsService->flattenVCard($cardUri, $vCard, $withTypes);

    $photo = $result['PHOTO'] ?? null;
    if ($photo && str_starts_with($photo, 'VALUE=uri:data')) {
        $url = $this->urlGenerator()->getAbsoluteURL(
          $this->urlGenerator()->linkTo('', 'remote.php') . '/dav/');
        $url .= implode('/', [
          'addressbooks',
          'users/' . $this->userId(),
          $this->getUri(),
          $cardUri
        ]) . '?photo';
        $result['PHOTO'] = 'VALUE=uri:' . $url;
    }
    return $result;
  }

  /** {@inheritdoc} */
  public function createOrUpdate($properties)
  {
    return [];
  }

  /** {@inheritdoc} */
  public function getPermissions()
  {
    return Constants::PERMISSION_READ;
  }

  /** {@inheritdoc} */
  public function delete($id)
  {
    return false;
  }

  /** {@inheritdoc} */
  public function isShared():bool
  {
    return true;
  }

  /** {@inheritdoc} */
  public function isSystemAddressBook():bool
  {
    return true;
  }
}
