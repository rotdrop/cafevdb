<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2014, 2016, 2020, 2021 Claus-Justus Heine <himself@claus-justus-heine.de>
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

use OCP\IUser;
use OCP\IConfig;
use OCP\IUserSession;
use OCP\IL10N;
use OCP\IUserManager;
use OCP\IGroupManager;
use OCP\Group\ISubAdmin;
use OCP\IURLGenerator;
use OCP\L10N\IFactory as IL10NFactory;
use OCP\IDateTimeZone;
use OCP\Security\ISecureRandom;
use OCP\AppFramework\IAppContainer;
use OCP\IDateTimeFormatter;
use OCP\ILogger;

/**
 * Configuration do-it-all class.
 *
 * @todo This is called on boot without user, determine why.
 *
 * @bug This class is too big.
 *
 */
class ConfigService {
  use \OCA\CAFEVDB\Traits\SessionTrait;
  use \OCA\CAFEVDB\Traits\LoggerTrait;

  /****************************************************************************
   *
   * Class constants.
   *
   */
  const DEBUG_GENERAL   = (1 << 0);
  const DEBUG_QUERY     = (1 << 1);
  const DEBUG_CSP       = (1 << 2);
  const DEBUG_L10N      = (1 << 3);
  const DEBUG_REQUEST   = (1 << 4);
  const DEBUG_TOOLTIPS  = (1 << 5);
  const DEBUG_EMAILFORM = (1 << 6);
  const DEBUG_ALL       = self::DEBUG_GENERAL|self::DEBUG_QUERY|self::DEBUG_CSP|self::DEBUG_L10N|self::DEBUG_REQUEST|self::DEBUG_TOOLTIPS|self::DEBUG_EMAILFORM;
  const DEBUG_NONE      = 0;

  /*
   ****************************************************************************
   *
   * Some configuration constants
   *
   */
  const SHARED_FOLDER = 'sharedfolder';
  const PROJECTS_FOLDER = 'projectsfolder';
  const PROJECT_PARTICIPANTS_FOLDER = 'projectparticipantsfolder';
  const PROJECT_BALANCE_FOLDER = 'projectbalancefolder';
  const DOCUMENT_TEMPLATES_FOLDER = 'documenttemplatesfolder';

  const CMS_CATEGORIES = [
    'preview',
    'archive',
    'rehearsals',
    'trashbin',
  ];
  const CMS_MODULES = [
    'concert',
    'rehearsals',
  ];
  const CMS_TEMPLATES = [
    'sub-page',
  ];
  const WYSIWYG_EDITORS = [
    'tinymce' => [ 'name' => 'TinyMCE', 'enabled' => true],
    // ckeditor still uses excessive inline js-code. So what?
    'ckeditor' => [ 'name' => 'CKEditor', 'enabled' => true],
  ];
  const CALENDARS = [
    [ 'uri' => 'concerts', 'public' => true ],
    [ 'uri' => 'rehearsals', 'public' => true ],
    [ 'uri' => 'other', 'public' => true ],
    [ 'uri' => 'management', 'public' => false ],
    [ 'uri' => 'finance', 'public' => false ],
  ];

  const DOCUMENT_TYPE_CONSTANT = 'constant';
  const DOCUMENT_TYPE_TEMPLATE = 'template';

  /** @var Dedicated document-templates used in various places. */
  const DOCUMENT_TEMPLATES = [
    'logo' => [
      'name' => 'orchestra logo',
      'type' => self::DOCUMENT_TYPE_CONSTANT,
    ],
    'seal' => [
      'name' => 'orchestra seal',
      'type' => self::DOCUMENT_TYPE_CONSTANT,
    ],
    'projectDebitNoteMandateForm' => [
      'name' => 'project debit-note mandate',
      'type' => self::DOCUMENT_TYPE_TEMPLATE,
    ],
    'generalDebitNoteMandateForm' => [
      'name' => 'general debit-note mandate',
      'type' => self::DOCUMENT_TYPE_TEMPLATE,
    ],
    'instrumentInsuranceRecord' => [
      'name' => 'instrument insurance record template',
      'type' => self::DOCUMENT_TYPE_TEMPLATE,
    ],
  ];

  const SIGNATURE_FIELD_NAME = 'signature';

  /** @var array */
  protected $encryptionCache;

  /*
   ****************************************************************************
   *
   * Private and protected internal data
   *
   */

  /** @var string */
  protected $appName;

  /** @var IConfig */
  private $containerConfig;

  /** @var IUserSession */
  private $userSession;

  /** @var IUserManager */
  private $userManager;

  /** @var IGroupManager */
  private $groupManager;

  /** @var ISubAdmin */
  private $groupSubAdmin;

  /** @var IUser
   *
   * Will be overridden by sudo().
   */
  private $user;

