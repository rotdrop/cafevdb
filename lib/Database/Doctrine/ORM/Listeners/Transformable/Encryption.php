<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2020, 2021, 2022 Claus-Justus Heine <himself@claus-justus-heine.de>
 *
 * This library se Doctrine\ORM\Tools\Setup;is free software; you can redistribute it and/or
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

namespace OCA\CAFEVDB\Database\Doctrine\ORM\Listeners\Transformable;

use OCA\CAFEVDB\Wrapped\MediaMonks\Doctrine\Transformable;

use OCA\CAFEVDB\Common\Crypto;
use OCA\CAFEVDB\Common\Util;

class Encryption implements Transformable\Transformer\TransformerInterface
{
  /** @var string */
  private $managementGroupId;

  /** @var Crypto\CloudSymmetricCryptor */
  private $appCryptor;

  /** @var Crypto\SealCryptor */
  private $sealCryptor;

  /** @var bool */
  private $cachable;

  public function __construct(
    $managementGroupId
    , Crypto\CloudSymmetricCryptor $appCryptor
    , Crypto\SealCryptor $sealCryptor
  ) {
    $this->managementGroupId = '@' . $managementGroupId;
    $this->appCryptor = $appCryptor;
    // The seal-cryptor carries state, namely the array of key-cryptors, so
    // better take a copy here.
    $this->sealCryptor = clone $sealCryptor;
    $this->cachable = true;
  }

  /**
   * Install a new encryption key, return the old one.
   *
   * @param null|string $encryptionKey The new encryption key.
   *
   * @return null|string The old global shared encryption key.
   */
  public function setAppEncryptionKey(?string $encryptionKey):?string
  {
    return $this->appCryptor->setEncryptionKey($encryptionKey);
  }

  /**
   * @return null|string The global shared encryption key of the management
   * board.
   */
  public function getAppEncryptionKey():?string
  {
    return $this->appCryptor->getEncryptionKey();
  }

  /**
   * @param bool $isCachable Set cachable status of transformer.
   */
  public function setCachable(bool $isCachable)
  {
    $this->cachable = $isCachable;
  }

  /**
   * Forward transform to data-base.
   *
   * @param string $value Unencrypted data.
   *
   * @return string Encrypted data.
   */
  public function transform($value)
  {
    if (!$this->isEncrypted()) {
      return $value;
    }

    $sealCryptors = [];
    foreach ($context as $encryptionId) {
      $sealCryptors[] = $this->getSealCryptor($encryptionId);
    }
    $this->sealCryptor->setSealCryptors($sealCryptors);

    return $this->sealCryptor->encrypt($value);
  }

  /**
   * Decrypt.
   *
   * @param string $value Encrypted data.
   *
   * @return string Decrypted data.
   */
  public function reverseTransform($value)
  {
    $context = $this->manageEncryptionContext($value, $context);

    $sealCryptors = [];
    foreach ($context as $encryptionId) {
      $sealCryptors[$encryptionId] = $this->getSealCryptor($encryptionId);
    }
    $this->sealCryptor->setSealCryptors($sealCryptors);

    return $this->sealCryptor->decrypt($value);
  }

  /**
   * Disable caching while changing the encryption key.
   *
   * @return bool
   */
  public function isCachable()
  {
    return $this->cachable;
  }

  public function isEncrypted():bool
  {
    return !empty($this->appCryptor->getEncryptionKey());
  }

  private function getSealCryptor(string $encryptionId):Crypto\ICryptor
  {
    if ($encryptionId != $this->managementGroupId) {
      throw new \RuntimeException('Not yet implemented, encryption ids are tied to the global orchestra id.');
    }
    return $this->appCryptor;
  }

  private function manageEncryptionContext(string $value, $context)
  {
    if (empty($context)) {
      return [ $this->managementGroupId ];
    }
    if (is_string($context)) {
      try {
        $context = json_decode($context);
      } catch (\Throwable $t) {
        $context = Util::explode(',', $context);
      }
    }
    if (!is_array($context)) {
      throw new \RuntimeException('Encryption context must be an array of user- or @group-ids.');
    }
    $context[] = $this->managementGroupId;
    return array_unique($context);
  }
};
