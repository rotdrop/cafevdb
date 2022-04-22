<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2014, 2016, 2020, 2021, 2022 Claus-Justus Heine <himself@claus-justus-heine.de>
 * @license AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

namespace OCA\CAFEVDB\Service;

use OCP\IUser;
use OCP\IGroup;
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
use OCP\AppFramework\Utility\ITimeFactory;
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
class ConfigService
{
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
  const DEBUG_GEOCODING = (1 << 7);
  const DEBUG_ALL       = self::DEBUG_GENERAL|self::DEBUG_QUERY|self::DEBUG_CSP|self::DEBUG_L10N|self::DEBUG_REQUEST|self::DEBUG_TOOLTIPS|self::DEBUG_EMAILFORM|self::DEBUG_GEOCODING;
  const DEBUG_NONE      = 0;

  const DEFAULT_LOCALE = 'en_US';

  /*
   ****************************************************************************
   *
   * Some configuration constants
   *
   */
  const SHARED_FOLDER = 'sharedfolder';
  const PROJECTS_FOLDER = 'projectsfolder';
  const PROJECT_PARTICIPANTS_FOLDER = 'projectparticipantsfolder';
  const PROJECT_POSTERS_FOLDER = 'projectpostersfolder';
  const FINANCE_FOLDER = 'financefolder';
  const BALANCES_FOLDER = 'balancesfolder';
  const TRANSACTIONS_FOLDER = 'transactionsfolder';
  const DOCUMENT_TEMPLATES_FOLDER = 'documenttemplatesfolder';
  const POSTBOX_FOLDER = 'postboxfolder';
  const OUTBOX_FOLDER = 'outboxfolder';

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

  const DOCUMENT_TEMPLATE_LOGO = 'logo';
  const DOCUMENT_TEMPLATE_SEAL = 'seal';
  const DOCUMENT_TEMPLATE_INSTRUMENT_INSURANCE_RECORD = 'instrumentInsuranceRecord';
  const DOCUMENT_TEMPLATE_PROJECT_DEBIT_NOTE_MANDATE = 'projectDebitNoteMandateForm';
  const DOCUMENT_TEMPLATE_GENERAL_DEBIT_NOTE_MANDATE = 'generalDebitNoteMandateForm';
  const DOCUMENT_TEMPLATE_MEMBER_DATA_UPDATE = 'memberDataUpdateForm';

  /** @var Dedicated document-templates used in various places. */
  const DOCUMENT_TEMPLATES = [
    self::DOCUMENT_TEMPLATE_LOGO => [
      'name' => 'orchestra logo',
      'type' => self::DOCUMENT_TYPE_CONSTANT,
      'folder' => null,
      'blank' => true,
    ],
    self::DOCUMENT_TEMPLATE_SEAL => [
      'name' => 'orchestra seal',
      'type' => self::DOCUMENT_TYPE_CONSTANT,
      'folder' => null,
      'blank' => true,
    ],
    self::DOCUMENT_TEMPLATE_PROJECT_DEBIT_NOTE_MANDATE => [
      'name' => 'project debit-note mandate',
      'type' => self::DOCUMENT_TYPE_TEMPLATE,
      'folder' => self::FINANCE_FOLDER,
      'blank' => true,
    ],
    self::DOCUMENT_TEMPLATE_GENERAL_DEBIT_NOTE_MANDATE => [
      'name' => 'general debit-note mandate',
      'type' => self::DOCUMENT_TYPE_TEMPLATE,
      'folder' => self::FINANCE_FOLDER,
      'blank' => true,
    ],
    self::DOCUMENT_TEMPLATE_MEMBER_DATA_UPDATE => [
      'name' => 'member data update',
      'type' => self::DOCUMENT_TYPE_TEMPLATE,
      'folder' => self::FINANCE_FOLDER,
      'blank' => true,
    ],
    self::DOCUMENT_TEMPLATE_INSTRUMENT_INSURANCE_RECORD => [
      'name' => 'instrument insurance record template',
      'type' => self::DOCUMENT_TYPE_TEMPLATE,
      'folder' => self::FINANCE_FOLDER,
      'blank' => false,
    ],
  ];

  /**
   * @var string
   * Name of a participant field holding a personal signature. This is used by
   * the OrganizationalRolesService in order to find images of signatures of
   * the organizing committee.
   */
  const SIGNATURE_FIELD_NAME = 'signature';

