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

use Throwable;

use OCP\Settings\IDelegatedSettings;
use OCP\App\IAppManager;
use OCP\AppFramework\Services\IInitialState;

use OCA\DokuWiki\Service\AuthDokuWiki as WikiRPC;
use OCA\CAFEVDB\Service\AuthorizationService;
use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Service\CloudUserConnectorService;
use OCA\CAFEVDB\Service\FontService;
use OCA\CAFEVDB\Service\AssetService;

use OCA\CAFEVDB\Constants;

/** Admin settings class. */
class Admin implements IDelegatedSettings
{
  use \OCA\CAFEVDB\Traits\ConfigTrait;
  use \OCA\CAFEVDB\Toolkit\Traits\ResponseTrait;

  const TEMPLATE = "admin-settings";

  const PERSONAL_APP_SETTINGS_LINK = 'personalAppSettingsLink';
  const ORCHESTRA_USER_GROUP_KEY = 'orchestraUserGroup';
  const ORCHESTRA_USER_GROUP_ADMINS_KEY = self::ORCHESTRA_USER_GROUP_KEY . 'Admins';
  const DEFAULT_USER_AND_GROUP_BACKEND = 'Database';
  const USER_AND_GROUP_BACKEND_KEY = ConfigService::USER_AND_GROUP_BACKEND_KEY;
  const WIKI_NAME_SPACE_KEY = 'wikiNameSpace';
  const WIKI_VERSION = 'wikiVersion';
  const CLOUD_USER_BACKEND_CONFIG_KEY = 'cloudUserBackendConfig';
  const CLOUD_USER_BACKEND = 'cloudUserBackend';
  const CLOUD_USER_BACKEND_RESTRICTIONS = 'cloudUserBackendRestrictions';
  const OFFICE_FONTS = 'officeFonts';
  const SETTINGS_PROPERTIES = 'settingsProperties';
  const IS_ADMIN = 'isAdmin';
  const IS_SUB_ADMIN = 'isSubAdmin';
  const USER_AND_GROUP_BACKENDS = 'userAndGroupBackends';
  const AUTHORIZATION_GROUP_SUFFIXES = AuthorizationService::GROUP_SUFFIX_LIST;
  const AUTHORIZATION_GROUP_SUFFIXES_KEY = 'authorizationGroupSuffixes';

  const DELEGATABLE = 'delegatable';
  const ADMIN_ONLY = 'admin_only';
  const SETTINGS_PROPERTY_VALUES = [
    self::ORCHESTRA_USER_GROUP_KEY => self::ADMIN_ONLY,
    self::WIKI_NAME_SPACE_KEY => self::DELEGATABLE,
    self::CLOUD_USER_BACKEND_CONFIG_KEY => self::ADMIN_ONLY,
  ];

  // phpcs:ignore Squiz.Commenting.FunctionComment.Missing
  public function __construct(
    protected ConfigService $configService,
    private AssetService $assetService,
    private IInitialState $initialState,
    private WikiRPC $wikiRPC,
    private IAppManager $appManager,
    private CloudUserConnectorService $cloudUserConnector,
    private FontService $fontService,
  ) {
    $this->l = $this->l10n();
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

    $userBackends = array_values(
      array_filter(
        array_map(
          function ($backend) {
            try {
              return $backend->getBackendName();
            } catch (Throwable) {
              return null;
          }
          },
          $this->userManager()->getBackends(),
        )));
    $groupBackends = array_values(
      array_filter(
        array_map(
          function ($backend) {
            try {
              return $backend->getBackendName();
            } catch (Throwable) {
              return null;
            }
          },
          $this->groupManager()->getBackends(),
        )));
    $userAndGroupBackends = array_intersect($userBackends, $groupBackends);

    $this->logInfo('BACKEND U / G ' . print_r($userAndGroupBackends, true));

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
      self::USER_AND_GROUP_BACKENDS => $userAndGroupBackends,
      self::AUTHORIZATION_GROUP_SUFFIXES_KEY => self::AUTHORIZATION_GROUP_SUFFIXES,
    ];

    $this->initialState->provideInitialState('adminConfig', $configData);

    return $this->templateResponse(
      self::TEMPLATE,
      [
        'assets' => [
          Constants::JS => $this->assetService->getJSAsset(self::TEMPLATE),
          Constants::CSS => $this->assetService->getCSSAsset(self::TEMPLATE),
        ],
      ],
    );
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
