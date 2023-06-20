<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2011-2014, 2016, 2020, 2021, 2022, 2023 Claus-Justus Heine
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

use Throwable;
use RuntimeException;
use DateTimeZone;
use NumberFormatter;
use DateTimeImmutable;

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
use Psr\Log\LoggerInterface as ILogger;

use OCA\CAFEVDB\Exceptions;

/**
 * Configuration do-it-all class.
 *
 * @todo This is called on boot without user, determine why.
 *
 * @bug This class is too big.
 */
class ConfigService
{
  use \OCA\CAFEVDB\Traits\SessionTrait;
  use \OCA\CAFEVDB\Toolkit\Traits\LoggerTrait;
  use \OCA\CAFEVDB\Traits\TimeStampTrait;

  /*-**************************************************************************
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

  const APP_LOGO = 'logo-greyf.svg';

  /*-**************************************************************************
   *
   * Some configuration constants
   *
   */
  const SHAREOWNER_KEY = 'shareowner';

  const SHARED_FOLDER = 'sharedfolder';
  const PROJECTS_FOLDER = 'projectsfolder';
  const PROJECT_PARTICIPANTS_FOLDER = 'projectparticipantsfolder';
  const PROJECT_POSTERS_FOLDER = 'projectpostersfolder';
  const PROJECT_PUBLIC_DOWNLOADS_FOLDER = 'projectpublicdownloadsfolder';
  const FINANCE_FOLDER = 'financefolder';
  const BALANCES_FOLDER = 'balancesfolder';
  const TRANSACTIONS_FOLDER = 'transactionsfolder';
  const DOCUMENT_TEMPLATES_FOLDER = 'documenttemplatesfolder';
  const POSTBOX_FOLDER = 'postboxfolder';
  const OUTBOX_FOLDER = 'outboxfolder';
  const PROJECT_SKELETON_FOLDER = 'skeleton';
  const PROJECT_PARTICIPANTS_SKELETON_FOLDER = 'forename.surname';
  const PROJECT_MANAGEMENT_SKELETON_FOLDER = 'management';

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
  const CONCERTS_CALENDAR_URI = 'concerts';
  const REHEARSALS_CALENDAR_URI = 'rehearsals';
  const OTHER_CALENDAR_URI = 'other';
  const MANAGEMENT_CALENDAR_URI = 'management';
  const FINANCE_CALENDAR_URI = 'finance';
  const CALENDARS = [
    self::CONCERTS_CALENDAR_URI => [ 'uri' => self::CONCERTS_CALENDAR_URI, 'public' => true ],
    self::REHEARSALS_CALENDAR_URI => [ 'uri' => self::REHEARSALS_CALENDAR_URI, 'public' => true ],
    self::OTHER_CALENDAR_URI => [ 'uri' => self::OTHER_CALENDAR_URI, 'public' => true ],
    self::MANAGEMENT_CALENDAR_URI => [ 'uri' => self::MANAGEMENT_CALENDAR_URI, 'public' => false ],
    self::FINANCE_CALENDAR_URI => [ 'uri' => self::FINANCE_CALENDAR_URI, 'public' => false ],
  ];

  const BANK_ACCOUNT_OWNER = 'bankAccountOwner';
  const BANK_ACCOUNT_IBAN = 'bankAccountIBAN';
  const BANK_ACCOUNT_BLZ = 'bankAccountBLZ';
  const BANK_ACCOUNT_BIC = 'bankAccountBIC';
  const BANK_ACCOUNT_NAME = 'bankAccountBankName';
  const BANK_ACCOUNT_CREDITOR_IDENTIFIER = 'bankAccountCreditorIdentifier';
  const BANK_ACCOUNT_BANK_HOLIDAYS = 'bankAccountBankHolidays';

