<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2011-2014, 2016, 2020-2025 Claus-Justus Heine
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

use DateTimeImmutable;
use DateTimeZone;
use NumberFormatter;
use RuntimeException;
use Throwable;

use OCP\AppFramework\IAppContainer;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Group\ISubAdmin;
use OCP\IConfig;
use OCP\IDateTimeFormatter;
use OCP\IDateTimeZone;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\L10N\IFactory as IL10NFactory;
use OCP\Security\ISecureRandom;
use Psr\Log\LoggerInterface as ILogger;

use OCA\CAFEVDB\Common\RationalNumber;
use OCA\CAFEVDB\Common\Transliterator;
use OCA\CAFEVDB\Controller\EnumPersonalSettingsKey;
use OCA\CAFEVDB\Exceptions;
use OCA\CAFEVDB\Service\L10N\AppL10N;
use OCA\CAFEVDB\Service\L10N\L10NFactory;
use OCA\CAFEVDB\Settings\ConfigConstants;
use OCA\CAFEVDB\Settings\OldSettingsKeys;

/**
 * Configuration do-it-all class.
 *
 * @todo This is called on boot without user, determine why.
 *
 * @bug This class is too big.
 */
class ConfigService
{
  use \OCA\CAFEVDB\Traits\AppConfigTrait;
  use \OCA\CAFEVDB\Traits\UserPreferencesTrait;
  use \OCA\CAFEVDB\Traits\SessionTrait;
  use \OCA\CAFEVDB\Toolkit\Traits\LoggerTrait;
  use \OCA\CAFEVDB\Traits\TimeStampTrait;

  /**
   * @var array
   *
   * Cache encrypted config values in order to speed up things
   */
  protected $encryptionCache = [];

  /*
   ****************************************************************************
   *
   * Private and protected internal data
   *
   */

  /** @var IUser
   *
   * Will be overridden by sudo().
   */
  private $user;

  /** @var IL10N */
  protected AppL10N $appL10n;

  /** @var string */
  protected $appLocale;

  /** @var string */
  protected $appLanguage;

  /**
   * @var array<string, array<string, string>>
   */
  private $localeCountryNames = [];

  /** @var L10NFactory */
  private L10NFactory $l10NFactory;

  /** @var IURLGenerator */
  private IURLGenerator $urlGenerator;

  /** @var ISecureRandom */
  private ISecureRandom $secureRandom;

  /** @var IUserManager */
  private IUserManager $userManager;

  /** @var IGroupManager */
  private IGroupManager $groupManager;

  /** @var ISubAdmin */
  private ISubAdmin $subAdminManager;

  /** @var EncryptionService */
  private EncryptionService $encryptionService;

  /** @var IConfig */
  private IConfig $cloudConfig;

  /** @var IUserSession */
  private IUserSession $userSession;

  /**
   * {@inheritdoc}
   *
   * @SuppressWarnings(PHPMD.Superglobals)
   */
  public function __construct(
    protected string $appName,
    protected IAppContainer $appContainer,
    protected ILogger $logger,
    protected IL10N $l,
  ) {
  }

  /** @return IAppContainer */
  public function getAppContainer():IAppContainer
  {
    return $this->appContainer;
  }

  /**
   * @return IL10NFactory
   */
  private function getL10NFactory():IL10NFactory
  {
    return $this->l10NFactory ?? ($this->l10NFactory = $this->appContainer->get(L10NFactory::class));
  }

  /**
   * @return ISecureRandom
   */
  private function getSecureRandom():ISecureRandom
  {
    return $this->secureRandom ?? ($this->secureRandom = $this->appContainer->get(ISecureRandom::class));
  }

  /** @return ITimeFactory */
  public function getTimeFactory():ITimeFactory
  {
    return $this->appContainer->get(ITimeFactory::class);
  }

  /** @return IConfig */
  public function getAppConfig():IConfig
  {
    return $this->getCloudConfig();
  }

  /** @return IConfig */
  public function getCloudConfig():IConfig
  {
    return $this->cloudConfig ?? ($this->cloudConfig = $this->appContainer->get(IConfig::class));
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
    return $this->getUrlGenerator()->imagePath($this->appName, ConfigConstants::APP_LOGO);
  }

