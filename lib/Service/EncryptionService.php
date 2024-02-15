<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2020, 2021, 2022, 2023, 2024 Claus-Justus Heine
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

namespace OCA\CAFEVDB\Service;

use OCP\IConfig;
use OCP\IUserSession;
use OCP\Security\IHasher;
use OCP\Authentication\LoginCredentials\IStore as ICredentialsStore;
use OCP\Authentication\LoginCredentials\ICredentials;
use OCP\EventDispatcher\IEventDispatcher;
use Psr\Log\LoggerInterface as ILogger;
use OCP\IL10N;

use OCA\CAFEVDB\Exceptions;
use OCA\CAFEVDB\Events\EncryptionServiceBound as EncryptionServiceBoundEvent;

use OCA\CAFEVDB\Crypto;

// phpcs:disable PSR1.Classes.ClassDeclaration.MultipleClasses

/**
 * This kludge is here as long as our slightly over-engineered
 * "missing-translation" event-handler is in action.
 *
 * @SuppressWarnings(PHPMD.ShortMethodName)
 */
class FakeL10N
{
  /** {@inheritdoc} */
  public function t($text, $parameters = [])
  {
    if (!is_array($parameters)) {
      $parameters = [ $parameters ];
    }
    return vsprintf($text, $parameters);
  }
}

/**
 * Handle some encryption tasks:
 *
 * - shared encryption key
 * - encrypted app config values with the shared encryption key
 * - encrypted per-user config values where needed
 * - asymmetric encryption for per-user encryption key
 *
 * We only support the case where the password is available through
 * the credential store of the emedding cloud instance.
 *
 * @todo One could cache the decrypted configuration values given that
 * encryption may be costly.
 */
class EncryptionService
{
  use \OCA\CAFEVDB\Toolkit\Traits\LoggerTrait;

  const PUBLIC_ENCRYPTION_KEY = Crypto\AsymmetricKeyService::PUBLIC_ENCRYPTION_KEY_CONFIG;
  const PRIVATE_ENCRYPTION_KEY = Crypto\AsymmetricKeyService::PRIVATE_ENCRYPTION_KEY_CONFIG;

  const USER_ENCRYPTION_KEY_KEY = 'encryptionkey';
  const APP_ENCRYPTION_KEY_HASH_KEY = 'encryptionkeyhash';

  const CONFIG_LOCK_KEY = 'configlock';

  const NEVER_ENCRYPT = [
    'enabled',
    'installed_version',
    'types',
    ConfigService::USER_GROUP_KEY, // cloud-admin setting
    ConfigService::SHAREOWNER_KEY, // needed as calendar principal in the member's app
    ConfigService::SHARED_FOLDER, // needed by some listeners in order to bail out early
    ConfigService::PROJECT_PARTICIPANTS_FOLDER, // needed by some listeners in order to bail out early
    'wikinamespace', // cloud-admin setting
    'cspfailuretoken', // for public post route
    'configlock', // better kept open
    'orchestra', // used in the member's app for the front-page announcement
    'orchestraLocale', // used in the member's app for consistent currencies etc.
    self::APP_ENCRYPTION_KEY_HASH_KEY,
  ];

  const SHARED_PRIVATE_VALUES = [
    self::USER_ENCRYPTION_KEY_KEY,
  ];

  /** @var string */
  private $appName;

  /** @var IConfig */
  private $containerConfig;

  /** @var IHasher */
  private $hasher;

  /** @var IEventDispatcher */
  private $eventDispatcher;

  /** @var Crypto\AsymmetricKeyService */
  private $asymKeyService;

  /** @var Crypto\CloudSymmetricCryptor */
  private $appCryptor;

  /** @var Crypto\AsymmetricCryptorInterface */
  private $appAsymmetricCryptor;

  /** @var string */
  private $userId = null;

  /** @var string */
  private $userPassword = null;

  /** @var Crypto\AsymmetricCryptorInterface */
  private $userAsymmetricCryptor;

