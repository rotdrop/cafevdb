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

class OpenSSLAsymmetricKeyStorage extends CloudAsymmetricKeyStorage
{
  // NOPE. OpenSSL supports RSA-encryption only.
  // const KEY_CONFIG = [
  //   'private_key_type' => OPENSSL_KEYTYPE_EC,
  //   'private_key_bits' => 512,
  //   'curve_name' => 'prime256v1',
  // ];
  const KEY_CONFIG = [
    'private_key_type' => OPENSSL_KEYTYPE_RSA,
    'private_key_bits' => 4096,
  ];

  static $name = 'openssl';

  /** create a key-pair, but don't store it */
  protected function createKeyPair()
  {
    $privKey = openssl_pkey_new(self::KEY_CONFIG);

    /* Extract the public key from $res to $pubKey */
    $details = openssl_pkey_get_details($privKey);

    if ($details === false) {
      return null;
    }

    $pubKey = $details['key'];

    return [
      self::PRIVATE_ENCRYPTION_KEY => $privKey,
      self::PUBLIC_ENCRYPTION_KEY => $pubKey,
    ];
  }

  /** Decode the raw data fetch from whatever storage backend */
  protected function unserializeKey(string $rawKeyMaterial, string $which)
  {
    if ($which == self::PRIVATE_ENCRYPTION_KEY) {
      return openssl_pkey_get_private($rawKeyMaterial);
    } else {
      return $rawKeyMaterial;
    }
  }

  /** Serialize key to string for storage in whatever storage backend */
  protected function serializeKey(mixed $key, string $which):string
  {
    if ($which == self::PRIVATE_ENCRYPTION_KEY) {
      openssl_pkey_export($key, $privKey);
      return $privKey;
    } else {
      return $key;
    }
  }

}
