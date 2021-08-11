<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2020, 2021 Claus-Justus Heine <himself@claus-justus-heine.de>
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
use OCP\ILogger;
use OCP\IL10N;

use OCA\CAFEVDB\Exceptions;

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
 */
class EncryptionService
{
  use \OCA\CAFEVDB\Traits\LoggerTrait;

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

  /** @var string */
  private $userId;

  /** @var IConfig */
  private $containerConfig;

  /** @var ICrypto */
  private $crypto;

  /** @var IHasher */
  private $hasher;

  /** @var string */
  private $userPrivateKey = null;

  /** @var string */
  private $userPublicKey = null;

  /** @var string */
  private $appEncryptionKey = null;

  /** @var string */
  private $userPassword = null;

  public function __construct(
    $appName
    , AuthorizationService $authorization
    , IConfig $containerConfig
    , IUserSession $userSession
    , ICrypto $crypto
    , IHasher $hasher
    , ICredentialsStore $credentialsStore
    , ILogger $logger
    , IL10N $l10n
  ) {
    $this->appName = $appName;
    $this->containerConfig = $containerConfig;
    $this->crypto = $crypto;
    $this->hasher = $hasher;
    $this->logger = $logger;
    $this->l = new FakeL10N(); // $l10n;
    try {
      $this->user = $userSession->getUser();
      $this->userId = $this->user->getUID();
    } catch (\Throwable $t) {
      //$this->logException($t);
      $this->user = null;
      $this->userId = null;
    }
    if (!$authorization->authorized($this->userId)) {
      return;
    }
    try {
      $this->credentials = $credentialsStore->getLoginCredentials();
      $this->userPassword = $this->credentials->getPassword();
    } catch (\Throwable $t) {
      $this->logException($t);
      $this->credentials = null;
      $this->userPassword = null;
    }
    if (!empty($this->userId) && !empty($this->userPassword)) {
      $this->initUserKeyPair();
    } else {
      $this->userPrivateKey = null;
      $this->userPublicKey = null;
    }
    if ($this->bound()) {
      try {
        $this->initAppEncryptionKey();
      } catch (\Throwable $t) {
        $this->logException($t);
        $this->appEncryptionKey = null;
      }
    }
  }

  /**
   * Bind $this to the given userId and password, for instance during
   * listeners for password-change etc.
   */
  public function bind(string $userId, string $password)
  {
    $this->userId = $userId;
    $this->userPassword = $password;
    $this->initUserKeyPair();
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
    return $this->appEncryptionKey;
  }

  public function setAppEncryptionKey($key)
  {
    //$this->logInfo('Installing encryption key '.$key);
    $this->appEncryptionKey = $key;
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
    if (empty($this->userId) || empty($this->userPassword)) {
      throw new Exceptions\EncryptionKeyException($this->l->t('Cannot initialize SSL key-pair without user and password'));
    }

    if (!$forceNewKeyPair) {
      $privKey = $this->getUserValue($this->userId, 'privateSSLKey', null);
      $pubKey = $this->getUserValue($this->userId, 'publicSSLKey', null);
    }
    if (empty($privKey) || empty($pubKey)) {
      // Ok, generate one. But this also means that we have not yet
      // access to the data-base encryption key.
      $keys = $this->generateUserKeyPair($this->userId, $this->userPassword);
      if ($keys === false) {
        throw new Exceptions\EncryptionKeyException($this->l->t('Unable to generate SSL key pair for user "%s".', [ $this->userId ]));
      }
      list($privKey, $pubKey) = $keys;
    }

    $privKey = openssl_pkey_get_private($privKey, $this->userPassword);

    if ($privKey === false) {
      $this->userPrivateKey = null;
      $this->userPublicKey = null;
      throw new Exceptions\EncryptionKeyException($this->l->t('Unable to unlock private key for user "%s"', [ $this->userId ]));
    }

    $this->userPrivateKey = $privKey;
    $this->userPublicKey = $pubKey;
  }

  // To distribute the encryption key for the data base and
  // application configuration values we use a public/private key pair
  // for each user. Then the admin-user can distribute the global
  // encryption key to each authorized user (in the orchestra-group)
  // using the public key. When the user logs into the cloud, the key
  // is decrypted with the users private key (which again is secured
  // by the user's password.
  private function generateUserKeyPair($login, $password)
  {
    /* Create the private and public key */
    $res = openssl_pkey_new();

    /* Extract the private key from $res to $privKey */
    if (!openssl_pkey_export($res, $privKey, $password)) {
      return false;
    }

    /* Extract the public key from $res to $pubKey */
    $pubKey = openssl_pkey_get_details($res);

    if ($pubKey === false) {
      return false;
    }

    $pubKey = $pubKey['key'];

    // We now store the public key unencrypted in the user preferences.
    // The private key already is encrypted with the user's password,
    // so there is no need to encrypt it again.

    $this->setUserValue($login, 'publicSSLKey', $pubKey);
    $this->setUserValue($login, 'privateSSLKey', $privKey);

    return [ $privKey, $pubKey ];
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
      throw new Exceptions\EncryptionKeyException($this->l-t>('Cannot initialize global encryption key without bound user credentials.'));
    }