  /** {@inheritdoc} */
  public function __construct(
    string $appName,
    AuthorizationService $authorization,
    IConfig $containerConfig,
    IUserSession $userSession,
    Crypto\AsymmetricKeyService $asymKeyService,
    Crypto\CryptoFactoryInterface $cryptoFactory,
    IHasher $hasher,
    ICredentialsStore $credentialsStore,
    IEventDispatcher $eventDispatcher,
    ILogger $logger,
    IL10N $l10n,
  ) {
    $this->appName = $appName;
    $this->containerConfig = $containerConfig;
    $this->asymKeyService = $asymKeyService;
    $this->appCryptor = $cryptoFactory->getSymmetricCryptor();
    $this->hasher = $hasher;
    $this->eventDispatcher = $eventDispatcher;
    $this->logger = $logger;
    $this->l = new FakeL10N(); // $l10n;

    try {
      $userId = $userSession->getUser()->getUID();
    } catch (\Throwable $t) {
      //$this->logException($t);
      $userId = null;
    }
    if ($authorization->getUserPermissions($userId) === AuthorizationService::PERMISSION_NONE) {
      return;
    }
    try {
      $userPassword = $credentialsStore->getLoginCredentials()->getPassword();
    } catch (\Throwable $t) {
      $this->logException($t, 'Unable to obtain login-password for "' . $userId . '".');
      $userPassword = null;
    }
    if (!empty($userId) && !empty($userPassword)) {
      try {
        $this->bind($userId, $userPassword);
      } catch (\Throwable $t) {
        $this->logException($t, 'Unable to bind to "' . $userId . '".');
      }
    }
  }

  /**
   * Bind $this to the given userId and password, for instance during
   * listeners for password-change etc.
   *
   * @param string $userId User id to bind.
   *
   * @param string $password Userpassword for binding.
   *
   * @return void
   */
  public function bind(string $userId, string $password):void
  {
    // $this->logDebug('BINDING TO ' . $userId . ' PW LEN ' . strlen($password));
    $this->userId = $userId;
    $this->userPassword = $password;
    $this->initUserKeyPair();
    $this->initAppEncryptionKey();
    $this->initAppKeyPair();
    $this->eventDispatcher->dispatchTyped(new EncryptionServiceBoundEvent($userId));
  }

  /**
   * Test if we a bound to a user
   *
   * @return bool
   */
  public function bound():bool
  {
    return !empty($this->userId)
      && !empty($this->userPassword)
      && !empty($this->userAsymmetricCryptor)
      && $this->userAsymmetricCryptor->canDecrypt()
      && $this->userAsymmetricCryptor->canEncrypt();
  }

  /** @return null|string Bound user id if any. */
  public function getUserId():?string
  {
    return $this->bound() ? $this->userId : null;
  }

  /** @return string App encryption key */
  public function getAppEncryptionKey():?string
  {
    return $this->appCryptor->getEncryptionKey();
  }

  /**
   * @param null|string $key Install the given encryption key for the app.
   *
   * @return void
   */
  public function setAppEncryptionKey(?string $key):void
  {
    //$this->logInfo('Installing encryption key '.$key);
    $this->appCryptor->setEncryptionKey($key);
  }

  /** @return Crypto\ICryptor */
  public function getAppCryptor():Crypto\ICryptor
  {
    return $this->appCryptor;
  }

  /**
   * @return null|Crypto\AsymmetricCryptorInterface
   */
  public function getAppAsymmetricCryptor():?Crypto\AsymmetricCryptorInterface
  {
    return $this->appAsymmetricCryptor;
  }

  /**
   * Initialize the per-user public/private key pair, which
   * inparticular is used to propagate the app's encryption key to all
   * relevant users.
   *
   * @param bool $forceNewKeyPair Generate a new key pair even if an
   * old one is found.
   *
   * @return void
   *
   * @throws Exceptions\EncryptionKeyException
   */
  public function initUserKeyPair(bool $forceNewKeyPair = false):void
  {
    $exception = null;
    try {
      $this->asymKeyService->initEncryptionKeyPair($this->userId, $this->userPassword, $forceNewKeyPair);
    } catch (Exceptions\EncryptionException $exception) {
      // Gracefully accept a broken key-pair if the app-encryption key is empty.
      try {
        $userKey = $this->getUserEncryptionKey();
        $exception = null;
        if (empty($userKey)) {
          // after all, this means that all values are unencrypted, so be graceful here
          $this->asymKeyService->initEncryptionKeyPair($this->userId, $this->userPassword, forceNewKeyPair: true);
        }
      } catch (\Throwable $t) {
        // give up
        $this->logException($t, 'User\'s "' . $this->userId . '" asymmetric key pair is broken and encryption key appears to be non-empty.');
      }
    }
    $this->userAsymmetricCryptor = $this->asymKeyService->getCryptor($this->userId);
    if (!empty($exception)) {
      $this->userAsymmetricCryptor
        ->setPrivateKey(null)
        ->setPublicKey(null);
      throw $exception;
    }
  }

