<?php
/* Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016, 2020 Claus-Justus Heine <himself@claus-justus-heine.de>
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

/**CamerataDB namespace to prevent name-collisions.
 */
namespace OCA\CAFEVDB\Common;

use \ioncube\phpOpensslCryptor\Cryptor;
use OCP\IGroupManager;
use OCP\IUserSession;
use OCP\IConfig;

// @@TODO: many of these function should go into the Util class. Also:
// Config should be rather dynamic than static.

/**Class for handling configuration values.
 */
class Config
{
  const APP_NAME  = 'cafevdb';
  const DISPLAY_NAME = 'Camerata DB';
  /**Configuration keys. In order for encryption/decryption to work
   * properly, every config setting has to be listed here.
   */
  const CFG_KEYS ='
orchestra
dbserver
dbuser
dbpassword
dbname
shareowner
sharedfolder
concertscalendar
concertscalendarid
rehearsalscalendar
rehearsalscalendarid
othercalendar
othercalendarid
managementcalendar
managementcalendarid
financecalendar
financecalendarid
eventduration
sharedaddressbook
sharedaddressbookid
emailuser
emailpassword
emailfromname
emailfromaddress
smtpserver
smtpport
smtpsecure
imapserver
imapport
imapsecure
emailtestaddress
emailtestmode
phpmyadmin
phpmyadminoc
sourcecode
sourcedocs
ownclouddev
presidentId
presidentUserId
presidentUserGroup
secretaryId
secretaryUserId
secretaryUserGroup
treasurerId
treasurerUserId
treasurerUserGroup
streetAddressName01
streetAddressName02
streetAddressStreet
streetAddressHouseNumber
streetAddressCity
streetAddressZIP
streetAddressCountry
phoneNumber
bankAccountOwner
bankAccountIBAN
bankAccountBLZ
bankAccountBIC
bankAccountCreditorIdentifier
projectsbalancefolder
projectsfolder
executiveBoardTable
executiveBoardTableId
memberTable
memberTableId
redaxoPreview
redaxoArchive
redaxoRehearsals
redaxoTrashbin
redaxoTemplate
redaxoConcertModule
redaxoRehearsalsModule
';
  const DFLT_CALS = 'concerts,rehearsals,other,management,finance';
  // L::t('concerts') L::t('rehearsals') L::t('other') L::t('management') L::t('finance')
  const APP_BASE  = 'apps/cafevdb/';
  public static $privateKey = false; ///< Storage
  public static $pmeopts = array();
  public static $dbopts = array();
  public static $opts = array();
  public static $cgiVars = array();
  public static $Languages = array();
  public static $locale = '';
  public static $currency = '';
  public static $wysiwygEditors = array('tinymce' => array('name' => 'TinyMCE',
                                                           'enabled' => true),
                                        // ckeditor still uses excessive inline js-code. NOGO.
                                        'ckeditor' => array('name' => 'CKEditor',
                                                            'enabled' => false)
  );
  public static $expertmode = false;
  public static $debug = array('general' => false,
                               'query' => false,
                               'request' => false,
                               'tooltips' => false,
                               'emailform' => false);
  private static $initialized = false;
  private static $toolTipsArray = array();
  public static $session = null;

  /** @var IUserSession */
  private static $userSession;

  /** @var IConfig */
  private static $containerConfig;

  /** @var IGroupManager */
  private static $groupManager;

  /**List of data-base entries that need to be encrypted. We should
   * invent some "registration" infrastructre for this AND first do a
   * survey about existing solutions.
   */
  private static function encryptedDataBaseTables()
  {
    return array(Finance::$dataBaseInfo);
  }

  private static function configKeys()
  {
    return preg_split('/\s+/', trim(self::CFG_KEYS));
  }

  public static function getUserId()
  {
    self::init();
    return self::$userSession->getUser()->getUID();
  }

  public static function inGroup($user = null, $group = null)
  {
    if (!$user) {
      $user = self::getUserId();
    }
    if (empty($group)) {
      $group = self::getAppValue('usergroup', '');
    }
    return !empty($group) && \OC_Group::inGroup($user, $group);
  }

  /**Return email and display name of the admin user for error
   * feedback messages.
   */
  static public function adminContact()
  {
    $name = \OCP\User::getDisplayName('admin');
    $email = \OCP\Config::getUserValue('admin', 'settings', 'email');
    return array('name' => $name,
                 'email' => $email);
  }

