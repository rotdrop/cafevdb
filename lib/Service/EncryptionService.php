<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2020, 2021, 2022 Claus-Justus Heine <himself@claus-justus-heine.de>
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

namespace OCA\CAFEVDB\Service;

use OCP\IConfig;
use OCP\IUserSession;
use OCP\Security\ICrypto;
use OCP\Security\IHasher;
use OCP\Authentication\LoginCredentials\IStore as ICredentialsStore;
use OCP\Authentication\LoginCredentials\ICredentials;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\ILogger;
use OCP\IL10N;

use OCA\CAFEVDB\Exceptions;
use OCA\CAFEVDB\Events\EncryptionServiceBound as EncryptionServiceBoundEvent;

use OCA\CAFEVDB\Crypto;

/**
 * This kludge is here as long as our slightly over-engineered
 * "missing-translation" event-handler is in action.
 */
class FakeL10N
{
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
  use \OCA\CAFEVDB\Traits\LoggerTrait;

  const PUBLIC_ENCRYPTION_KEY = Crypto\AsymmetricKeyService::PUBLIC_ENCRYPTION_KEY_CONFIG;
  const PRIVATE_ENCRYPTION_KEY = Crypto\AsymmetricKeyService::PRIVATE_ENCRYPTION_KEY_CONFIG;

  const USER_ENCRYPTION_KEY_KEY = 'encryptionkey';
  const APP_ENCRYPTION_KEY_HASH_KEY = 'encryptionkeyhash';

  const NEVER_ENCRYPT = [
    'enabled',
    'installed_version',
    'types',
    'usergroup', // cloud-admin setting
    'wikinamespace', // cloud-admin setting
    'cspfailuretoken', // for public post route
    'configlock', // better kept open
    self::APP_ENCRYPTION_KEY_HASH_KEY,
  ];

  const SHARED_PRIVATE_VALUES = [
    self::USER_ENCRYPTION_KEY_KEY,
  ];

  /** @var string */
  private $appName;

  /** @var IConfig */
  private $containerConfig;

  /** @var ICrypto */
  private $crypto;

  /** @var IHasher */
  private $hasher;

  /** @var IEventDispatcher */
  private $eventDispatcher;

  /** @var Crypto\AsymmetricKeyService */
  private $asymKeyService;

  /** @var Crypto\SealService */
  private $sealService;

  /** @var Crypto\CloudSymmetricCryptor */
  private $appCryptor;

  /** @var Crypto\OpenSSLAsymmetricCryptor */
  private $appAsymmetricCryptor;

  /** @var string */
  private $userId = null;

  /** @var string */
  private $userPassword = null;

  /** @var string */
  private $userPrivateKey = null;

  /** @var string */
  private $userPublicKey = null;

  public function __construct(
    $appName
    , AuthorizationService $authorization
    , IConfig $containerConfig
    , IUserSession $userSession
    , Crypto\AsymmetricKeyService $asymKeyService
    , Crypto\SealService $sealService
    , ICrypto $crypto
    , IHasher $hasher
    , ICredentialsStore $credentialsStore
    , IEventDispatcher $eventDispatcher
    , ILogger $logger
    , IL10N $l10n
  ) {
    $this->appName = $appName;
    $this->containerConfig = $containerConfig;
    $this->asymKeyService = $asymKeyService;
    $this->sealService = $sealService;
    $this->appCryptor = new Crypto\CloudSymmetricCryptor($crypto);
    $this->appAsymmetricCryptor = new Crypto\OpenSSLAsymmetricCryptor;
    $this->crypto = $crypto;
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
    if (!$authorization->authorized($userId)) {
      return;
    }
    try {
      $userPassword = $credentialsStore->getLoginCredentials()->getPassword();
    } catch (\Throwable $t) {
      $this->logException($t);
      $userPassword = null;
    }
    if (!empty($userId) && !empty($userPassword)) {
      try {
        $this->bind($userId, $userPassword);
      } catch (\Throwable $t) {
        $this->logException($t);
      }
    }
  }