  /** @var string
   *
   * Will be overridden by sudo().
   */
  private $userId;

  /** @var IUser
   *
   * Unaffected by sudo().
   */
  private $loginUser;

  /** @var string
   *
   * Unaffected by sudo().
   */
  private $loginUserId;

  /** @var IL10N */
  protected $l;

  /** @var IL10NFactory */
  private $l10NFactory;

  /** @var IURLGenerator */
  private $urlGenerator;

  /** @var IDateTimeZone */
  private $dateTimeZone;

  /** @var EncryptionService */
  private $encryptionService;

  /** @var ISecureRandom */
  private $secureRandom;

  /** @var ILogger */
  protected $logger;

  /** @var IDateTimeFormatter */
  protected $dateTimeFormatter;

  /** @var IAppContainer */
  private $appContainer;

  public function __construct(
    $appName
    , IConfig $containerConfig
    , IUserSession $userSession
    , IUserManager $userManager
    , IGroupManager $groupManager
    , ISubAdmin $groupSubAdmin
    , EncryptionService $encryptionService
    , ISecureRandom $secureRandom
    , IURLGenerator $urlGenerator
    , IL10NFactory $l10NFactory
    , IDateTimeZone $dateTimeZone
    , ILogger $logger
    , IDateTimeFormatter $dateTimeFormatter
    , IAppContainer $appContainer
    , IL10N $l
  ) {

    $this->appName = $appName;
    $this->containerConfig = $containerConfig;
    $this->userSession = $userSession;
    $this->userManager = $userManager;
    $this->groupManager = $groupManager;
    $this->groupSubAdmin = $groupSubAdmin;
    $this->encryptionService = $encryptionService;
    $this->secureRandom = $secureRandom;
    $this->urlGenerator = $urlGenerator;
    $this->l10NFactory = $l10NFactory;
    $this->dateTimeZone = $dateTimeZone;
    $this->logger = $logger;
    $this->dateTimeFormatter = $dateTimeFormatter;
    $this->appContainer = $appContainer;
    $this->l = $l;

    if (defined('OC_CONSOLE') && empty($userSession->getUser())) {
      $this->loginUid = $this->userId = $GLOBALS['cafevdb-user'];
      $this->loginUser = $this->userManager->get($this->loginUid);
      $this->user = $this->userManager->get($this->userId);
    } else {
      $this->loginUser = $this->user = $this->userSession->getUser();
      if (empty($this->user)) {
        // can happen at login
        $this->loginUid = $this->userId = null;
      } else {
        $this->loginUid = $this->userId = $this->user->getUID();
      }
    }

    // Cache encrypted config values in order to speed up things
    $this->encryptionCache = [];
  }

  public function getAppContainer():IAppContainer
  {
    return $this->appContainer;
  }

  public function getAppConfig()
  {
    return $this->getCloudConfig();
  }

  public function getCloudConfig()
  {
    return $this->containerConfig;
  }

  public function getAppName() {
    return $this->appName;
  }

  public function appPrefix($id, $join = '-')
  {
    return $this->appName . $join . $id;
  }

  public function getIcon() {
    // @@todo make it configurable
    return $this->urlGenerator->imagePath($this->appName, 'logo-greyf.svg');
  }

  public function getUserSession() {
    return $this->userSession;
  }

  public function getUserManager() {
    return $this->userManager;
  }

  public function getGroupManager() {
    return $this->groupManager;
  }

  public function getUrlGenerator() {
    return $this->urlGenerator;
  }

  public function getUser($userId = null) {
    if (!empty($userId)) {
      return $this->userManager->get($userId);
    }
    return $this->user;
  }

  public function getUserId() {
    return $this->userId;
  }

  /**Install a new user id.
   *
   * @parm int $user
   *
   * @return IUser old user.
   */
  public function setUserId($userId) {
    return $this->setUser($this->getUser($userId));
  }

  /**
   * Install a new user.
   *
   * @parm IUser $user
   *
   * @return IUser old user.
   */
  public function setUser($user) {
    if (empty($user)) {
      return null;
    }
    $oldUser = $this->user;
    $this->user = $user;
    $this->userId = $user->getUID();
    $this->userSession->setUser($this->user);
    return $oldUser;
  }

  public function getL10n() {
    return $this->l;
  }

  public function getGroupId() {
    return $this->getAppValue('usergroup');
  }

  public function groupExists($groupId = null) {
    empty($groupId) && ($groupId = $this->getGroupId());
    return !empty($groupId) && $this->groupManager->groupExists($groupId);
  }

  public function inGroup($userId = null, $groupId = null) {
    empty($userId) && ($userId = $this->getUserId());
    empty($groupId) && ($groupId = $this->getGroupId());
    return $this->groupManager->isInGroup($userId, $groupId);
  }

  public function getGroup($groupId = null) {
    empty($groupId) && ($groupId = $this->getGroupId());
    return $this->groupManager->get($groupId);
  }