  static private function containerConfig($force = false) {
    self::init();
    return self::$containerConfig;
  }

  /**A short-cut, redirecting to the stock functions for the
   * logged-in user.
   */
  static public function getUserValue($key, $default = false, $user = false)
  {
    ($user === false) && ($user = self::getUserId());
    return self::containerConfig()->getUserValue($user, self::APP_NAME, $key, $default);
  }

  /**A short-cut, redirecting to the stock functions for the
   * logged-in user.
   */
  static public function setUserValue($key, $value, $user = false)
  {
    ($user === false) && ($user = self::getUserId());
    return self::containerConfig()->setUserValue($user, self::APP_NAME, $key, $value);
  }

  /**A short-cut, redirecting to the stock functions for the app.
   */
  static public function getAppValue($key, $default = false)
  {
    return self::containerConfig()->getAppValue(self::APP_NAME, $key, $default);
  }

  /**A short-cut, redirecting to the stock functions for the app.
   */
  static public function setAppValue($key, $value)
  {
    return self::containerConfig()->setAppValue(self::APP_NAME, $key, $value);
  }

  /**A short-cut, redirecting to the stock functions for the app.
   */
  static public function deleteAppKey($key)
  {
    return self::containerConfig()->deleteAppValue(self::APP_NAME, $key);
  }

  static public function initPrivateKey($login, $password)
  {
    $privKey = self::getUserValue('privateSSLKey', '', $login);
    if ($privKey == '') {
      // Ok, generate one. But this also means that we have not yet
      // access to the data-base encryption key.
      self::generateKeyPair($login, $password);
      $privKey = self::getUserValue('privateSSLKey', '', $login);
    }

    $privKey = openssl_pkey_get_private($privKey, $password);
    if ($privKey === false) {
      return;
    }

    // Success. Store the private key. This may or may not be
    // permanent storage. ATM, it is not.
    self::setPrivateKey($privKey);
  }

  static public function initEncryptionKey($login)
  {
    // Fetch the encrypted "user" key from the preferences table
    $usrdbkey = self::getUserValue('encryptionkey', '', $login);

    if ($usrdbkey == '') {
      // No key -> unencrypted, maybe
      \OCP\Util::writeLog(Config::APP_NAME, "No Encryption Key", \OCP\Util::DEBUG);
      return;
    }

    $usrdbkey = base64_decode($usrdbkey);

    $privKey = self::getPrivateKey();

    // Try to decrypt the $usrdbkey
    if (openssl_private_decrypt($usrdbkey, $usrdbkey, $privKey) === false) {
      \OCP\Util::writeLog(Config::APP_NAME, "Decryption of EncryptionKey failed", \OCP\Util::DEBUG);
      return;
    }

    // Now try to decrypt the data-base encryption key
    $usrdbkey = self::padEncryptionKey($usrdbkey);
    self::setEncryptionKey($usrdbkey);
    $sysdbkey = self::padEncryptionKey(self::getValue('encryptionkey'));

    if ($sysdbkey != $usrdbkey) {
      // Failed
      self::setEncryptionKey('');
      \OCP\Util::writeLog(Config::APP_NAME, "EncryptionKeys do not match", \OCP\Util::DEBUG);
      return;
    }

    // Otherwise store the key in the session data
    self::setEncryptionKey($sysdbkey);
  }

  static public function recryptEncryptionKey($login, $password, $enckey = false)
  {
    // ok, new password, generate a new key-pair. Then re-encrypt the
    // global encryption key with the new key.

    // new key pair
    self::generateKeyPair($login, $password);

    // store the re-encrypted key in the configuration space
    self::setUserKey($login, $enckey);
  }

  // To distribute the encryption key for the data base and
  // application configuration values we use a public/private key pair
  // for each user. Then the admin-user can distribute the global
  // encryption pair to each authorized user (in the orchestra-group)
  // using the pulic key. Then the user logs into owncloud, the key is
  // decrypted with the users private key (which again is secured by
  // the user's password.
  static public function generateKeyPair($login, $password)
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

