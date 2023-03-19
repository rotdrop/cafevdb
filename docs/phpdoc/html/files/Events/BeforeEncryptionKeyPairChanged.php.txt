<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2011-2014, 2016, 2020, 2021, 2022 Claus-Justus Heine
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

namespace OCA\CAFEVDB\Events;

use OCP\EventDispatcher\Event;

/**
 * Fired before a new key-pair has been generated and saved. Depending on the
 * circumstances the old key-pair may or may not be available (e.g. password
 * forgotten: no old key-pair, or at least no unlocked old private key).
 */
class BeforeEncryptionKeyPairChanged extends Event
{
  /** @var string */
  private $ownerId;

  /** @var null|array */
  private $oldKeyPair;

  /**
   * @param string $ownerId The owner-id. If used for a group then it should
   * be prefixed by '@'.
   *
   * @param null|array<string, string> $oldKeyPair Unlocked old key-pair. It
   * may be null if it is missing. The old private key may be locked or
   * unlocked depending on the circumstances (e.g. password forgotten: old
   * private key cannot be unlocked).
   * ```
   * [ 'privateEncryptionKey' => PRIV_KEY, 'publicEncryptionKey' => PUB_KEY ]
   * ```.
   */
  public function __construct(string $ownerId, ?array $oldKeyPair)
  {
    parent::__construct();
    $this->ownerId = $ownerId;
    $this->oldKeyPair = $oldKeyPair;
  }

  /** @return string */
  public function getOwnerId():string
  {
    return $this->ownerId;
  }

  /** @return null|array */
  public function getOldKeyPair():?array
  {
    return $this->oldKeyPair;
  }
}