  public function isSubAdminOfGroup($userId = null, $groupId = null) {
    $user = empty($userId) ? $this->user : $this->userManager->get($userId);
    $group = empty($groupId) ? $this->getGroup() : $this->groupManager->get($groupId);

    if (empty($user) || empty($group)) {
      return false;
    }
    return $this->groupSubAdmin->isSubAdminofGroup($user, $group);
  }

  /*
   ****************************************************************************
   *
   * unencrypted cloud config space
   *
   */

  public function getUserValue($key, $default = null, $userId = null)
  {
    empty($userId) && ($userId = $this->userId);
    return $this->containerConfig->getUserValue($userId, $this->appName, $key, $default);
  }

  public function setUserValue($key, $value, $userId = null)
  {
    empty($userId) && ($userId = $this->userId);
    return $this->containerConfig->setUserValue($userId, $this->appName, $key, $value);
  }

  /**
   * A short-cut, redirecting to the stock functions for the app.
   */
  public function getAppValue($key, $default = null)
  {
    return $this->containerConfig->getAppValue($this->appName, $key, $default);
  }

  /**
   * A short-cut, redirecting to the stock functions for the app.
   */
  public function setAppValue($key, $value)
  {
    return $this->containerConfig->setAppValue($this->appName, $key, $value);
  }

  /**
   * A short-cut, redirecting to the stock functions for the app.
   */
  public function deleteAppValue($key)
  {
    return $this->containerConfig->deleteAppValue($this->appName, $key);
  }

  /*
   ****************************************************************************
   *
   * encrypted config space
   *
   */
  public function encryptionService()
  {
    return $this->encryptionService;
  }

  public function setUserEncryptionKey($key)
  {
    return $this->encryptionService->setUserEncryptionKey($key);
  }

  public function getUserEncryptionKey()
  {
    return $this->encryptionService->getUserEncryptionKey();
  }

  public function setAppEncryptionKey($key)
  {
    return $this->encryptionService->setAppEncryptionKey($key);
  }

  public function getAppEncryptionKey()
  {
    return $this->encryptionService->getAppEncryptionKey();
  }

  public function encrypt($value, $key = null)
  {
    $key = $key?: $this->getAppEncryptionKey();
    return $this->encryptionService->encrypt($value, $key);
  }

  public function decrypt($value, $key = null)
  {
    $key = $key?: $this->getAppEncryptionKey();
    return $this->encryptionService->decrypt($value, $key);
  }

  public function encryptionKeyValid($encryptionKey)
  {
    return $this->encryptionService->encryptionKeyValid($encryptionKey);
  }

  public function getConfigValue($key, $default = null)
  {
    if (!isset($this->encryptionCache[$key])) {
      $value = $this->encryptionService->getConfigValue($key, $default);
      if ($value !== false) {
        $this->encryptionCache[$key] = $value;
      } else {
        return null;
      }
    }
    return $this->encryptionCache[$key];
  }

  public function setConfigValue($key, $value)
  {
    //$this->logInfo("enckey: ". $this->encryptionService->appEncryptionKey);
    if ($this->encryptionService->setConfigValue($key, $value)) {
      $this->encryptionCache[$key] = $value;
      return true;
    }
    return false;
  }

  public function deleteConfigValue($key)
  {
    unset($this->encryptionCache[$key]);
    return $this->deleteAppValue($key);
  }

  public function getAppKeys()
  {
    return array_values(
      array_filter(
        $this->containerConfig->getAppKeys($this->appName),
        function($k) { return strpos('::', $k) === false; }
      )
    );
  }

  /**
   * Fetch all config values and decrypt them.
   *
   * @return array Configuration values.
   */
  public function decryptConfigValues()
  {
    foreach ($this->getAppKeys() as $key) {
      $this->getConfigValue($key);
    }
    return $this->encryptionCache;
  }

  /**
   * Flush all configuration values to the database, possibly
   * encrypting them.
   */
  public function encryptConfigValues(array $override = [])
  {
    $this->encryptionCache = array_merge($this->encryptionCache, $override);
    $appKeys = $this->getAppKeys();
    $cacheKeys = array_keys($this->encryptionCache);
    foreach (array_diff($appKeys, $cacheKeys) as $uncached) {
      $this->logWarn("Found un-cached configuration key $uncached");
      $this->getConfigValue($uncached);
    }
    foreach (array_diff($cacheKeys, $appKeys) as $unstored) {
      $this->logWarn("Found un-persisted configuration key $unstored");
    }
    $cacheKeys = array_keys($this->encryptionCache);
    //$this->logInfo('keys: '.print_r($cacheKeys, true));
    foreach ($cacheKeys as $key) {
      $this->setConfigValue($key, $this->encryptionCache[$key]);
    }
  }

