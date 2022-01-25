<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016, 2020, 2021, 2022 Claus-Justus Heine <himself@claus-justus-heine.de>
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

namespace OCA\CAFEVDB\Common\Crypto;

use OCP\Security\ICrypto;
use OCA\CAFEVDB\Exceptions;

/** Use the encryption service provided by the ambient cloud software. */
class CloudSymmetricCryptor implements ICryptor
{
  /** @var ICrypto */
  private $crypto;

  /** @var null|string */
  private $encryptionKey;

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
      if (substr($data, -2, 2) !== '|3') {
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
};
