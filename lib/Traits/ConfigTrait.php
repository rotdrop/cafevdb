<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016, 2020, 2021 Claus-Justus Heine <himself@claus-justus-heine.de>
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

namespace OCA\CAFEVDB\Traits;

use Behat\Transliterator\Transliterator;

use OCP\IUser;
use OCP\IConfig;
use OCP\IL10N;
use OCP\ILogger;
use OCP\AppFramework\IAppContainer;
use OCP\IURLGenerator;
use OCP\IDateTimeFormatter;

use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Service\EncryptionService;
use OCA\CAFEVDB\Service\ToolTipsService;
use OCA\CAFEVDB\Common\Util;

trait ConfigTrait {

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
   */
  public function cloudConfig():IConfig
  {
    return $this->configService->getCloudConfig();
  }

  protected function l10n():IL10N
  {
    return $this->configService->getL10n();
  }

  protected function logger():ILogger
  {
    return $this->configService->logger();
  }

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

  protected function appName():string
  {
    return $this->configService->getAppName();
  }

  protected function appVersion()
  {
    return \OCP\App::getAppVersion($this->appName());
  }

  protected function appConfig()
  {
    return $this->configService->getAppConfig();
  }

  protected function userSession()
  {
    return $this->configService->getUserSession();
  }

  /**
   * @return IURLGenerator
   */
  protected function urlGenerator():IURLGenerator
  {
    return $this->configService->getUrlGenerator();
  }

  protected function userManager()
  {
    return $this->configService->getUserManager();
  }

  protected function groupManager()
  {
    return $this->configService->getGroupManager();
  }

  protected function getUserValue($key, $default = null, $userId = null)
  {
    return $this->configService->getUserValue($key, $default, $userId);
  }

  protected function setUserValue($key, $value, $userId = null)
  {
    return $this->configService->setUserValue($key, $value, $userId);
  }

  /**
   * A short-cut, redirecting to the stock functions for the app.
   */
  protected function getAppValue($key, $default = null)
  {
    return $this->configService->getAppValue($key, $default);
  }

  /**
   * A short-cut, redirecting to the stock functions for the app.
   */
  protected function setAppValue($key, $value)
  {
    return $this->configService->setAppValue($key, $value);
  }

  /**
   * A short-cut, redirecting to the stock functions for the app.
   */
  protected function deleteAppValue($key)
  {
    return $this->configService->deleteAppValue($key);
  }

  /**
   * return EncryptionService
   */
  protected function encryptionService():EncryptionService
  {
    return $this->configService->encryptionService();
  }

  protected function getAppEncryptionKey()
  {
    return $this->configService->getAppEncryptionKey();
  }

  protected function setAppEncryptionKey($key)
  {
    return $this->configService->setAppEncryptionKey($key);
  }

  protected function encryptionKeyValid($encryptionKey = null)
  {
    return $this->configService->encryptionKeyValid($encryptionKey);
  }

  protected function encrypt($value, $key = null)
  {
    return $this->configService->encrypt($value, $key);
  }

  protected function decrypt($value, $key = null)
  {
    return $this->configService->decrypt($value, $key);
  }

  protected function verifyHash($value, $hash)
  {
    return $this->configService->verifyHash($value, $hash);
  }

  protected function computeHash($value)
  {
    return $this->configService->computeHash($value);
  }

  /**Get a possibly encrypted app-config value. */
  public function getConfigValue($key, $default = null)
  {
    return $this->configService->getConfigValue($key, $default);
  }

  /** Set a possibly encrypted app-config value. */
  public function setConfigValue($key, $value)
  {
    return $this->configService->setConfigValue($key, $value);
  }

  public function deleteConfigValue($key)
  {
    return $this->configService->deleteConfigValue($key);
  }

  public function generateRandomBytes($length = 30)
  {
    return $this->configService->generateRandomBytes($length);
  }

  protected function loginUser()
  {
    return $this->configService->loginUser;
  }

  protected function loginUserId()
  {
    return $this->configService->loginUserId;
  }

  protected function user($userId = null)
  {
    return $this->configService->getUser($userId);
  }

  protected function userId()
  {
    return $this->configService->getUserId();
  }

  protected function setUserId($userId)
  {
    return $this->configService->setUserId($userId);
  }

  protected function setUser($user)
  {
    return $this->configService->setUser($user);
  }

  protected function sudo($uid, $callback)
  {
    return $this->configService->sudo($uid, $callback);
  }

  protected function shareOwnerId()
  {
    return $this->getConfigValue('shareowner');
  }

