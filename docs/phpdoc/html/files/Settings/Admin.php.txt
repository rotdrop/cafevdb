<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2011-2016, 2020-2025 Claus-Justus Heine
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

use Spatie\TypeScriptTransformer\Attributes as TSAttributes;

use Throwable;

use OCP\AppFramework\Services\IInitialState;
use OCP\App\IAppManager;
use OCP\Settings\IDelegatedSettings;

use OCA\CAFEVDB\Constants;
use OCA\CAFEVDB\Service\AssetService;
use OCA\CAFEVDB\Service\AuthorizationService;
use OCA\CAFEVDB\Service\CloudUserConnectorService;
use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Service\FontService;
use OCA\DokuWiki\Service\AuthDokuWiki as WikiRPC;

/** Admin settings class. */
#[TSAttributes\TypeScript]
class Admin implements IDelegatedSettings
{
  use \OCA\CAFEVDB\Traits\ConfigTrait;
  use \OCA\CAFEVDB\Toolkit\Traits\ResponseTrait;

  const TEMPLATE = "admin-settings";

  const INITIAL_STATE_SECTION = 'adminConfig';

  const AUTHORIZATION_GROUP_SUFFIXES = AuthorizationService::GROUP_SUFFIX_LIST;
  const AUTHORIZATION_GROUP_SUFFIXES_KEY = 'authorizationGroupSuffixes';
  const CLOUD_USER_BACKEND = 'cloudUserBackend';
  const CLOUD_USER_BACKEND_RESTRICTIONS = 'cloudUserBackendRestrictions';
  const DEFAULT_OFFICE_FONT_CONFIG = FontService::DEFAULT_OFFICE_FONT_CONFIG;
  const DEFAULT_USER_AND_GROUP_BACKEND = 'Database';
  const EMAIL_CHALLENGE_SUFFIX = 'Challenge';
  const EMAIL_VERIFICATION_SUFFIX = 'Verification';
  const EMAIL_STATUS_SUFFIX = 'Status';
  const GNU_CASH_ACCOUNTS_TREE_DATA_KEY = 'gnuCashAccountsTreeData';
  const GNU_CASH_INSTRUMENT_INSURANCE_BALANCING_ACCOUNT_KEY = 'gnuCashInstrumentInsuranceBalancingAccount';
  const GNU_CASH_PARTICIPANT_RECEIVABLES_ACCOUNT_KEY = 'gnuCashParticipantReceivablesAccount';
  const HAVE_CLOUD_USER_BACKEND_CONFIG_KEY = 'haveCloudUserBackendConfig';
  const IS_ADMIN = 'isAdmin';
  const IS_SUB_ADMIN = 'isSubAdmin';
  const OFFICE_FONTS = 'officeFonts';
  const OFFICE_FONTS_FOLDER_CONFIG = FontService::OFFICE_FONTS_FOLDER_CONFIG;
  const ORCHESTRA_USER_GROUP_ADMINS_KEY = self::ORCHESTRA_USER_GROUP_KEY . 'Admins';
  const ORCHESTRA_USER_GROUP_KEY = 'orchestraUserGroup';
  const PERSONAL_APP_SETTINGS_LINK = 'personalAppSettingsLink';
  const PROBLEM_REPORT_EMAIL_RECIPIENT_KEY = 'problemReportEmailRecipient';
  const SETTINGS_PROPERTIES = 'settingsProperties';
  const SHARED_FOLDER_KEY = 'sharedFolder';
  const USER_AND_GROUP_BACKENDS = 'userAndGroupBackends';
  const USER_AND_GROUP_BACKEND_KEY = ConfigConstants::USER_AND_GROUP_BACKEND_KEY;
  const WIKI_NAME_SPACE_KEY = 'wikiNameSpace';
  const WIKI_VERSION = 'wikiVersion';

  const DELEGATABLE = 'delegatable';
  const ADMIN_ONLY = 'admin_only';
  const SETTINGS_PROPERTY_VALUES = [
    self::ORCHESTRA_USER_GROUP_KEY => self::ADMIN_ONLY,
    self::WIKI_NAME_SPACE_KEY => self::DELEGATABLE,
    self::HAVE_CLOUD_USER_BACKEND_CONFIG_KEY => self::ADMIN_ONLY,
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

    $configData = AdminInitialState::fromArray([
      self::AUTHORIZATION_GROUP_SUFFIXES_KEY => self::AUTHORIZATION_GROUP_SUFFIXES,
      self::CLOUD_USER_BACKEND => $cloudUserBackend,
      self::HAVE_CLOUD_USER_BACKEND_CONFIG_KEY => $haveCloudUserBackendConfig,
      self::IS_ADMIN => $isAdmin,
      self::IS_SUB_ADMIN => $isSubAdmin,
      self::OFFICE_FONTS => $this->fontService->scanFontsFolder(),
      self::PERSONAL_APP_SETTINGS_LINK => $personalAppSettingsLink,
      self::SHARED_FOLDER_KEY => $this->configService->getConfigValue(ConfigConstants::SHARED_FOLDER),
      self::USER_AND_GROUP_BACKENDS => $userAndGroupBackends,
      FontService::DEFAULT_OFFICE_FONT_CONFIG => $this->fontService->getDefaultFontName(),
      FontService::OFFICE_FONTS_FOLDER_CONFIG => $this->fontService->getFontsFolderName(),
    ]);

    $this->initialState->provideInitialState(self::INITIAL_STATE_SECTION, $configData);

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
