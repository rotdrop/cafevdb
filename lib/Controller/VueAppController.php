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

use Throwable;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\AppFramework\IAppContainer;
use OCP\IInitialStateService;
use OCP\IRequest;
use OCP\Util;

use OCA\CAFEVDB\PageRenderer;
use OCA\CAFEVDB\Service\AssetService;
use OCA\CAFEVDB\Service\AuthorizationService;
use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Service\HistoryService;
use OCA\CAFEVDB\Service\ToolTipsService;

/** AJAX endpoint for generating the main page of the app. */
class VueAppController extends Controller
{
  use \OCA\CAFEVDB\Traits\InitialStateTrait;
  use \OCA\CAFEVDB\Toolkit\Traits\ResponseTrait;

  // phpcs:ignore Squiz.Commenting.FunctionComment.Missing
  public function __construct(
    string $appName,
    IRequest $request,
    protected ?string $userId,
    protected AssetService $assetService,
    protected ConfigService $configService,
    protected HistoryService $historyService,
    protected AuthorizationService $authorizationService,
    protected ToolTipsService $toolTipsService,
    protected IInitialState $initialState,
    protected IInitialStateService $initialStateService,
    protected IAppContainer $appContainer,
  ) {
    parent::__construct($appName, $request);

    $this->l = $this->l10n();
  }
  // phpcs:enable

  /**
   * Render default template
   *
   * @return TemplateResponse
   *
   * @NoAdminRequired
   * @NoCSRFRequired
   * @AllowIFrameSelf
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

    $queryHash = $this->request->getParam('hash');
    if ($queryHash) {
      $initialPostData = $this->historyService->get($queryHash);
      $this->logInfo('HASH VALUE ' . $queryHash . ' DATA ' . print_r($initialPostData ?? [], true));
      if (!empty($initialPostData)) {
        $this->initialStateService->provideInitialState(
          $this->appName,
          'historyPostData',
          [
            'hash' => $queryHash,
            'post' => $initialPostData,
          ],
        );
      }
    }

    return new TemplateResponse($this->appName, 'vue-app');
  }

  /**
   * Fetch navigation Items for current page
   *
   * @param string $template
   *
   * @param null|int $projectId
   *
   * @param null|string $projectName
   *
   * @return DataResponse
   *
   * @NoAdminRequired
   * _AT_NoCSRFRequired
   */
  public function navigation(string $template, ?int $projectId = null, ?string $projectName = null)
  {
    $template = urldecode($template);
    if ($template == 'home') {
      $navigationItems = [
        PageRenderer\Projects::navigationItem(),
        PageRenderer\AllMusicians::navigationItem(),
        PageRenderer\Blog::navigationItem(),
      ];
    } else {
      try {
        /** @var $renderer PageRenderer\IPageRenderer */
        $renderer = $this->appContainer->query(PageRenderer\Registration::TEMPLATE_PREFIX . $template);
      } catch (Throwable $t) {
        return $this->exceptionResponse($t, self::RENDER_AS_BLANK);
      }
      $navigationItems = $renderer->navigationItems();
    }
    $userPermissions = $this->authorizationService->getUserPermissions();
    $navigationItems = array_filter($navigationItems, fn($item) => ($item['permissions'] === ($item['permissions'] & $userPermissions)));
    foreach ($navigationItems as &$item) {
      $item['nameKey'] = $item['name'];
      $item['name'] = $this->toolTipsService[$item['name']] ?: $item['name'];
      $item['tooltipKey'] = $item['tooltip'];
      $item['tooltip'] = $this->toolTipsService[$item['tooltip']] ?: $item['tooltip'];
    }
    return self::dataResponse([
      'navigation' => $navigationItems,
    ]);
  }
}