  /**
   * @return null|\OCP\IUSER
   */
  protected function shareOwner():?\OCP\User
  {
    $shareOwnerUid = $this->shareOwnerId();
    if (empty($shareOwnerUid)) {
      return null;
    }
    return $this->user($shareOwnerUid);
  }

  protected function groupId()
  {
    return $this->configService->getGroupId();
  }

  /**
   * @return \OCP\IGroup
   */
  protected function group($groupId = null)
  {
    return $this->configService->getGroup($groupId);
  }

  protected function groupExists($groupId = null)
  {
    return $this->configService->groupExists($groupId);
  }

  protected function inGroup($userId = null, $groupId = null) {
    return $this->configService->inGroup($userId, $groupId);
  }

  protected function isSubAdminOfGroup($userId = null, $groupId = null) {
    return $this->configService->isSubAdminOfGroup($userId, $groupId);
  }

  public function defaultUserIdSlug(?string $surName, ?string $firstName, ?string $nickName)
  {
    if (empty($firstName) && empty($nickName)) {
      return '';
    }
    return Transliterator::transliterate($this->transliterate($nickName?:$firstName), '-')
      .'.'
      . Transliterator::transliterate($this->transliterate($surName), '-');
  }

  protected function getClubMembersProjectId():int
  {
    return (int)$this->getConfigValue('memberProjectId', 0);
  }

  protected function getClubMembersProjectName():string
  {
    return $this->getConfigValue('memberProject', '');
  }

  protected function getExecutiveBoardProjectId():int
  {
    return (int)$this->getConfigValue('executiveBoardProjectId', 0);
  }

  protected function getExcutiveBoardProjectName():string
  {
    return $this->getConfigValue('executiveBoardProject', '');
  }

  protected function getSharedFolderPath()
  {
    return $this->getConfigValue(ConfigService::SHARED_FOLDER, null);
  }

  /**
   * Return the full path to the document templates folder.
   */
  protected function getDocumentTemplatesPath()
  {
    $sharedFolder = $this->getSharedFolderPath();
    if (empty($sharedFolder)) {
      return null;
    }
    $templatesFolder = $this->getConfigValue(ConfigService::DOCUMENT_TEMPLATES_FOLDER);
    if (empty($templatesFolder)) {
      return null;
    }
    return '/' . $sharedFolder . '/' . $templatesFolder;
  }

  /**
   * Return the full path to the finance folder.
   */
  protected function getFinanceFolderPath()
  {
    $sharedFolder = $this->getSharedFolderPath();
    if (empty($sharedFolder)) {
      return null;
    }
    $financeFolder = $this->getConfigValue(ConfigService::FINANCE_FOLDER);
    if (empty($financeFolder)) {
      return null;
    }
    return '/' . $sharedFolder . '/' . $financeFolder;
  }

  /**
   * Return the full path to the bank-transactions folder
   */
  protected function getBankTransactionsPath()
  {
    $financeFolder = $this->getFinanceFolderPath();
    if (empty($financeFolder)) {
      return null;
    }
    $transactionsFolder = $this->getConfigValue(ConfigService::TRANSACTIONS_FOLDER);
    if (empty($transactionsFolder)) {
      return null;
    }
    return '/' . $financeFolder . '/' . $transactionsFolder;
  }

  public function getIcon()
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
   * @param int|\DateTimeInterface $timestamp
   */
  protected function formatDate($timestamp, $format = 'long', \DateTimeZone $timeZone = null, IL10N $l = null)
  {
    if ($timestamp instanceof \DateTimeInterface
        && !($timestamp instanceof \DateTime)) {
      // fix NC misfeature
      $date = new \DateTime();
      $date->setTimestamp($timestamp->getTimestamp());
      $date->setTimezone($timestamp->getTimezone());
      $timestamp = $date;
    }
    return $this->dateTimeFormatter()->formatDate(
      $timestamp, $format, $timeZone, $$l);
  }