    $usrdbkey = $this->getUserEncryptionKey();
    if (empty($usrdbkey)) {
      // No key -> unencrypted
      $this->logDebug("No Encryption Key, setting to empty string in order to disable encryption.");
      $this->appEncryptionKey = ''; // not null, just empty
    } else {
      $this->appEncryptionKey = $usrdbkey;
    }

    // compare the user-key with the stored encryption key hash
    $sysdbkeyhash = $this->getConfigValue(self::APP_ENCRYPTION_KEY_HASH_KEY);
    if (!$this->verifyHash($usrdbkey, $sysdbkeyhash)) {
      // Failed
      $this->appEncryptionKey = null;
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
      $userPublicKey = $this->getUserValue($userId, 'publicSSLKey');
      if (empty($userPublicKey)) {
        throw new Exceptions\EncryptionKeyException($this->l->t('Cannot encrypt private values for user "%s" without public SSL key.', $userId));
      }
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
      $encryptionKey = $this->appEncryptionKey;
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

  public function getConfigValue($key, $default = null)
  {
    $value  = $this->getAppValue($key, $default);

    if (!empty($value) && ($value !== $default) && array_search($key, self::NEVER_ENCRYPT) === false) {
      // null is error or uninitialized, string '' means no encryption
      if ($this->appEncryptionKey === null) {
        if (!empty($this->userId)) {
          $message = $this->l->t('Decryption requested for user "%s", but not configured, empty encryption key.', $this->userId);
          throw new Exceptions\EncryptionKeyException($message);
          // $this->logError($message);
        }
        return false;
      }
      try {
        $value  = $this->decrypt($value, $this->appEncryptionKey);
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
    if (!empty($this->appEncryptionKey) && array_search($key, self::NEVER_ENCRYPT) === false) {
      if ($this->appEncryptionKey === null) {
        // null is error or uninitialized, string '' means no encryption
        if (!empty($this->userId)) {
          $message = $this->l->t('Encryption requested but not configured for user "%s", empty encryption key.', $this->userId);
          //throw new Exceptions\EncryptionKeyException($message);
          $this->logError($message);
        }
        return false;
      }
      //$this->logInfo('Encrypting value for key '.$key);
      $value = $this->encrypt($value, $this->appEncryptionKey);
    }
    $this->setAppValue($key, $value);
    return true;
  }

  /**
   * Encrypt the given value with the given encryption
   * key. Internally, the first 4 bytes contain the length of $value
   * as string in hexadecimal notation, the following 32 bytes contain
   * the MD5 checksum of $value, starting at byte 36 follows the
   * data. Everyting is encrypted, and a BASE64 encoded representation
   * of the encoded data is stored int the data-base.
   *
   * @param $value The data to encrypt
   *
   * @param $enckey The encrypt key.
   *
   * @return The encrypted and encoded data.
   *
   * @throws Exceptions\EncryptionFailedException
   */
  public function encrypt($value, $enckey)
  {
    // Store the size in the first 4 bytes in order not to have to
    // rely on padding. We store the value in hexadecimal notation
    // in order to keep text-fields as text fields.
    $value = strval($value);
    if (!empty($enckey)) {
      try {
        $value = $this->crypto->encrypt($value, $enckey);
      } catch (\Throwable $t) {
         throw new Exceptions\EncryptionFailedException($this->l->t('Encrypt failed'), $t->getCode(), $t);
      }
    }
    return $value;
  }

  /**
   * Decrypt $value using the specified encryption key $enckey. If
   * $enckey is empty or unset, no decryption is attempted. This
   * function also checks against the internally stored MD5 sum.
   *
   * @param $value The encrypted and BASE64 encoded data.
   *
   * @param $enckey The encryption key or an empty string or
   * nothing.
   *
   * @return The decrypted data in case of success, or false
   * otherwise. If either @c $value or @c enckey is empty the return
   * value is just passed argument @c value.
   *
   * @throws Exceptions\DecryptionFailedException
   */
  public function decrypt($value, $enckey)
  {
    if (!empty($enckey) && !empty($value)) {
      // not encrypted hack
      if (substr($value, -2, 2) !== '|3') {
        return $value;
      }
      try {
         $value = $this->crypto->decrypt($value, $enckey);
      } catch (\Throwable $t) {
        throw new Exceptions\DecryptionFailedException($this->l->t('Decrypt failed'), $t->getCode(), $t);
      }
    }
    return $value;
  }

  public function verifyHash($value, $hash)
  {
    return $value !== null && (empty($hash) || $this->hasher->verify($value, $hash));
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
