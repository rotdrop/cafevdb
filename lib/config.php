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
  const CFG_KEYS = '
dbserver,
dbuser,
dbpassword,
dbname,
shareowner,
concertscalendar,
concertscalendarid,
rehearsalscalendar,
rehearsalscalendarid,
othercalendar,
othercalendarid,
managementcalendar,
managementcalendarid,
eventduration';
  const MD5_SUF  = '::MD5';
  const MD5_LEN  = 5;
  public static $appbase = "apps/cafevdb/";
  public static $prefix = false;
  public static $triggers = false;
  public static $debug_query = false;
  public static $Wartung = true;
  public static $pmeopts = array();
  public static $opts = array();
  public static $cgiVars = array();
  public static $Languages = array();
  private static $initialized = false;

  public static function loginListener($params)
  {
    self::initPrivateKey($params['uid'], $params['password']);
    self::initEncryptionKey($params['uid']);
  }

  public static function changePasswordListener($params) {
    self::recryptEncryptionKey($params['uid'], $params['password']);
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
    $keys = array_map('trim',explode(',', self::CFG_KEYS));

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
    $keys = array_map('trim',explode(',', self::CFG_KEYS));

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
      $cnt   = unpack('V',substr($value, 0, 4))[1];
      $value = substr($value, 4, $cnt);
      //$value = trim($value, "\0\4");
    }
    return $value;
  }

  static public function encrypt($value, $enckey)
  {
    if ($enckey != '') {
      // Store the size in the first 4 bytes in order not to have to
      // rely on padding.
      $cnt = pack('V', strlen($value));
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
      self::$prefix = self::$appbase . "lib/";
    }
    if (!self::$triggers) {
      self::$triggers = self::$prefix . "triggers/";
    }
    self::$pmeopts['hn'] = self::$opts['dbserver'];
    self::$pmeopts['un'] = self::$opts['dbuser'];
    self::$pmeopts['pw'] = self::$opts['dbpassword'];
    self::$pmeopts['db'] = self::$opts['dbname'];

    self::$pmeopts['url']['images'] = self::$appbase . 'img/';
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
    self::$opts['geburtstag'] = array('name'     => 'Geburtstag',
                                      'select'   => 'T',
                                      'maxlen'   => 10,
                                      'sort'     => true,
                                      /*'datemask' => 'Y-m-d',*/
                                      'calendar' => array('showTime' => '24',
                                                          'dateFormat' =>'%Y-%m-%d'),
                                      'default' => '1970-01-01'
                                      );
    self::$opts['email'] = array('name'     => 'Em@il',
                                'mask'     => '<A HReF="mailto:%1$s">&lt;%1$s&gt;</A>',
                                'select'   => 'T',
                                'maxlen'   => 768,
                                'sort'     => true,
                                'nowrap'   => true,
                                'escape'   => false);
    
    self::$opts['money'] = array('name' => 'Unkostenbeitrag<BR/>(Gagen negativ)',
                                 'phpview' => self::$prefix . 'money.inc.php',
                                 'align' => 'right',
                                 'select' => 'N',
                                 'maxlen' => '6',
                                 'escape' => false,
                                 'sort' => true);
    
    self::$opts['calendar'] = array('select'   => 'T',
                                    'maxlen'   => 19,
                                    'sort'     => true,
                                    'datemask' => 'Y-m-d H:i:s',
                                    'calendar' => array(
                                                        'showTime' => '24',
                                                        'dateFormat' =>'%Y-%m-%d %H:%M:%S'
                                                        )
                                    );
    //  add as needed
    self::$opts['languages'] = explode(',','Deutsch,Englisch,Französich,Spanisch,Polnisch,Russisch,Japanisch,Koreanisch');
    sort(self::$opts['languages']);

    self::$cgiVars = array('Action' => 'BriefInstrumentation',
                           'SubAction' => '',
                           'Template' => 'projects',
                           'MusicianId' => -1,
                           'ProjectId' => -1,
                           'Project' => '',
                           'RecordsPerPage' => -1);
    self::$pmeopts['tooltips'] = ToolTips::pmeToolTips();
  }

  public static function toolTips($key)
  {
      $tip = '';
      if (isset(self::$pmeopts['tooltips'][$key])) {
          $tip = self::$pmeopts['tooltips'][$key];
      }
      return $tip;
  }
};

}

?>
