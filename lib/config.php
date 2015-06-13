<?php
/* Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2014 Claus-Justus Heine <himself@claus-justus-heine.de>
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
namespace CAFEVDB
{

// PHP shit
//date_default_timezone_set('Europe/Berlin');

/**Class for handling configuration values.
 */
  class Config
  {
    const APP_NAME  = 'cafevdb';
    const DISPLAY_NAME = 'Camerata DB';
    const MCRYPT_CIPHER = MCRYPT_RIJNDAEL_128;
    const MCRYPT_MODE = MCRYPT_MODE_ECB;
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
secretaryId
treasurerId
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
    public static $prefix = false;
    public static $pmeopts = array();
    public static $dbopts = array();
    public static $opts = array();
    public static $cgiVars = array();
    public static $Languages = array();
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
    public static $session = false;

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

    public static function inGroup($user = null)
    {
      if (!$user) {
        $user = \OC_User::getUser();
      }
      $group = self::getAppValue('usergroup', '');
      return $group != '' && \OC_Group::inGroup($user, $group);
    }

    public static function loginListener($params)
    {
      //self::init();
      $group = self::getAppValue('usergroup', '');
      $user = $params['uid'];
      if ($group != '' && \OC_Group::inGroup($user, $group)) {
        // Fetch the encryption key and store in the session data
        self::initPrivateKey($user, $params['password']);
        self::initEncryptionKey($user);
      }
    }

    public static function logoutListener($params)
    {
      self::init();

      // OC does not destroy the session on logout, additionally, there
      // is not alway a logout event. But if there is one, we destroy
      // our session data.
      self::$session->clearValues();
    }

    public static function changePasswordListener($params) {
      self::init();
      $group = self::getAppValue('usergroup', '');
      $user = $params['uid'];
      if ($group != '' && \OC_Group::inGroup($user, $group)) {
        self::recryptEncryptionKey($params['uid'], $params['password']);
      }
    }

    /**Configuration hook. The array can be filled with arbitrary
     * variable-value pairs (global scope). Additionally it is possible
     * to emit any other java-script code here, although this is
     * probably not the intended usage.
     *
     * We do not use this but instead stick to our own config.php which
     * is loader AFTER all JS structures have been initialized, this is
     * simply much easier.
     */
    public static function jsLoadHook($params) {
      //$jsAssign = &$params['array'];
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

    /**A short-cut, redirecting to the stock functions for the
     * logged-in user.
     */
    static public function getUserValue($key, $default = false, $user = false)
    {
      ($user === false) && ($user = \OCP\USER::getUser());
      return \OCP\Config::getUserValue($user, self::APP_NAME, $key, $default);
    }

    /**A short-cut, redirecting to the stock functions for the
     * logged-in user.
     */
    static public function setUserValue($key, $value, $user = false)
    {
      ($user === false) && ($user = \OCP\USER::getUser());
      return \OCP\Config::setUserValue($user, self::APP_NAME, $key, $value);
    }

    /**A short-cut, redirecting to the stock functions for the app.
     */
    static public function getAppValue($key, $default = false)
    {
      return \OC_AppConfig::getValue(self::APP_NAME, $key, $default);
    }

    /**A short-cut, redirecting to the stock functions for the app.
     */
    static public function setAppValue($key, $value)
    {
      return \OC_AppConfig::setValue(self::APP_NAME, $key, $value);
    }

    /**A short-cut, redirecting to the stock functions for the app.
     */
    static public function deleteAppKey($key)
    {
      return \OC_AppConfig::deleteKey(self::APP_NAME, $key);
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
      self::setEncryptionKey($usrdbkey);
      $sysdbkey = self::getValue('encryptionkey');

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

    /**Store something in the session-data. It is completely left open
     * how this is done.
     *
     * sessionStoreValue() and sessionRetrieveValue() should be the only
     * interface points to the PHP session (except for, ahem, tweaks).
     */
    static public function sessionStoreValue($key, $value)
    {
      if (self::$session === false) {
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
      if (self::$session === false) {
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
      if ($key == '') {
        return $key;
      }

      $keySize  = mcrypt_module_get_algo_key_size(self::MCRYPT_CIPHER);
      $keySizes = mcrypt_module_get_supported_key_sizes(self::MCRYPT_CIPHER);
      if (count($keySizes) == 0) {
        $keySizes = array($keySize);
      }
      sort($keySizes);
      $maxSize = $keySizes[count($keySizes) - 1];
      $klen = strlen($key);
      if ($klen > $maxSize) {
        $key = substr($key, 0, $maxSize);
      } else {
        foreach($keySizes as $size) {
          if ($size >= $klen) {
            $key = str_pad($key, $size, "\0");
            break;
          }
        }
      }
      return $key;
    }

    /**Generate a random key of the maximum supported size */
    static public function generateEncryptionKey()
    {
      $size = mcrypt_module_get_algo_key_size(self::MCRYPT_CIPHER);
      return Util::generateRandomBytes($size);
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
        $value = base64_encode(mcrypt_encrypt(MCRYPT_RIJNDAEL_128,
                                              $enckey,
                                              $src,
                                              MCRYPT_MODE_ECB));
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
        $value = mcrypt_decrypt(MCRYPT_RIJNDAEL_128,
                                $enckey,
                                base64_decode($value),
                                MCRYPT_MODE_ECB);
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
        mySQL::query("UNLOCK TABLES");
        if ($ownConnection) {
          mySQL::close($handle);
        }

        throw $exception;
      }

      // Unlock again
      mySQL::query("UNLOCK TABLES");
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

    /**Return true if the logged in user is the treasurer. We do this
     * by matching the real name of the logged in user.
     */
    static public function isTreasurer($uid = null)
    {
      $musicianId = Config::getSetting('treasurerId', -1);
      if ($musicianId == -1) {
        return false;
      }
      return self::matchDisplayName($musicianId);
    }

    /**Return true if the logged in user is the secretary. We do this
     * by matching the real name of the logged in user.
     */
    static public function isSecretary($uid = null)
    {
      $musicianId = Config::getSetting('secretaryId', -1);
      if ($musicianId == -1) {
        return false;
      }
      return self::matchDisplayName($musicianId);
    }

    /**Return true if the logged in user is the president. We do this
     * by matching the real name of the logged in user.
     */
    static public function isPresident($uid = null)
    {
      $musicianId = Config::getSetting('presidentId', -1);
      if ($musicianId == -1) {
        return false;
      }
      return self::matchDisplayName($musicianId);
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

    static public function init() {

      if (self::$initialized == true) {
        return;
      }
      self::$initialized = true;

      if (self::$session === false) {
        self::$session = new Session();
      }

      date_default_timezone_set(Util::getTimezone());

      // Fetch possibly encrypted config values from the OC data-base
      self::decryptConfigValues();

      if (!self::$prefix) {
        self::$prefix = self::APP_BASE . "lib/";
      }

      // Oh well. This is just a hack to pass the OC-user to the
      // changelog table of PME.
      $_SERVER['REMOTE_USER'] = \OC_User::getUser();

      self::$dbopts['hn'] = @self::$opts['dbserver'];
      self::$dbopts['un'] = @self::$opts['dbuser'];
      self::$dbopts['pw'] = @self::$opts['dbpassword'];
      self::$dbopts['db'] = @self::$opts['dbname'];

      foreach (array_keys(self::$dbopts) as $key) {
        self::$pmeopts[$key] = self::$dbopts[$key];
      }

      self::$expertmode = self::getUserValue('expertmode', 'off') === 'on';
      $debug = explode(',', self::getUserValue('debug', ''));
      foreach ($debug as $key) {
        self::$debug[$key] = true;
      }
      if (self::$expertmode) {
        foreach(self::$wysiwygEditors as $key => &$value) {
          $value['enabled'] = true;
        }
      }

      self::$pmeopts['url']['images'] = self::APP_BASE . 'img/';
      global $HTTP_SERVER_VARS;
      self::$pmeopts['page_name'] = $HTTP_SERVER_VARS['PHP_SELF'].'?app=cafevdb';

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
                                   //'phpview' => self::$prefix . 'money.inc.php',
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
        'german' => L::t('German'),
        'english' => L::t('English'),
        'french' => L::t('French'),
        'spanish' => L::t('Spanish'),
        'portuguese' => L::t('Portuguese'),
        'polish' => L::t('Polish'),
        'russian' => L::t('Russian'),
        'chinese' => L::t('Chinese'),
        'japanese' => L::t('Japaneese'),
        'korean' => L::t('Korean'));


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
    public static function toolTips($key)
    {
      $tip = '';
      if (isset(self::$toolTipsArray[$key]) && !empty(self::$toolTipsArray[$key])) {
        $tip = self::$toolTipsArray[$key];
      } else if (self::$debug['tooltips']) {
        $tip = L::t("Unknown Tooltip for key `%s' requested.", array($key));
      }
      return htmlspecialchars($tip);
    }
  };

/**Check for a usable configuration.
 */
  class ConfigCheck
  {
    public static function checkImapServer($host, $port, $secure, $user, $password)
    {
      $oldReporting = ini_get('error_reporting');
      ini_set('error_reporting', $oldReporting & ~E_STRICT);

      $imap = new \Net_IMAP($host, $port, $secure == 'starttls' ? true : false, 'UTF-8');
      $result = $imap->login($user, $password) === true;
      $imap->disconnect();

      ini_set('error_reporting', $oldReporting);

      return $result;
    }

    public static function checkSmtpServer($host, $port, $secure, $user, $password)
    {
      $result = true;

      $mail = new \PHPMailer(true);
      $mail->CharSet = 'utf-8';
      $mail->SingleTo = false;
      $mail->IsSMTP();

      $mail->Host = $host;
      $mail->Port = $port;
      switch ($secure) {
      case 'insecure': $mail->SMTPSecure = ''; break;
      case 'starttls': $mail->SMTPSecure = 'tls'; break;
      case 'ssl':      $mail->SMTPSecure = 'ssl'; break;
      default:         $mail->SMTPSecure = ''; break;
      }
      $mail->SMTPAuth = true;
      $mail->Username = $user;
      $mail->Password = $password;

      try {
        $mail->SmtpConnect();
        $mail->SmtpClose();
      } catch (\Exception $exception) {
        $result = false;
      }

      return $result;
    }

    /**Check whether the shared object exists. Note: this function has
     *to be executed under the uid of the user the object belongs
     *to. See ConfigCheck::sudo().
     *
     * @param[in] $id The @b numeric id of the object (not the name).
     *
     * @param[in] $group The group to share the item with.
     *
     * @param[in] $type The type of the item, for exmaple calendar,
     * event, folder, file etc.
     *
     * @return @c true for success, @c false on error.
     */
    public static function groupSharedExists($id, $group, $type)
    {
      // First check whether the object is already shared.
      $shareType  = \OCP\Share::SHARE_TYPE_GROUP;
      $groupPerms = (\OCP\PERMISSION_CREATE|
                     \OCP\PERMISSION_READ|
                     \OCP\PERMISSION_UPDATE|
                     \OCP\PERMISSION_DELETE);

      $token =\OCP\Share::getItemShared($type, $id, \OCP\Share::FORMAT_NONE);

      // Note: getItemShared() returns an array with one element, strip
      // the outer array!
      if (is_array($token) && count($token) == 1) {
        $token = array_shift($token);
        return isset($token['permissions']) &&
          ($token['permissions'] & $groupPerms) == $groupPerms;
      } else {
        return false;
      }
    }

    /**Share an object between the members of the specified group. Note:
     * this function has to be executed under the uid of the user the
     * object belongs to. See ConfigCheck::sudo().
     *
     * @param[in] $id The @b numeric id of the object (not the name).
     *
     * @param[in] $group The group to share the item with.
     *
     * @param[in] $type The type of the item, for exmaple calendar,
     * event, folder, file etc.
     *
     * @return @c true for success, @c false on error.
     */
    public static function groupShareObject($id, $group, $type = 'calendar')
    {
      $groupPerms = (\OCP\PERMISSION_CREATE|
                     \OCP\PERMISSION_READ|
                     \OCP\PERMISSION_UPDATE|
                     \OCP\PERMISSION_DELETE);

      // First check whether the object is already shared.
      $shareType   = \OCP\Share::SHARE_TYPE_GROUP;
      $token = \OCP\Share::getItemShared($type, $id);
      if ($token !== false && (!is_array($token) || count($token) > 0)) {
        return \OCP\Share::setPermissions($type, $id, $shareType, $group, $groupPerms);
      }
      // Otherwise it should be legal to attempt a new share ...

      // try it ...
      return \OCP\Share::shareItem($type, $id, $shareType, $group, $groupPerms);
    }

    /**Fake execution with other user-id. Note that this function will
     * catch any exception thrown while executing the callback-function
     * and in case an exeption has been called will re-throw the
     * exception.
     *
     * @param[in] $uid The "fake" uid.
     *
     * @param[in] $callback function.
     *
     * @return Whatever the callback-functoni returns.
     *
     */
    public static function sudo($uid, $callback)
    {
      \OC_Util::setupFS(); // This must come before trying to sudo

      $olduser = \OC_User::getUser();
      \OC_User::setUserId($uid);
      try {
        $result = call_user_func($callback);
      } catch (\Exception $exception) {
        \OC_User::setUserId($olduser);

        throw $exception;
      }
      \OC_User::setUserId($olduser);

      return $result;
    }

    /**Return an array with necessary configuration items, being either
     * true or false, depending the checks performed. The summary
     * component is just the logic and of all other items.
     *
     * @return bool
     * array('summary','orchestra','usergroup','shareowner','sharedfolder','database','encryptionkey')
     *
     * where summary is a bool and everything else is
     * array('status','message') where 'message' should be empty if
     * status is true.
     */
    public static function configured()
    {
      $result = array();

      foreach (array('orchestra','usergroup','shareowner','sharedfolder','database','encryptionkey') as $key) {
        $result[$key] = array('status' => false, 'message' => '');
      }

      $key ='orchestra';
      try {
        $result[$key]['status'] = Config::encryptionKeyValid() && Config::getValue('orchestra');
      } catch (\Exception $e) {
        $result[$key]['message'] = $e->getMessage();
      }

      $key = 'encryptionkey';
      try {
        $result[$key]['status'] = $result['orchestra']['status'] && Config::encryptionKeyValid();
      } catch (\Exception $e) {
        $result[$key]['message'] = $e->getMessage();
      }

      $key = 'database';
      try {
        $result[$key]['status'] = $result['orchestra']['status'] && self::databaseAccessible();
      } catch (\Exception $e) {
        $result[$key]['message'] = $e->getMessage();
      }

      $key = 'usergroup';
      try {
        $result[$key]['status'] = self::ShareGroupExists();
      } catch (\Exception $e) {
        $result[$key]['message'] = $e->getMessage();
      }

      $key = 'shareowner';
      try {
        $result[$key]['status'] = $result['usergroup']['status'] && self::shareOwnerExists();
      } catch (\Exception $e) {
        $result[$key]['message'] = $e->getMessage();
      }

      $key = 'sharedfolder';
      try {
        $result[$key]['status'] = $result['shareowner']['status'] && self::sharedFolderExists();
      } catch (\Exception $e) {
        $result[$key]['message'] = $e->getMessage();
      }

      $summary = true;
      foreach ($result as $key => $value) {
        $summary = $summary && $value['status'];
      }
      $result['summary'] = $summary;

      return $result;
    }

    /**Return @c true if the share-group is set as application
     * configuration option and exists.
     */
    public static function shareGroupExists()
    {
      $group = Config::getAppValue('usergroup');

      if (!\OC_Group::groupExists($group)) {
        return false;
      }

      return true;
    }

    /**Return @c true if the share-owner exists and belongs to the
     * orchestra user group (and only to this group).
     *
     * @param[in] $shareowner Optional. If unset, then the uid is
     * fetched from the application configuration options.
     *
     * @return bool, @c true on success.
     */
    public static function shareOwnerExists($shareowner = '')
    {
      $sharegroup = Config::getAppValue('usergroup');
      $shareowner === '' && $shareowner = Config::getValue('shareowner');

      if ($shareowner === false) {
        return false;
      }

      if (!\OC_user::isEnabled($shareowner)) {
        return false;
      }

      /* Ok, the user exists and is configured as "share-owner" in our
       * poor orchestra app, now perform additional consistency checks.
       *
       * How paranoid should we be?
       */
      $groups = \OC_Group::getUserGroups($shareowner);

      // well, the share-owner should in turn only be owned by the
      // group.
      if (count($groups) != 1) {
        return false;
      }

      // The one and only group should be our's.
      if ($groups[0] != $sharegroup) {
        return false;
      }

      // Add more checks as needed ... ;)
      return true;
    }

    /**Make sure the "sharing" user exists, create it when necessary.
     * May throw an exception.
     *
     * @param[in] $shareowner The account holding the shared resources.
     *
     * @return bool, @c true on success.
     */
    public static function checkShareOwner($shareowner)
    {
      if (!$sharegroup = Config::getAppValue('usergroup', false)) {
        return false; // need at least this group!
      }

      // Create the user if necessary
      if (!\OC_User::userExists($shareowner) &&
          !\OC_User::createUser($shareowner,
                                \OC_User::generatePassword())) {
        return false;
      }

      // Sutff the user in its appropriate group
      if (!\OC_Group::inGroup($shareowner, $sharegroup) &&
          !\OC_Group::addToGroup($shareowner, $sharegroup)) {
        return false;
      }

      return self::shareOwnerExists($shareowner);
    }

    /**We require that the share-owner owns a directory shared with the
     * orchestra group. Check whether this folder exists.
     *
     * @param[in] $sharedfolder Optional. If unset, the name is fetched
     * from the application configuration options.
     *
     * @return bool, @c true on success.
     */
    public static function sharedFolderExists($sharedfolder = '')
    {
      if (!self::shareOwnerExists()) {
        return false;
      }

      $sharegroup   = Config::getAppValue('usergroup');
      $shareowner   = Config::getValue('shareowner');
      $groupadmin   = \OCP\USER::getUser();

      $sharedfolder == '' && $sharedfolder = Config::getSetting('sharedfolder', '');

      if ($sharedfolder == '') {
        // not configured
        return false;
      }

      //$id = \OC\Files\Cache\Cache::getId($sharedfolder, $vfsroot);
      $result = self::sudo($shareowner, function() use ($sharedfolder, $sharegroup) {
          $user         = \OCP\USER::getUser();
          $vfsroot = '/'.$user.'/files';

          if ($sharedfolder[0] != '/') {
            $sharedfolder = '/'.$sharedfolder;
          }

          \OC\Files\Filesystem::initMountPoints($user);

          $rootView = new \OC\Files\View($vfsroot);
          $info = $rootView->getFileInfo($sharedfolder);

          if ($info) {
            $id = $info['fileid'];
            return ConfigCheck::groupSharedExists($id, $sharegroup, 'folder');
          } else {
            \OC_Log::write('CAFEVDB', 'No file info for  ' . $sharedfolder, \OC_Log::ERROR);
            return false;
          }
        });

      return $result;
    }

    /**Check for existence of the shared folder and create it when not
     * found.
     *
     * @param[in] $sharedfolder The name of the folder.
     *
     * @return bool, @c true on success.
     */
    public static function checkSharedFolder($sharedfolder)
    {
      if ($sharedfolder == '') {
        return false;
      }

      if ($sharedfolder[0] != '/') {
        $sharedfolder = '/'.$sharedfolder;
      }

      if (self::sharedFolderExists($sharedfolder)) {
        // no need to create
        return true;
      }

      $sharegroup = Config::getAppValue('usergroup');
      $shareowner = Config::getValue('shareowner');
      $groupadmin = \OCP\USER::getUser();

      if (!\OC_SubAdmin::isSubAdminofGroup($groupadmin, $sharegroup)) {
        \OC_Log::write(Config::APP_NAME,
                       "Permission denied: ".$groupadmin." is not a group admin of ".$sharegroup.".",
                       \OC_Log::ERROR);
        return false;
      }

      // try to create the folder and share it with the group
      $result = self::sudo($shareowner, function() use ($sharedfolder, $sharegroup, $user) {
          $user    = \OCP\USER::getUser();
          $vfsroot = '/'.$user.'/files';

          // Create the user data-directory, if necessary
          $user_root = \OC_User::getHome($user);
          $userdirectory = $user_root . '/files';
          if( !is_dir( $userdirectory )) {
            mkdir( $userdirectory, 0770, true );
          }
          if( !is_dir( $userdirectory )) {
            return false;
          }

          \OC\Files\Filesystem::initMountPoints($user);

          $rootView = new \OC\Files\View($vfsroot);

          if ($rootView->file_exists($sharedfolder) &&
              (!$rootView->is_dir($sharedfolder) ||
               !$rootView->isSharable($sharedfolder)) &&
              !$rootView->unlink($sharedfolder)) {
            return false;
          }

          if (!$rootView->file_exists($sharedfolder) &&
              !$rootView->mkdir($sharedfolder)) {
            return false;
          }

          if (!$rootView->file_exists($sharedfolder) ||
              !$rootView->is_dir($sharedfolder)) {
            throw new \Exception('Still does not exist.');
          }

          // Now it should exist as directory. Share it
          // Nice ass-hole stuff. We need the id.

          //\OC\Files\Cache\Cache::scanFile($sharedfolder, $vfsroot);
          //$id = \OC\Files\Cache\Cache::getId($sharedfolder, $vfsroot);
          $info = $rootView->getFileInfo($sharedfolder);
          if ($info) {
            $id = $info['fileid'];
            if (!ConfigCheck::groupShareObject($id, $sharegroup, 'folder') ||
                !ConfigCheck::groupSharedExists($id, $sharegroup, 'folder')) {
              return false;
            }
          } else {
            \OC_Log::write('CAFEVDB', 'No file info for ' . $sharedfolder, \OC_Log::ERROR);
            return false;
          }

          return true; // seems to be ok ...
        });

      return self::sharedFolderExists($sharedfolder);
    }

    /**Check for existence of the project folder and create it when not
     * found.
     *
     * @param[in] $projectsFolder The name of the folder. The name may
     * be composed of several path components.
     *
     * @return bool, @c true on success.
     */
    public static function checkProjectsFolder($projectsFolder)
    {
      $sharedFolder = Config::getValue('sharedfolder');

      if (!self::sharedFolderExists($sharedFolder)) {
        return false;
      }

      $sharegroup = Config::getAppValue('usergroup');
      $shareowner = Config::getValue('shareowner');
      $user       = \OCP\USER::getUser();

      if (!\OC_SubAdmin::isSubAdminofGroup($user, $sharegroup)) {
        \OC_Log::write(Config::APP_NAME,
                       "Permission denied: ".$user." is not a group admin of ".$sharegroup.".",
                       \OC_Log::ERROR);
        return false;
      }

      /* Ok, then there should be a folder /$sharedFolder */

      $fileView = \OC\Files\Filesystem::getView();

      $projectsFolder = trim(preg_replace('|[/]+|', '/', $projectsFolder), "/");
      $projectsFolder = explode('/', $projectsFolder);

      $path = '/'.$sharedFolder;

      //trigger_error("Path: ".print_r($projectsFolder, true), E_USER_NOTICE);

      foreach ($projectsFolder as $pathComponent) {
        $path .= '/'.$pathComponent;
        //trigger_error("Path: ".$path, E_USER_NOTICE);
        if (!$fileView->is_dir($path)) {
          if ($fileView->file_exists($path)) {
            $fileView->unlink($path);
          }
          $fileView->mkdir($path);
          if (!$fileView->is_dir($path)) {
            return false;
          }
        }
      }

      return true;
    }

    /**Check whether we have data-base access by connecting to the
     * data-base server and selecting the configured data-base.
     *
     * @return bool, @c true on success.
     */
    public static function databaseAccessible($opts = array())
    {
      try {
        Config::init();
        if (empty($opts)) {
          $opts = Config::$dbopts;
        }

        $handle = mySQL::connect($opts, false /* don't die */, true);
        if ($handle === false) {
          return false;
        }

        if (Events::configureDatabase($handle) === false) {
          mySQL::close($handle);
          return false;
        }

        mySQL::close($handle);
        return true;

      } catch(\Exception $e) {
        mySQL::close($handle);
        throw $e;
      }

      return false;
    }

  }

} // namespace CAFEVDB

?>
