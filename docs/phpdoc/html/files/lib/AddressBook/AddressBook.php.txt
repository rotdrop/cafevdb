<?php
declare(strict_types=1);
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2021, 2022, 2024 Claus-Justus Heine
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

namespace OCA\CAFEVDB\AddressBook;

use Exception;

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

  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct(
    protected ConfigService $configService,
    private ICardBackend $cardBackend,
    private string $principalUri,
  ) {
    parent::__construct($configService->getAppName(), $cardBackend->getURI());
    $this->l = $this->l10n();
  }
  // phpcs:enable

  /** {@inheritdoc} */
  public function createFile($name, $data = null)
  {
    throw new Exception('This addressbook is immutable');
  }

  /**
   * {@inheritdoc}
   *
   * @throws RecordNotFound
   */
  public function getChild($name)
  {
    // $this->logInfo('name '.$name);
    return $this->cardBackend->getCard($name);
  }

  /** {@inheritdoc} */
  public function getChildren()
  {
    $cards = $this->cardBackend->getCards();

    //$this->logInfo(print_r($cards, true));
    return $cards;
  }

  /** {@inheritdoc} */
  public function childExists($name)
  {
    // $this->logInfo('name '.$name);
    try {
      $this->getChild($name);
      return true;
    } catch (RecordNotFound $e) {
      return false;
    }
  }

  /** {@inheritdoc} */
  public function delete()
  {
    throw new Exception('This addressbook is immutable');
  }

  /** {@inheritdoc} */
  public function getLastModified()
  {
    return $this->cardBackend->getLastModified();
  }

  /** {@inheritdoc} */
  public function propPatch(PropPatch $propPatch)
  {
    throw new Exception('This addressbook is immutable');
  }

  /** {@inheritdoc} */
  public function getProperties($properties)
  {
    $eTag = md5((string)$this->getLastModified());
    $props = [
      '{' . Plugin::NS_OWNCLOUD . '}principaluri' => $this->principalUri,
      '{DAV:}displayname' => $this->cardBackend->getDisplayName(),
      '{' . Plugin::NS_OWNCLOUD . '}read-only' => true,
      '{http://calendarserver.org/ns/}getctag' => $eTag,
      '{DAV:}getetag' => $eTag,
    ];
    $this->logDebug('PROPERTIES '.print_r($props, true), [], 1);
    return $props;
  }
}