  /**
   * Bind $this to the given userId and password, for instance during
   * listeners for password-change etc.
   */
  public function bind(string $userId, string $password)
  {
    $this->logDebug('BINDING TO ' . $userId . ' PW LEN ' . strlen($password));
    $this->userId = $userId;
    $this->userPassword = $password;
    $this->initUserKeyPair();
    $this->initAppEncryptionKey();
    $this->initAppKeyPair();
    $this->eventDispatcher->dispatchTyped(new EncryptionServiceBoundEvent($userId));
  }

  /**
   * Test if we a bound to a user
   */
  public function bound():bool
  {
    return !empty($this->userId)
      && !empty($this->userPassword)
      && !empty($this->userPrivateKey)
      && !empty($this->userPublicKey);
  }

  /**
   * Return bound user id
   */
  public function getUserId()
  {
    return $this->bound() ? $this->userId : null;
  }

  public function getAppEncryptionKey()
  {
    return $this->appCryptor->getEncryptionKey();
  }

  public function setAppEncryptionKey($key)
  {
    //$this->logInfo('Installing encryption key '.$key);
    $this->appCryptor->setEncryptionKey($key);
  }

  /**
   * @return Crypto\ICryptor
   */
  public function getAppCryptor():Crypto\ICryptor
  {
    return $this->appCryptor;
  }

  /**
   * Initialize the per-user public/private key pair, which
   * inparticular is used to propagate the app's encryption key to all
   * relevant users.
   *
   * @param bool $forceNewKeyPair Generate a new key pair even if an
   * old one is found.
   *
   * @throws Exceptions\EncryptionKeyException
   */
  public function initUserKeyPair($forceNewKeyPair = false)
  {
    try {
      list(self::PRIVATE_ENCRYPTION_KEY => $this->userPrivateKey, self::PUBLIC_ENCRYPTION_KEY => $this->userPublicKey) =
        $this->asymKeyService->initEncryptionKeyPair($this->userId, $this->userPassword, $forceNewKeyPair);
    } catch (Exceptions\EncryptionException $e) {
      $this->userPrivateKey = null;
      $this->userPublicKey = null;
      throw $e;
    }
  }

  public function initAppKeyPair($forceNewKeyPair = false)
  {
    $group = $this->getAppValue('usergroup');
    $encryptionKey = $this->getAppEncryptionKey();
    if (empty($group) || empty($encryptionKey)) {
      $this->logDebug('Cannot initialize SSL key-pair without user-group and encryption key');
      return;
    }
    $group = '@' . $group;

    try {
      list(self::PRIVATE_ENCRYPTION_KEY => $privKay, self::PUBLIC_ENCRYPTION_KEY => $pubKey) =
        $this->asymKeyService->initEncryptionKeyPair($group, $encryptionKey, $forceNewKeyPair);
    } catch (Exceptions\EncryptionException $e) {
      $this->appAsymmetricCryptor->setPrivateKey(null);
      $this->appAsymmetricCryptor->setPublicKey(null);
      throw $e;
    }
    $this->appAsymmetricCryptor->setPrivateKey($privKey);
    $this->appAsymmetricCryptor->setPublicKey($pubKey);
  }

  /**
   * Remove the SSL key-pair for the given login. This is needed if an
   * administrator changes the password. This will make all other
   * encrypted data unavailable, so as a side-effect all encrypted
   * data is removed.
   *
   * @param string $login
   */
  public function deleteUserKeyPair($login)
  {
    $this->logDebug('REMOVING ENCRYPTION DATA FOR USER ' . $login);
    $this->containerConfig->deleteUserValue($login, $this->appName, self::PUBLIC_ENCRYPTION_KEY);
    $this->containerConfig->deleteUserValue($login, $this->appName, self::PRIVATE_ENCRYPTION_KEY);
    foreach (self::SHARED_PRIVATE_VALUES as $key) {
      $this->containerConfig->deleteUserValue($login, $this->appName, $key);
    }
  }

