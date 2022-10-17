<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2011-2016, 2020, 2021, 2022 Claus-Justus Heine
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

namespace OCA\CAFEVDB\Traits;

use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;

use Behat\Transliterator\Transliterator;

use OCP\IUser;
use OCP\IConfig;
use OCP\IL10N;
use OCP\ILogger;
use OCP\IUserSession;
use OCP\AppFramework\IAppContainer;
use OCP\IURLGenerator;
use OCP\IDateTimeFormatter;
use OCP\IUserManager;
use OCP\IGroupManager;
use OCP\Group\ISubAdmin;

use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Service\EncryptionService;
use OCA\CAFEVDB\Service\ToolTipsService;
use OCA\CAFEVDB\Common\Util;

/**
 * Too big a traits class to forward most of the ConfigService methods to the
 * using class.
 */
trait ConfigTrait
{
  use LoggerTrait;

  /** @var ConfigService */
  protected $configService;

  /** @var ToolTipsService */
  protected $toolTipsService;

  /** @var IL10N */
  protected $l;

  /**
   * Return the stored config-service for convenience.
   *
   * @return \OCA\CAFEVDB\Service\ConfigService
   */
  public function configService():ConfigService
  {
    return $this->configService;
  }

  /**
   * Return the stored config-service of the embedding cloud
   * container.
   *
   * @return IConfig
   */
  public function cloudConfig():IConfig
  {
    return $this->configService->getCloudConfig();
  }

  /** @return IL10N */
  protected function l10n():IL10N
  {
    return $this->configService->getL10n();
  }

  /** @return IL10N */
  protected function appL10n():IL10N
  {
    return $this->configService->getAppL10n();
  }

  /** @return ILogger */
  public function logger():ILogger
  {
    return $this->configService ? $this->configService->logger() : $this->logger;
  }

  /** @return IAppContainer */
  protected function appContainer():IAppContainer
  {
    return $this->configService->getAppContainer();
  }

  /**
   * Dependency injection of $className.
   *
   * @param string $className
   *
   * @return mixed
   */
  protected function di(string $className)
  {
    return $this->appContainer()->get($className);
  }

  /** @return string */
  protected function appName():string
  {
    return $this->configService->getAppName();
  }

  /** @return string */
  protected function appVersion():string
  {
    return \OCP\App::getAppVersion($this->appName());
  }

  /** @return IConfig */
  protected function appConfig():IConfig
  {
    return $this->configService->getAppConfig();
  }

  /** @return IUserSession */
  protected function userSession():IUserSession
  {
    return $this->configService->getUserSession();
  }

  /** @return IURLGenerator */
  protected function urlGenerator():IURLGenerator
  {
    return $this->configService->getUrlGenerator();
  }

  /** @return IUserManager */
  protected function userManager():IUserManager
  {
    return $this->configService->getUserManager();
  }

  /** @return IGroupManager */
  protected function groupManager():IGroupManager
  {
    return $this->configService->getGroupManager();
  }

  /** @return ISubAdmin */
  protected function subAdminManager():ISubAdmin
  {
    return $this->configService->getSubAdminManager();
  }

