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
  /** @var Halite\Asymmetric\SignatureSecretKey */
  private $privSignKey = null;

  /** @var Halite\Asymmetric\SignaturePublicKey */
  private $pubSignKey = null;

  /** @var Halite\Asymmetric\EncryptionSecretKey */
  private $privEncKey = null;

  /** @var Halite\Asymmetric\EncryptionPublicKey */
  private $pubEncKey = null;

  public function __construct($privSignKey = null, ?string $password = null)
  {
    $this->setPrivateKey($privSignKey, $password);
  }

  /**
   * {@inheritdoc}
   *
   * The paramter $password is ignored and must be kept at null.
   */
  public function setPrivateKey($privSignKey, ?string $password = null):AsymmetricCryptorInterface
  {
    if ($password !== null) {
      throw new Exceptions\EncryptionKeyException('The private key has to be unlocked before passing it here.');
    }
    $this->privSignKey = $privSignKey;
    if (!empty($privSignKey)) {
      $this->privEncKey = $privSignKey->getEncryptionSecretKey();
    } else {
      $this->privEncKey = null;
    }
    return $this;
  }

  /** {@inheritdoc} */
  public function setPublicKey($pubSignKey):AsymmetricCryptorInterface
  {
    $this->pubSignKey = $pubSignKey;
    if (!empty($pubSignKey)) {
      $this->pubEncKey = $pubSignKey->getEncryptionPublicKey();
    } else {
      $this->pubEncKey = null;
    }
    return $this;
  }

  /** {@inheritdoc} */
  public function getPublicKey()
  {
    return $this->pubSignKey;
  }

  /** {@inheritdoc} */
  public function encrypt(?string $decryptedData):?string
  {
    if (empty($decryptedData) || empty($this->pubEncKey)) {
      return $decryptedData;
    }
    return Halite\Asymmetric\Crypto::seal(
      new HiddenString($decryptedData),
      $this->pubEncKey,
      Halite\Halite::ENCODE_BASE64URLSAFE
    );
  }

  /** {@inheritdoc} */
  public function decrypt(?string $encryptedData):?string
  {
    if (empty($encryptedData) || empty($this->privEncKey)) {
      return $encryptedData;
    }
    /** @var HiddenString $result */
    $result = Halite\Asymmetric\Crypto::unseal(
      $encryptedData,
      $this->privEncKey,
      Halite\Halite::ENCODE_BASE64URLSAFE
    );
    return $result->getString();
  }

  /** {@inheritdoc} */
  public function sign($data):string
  {
    return Halite\Asymmetric\Crypto::sign(
      $data,
      $this->privSignKey,
      Halite\Halite::ENCODE_BASE64URLSAFE
    );
  }

  /** {@inheritdoc} */
  public function verify($data, string $signature):bool
  {
    return Halite\Asymmetric\Crypto::verify(
      $data,
      $this->pubSignKey,
      $signature,
      Halite\Halite::ENCODE_BASE64URLSAFE
    );
  }

  /** {@inheritdoc} */
  public function canEncrypt():bool
  {
    return $this->pubSignKey !== null;
  }

  /** {@inheritdoc} */
  public function canDecrypt():bool
  {
    return $this->privSignKey !== null;
  }

  /** {@inheritdoc} */
  public function canSign():bool
  {
    return $this->canDecrypt();
  }

  /** {@inheritdoc} */
  public function canVerify():bool
  {
    return $this->canEncrypt();
  }
};