  /**
   * @var int
   * Default auto-save interval in seconds. Used by the email-form
   */
  const DEFAULT_AUTOSAVE_INTERVAL = 300;

  /** @var array Config-keys for the mailing-list server REST access */
  const MAILING_LIST_REST_CONFIG = [
    'url' => 'mailingListRestUrl',
    'user' => 'mailingListRestUser',
    'password' => 'mailingListRestPassword',
  ];
  /** @var array Config-keys for some general mailing list settings */
  const MAILING_LIST_CONFIG = [
    'domain' => 'mailingListEmailDomain',
    'web' => 'mailingListWebPages',
    'owner' => 'mailingListDefaultOwner',
    'moderator' => 'mailingListDefaultModerator',
  ];
  /** @var string Config-key for the announcements mailing list */
  const ANNOUNCEMENTS_MAILING_LIST_FQDN_NAME = 'announcementsMailingList';
  /** @var string Config-key for the announcements mailing list */
  const ANNOUNCEMENTS_MAILING_LIST_DISPLAY_NAME = 'announcementsMailingListName';

  /** @var string */
  const USER_GROUP_KEY = 'usergroup';

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

  /**
   * @var IL10N
   * Personal localization settings based on user preferences.
   */
  protected $l;

  /** @var IL10N */
  protected $appL10n;

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

  /** @var ITimeFactory */
  protected $timeFactory;

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
    , IAppContainer $appContainer
    , IDateTimeFormatter $dateTimeFormatter
    , ITimeFactory $timeFactory
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
    $this->timeFactory = $timeFactory;
    $this->l = $l;

    if (defined('OC_CONSOLE') && empty($userSession->getUser()) && !empty($GLOBALS['cafevdb-user'])) {
      $this->setUserId($GLOBALS['cafevdb-user']);
    } else {
      // The user may be empty at login. This is lazily corrected later.
      $this->user = $this->userSession->getUser();
    }