  /**
   * @param string $key Config key.
   *
   * @param mixed $default Default value.
   *
   * @param null|string $userId Use the current user if null.
   *
   * @return mixed
   */
  protected function getUserValue(string $key, mixed $default = null, ?string $userId = null)
  {
    return $this->configService->getUserValue($key, $default, $userId);
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
  protected function setUserValue(string $key, mixed $value, ?string $userId = null)
  {
    return $this->configService->setUserValue($key, $value, $userId);
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
  protected function getAppValue(string $key, mixed $default = null)
  {
    return $this->configService->getAppValue($key, $default);
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
  protected function setAppValue(string $key, mixed $value)
  {
    return $this->configService->setAppValue($key, $value);
  }

  /**
   * A short-cut, redirecting to the stock functions for the app.
   *
   * @param string $key Config key.
   *
   * @return void
   */
  protected function deleteAppValue(string $key)
  {
    $this->configService->deleteAppValue($key);
  }

  /** @return EncryptionService */
  protected function encryptionService():EncryptionService
  {
    return $this->configService->encryptionService();
  }

  /** @return null|string */
  protected function getAppEncryptionKey():?string
  {
    return $this->configService->getAppEncryptionKey();
  }

  /**
   * @param string $key Encryption key to set.
   *
   * @return void
   */
  protected function setAppEncryptionKey(string $key):void
  {
    $this->configService->setAppEncryptionKey($key);
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
  protected function encryptionKeyValid(?string $encryptionKey = null):bool
  {
    return $this->configService->encryptionKeyValid($encryptionKey);
  }

  /**
   * @param null|string $value Value to encrypt.
   *
   * @return null|string Encrypted value.
   */
  protected function encrypt($value)
  {
    return $this->configService->encrypt($value);
  }

  /**
   * @param null|string $value Value to decrypt.
   *
   * @return null|string Decrypted value.
   */
  protected function decrypt($value)
  {
    return $this->configService->decrypt($value);
  }

  /**
   * @param null|string $value Value to verify.
   *
   * @param null|string $hash Hash to verify against.
   *
   * @return bool \true if either hash or value are empty or if the hash could
   * be verified.
   */
  protected function verifyHash($value, $hash)
  {
    return $this->configService->verifyHash($value, $hash);
  }

  /**
   * @param string $value The value to hash.
   *
   * @return string The hash of $value.
   */
  protected function computeHash(string $value)
  {
    return $this->configService->computeHash($value);
  }

  /**
   * Get a possibly encrypted config value.
   *
   * @param string $key
   *
   * @param mixed $default
   *
   * @return mixed
   *
   * @throws Exceptions\ConfigLockedException
   */
  public function getConfigValue(string $key, mixed $default = null)
  {
    return $this->configService->getConfigValue($key, $default);
  }

  /**
   * @param string $key
   *
   * @param mixed $value
   *
   * @return bool Success or not.
   *
   * @throws Exceptions\ConfigLockedException
   */
  public function setConfigValue(string $key, mixed $value):bool
  {
    return $this->configService->setConfigValue($key, $value);
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
    $this->configService->deleteConfigValue($key);
  }

  /**
   * Would rather belong to the EncryptionService.
   *
   * @param int $length Length of random string.
   *
   * @return string
   */
  public function generateRandomBytes(int $length = 30):string
  {
    return $this->configService->generateRandomBytes($length);
  }

  /**
   * @param null|string $userId
   *
   * Get the currently active user.
   *
   * @return null|IUser
   */
  protected function user(?string $userId = null):IUser
  {
    return $this->configService->getUser($userId);
  }

  /** @return null|string */
  protected function userId():?string
  {
    return $this->configService->getUserId();
  }

  /**
   * Install a new user id.
   *
   * @param string $userId The user id to install.
   *
   * @return null|IUser old user.
   */
  protected function setUserId(string $userId):?IUser
  {
    return $this->configService->setUserId($userId);
  }

  /**
   * Install a new user.
   *
   * @param null|IUser $user
   *
   * @return null|IUser old user.
   */
  protected function setUser($user):?IUser
  {
    return $this->configService->setUser($user);
  }

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
  protected function sudo(string $uid, callable $callback)
  {
    return $this->configService->sudo($uid, $callback);
  }

  /** @return null|string the User id of the share-owner, if configured. */
  protected function shareOwnerId():?string
  {
    return $this->getConfigValue('shareowner');
  }

  /**
   * @return null|\OCP\IUSER
   */
  protected function shareOwner():?\IUser
  {
    $shareOwnerUid = $this->shareOwnerId();
    if (empty($shareOwnerUid)) {
      return null;
    }
    return $this->user($shareOwnerUid);
  }


  /** @return string The orchestra orga-group id. */
  protected function groupId():?string
  {
    return $this->configService->getGroupId();
  }

  /**
   * @param null|string $groupId
   *
   * @return null|IGroup The group for the given id or the orchetra group.
   */
  protected function group(?string $groupId = null):?IGroup
  {
    return $this->configService->getGroup($groupId);
  }

  /**
   * @param null|string $groupId Use the orchestra group if null.
   *
   * @return bool
   */
  protected function groupExists(?string $groupId = null):bool
  {
    return $this->configService->groupExists($groupId);
  }

  /**
   * @param null|string $userId Use the current user if null.
   *
   * @param null|string $groupId then Use orchestra group if null.
   *
   * @return bool
   */
  protected function inGroup(?string $userId = null, ?string $groupId = null):bool
  {
    return $this->configService->inGroup($userId, $groupId);
  }

  /**
   * @param null|string $userId Use the current user if null.
   *
   * @param null|string $groupId then Use orchestra group if null.
   *
   * @return bool
   */
  protected function isSubAdminOfGroup(?string $userId = null, ?string $groupId = null):bool
  {
    return $this->configService->isSubAdminOfGroup($userId, $groupId);
  }

  /**
   * Return all the sub-admins of the given or the configured orchestra group.
   *
   * @param null|string $groupId then Use orchestra group if null.
   *
   * @return array
   */
  protected function getGroupSubAdmins(?string $groupId = null):array
  {
    return $this->configService->getGroupSubAdmins($groupId);
  }

  /**
   * Return the id of the dedicated admin-group which contains all sub-admins
   *
   * @return string
   */
  protected function subAdminGroupId():string
  {
    return $this->configService->getSubAdminGroupId();
  }

  /**
   * Return the dedicated admin-group if it exists.
   *
   * @return null|IGroup
   */
  protected function subAdminGroup():?IGroup
  {
    return $this->configService->getSubAdminGroup();
  }

  /**
   * Check if the currently logged in or given user-id belongs to the
   * dedicated sub-admin group.
   *
   * @param null|string $userId
   *
   * @return bool
   */
  protected function inSubAdminGroup(?string $userId = null):bool
  {
    return $this->configService->inSubAdminGroup($userId);
  }

  /**
   * @param null|string $surName
   *
   * @param null|string $firstName
   *
   * @param null|string $nickName
   *
   * @return string The default user-id slug.
   */
  public function defaultUserIdSlug(?string $surName, ?string $firstName, ?string $nickName):string
  {
    if (empty($firstName) && empty($nickName)) {
      return '';
    }
    return Transliterator::transliterate($this->transliterate($nickName?:$firstName), '-')
      .'.'
      . Transliterator::transliterate($this->transliterate($surName), '-');
  }

  /** @return int */
  protected function getClubMembersProjectId():int
  {
    return (int)$this->getConfigValue('memberProjectId', 0);
  }

  /** @return string */
  protected function getClubMembersProjectName():string
  {
    return $this->getConfigValue('memberProject', '');
  }

  /** @return int */
  protected function getExecutiveBoardProjectId():int
  {
    return (int)$this->getConfigValue('executiveBoardProjectId', 0);
  }

  /** @return string */
  protected function getExcutiveBoardProjectName():string
  {
    return $this->getConfigValue('executiveBoardProject', '');
  }

  /** @return null|string */
  protected function getSharedFolderPath():?string
  {
    return $this->getConfigValue(ConfigService::SHARED_FOLDER, null);
  }

  /**
   * Create the full-path for the sub-folder corresponding to the given config
   * key.
   *
   * @param string $configKey
   *
   * @return string
   */
  protected function getSharedSubFolderPath(string $configKey):string
  {
    $sharedFolder = $this->getSharedFolderPath();
    if (empty($sharedFolder)) {
      return null;
    }
    $subFolder = $this->getConfigValue($configKey);
    if (empty($subFolder)) {
      return null;
    }
    return '/' . $sharedFolder . '/' . $subFolder;
  }

  /**
   * Return the full path to the document templates folder.
   *
   * @param string $templateName If given return the full path to the
   * given document template which can be one of the array-keys of
   * ConfigService::DOCUMENT_TEMPLATES.
   *
   * @param bool $dirName If $templateName is given return only the
   * dirname part of the path.
   *
   * @return string Cloud file-system path as requested.
   */
  protected function getDocumentTemplatesPath(string $templateName = null, bool $dirName = false)
  {
    $pathComponents = [''];
    $sharedFolder = $this->getSharedFolderPath();
    if (empty($sharedFolder)) {
      return null;
    }
    $pathComponents[] = $sharedFolder;
    $templatesFolder = $this->getConfigValue(ConfigService::DOCUMENT_TEMPLATES_FOLDER);
    if (empty($templatesFolder)) {
      return null;
    }
    $pathComponents[] = $templatesFolder;
    if (!empty($templateName)) {
      $subFolder = ConfigService::DOCUMENT_TEMPLATES[$templateName]['folder']??null;
      if (!empty($subFolder)) {
        $subFolder = $this->getConfigValue($subFolder);
        if (!empty($subFolder)) {
          $pathComponents[] = $subFolder;
        }
      }
      $templateFileName = $this->getConfigValue($templateName);
      if (empty($templateFileName)) {
        return null;
      }
      $pathComponents[] = $dirName ? '' : $templateFileName ;
    }
    return implode('/', $pathComponents);
  }

  /**
   * @return null|string Return the full path to the finance folder.
   */
  protected function getProjectsFolderPath():?string
  {
    return $this->getSharedSubFolderPath(ConfigService::PROJECTS_FOLDER);
  }

  /**
   * @return null|string Return the full path to the finance folder.
   */
  protected function getFinanceFolderPath():?string
  {
    return $this->getSharedSubFolderPath(ConfigService::FINANCE_FOLDER);
  }

  /**
   * @return null|string Return the full path to the bank-transactions folder
   */
  protected function getBankTransactionsPath():?string
  {
    $financeFolder = $this->getFinanceFolderPath();
    if (empty($financeFolder)) {
      return null;
    }
    $transactionsFolder = $this->getConfigValue(ConfigService::TRANSACTIONS_FOLDER);
    if (empty($transactionsFolder)) {
      return null;
    }
    return $financeFolder . '/' . $transactionsFolder;
  }

  /**
   * @return null|string Return the full path to the financial balances folder
   */
  protected function getFinancialBalancesPath():?string
  {
    $financeFolder = $this->getFinanceFolderPath();
    if (empty($financeFolder)) {
      return null;
    }
    $balancesFolder = $this->getConfigValue(ConfigService::BALANCES_FOLDER);
    if (empty($balancesFolder)) {
      return null;
    }
    return $financeFolder . '/' . $balancesFolder;
  }

  /**
   * @return null|string Return the full path to the financial balances folder
   */
  protected function getProjectBalancesPath():?string
  {
    $balancesFolder = $this->getFinancialBalancesPath();
    if (empty($balancesFolder)) {
      return null;
    }
    $projectsFolder = $this->getConfigValue(ConfigService::PROJECTS_FOLDER);
    if (empty($projectsFolder)) {
      return null;
    }
    return $balancesFolder . '/' . $projectsFolder;
  }

  /**
   * @return null|string Return the full path to incoming post-box folder.
   */
  protected function getPostBoxFolderPath():?string
  {
    return $this->getSharedSubFolderPath(ConfigService::POSTBOX_FOLDER);
  }

  /**
   * @return null|string Return the full path to the outgoing postbox folder.
   */
  protected function getOutBoxFolderPath():?string
  {
    return $this->getSharedSubFolderPath(ConfigService::OUTBOX_FOLDER);
  }

  /** @return string Image web-path to the app-icon. */
  public function getIcon():string
  {
    return $this->configService->getIcon();
  }

  /**
   * Get the current timezone
   *
   * @param bool|int $timeStamp
   *
   * @return \DateTimeZone
   */
  protected function getDateTimeZone($timeStamp = false):\DateTimeZone
  {
    return $this->configService->getDateTimeZone($timeStamp);
  }

  /**
   * Return the current time-zone string
   *
   * @param bool|int $timeStamp
   *
   * @return string
   */
  protected function getTimezone($timeStamp = false):string
  {
    $timeZone = $this->getDateTimeZone($timeStamp);
    if (empty($timeZone)) {
      return 'UTC';
    }
    $zoneName = $timeZone->getName();
    return empty($zoneName) ? 'UTC' : $zoneName;
  }

  /**
   * @return IDateTimeFormatter
   */
  protected function dateTimeFormatter():IDateTimeFormatter
  {
    return $this->configService->dateTimeFormatter();
  }

  /**
   * Work around NC annoyingly not accepting \DateTimeInterface
   *
   * @param int|DateTimeInterface $timestamp
   *
   * @param string $format
   *
   * @param DateTimeZone $timeZone
   *
   * @param IL10N $l
   *
   * @return string
   */
  protected function formatDate($timestamp, string $format = 'long', DateTimeZone $timeZone = null, IL10N $l = null):string
  {
    if ($timestamp instanceof DateTimeInterface
        && !($timestamp instanceof DateTime)) {
      // fix NC misfeature
      $date = new DateTime();
      $date->setTimestamp($timestamp->getTimestamp());
      $date->setTimezone($timestamp->getTimezone());
      $timestamp = $date;
    }
    return $this->dateTimeFormatter()->formatDate(
      $timestamp, $format, $timeZone, $l);
  }

  /**
   * Work around NC annoyingly not accepting \DateTimeInterface
   *
   * @param int|DateTimeInterface $timestamp
   *
   * @param string $formatDate
   *
   * @param string $formatTime
   *
   * @param DateTimeZone $timeZone
   *
   * @param IL10N $l
   *
   * @return string
   */
  protected function formatDateTime($timestamp, string $formatDate = 'long', string $formatTime = 'medium', DateTimeZone $timeZone = null, IL10N $l = null)
  {
    if ($timestamp instanceof DateTimeInterface
        && !($timestamp instanceof DateTime)) {
      // fix NC misfeature
      $date = new DateTime();
      $date->setTimestamp($timestamp->getTimestamp());
      $date->setTimezone($timestamp->getTimezone());
      $timestamp = $date;
    }
    return $this->dateTimeFormatter()->formatDateTime(
      $timestamp, $formatDate, $formatTime, $timeZone, $l);
  }

  /**
   * Return the locale.
   *
   * @param null|string $lang
   *
   * @return string
   */
  protected function getLocale(?string $lang = null):string
  {
    return $this->configService->getLocale($lang);
  }

  /** @return string */
  protected function appLocale():string
  {
    return $this->configService->getAppLocale();
  }

  /**
   * @param null|string $locale
   *
   * @return string
   */
  protected function getLanguage(?string $locale = null):string
  {
    return $this->configService->getLanguage($locale);
  }

  /**
   * @param null|string $locale
   *
   * @return array
   */
  protected function localeCountryNames($locale = null):array
  {
    return $this->configService->localeCountryNames($locale);
  }

  /**
   * @param null|string $locale
   *
   * @return array
   */
  protected function localeLanguageNames($locale = null):array
  {
    return $this->configService->localeLanguageNames($locale);
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
    return $this->configService->findAvailableLanguages($app);
  }

  /**
   * @return array
   *
   * @see IL10NFactory::findAvailableLocales()
   */
  public function findAvailableLocales():array
  {
    return $this->configService->findAvailableLocales();
  }

  /**
   * Transliterate the given string to the given or default locale
   *
   * @param string $string
   *
   * @param null|string $locale
   *
   * @return string
   */
  public function transliterate(string $string, ?string $locale = null):string
  {
    return $this->configService->transliterate($string, $locale);
  }

  /**
   * Return the currency code for the locale.
   *
   * @param null|string $locale
   *
   * @return string
   */
  public function currencyCode($locale = null):string
  {
    return $this->configService->currencyIsoCode($locale);
  }

  /**
   * Return the currency symbol for the locale.
   *
   * @param null|string $locale
   *
   * @return string
   */
  public function currencySymbol(?string $locale = null):string
  {
    return $this->configService->currencySymbol($locale);
  }

  /**
   * Convert $value to a currency value in the given or default locale.
   *
   * @param mixed $value
   *
   * @param null|string $locale
   *
   * @return string
   */
  public function moneyValue(mixed $value, ?string $locale = null):string
  {
    return $this->configService->moneyValue($value, $locale);
  }

  /**
   * Convert a float value in the given or default locale.
   *
   * @param mixed $value
   *
   * @param int $decimals
   *
   * @param null|string $locale
   *
   * @return string
   */
  public function floatValue(mixed $value, int $decimals = 2, ?string $locale = null):?string
  {
    return $this->configService->floatValue($value, $decimals, $locale);
  }

  /**
   * Format a bytes value with "readable" postfix.
   *
   * @param int $bytes
   *
   * @param null|string $locale
   *
   * @param bool $binary
   *
   * @param int $digits
   *
   * @return string
   */
  protected function humanFileSize(int $bytes, ?string $locale = null, bool $binary = true, int $digits = 2):string
  {
    $locale = $locale ?? $this->getLocale();
    return Util::humanFileSize($bytes, $locale, $binary, $digits);
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
  protected function timeStamp(?string $format = null, ?DateTimeZone $timeZone = null):string
  {
    return $this->configService->timeStamp($format, $timeZone);
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
  protected function formatTimeStamp($date = null, ?string $format = null, ?DateTimeZone $timeZone = null):string
  {
    return $this->configService->formatTimeStamp($date, $format, $timeZone);
  }

  /** @return int Return the current Unix timestamp. */
  protected function getTimeStamp():int
  {
    return $this->configService->getTimeFactory()->getTime();
  }

  /** @return string */
  protected function generateUUID():string
  {
    return \Sabre\VObject\UUIDUtil::getUUID();
  }

  /**
   * Search for translation variants by trying several camel-case
   * transformations.
   *
   * @param string $name
   *
   * @return string
   */
  protected function translationVariants(string $name)
  {
    $camelCase = Util::dashesToCamelCase($name, true, '-_ ');
    $words = ucwords(Util::camelCaseToDashes($camelCase, ' '), ' ');
    $variants = array_unique([
      $name,
      strtolower($name),
      strtoupper($name),
      lcfirst($camelCase),
      $camelCase,
      strtolower($camelCase),
      strtoupper($camelCase),
      $words,
      strtoupper($words),
    ]);
    $variants = array_merge(
      array_map('strtolower', $variants),
      array_map(fn($value) => strtolower($this->l->t($value)), $variants)
    );
    return array_unique($variants);
  }

  /** @return ToolTipsService */
  protected function toolTipsService():ToolTipsService
  {
    if (empty($this->toolTipsService)) {
      $this->toolTipsService = $this->di(ToolTipsService::class);
      if (!empty($this->toolTipsService)) {
        $this->toolTipsService->debug($this->shouldDebug(ConfigService::DEBUG_TOOLTIPS));
      }
    }
    return $this->toolTipsService;
  }

  /*-**************************************************************************
   *
   * short-cuts
   *
   */

  /** @return bool */
  protected function databaseConfigured():bool
  {
    return !(empty($this->getConfigValue('dbname'))
             || empty($this->getConfigValue('dbuser'))
             || empty($this->getConfigValue('dbpassword'))
             || empty($this->getConfigValue('dbserver')));
  }

  /**
   * Forward to OCP\IConfig::getSystemValue().
   *
   * @param string $key
   *
   * @param mixed $default
   *
   * @return mixed
   */
  protected function getSystemValue(string $key, mixed $default = '')
  {
    $this->cloudConfig()->getSystemValue($key, $default);
  }

  /**
   * Forward to OCP\IConfig::getSystemValueBool().
   *
   * @param string $key
   *
   * @param mixed $default
   *
   * @return bool
   */
  protected function getSystemValueBool(string $key, mixed $default = ''):bool
  {
    return $this->cloudConfig()->getSystemValueBool($key, $default);
  }

  /**
   * Forward to OCP\IConfig::getSystemValueInt()
   *
   * @param string $key
   *
   * @param mixed $default
   *
   * @return int
   */
  protected function getSystemValueInt(string $key, mixed $default = ''):int
  {
    return $this->cloudConfig()->getSystemValueInt($key, $default);
  }

  /**
   * Forward to OCP\IConfig::getSystemValueString().
   *
   * @param string $key
   *
   * @param mixed $default
   *
   * @return string
   */
  protected function getSystemValueString(string $key, mixed $default = ''):string
  {
    return $this->cloudConfig()->getSystemValueString($key, $default);
  }

  /**
   * Check whether the current config-setting for debug contains the provided
   * flags.
   *
   * @param int $flag
   *
   * @return bool
   */
  protected function shouldDebug(int $flag):bool
  {
    $debugMode = (int)$this->getConfigValue('debugmode', 0);
    return ($debugMode & $flag) != 0;
  }
}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
