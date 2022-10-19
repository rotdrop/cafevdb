<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016, 2020, 2021, 2022 Claus-Justus Heine <himself@claus-justus-heine.de>
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

namespace OCA\CAFEVDB\Settings;

use OCP\AppFramework\Http\TemplateResponse;
use OCP\Settings\IDelegatedSettings;
use OCP\App\IAppManager;

use OCA\DokuWikiEmbedded\Service\AuthDokuWiki as WikiRPC;
use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Service\AssetService;
use OCA\CAFEVDB\Service\CloudUserConnectorService;

class Admin implements IDelegatedSettings {
  use \OCA\CAFEVDB\Traits\ConfigTrait;

  const TEMPLATE = "admin-settings";

  const ORCHESTRA_USER_GROUP_KEY = 'orchestraUserGroup';
  const WIKI_NAME_SPACE_KEY = 'wikiNameSpace';
  const CLOUD_USER_BACKEND_CONFIG_KEY = 'cloudUserBackendConfig';

  const DELEGATABLE = 'delegatable';
  const ADMIN_ONLY = 'admin_only';
  const SETTINGS_PROPERTIES = [
    self::ORCHESTRA_USER_GROUP_KEY => self::ADMIN_ONLY,
    self::WIKI_NAME_SPACE_KEY => self::DELEGATABLE,
    self::CLOUD_USER_BACKEND_CONFIG_KEY => self::ADMIN_ONLY,
  ];

  /** @var AssetService */
  private $assetService;

  /** @var OCA\DokuWikiEmedded\Service\AuthDokuWiki */
  private $wikiRPC;

  /** @var IAppManager */
  private $appManager;

  /** @var CloudUserConnectorService */
  private $cloudUserConnector;

  public function __construct(
    ConfigService $configService
    , AssetService $assetService
    , WikiRPC $wikiRPC
    , IAppManager $appManager
    , CloudUserConnectorService $cloudUserConnector
  ) {
    $this->configService = $configService;
    $this->assetService = $assetService;
    $this->wikiRPC = $wikiRPC;
    $this->appManager = $appManager;
    $this->cloudUserConnector = $cloudUserConnector;
  }

  public function getForm()
  {
    $cloudUserBackend = CloudUserConnectorService::CLOUD_USER_BACKEND;
    $cloudUserBackendEnabled = $this->appManager->isInstalled($cloudUserBackend);
    $cloudUserBackendRestrictions = $this->appManager->getAppRestriction($cloudUserBackend);
    $haveCloudUserBackendConfig = $this->cloudUserConnector->haveCloudUserBackendConfig();

    $personalAppSettingsLink = $this->urlGenerator()->getBaseUrl() . '/index.php/settings/user/' . $this->appName();

    $isAdmin = $this->groupManager()->isAdmin($this->userId());
    $isSubAdmin = $this->isSubAdminOfGroup();

    return new TemplateResponse(
      $this->appName(),
      self::TEMPLATE,
      [
        'assets' => [
          AssetService::JS => $this->assetService->getJSAsset(self::TEMPLATE),
          AssetService::CSS => $this->assetService->getCSSAsset(self::TEMPLATE),
        ],
        'appName' => $this->appName(),
        'config' => [
          self::ORCHESTRA_USER_GROUP_KEY =>  $this->getAppValue('usergroup'),
          'personalAppSettingsLink' => $personalAppSettingsLink,
          self::WIKI_NAME_SPACE_KEY => $this->getAppValue('wikinamespace'),
          'wikiVersion' => $this->wikiRPC->version(),
          'cloudUserBackend' => $cloudUserBackend,
          'cloudUserBackendRestrictions' => $cloudUserBackendRestrictions,
          self::CLOUD_USER_BACKEND_CONFIG_KEY => $haveCloudUserBackendConfig,
          'settingsProperties' => self::SETTINGS_PROPERTIES,
          'isAdmin' => $isAdmin,
          'isSubAdmin' => $isSubAdmin,
        ],
      ]);
  }

  /**
   * @return string the section ID, e.g. 'sharing'
   * @since 9.1
   */
  public function getSection() {
    return $this->appName();
  }

  /**
   * @return int whether the form should be rather on the top or bottom of
   * the admin section. The forms are arranged in ascending order of the
   * priority values. It is required to return a value between 0 and 100.
   *
   * E.g.: 70
   * @since 9.1
   */
  public function getPriority() {
    // @@todo could be made a configure option.
    return 50;
  }

  public function getName(): ?string {
    return null; // Only one setting in this section
  }

  public function getAuthorizedAppConfig(): array {
    return []; // Custom controller
  }
}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
