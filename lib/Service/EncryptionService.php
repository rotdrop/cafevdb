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

use \ioncube\phpOpensslCryptor\Cryptor;

class EncryptionService
{
  use \OCA\CAFEVDB\Traits\SessionTrait;

  /** @var string */
  private $appName;

  /** @var IConfig */
  private $containerConfig;

  /** @var string */
  private $cryptor;
  
  /** @var string */
  private $userPrivateKey = null;
  
  /** @var string */
  private $appEncryptionKey = null;
  
  public function __construct($appName, IConfig $containerConfig, SessionService $sessionService) {
    $this->appName = $appName;
    $this->containerConfig = $containerConfig;
    $this->sessionService = $sessionService;
    $this->cryptor = new Cryptor();
  }

  public function initUserPrivateKey($login, $password)
  {
    $privKey = $this->getUserValue($login, 'privateSSLKey', null);
    if ($privKey == '') {
      // Ok, generate one. But this also means that we have not yet
      // access to the data-base encryption key.
      $this->generateKeyPair($login, $password);
      $privKey = $this->getUserValue($login, 'privateSSLKey');
    }

    $privKey = openssl_pkey_get_private($privKey, $password);
    if ($privKey === false) {
      return;
    }

    // Success. Store the private key. This may or may not be
    // permanent storage. ATM, it is not.
    $this->setUserPrivateKey($privKey);
  }

  /**Return the private key. First try local storage as static class
   * variable. If unset, try the session. Else return @c false.
   */
  public function getUserPrivateKey() {
    if (empty($this->userPrivateKey)) {
      $this->userPrivateKey = $this->sessionRetrieveValue('privatekey');
    }
    return $this->userPrivateKey;
  }
  
