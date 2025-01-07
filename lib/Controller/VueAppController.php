<?php
/**
 * Nextcloud - cafevdb
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright Claus-Justus Heine 2014-2025
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

namespace OCA\CAFEVDB\Controller;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\IInitialStateService;
use OCP\IRequest;
use OCP\Util;

use OCA\CAFEVDB\Service\AssetService;
use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Service\HistoryService;

/** AJAX endpoint for generating the main page of the app. */
class VueAppController extends Controller
{
  use \OCA\CAFEVDB\Traits\InitialStateTrait;

  /** @var array
   *
   * Result of ConfigCheckService.
   */
  private $configCheck;

  // phpcs:ignore Squiz.Commenting.FunctionComment.Missing
  public function __construct(
    string $appName,
    IRequest $request,
    protected AssetService $assetService,
    protected ConfigService $configService,
    protected HistoryService $historyService,
    protected IInitialState $initialState,
    protected IInitialStateService $initialStateService,
  ) {
    parent::__construct($appName, $request);

    $this->l = $this->l10N();
  }
  // phpcs:enable

  /**
   * Render default template
   *
   * @return TemplateResponse
   *
   * @NoAdminRequired
   * @NoCSRFRequired
   */
  public function index():TemplateResponse
  {
    // add the vue assets
    Util::addScript($this->appName, $this->assetService->getJSAsset('vue-app')['asset']);
    Util::addStyle($this->appName, $this->assetService->getCSSAsset('vue-app')['asset']);

    // add the legacy assets
    Util::addScript($this->appName, $this->assetService->getJSAsset('app')['asset']);
    Util::addStyle($this->appName, $this->assetService->getCSSAsset('app')['asset']);

    // Initial state injecton for JS
    $this->publishInitialStateForUser($this->userId());

    $this->initialStateService->provideInitialState(
      $this->appName,
      'iFrameContentScript',
      $this->assetService->getJSAsset('iframe-content-script'),
    );


    return new TemplateResponse($this->appName, 'vue-app');
  }
}