  public function getUserEncryptionKey()
  {
    return $this->getSharedPrivateValue(self::USER_ENCRYPTION_KEY_KEY, null);
  }

  /**
   * Set the encryption key for the current or the given user.
   *
   * @param string $key Encryption key
   *
   * @param null|string $userId A potentially different than the
   * currently logged in user.
   */
  public function setUserEncryptionKey($key, ?string $userId = null)
  {
    return $this->setSharedPrivateValue(self::USER_ENCRYPTION_KEY_KEY, $key, $userId);
  }

  /**
   * Recrypt the user's shared private values, e.g. when the password
   * was updated. We assume here that we still have access to the old
   * values. We generate a new private/public SSL key pair and recrypt
   * the values.
   */
  public function recryptSharedPrivateValues(string $newPassword)
  {
    $decrypted = [];
    foreach (self::SHARED_PRIVATE_VALUES as $key) {
      $value = $this->getSharedPrivateValue($key);
      if (!empty($value)) {
        $decrypted[$key] = $value;
      } else {
        $this->containerConfig->deleteUserValue($this->userId, $this->appName, $key);
      }
    }
    $this->userPassword = $newPassword;
    $this->initUserKeyPair(true);
    foreach ($decrypted as $key => $value) {
      $this->setSharedPrivateValue($key, $value);
    }
  }

  /**
   * Initialize the global symmetric app encryption key used to
   * encrypt shared data.
   *
   * @throws Exceptions\EncryptionKeyException
   */
  public function initAppEncryptionKey()
  {
    if (!$this->bound()) {
      throw new Exceptions\EncryptionKeyException($this->l->t('Cannot initialize global encryption key without bound user credentials.'));
    }

    $usrdbkey = $this->getUserEncryptionKey();
    if (empty($usrdbkey)) {
      // No key -> unencrypted
      $this->logDebug("No Encryption Key, setting to empty string in order to disable encryption.");
      $this->appCryptor->setEncryptionKey(''); // not null, just empty
    } else {
      $this->appCryptor->setEncryptionKey($usrdbkey);
    }

    // compare the user-key with the stored encryption key hash
    $sysdbkeyhash = $this->getConfigValue(self::APP_ENCRYPTION_KEY_HASH_KEY);
    if (!$this->verifyHash($usrdbkey, $sysdbkeyhash)) {
      // Failed
      $this->appCryptor->setEncryptionKey(null);
      throw new Exceptions\EncryptionKeyException($this->l->t('Failed to validate user encryption key.'));
      $this->logError('Unable to validate HASH for encryption key.');
    } else {
      $this->logDebug('Encryption keys validated'.(empty($usrdbkey) ? ' (no encryption)' : '').'.');
    }

    return true;
  }