  /*
   ****************************************************************************
   */

  /**
   * Would rather belong to the EncryptionService
   */
  public function generateRandomBytes($length = 30)
  {
    return $this->secureRandom->generate($length);
  }

  /*
   ****************************************************************************
   *
   * Sudo, run a function as other user, e.g. to setup shares.
   *
   */

  /**
   * Fake execution with other user-id. Note that this function will
   * catch any exception thrown while executing the callback-function
   * and in case an exeption has been called will re-throw the
   * exception.
   *
   * @param $uid The "fake" uid.
   *
   * @param $callback function.
   *
   * @return Whatever the callback-functoni returns.
   *
   */
  public function sudo($uid, $callback)
  {
    $oldUser = $this->setUserId($uid);
    if (empty($oldUser)) {
      return false;
    }
    try {
      $result = $callback();
    } catch (\Exception $exception) {
      $this->setUserId($oldUserId);
      throw $exception;
    }
    $this->setUser($oldUser);

    return $result;
  }

  /*
   ****************************************************************************
   *
   * date time timezone locale
   *
   */

  public function dateTimeFormatter():IDateTimeFormatter
  {
    return $this->dateTimeFormatter;
  }

  /**
   * Get the current timezone
   */
  public function getDateTimeZone($timeStamp = null):\DateTimeZone
  {
    return $this->dateTimeZone->getTimeZone($timeStamp);
  }

  /**
   * Return the locale as string, e.g. de_DE.UTF-8.
   */
  public function getLocale($lang = null)
  {
    if (empty($lang)) {
      $locale = $this->l10NFactory->findLocale($this->appName);
      $lang = $this->l10NFactory->findLanguageFromLocale($this->appName, $locale);
      $this->logDebug('Locale seems to be ' . $locale);
      $this->logDebug('Language seems to be ' . $lang);
    } else {
      $locale = $lang;
    }
    $primary = locale_get_primary_language($locale);
    if ($primary == $locale) {
      $locale = $lang.'_'.strtoupper($lang);
    }
    if (strpos($locale, '.') === false) {
      $locale .= '.UTF-8';
    }
    $this->logDebug('Generated locale string: ' . $locale);
    return $locale;
  }

  /**Return an array of supported country-codes and names*/
  public function localeCountryNames($locale = null)
  {
    if (!$locale) {
      $locale = $this->getLocale();
    }
    $language = locale_get_primary_language($locale);
    $locales = resourcebundle_locales('');
    $countryCodes = array();
    foreach ($locales as $locale) {
      $country = locale_get_region($locale);
      if ($country) {
        $countryCodes[$country] = locale_get_display_region($locale, $language);
      }
    }
    asort($countryCodes);
    return $countryCodes;
  }

  public function findAvailableLanguages($app = 'core') {
    return $this->l10NFactory->findAvailableLanguages($app);
  }

  public function findAvailableLocales() {
    return $this->l10NFactory->findAvailableLocales();
  }

  /**
   * Transliterate the given string to the given or default locale.
   *
   * @todo We should define a user-independent locale based on the
   * location of the orchestra.
   */
  public function transliterate(string $string, $locale = null):string
  {
    $oldlocale = setlocale(LC_CTYPE, '0');
    empty($locale) && $locale = $this->getLocale();
    setlocale(LC_CTYPE, $locale);
    $result = iconv('UTF-8', 'ASCII//TRANSLIT', $string);
    setlocale(LC_CTYPE, $oldlocale);
    return $result;
  }

  /** Return the currency symbol for the locale. */
  public function currencySymbol($locale = null)
  {
    if (empty($locale)) {
      $locale = $this->getLocale();
    }
    $fmt = new \NumberFormatter($locale, \NumberFormatter::CURRENCY);
    return $fmt->getSymbol(\NumberFormatter::CURRENCY_SYMBOL);
  }

  /** Convert $value to a currency value in the given or default locale */
  public function moneyValue($value, $locale = null)
  {
    if (empty($locale)) {
      $locale = $this->getLocale();
    }
    $fmt = new \NumberFormatter($locale, \NumberFormatter::CURRENCY);
    $result = $fmt->format((float)$value);

    return $result;
  }

  /** Convert a float value in the given or default locale */
  public function floatValue($value, $decimals = 4, $locale = null)
  {
    empty($locale) && $locale = $this->getLocale();
    $fmt = new \NumberFormatter($locale, \NumberFormatter::DECIMAL);
    $result = $fmt->format((float)$value);
    return $result;
  }

  public function timeStamp($format = null, $timeZone = null)
  {
    if (empty($format)) {
      $format = 'Ymd-his-T';
    }
    if (empty($timeZone)) {
      $timeZone = $this->getDateTimeZone();
    }
    return (new \DateTime())->setTimeZone($timeZone)->format($format);
  }
}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