  /**
   * @param bool $forceNewKeyPair Whether to force regeneration.
   *
   * @return void
   */
  public function initAppKeyPair(bool $forceNewKeyPair = false):void
  {
    $group = $this->getAppValue(ConfigService::USER_GROUP_KEY);
    $encryptionKey = $this->getAppEncryptionKey();
    if (empty($group) || empty($encryptionKey)) {
      $this->logDebug('Cannot initialize encryption key-pair without user-group and encryption key');
      $this->appAsymmetricCryptor = null;
      return;
    }
    $group = '@' . $group;

    $exception = null;
    try {
      $this->asymKeyService->initEncryptionKeyPair($group, $encryptionKey, $forceNewKeyPair);
    } catch (Exceptions\EncryptionException $exception) {
      // empty, see below
    }
    $this->appAsymmetricCryptor = $this->asymKeyService->getCryptor($group);
    if (!empty($exception)) {
      $this->appAsymmetricCryptor->setPrivateKey(null);
      $this->appAsymmetricCryptor->setPublicKey(null);
      throw $exception;
    }
    // remove any pending notifications for the (forced) regeneration of the
    // shared orchestra key.
    $this->asymKeyService->removeRecryptionRequestNotification($group);
  }

  /**
   * Restore a potential backup e.g. after recryption failure.
   *
   * @return void
   */
  public function restoreAppKeyPair():void
  {
    $group = $this->getAppValue(ConfigService::USER_GROUP_KEY);
    $encryptionKey = $this->getAppEncryptionKey();
    if (empty($group) || empty($encryptionKey)) {
      $this->logDebug('Cannot restore encryption key-pair without user-group and encryption key');
      $this->appAsymmetricCryptor = null;
      return;
    }
    $group = '@' . $group;
    $this->asymKeyService->restoreEncryptionKeyPair($group);
    $this->initAppKeyPair();
  }

  /**
   * Remove the SSL key-pair for the given login. This is needed if an
   * administrator changes the password. This will make all other
   * encrypted data unavailable, so as a side-effect all encrypted
   * data is removed.
   *
   * @param string $userId
   *
   * @return void
   */
  public function deleteUserKeyPair(string $userId):void
  {
    $this->logDebug('REMOVING ENCRYPTION DATA FOR USER ' . $userId);
    $this->asymKeyService->deleteEncryptionKeyPair($userId);
  }

  /** @return null|string */
  public function getUserEncryptionKey():?string
  {
    return $this->getSharedPrivateValue(self::USER_ENCRYPTION_KEY_KEY, null);
  }

  /**
   * Set the encryption key for the current or the given user.
   *
   * @param null|string $key Encryption key.
   *
   * @param null|string $userId A potentially different than the
   * currently logged in user.
   *
   * @return void
   */
  public function setUserEncryptionKey(?string $key, ?string $userId = null):void
  {
    $this->setSharedPrivateValue(self::USER_ENCRYPTION_KEY_KEY, $key, $userId);
  }

  /**
   * Initialize the global symmetric app encryption key used to
   * encrypt shared data.
   *
   * @return bool \true on success.
   *
   * @throws Exceptions\EncryptionKeyException
   */
  public function initAppEncryptionKey():bool
  {
    if (!$this->bound()) {
      throw new Exceptions\EncryptionKeyException($this->l->t('Cannot initialize global encryption key without bound user credentials.'));
    }

    $userDatabaseKey = $this->getUserEncryptionKey();
    if (empty($userDatabaseKey)) {
      // No key -> unencrypted
      $this->logDebug("No Encryption Key, setting to empty string in order to disable encryption.");
      $this->appCryptor->setEncryptionKey(''); // not null, just empty
    } else {
      $this->appCryptor->setEncryptionKey($userDatabaseKey);
    }

    // compare the user-key with the stored encryption key hash
    $sysDatabaseKeyHash = $this->getConfigValue(self::APP_ENCRYPTION_KEY_HASH_KEY);
    if (!$this->verifyHash($userDatabaseKey, $sysDatabaseKeyHash)) {
      // Failed
      $this->appCryptor->setEncryptionKey(null);
      throw new Exceptions\EncryptionKeyException($this->l->t('Failed to validate user encryption key.'));
      $this->logError('Unable to validate HASH for encryption key.');
    } else {
      $this->logDebug('Encryption keys validated'.(empty($userDatabaseKey) ? ' (no encryption)' : '').'.');
    }

    return true;
  }

