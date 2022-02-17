<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016, 2020, 2021, 2022 Claus-Justus Heine <himself@claus-justus-heine.de>
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

namespace OCA\CAFEVDB\Settings;

use OCP\AppFramework\Http\TemplateResponse;
use OCP\Settings\ISettings;
use OCP\App\IAppManager;
use OCP\IInitialStateService;
use OCP\IGroup;

use OCA\DokuWikiEmbedded\Service\AuthDokuWiki as WikiRPC;
use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Service\AssetService;
use OCA\CAFEVDB\Service\CloudUserConnectorService;

class Admin implements ISettings {
  use \OCA\CAFEVDB\Traits\ConfigTrait;

  const TEMPLATE = "admin-settings";

  /** @var AssetService */
  private $assetService;

  /** @var OCA\DokuWikiEmedded\Service\AuthDokuWiki */
  private $wikiRPC;

  /** @var IAppManager */
  private $appManager;

  /** @var IInitialStateService */
  private $initialStateService;

  public function __construct(
    ConfigService $configService
    , AssetService $assetService
    , WikiRPC $wikiRPC
    , IAppManager $appManager
    , IInitialStateService $initialStateService
  ) {
    $this->configService = $configService;
    $this->assetService = $assetService;
    $this->wikiRPC = $wikiRPC;
    $this->appManager = $appManager;
    $this->initialStateService = $initialStateService;
  }

  public function getForm()
  {
    $cloudUserBackend = CloudUserConnectorService::CLOUD_USER_BACKEND;
    $cloudUserBackendEnabled = $this->appManager->isInstalled($cloudUserBackend);
    $cloudUserBackendRestrictions = $this->appManager->getAppRestriction($cloudUserBackend);
    $haveCloudUserBackendConfig = !empty(array_filter(
      $this->cloudConfig()->getAppKeys($this->appName()),
      function($value) use ($cloudUserBackend) {
        return str_starts_with($value, $cloudUserBackend . ':');
      }));
    $personalAppSettingsLink = $this->urlGenerator()->getBaseUrl() . '/index.php/settings/user/' . $this->appName();

    $groupList = $this->groupManager()->search('');
    $groups = [];
    /** @var IGroup $group */
    foreach ($groupList as $group) {
      $groups[$group->getGID()] = $group->getDisplayName();
    }

    // Initial state injecton for JS
    $this->initialStateService->provideInitialState(
      $this->appName(),
      'CAFEVDB',
      [
        'appName' => $this->appName(),
        'toolTipsEnabled' => $this->getUserValue('tooltips', ''),
        'language' => $this->getUserValue('lang', 'en'),
        'wysiwygEditor' =>$this->getUserValue('wysiwygEditor', 'tinymce'),
        'expertMode' => $this->getUserValue('expertmode'),
      ]);
    $this->initialStateService->provideInitialState($this->appName(), 'PHPMyEdit', []);
    $this->initialStateService->provideInitialState($this->appName(), 'Calendar', []);

    return new TemplateResponse(
      $this->appName(),
      self::TEMPLATE,
      [
        'assets' => [
          AssetService::JS => $this->assetService->getJSAsset(self::TEMPLATE),
          AssetService::CSS => $this->assetService->getCSSAsset(self::TEMPLATE),
        ],
        'appName' => $this->appName(),
        'userGroup' => $this->getAppValue('usergroup'),
        'cloudGroups' => $groups,
        'personalAppSettingsLink' => $personalAppSettingsLink,
        'wikiNameSpace' => $this->getAppValue('wikinamespace'),
        'wikiVersion' => $this->wikiRPC->version(),
        'cloudUserBackend' => $cloudUserBackend,
        'cloudUserBackendEnabled' => $cloudUserBackendEnabled,
        'cloudUserBackendRestrictions' => $cloudUserBackendRestrictions,
        'haveCloudUserBackendConfig' => $haveCloudUserBackendConfig,
        'toolTips' => $this->toolTipsService(),
        'requesttoken' => \OCP\Util::callRegister(),
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
}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
