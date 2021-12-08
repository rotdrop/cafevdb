<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016, 2020, 2021 Claus-Justus Heine <himself@claus-justus-heine.de>
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

  public function setEncryptionKey($encryptionKey)
  {
    $this->encryptionKey = $encryptionKey;
  }

  /** {@inheritdoc} */
  public function encrypt(string $data):string
  {
    return $this->crypto->encrypt($data, $this->encryptionKey);
  }

  /** {@inheritdoc} */
  public function decrypt(string $data):string
  {
    return $this->crypto->decrypt($data, $this->encryptionKey);
  }
};