  /**
   * Get and decrypt an personal 'user' value encrypted with the
   * public key of key-pair. Throw an exception if $this is not bound
   * to a user and password, see self::bind().
   *
   * @param string $key Config key.
   *
   * @param mixed $default Default value.
   *
   * @return string Decrypted config value.
   *
   * @throws Exceptions\EncryptionKeyException
   */
  public function getSharedPrivateValue(string $key, mixed $default = null)
  {
    return $this->asymKeyService->getSharedPrivateValue($this->userId, $key, $default);
  }

  /**
   * Encrypt the given value with the user's public key.
   *
   * @param string $key Configuration key.
   *
   * @param mixed $value Value to be encrypted. Must be convertible to
   * string.
   *
   * @param null|string $userId Potentitally different than logged in
   * user id.
   *
   * @return void
   *
   * @throws Exceptions\EncryptionKeyException
   * @throws Exceptions\EncryptionFailedException
   */
  public function setSharedPrivateValue(string $key, mixed $value, ?string $userId = null):void
  {
    if (empty($userId)) {
      if (!$this->bound()) {
        throw new Exceptions\EncryptionKeyException($this->l->t('Cannot encrypt private values without bound user credentials'));
      }
      $userId = $this->userId;
    }
    $this->asymKeyService->setSharedPrivateValue($userId, $key, $value);
  }

  /**
   * Check the validity of the encryption key. In order to do so we fetch
   * an encrypted representation of the key from the OC config space
   * and try to decrypt that key with the given key. If the decrypted
   * key matches our key, then we accept the key.
   *
   * @param null|string $encryptionKey Key to check.
   *
   * @return bool
   *
   * @throws Exceptions\EncryptionKeyException
   */
  public function encryptionKeyValid(?string $encryptionKey = null):bool
  {
    if (!$this->bound()) {
      throw new Exceptions\EncryptionKeyException($this->l->t('Cannot validate app encryption key without bound user credentials'));
    }

    if (empty($encryptionKey)) {
      $encryptionKey = $this->appCryptor->getEncryptionKey();
    }
    if (empty($encryptionKey)) {
      $this->logWarn('Provided encryption-key is empty, encryption is switched off.');
    }

    $sysDatabaseKeyHash = $this->getConfigValue(self::APP_ENCRYPTION_KEY_HASH_KEY);

    if (empty($sysDatabaseKeyHash) !== empty($encryptionKey)) {
      if (empty($sysDatabaseKeyHash)) {
        $this->logError('Stored encryption key is empty while provided encryption key is not empty.');
      } else {
        $this->logError('Stored encryption key is not empty while provided encryption key is empty.');
      }
      return false;
    }

    $match = $this->verifyHash($encryptionKey, $sysDatabaseKeyHash);

    if (!$match) {
      $this->logError('Unable to validate HASH for encryption key.');
    } else {
      $this->logDebug('Validated HASH for encryption key.');
    }

    return $match;
  }

  /**
   * A short-cut, redirecting to the stock functions for the app.
   *
   * @param string $key Config key.
   *
   * @param mixed $default Default value.
   *
   * @return mixed
   */
  public function getAppValue(string $key, mixed $default = null)
  {
    return $this->containerConfig->getAppValue($this->appName, $key, $default);
  }

  /**
   * A short-cut, redirecting to the stock functions for the app.
   *
   * @param string $key Config key.
   *
   * @param mixed $value Value to set.
   *
   * @return mixed
   */
  public function setAppValue(string $key, mixed $value)
  {
    return $this->containerConfig->setAppValue($this->appName, $key, $value);
  }

