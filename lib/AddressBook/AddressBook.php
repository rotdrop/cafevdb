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

use Sabre\DAV\PropPatch;
use OCA\DAV\CardDAV\Integration\ExternalAddressBook;
use OCA\DAV\DAV\Sharing\Plugin;

use OCA\CAFEVDB\Service\ConfigService;

/**
 * Cloud- and CardDAV address-book integration.
 */
class AddressBook extends ExternalAddressBook
{
  use \OCA\CAFEVDB\Traits\ConfigTrait;

  /** @var ICardBackend */
  private $cardBackend;

  /** @var string */
  private $principalUri;

  public function __construct(
    ConfigService $configService
    , ICardBackend $cardBackend
    , string $principalUri
  ) {
    parent::__construct($configService->getAppName(), $cardBackend->getURI());
    $this->cardBackend = $cardBackend;
    $this->principalUri = $principalUri;
    $this->configService = $configService;
    $this->l = $this->l10n();
  }

  /**
   * @inheritDoc
   */
  function createFile($name, $data = null) {
    throw new \Exception('This addressbook is immutable');
  }

  /**
   * @inheritDoc
   * @throws RecordNotFound
   */
  function getChild($name) {
    // $this->logInfo('name '.$name);
    return $this->cardBackend->getCard($name);
  }

  /**
   * @inheritDoc
   */
  function getChildren() {
    $cards = $this->cardBackend->getCards();

    //$this->logInfo(print_r($cards, true));
    return $cards;
  }

  /**
   * @inheritDoc
   */
  function childExists($name) {
    // $this->logInfo('name '.$name);
    try {
      $this->getChild($name);
      return true;
    } catch (RecordNotFound $e) {
      return false;
    }
  }

  /**
   * @inheritDoc
   */
  function delete() {
    throw new \Exception('This addressbook is immutable');
  }

  /**
   * @inheritDoc
   */
  function getLastModified() {
    return $this->cardBackend->getLastModified();
  }

  /**
   * @inheritDoc
   */
  function propPatch(PropPatch $propPatch) {
    throw new \Exception('This addressbook is immutable');
  }

  /**
   * @inheritDoc
   */
  function getProperties($properties) {
    $eTag = md5((string)$this->getLastModified());
    $props = [
      '{' . Plugin::NS_OWNCLOUD . '}principaluri' => $this->principalUri,
      '{DAV:}displayname' => $this->cardBackend->getDisplayName(),
      '{' . Plugin::NS_OWNCLOUD . '}read-only' => true,
      '{http://calendarserver.org/ns/}getctag' => $eTag,
      '{DAV:}getetag' => $eTag,
    ];
    $this->logDebug('PROPERTIES '.print_r($props, true), [], 2);
    return $props;
  }
}
