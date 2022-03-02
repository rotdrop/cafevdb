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
use OCP\IConfig;

use OCA\CAFEVDB\Exceptions;

abstract class CloudAsymmetricKeyStorage extends AbstractAsymmetricKeyStorage
{
  use \OCA\CAFEVDB\Traits\LoggerTrait;

  const NAME_SEPARATOR = ';';

  /** @var string */
  static $name = null;

  /** @var string */
  private $appName;

  /** @var IConfig */
  private $cloudConfig;

  /** @var SymmetricCryptorInterface */
  private $cryptor;

  /** @var IL10N */
  protected $l;

  public function __construct(
    string $appName
    , ILogger $logger
    , IL10N $l10n
    , IConfig $cloudConfig
    , SymmetricCryptorInterface $cryptor
  ) {
    $this->appName = $appName;
    $this->logger = $logger;
    $this->l = $l10n;
    $this->cloudConfig = $cloudConfig;
    $this->cryptor = $cryptor;
  }

  /** {@inheritdoc} */
  public function getKeyPair(string $ownerId, string $keyPassphrase)
  {
    $privKeyMaterial = $this->getUserValue($ownerId, self::PRIVATE_ENCRYPTION_KEY);
    if (!empty($privKeyMaterial)) {
      try {
        $this->cryptor->setEncryptionKey($keyPassphrase);
        $privKeyMaterial = $this->cryptor->decrypt($privKeyMaterial);
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
    $this->cryptor->setEncryptionKey($keyPassphrase);
    $privKeyMaterial = $this->cryptor->encrypt($privKeyMaterial);
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
    return empty($pubKeyMaterial) ? null : $this->unserializeKey($pubKeyMaterial, self::PUBLIC_ENCRYPTION_KEY);
  }

  /** {@inheritdoc} */
  public function setPrivateKeyPassphrase(string $ownerId, $privateKey, string $newPassphrase)
  {
    $privKeyMaterial = $this->serializeKey($privateKey, self::PRIVATE_ENCRYPTION_KEY);
    $privKeyMaterial = $this->crypto->encrypt($privKeyMaterial, $newPassphrase);
    $this->setUserValue($ownerId, self::PRIVATE_ENCRYPTION_KEY, $privKeyMaterial);
  }

  /** {@inheritdoc} */
  public function backupKeyPair(string $ownerId, string $tag = 'old')
  {
    foreach ([self::PUBLIC_ENCRYPTION_KEY, self::PRIVATE_ENCRYPTION_KEY] as $key) {
      $value = $this->getUserValue($ownerId, $key);
      $backupKey = $key . '.' . $tag;
      if (!empty($value)) {
        $this->setUserValue($ownerId, $backupKey, $value);
      } else {
        $this->cloudConfig->deleteUserValue($ownerId, $this->appName, $backupKey);
      }
    }
  }

  /** {@inheritdoc} */
  public function restoreKeyPair(string $ownerId, string $tag = 'old')
  {
    foreach ([self::PUBLIC_ENCRYPTION_KEY, self::PRIVATE_ENCRYPTION_KEY] as $key) {
      $backupKey = $key . '.' . $tag;
      $value = $this->getUserValue($ownerId, $backupKey);
      if (!empty($value)) {
        $this->setUserValue($ownerId, $key, $value);
      } else {
        $this->cloudConfig->deleteUserValue($ownerId, $this->appName, $key);
      }
    }
  }

  /** create a key-pair, but don't store it */
  abstract protected function createKeyPair();

  /** Decode the raw data fetch from whatever storage backend */
  abstract protected function unserializeKey(string $rawKeyMaterial, string $which);

  /** Serialize key to string for storage in whatever storage backend */
  abstract protected function serializeKey(mixed $key, string $which):string;

  private function getUserValue(string $ownerId, string $key, mixed $default = null)
  {
    $value = $this->cloudConfig->getUserValue($ownerId, $this->appName, $key, $default);
    if (!empty($value)) {
      $separatorPos = strpos($value, self::NAME_SEPARATOR);
      if ($separatorPos !== false) {
        $name = substr($value, 0, $separatorPos);
        if ($name != static::$name) {
          throw new \InvalidArgumentException($this->l->t('Key-storage mismatch: %1$s / %2$s', [ $name, static::$name ]));
        }
        $value = substr($value, $separatorPos+1);
      }
    }
    return $value;
  }

  private function setUserValue(string $ownerId, string $key, mixed $value)
  {
    if (!empty(static::$name)) {
      $value = static::$name . self::NAME_SEPARATOR . $value;
    }
    return $this->cloudConfig->setUserValue($ownerId, $this->appName, $key, $value);
  }
}
