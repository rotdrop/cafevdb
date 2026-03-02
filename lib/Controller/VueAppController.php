<?php
/**
 * Nextcloud - cafevdb
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright Claus-Justus Heine 2025, 2026
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

use Spatie\TypeScriptTransformer\Attributes as TSAttributes;

use Throwable;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute as CoreAttributes;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Services\IInitialState;
use Psr\Container\ContainerInterface;
use OCP\IInitialStateService;
use OCP\IRequest;
use OCP\Util;

use OCA\CAFEVDB\Attributes;
use OCA\CAFEVDB\Common\Util as CommonUtil;
use OCA\CAFEVDB\Controller\DTO;
use OCA\CAFEVDB\Exceptions;
use OCA\CAFEVDB\PageRenderer;
use OCA\CAFEVDB\PageRenderer\DTO\SidebarNavigationItem as RendererNavigationItem;
use OCA\CAFEVDB\Service\AssetService;
use OCA\CAFEVDB\Service\AuthorizationService;
use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Service\HistoryService;
use OCA\CAFEVDB\Service\ToolTipsService;

/** AJAX endpoint for generating the main page of the app. */
#[TSAttributes\TypeScript]
class VueAppController extends Controller
{
  use \OCA\CAFEVDB\Traits\InitialStateTrait;

  public const END_POINT_PAGE = 'p';
  public const END_POINT_NAVIGATION = 'n';

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
    protected ContainerInterface $appContainer,
  ) {
    parent::__construct($appName, $request);

    $this->l = $this->l10n();
  }
  // phpcs:enable

  /**
   * Render default template
   *
   * @return TemplateResponse
   */
  #[CoreAttributes\NoAdminRequired]
  #[CoreAttributes\NoCSRFRequired]
  #[CoreAttributes\FrontpageRoute(verb: 'GET', url: '/')]
  #[CoreAttributes\FrontpageRoute(
    verb: 'GET',
    url: '/' . self::END_POINT_PAGE . '/{template}/{projectName}',
    requirements: [ 'template' => '.+' ],
    defaults: [ 'projectName' => null ],
    postfix: 'front',
  )]
  #[Attributes\AllowIFrameSelf]
  public function index(): TemplateResponse
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

    $historyPostData = [];

    $queryHash = $this->request->getParam('hash');
    if ($queryHash) {
      $initialPostData = $this->historyService->get($queryHash);
      $this->logDebug('HASH VALUE ' . $queryHash . ' DATA ' . print_r($initialPostData ?? [], true));
      if ($initialPostData !== null) {
        $historyPostData = CommonUtil::arrayMergeRecursive(
          $historyPostData, [
            'post' => [
              $queryHash => $initialPostData,
            ],
            'queryHash' => $queryHash,
          ],
        );
      }
    }

    $lastUrlPath = $this->historyService->getLastUrlPath();
    if (!empty($lastUrlPath)) {
      $queryData = [];
      parse_str(parse_url($lastUrlPath, PHP_URL_QUERY), $queryData);
      $hash = $queryData['hash'] ?? null;
      if ($hash) {
        $postData = $this->historyService->get($hash);
        if ($postData !== null) {
          $historyPostData = CommonUtil::arrayMergeRecursive(
            $historyPostData, [
              'post' => [
                $hash => $postData,
              ],
              'lastUrlPath' => $lastUrlPath,
              'lastUrlHash' => $hash,
            ]
          );
        }
      }
    }

    if (!empty($historyPostData)) {
      $this->initialStateService->provideInitialState($this->appName, 'historyPostData', $historyPostData);
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
   */
  #[CoreAttributes\NoAdminRequired]
  #[CoreAttributes\FrontpageRoute(
    verb: 'POST',
    url: '/' . self::END_POINT_NAVIGATION . '/{template}',
    requirements: [ 'template' => '.+' ],
  )]
  public function navigation(
    string $template,
    ?int $projectId = null,
    ?string $projectName = null,
  ): DataResponse|JSONResponse {
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
        $renderer = $this->appContainer->get(PageRenderer\DataConstants::RENDERER_PREFIX_TAG . $template);
      } catch (Throwable $t) {
        throw new Exceptions\EnduserNotificationException(
          $this->l->t('Unable to generate the navigation menu for page "%s".', $template),
          previous: $t,
        );
      }
      $navigationItems = $renderer->navigationItems();
    }
    $userPermissions = $this->authorizationService->getUserPermissions();
    $navigationItems = array_filter(
      $navigationItems,
      fn(RendererNavigationItem $item) => ($item->permissions === ($item->permissions & $userPermissions)),
    );
    return new DTO\NavigationItemsResponse(
      navigation: array_map(
        function(RendererNavigationItem $item): DTO\SidebarNavigationItem {
          return new DTO\SidebarNavigationItem(
            template: $item->template,
            name: $this->toolTipsService[$item->name] ?: $item->name,
            nameKey: $item->name,
            tooltip: $this->toolTipsService[$item->tooltip] ?: $item->tooltip,
            tooltipKey: $item->tooltip,
            templateParameters: $item->templateParameters,
            permissions: $item->permissions,
          );
        },
        $navigationItems,
      ),
    )->response();
  }
}