    self::setUserValue('publicSSLKey', $pubKey, $login);
    self::setUserValue('privateSSLKey', $privKey, $login);
  }

  static public function setUserKey($user, $enckey = false)
  {
    if ($enckey === false) {
      $enckey = self::getEncryptionKey();
    }

    if ($enckey != '') {
      $enckey = self::padEncryptionKey($enckey);
      $pubKey = self::getUserValue('publicSSLKey', '', $user);
      $usrdbkey = '';
      if ($pubKey == '' ||
          openssl_public_encrypt($enckey, $usrdbkey, $pubKey) === false) {
        return false;
      }
      $usrdbkey = base64_encode($usrdbkey);
    } else {
      $usrdbkey = '';
    }

    $pubKey = self::setUserValue('encryptionkey', $usrdbkey, $user);

    return true;
  }

  /**Close the active session, if any. */
  static public function sessionClose()
  {
    if (empty(self::$session)) {
      return;
    }
    self::$session->close();
    self::$session = null;
  }


  /**Store something in the session-data. It is completely left open
   * how this is done.
   *
   * sessionStoreValue() and sessionRetrieveValue() should be the only
   * interface points to the PHP session (except for, ahem, tweaks).
   */
  static public function sessionStoreValue($key, $value)
  {
    if (empty(self::$session)) {
      self::$session = new Session();
    }
    self::$session->storeValue($key, $value);
  }

  /**Fetch something from the session-data. It is completely left open
   * how this is done.
   *
   * @param $key The key tagging the desired data.
   *
   * @param $default What to return if the data is not
   * available. Defaults to @c false.
   *
   * sessionStoreValue() and sessionRetrieveValue() should be the only
   * interface points to the PHP session (except for, ahem, tweaks).
   */
  static public function sessionRetrieveValue($key, $default = false)
  {
    if (empty(self::$session)) {
      self::$session = new Session();
    }
    return self::$session->retrieveValue($key, $default);
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
  static public function setPrivateKey($key, $storeDecrypted = false, $permanent = false) {
    if ($storeDecrypted) {
      // Really: DO NOT DO THIS. PERIOD.
      if (openssl_pkey_export($key, $key) === false) {
        return false;
      }
    }
    if ($permanent) {
      self::sessionStoreValue('privatekey', $key);
    } else {
      self::$privateKey = $key;
    }
    return true;
  }

  /**Return the private key. First try local storage as static class
   * variable. If unset, try the session. Else return @c false.
   */
  static public function getPrivateKey() {
    if (self::$privateKey !== false) {
      return self::$privateKey;
    } else {
      return self::sessionRetrieveValue('privatekey');
    }
  }

  /**Pad the given key to a supprted length. */
  static private function padEncryptionKey($key)
  {
    return $key;
  }

  /**Store the encryption key in the session data. This cannot (i.e.:
   *must not) fail.
   *
   * @param $key The encryption key to store.
   */
  static public function setEncryptionKey($key) {
    //\OCP\Util::writeLog(Config::APP_NAME, "Storing encryption key: ".$key, \OCP\Util::DEBUG);
    $key = self::padEncryptionKey($key);
    self::sessionStoreValue('encryptionkey', $key);
  }

  /**Retrieve the encryption key from the session data.
   *
   * @return @c false in case of error, otherwise the encryption key.
   */
  static public function getEncryptionKey() {
    return self::sessionRetrieveValue('encryptionkey');
  }

  /**Check the validity of the encryption. In order to do so we fetch
   * an encrypted representation of the key from the OC config space
   * and try to decrypt that key with the given key. If the decrypted
   * key matches our key, then we accept the key.
   *
   * @bug This scheme of storing a key which is encrypted with itself
   * is a security issue. Think about it. It really is.
   */
  static public function encryptionKeyValid($sesdbkey = false)
  {
    if ($sesdbkey === false) {
      // Get the supposed-to-be key from the session data
      $sesdbkey = self::getEncryptionKey();
    } else {
      $sesdbkey = self::padEncryptionKey($sesdbkey);
    }

    // Fetch the encrypted "system" key from the app-config table
    $sysdbkey = self::getAppValue('encryptionkey');

    // Now try to decrypt the data-base encryption key
    $sysdbkey = self::decrypt($sysdbkey, $sesdbkey);
    $sysdbkey = self::padEncryptionKey($sysdbkey);

    return $sysdbkey !== false && $sysdbkey == $sesdbkey;
  }

  /**Decrypt all configuration values stored in the data base.
   */
  static public function decryptConfigValues()
  {
    $keys = self::configKeys();

    foreach ($keys as $key) {
      if (self::getValue($key) === false) {
        return false;
      }
    }
    return true;
  }

  /**Encrypt all configuration values stored in self::opts[}.
   */
  static public function encryptConfigValues()
  {
    $keys = self::configKeys();

    foreach ($keys as $key) {
      if (!isset(self::$opts[$key])) {
        self::$opts[$key] = '';
      }
      self::setValue($key, self::$opts[$key]);
    }
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
   * @param[in] $enckey The encrypt key. If empty or unset, no
   * encryption is performed.
   *
   * @return The encrypted and encoded data.
   */
  static public function encrypt($value, $enckey = false)
  {
    if ($enckey === false) {
      $enckey = self::getEncryptionKey();
    }

    if ($enckey != '') {
      // Store the size in the first 4 bytes in order not to have to
      // rely on padding. We store the value in hexadecimal notation
      // in order to keep text-fields as text fields.
      $value = strval($value);
      $md5   = md5($value);
      $cnt   = sprintf('%04x', strlen($value));
      $src   = $cnt.$md5.$value; // 4 Bytes + 32 Bytes + X bytes of data
      $value = Cryptor::Encrypt($src, $enckey, Cryptor::FORMAT_B64);
    }
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
  static public function decrypt($value, $enckey = false)
  {
    if ($enckey === false) {
      $enckey = self::getEncryptionKey();
    }

    if ($enckey != '' && $value != '') {
      $value = Cryptor::Decrypt($value, $enckey, Cryptor::FORMAT_B64);
      $cnt = intval(substr($value, 0, 4), 16);
      $md5 = substr($value, 4, 32);
      $value = substr($value, 36, $cnt);

      if (strlen($md5) != 32 || strlen($value) != $cnt || $md5 != md5($value)) {
        return false;
      }
    }
    return $value;
  }

  /**Re-encrypt all encrypted data in case of a key change.
   */
  static public function recryptDataBaseColumns($newKey, $oldKey, $handle = false)
  {
    $ownConnection = $handle === false;

    // pad both keys to required length, acts as no-op on empty keys
    // or keys with supported length.
    $newKey = self::padEncryptionKey($newKey);
    $oldKey = self::padEncryptionKey($oldKey);

    if ($ownConnection) {
      self::init();
      $handle = mySQL::connect(self::$pmeopts);
    }

    $allTables = array();
    foreach (self::encryptedDataBaseTables() as $table) {
      $allTables[] = $table['table'];
    }

    // Lock tables until done
    mySQL::query("LOCK TABLES `".implode('` WRITE, `', $allTables)."` WRITE", $handle);
    //throw new \Exception("LOCK TABLES `".implode('` WRITE, `', $allTables)."` WRITE");

    try {
      foreach (self::encryptedDataBaseTables() as $table) {
        $tableName     = $table['table'];
        $primaryKey    = $table['key'];
        $columns       = $table['encryptedColumns'];
        $queryColumns  = '`'.$primaryKey.'`,`'.implode('`,`', $columns).'`';

        $query = "SELECT ".$queryColumns." FROM `".$tableName."` WHERE 1";
        //throw new \Exception($query);
        $result = mySQL::query($query, $handle);
        while ($row = mysql::fetch($result)) {
          $query = array();
          foreach ($columns as $valueKey) {
            $value = self::decrypt($row[$valueKey], $oldKey);
            if ($value === false) {
              //throw new \Exception(L::t("Decryption of `%s`@`%s` failed", array($valueKey, $tableName)));
              throw new \Exception(L::t("Decryption of `%s`@`%s` failed", array($oldKey, strlen($oldKey))));
            }
            $value = self::encrypt($value, $newKey);
            if ($value === false) {
              throw new Exception(L::t("Encryption of `%s`@`%s` failed", array($valueKey, $tableName)));
            }
            $query[] = "`".$valueKey."` = '".$value."'";
          }
          $query = "UPDATE `".$tableName."` SET ".implode(', ', $query)." WHERE `".$primaryKey."` = ".$row[$primaryKey];
          if (mySQL::query($query, $handle) === false) {
            throw new \Exception(L::t("Unable to update table data at index %s", arry($primaryKey)));
          }
        }
      }
    } catch (\Exception $exception) {
      // Unlock again
      mySQL::query("UNLOCK TABLES", $handle);
      if ($ownConnection) {
        mySQL::close($handle);
      }

      throw $exception;
    }

    // Unlock again
    mySQL::query("UNLOCK TABLES", $handle);
    if ($ownConnection) {
      mySQL::close($handle);
    }

    return true;
  }

  /**Encrypt the given value and store it in the application settings
   * table of OwnCloud.
   *
   * @param[in] $key Configuration key.
   * @param[in] $value Configuration value.
   */
  static public function setValue($key, $value)
  {
    $enckey = self::getEncryptionKey();

    self::$opts[$key] = $value;
    $value = self::encrypt($value, $enckey);
    self::setAppValue($key, $value);
  }

  /**Like getValue(), but with default. */
  static public function getSetting($key, $default = '', $strict = false)
  {
    $value = self::getValue($key, $strict);
    if (!$value || $value == '') {
      $value = $default;
    }
    return $value;
  }

  /**Helper function to determine if this user has a special role.*/
  static private function matchDisplayName($musicianId, $uid = null)
  {
    $name = Musicians::fetchName($musicianId);
    $dbName = trim($name['firstName'].' '.$name['lastName']);
    $dbName = preg_replace('/\s+/', ' ', $dbName);

    $ocName = trim(\OC_User::getDisplayName($uid));
    $ocName = preg_replace('/\s+/', ' ', $ocName);

    return strtolower($ocName) == strtolower($dbName);
  }

  /**Return true if the logged in user is the treasurer.*/
  static public function isTreasurer($uid = null, $strict = false)
  {
    empty($uid) && $uid = self::getUserId();
    $musicianId = Config::getSetting('treasurerId', -1);
    if ($musicianId == -1) {
      return false;
    }
    $userId = Config::getSetting('treasurerUserId', null);
    if (self::inGroup($userId) && $userId === $uid) {
      return true;
    }
    if ($strict) {
      return false;
    }
    // check for group-membership
    $group = Config::getSetting('treasurerUserGroupId', null);
    return !empty($group) && \OC_Group::inGroup($uid, $group);
  }

  /**Return email and display name of the treasurer user for error
   * feedback messages.
   */
  static public function treasurerContact()
  {
    $treasurer = Config::getSetting('treasurerUserId', null);
    if (!empty($treasurer)) {
      $name = \OCP\User::getDisplayName($treasurer);
      $email = \OCP\Config::getUserValue($treasurer, 'settings', 'email');
      return array('name' => $name,
                   'email' => $email);
    }
  }

  /**Return true if the logged in user is the secretary.*/
  static public function isSecretary($uid = null)
  {
    empty($uid) && $uid = self::getUserId();
    $musicianId = Config::getSetting('secretaryId', -1);
    if ($musicianId == -1) {
      return false;
    }
    $userId = Config::getSetting('secretaryUserId', null);
    return self::inGroup($userId) && $userId === $uid;
  }

  /**Return true if the logged in user is the president.*/
  static public function isPresident($uid = null)
  {
    empty($uid) && $uid = self::getUserId();
    $musicianId = Config::getSetting('presidentId', -1);
    if ($musicianId == -1) {
      return false;
    }
    $userId = Config::getSetting('presidentUserId', null);
    return self::inGroup($userId) && $userId === $uid;
  }

  /**Return true if the logged in user is in the treasurer group. */
  static public function inTreasurerGroup($uid = null)
  {
    if (self::isTreasurer($uid)) {
      return true; // ;)
    }
    $gid = Config::getSetting('treasurerGroupId', null);
    if (empty($gid)) {
      return false;
    }
    return self::inGroup($uid, $gid);
  }

  /**Return true if the logged in user is in the secretary group. */
  static public function inSecretaryGroup($uid = null)
  {
    if (self::isSecretary($uid)) {
      return true; // ;)
    }
    $gid = Config::getSetting('secretaryGroupId', null);
    if (empty($gid)) {
      return false;
    }
    return self::inGroup($uid, $gid);
  }

  /**Return true if the logged in user is in the president group. */
  static public function inPresidentGroup($uid = null)
  {
    if (self::isPresident($uid)) {
      return true; // ;)
    }
    $gid = Config::getSetting('presidentGroupId', null);
    if (empty($gid)) {
      return false;
    }
    return self::inGroup($uid, $gid);
  }

  static public function getValue($key, $strict = false)
  {
    if ($strict && !self::encryptionKeyValid()) {
      return false;
    }

    $enckey = self::getEncryptionKey();
    $value  = self::getAppValue($key, '');
    $value  = self::decrypt($value, $enckey);
    if ($value !== false) {
      self::$opts[$key] = $value;
    }

    return $value;
  }

  /**Return the name and generate a per-user cache-directory
   */
  static public function userCacheDirectory($path = '', $user = null)
  {
    empty($user) && $user = self::getUserId();
    if (empty($user)) {
      return false;
    }

    $view = new \OC\Files\View('/' . $user);
    $subDir = self::APP_NAME.'/cache/'.$path;
    if (!$view->is_dir($subDir)) {
      $subDir = self::APP_NAME;
      if (!$view->is_dir($subDir)) {
        $view->mkdir($subDir);
      }
      $subDir .= '/cache';
      if (!$view->is_dir($subDir)) {
        $view->mkdir($subDir);
      }
      if (!empty($path)) {
        $subDir .= '/'.$path;
        if (!$view->is_dir($subDir)) {
          $view->mkdir($subDir);
        }
      }
    }

    return $view->getLocalFolder($subDir);
  }

  /**Return the locale according to the street-address country.
   */
  public static function getOrchestraLocale()
  {
    self::init();
    $countryCode = Config::getValue('streetAddressCountry');
    return Util::getLocale($countryCode);
  }

  /**Initialize all this stuff. This fetches all config values and
   * keeps a cache of them in memory.
   */
  static public function init() {

    if (self::$initialized == true) {
      return;
    }
    self::$initialized = true;

    if (empty(self::$session)) {
      self::$session = new Session();
    }

    //@@TODO use dependency injection in controller
    self::$userSession = \OC::$server->getUserSession();
    self::$containerConfig = \OC::$server->getConfig();
    self::$groupManager = \OC::$server->getGroupManager();

    //@@TODO: still necessasry??? This does not work ATM
    //date_default_timezone_set(Util::getTimezone());

    // Fetch possibly encrypted config values from the OC data-base
    self::decryptConfigValues();

    // Oh well. This is just a hack to pass the OC-user to the
    // changelog table of PME.
    $_SERVER['REMOTE_USER'] = self::getUserId();

    self::$dbopts['hn'] = @self::$opts['dbserver'];
    self::$dbopts['un'] = @self::$opts['dbuser'];
    self::$dbopts['pw'] = @self::$opts['dbpassword'];
    self::$dbopts['db'] = @self::$opts['dbname'];

    foreach (array_keys(self::$dbopts) as $key) {
      self::$pmeopts[$key] = self::$dbopts[$key];
    }

    self::$expertmode = self::getUserValue('expertmode', 'off') === 'on';
    $debug = Util::explode(',', self::getUserValue('debug', ''));
    foreach ($debug as $key) {
      self::$debug[$key] = true;
    }
    if (self::$expertmode) {
      foreach(self::$wysiwygEditors as $key => &$value) {
        $value['enabled'] = true;
      }
    }

    self::$locale = self::getOrchestraLocale();
    self::$currency = Util::currencySymbol(self::$locale);

    self::$pmeopts['url']['images'] = self::APP_BASE . 'img/';
    self::$pmeopts['page_name'] = $_SERVER['PHP_SELF'].'?app=cafevdb';

    self::$pmeopts['logtable'] = 'changelog';
    //self::$pmeopts['language'] = 'DE-UTF8';
    self::$pmeopts['cgi']['append']['PME_sys_fl'] = 1;

    // Set default prefixes for variables for PME
    self::$pmeopts['js']['prefix']               = 'PME_js_';
    self::$pmeopts['dhtml']['prefix']            = 'PME_dhtml_';
    self::$pmeopts['cgi']['prefix']['operation'] = 'PME_op_';
    self::$pmeopts['cgi']['prefix']['sys']       = 'PME_sys_';
    self::$pmeopts['cgi']['prefix']['data']      = 'PME_data_';

    self::$pmeopts['display']['disabled'] = 'disabled'; // or 'readonly'
    self::$pmeopts['display']['readonly'] = 'readonly'; // or 'disabled'
    self::$pmeopts['display']['query'] = 'always';

    // Initially hide the filter fields
    self::$pmeopts['cgi']['append'][self::$pmeopts['cgi']['prefix']['sys'].'fl'] = 0;

    // Navigation style: B - buttons (default), T - text links, G - graphic links
    // Buttons position: U - up, D - down (default)
    self::$pmeopts['navigation'] = 'GUDM';
    self::$pmeopts['misc'] = array('php' => function() { return true; },
                                   'css' => array('major' => 'email'));
    self::$pmeopts['labels']['Misc'] = 'Em@il';
    //self::$pmeopts['labels']['Sort Field'] = 'Sortierfeld';

    self::$pmeopts['css']['separator'] = ' ';
    self::$pmeopts['css']['textarea'] = '';
    //self::$pmeopts['css']['position'] = true;
    self::$opts['editor'] = self::getUserValue('wysiwygEditor', 'tinymce');

    self::$opts['email'] = array('name'     => 'Em@il',
                                 'css'      => array('postfix' => ' email'),
                                 'URL'      => 'mailto:$link?recordId=$key',
                                 'URLdisp'  => '$value',
                                 'select'   => 'T',
                                 'maxlen'   => 768,
                                 'sort'     => true,
                                 'nowrap'   => true,
                                 'escape'   => true);

    self::$opts['money'] = array('name' => 'Unkostenbeitrag<BR/>(Gagen negativ)',
                                 'mask'  => '%02.02f'.' &euro;',
                                 'css'   => array('postfix' => ' money'),
                                 //'align' => 'right',
                                 'select' => 'N',
                                 'maxlen' => '8', // NB: +NNNN.NN = 8
                                 'escape' => false,
                                 'sort' => true);

    self::$opts['datetime'] = array('select'   => 'T',
                                    'maxlen'   => 19,
                                    'sort'     => true,
                                    'datemask' => 'd.m.Y H:i:s',
                                    'css'      => array('postfix' => ' datetime'),
    );

    self::$opts['date'] = array('name'     => strval(L::t('birthday')),
                                'select'   => 'T',
                                'maxlen'   => 10,
                                'sort'     => true,
                                'css'      => array('postfix' => ' birthday date'),
                                'datemask' => 'd.m.Y');
    self::$opts['birthday'] = self::$opts['date'];

    //  add as needed
    self::$opts['languages'] = array(
      '' => L::t('no preference'),
      'de' => L::t('German'),
      'en' => L::t('English'),
      'fr' => L::t('French'),
      'es' => L::t('Spanish'),
      'pt' => L::t('Portuguese'),
      'pl' => L::t('Polish'),
      'ru' => L::t('Russian'),
      'zh' => L::t('Chinese'),
      'ja' => L::t('Japaneese'),
      'ko' => L::t('Korean'));


    self::$cgiVars = array('Template' => 'blog',
                           'MusicianId' => -1,
                           'ProjectId' => -1,
                           'ProjectName' => false,
                           'RecordsPerPage' => self::getUserValue('pagerows', 20));
    self::$toolTipsArray = ToolTips::toolTips();
    self::$pmeopts['tooltips'] = self::$toolTipsArray;
    self::$pmeopts['inc'] = self::$cgiVars['RecordsPerPage'];
  }

  /**Return a translated tool-tip for the given key.
   */
  public static function toolTips($key, $subKey = null)
  {
    $tip = '';
    if (!empty($subKey)) {
      if (isset(self::$toolTipsArray[$key][$subKey])) {
        $tip = self::$toolTipsArray[$key][$subKey];
      } else if (isset(self::$toolTipsArray[$key]['default'])) {
        $tip = self::$toolTipsArray[$key]['default'];
      } else if (is_scalar(self::$toolTipsArray[$key])) {
        $tip = self::$toolTipsArray[$key];
      }
    } else if (isset(self::$toolTipsArray[$key])) {
      $tip = self::$toolTipsArray[$key];
      !empty($tip['default']) && $tip = $tip['default'];
    }

    if (!is_scalar($tip)) {
      $tip = '';
    }

    if (self::$debug['tooltips'] && empty($tip)) {
      if (!empty($subKey)) {
        $tip = L::t('Unknown Tooltip for key "%s-%s" requested.',
                    array($key, $subKey));
      } else {
        $tip = L::t('Unknown Tooltip for key "%s" requested.',
                    array($key));
      }
    }

    return htmlspecialchars($tip);
  }
};

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
