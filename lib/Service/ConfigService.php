<?php
/* Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2014, 2016, 2020 Claus-Justus Heine <himself@claus-justus-heine.de>
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
use \OCP\ILogger;

class ConfigService {
  use \OCA\CAFEVDB\Traits\SessionTrait;

  /****************************************************************************
   *
   * Class constants.
   *
   */
  const DEBUG_GENERAL   = (1 << 0);
  const DEBUG_QUERY     = (1 << 1);
  const DEBUG_REQUEST   = (1 << 2);
  const DEBUG_TOOLTIPS  = (1 << 3);
  const DEBUG_EMAILFORM = (1 << 4);
  const DEBUG_ALL       = self::DEBUG_GENERAL|self::DEBUG_QUERY|self::DEBUG_REQUEST|self::DEBUG_TOOLTIPS|self::DEBUG_EMAILFORM;
  const DEBUG_NONE      = 0;

  /*
   ****************************************************************************
   *
   * Keys for encrypted configuration values. In order for
   * encryption/decryption to work properly, every config setting has
   * to be listed here.
   *
   */
  const CFG_KEYS = [
    'orchestra',
    'dbserver',
    'dbuser',
    'dbpassword',
    'dbname',
    'shareowner',
    'shareownerpassword',
    'sharedfolder',
    'concertscalendar',
    'concertscalendarid',
    'rehearsalscalendar',
    'rehearsalscalendarid',
    'othercalendar',
    'othercalendarid',
    'managementcalendar',
    'managementcalendarid',
    'financecalendar',
    'financecalendarid',
    'eventduration',
    'generaladdressbook',
    'generaladdressbookid',
    'musiciansaddressbook',
    'musiciansaddressbookid',
    'emailuser',
    'emailpassword',
    'emailfromname',
    'emailfromaddress',
    'smtpserver',
    'smtpport',
    'smtpsecure',
    'imapserver',
    'imapport',
    'imapsecure',
    'emailtestaddress',
    'emailtestmode',
    'phpmyadmin',
    'phpmyadminoc',
    'sourcecode',
    'sourcedocs',
    'ownclouddev',
    'presidentId',
    'presidentUserId',
    'presidentUserGroup',
    'secretaryId',
    'secretaryUserId',
    'secretaryUserGroup',
    'treasurerId',
    'treasurerUserId',
    'treasurerUserGroup',
    'streetAddressName01',
    'streetAddressName02',
    'streetAddressStreet',
    'streetAddressHouseNumber',
    'streetAddressCity',
    'streetAddressZIP',
    'streetAddressCountry',
    'phoneNumber',
    'bankAccountOwner',
    'bankAccountIBAN',
    'bankAccountBLZ',
    'bankAccountBIC',
    'bankAccountCreditorIdentifier',
    'projectsbalancefolder',
    'projectsfolder',
    'executiveBoardTable',
    'executiveBoardTableId',
    'memberTable',
    'memberTableId',
    'redaxoPreview',
    'redaxoArchive',
    'redaxoRehearsals',
    'redaxoTrashbin',
    'redaxoTemplate',
    'redaxoConcertModule',
    'redaxoRehearsalsModule',
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
  private $l;

  /** @var IL10NFactory */
  private $iL10NFactory;

  /** @var IURLGenerator */
  private $urlGenerator;

  /** @var IDateTimeZone */
  private $dateTimeZone;

  /** @var EncryptionService */
  private $encryptionService;

  /** @var ISecureRandom */
  private $secureRandom;

  /** @var ILogger */
  private $logger;

  public function __construct(
    $appName,
    IConfig $containerConfig,
    IUserSession $userSession,
    IUserManager $userManager,
    IGroupManager $groupManager,
    ISubAdmin $groupSubAdmin,
    EncryptionService $encryptionService,
    ISecureRandom $secureRandom,
    IURLGenerator $urlGenerator,
    IL10NFactory $iL10NFactory,
    IDateTimeZone $dateTimeZone,
    ILogger $logger,
    IL10N $l
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
    $this->iL10NFactory = $iL10NFactory;
    $this->dateTimeZone = $dateTimeZone;
    $this->logger = $logger;
    $this->l = $l;

    if (defined('OC_CONSOLE') && empty($userSession->getUser())) {
      $this->loginUid = $this->userId = $GLOBALS['cafevdb-user'];
      $this->loginUser = $this->userManager->get($this->loginUid);
      $this->user = $this->userManager->get($this->userId);
    } else {
      $this->loginUser = $this->user = $this->userSession->getUser();
      //trigger_error('user: ' . (empty($this->user) ? 'empty' : 'defined'));
      $this->loginUid = $this->userId = $this->user->getUID();
    }

    // Initialize the encryption service.
    $this->encryptionService->initAppEncryptionKey($this->userId);

    $this->encryptionCache = [];
  }

  public function getAppConfig()
  {
    return $this->containerConfig;
  }

  public function getAppName() {
    return $this->appName;
  }

  public function getIcon() {
    // @@TODO make it configurable
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

  /**Install a new user.
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

  public function getL10N() {
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

  /**A short-cut, redirecting to the stock functions for the app.
   */
  public function getAppValue($key, $default = null)
  {
    return $this->containerConfig->getAppValue($this->appName, $key, $default);
  }

  /**A short-cut, redirecting to the stock functions for the app.
   */
  public function setAppValue($key, $value)
  {
    return $this->containerConfig->setAppValue($this->appName, $key, $value);
  }

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
  public function setAppEncryptionKey($key)
  {
    return $this->encryptionService->setAppEncryptionKey($key);
  }

  public function getAppEncryptionKey()
  {
    return $this->encryptionService->getAppEncryptionKey();
  }

  public function recryptAppEncryptionKey($login, $password, $enckey = null)
  {
    return $this->encryptionService->recryptAppEncryptionkey($login, $password, $enckey);
  }

  public function encryptionKeyValid($encryptionKey = null)
  {
    return $this->encryptionService->encryptionKeyValid($encryptionKey);
  }

  public function getValue($key, $default = null)
  {
    if (!isset($this->encryptionCache[$key])) {
      $value = $this->encryptionService->getValue($key, $default);
      if ($value !== false) {
        $this->encryptionCache[$key] = $value;
      } else {
        return null;
      }
    }
    return $this->encryptionCache[$key];
  }

  public function setValue($key, $value)
  {
    if ($this->encryptionService->setValue($key, $value)) {
      $this->encryptionCache[$key] = $value;
      return true;
    }
    return false;
  }

  public function deleteValue($key)
  {
    return $this->deleteAppValue($key);
  }

  public function generateRandomBytes($length = 30)
  {
    return $this->secureRandom->generate($length);
  }

  /*
   ****************************************************************************
   *
   * logging
   *
   */

  /**Fake execution with other user-id. Note that this function will
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
   * logging
   *
   */

  public function log(int $level, string $message, array $context = []) {
    return $this->logger->log($level, $message, $context);
  }

  public function logException($exception, $message = null) {
    empty($message) && ($message = $this->l->t("Caught an Exception"));
    $this->logger->logException($exception, [ 'message' => $message ]);
  }

  public function logError(string $message, array $context = []) {
    return $this->log(ILogger::ERROR, $message, $context);
  }

  public function logDebug(string $message, array $context = []) {
    return $this->log(ILogger::DEBUG, $message, $context);
  }

  public function logInfo(string $message, array $context = []) {
    return $this->log(ILogger::INFO, $message, $context);
  }

  public function logWarn(string $message, array $context = []) {
    return $this->log(ILogger::WARN, $message, $context);
  }

  public function logFatal(string $message, array $context = []) {
    return $this->log(ILogger::FATAL, $message, $context);
  }

  /*
   ****************************************************************************
   *
   * date time timezone locale
   *
   */

  public function getDateTimeZone($timeStamp = null) {
    return $this->dateTimeZone;
  }

  /**Return the locale as string, e.g. de_DE.UTF-8.
   */
  public function getLocale($lang = null)
  {
    // @@TODO base this on l10n?
    if (empty($lang)) {
      $lang = $this->iL10NFactory->findLanguage($this->appName);
      $this->logInfo('Language seems to be ' . $lang);
    }
    $locale = $lang.'_'.strtoupper($lang).'.UTF-8';
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

  /**Return the currency symbol for the locale. */
  public function currencySymbol($locale = null)
  {
    if (empty($locale)) {
      $locale = self::getLocale();
    }
    $fmt = new \NumberFormatter($locale, \NumberFormatter::CURRENCY);
    return $fmt->getSymbol(\NumberFormatter::CURRENCY_SYMBOL);
  }

  //!Just display the given value
  public function moneyValue($value, $locale = null)
  {
    $oldlocale = setlocale(LC_MONETARY, '0');
    empty($locale) && $locale = $this->getLocale();
    setlocale(LC_MONETARY, $locale);
    $result = money_format('%n', (float)$value);
    setlocale(LC_MONETARY, $oldlocale);
    return $result;
  }
}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
