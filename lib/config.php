<?php

/**Basic namespace for the cafevdb application.
 */
namespace CAFEVDB
{

// PHP shit
date_default_timezone_set('Europe/Berlin');

class Config
{
  const APP_NAME  = 'cafevdb';
  // Separate by whitespace
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
sourcecode
sourcedocs
ownclouddev';
  const MD5_SUF  = '::MD5';
  const MD5_LEN  = 5;
  const DFLT_CALS = 'concerts,rehearsals,other,management';
// L::t('concerts') L::t('rehearsals') L::t('other') L::t('management')
  const APP_BASE  = 'apps/cafevdb/';
  public static $prefix = false;
  public static $triggers = false;
  public static $debug_query = false;
  public static $Wartung = true;
  public static $pmeopts = array();
  public static $dbopts = array();
  public static $opts = array();
  public static $cgiVars = array();
  public static $Languages = array();
  private static $initialized = false;

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
    $_SESSION['CAFEVDB\\privatekey'] = $key;
  }

  static public function getPrivateKey() {
    return isset($_SESSION['CAFEVDB\\privatekey']) ? $_SESSION['CAFEVDB\\privatekey'] : '';
  }

  static public function setEncryptionKey($key) {
    $_SESSION['CAFEVDB\\encryptionkey'] = $key;
  }

  static public function getEncryptionKey() {
    return isset($_SESSION['CAFEVDB\\encryptionkey']) ? $_SESSION['CAFEVDB\\encryptionkey'] : '';
  }

