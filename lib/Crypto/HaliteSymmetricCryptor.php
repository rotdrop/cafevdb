<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016, 2020, 2021, 2022 Claus-Justus Heine <himself@claus-justus-heine.de>
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

use OCA\CAFEVDB\Exceptions;

/**
 * Use libsodium through Halite.
 */
class HaliteSymmetricCryptor implements SymmetricCryptorInterface
{
  /**
   * @var string
   * The magic bytes at the start of the encrypted data.
   */
  private const HALITE_MAGIC = Halite\Halite::VERSION_PREFIX;

  /** @var null|string */
  private $encryptionKey;

  /** @var Halite\Symmetric\EncryptionKey */
  private $haliteEncryptionKey;

  public function __construct(?string $encryptionKey = null)
  {
    $this->setEncryptionKey($encryptionKey);
  }

  /**
   * Set the encryption-key to use. If left empty then the data will be left
   * unencrypted and just be passed through.
   *
   * @param null|string $encryptionKey
   *
   * @return null|string The old encryption get.
   */
  public function setEncryptionKey(?string $encryptionKey):?string
  {
    $oldEncryptionKey = $this->encryptionKey;
    $this->encryptionKey = $encryptionKey;

    if (!empty($encryptionKey)) {
      // Halite will compute a hash of its own. We rather assume that the
      // $encryptionKey is more or less random and not a simple password and
      // just split it in order to avoid passing 0-bytes as salt.
      // $salt = substr($encryptionKey, -SODIUM_CRYPTO_PWHASH_SALTBYTES);
      $salt = str_pad('', SODIUM_CRYPTO_PWHASH_SALTBYTES, chr(0));
      $this->haliteEncryptionKey = Halite\KeyFactory::deriveEncryptionKey(
        new HiddenString($encryptionKey),
        $salt
      );
    } else {
      $this->haliteEncryptionKey = null;
    }

    return $oldEncryptionKey;
  }

  /**
   * Fetch the installed encryption key, if any.
   *
   * @return null|string
   */
  public function getEncryptionKey():?string
  {
    return $this->encryptionKey;
  }

  /** {@inheritdoc} */
  public function encrypt(?string $data):?string
  {
    if (!empty($this->encryptionKey)) {
      try {
        // $startTime = microtime(true);
        // \OCP\Util::writeLog('cafevdb', 'Start encrypt ' . strlen($data) . ' bytes', \OCP\Util::INFO);
        $data = Halite\Symmetric\Crypto::encrypt(
          new HiddenString($data),
          $this->haliteEncryptionKey,
          Halite\Halite::ENCODE_BASE64URLSAFE);
        // $duration = microtime(true) - $startTime;
        // \OCP\Util::writeLog('cafevdb', 'End encrypt ' . $duration . ' seconds '  . strlen($data) . ' bytes', \OCP\Util::INFO);
      } catch (\Throwable $t) {
        throw new Exceptions\EncryptionFailedException('Encrypt failed', $t->getCode(), $t);
      }
    }
    return $data;
  }

  /** {@inheritdoc} */
  public function decrypt(?string $data):?string
  {
    if (!empty($this->encryptionKey) && !empty($data)) {
      if (!str_starts_with($data, self::HALITE_MAGIC)) {
        // not encrypted hack
        return $data;
      }
      try {
        // $startTime = microtime(true);
        // \OCP\Util::writeLog('cafevdb', 'Start Decrypt ' . strlen($data) . ' bytes', \OCP\Util::INFO);
        /** @var HiddenString $data */
        $data = Halite\Symmetric\Crypto::decrypt(
          $data,
          $this->haliteEncryptionKey,
          Halite\Halite::ENCODE_BASE64URLSAFE);
        $data = $data->getString();
        // $duration = microtime(true) - $startTime;
        // \OCP\Util::writeLog('cafevdb', 'End Decrypt ' . $duration . ' seconds '  . strlen($data) . ' bytes', \OCP\Util::INFO);
      } catch (\Throwable $t) {
        throw new Exceptions\DecryptionFailedException('Decrypt failed', $t->getCode(), $t);
      }
    }
    return $data;
  }

  /** {@inheritdoc} */
  public function canEncrypt():bool
  {
    return $this->encryptionKey !== null;
  }

  /** {@inheritdoc} */
  public function canDecrypt():bool
  {
    return $this->encryptionKey !== null;
  }
};