  /**
   * Get and decrypt an personal 'user' value encrypted with the
   * public key of key-pair. Throw an exception if $this is not bound
   * to a user and password, see self::bind().
   *
   * @return string Decrypt config value.
   *
   * @throws Exceptions\EncryptionKeyException
   */
  public function getSharedPrivateValue($key, $default = null)
  {
    if (!$this->bound()) {
      throw new Exceptions\EncryptionKeyException($this->l->t('Cannot decrypt private values without bound user credentials'));
    }

    // Fetch the encrypted "user" key from the preferences table
    $value = $this->getUserValue($this->userId, $key, $default);

    // we allow null values without encryption
    if (empty($value) || $value === $default) {
      return $value;
    }

    $value = base64_decode($value);

    // Try to decrypt the $usrdbkey
    if (openssl_private_decrypt($value, $value, $this->userPrivateKey) === false) {
      throw new Exceptions\DecryptionFailedException($this->l->t('Decryption of "%s" with private key of user "%s" failed.', [ $key, $this->userId ]));
    }

    return $value;
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
   * @return string Encrypted value.
   *
   * @throws Exceptions\EncryptionKeyException
   * @throws Exceptions\EncryptionFailedException
   */
  public function setSharedPrivateValue(string $key, $value, ?string $userId = null)
  {
    if (empty($userId)) {
      if (!$this->bound()) {
        throw new Exceptions\EncryptionKeyException($this->l->t('Cannot encrypt private values without bound user credentials'));
      }
      $userId = $this->userId;
      $userPublicKey = $this->userPublicKey;
    } else {
      // encrypt for different than bound user
      $userPublicKey = $this->getUserValue($userId, self::PUBLIC_ENCRYPTION_KEY);
      if (empty($userPublicKey)) {
        throw new Exceptions\EncryptionKeyException($this->l->t('Cannot encrypt private values for user "%s" without public SSL key.', $userId));
      }
    }

    // We maintain empty values as empty values ATM. This is problematic as it
    // also reveals the value of the setting as empty
    if (empty($value)) {
      $this->deleteUserValue($userId, $key);
      return;
    }

    if (openssl_public_encrypt($value, $encrypted, $userPublicKey) === false) {
      throw new Exceptions\EncryptionFailedException($this->l->t('Encrypting value for key "%s" with public key of user "%s" failed.', [ $key, $userId ]));
    }

    $encrypted = base64_encode($encrypted);

    $this->setUserValue($userId, $key, $encrypted);
  }

  /**
   * Check the validity of the encryption key. In order to do so we fetch
   * an encrypted representation of the key from the OC config space
   * and try to decrypt that key with the given key. If the decrypted
   * key matches our key, then we accept the key.
   *
   * @param string $encrytionKey
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

    $sysdbkeyhash = $this->getConfigValue(self::APP_ENCRYPTION_KEY_HASH_KEY);

    if (empty($sysdbkeyhash) !== empty($encryptionKey)) {
      if (empty($sysdbkeyhash)) {
        $this->logError('Stored encryption key is empty while provided encryption key is not empty.');
      } else {
        $this->logError('Stored encryption key is not empty while provided encryption key is empty.');
      }
      return false;
    }

    $match = $this->verifyHash($encryptionKey, $sysdbkeyhash);

    if (!$match) {
      $this->logError('Unable to validate HASH for encryption key.');
    } else {
      $this->logDebug('Validated HASH for encryption key.');
    }

    return $match;
  }

  public function getAppValue($key, $default = null)
  {
    return $this->containerConfig->getAppValue($this->appName, $key, $default);
  }

  public function setAppValue($key, $value)
  {
    return $this->containerConfig->setAppValue($this->appName, $key, $value);
  }

  public function getUserValue($userId, $key, $default = null)
  {
    return $this->containerConfig->getUserValue($userId, $this->appName, $key, $default);
  }

  public function setUserValue($userId, $key, $value)
  {
    return $this->containerConfig->setUserValue($userId, $this->appName, $key, $value);
  }

  public function deleteUserValue($userId, $key)
  {
    return $this->containerConfig->deleteUserValue($userId, $this->appName, $key);
  }

  public function getConfigValue($key, $default = null)
  {
    $value  = $this->getAppValue($key, $default);

    if (!empty($value) && ($value !== $default) && array_search($key, self::NEVER_ENCRYPT) === false) {
      // null is error or uninitialized, string '' means no encryption
      if ($this->appCryptor->getEncryptionKey() === null) {
        if (!empty($this->userId)) {
          $message = $this->l->t('Decryption requested for user "%s", but not configured, empty encryption key.', $this->userId);
          throw new Exceptions\EncryptionKeyException($message);
          // $this->logError($message);
        }
        return false;
      }
      try {
        $value  = $this->appCryptor->decrypt($value);
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
   * @param $key Configuration key.
   * @param $value Configuration value.
   */
  public function setConfigValue($key, $value)
  {
    $encryptionKey = $this->appCryptor->getEncryptionKey();
    if (!empty($encryptionKey) && array_search($key, self::NEVER_ENCRYPT) === false) {
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

  public function verifyHash($value, $hash)
  {
    return $value === null || empty($hash) || $this->hasher->verify($value, $hash);
  }

  public function computeHash($value)
  {
    return $this->hasher->hash($value);
  }
}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