  static public function encryptionKeyValid($sesdbkey = false)
  {
    if ($sesdbkey === false) {
      // Get the supposed-to-be key from the session data
      $sesdbkey = self::getEncryptionKey();
    }

    // Fetch the encrypted "system" key from the app-config table
    $sysdbkey = self::getAppValue('encryptionkey');
    $md5sysdbkey = self::getAppValue('encryptionkey'.self::MD5_SUF);

    // Now try to decrypt the data-base encryption key
    $sysdbkey = self::decrypt($sysdbkey, $sesdbkey);

    if ($md5sysdbkey != '' && $md5sysdbkey != md5($sysdbkey)) {
        return false;
    }

    return $sysdbkey == $sesdbkey;
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

  // Decrypt and remove the padding. If $value is the empty string
  // then do nothing. If the encryption key is not set, then do not try to
  // decrypt.
  static public function decrypt($value, $enckey)
  {
    if ($enckey != '' && $value != '') {
      $value = mcrypt_decrypt(MCRYPT_RIJNDAEL_128,
                              $enckey,
                              base64_decode($value),
                              MCRYPT_MODE_ECB);
      $cnt   = intval(substr($value, 0, 4), 16);
      
      $value = substr($value, 4, $cnt);
      //$value = trim($value, "\0\4");
    }
    return $value;
  }

  static public function encrypt($value, $enckey)
  {
    if ($enckey != '') {
      // Store the size in the first 4 bytes in order not to have to
      // rely on padding. We store the value in hexadecimal notation
      // in order to keep text-fields as text fields.
      $cnt = sprintf('%04x', strlen($value));
      $value = base64_encode(mcrypt_encrypt(MCRYPT_RIJNDAEL_128,
                                            $enckey,
                                            $cnt.$value,
                                            MCRYPT_MODE_ECB)); 
    }
    return $value;
  }

  static public function setValue($key, $value)
  {
    $enckey = self::getEncryptionKey();

    self::$opts[$key] = $value;
    $md5value = $enckey != '' ? md5($value) : '';
    $value = self::encrypt($value, $enckey);
    self::setAppValue($key, $value);
    self::setAppValue($key.self::MD5_SUF, $md5value);
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

    $value    = self::getAppValue($key, '');
    $md5value = self::getAppValue($key.self::MD5_SUF, '');

    $value = self::decrypt($value, $enckey);
    if ($md5value != '' && $md5value != md5($value)) {
        return false;
    }

    self::$opts[$key] = $value;

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

    // Navigation style: B - buttons (default), T - text links, G - graphic links
    // Buttons position: U - up, D - down (default)
    self::$pmeopts['navigation'] = 'GUDM';
    self::$pmeopts['miscphp'] = 'Email::display';
    self::$pmeopts['labels']['Misc'] = 'Em@il';
    //self::$pmeopts['labels']['Sort Field'] = 'Sortierfeld';

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
                                    'datemask' => 'd.m.Y',
                                    'default' => '01.01.1900');
    //  add as needed
    self::$opts['languages'] = explode(',','Deutsch,Englisch,Französich,Spanisch,Polnisch,Russisch,Japanisch,Koreanisch');
    sort(self::$opts['languages']);

    self::$cgiVars = array('Template' => 'blog',
                           'MusicianId' => -1,
                           'ProjectId' => -1,
                           'Project' => '',
                           'RecordsPerPage' => -1);
    self::$pmeopts['tooltips'] = ToolTips::pmeToolTips();
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
    $groupPerms = (\OCP\Share::PERMISSION_CREATE|
                   \OCP\Share::PERMISSION_READ|
                   \OCP\Share::PERMISSION_UPDATE|
                   \OCP\Share::PERMISSION_DELETE);

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
    $groupPerms = (\OCP\Share::PERMISSION_CREATE|
                   \OCP\Share::PERMISSION_READ|
                   \OCP\Share::PERMISSION_UPDATE|
                   \OCP\Share::PERMISSION_DELETE);

    // First check whether the object is already shared.
    $shareType   = \OCP\Share::SHARE_TYPE_GROUP;
    $token =\OCP\Share::getItemShared($type, $id);
    if ($token !== false) {
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
    $olduser = \OC_User::getUser();
    \OC_User::setUserId($uid);
    try {
      $result = call_user_func($callback);
    } catch (\Exception $exception) {
      \OC_User::setUserId($olduser);
      throw new \Exception($exception->getMessage());
      return false;
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
   */
  public static function configured()
  {
    $resuilt = array();

    $result['orchestra'] =  Config::encryptionKeyValid() && Config::getValue('orchestra');
    $result['encryptionkey'] = $result['orchestra'] && Config::encryptionKeyValid();
    $result['database'] = $result['orchestra'] && self::databaseAccessible();
    $result['usergroup'] = self::ShareGroupExists();
    $result['shareowner'] = $result['usergroup'] && self::shareOwnerExists();
    $result['sharedfolder'] = $result['shareowner'] && self::sharedFolderExists();

    $summary = true;
    foreach ($result as $key => $value) {
      $summary = $summary && $value;  
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

  /**We require that the share-owner ownes a directory shared with the
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
    $sharedfolder == '' && $sharedfolder = Config::getSetting('sharedfolder', '');

    if ($sharedfolder == '') {
      // not configured
      return false;
    }

    if ($sharedfolder[0] != '/') {
      $sharedfolder = '/'.$sharedfolder;
    }
    $vfsroot = '/'.$shareowner.'/files';

    $id = \OC_FileCache::getId($sharedfolder, $vfsroot);
    $result = self::sudo($shareowner, function() use ($id, $sharegroup) {
        return self::groupSharedExists($id, $sharegroup, 'folder');
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
    if (!self::shareOwnerExists()) {
      return false;
    }

    if ($sharedfolder[0] != '/') {
      $sharedfolder = '/'.$sharedfolder;
    }

    if (self::sharedFolderExists($sharedfolder)) {
      // no need to create
      return true;
    }

    $sharegroup   = Config::getAppValue('usergroup');
    $shareowner   = Config::getValue('shareowner');

    // try to create the folder and share it with the group
    $result = self::sudo($shareowner, function() use ($sharedfolder, $sharegroup) {
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

        $rootView = new \OC_FilesystemView($vfsroot);

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
 
        \OC_FileCache::scanFile($sharedfolder, $vfsroot);
        $id = \OC_FileCache::getId($sharedfolder, $vfsroot);
        if (!self::groupShareObject($id, $sharegroup, 'folder') ||
            !self::groupSharedExists($id, $sharegroup, 'folder')) {
          return false;
        }
              
        return true; // seems to be ok ...
      });

    return self::sharedFolderExists($sharedfolder);
  }
  
  /**Check whether we have data-base access by connecting to the
   * data-base server and selecting the configured data-base.
   *
   * @return bool, @c true on success.
   */
  public static function databaseAccessible($opts = array())
  {
    Config::init();
    if (empty($opts)) {
      $opts = Config::$dbopts;
    }

    $handle = mySQL::connect($opts, false /* don't die */, true);
    if ($handle !== false) {
      mySQL::close($handle);
      return true;
    }
    return false;
  } 

}

}

?>