  // To distribute the encryption key for the data base and
  // application configuration values we use a public/private key pair
  // for each user. Then the admin-user can distribute the global
  // encryption pair to each authorized user (in the orchestra-group)
  // using the pulic key. Then the user logs into owncloud, the key is
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
  }
  
  /**Set the private key used to decode some sensible data like the
   * general shared encryption key and so forth.
   *
   * @param $key The key to safe.
   *
   * @param $storeDecrypted Whether or not to export the key before
   * storing it. This will convert the key-argument, which may only be
   * a resource, to a string-representation of the key.
   *
   * @param $permanent Whether or not to store the key in permanent
   * storage, which means something at least persisitent during the
   * lifetime of the PHP session. Whether or not this is really the
   * PHP $_SESSION data is left open.
   *
   * @return @c true in the case of success, @c false otherwise.
   *
   */
  private function setUserPrivateKey($key, $storeDecrypted = false, $permanent = false)
  {
    if ($storeDecrypted) {
      // Really: DO NOT DO THIS. PERIOD.
      if (openssl_pkey_export($key, $key) === false) {
        return false;
      }
    }
    $this->$userPrivateKey = $key;
    if ($permanent) {
      $this->sessionStoreValue('privatekey', $key);
    }
    return true;
  }

  public function initEncryptionKey($login)
  {
    // Fetch the encrypted "user" key from the preferences table
    $usrdbkey = $this->getUserValue($login, 'encryptionkey');

    if (empty($usrdbkey)) {
      // No key -> unencrypted, maybe
      \OCP\Util::writeLog($this->appName, "No Encryption Key", \OCP\Util::DEBUG);
      return false;
    }

    $usrdbkey = base64_decode($usrdbkey);

    $privKey = $this->getUserPrivateKey();

    // Try to decrypt the $usrdbkey
    if (openssl_private_decrypt($usrdbkey, $usrdbkey, $privKey) === false) {
      \OCP\Util::writeLog($this->appName, "Decryption of EncryptionKey failed", \OCP\Util::DEBUG);
      return false;
    }

    // Now try to decrypt the data-base encryption key
    $this->setEncryptionKey($usrdbkey);
    $sysdbkey = $this->getValue('encryptionkey');

    if ($sysdbkey != $usrdbkey) {
      // Failed
      $this->setEncryptionKey('');
      \OCP\Util::writeLog($this->appName, "EncryptionKeys do not match", \OCP\Util::DEBUG);
      return false;
    }

    // Otherwise store the key in the session data
    $this->setEncryptionKey($sysdbkey);
    return true;
  }

  /**Store the encryption key in the session data. This cannot (i.e.:
   *must not) fail.
   *
   * @param $key The encryption key to store.
   */
  private function setAppEncryptionKey($key) {
    //\OCP\Util::writeLog(Config::APP_NAME, "Storing encryption key: ".$key, \OCP\Util::DEBUG);
    $this->appEncryptionKey = $key;
    $this->sessionStoreValue('encryptionkey', $key);
  }

  /**Retrieve the encryption key from the session data.
   *
   * @return @c false in case of error, otherwise the encryption key.
   */
  private function getAppEncryptionKey() {
    if (empty($this->appEncryptionKey)) {
      $this->appEncryptionKey = $this->sessionRetrieveValue('encryptionkey');
    }
    return $this->appEncryptionKey;
  }

  /**Check the validity of the encryption. In order to do so we fetch
   * an encrypted representation of the key from the OC config space
   * and try to decrypt that key with the given key. If the decrypted
   * key matches our key, then we accept the key.
   *
   * @bug This scheme of storing a key which is encrypted with itself
   * is a security issue. Think about it. It really is.
   */
  private function encryptionKeyValid($sesdbkey = null)
  {
    empty($sesdbkey) && ($sesdbkey = $this->getEncryptionKey());

    // Fetch the encrypted "system" key from the app-config table
    $sysdbkey = $this->getAppValue('encryptionkey');

    // Now try to decrypt the data-base encryption key
    $sysdbkey = $this->decrypt($sysdbkey, $sesdbkey);

    return $sysdbkey !== false && $sysdbkey == $sesdbkey;
  }  
  
  function getAppValue($key, $default = null)
  {
    return $this->containerConfig->getAppValue($this->appName, $key, $default);
  }

  function setAppValue($key, $value)
  {
    return $this->containerConfig->setAppValue($this->appName, $key, $value);
  }
  
  function getUserValue($userId, $key, $default = null)
  {
    return $this->containerConfig->getUserValue($userId, $this->appName, $key, $default);
  }

  function setUserValue($uesrId, $key, $value)
  {
    return $this->containerConfig->setUserValue($userId, $this->appName, $key, $value);
  }

  public function getValue($key, $default = null, $strict = false)
  {
    if ($strict && !$this->encryptionKeyValid()) {
      return false;
    }

    $enckey = $this->getEncryptionKey();
    $value  = $this->getAppValue($key, $default);

    $value  = self::decrypt($value, $enckey);

    return $value;
  }

  /**Encrypt the given value and store it in the application settings
   * table of OwnCloud.
   *
   * @param[in] $key Configuration key.
   * @param[in] $value Configuration value.
   */
  public function setValue($key, $value)
  {
    $enckey = $this->getEncryptionKey();

    $value = self::encrypt($value, $enckey);
    $this->setAppValue($key, $value);
  }
  
  /**Encrypt the given value with the given encryption
   * key. Internally, the first 4 bytes contain the length of $value
   * as string in hexadecimal notation, the following 32 bytes contain
   * the MD5 checksum of $value, starting at byte 36 follows the
   * data. Everyting is encrypted, and a BASE64 encoded representation
   * of the encoded data is stored int the data-base.
   *
   * @param[in] $value The data to encrypt
   *
   * @param[in] $enckey The encrypt key.
   *
   * @return The encrypted and encoded data.
   */
  static private function encrypt($value, $enckey)
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
   * @param[in] $value The encrypted and BASE64 encoded data.
   *
   * @param[in] $enckey The encryption key or an empty string or
   * nothing.
   *
   * @return The decrypted data in case of success, or false otherwise.
   */
  static public function decrypt($value, $enckey)
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