  /**
   * Work around NC annoyingly not accepting \DateTimeInterface
   *
   * @param int|\DateTimeInterface $timestamp
   */
  protected function formatDateTime($timestamp, $formatDate = 'long', $formatTime = 'medium', \DateTimeZone $timeZone = null, \OCP\IL10N $l = null)
  {
    if ($timestamp instanceof \DateTimeInterface
        && !($timestamp instanceof \DateTime)) {
      // fix NC misfeature
      $date = new \DateTime();
      $date->setTimestamp($timestamp->getTimestamp());
      $date->setTimezone($timestamp->getTimezone());
      $timestamp = $date;
    }
    return $this->dateTimeFormatter()->formatDateTime(
      $timestamp, $formatDate, $formatTime, $timeZone, $$l);
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

  protected function localeCountryNames($locale = null) {
    return $this->configService->localeCountryNames($locale);
  }

  protected function localeLanguageNames($locale = null) {
    return $this->configService->localeLanguageNames($locale);
  }

  public function findAvailableLanguages($app = 'core') {
    return $this->configService->findAvailableLanguages($app);
  }

  public function findAvailableLocales() {
    return $this->configService->findAvailableLocales();
  }

  /** Transliterate the given string to the given or default locale */
  public function transliterate(string $string, $locale = null):string
  {
    return $this->configService->transliterate($string, $locale);
  }

  /** Return the currency symbol for the locale. */
  public function currencySymbol($locale = null)
  {
    return $this->configService->currencySymbol($locale);
  }

  /** Convert $value to a currency value in the given or default locale */
  public function moneyValue($value, $locale = null)
  {
    return $this->configService->moneyValue($value, $locale);
  }

  /** Convert a float value in the given or default locale */
  public function floatValue($value, $decimals = 2, $locale = null)
  {
    return $this->configService->floatValue($value, $decimals, $locale);
  }

  /** Return the current time as short time-stamp (textual). */
  protected function timeStamp($format = null, $timeZone = null)
  {
    return $this->configService->timeStamp($format, $timeZone);
  }

  /** Return the given time as short time-stamp (textual). */
  protected function formatTimeStamp(\DateTimeInterface $date, $format = null, $timeZone = null)
  {
    return $this->configService->formatTimeStamp($date, $format, $timeZone);
  }

  protected function generateUUID() {
    \Sabre\VObject\UUIDUtil::getUUID();
  }

  protected function translationVariants(string $name) {
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
      array_map(
        function($value) { return strtolower($this->l->t($value)); },
        $variants)
    );
    return array_unique($variants);
  }

  protected function toolTipsService()
  {
    if (empty($this->toolTipsService)) {
      $this->toolTipsService = $this->di(ToolTipsService::class);
      if (!empty($this->toolTipsService)) {
        $debugMode = $this->getConfigValue('debugmode', 0);
        $this->toolTipsService->debug(!!($debugMode & ConfigService::DEBUG_TOOLTIPS));
      }
    }
    return $this->toolTipsService;
  }

  /****************************************************************************
   *
   * short-cuts
   *
   */

  protected function databaseConfigured()
  {
    return !(empty($this->getConfigValue('dbname'))
             || empty($this->getConfigValue('dbuser'))
             || empty($this->getConfigValue('dbpassword'))
             || empty($this->getConfigValue('dbserver')));
  }

  protected function log(int $level, string $message, array $context = [], $shift = 2)
  {
    return $this->configService->log($level, $message, $context, $shift);
  }

  protected function logException($exception, $message = null, $shift = 2)
  {
    return $this->configService->logException($exception, $message, $shift);
  }

  protected function logError(string $message, array $context = [], $shift = 2) {
    $this->configService->logError($message, $context, $shift);
  }

  protected function logDebug(string $message, array $context = [], $shift = 2) {
    $this->configService->logDebug($message, $context, $shift);
  }

  protected function logInfo(string $message, array $context = [], $shift = 2) {
    $this->configService->logInfo($message, $context, $shift);
  }

  protected function logWarn(string $message, array $context = [], $shift = 2) {
    $this->configService->logWarn($message, $context, $shift);
  }

  protected function logFatal(string $message, array $context = [], $shift = 2) {
    $this->configService->logFatal($message, $context, $shift);
  }

  /** Forward to OCP\IConfig::getSystemValue() */
  protected function getSystemValue($key , $default = '')
  {
    $this->cloudConfig()->getSystemValue($key, $default);
  }

  /** Forward to OCP\IConfig::getSystemValueBool() */
  protected function getSystemValueBool($key , $default = ''): bool
  {
    $this->cloudConfig()->getSystemValueBool($key, $default);
  }

  /** Forward to OCP\IConfig::getSystemValueInt() */
  protected function getSystemValueInt($key , $default = ''): int
  {
    $this->cloudConfig()->getSystemValueInt($key, $default);
  }

  /** Forward to OCP\IConfig::getSystemValueString() */
  protected function getSystemValueString($key , $default = ''): string
  {
    $this->cloudConfig()->getSystemValueString($key, $default);
  }

  protected function shouldDebug(int $flag): bool
  {
    $debugMode = $this->getConfigValue('debugmode', 0);
    return ($debugMode & $flag) != 0;
  }
}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
