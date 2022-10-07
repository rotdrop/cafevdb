<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2020, 2021, 2022 Claus-Justus Heine <himself@claus-justus-heine.de>
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

namespace OCA\CAFEVDB\Database\Doctrine\ORM\Listeners\Transformable;

use OCP\ILogger;

use OCA\CAFEVDB\Wrapped\MediaMonks\Doctrine\Transformable;

use OCA\CAFEVDB\Service\EncryptionService;
use OCA\CAFEVDB\Crypto;
use OCA\CAFEVDB\Common\Util;

class Encryption implements Transformable\Transformer\TransformerInterface
{
  use \OCA\CAFEVDB\Traits\LoggerTrait;

  /** @var Crypto\AsymmetricKeyService */
  private $keyService;

  /** @var string */
  private $managementGroupId;

  /** @var Crypto\ICryptor */
  private $appCryptor;

  /** @var Crypto\SealCryptor */
  private $sealCryptor;

  /** @var Crypto\SealService */
  private $sealService;

  /** @var bool */
  private $cachable;

  public function __construct(
    Crypto\AsymmetricKeyService $keyService
    , string $managementGroupId
    , EncryptionService $encryptionService
    , Crypto\SealCryptor $sealCryptor
    , ILogger $logger
  ) {
    $this->keyService = $keyService;
    $this->managementGroupId = '@' . $managementGroupId;
    $this->appCryptor = $encryptionService->getAppAsymmetricCryptor();
    // The seal-cryptor carries state, namely the array of key-cryptors, so
    // better take a copy here.
    $this->sealCryptor = clone $sealCryptor;
    $this->sealService = $this->sealCryptor->getSealService();
    $this->cachable = true;
    $this->logger = $logger;
  }

  /**
   * Set the shared app-cryptor which is used to encrypt the database values
   */
  public function setAppCryptor(?Crypto\ICryptor $appCryptor)
  {
    $this->appCryptor = $appCryptor;
  }

  /**
   * Return the shared app-cryptor which is used to encrypt the database values
   */
  public function getAppCryptor():?Crypto\ICryptor
  {
    return $this->appCryptor;
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
  public function transform(?string $value, mixed &$context = null): mixed
  {
    if (!$this->isEncrypted()) {
      return $value;
    }

    $context = $this->manageEncryptionContext(null, $context);

    $sealCryptors = [];
    foreach ($context as $encryptionId) {
      $cryptor = $this->getSealCryptor($encryptionId);
      if ($cryptor->canEncrypt()) {
        $sealCryptors[$encryptionId] = $cryptor;
      }
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
  public function reverseTransform(?string $value, mixed &$context = null): mixed
  {
    if (!$this->isEncrypted()) {
      return $value;
    }

    $context = $this->manageEncryptionContext($value, $context);

    $sealCryptors = [];
    foreach ($context as $encryptionId) {
      $cryptor = $this->getSealCryptor($encryptionId);
      if ($cryptor->canDecrypt()) {
        $sealCryptors[$encryptionId] = $cryptor;
      }
    }
    $this->sealCryptor->setSealCryptors($sealCryptors);

    return $this->sealCryptor->decrypt($value);
  }

  /**
   * Disable caching while changing the encryption key.
   *
   * @return bool
   */
  public function isCachable():bool
  {
    return $this->cachable;
  }

  public function isEncrypted():bool
  {
    return !empty($this->appCryptor);
  }

  private function getSealCryptor(string $encryptionId):Crypto\ICryptor
  {
    if ($encryptionId == $this->managementGroupId) {
      return $this->appCryptor;
    } else {
      return $this->keyService->getCryptor($encryptionId);
    }
  }

  private function manageEncryptionContext(?string $value, $context)
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
