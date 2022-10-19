<?php
/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2022 Claus-Justus Heine
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

use Throwable;
use InvalidArgumentException;

use OCP\ILogger;
use OCP\IL10N;
use OCP\IConfig;

use OCA\CAFEVDB\Exceptions;

/** Key-storage base-class. */
abstract class CloudAsymmetricKeyStorage extends AbstractAsymmetricKeyStorage
{
  use \OCA\CAFEVDB\Traits\LoggerTrait;

  const NAME_SEPARATOR = ';';

  /** @var string */
  public static $name = null;

  /** @var string */
  private $appName;

  /** @var IConfig */
  private $cloudConfig;

  /** @var SymmetricCryptorInterface */
  private $cryptor;

  /** @var IL10N */
  protected $l;

  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct(
    string $appName,
    ILogger $logger,
    IL10N $l10n,
    IConfig $cloudConfig,
    SymmetricCryptorInterface $cryptor,
  ) {
    $this->appName = $appName;
    $this->logger = $logger;
    $this->l = $l10n;
    $this->cloudConfig = $cloudConfig;
    $this->cryptor = $cryptor;
  }
  // phpcs:enable

  /** {@inheritdoc} */
  public function getKeyPair(string $ownerId, string $keyPassphrase)
  {
    $privKeyMaterial = $this->getUserValue($ownerId, self::PRIVATE_ENCRYPTION_KEY);
    if (!empty($privKeyMaterial)) {
      try {
        $this->cryptor->setEncryptionKey($keyPassphrase);
        $privKeyMaterial = $this->cryptor->decrypt($privKeyMaterial);
      } catch (Throwable $t) {
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
  public function wipeKeyPair(string $ownerId):void
  {
    $this->cloudConfig->deleteUserValue($ownerId, $this->appName, self::PRIVATE_ENCRYPTION_KEY);
    $this->cloudConfig->deleteUserValue($ownerId, $this->appName, self::PUBLIC_ENCRYPTION_KEY);
  }


  /** {@inheritdoc} */
  public function getPublicKey(string $ownerId):mixed
  {
    $pubKeyMaterial = $this->getUserValue($ownerId, self::PUBLIC_ENCRYPTION_KEY);
    return empty($pubKeyMaterial) ? null : $this->unserializeKey($pubKeyMaterial, self::PUBLIC_ENCRYPTION_KEY);
  }

  /** {@inheritdoc} */
  public function setPrivateKeyPassphrase(string $ownerId, mixed $privateKey, string $newPassphrase):void
  {
    $privKeyMaterial = $this->serializeKey($privateKey, self::PRIVATE_ENCRYPTION_KEY);
    $privKeyMaterial = $this->crypto->encrypt($privKeyMaterial, $newPassphrase);
    $this->setUserValue($ownerId, self::PRIVATE_ENCRYPTION_KEY, $privKeyMaterial);
  }

  /** {@inheritdoc} */
  public function backupKeyPair(string $ownerId, string $tag = 'old'):void
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
  public function restoreKeyPair(string $ownerId, string $tag = 'old'):void
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

  /**
   * Create a key-pair, but don't store it
   *
   * @return null|array
   */
  abstract protected function createKeyPair():?array;

  /**
   * Decode the raw data fetch from whatever storage backend.
   *
   * @param string $rawKeyMaterial
   *
   * @param string $which
   *
   * @return mixed
   */
  abstract protected function unserializeKey(string $rawKeyMaterial, string $which):mixed;

  /**
   * Serialize key to string for storage in whatever storage backend
   *
   * @param mixed $key
   *
   * @param string $which
   *
   * @return string
   */
  abstract protected function serializeKey(mixed $key, string $which):string;

  /**
   * @param string $ownerId
   *
   * @param string $key
   *
   * @param mixed $default
   *
   * @return mixed
   */
  private function getUserValue(string $ownerId, string $key, mixed $default = null):mixed
  {
    $value = $this->cloudConfig->getUserValue($ownerId, $this->appName, $key, $default);
    if (!empty($value)) {
      $separatorPos = strpos($value, self::NAME_SEPARATOR);
      if ($separatorPos !== false) {
        $name = substr($value, 0, $separatorPos);
        if ($name != static::$name) {
          throw new InvalidArgumentException($this->l->t('Key-storage mismatch: %1$s / %2$s', [ $name, static::$name ]));
        }
        $value = substr($value, $separatorPos+1);
      }
    }
    return $value;
  }

  /**
   * @param string $ownerId
   *
   * @param string $key
   *
   * @param mixed $value
   *
   * @return void
   */
  private function setUserValue(string $ownerId, string $key, mixed $value):void
  {
    if (!empty(static::$name)) {
      $value = static::$name . self::NAME_SEPARATOR . $value;
    }
    $this->cloudConfig->setUserValue($ownerId, $this->appName, $key, $value);
  }
}
