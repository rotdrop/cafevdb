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

use OC\Security\CSRF\CsrfTokenManager;
use OCP\Constants;
use OCP\IAddressBook;
use OCP\IConfig;
use OCP\IURLGenerator;

use OCA\CAFEVDB\Service\ConfigService;

class ContactsAddressBook implements IAddressBook
{
  use \OCA\CAFEVDB\Traits\ConfigTrait;

  /** @var ICardBackend */
  private $cardBackend;

  /** @var string */
  private $uri;

  public const DAV_PROPERTY_SOURCE = 'X-CAFEVDB_CONTACTS_ID';
  /** @var PhotoService */
  private $photoService;

  public function __construct(
    ConfigService $configService
    , ICardBackend $cardBackend
    , ?string $uri = null
  ) {
    $this->cardBackend = $cardBackend;
    $this->uri = $uri ?? $this->cardBackend->getURI();
    $this->configService = $configService;
    $this->l = $this->l10n();
  }

  /**
   * @inheritDoc
   */
  public function getKey() {
    return $this->cardBackend->getURI();
  }

  /**
   * @inheritDoc
   */
  public function getUri(): string {
    return $this->uri;
  }

  /**
   * @inheritDoc
   */
  public function getDisplayName() {
    return $this->cardBackend->getDisplayName();
  }

  /**
   * @inheritDoc
   */
  public function search($pattern, $searchProperties, $options) {
    $this->logInfo('Pattern '.$pattern.' Properties '.print_r($searchProperties, true));
    // searchProperties are ignored as we follow search attributes
    // options worth considering: types
    $vCards = $this->cardBackend->searchCards($pattern, $searchProperties);
    if(isset($options['offset'])) {
      $vCards = array_slice($vCards, (int)$options['offset']);
    }
    if (isset($options['limit'])) {
      $vCards = array_slice($vCards, 0, (int)$options['limit']);
    }

    $result = [];
    foreach ($vCards as $card) {
      $record = $card->getData();
      $this->logInfo('CARD DATA'.print_r($record, true));
      if (is_array($record['FN'])) {
        //FN field must be flattened for contacts menu
        $record['FN'] = array_pop($record['FN']);
      }
      // prevents linking to contacts if UID is set
      $record['isLocalSystemBook'] = true;
      $record[self::DAV_PROPERTY_SOURCE] = $this->cardBackend->getURI();
      $result[] = $record;
    }
    return $result;
  }

  /**
   * @inheritDoc
   */
  public function createOrUpdate($properties) {
    return [];
  }

  /**
   * @inheritDoc
   */
  public function getPermissions() {
    return Constants::PERMISSION_READ;
  }

  /**
   * @inheritDoc
   */
  public function delete($id) {
    return false;
  }

  /**
   * @inheritDoc
   */
  public function isShared(): bool {
    return true;
  }

  /**
   * @inheritDoc
   */
  public function isSystemAddressBook(): bool {
    return true;
  }
}