  const BANK_ACCOUNT_CONFIG_KEYS = [
    self::BANK_ACCOUNT_OWNER,
    self::BANK_ACCOUNT_IBAN,
    self::BANK_ACCOUNT_BLZ,
    self::BANK_ACCOUNT_BIC,
    self::BANK_ACCOUNT_NAME,
    self::BANK_ACCOUNT_CREDITOR_IDENTIFIER,
    self::BANK_ACCOUNT_BANK_HOLIDAYS
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

  /** @var string */
  const CONFIG_LOCK_KEY = EncryptionService::CONFIG_LOCK_KEY;

  /** @var string */
  public const EMAIL_FORM_ADDRESS_KEY = 'emailfromaddress';

  /** @var string */
  public const EMAIL_TEST_ADDRESS_KEY = 'emailtestaddress';

  /** @var string */
  public const ANNOUNCEMENTS_MAILING_LIST_KEY = 'announcementsMailingList';

  /** @var string */
  public const EXECUTIVE_BOARD_PROJECT_KEY = 'executiveBoardProject';

  /** @var string */
  public const EXECUTIVE_BOARD_PROJECT_ID_KEY = self::EXECUTIVE_BOARD_PROJECT_KEY . 'Id';

  /** @var string */
  public const CLUB_MEMBERS_PROJECT_KEY = 'memberProject';

  /** @var string */
  public const CLUB_MEMBER_PROJECT_ID_KEY = self::CLUB_MEMBERS_PROJECT_KEY . 'Id';

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

  /** @var string */
  protected $appLocale;

  /** @var string */
  protected $appLanguage;

  /** @var IL10NFactory */
  private $l10NFactory;

  /** @var IURLGenerator */
  private $urlGenerator;

  /** @var IDateTimeZone */
  protected $dateTimeZone;

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
  protected $appContainer;

  /**
   * @var array<string, array<string, string>>
   */
  private $localeCountryNames = [];

  /**
   * {@inheritdoc}
   *
   * @SuppressWarnings(PHPMD.Superglobals)
   */
  public function __construct(
    string $appName,
    IConfig $containerConfig,
    IUserSession $userSession,
    IUserManager $userManager,
    IGroupManager $groupManager,
    ISubAdmin $groupSubAdmin,
    EncryptionService $encryptionService,
    ISecureRandom $secureRandom,
    IURLGenerator $urlGenerator,
    IL10NFactory $l10NFactory,
    IDateTimeZone $dateTimeZone,
    ILogger $logger,
    IAppContainer $appContainer,
    IDateTimeFormatter $dateTimeFormatter,
    ITimeFactory $timeFactory,
    IL10N $l,
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

    if (\OC::$CLI && empty($userSession->getUser()) && !empty($GLOBALS['cafevdb-user'])) {
      $this->setUserId($GLOBALS['cafevdb-user']);
    } else {
      // The user may be empty at login. This is lazily corrected later.
      $this->user = $this->userSession->getUser();
    }

    // Cache encrypted config values in order to speed up things
    $this->encryptionCache = [];
  }

  /** @return IAppContainer */
  public function getAppContainer():IAppContainer
  {
    return $this->appContainer;
  }

  /** @return ITimeFactory */
  public function getTimeFactory():ITimeFactory
  {
    return $this->timeFactory;
  }

  /** @return IConfig */
  public function getAppConfig():IConfig
  {
    return $this->getCloudConfig();
  }

  /** @return IConfig */
  public function getCloudConfig():IConfig
  {
    return $this->containerConfig;
  }

  /** @return string */
  public function getAppName():string
  {
    return $this->appName;
  }

  /**
   * @param string $id Given something.
   *
   * @param string $join Default '-'.
   *
   * @return string The $id prefixed by the app-name, joined with a dash.
   */
  public function appPrefix(string $id, string $join = '-'):string
  {
    return $this->appName . $join . $id;
  }

  /** @return string Image web-path to the app-icon. */
  public function getIcon():string
  {
    // @@todo make it configurable
    return $this->urlGenerator->imagePath($this->appName, self::APP_LOGO);
  }

  /** @return IUserSession */
  public function getUserSession():IUserSession
  {
    return $this->userSession;
  }

  /** @return IUserManager */
  public function getUserManager():IUserManager
  {
    return $this->userManager;
  }

  /** @return IGroupManager */
  public function getGroupManager():IGroupManager
  {
    return $this->groupManager;
  }

  /** @return ISubAdmin */
  public function getSubAdminManager():ISubAdmin
  {
    return $this->groupSubAdmin;
  }

  /** @return IURLGenerator */
  public function getUrlGenerator():IURLGenerator
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

  /** @return string */
  public function getUserId():?string
  {
    $user = $this->getUser();
    return !empty($user) ? $user->getUID() : null;
  }

  /**
   * Install a new user id.
   *
   * @param string $userId The user id to install.
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
   * @param null|IUser $user
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

  /** @return IL10N */
  public function getL10n():IL10N
  {
    return $this->l;
  }

  /** @return IL10N */
  public function getAppL10n():IL10N
  {
    if (empty($this->appL10n)) {
      $this->appL10n = $this->appContainer->get(Registration::APP_L10N);
    }
    return $this->appL10n;
  }

  /** @return string The orchestra orga-group id. */
  public function getGroupId():string
  {
    return $this->getAppValue(self::USER_GROUP_KEY);
  }

  /**
   * @param null|string $groupId
   *
   * @return null|IGroup The group for the given id or the orchetra group.
   */
  public function getGroup(?string $groupId = null):?IGroup
  {
    empty($groupId) && ($groupId = $this->getGroupId());
    return empty($groupId) ? null : $this->groupManager->get($groupId);
  }

  /**
   * @param null|string $groupId Use the orchestra group if null.
   *
   * @return bool
   */
  public function groupExists($groupId = null):bool
  {
    empty($groupId) && ($groupId = $this->getGroupId());
    return !empty($groupId) && $this->groupManager->groupExists($groupId);
  }

  /**
   * @param null|string $userId Use the current user if null.
   *
   * @param null|string $groupId then Use orchestra group if null.
   *
   * @return bool
   */
  public function inGroup(?string $userId = null, ?string $groupId = null):bool
  {
    empty($userId) && ($userId = $this->getUserId());
    empty($groupId) && ($groupId = $this->getGroupId());
    if (empty($userId) || empty($groupId)) {
      return false;
    }
    return $this->groupManager->isInGroup($userId, $groupId);
  }

  /**
   * @param null|string $userId Use the current user if null.
   *
   * @param null|string $groupId then Use orchestra group if null.
   *
   * @return bool
   */
  public function isSubAdminOfGroup($userId = null, $groupId = null):bool
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
   * @param null|string $groupId then Use orchestra group if null.
   *
   * @return array
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
   *-**************************************************************************
   *
   * unencrypted cloud config space
   *
   */

  /**
   * @param string $key Config key.
   *
   * @param mixed $default Default value.
   *
   * @param null|string $userId Use the current user if null.
   *
   * @return mixed
   */
  public function getUserValue(string $key, mixed $default = null, ?string $userId = null)
  {
    empty($userId) && ($userId = $this->getUserId());
    return $this->containerConfig->getUserValue($userId, $this->appName, $key, $default);
  }

  /**
   * @param string $key Config key.
   *
   * @param mixed $value Value to set.
   *
   * @param null|string $userId Use the current user if null.
   *
   * @return mixed
   */
  public function setUserValue(string $key, mixed $value, ?string $userId = null)
  {
    empty($userId) && ($userId = $this->getUserId());
    return $this->containerConfig->setUserValue($userId, $this->appName, $key, $value);
  }

  /**
   * A short-cut, redirecting to the stock functions for the app.
   *
   * @param string $key Config key.
   *
   * @param mixed $default Default value.
   *
   * @return mixed
   */
  public function getAppValue(string $key, mixed $default = null)
  {
    return $this->containerConfig->getAppValue($this->appName, $key, $default);
  }

  /**
   * A short-cut, redirecting to the stock functions for the app.
   *
   * @param string $key Config key.
   *
   * @param mixed $value Value to set.
   *
   * @return mixed
   */
  public function setAppValue(string $key, mixed $value)
  {
    return $this->containerConfig->setAppValue($this->appName, $key, $value);
  }

  /**
   * A short-cut, redirecting to the stock functions for the app.
   *
   * @param string $key Config key.
   *
   * @return void
   */
  public function deleteAppValue(string $key):void
  {
    $this->containerConfig->deleteAppValue($this->appName, $key);
  }

  /*
   *-**************************************************************************
   *
   * encrypted config space
   *
   */

  /** @return EncryptionService */
  public function encryptionService():EncryptionService
  {
    return $this->encryptionService;
  }

  /**
   * @param string $key Encryption key to set.
   *
   * @return void
   */
  public function setUserEncryptionKey(string $key):void
  {
    $this->encryptionService->setUserEncryptionKey($key);
  }

  /** @return null|string */
  public function getUserEncryptionKey():?string
  {
    return $this->encryptionService->getUserEncryptionKey();
  }

  /**
   * @param string $key Encryption key to set.
   *
   * @return void
   */
  public function setAppEncryptionKey(string $key):void
  {
    $this->encryptionService->setAppEncryptionKey($key);
  }

  /** @return null|string */
  public function getAppEncryptionKey():?string
  {
    return $this->encryptionService->getAppEncryptionKey();
  }

  /**
   * @param null|string $value Value to encrypt.
   *
   * @return null|string Encrypted value.
   */
  public function encrypt(?string $value):?string
  {
    return $this->encryptionService->getAppCryptor()->encrypt($value);
  }

  /**
   * @param null|string $value Value to decrypt.
   *
   * @return null|string Decrypted value.
   */
  public function decrypt(?string $value):?string
  {
    return $this->encryptionService->getAppCryptor()->decrypt($value);
  }

  /**
   * @param null|string $value Value to verify.
   *
   * @param null|string $hash Hash to verify against.
   *
   * @return bool \true if either hash or value are empty or if the hash could
   * be verified.
   */
  public function verifyHash($value, $hash)
  {
    return $this->encryptionService->verifyHash($value, $hash);
  }

  /**
   * @param string $value The value to hash.
   *
   * @return string The hash of $value.
   */
  public function computeHash(string $value):string
  {
    return $this->encryptionService->computeHash($value);
  }

  /**
   * Check the validity of the encryption key. In order to do so we fetch
   * an encrypted representation of the key from the OC config space
   * and try to decrypt that key with the given key. If the decrypted
   * key matches our key, then we accept the key.
   *
   * @param null|string $encryptionKey Key to check.
   *
   * @return bool
   *
   * @throws Exceptions\EncryptionKeyException
   *
   * @see EncryptionService::encryptionKeyValid()
   */
  public function encryptionKeyValid(?string $encryptionKey):bool
  {
    return $this->encryptionService->encryptionKeyValid($encryptionKey);
  }

  /**
   * Get a possibly encrypted config value.
   *
   * @param string $key
   *
   * @param mixed $default
   *
   * @param bool $ignoreLock Only to be used while changing the encryption key.
   *
   * @return mixed
   *
   * @throws Exceptions\ConfigLockedException
   */
  public function getConfigValue(string $key, mixed $default = null, bool $ignoreLock = false)
  {
    if (!isset($this->encryptionCache[$key])) {
      $value = $this->encryptionService->getConfigValue($key, $default, $ignoreLock);
      if ($value !== false) {
        $this->encryptionCache[$key] = $value;
      } else {
        return null;
      }
    }
    return $this->encryptionCache[$key];
  }

  /**
   * @param string $key
   *
   * @param mixed $value
   *
   * @param bool $ignoreLock Default false. Ignore the configuration lock. The
   * lock is set while changing the encryption key.
   *
   * @return bool Success or not.
   *
   * @throws Exceptions\ConfigLockedException
   */
  public function setConfigValue(string $key, mixed $value, bool $ignoreLock = false)
  {
    //$this->logInfo("enckey: ". $this->encryptionService->appEncryptionKey);
    if ($this->encryptionService->setConfigValue($key, $value, $ignoreLock)) {
      $this->encryptionCache[$key] = $value;
      return true;
    }
    return false;
  }

  /**
   * Delete the value for the given key.
   *
   * @param string $key Config key.
   *
   * @return void
   */
  public function deleteConfigValue(string $key):void
  {
    unset($this->encryptionCache[$key]);
    $this->deleteAppValue($key);
  }

  /** @return array All config keys for the app. */
  public function getAppKeys():array
  {
    return array_values(
      array_filter(
        $this->containerConfig->getAppKeys($this->appName),
        fn($k) => strpos('::', $k) === false
      )
    );
  }

  /**
   * Fetch all config values and decrypt them. This is only meant for use
   * during re-cryption of config value when changing the encryption
   * key. Hence we enforce "ignoreLock: true".
   *
   * @return array Configuration values.
   */
  public function decryptConfigValues()
  {
    foreach ($this->getAppKeys() as $key) {
      $this->getConfigValue($key, ignoreLock: true);
    }
    return $this->encryptionCache;
  }

  /**
   * Flush all configuration values to the database, possibly encrypting
   * them.T his is only meant for use during re-cryption of config value when
   * changing the encryption key. Hence we enforce "ignoreLock: true".
   *
   * @param array $override Values which override the configured values.
   *
   * @return void
   */
  public function encryptConfigValues(array $override = []):void
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
      $this->getConfigValue($uncached, ignoreLock: true);
    }
    foreach (array_diff($cacheKeys, $appKeys) as $unstored) {
      $this->logWarn("Found un-persisted configuration key $unstored");
    }
    $cacheKeys = array_keys($this->encryptionCache);
    //$this->logInfo('keys: '.print_r($cacheKeys, true));
    foreach ($cacheKeys as $key) {
      $this->setConfigValue($key, $this->encryptionCache[$key], ignoreLock: true);
    }
  }

  /*
   ****************************************************************************
   */

  /**
   * Would rather belong to the EncryptionService.
   *
   * @param int $length Length of random string.
   *
   * @return string
   */
  public function generateRandomBytes(int $length = 30):string
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
   */
  public function sudo(string $uid, callable $callback)
  {
    $oldUser = $this->setUserId($uid);
    if (empty($oldUser)) {
      return false;
    }
    try {
      $result = $callback($uid);
    } catch (Throwable $t) {
      $this->setUser($oldUser);
      throw new RuntimeException('Caught an execption during sudo to "' . $uid . '".', 0, $t);
    }
    $this->setUser($oldUser);

    return $result;
  }

  /*
   *-**************************************************************************
   *
   * date time timezone locale
   *
   */

  /** @return IDateTimeFormatter */
  public function dateTimeFormatter():IDateTimeFormatter
  {
    return $this->dateTimeFormatter;
  }

  /**
   * Get the current timezone
   *
   * @param bool|int $timeStamp
   *
   * @return DateTimeZone
   */
  public function getDateTimeZone(mixed $timeStamp = false):DateTimeZone
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
      $locale = $this->appContainer->get(Registration::USER_LOCALE);
      $this->logDebug('Locale seems to be ' . $locale);
      $this->logDebug('Language seems to be ' . $lang);
      $lang = locale_get_primary_language($locale);
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
   *
   * @return string
   */
  public function getAppLocale():string
  {
    if (empty($this->appLocale)) {
      $this->appLocale = $this->appContainer->get(Registration::APP_LOCALE);
    }
    return $this->appLocale;
  }

  /**
   * Return the language part of the current or given locale.
   *
   * @param null|string $locale The locale to use, if null the current
   * user's locale.
   *
   * @return string
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
   * @return string The language part of the current or given locale.
   */
  public function getAppLanguage():string
  {
    if (empty($this->appLanguage)) {
      $this->appLanguage =$this->appContainer->get(Registration::APP_LANGUAGE);
    }
    return $this->appLanguage;
  }

  /**
   * @param null|string $displayLocale Locale to use, if null the current user's locale.
   *
   * @return array An array of supported country-codes and names.
   */
  public function localeCountryNames(?string $displayLocale = null):array
  {
    if (!$displayLocale) {
      $displayLocale = $this->getLocale();
    }
    $displayLanguage = locale_get_primary_language($displayLocale);
    if (!empty($this->localeCountryNames[$displayLanguage])) {
      return $this->localeCountryNames[$displayLanguage];
    }
    $locales = resourcebundle_locales('');
    $countryCodes = array();
    foreach ($locales as $locale) {
      $country = locale_get_region($locale);
      if ($country) {
        $countryCodes[$country] = locale_get_display_region($locale, $displayLanguage);
      }
    }
    asort($countryCodes);
    $this->localeCountryNames[$displayLanguage] = $countryCodes;
    return $countryCodes;
  }

  /**
   * @param null|string $locale Locale to use, if null the current user's locale.
   *
   * @return array An array of supported languages indexed by language code.
   */
  public function localeLanguageNames($locale = null):array
  {
    if (empty($locale)) {
      $locale = $this->getLocale();
    }
    $displayLanguage = locale_get_primary_language($locale);
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

  /**
   * @param string $app The app with the translations.
   *
   * @return array
   *
   * @see IL10NFactory::findAvailableLanguages()
   */
  public function findAvailableLanguages(string $app = 'core'):array
  {
    return $this->l10NFactory->findAvailableLanguages($app);
  }

  /**
   * @return array
   *
   * @see IL10NFactory::findAvailableLocales()
   */
  public function findAvailableLocales():array
  {
    return $this->l10NFactory->findAvailableLocales();
  }

  /**
   * Transliterate the given string to the given or default locale.
   *
   * @param string $string The string to work on.
   *
   * @param null|string $locale Locale to use, use app-locale if null.
   *
   * @return string
   *
   * @todo We should define a user-independent locale based on the
   * location of the orchestra.
   */
  public function transliterate(string $string, ?string $locale = null):string
  {
    $oldlocale = setlocale(LC_CTYPE, '0');
    empty($locale) && $locale = $this->getAppLocale();
    setlocale(LC_CTYPE, $locale);
    $result = iconv('UTF-8', 'ASCII//TRANSLIT', $string);
    setlocale(LC_CTYPE, $oldlocale);
    return $result;
  }

  /**
   * @param null|string $locale Locale to use, use app-locale if null.
   *
   * @return The currency symbol for the given or the app's locale.
   */
  public function currencySymbol($locale = null):string
  {
    if (empty($locale)) {
      $locale = $this->getAppLocale();
    }
    $fmt = new NumberFormatter($locale, \NumberFormatter::CURRENCY);
    return $fmt->getSymbol(\NumberFormatter::CURRENCY_SYMBOL);
  }

  /**
   * @param null|string $locale Locale to use, use app-locale if null.
   *
   * @return string The currency 3-letter ISO code for the given or the app's locale.
   */
  public function currencyIsoCode($locale = null):string
  {
    if (empty($locale)) {
      $locale = $this->getAppLocale();
    }
    $fmt = new NumberFormatter($locale, \NumberFormatter::CURRENCY);
    return $fmt->getTextAttribute(\NumberFormatter::CURRENCY_CODE);
  }

  /**
   * Convert $value to a currency value in the given or the user's locale. The
   * currency symbol, however, always refers to the fixed app locale as we
   * really do not want to implement stock-exchange things.
   *
   * @param mixed $value Value to format.
   *
   * @param null|string $locale Locale to use, use user-locale if null.
   *
   * @return string
   */
  public function moneyValue(mixed $value, ?string $locale = null):string
  {
    if (empty($locale)) {
      $locale = $this->getLocale();
    }
    $fmt = new NumberFormatter($locale, \NumberFormatter::CURRENCY);
    $result = $fmt->formatCurrency((float)$value, $this->currencyIsoCode());

    return $result;
  }

  /**
   * Convert a float value in the given or default locale.
   *
   * @param mixed $value Value to format.
   *
   * @param int $decimals Number of decimal places.
   *
   * @param null|string $locale Locale to use, use user-locale if null.
   *
   * @return string
   */
  public function floatValue(mixed $value, int $decimals = 4, ?string $locale = null):string
  {
    empty($locale) && $locale = $this->getLocale();
    $fmt = new NumberFormatter($locale, \NumberFormatter::DECIMAL);
    $fmt->setAttribute(NumberFormatter::MIN_FRACTION_DIGITS, 0);
    $fmt->setAttribute(NumberFormatter::MAX_FRACTION_DIGITS, $decimals);
    $result = $fmt->format((float)$value);
    return $result;
  }

  /**
   * Format the given date according to $format and $timeZone to a
   * human readable time-stamp, providing defaults for $format and
   * using the default time-zone if none is specified.
   *
   * @param null|int|\DateTimeInterface $date
   *
   * @param null|string $format
   *
   * @param null|\DateTimeZone $timeZone
   *
   * @return string
   */
  public function formatTimeStamp($date = null, ?string $format = null, ?DateTimeZone $timeZone = null):string
  {
    if ($date === null) {
      $date = new DateTimeImmutable;
    } elseif (!($date instanceof \DateTimeInterface)) {
      $date = (new DateTimeImmutable())->setTimestamp($date);
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
  public function timeStamp(?string $format = null, ?DateTimeZone $timeZone = null):string
  {
    return $this->formatTimeStamp(new DateTimeImmutable, $format, $timeZone);
  }
}
