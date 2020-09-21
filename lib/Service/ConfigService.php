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
use OCP\L10N\IFactory;
use OCP\IDateTimeZone;

class ConfigService {
  const DEBUG_GENERAL   = (1 << 0);
  const DEBUG_QUERY     = (1 << 1);
  const DEBUG_REQUEST   = (1 << 2);
  const DEBUG_TOOLTIPS  = (1 << 3);
  const DEBUG_EMAILFORM = (1 << 4);
  const DEBUG_ALL       = self::DEBUG_GENERAL|self::DEBUG_QUERY|self::DEBUG_REQUEST|self::DEBUG_TOOLTIPS|self::DEBUG_EMAILFORM;
  const DEBUG_NONE      = 0;

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

  /** @var IUser */
  private $user;

  /** @var string */
  private $userId;

  /** @var IL10N */
  private $l;

  /** @var IFactory */
  private $iFactory;

  /** @var IURLGenerator */
  private $urlGenerator;

  /** @var IDateTimeZone */
  private $dateTimeZone;

  /** @var ToolTipsService */
  private $toolTipsService;
  
  public static $wysiwygOptions = [
    'tinymce' => [ 'name' => 'TinyMCE', 'enabled' => true],
    // @@TODO: is inline script really so evil?
    'ckeditor' => [ 'name' => 'CKEditor', 'enabled' => false],
  ];
  
  public function __construct(
    $appName,
    IConfig $containerConfig,
    IUserSession $userSession,
    IUserManager $userManager,
    IGroupManager $groupManager,
    ISubAdmin $groupSubAdmin,
    IURLGenerator $urlGenerator,
    IFactory $iFactory,
    IDateTimeZone $dateTimeZone,
    IL10N $l
  ) {

    $this->appName = $appName;
    $this->containerConfig = $containerConfig;
    $this->userSession = $userSession;
    $this->userManager = $userManager;
    $this->groupManager = $groupManager;
    $this->groupSubAdmin = $groupSubAdmin;
    $this->urlGenerator = $urlGenerator;
    $this->iFactoy = $iFactory;
    $this->dateTimeZone = $dateTimeZone;
    $this->l = $l;

    $this->user = $this->userSession->getUser();
    trigger_error('user: ' . (empty($this->user) ? 'empty' : 'defined'));
    $this->userId = $this->user->getUID();
  }

  public function getAppName() {
    return $this->appName;
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
      return $this->userManager->getUser($userId);
    }
    return $this->user;
  }

  public function getUserId() {
    return $this->userId;
  }

  public function getL10N() {
    return $this->l;
  }

  public function getGroupId() {
    return $this->getAppValue('usergroup');
  }

  public function groupExists($groupId = null) {
    if (empty($groupId)) {
      $groupId = $this->getGroupId();
    }
    return !empty($groupId) && $this->groupManager->groupExists($groupId);
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

  public function getIcon() {
    // @@TODO make it configurable
    return $this->urlGenerator->imagePath($this->appName, 'logo-greyf.svg');
  }

  public function getDateTimeZone($timeStamp = null) {
    $this->dateTimeZone->getTimeZone($timeStamp);
  }

  /**Return the locale. */
  public function getLocale($lang = null)
  {
    if (empty($lang)) {
      $lang = $this->iFactory->findLanguage($this->appName);
    }
    $locale = $lang.'_'.strtoupper($lang).'.UTF-8';
    return $locale;
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