  /** @return IUserSession */
  public function getUserSession():IUserSession
  {
    return $this->userSession ?? ($this->userSession = $this->appContainer->get(IUserSession::class));
  }

  /** @return IUserManager */
  public function getUserManager():IUserManager
  {
    return $this->userManager ?? ($this->userManager = $this->appContainer->get(IUserManager::class));
  }

  /** @return IGroupManager */
  public function getGroupManager():IGroupManager
  {
    return $this->groupManager ?? ($this->groupManager = $this->appContainer->get(IGroupManager::class));
  }

  /** @return ISubAdmin */
  public function getSubAdminManager():ISubAdmin
  {
    return $this->subAdminManager ?? ($this->subAdminManager = $this->appContainer->get(ISubAdmin::class));
  }

  /** @return IURLGenerator */
  public function getUrlGenerator():IURLGenerator
  {
    return $this->urlGenerator ?? ($this->urlGenerator = $this->appContainer->get(IURLGenerator::class));
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
      return $this->getUserManager()->get($userId);
    }
    if (empty($this->user)) {
      $this->user = $this->getUserSession()->getUser();
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
    $this->getUserSession()->setUser($this->user);
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
      $this->appL10n = $this->appContainer->get(AppL10N::class);
    }
    return $this->appL10n;
  }

  /** @return string The orchestra orga-group id. */
  public function getGroupId():string
  {
    return $this->getAppValue(ConfigConstants::USER_GROUP_KEY);
  }

  /**
   * @param null|string $groupId
   *
   * @return null|IGroup The group for the given id or the orchetra group.
   */
  public function getGroup(?string $groupId = null):?IGroup
  {
    empty($groupId) && ($groupId = $this->getGroupId());
    return empty($groupId) ? null : $this->getGroupManager()->get($groupId);
  }

  /**
   * @param null|string $groupId Use the orchestra group if null.
   *
   * @return bool
   */
  public function groupExists($groupId = null):bool
  {
    empty($groupId) && ($groupId = $this->getGroupId());
    return !empty($groupId) && $this->getGroupManager()->groupExists($groupId);
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
    try {
      return $this->getGroupManager()->isInGroup($userId, $groupId);
    } catch (Throwable $t) {
      $this->logException($t);
      return false;
    }
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
    $user = empty($userId) ? $this->getUser() : $this->getUserManager()->get($userId);
    $group = empty($groupId) ? $this->getGroup() : $this->getGroupManager()->get($groupId);

    if (empty($user) || empty($group)) {
      return false;
    }
    return $this->getSubAdminManager()->isSubAdminofGroup($user, $group);
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
    return $this->getSubAdminManager()->getGroupsSubAdmins($group);
  }

  /**
   * Return the id of the dedicated admin-group which contains all sub-admins
   *
   * @return string
   */
  public function getSubAdminGroupId():string
  {
    return $this->getGroupId() . ConfigConstants::ADMIN_GROUP_SUFFIX;
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
    return $this->getGroupManager()->isInGroup($userId, $groupId);
  }

  /*
   *-**************************************************************************
   *
   * encrypted config space
   *
   */

  /** @return EncryptionService */
  public function getEncryptionService():EncryptionService
  {
    return $this->encryptionService ?? ($this->encryptionService = $this->appContainer->get(EncryptionService::class));
  }

  /**
   * @param string $key Encryption key to set.
   *
   * @return void
   */
  public function setUserEncryptionKey(string $key):void
  {
    $this->getEncryptionService()->setUserEncryptionKey($key);
  }

  /** @return null|string */
  public function getUserEncryptionKey():?string
  {
    return $this->getEncryptionService()->getUserEncryptionKey();
  }

  /**
   * @param string $key Encryption key to set.
   *
   * @return void
   */
  public function setAppEncryptionKey(string $key):void
  {
    $this->getEncryptionService()->setAppEncryptionKey($key);
  }

  /** @return null|string */
  public function getAppEncryptionKey():?string
  {
    return $this->getEncryptionService()->getAppEncryptionKey();
  }

  /**
   * @param null|string $value Value to encrypt.
   *
   * @return null|string Encrypted value.
   */
  public function encrypt(?string $value):?string
  {
    return $this->getEncryptionService()->getAppCryptor()->encrypt($value);
  }

  /**
   * @param null|string $value Value to decrypt.
   *
   * @return null|string Decrypted value.
   */
  public function decrypt(?string $value):?string
  {
    return $this->getEncryptionService()->getAppCryptor()->decrypt($value);
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
    return $this->getEncryptionService()->verifyHash($value, $hash);
  }

  /**
   * @param string $value The value to hash.
   *
   * @return string The hash of $value.
   */
  public function computeHash(string $value):string
  {
    return $this->getEncryptionService()->computeHash($value);
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
    return $this->getEncryptionService()->encryptionKeyValid($encryptionKey);
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
      $value = $this->getEncryptionService()->getConfigValue($key, $default, $ignoreLock);
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
    //$this->logInfo("enckey: ". $this->getEncryptionService()->appEncryptionKey);
    if ($this->getEncryptionService()->setConfigValue($key, $value, $ignoreLock)) {
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
        $this->getCloudConfig()->getAppKeys($this->appName),
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
    return $this->getSecureRandom()->generate($length, ISecureRandom::CHAR_HUMAN_READABLE);
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
    return $this->appContainer->get(IDateTimeFormatter::class);
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
    return $this->appContainer->get(IDateTimeZone::class)->getTimeZone($timeStamp);
  }

  /**
   * Cache for self::getLocale().
   */
  private array $localeLanguageCache = [];

  /**
   * Return the locale as string, e.g. de_DE.UTF-8.
   *
   * @param string|null $lang Maybe be a short language string like 'de' or
   * something up to a full-fledged locate symbold 'de_DE.UTF-8'. In the
   * latter case this function is "idem potent" and just returns the given
   * string. If $lang is null the cloud's idea about the language setting is
   * used.
   *
   * @param bool $addEncoding Whether to add '.UTF-8' if not already presend
   * in $lang
   *
   * @return string
   */
  public function getLocale(?string $lang = null, bool $addEncoding = false):string
  {
    if (!empty($this->localeLanguageCache[$lang . $addEncoding])) {
      return $this->localeLanguageCache[$lang . $addEncoding];
    }

    if (empty($lang)) {
      $locale = $this->appContainer->get(Registration::USER_LOCALE);
      $this->logDebug('Locale seems to be ' . $locale);
      $this->logDebug('Language seems to be ' . $lang);
    } else {
      $primary = locale_get_primary_language($lang);
      $region = locale_get_region($lang);
      if (empty($region)) {
        $locale = $primary . '_' . strtoupper($primary);
      } else {
        $locale = $lang;
      }
    }

    if (strpos($locale, '.') === false) {
      if ($addEncoding) {
        $locale .= '.UTF-8';
      }
    } else {
      if (!$addEncoding) {
        $locale = locale_get_primary_language($locale) . '_' . locale_get_region($locale);
      }
    }

    $this->localeLanguageCache[$lang . $addEncoding] = $locale;

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
    if (method_exists($this->getL10NFactory(), 'getLanguages')) {
      $cloudLanguages = $this->getL10NFactory()->getLanguages();
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
    return $this->getL10NFactory()->findAvailableLanguages($app);
  }

  /**
   * @return array
   *
   * @see IL10NFactory::findAvailableLocales()
   */
  public function findAvailableLocales():array
  {
    return $this->getL10NFactory()->findAvailableLocales();
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
    /** @var Transliterator $transliterator */
    $transliterator = $this->appContainer->get(Transliterator::class);

    empty($locale) && $locale = $this->getAppLocale();

    return $transliterator->transliterate($string, ' ', $locale);
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
    if ($value instanceof RationalNumber) {
      $value = $value->toDecimal(2);
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
    if ($value instanceof RationalNumber) {
      $value = $value->toDecimal($decimals);
    }
    empty($locale) && $locale = $this->getLocale();
    $fmt = new NumberFormatter($locale, NumberFormatter::DECIMAL);
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
   * Call ConfigConstants::formatTimeStamp() with the current date and time.
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
