<?php
/* Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2020 Claus-Justus Heine <himself@claus-justus-heine.de>
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
use OCP\ISession;
use OCP\IUserSession;
use OCP\Security\ICrypto;
use OCP\Authentication\LoginCredentials\IStore as ICredentialsStore;
use OCP\Authentication\LoginCredentials\ICredentials;
use OCP\ILogger;
use OCP\IL10N;

// Perhaps just use NC builtin encryption via ICrypto?
use \ioncube\phpOpensslCryptor\Cryptor;

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
  use \OCA\CAFEVDB\Traits\SessionTrait;
  use \OCA\CAFEVDB\Traits\LoggerTrait;

  /** @var string */
  private $appName;

  /** @var IConfig */
  private $containerConfig;

  /** @var  */
  private $cryptor;

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
    , IConfig $containerConfig
    , ISession $session
    , IUserSession $userSession
    , ICrypto $crypto
    , ICredentialsStore $credentialsStore
    , ILogger $logger
    , IL10N $l10n
  ) {
    $this->appName = $appName;
    $this->containerConfig = $containerConfig;
    $this->session = $session;
    $this->cryptor = new Cryptor();
    $this->crypto = $crypto;
    $this->logger = $logger;
    $this->l = $l10n;
    try {
      $this->user = $userSession->getUser();
      $this->userId = $this->user->getUID();
    } catch (\Throwable $t) {
      $this->logException($t);
      $this->user = null;
      $this->userId = null;
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
        $this->appEncryptionKey = '';
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

  public function getAppEncryptionKey()
  {
    return $this->appEncryptionKey;
  }

  /**
   * Initialize the per-user public/private key pair, which
   * inparticular is used to propagate the app's encryption key to all
   * relevant users.
   */
  public function initUserKeyPair()
  {
    if (empty($this->userId) || empty($this->userPassword)) {
      throw new \Exception($this->l->t('Cannot initialize SSL key-pair without user and password'));
    }

    $privKey = $this->getUserValue($this->userId, 'privateSSLKey', null);
    $pubKey = $this->getUserValue($this->userId, 'publicSSLKey', null);
    if (empty($privKey) || empty($pubKey)) {
      // Ok, generate one. But this also means that we have not yet
      // access to the data-base encryption key.
      $this->generateUserKeyPair($this->userId, $this->userPassword);
      $privKey = $this->getUserValue($this->userId, 'privateSSLKey');
    }

    $privKey = openssl_pkey_get_private($privKey, $this->userPassword);

    $this->userPrivateKey = $privKey;
    $this->userPublicKey = $pubKey;
  }

  // To distribute the encryption key for the data base and
  // application configuration values we use a public/private key pair
  // for each user. Then the admin-user can distribute the global
  // encryption key to each authorized user (in the orchestra-group)
  // using the public key. When the user logs into owncloud, the key is
  // decrypted with the users private key (which again is secured by
  // the user's password.
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

    return true;
  }

  public function getUserEncryptionKey()
  {
    return $this->getSharedPrivateValue('encryptionkey', null);
  }

  public function setUserEncryptionKey($key)
  {
    return $this->setSharedPrivateValue('encryptionkey', $key);
  }

  public function initAppEncryptionKey()
  {
    if (!$this->bound()) {
      throw new \Exception($this->l-t>('Cannot initialize global encryption key without bound user credentials.'));
    }

    $usrdbkey = $this->getUserEncryptionKey();
    if (empty($usrdbkey)) {
      // No key -> unencrypted
      $this->logDebug("No Encryption Key");
      $this->appEncryptionKey = ''; // not null, just empty
      return false;
    }

    // Now try to decrypt the data-base encryption key
    $this->appEncryptionKey = $usrdbkey;
    $sysdbkey = $this->getConfigValue('encryptionkey');

    if ($sysdbkey != $usrdbkey) {
      // Failed
      $this->appEncryptionKey = null;
      throw new \Exception($this->l->t('Stored keys for application and user do not match'));
    }
    return true;
  }

  /**
   * Get and decrypt an personal 'user' value encrypted with the
   * public key of key-pair. Throw an exception if $this is not bound
   * to a user and password, see self::bind().
   *
   * @return string Decrypt config value.
   */
  public function getSharedPrivateValue($key, $default = null)
  {
    if (!$this->bound()) {
      throw new \Exception($this->l->t('Cannot decrypt private values without bound user credentials'));
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
      throw new \Exception($this->l->t('Decryption of "%s" with private key of user "%s" failed.', [ $key, $this->userId ]));
    }

    return $value;
  }

  /**
   * Encrypt the given value with the user's public key.
   */
  public function setSharedPrivateValue($key, $value)
  {
    if (!$this->bound()) {
      throw new \Exception($this->l->t('Cannot encrypt private values without bound user credentials'));
    }

    if (openssl_public_encrypt($value, $encrypted, $this->userPublicKey) === false) {
      throw new \Exception($this->l->t('Encrypting value for key "%s" with public key of user "%s" failed.', [ $key, $this->userId ]));
    }

    $encrypted = base64_encode($encrypted);

    $this->setUserValue($this->userId, $key, $encrypted);
  }

  /**
   * Check the validity of the encryption key. In order to do so we fetch
   * an encrypted representation of the key from the OC config space
   * and try to decrypt that key with the given key. If the decrypted
   * key matches our key, then we accept the key.
   */
  public function encryptionKeyValid()
  {
    if (!$this->bound()) {
      throw new \Exception($this->l->t('Cannot validate app encryption key without bound user credentials'));
    }

    // Fetch the encrypted "system" key from the app-config table
    $sysdbkey = $this->getAppValue('encryptionkey');

    // Now try to decrypt the data-base encryption key
    $sysdbkey = $this->decrypt($sysdbkey, $this->appEncryptionKey);

    return $sysdbkey == $sesdbkey;
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

  public function getConfigValue($key, $default = null, $strict = false)
  {
    if ($strict && !$this->encryptionKeyValid()) {
      return false;
    }

    $value  = $this->getAppValue($key, $default);

    $value  = $this->decrypt($value, $this->appEncryptionKey);

    return $value;
  }

  /**
   * Encrypt the given value and store it in the application settings
   * table of OwnCloud.
   *
   * @param $key Configuration key.
   * @param $value Configuration value.
   */
  public function setConfigValue($key, $value, bool $strict = false)
  {
    if ($strict && !$this->encryptionKeyValid()) {
      return false;
    }

    if (!empty($this->appEncryptionKey)) {
      $value = $this->encrypt($value, $this->appEncryptionKey);
    }
    $this->setAppValue($key, $value);
    return true;
  }

  /**Encrypt the given value with the given encryption
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
   */
  //@@TODO catch exceptions
  private function encrypt($value, $enckey)
  {
    // Store the size in the first 4 bytes in order not to have to
    // rely on padding. We store the value in hexadecimal notation
    // in order to keep text-fields as text fields.
    $value = strval($value);
    $md5   = md5($value);
    $cnt   = sprintf('%04x', strlen($value));
    $src   = $cnt.$md5.$value; // 4 Bytes + 32 Bytes + X bytes of data
    $value = $this->cryptor->encrypt($src, $enckey, Cryptor::FORMAT_B64);
    return $value;
  }

  /**Decrypt $value using the specified encryption key $enckey. If
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
   */
  //@@TODO catch exceptions
  private function decrypt($value, $enckey)
  {
    if (!empty($enckey) && !empty($value)) {
      $value = $this->cryptor->decrypt($value, $enckey, Cryptor::FORMAT_B64);
      $cnt = intval(substr($value, 0, 4), 16);
      $md5 = substr($value, 4, 32);
      $value = substr($value, 36, $cnt);

      if (strlen($md5) != 32 || strlen($value) != $cnt || $md5 != md5($value)) {
        return false;
      }
    }
    return $value;
  }
}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
