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

use ParagonIE\Halite;
use ParagonIE\HiddenString\HiddenString;

class HaliteAsymmetricKeyStorage extends CloudAsymmetricKeyStorage
{

  /** create a key-pair, but don't store it */
  protected function createKeyPair()
  {
    $keyPair = Halite\KeyFactory::generateSignatureKeyPair();
    return [
      self::PRIVATE_ENCRYPTION_KEY => $keyPair->getSecretKey(),
      self::PUBLIC_ENCRYPTION_KEY => $keyPair->getPublicKey(),
    ];
  }

  /** Decode the raw data fetch from whatever storage backend */
  protected function unserializeKey(string $rawKeyMaterial, string $which)
  {
    if ($which == self::PRIVATE_ENCRYPTION_KEY) {
      return Halite\Asymmetric\SignatureSecretKey(
        new HiddenString(
          base64_decode($rawKeyMaterial)
        )
      );
    } else {
      return Halite\Asymmetric\SignaturePublicKey(
        new HiddenString(
          base64_decode($rawKeyMaterial)
        )
      );
    }
  }

  /** Serialize key to string for storage in whatever storage backend */
  protected function serializeKey(mixed $key, string $which):string
  {
    return base64_encode($key->getRawKeyMaterial());
  }

}
