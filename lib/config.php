<?php

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
eventduration
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
streetAddressName01
streetAddressName02
streetAddressStreet
streetAddressHouseNumber
streetAddressCity
streetAddressZIP
streetAddressCountry
bankAccountOwner
bankAccountIBAN
bankAccountBLZ
bankAccountBIC
bankAccountCreditorIdentifier
projectsbalancefolder
projectsfolder
executiveBoardTable
memberTable
';
  const DFLT_CALS = 'concerts,rehearsals,other,management';
// L::t('concerts') L::t('rehearsals') L::t('other') L::t('management')
  const APP_BASE  = 'apps/cafevdb/';
  public static $prefix = false;
  public static $triggers = false;
  public static $debug_query = false;
  public static $pmeopts = array();
  public static $dbopts = array();
  public static $opts = array();
  public static $cgiVars = array();
  public static $Languages = array();
  public static $wysiwygEditors = array('tinymce' => 'TinyMCE',
                                        'ckeditor' => 'CKEditor');
  private static $initialized = false;

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

  public static function loginListener($params)
  {
    $group = self::getAppValue('usergroup', '');
    $user = $params['uid'];
    if ($group != '' && \OC_Group::inGroup($user, $group)) {
      self::initPrivateKey($user, $params['password']);
      self::initEncryptionKey($user);
    }
  }

  public static function changePasswordListener($params) {
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
   * CAVEAT: for the headervisibility we still need to do this
   * ourselves, so the hook is not connected ATM.
   */
  public static function jsLoadHook($params) {
    //$jsAssign = &$params['array'];
    //$jsAssign['DWEmbed'] = 'DWEmbed || {}';

    self::init();

    $headervisibility = Util::cgiValue('headervisibility', 'expanded');
    $user             = \OCP\USER::getUser();
    $tooltips         = \OCP\Config::getUserValue($user, 'cafevdb', 'tooltips', '');
    $language         = \OCP\Config::getUserValue($user, 'core', 'lang', 'en');

    $array = array(
      "CAFEVDB.headervisibility" => "'".$headervisibility."'",
      "CAFEVDB.toolTips" => ($tooltips == "off" ? 'false' : 'true'),
      "CAFEVDB.wysiwygEditor" => "'".self::$opts['editor']."'",
      "CAFEVDB.language" => "'".$language."'",
      "PHPMYEDIT.filterSelectPlaceholder" => "'".L::t("Select a filter option.")."'",
      "PHPMYEDIT.filterSelectNoResult" => "'".L::t("No values match.")."'",
      "PHPMYEDIT.filterSelectChosen" => "true",
      "PHPMYEDIT.filterSelectChosenTitle" => "'".L::t("Select from the pull-down menu. ".
                                                      "Double-click will submit the form.")."'",
      "PHPMYEDIT.inputSelectPlaceholder" => "'".L::t("Select an option.")."'",
      "PHPMYEDIT.inputSelectNoResult" => "'".L::t("No values match.")."'",
      "PHPMYEDIT.inputSelectChosen" => "true",
      "PHPMYEDIT.inputSelectChosenTitle" => "'".L::t("Select from the pull-down menu.")."'",
      "PHPMYEDIT.chosenPixelWidth" => "['projectname']",
      );

    // Echo it
    echo "var CAFEVDB = CAFEVDB || {};\n";
    echo "CAFEVDB.Projects = CAFEVDB.Projects || {};\n";
    echo "var PHPMYEDIT = PHPMYEDIT || {} ;\n";
    foreach ($array as  $setting => $value) {
      echo($setting ."=".$value.";\n");
    }    
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

    $privKey = openssl_pkey_get_private ($privKey, $password);
    if ($privKey === false) {
      return;
    }

    if (false) {
      // Probably not necessary and not a good idea.
      if (openssl_pkey_export($privKey, $privKey) === false) {
        return;
      }
    }

    // Success. Store the decrypted private key in the session data.
    self::setPrivateKey($privKey);
  }

  static public function initEncryptionKey($login)
  {
    // Fetch the encrypted "user" key from the preferences table
    $usrdbkey = self::getUserValue('encryptionkey', '', $login);

    if ($usrdbkey == '') {
      // No key -> unencrypted, maybe
      return;
    }

    $usrdbkey = base64_decode($usrdbkey);

    $privKey = self::getPrivateKey();

    // Try to decrypt the $usrdbkey
    if (openssl_private_decrypt($usrdbkey, $usrdbkey, $privKey) === false) {
      return;
    }

    // Now try to decrypt the data-base encryption key
    self::setEncryptionKey($usrdbkey);
    $sysdbkey = self::getValue('encryptionkey');
    
    if ($sysdbkey != $usrdbkey) {
      // Failed
      self::setEncryptionKey('');
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

  static public function setPrivateKey($key) {
    \OC::$session->set('CAFEVDB\\privatekey', $key);
  }

  static public function getPrivateKey() {
    return \OC::$session->exists('CAFEVDB\\privatekey')
      ? \OC::$session->get('CAFEVDB\\privatekey')
      : '';
  }

  static public function setEncryptionKey($key) {
    \OC::$session->set('CAFEVDB\\encryptionkey', $key);
  }

  static public function getEncryptionKey() {
    return \OC::$session->exists('CAFEVDB\\encryptionkey')
      ? \OC::$session->get('CAFEVDB\\encryptionkey')
      : '';
  }

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
              throw new \Exception(L::t("Decryption of `%s`@`%s` failed", array($valueKey, $tableName)));
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

  static public function getSetting($key, $default = '', $strict = false)
  {
      $value = self::getValue($key, $strict);
      if (!$value || $value == '') {
          $value = $default;
      }
      return $value;
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

    // Fetch possibly encrypted config values from the OC data-base
    self::decryptConfigValues();

    if (!self::$prefix) {
      self::$prefix = self::APP_BASE . "lib/";
    }
    if (!self::$triggers) {
      self::$triggers = self::$prefix . "triggers/";
    }

    self::$dbopts['hn'] = @self::$opts['dbserver'];
    self::$dbopts['un'] = @self::$opts['dbuser'];
    self::$dbopts['pw'] = @self::$opts['dbpassword'];
    self::$dbopts['db'] = @self::$opts['dbname'];

    foreach (array_keys(self::$dbopts) as $key) {
      self::$pmeopts[$key] = self::$dbopts[$key];
    }

    self::$debug_query = self::getUserValue('debugmode') === 'on' ? true : false;

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

    // Initially hide the filter fields
    self::$pmeopts['cgi']['append'][self::$pmeopts['cgi']['prefix']['sys'].'fl'] = 0;

    // Navigation style: B - buttons (default), T - text links, G - graphic links
    // Buttons position: U - up, D - down (default)
    self::$pmeopts['navigation'] = 'GUDM';
    self::$pmeopts['miscphp'] = 'dummy';
    self::$pmeopts['misccssclass'] = 'email';
    self::$pmeopts['labels']['Misc'] = 'Em@il';
    //self::$pmeopts['labels']['Sort Field'] = 'Sortierfeld';

    self::$pmeopts['css']['textarea'] = '';
    self::$opts['editor'] = self::getUserValue('wysiwygEditor', 'tinymce');

    self::$opts['phpmyadmin'] = 'https://ch.homelinux.net:8888/phpmyadmin/index.php?user=camerata&db=camerata';
    self::$opts['email'] = array('name'     => 'Em@il',
                                 'URL'      => 'mailto:$link',
                                 'URLdisp'  => '$value',
                                 'select'   => 'T',
                                 'maxlen'   => 768,
                                 'sort'     => true,
                                 'nowrap'   => true,
                                 'escape'   => true);
    
    self::$opts['money'] = array('name' => 'Unkostenbeitrag<BR/>(Gagen negativ)',
                                 //'phpview' => self::$prefix . 'money.inc.php',
                                 'mask'  => '%02.02f'.'&euro;',
                                 'css'   => array('postfix' => 'money'),
                                 //'align' => 'right',
                                 'select' => 'N',
                                 'maxlen' => '6',
                                 'escape' => false,
                                 'sort' => true);
    
    self::$opts['datetime'] = array('select'   => 'T',
                                    'maxlen'   => 19,
                                    'sort'     => true,
                                    'datemask' => 'd.m.Y H:i:s',
                                    'css'      => array('postfix' => 'datetime'),
                                    );

    self::$opts['birthday'] = array('name'     => strval(L::t('birthday')),
                                    'select'   => 'T',
                                    'maxlen'   => 10,
                                    'sort'     => true,
                                    'css'      => array('postfix' => 'birthday'),
                                    'datemask' => 'd.m.Y');
//                                    'default' => '01.01.1900');
    //  add as needed
    self::$opts['languages'] = explode(',','Deutsch,Englisch,Französich,Spanisch,Polnisch,Russisch,Japanisch,Koreanisch');
    sort(self::$opts['languages']);

    self::$cgiVars = array('Template' => 'blog',
                           'MusicianId' => -1,
                           'ProjectId' => -1,
                           'Project' => '',
                           'RecordsPerPage' => -1);
    self::$pmeopts['tooltips'] = ToolTips::toolTips();
  }

  /**Return an (English!) tool-tip for the given key.
   */
  public static function toolTips($key)
  {
      $tip = '';
      if (isset(self::$pmeopts['tooltips'][$key])) {
          $tip = self::$pmeopts['tooltips'][$key];
      }
      return $tip;
  }
};

/**Check for a usable configuration.
 */
class ConfigCheck
{
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
   * @param[in] $projectsfolder The name of the folder. The name may
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

    trigger_error("Path: ".print_r($projectsFolder, true), E_USER_NOTICE);
      
    foreach ($projectsFolder as $pathComponent) {
      $path .= '/'.$pathComponent;
      trigger_error("Path: ".$path, E_USER_NOTICE);
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

}

?>
