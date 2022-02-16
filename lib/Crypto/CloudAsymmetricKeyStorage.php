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

use OCP\ILogger;
use OCP\IL10N;
use OCP\Security\ICrypto;
use OCP\IConfig;

use OCA\CAFEVDB\Exceptions;

abstract class CloudAsymmetricKeyStorage extends AbstractAsymmetricKeyStorage
{
  use \OCA\CAFEVDB\Traits\LoggerTrait;

  /** @var string */
  private $appName;

  /** @var IConfig */
  private $cloudConfig;

  /** @var ICrypto */
  private $crypto;

  /** @var IL10N */
  protected $l;

  public function __construct(
    string $appName
    , ILogger $logger
    , IL10N $l10n
    , IConfig $cloudConfig
    , ICrypto $crypto
  ) {
    $this->appName = $appName;
    $this->logger = $logger;
    $this->l = $l10n;
    $this->cloudConfig = $cloudConfig;
    $this->crypto = $crypto;
  }

  /** {@inheritdoc} */
  public function getKeyPair(string $ownerId, string $keyPassphrase)
  {
    $privKeyMaterial = $this->getUserValue($ownerId, self::PRIVATE_ENCRYPTION_KEY);
    if (!empty($privKeyMaterial)) {
      try {
        $privKeyMaterial = $this->crypto->decrypt($privKeyMaterial, $keyPassphrase);
      } catch(\Throwable $t) {
        // exceptions are ok, but we want to stick to Exceptions\EncryptionException
        throw new Exceptions\EncryptionKeyException(
          $this->l->t('Unable to decrypt private key for owner "%s".', $ownerId),
          $t->getCode(), $t
        );
      }
      $privKey = $this->unserializeKey($privKeyMaterial, self::PRIVATE_ENCRYPTION_KEY);
      $pubKey = $this->getPublicKey($ownerId);
    }
    return [
      self::PRIVATE_ENCRYPTION_KEY => $privKey ?? null,
      self::PUBLIC_ENCRYPTION_KEY => $pubKey ?? null,
    ];
  }

  /** {@inheritdoc} */
  public function generateKeyPair(string $ownerId, string $keyPassphrase)
  {
    list(
      self::PRIVATE_ENCRYPTION_KEY => $privKey,
      self::PUBLIC_ENCRYPTION_KEY => $pubKey,
    ) = $keyPair = $this->createKeyPair();

    $privKeyMaterial = $this->serializeKey($privKey, self::PRIVATE_ENCRYPTION_KEY);
    $privKeyMaterial = $this->crypto->encrypt($privKeyMaterial, $keyPassphrase);
    $this->setUserValue($ownerId, self::PRIVATE_ENCRYPTION_KEY, $privKeyMaterial);

    $pubKeyMaterial = $this->serializeKey($pubKey, self::PUBLIC_ENCRYPTION_KEY);
    $this->setUserValue($ownerId, self::PUBLIC_ENCRYPTION_KEY, $pubKeyMaterial);

    return $keyPair;
  }

  /** {@inheritdoc} */
  public function wipeKeyPair(string $ownerId)
  {
    $this->cloudConfig->deleteUserValue($ownerId, $this->appName, self::PRIVATE_ENCRYPTION_KEY);
    $this->cloudConfig->deleteUserValue($ownerId, $this->appName, self::PUBLIC_ENCRYPTION_KEY);
  }

  /** {@inheritdoc} */
  public function getPublicKey(string $ownerId)
  {
    $pubKeyMaterial = $this->getUserValue($ownerId, self::PUBLIC_ENCRYPTION_KEY);
    return $this->unserializeKey($pubKeyMaterial, self::PUBLIC_ENCRYPTION_KEY);
  }

  /** {@inheritdoc} */
  public function setPrivateKeyPassphrase(string $ownerId, $privateKey, string $newPassphrase)
  {
    $privKeyMaterial = $this->serializeKey($privateKey, self::PRIVATE_ENCRYPTION_KEY);
    $privKeyMaterial = $this->crypto->encrypt($privKeyMaterial, $newPassphrase);
    $this->setUserValue($ownerId, self::PRIVATE_ENCRYPTION_KEY, $privKeyMaterial);
  }

  /** create a key-pair, but don't store it */
  abstract protected function createKeyPair();

  /** Decode the raw data fetch from whatever storage backend */
  abstract protected function unserializeKey(string $rawKeyMaterial, string $which);

  /** Serialize key to string for storage in whatever storage backend */
  abstract protected function serializeKey(mixed $key, string $which):string;

  private function getUserValue(string $ownerId, string $key, mixed $default = null)
  {
    return $this->cloudConfig->getUserValue($ownerId, $this->appName, $key, $default);
  }

  private function setUserValue(string $ownerId, string $key, mixed $value)
  {
    return $this->cloudConfig->setUserValue($ownerId, $this->appName, $key, $value);
  }
}
