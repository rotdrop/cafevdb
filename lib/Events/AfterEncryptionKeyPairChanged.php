<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2014, 2016, 2020, 2021, 2022 Claus-Justus Heine <himself@claus-justus-heine.de>
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

namespace OCA\CAFEVDB\Events;

use OCP\EventDispatcher\Event;

/**
 * Fired before a new key-pair has been generated and saved. Depending on the
 * circumstances the old key-pair may or may not be available (e.g. password
 * forgotten: no old key-pair, or at least no unlocked old private key).
 */
class AfterEncryptionKeyPairChanged extends Event
{
  /** @var string */
  private $ownerId;

  /** @var null|array */
  private $oldKeyPair;

  /** @var array */
  private $newKeyPair;

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
   * ```
   *
   * @param null|array<string, string> $newKeyPair Unlocked new key-pair, may be empty.
   * ```
   * [ 'privateEncryptionKey' => PRIV_KEY, 'publicEncryptionKey' => PUB_KEY ]
   * ```
   */
  public function __construct($ownerId, ?array $oldKeyPair, array $newKeyPair)
  {
    parent::__construct();
    $this->ownerId = $ownerId;
    $this->oldKeyPair = $oldKeyPair;
    $this->newKeyPair = $newKeyPair;
  }

  public function getOwnerId():string
  {
    return $this->ownerId;
  }

  public function getOldKeyPair():?array
  {
    return $this->oldKeyPair;
  }

  public function getNewKeyPair():array
  {
    return $this->newKeyPair;
  }
}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
