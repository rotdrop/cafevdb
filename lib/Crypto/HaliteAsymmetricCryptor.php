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

namespace OCA\CAFEVDB\Crypto;

use ParagonIE\Halite;
use ParagonIE\HiddenString\HiddenString;

use OCA\CAFEVDB\Exceptions;

/** Asymmetric encryption using the ParagonIE\Halite library */
class HaliteAsymmetricCryptor implements AsymmetricCryptorInterface
{
  private $privKey = null;

  private $pubKey = null;

  public function __construct($privKey = null, ?string $password = null)
  {
    if (!empty($privKey)) {
      $this->setPrivateKey($privKey, $password);
    }
  }

  /**
   * {@inheritdoc}
   *
   * The paramter $password is ignored and must be kept at null.
   */
  public function setPrivateKey($privKey, ?string $password = null):AsymmetricCryptorInterface
  {
    if ($password !== null) {
      throw new Exceptions\EncryptionKeyException('The private key has to be unlocked before passing it here.');
    }
    $this->privKey = $privKey;
    return $this;
  }

  /** {@inheritdoc} */
  public function setPublicKey($pubKey):AsymmetricCryptorInterface
  {
    $this->pubKey = $pubKey;
    return $this;
  }

  /** {@inheritdoc} */
  public function getPublicKey()
  {
    return $this->pubKey;
  }

  /** {@inheritdoc} */
  public function encrypt(?string $decryptedData):?string
  {
    if (empty($decryptedData)) {
      return $decryptedData;
    }
    return Halite\Asymmetric\Crypto::seal(
      new HiddenString($decryptedData),
      $this->pubKey,
      Halite::ENCODE_BASE64URLSAFE
    );
  }

  /** {@inheritdoc} */
  public function decrypt(?string $encryptedData):?string
  {
    if (empty($encryptedData)) {
      return $encryptedData;
    }
    return Halite\Asymmetric\Crypto::unseal(
      $encryptedData,
      $this->privKey,
      Halite::ENCODE_BASE64URLSAFE
    );
  }

  /** {@inheritdoc} */
  public function canEncrypt():bool
  {
    return $this->pubKey !== null;
  }

  /** {@inheritdoc} */
  public function canDecrypt():bool
  {
    return $this->privKey !== null;
  }
};
