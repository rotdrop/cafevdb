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

/** Asymmetric encryption using the OpenSSL extension of PHP */
class OpenSSLAsymmetricCryptor implements AsymmetricCryptorInterface
{
  private const SIGNING_ALGORITHM = OPENSSL_ALGO_SHA512;

  private $privKey = null;

  private $pubKey = null;

  public function __construct($privKey = null, string $password = null)
  {
    if (!empty($privKey)) {
      $this->setPrivateKey($privKey, $password);
    }
  }

  /** {@inheritdoc} */
  public function setPrivateKey($privKey, ?string $password = null):AsymmetricCryptorInterface
  {
    if ($privKey !== null) {
      $this->privKey = openssl_pkey_get_private($privKey, $password);
      $details = openssl_pkey_get_details($this->privKey);
      $this->setPublicKey($details['key']);
    } else {
      $this->privKey = $privKey;
    }
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
    $encryptedData = null;
    openssl_public_encrypt($decryptedData, $encryptedData, $this->pubKey);
    return base64_encode($encryptedData);
  }

  /** {@inheritdoc} */
  public function decrypt(?string $encryptedData):?string
  {
    $decryptedData = null;
    openssl_private_decrypt(base64_decode($encryptedData), $decryptedData, $this->privKey);
    return $decryptedData;
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

  /** {@inheritdoc} */
  public function sign($data):string
  {
    openssl_sign($data, $signature, $this->privKey, self::SIGNING_ALGORITHM);
    return $signature;
  }

  /** {@inheritdoc} */
  public function verify($data, string $signature):bool
  {
    return openssl_verify($data, $signature, $this->pubKey, self::SIGNING_ALGORITHM);
  }
};
