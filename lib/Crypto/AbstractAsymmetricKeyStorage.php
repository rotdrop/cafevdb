<?php
/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2022 Claus-Justus Heine <himself@claus-justus-heine.de>
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

namespace OCA\CAFEVDB\Crypto;

abstract class AbstractAsymmetricKeyStorage implements AsymmetricKeyStorageInterface
{
  /** {@inheritdoc} */
  public function getPrivateKey(string $ownerId, string $keyPassphrase)
  {
    $keyPair = $this->getKeyPair($ownerId, $keyPassphrase);
    return $keyPair[self::PRIVATE_ENCRYPTION_KEY]??null;
  }

  /** {@inheritdoc} */
  public function initializeKeyPair(string $ownerId, string $keyPassphrase, bool $forceNewKeypair = false)
  {
    if ($forceNewKeypair) {
      return $this->generateKeyPair($ownerId, $keyPassphrase);
    }
    $keyPair = $this->getKeyPair($ownerId, $keyPassphrase);
    if (empty($keyPair[self::PRIVATE_ENCRYPTION_KEY]) || empty($keyPair[self::PUBLIC_ENCRYPTION_KEY])) {
      return $this->generateKeyPair($ownerId, $keyPassphrase);
    }
    return $keyPair;
  }

}
