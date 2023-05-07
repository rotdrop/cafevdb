<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2011-2016, 2020, 2021, 2022, 2023 Claus-Justus Heine
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

use OCP\Security\ICrypto;
use OCA\CAFEVDB\Exceptions;

/** Use the encryption service provided by the ambient cloud software. */
class CloudSymmetricCryptor implements SymmetricCryptorInterface
{
  /** @var ICrypto */
  private $crypto;

  /** @var null|string */
  private $encryptionKey;

  /**
   * @param ICrypto $crypto
   *
   * @param null|string $encryptionKey
   */
  public function __construct(ICrypto $crypto, ?string $encryptionKey = null)
  {
    $this->crypto = $crypto;
    $this->encryptionKey = $encryptionKey;
  }

  /**
   * Set the encryption-key to use. If left empty then the data will be left unencrypted and decrypted.
   *
   * @param null|string $encryptionKey
   *
   * @return null|string The old encryption get.
   */
  public function setEncryptionKey(?string $encryptionKey):?string
  {
    $oldEncryptionKey = $this->encryptionKey;
    $this->encryptionKey = $encryptionKey;
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
        $data = $this->crypto->encrypt($data, $this->encryptionKey);
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
      // not encrypted hack
      if (!$this->isEncrypted($data)) {
        return $data;
      }
      try {
        $data = $this->crypto->decrypt($data, $this->encryptionKey);
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

  /** {@inheritdoc} */
  public function isEncrypted(?string $data):?bool
  {
    if (empty($data)) {
      return false;
    }
    $version = $data[-1];
    if ($data[-2] !== '|' || !preg_match('/[0-9a-zA-Z]/', $version)) {
      return false;
    }
    return true;
  }
}