    // Cache encrypted config values in order to speed up things
    $this->encryptionCache = [];
  }

  public function getAppContainer():IAppContainer
  {
    return $this->appContainer;
  }

  public function getTimeFactory()
  {
    return $this->timeFactory;
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

  public function getIcon()
  {
    // @@todo make it configurable
    return $this->urlGenerator->imagePath($this->appName, 'logo-greyf.svg');
  }

  public function getUserSession():IUserSession
  {
    return $this->userSession;
  }

  public function getUserManager():IUserManager
  {
    return $this->userManager;
  }

  public function getGroupManager():IGroupManager
  {
    return $this->groupManager;
  }

  public function getSubAdminManager():ISubAdmin
  {
    return $this->groupSubAdmin;
  }

  public function getUrlGenerator()
  {
    return $this->urlGenerator;
  }

  /**
   * @param null|string $userId
   *
   * Get the currently active user.
   *
   * @return null|IUser
   */
  public function getUser(?string $userId = null):?IUser
  {
    if (!empty($userId)) {
      return $this->userManager->get($userId);
    }
    if (empty($this->user)) {
      $this->user = $this->userSession->getUser();
    }
    return $this->user;
  }

  public function getUserId():?string
  {
    $user = $this->getUser();
    return !empty($user) ? $user->getUID() : null;
  }

  /**
   * Install a new user id.
   *
   * @parm string $userId
   *
   * @return null|IUser old user.
   */
  public function setUserId(string $userId):?IUser
  {
    return $this->setUser($this->getUser($userId));
  }

  /**
   * Install a new user.
   *
   * @parm null|IUser $user
   *
   * @return null|IUser old user.
   */
  public function setUser(?IUser $user):?IUser
  {
    if (empty($user)) {
      return null;
    }
    $oldUser = $this->getUser();
    $this->user = $user;
    $this->userSession->setUser($this->user);
    return $oldUser;
  }

  public function getL10n()
  {
    return $this->l;
  }

  public function getAppL10n()
  {
    if (empty($this->appL10n)) {
      $appLocale = $this->getAppLocale();
      $appLanguage = locale_get_primary_language($appLocale);
      $this->appL10n = $this->l10NFactory->get($this->appName, $appLanguage, $appLocale);
    }
    return $this->appL10n;
  }

  public function getGroupId()
  {
    return $this->getAppValue(self::USER_GROUP_KEY);
  }

  public function getGroup($groupId = null)
  {
    empty($groupId) && ($groupId = $this->getGroupId());
    return empty($groupId) ? null : $this->groupManager->get($groupId);
  }

  public function groupExists($groupId = null)
  {
    empty($groupId) && ($groupId = $this->getGroupId());
    return !empty($groupId) && $this->groupManager->groupExists($groupId);
  }

  public function inGroup($userId = null, $groupId = null)
  {
    empty($userId) && ($userId = $this->getUserId());
    empty($groupId) && ($groupId = $this->getGroupId());
    if (empty($userId) || empty($groupId)) {
      return false;
    }
    return $this->groupManager->isInGroup($userId, $groupId);
  }

  public function isSubAdminOfGroup($userId = null, $groupId = null)
  {
    $user = empty($userId) ? $this->user : $this->userManager->get($userId);
    $group = empty($groupId) ? $this->getGroup() : $this->groupManager->get($groupId);

    if (empty($user) || empty($group)) {
      return false;
    }
    return $this->groupSubAdmin->isSubAdminofGroup($user, $group);
  }

  /**
   * Return all the sub-admins of the given or the configured orchestra group.
   *
   * @param null|string $groupId
   */
  public function getGroupSubAdmins(?string $groupId = null): array
  {
    $group = $this->getGroup($groupId);
    return $this->groupSubAdmin->getGroupsSubAdmins($group);
  }

  /**
   * Return the id of the dedicated admin-group which contains all sub-admins
   *
   * @return string
   */
  public function getSubAdminGroupId():string
  {
    return $this->getGroupId() . '-admin';
  }

  /**
   * Return the dedicated admin-group if it exists.
   *
   * @return null|IGroup
   */
  public function getSubAdminGroup():?IGroup
  {
    return $this->getGroup($this->getSubAdminGroupId());
  }

  /**
   * Check if the currently logged in or given user-id belongs to the
   * dedicated sub-admin group.
   *
   * @param null|string $userId
   *
   * @return bool
   */
  public function inSubAdminGroup(?string $userId = null):bool
  {
    empty($userId) && ($userId = $this->getUserId());
    if (empty($userId)) {
      return false;
    }
    $groupId = $this->getSubAdminGroupId();
    return $this->groupManager->isInGroup($userId, $groupId);
  }
  /*
   ****************************************************************************
   *
   * unencrypted cloud config space
   *
   */

  public function getUserValue($key, $default = null, $userId = null)
  {
    empty($userId) && ($userId = $this->getUserId());
    return $this->containerConfig->getUserValue($userId, $this->appName, $key, $default);
  }

  public function setUserValue($key, $value, $userId = null)
  {
    empty($userId) && ($userId = $this->getUserId());
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

  public function encrypt($value)
  {
    return $this->encryptionService->getAppCryptor()->encrypt($value);
  }

  public function decrypt($value)
  {
    return $this->encryptionService->getAppCryptor()->decrypt($value);
  }

  public function verifyHash($value, $hash)
  {
    return $this->encryptionService->verifyHash($value, $hash);
  }

  public function computeHash($value)
  {
    return $this->encryptionService->computeHash($value);
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
      if (preg_match('/::[0-9]+$/', $uncached)) {
        // skip backup keys
        continue;
      }
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
    return $this->secureRandom->generate($length, ISecureRandom::CHAR_HUMAN_READABLE);
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
   * @param string $uid The "fake" uid.
   *
   * @param callable $callback function.
   *
   * @return mixed Whatever the callback-functoni returns.
   *
   */
  public function sudo($uid, $callback)
  {
    $oldUser = $this->setUserId($uid);
    if (empty($oldUser)) {
      return false;
    }
    try {
      $result = $callback($uid);
    } catch (\Throwable $t) {
      $this->setUser($oldUser);
      throw new \RuntimeException('Caught an execption during sudo to "' . $uid . '".', 0, $t);
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
   *
   * @param bool|int $timeStamp
   *
   * @return \DateTimeZone
   */
  public function getDateTimeZone($timeStamp = false):\DateTimeZone
  {
    return $this->dateTimeZone->getTimeZone($timeStamp);
  }

  /**
   * Return the locale as string, e.g. de_DE.UTF-8.
   *
   * @param string|null $lang
   *
   * @return string
   */
  public function getLocale(?string $lang = null):string
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

  /**
   * Get the configured app locale. Used to implement consistent currency
   * symbols and some "localized" folder names.
   */
  public function getAppLocale():string
  {
    return $this->getConfigValue('orchestraLocale', $this->getLocale()) ?? self::DEFAULT_LOCALE;
  }

  /**
   * Return the language part of the current or given locale.
   */
  public function getLanguage(?string $locale = null):string
  {
    if (empty($locale)) {
      $locale = $this->getLocale();
    }
    $lang = locale_get_primary_language($locale);
    return $lang;
  }

  /**
   * Return the language part of the current or given locale.
   */
  public function getAppLanguage():string
  {
    return $this->getLanguage($this->getAppLocale());
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

  /**Return an array of supported languages indexed by language code*/
  public function localeLanguageNames($locale = null)
  {
    if (empty($locale)) {
      $locale = $this->getLocale();
    }
    $displayLanguage = substr($locale, 0, 2);
    $languages = $this->findAvailableLanguages();
    $result = [];
    if (method_exists($this->l10NFactory, 'getLanguages')) {
      $cloudLanguages = $this->l10NFactory->getLanguages();
      $otherLanguages = array_column($cloudLanguages['otherLanguages'], 'name', 'code');
      $commonLanguages = array_column($cloudLanguages['commonLanguages'], 'name', 'code');
      $cloudLanguages = array_merge($otherLanguages, $commonLanguages);
      ksort($cloudLanguages);
    }

    foreach ($languages as $language) {
      if (strlen($language) > 5) {
        continue;
      }
      if (!empty($cloudLanguages[$language])) {
        $result[$language] = $cloudLanguages[$language];
      } else {
        $result[$language] = locale_get_display_language($language, $displayLanguage);
        $result[$language] .= ' (' . $language . ')';
      }
    }

    return $result;
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
    empty($locale) && $locale = $this->getAppLocale();
    setlocale(LC_CTYPE, $locale);
    $result = iconv('UTF-8', 'ASCII//TRANSLIT', $string);
    setlocale(LC_CTYPE, $oldlocale);
    return $result;
  }

  /** Return the currency symbol for the given or the app's locale. */
  public function currencySymbol($locale = null)
  {
    if (empty($locale)) {
      $locale = $this->getAppLocale();
    }
    $fmt = new \NumberFormatter($locale, \NumberFormatter::CURRENCY);
    return $fmt->getSymbol(\NumberFormatter::CURRENCY_SYMBOL);
  }

  /** Return the currency 3-letter ISO code for the given or the app's locale */
  public function currencyIsoCode($locale = null)
  {
    if (empty($locale)) {
      $locale = $this->getAppLocale();
    }
    $fmt = new \NumberFormatter($locale, \NumberFormatter::CURRENCY);
    return $fmt->getTextAttribute(\NumberFormatter::CURRENCY_CODE);
  }

  /**
   * Convert $value to a currency value in the given or the user's locale. The
   * currency symbol, however, always refers to the fixed app locale as we
   * really do not want to implement stock-exchange things.
   */
  public function moneyValue($value, $locale = null)
  {
    if (empty($locale)) {
      $locale = $this->getLocale();
    }
    $fmt = new \NumberFormatter($locale, \NumberFormatter::CURRENCY);
    $result = $fmt->formatCurrency((float)$value, $this->currencyIsoCode());

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

  /**
   * Format the given date according to $format and $timeZone to a
   * human readable time-stamp, providing defaults for $format and
   * using the default time-zone if none is specified.
   *
   * @param int|\DateTimeInterface $date
   *
   * @param null|string $format
   *
   * @param null|\DateTimeZone $timeZone
   *
   * @return string
   */
  public function formatTimeStamp($date, ?string $format = null, ?\DateTimeZone $timeZone = null):string
  {
    if (!($date instanceof \DateTimeInterface)) {
      $date = (new \DateTimeImmutable())->setTimestamp($date);
    }

    if (empty($format)) {
      $format = 'Ymd-His-T';
    }
    if (empty($timeZone)) {
      $timeZone = $this->getDateTimeZone();
    }
    return $date->setTimeZone($timeZone)->format($format);
  }

  /**
   * Call ConfigService::formatTimeStamp() with the current date and time.
   *
   * @param null|string $format
   *
   * @param null|\DateTimeZone $timeZone
   *
   * @return string
   */
  public function timeStamp(?string $format = null, ?\DateTimeZone $timeZone = null):string
  {
    return $this->formatTimeStamp(new \DateTimeImmutable, $format, $timeZone);
  }
}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
