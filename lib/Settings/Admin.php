<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2011-2016, 2020, 2021, 2022, 2023, 2024 Claus-Justus Heine
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

use OCP\Settings\IDelegatedSettings;
use OCP\App\IAppManager;
use OCP\AppFramework\Services\IInitialState;

use OCA\DokuWiki\Service\AuthDokuWiki as WikiRPC;
use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Service\CloudUserConnectorService;
use OCA\CAFEVDB\Service\FontService;
use OCA\CAFEVDB\Service\AssetService;

use OCA\CAFEVDB\Constants;
use OCA\CAFEVDB\Http\TemplateResponse;

/** Admin settings class. */
class Admin implements IDelegatedSettings
{
  use \OCA\CAFEVDB\Traits\ConfigTrait;

  const TEMPLATE = "admin-settings";

  const PERSONAL_APP_SETTINGS_LINK = 'personalAppSettingsLink';
  const ORCHESTRA_USER_GROUP_KEY = 'orchestraUserGroup';
  const WIKI_NAME_SPACE_KEY = 'wikiNameSpace';
  const WIKI_VERSION = 'wikiVersion';
  const CLOUD_USER_BACKEND_CONFIG_KEY = 'cloudUserBackendConfig';
  const CLOUD_USER_BACKEND = 'cloudUserBackend';
  const CLOUD_USER_BACKEND_RESTRICTIONS = 'cloudUserBackendRestrictions';
  const OFFICE_FONTS = 'officeFonts';
  const SETTINGS_PROPERTIES = 'settingsProperties';
  const IS_ADMIN = 'isAdmin';
  const IS_SUB_ADMIN = 'isSubAdmin';

  const DELEGATABLE = 'delegatable';
  const ADMIN_ONLY = 'admin_only';
  const SETTINGS_PROPERTY_VALUES = [
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

  /** @var IInitialState */
  private $initialState;

  /** @var CloudUserConnectorService */
  private $cloudUserConnector;

  /** @var FontService */
  private $fontService;

  // phpcs:ignore Squiz.Commenting.FunctionComment.Missing
  public function __construct(
    ConfigService $configService,
    AssetService $assetService,
    IInitialState $initialState,
    WikiRPC $wikiRPC,
    IAppManager $appManager,
    CloudUserConnectorService $cloudUserConnector,
    FontService $fontService,
  ) {
    $this->configService = $configService;
    $this->assetService = $assetService;
    $this->initialState = $initialState;
    $this->wikiRPC = $wikiRPC;
    $this->appManager = $appManager;
    $this->cloudUserConnector = $cloudUserConnector;
    $this->fontService = $fontService;
  }
  // phpcs:enable

  /** {@inheritdoc} */
  public function getForm()
  {
    $cloudUserBackend = CloudUserConnectorService::CLOUD_USER_BACKEND;
    // $cloudUserBackendEnabled = $this->appManager->isInstalled($cloudUserBackend);
    $cloudUserBackendRestrictions = $this->appManager->getAppRestriction($cloudUserBackend);
    $haveCloudUserBackendConfig = $this->cloudUserConnector->haveCloudUserBackendConfig();

    $personalAppSettingsLink = $this->urlGenerator()->getBaseUrl() . '/index.php/settings/user/' . $this->appName();

    $isAdmin = $this->groupManager()->isAdmin($this->userId());
    $isSubAdmin = $this->isSubAdminOfGroup();

    $configData = [
      self::ORCHESTRA_USER_GROUP_KEY => $this->getAppValue(ConfigService::USER_GROUP_KEY),
      self::PERSONAL_APP_SETTINGS_LINK => $personalAppSettingsLink,
      self::WIKI_NAME_SPACE_KEY => $this->getAppValue('wikinamespace'),
      self::WIKI_VERSION => $this->wikiRPC->version(),
      self::CLOUD_USER_BACKEND => $cloudUserBackend,
      self::CLOUD_USER_BACKEND_RESTRICTIONS => $cloudUserBackendRestrictions,
      self::CLOUD_USER_BACKEND_CONFIG_KEY => $haveCloudUserBackendConfig,
      FontService::OFFICE_FONTS_FOLDER_CONFIG => $this->fontService->getFontsFolderName(),
      self::OFFICE_FONTS => $this->fontService->scanFontsFolder(),
      FontService::DEFAULT_OFFICE_FONT_CONFIG => $this->fontService->getDefaultFontName(),
      self::SETTINGS_PROPERTIES => self::SETTINGS_PROPERTY_VALUES,
      self::IS_ADMIN => $isAdmin,
      self::IS_SUB_ADMIN => $isSubAdmin,
    ];

    $this->initialState->provideInitialState('adminConfig', $configData);

    return new TemplateResponse(
      $this->appName(),
      self::TEMPLATE, [
        'appName' => $this->appName(),
        'appNameTag' => 'app-' . $this->appName,
        'assets' => [
          Constants::JS => $this->assetService->getJSAsset(self::TEMPLATE),
          Constants::CSS => $this->assetService->getCSSAsset(self::TEMPLATE),
        ],
      ]);
  }

  /** {@inheritdoc} */
  public function getSection()
  {
    return $this->appName();
  }

  /** {@inheritdoc} */
  public function getPriority()
  {
    // @@todo could be made a configure option.
    return 50;
  }

  /** {@inheritdoc} */
  public function getName():?string
  {
    return null; // Only one setting in this section
  }

  /** {@inheritdoc} */
  public function getAuthorizedAppConfig():array
  {
    return []; // Custom controller
  }
}