  /**
   * @param string $userId Use the current user if null.
   *
   * @param string $key Config key.
   *
   * @param mixed $default Default value.
   *
   * @return mixed
   */
  public function getUserValue(string $userId, string $key, mixed $default = null)
  {
    return $this->containerConfig->getUserValue($userId, $this->appName, $key, $default);
  }

  /**
   * @param string $userId Use the current user if null.
   *
   * @param string $key Config key.
   *
   * @param mixed $value Value to set.
   *
   * @return mixed
   */
  public function setUserValue(string $userId, string $key, mixed $value)
  {
    return $this->containerConfig->setUserValue($userId, $this->appName, $key, $value);
  }

  /**
   * @param string $userId Use the current user if null.
   *
   * @param string $key Config key.
   *
   * @return mixed
   */
  public function deleteUserValue(string $userId, string $key)
  {
    return $this->containerConfig->deleteUserValue($userId, $this->appName, $key);
  }

  /**
   * Fetch the value for the given key and possibly decrypt it.
   *
   * @param string $key
   *
   * @param mixed $default
   *
   * @param bool $ignoreLock
   *
   * @return mixed
   *
   * @throws Exceptions\ConfigLockedException
   */
  public function getConfigValue(string $key, mixed $default = null, bool $ignoreLock = false)
  {
    if (!$ignoreLock && !empty($this->getAppValue(self::CONFIG_LOCK_KEY))) {
      throw new Exceptions\ConfigLockedException('Configuration locked, not retrieving value for ' . $key);
    }
    $value  = $this->getAppValue($key, $default);

    if (!empty($value) && ($value !== $default)) {
      // null is error or uninitialized, string '' means no encryption
      if ($this->appCryptor->getEncryptionKey() === null) {
        if ($this->appCryptor->isEncrypted($value) === false) {
          return $value;
        }
        if (!empty($this->userId)) {
          $message = $this->l->t('Decryption requested for user "%s", but not configured, empty encryption key.', $this->userId);
          throw new Exceptions\EncryptionKeyException($message);
          // $this->logError($message);
        }
        return false;
      }
      try {
        $value = $this->appCryptor->decrypt($value);
      } catch (\Throwable $t) {
        throw new Exceptions\DecryptionFailedException($this->l->t('Unable to decrypt value "%s" for "%s"', [$value, $key]), $t->getCode(), $t);
      }
      //$this->logInfo("Decrypted value for $key: ".$value);
    }

    return $value;
  }

  /**
   * Encrypt the given value and store it in the application settings
   * table of the Cloud.
   *
   * @param string $key Configuration key.
   *
   * @param mixed $value Configuration value.
   *
   * @param bool $ignoreLock
   *
   * @return bool
   *
   * @throws Exceptions\ConfigLockedException
   */
  public function setConfigValue(string $key, mixed $value, bool $ignoreLock = false):bool
  {
    if (!$ignoreLock && !empty($this->getAppValue(self::CONFIG_LOCK_KEY))) {
      throw new Exceptions\ConfigLockedException('Configuration locked, not storing value for config-key ' . $key);
    }
    $encryptionKey = $this->appCryptor->getEncryptionKey();
    if (!empty($encryptionKey) && !in_array($key, self::NEVER_ENCRYPT)) {
      if ($encryptionKey === null) {
        // null is error or uninitialized, string '' means no encryption
        if (!empty($this->userId)) {
          $message = $this->l->t('Encryption requested but not configured for user "%s", empty encryption key.', $this->userId);
          //throw new Exceptions\EncryptionKeyException($message);
          $this->logError($message);
        }
        return false;
      }
      //$this->logInfo('Encrypting value for key '.$key);
      $value = $this->appCryptor->encrypt($value);
    }
    $this->setAppValue($key, $value);
    return true;
  }

  /**
   * @param null|string $value Value to verify.
   *
   * @param null|string $hash Hash to verify against.
   *
   * @return bool \true if either hash or value are empty or if the hash could
   * be verified.
   */
  public function verifyHash(?string $value, $hash):bool
  {
    return $value === null || empty($hash) || $this->hasher->verify($value, $hash);
  }

  /**
   * @param string $value The value to hash.
   *
   * @return string The hash of $value.
   */
  public function computeHash(string $value):string
  {
    return $this->hasher->hash($value);
  }
}
