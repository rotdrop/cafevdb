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
use OutOfBoundsException;
use InvalidArgumentException;

use Psr\Log\LoggerInterface;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\ContentSecurityPolicy;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\IAppContainer;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IURLGenerator;
use OC\AppFramework\Utility\QueryNotFoundException;

use OCA\CAFEVDB\Common\Util;
use OCA\CAFEVDB\Constants;
use OCA\CAFEVDB\Exceptions;
use OCA\CAFEVDB\PageRenderer;
use OCA\CAFEVDB\PageRenderer\IPageRenderer;
use OCA\CAFEVDB\PageRenderer\Registration as RendererRegistration;
use OCA\CAFEVDB\PageRenderer\Util\Navigation as PageNavigation;
use OCA\CAFEVDB\Service\AssetService;
use OCA\CAFEVDB\Service\AuthorizationService;
use OCA\CAFEVDB\Service\ConfigCheckService;
use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Service\EncryptionService;
use OCA\CAFEVDB\Service\HistoryService;
use OCA\CAFEVDB\Service\MigrationsService;
use OCA\CAFEVDB\Service\OrganizationalRolesService;
use OCA\CAFEVDB\Service\ToolTipsService;
use OCA\CAFEVDB\Listener\BeforeMessageLoggedEventListener;

/** Main UI entry point providing the front pages. */
class PageController extends Controller
{
  use \OCA\CAFEVDB\Toolkit\Traits\ResponseTrait;
  use \OCA\CAFEVDB\Traits\ConfigTrait;

  const RENDER_AS_PARTS = 'parts'; // silly name

  const DEFAULT_TEMPLATE = PageRenderer\Projects::TEMPLATE;

  public const HISTORY_ACTION_REPLACE = 'replace';
  public const HISTORY_ACTION_PUSH = 'push';

  /** @var array
   *
   * Result of ConfigCheckService.
   */
  private $configCheck;

  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct(
    ?string $appName,
    IRequest $request,
    protected IAppContainer $appContainer,
    private AssetService $assetService,
    protected ConfigService $configService,
    protected HistoryService $historyService,
    private OrganizationalRolesService $organizationalRolesService,
    private AuthorizationService $authorizationService,
    protected ToolTipsService $toolTipsService,
    private PageNavigation $pageNavigation,
    private ConfigCheckService $configCheckService,
    private IURLGenerator $urlGenerator,
    protected LoggerInterface $logger,
  ) {
    parent::__construct($appName, $request);

    $this->l = $this->l10n();

    // See if we are configured
    $this->configCheck = $this->configCheckService->configured();
  }
  // phpcs:enable

  /**
   * Load a page and remembers the request parameters in the history.
   *
   * @param string $renderAs
   *
   * @return Http\Response
   *
   * @NoAdminRequired
   * @AllowIFrameSelf
   */
  public function remember(string $renderAs):Http\Response
  {
    $this->historyService->save($this->request->getParams());
    return $this->loader(
      $this->request->getParam('renderAs') ?? self::RENDER_AS_USER,
      $this->request['template'],
      $this->request['projectName'],
      $this->request['projectId'],
      $this->request['musicianId'],
      historyAction: self::HISTORY_ACTION_PUSH,
    );
  }

  /**
   * Load a specific page, also used to dynamically replace html content.
   *
   * @param string $renderAs
   *
   * @param null|string $template
   *
   * @param null|string $projectName
   *
   * @param mixed $projectId
   *
   * @param mixed $musicianId
   *
   * @param string $historyAction
   *
   * @return Http\Response
   *
   * @NoAdminRequired
   * @AllowIFrameSelf
   */
  public function loader(
    string $renderAs,
    ?string $template,
    ?string $projectName = '',
    mixed $projectId = null,
    mixed $musicianId = null,
    string $historyAction = self::HISTORY_ACTION_PUSH,
  ) {
    if ($renderAs != self::RENDER_AS_PARTS) {
      throw new InvalidArgumentException($this->l->t('This controller can no longer serve front-page requests, in may only be accessed by the Vue frontend.'));
    }

    if ($historyAction != self::HISTORY_ACTION_PUSH) {
      $this->logInfo('HISTORY ACTION ' . $historyAction);
    }

    $template = $this->getTemplate($template, $renderAs);
    $this->logDebug("Try load template ".$template);
    /** @var IPageRenderer $renderer */
    $renderer = $this->appContainer->get(RendererRegistration::TEMPLATE_PREFIX . $template);
    if (empty($renderer)) {
      // in principle this cannot happen has the DI container should already
      // have issued a QueryNotFoundException.
      throw new Exceptions\Exception(
        $this->l->t('Template-renderer for template "%s" is empty.', [$template]),
      );
    }

    $requiredPermissions = AuthorizationService::PERMISSION_FRONTEND|$renderer->requiredPermissions();

    if (!$this->authorizationService->authorized(null, $requiredPermissions)) {
      throw new Exceptions\NotAuthorizedException(
        $this->userId(),
        $this->authorizationService->getUserPermissions(),
        $requiredPermissions,
        $this->l->t('Access to the web frontend was denied for user "%s".', $this->userId()),
      );
    }

    // The most important ...
    $encrkey = $this->getAppEncryptionKey();

    $showToolTips = $this->getUserValue('tooltips', 'on');
    $usrFiltVis   = $this->getUserValue('filtervisibility', 'off');
    $restoreHist  = $this->getUserValue('restorehistory', 'off');
    $directChg    = $this->getUserValue('directchange', 'off');
    $deselectInvisible = $this->getUserValue('deselectInvisibleMiscRecs', 'off');
    $showDisabled = $this->getUserValue('showdisabled', 'off');
    $expertMode   = $this->getUserValue('expertMode', false);
    $financeMode   = $this->getUserValue('financeMode', false);
    $pageRows     = $this->getUserValue('pagerows', 20);

    $debugMode    = (int)$this->getConfigValue('debugmode', 0);

    $this->toolTipsService->debug(!!($debugMode & ConfigService::DEBUG_TOOLTIPS));

    $templateParameters = [
      'template' => $template,
      'renderer' => $renderer,
      'assets' => [
        AssetService::JS => $this->assetService->getJSAsset('app'),
        AssetService::CSS => $this->assetService->getCSSAsset('app'),
      ],
      'appConfig' => $this->configService,
      'pageNavigation' => $this->pageNavigation,
      'roles' => $this->organizationalRolesService,

      //'l' => $this->l,
      'appName' => $this->appName,
      'appNameTag' => 'app-' . $this->appName,

      'configcheck' => $this->configCheck,
      ConfigService::ORCHESTRA_NAME_KEY => $this->getConfigValue(ConfigService::ORCHESTRA_NAME_KEY),
      ConfigService::WIKI_NAME_SPACE_KEY => $this->getAppValue(ConfigService::WIKI_NAME_SPACE_KEY),
      ConfigService::USER_GROUP_KEY => $this->groupId(),
      ConfigService::SHAREOWNER_KEY => $this->getConfigValue(ConfigService::SHAREOWNER_KEY),
      'sharedfolder' => $this->getConfigValue('sharedfolder'),
      'database' => $this->getConfigValue('database'),
      'groupadmin' => $this->isSubAdminOfGroup(),
      self::RENDER_AS_USER => $this->userId(),
      'expertMode' => $expertMode,
      'financeMode' => $financeMode,
      'showToolTips' => $showToolTips,
      'toolTips' => $this->toolTipsService,
      'urlGenerator' => $this->urlGenerator,
      'debugMode' => $debugMode,
      'encryptionkey' => $encrkey,
      'encryptionkeyhash' => $this->getConfigValue(EncryptionService::APP_ENCRYPTION_KEY_HASH_KEY),
      'uploadMaxFilesize' => Util::maxUploadSize(),
      'uploadMaxHumanFilesize' => \OCP\Util::humanFileSize(Util::maxUploadSize()),
      'projectName' => $projectName,
      'projectId' => $projectId,
      'musicianId' => $musicianId,
      'localeSymbol' => $this->getLocale(), // locale itself should already have been provided by NC core
      'timezone' => $this->getTimezone(),
      'requesttoken' => \OCP\Util::callRegister(),
      'restorehistory' => $restoreHist,
      'filtervisibility' => $usrFiltVis,
      'directchange' => $directChg,
      'deselectInvisibleMiscRecs' => $deselectInvisible,
      'showdisabled' => $showDisabled,
      'pagerows' => $pageRows,
    ];

    $templateParameters['omitEnvelope'] = true;
    $pageHtml = $this->templateResponse(
      $template,
      $templateParameters,
      self::RENDER_AS_BLANK,
    )->render();

    return self::dataResponse([
      'template' => $template,
      'defaultTemplateParameters' => $renderer->navigationItem($projectId, $projectName)['templateParameters'],
      'headerHtml' => $renderer->headerText(), // actually html
      'bodyHtml' => $pageHtml,
      'cssPrefix' => $renderer->cssPrefix(),
      'cssClass' => $renderer->cssClass(),
      'historyAction' => $historyAction,
    ]);
  }

  /**
   * @param null|string $template
   *
   * @param string $renderAs
   *
   * @return string
   */
  private function getTemplate(?string $template, string $renderAs):string
  {
    // Replace colons back to path separators. All our template parameters are
    // alpha-numeric with the exception that they man contain path separators
    // (i.e. '/') which optionally are replaced by colons (i.e. ':') in order
    // to avoid url en-/decoding. Here we need to convert back to path
    // separators.
    if (!$this->configCheck['summary']) {
      return PageRenderer\ConfigCheck::TEMPLATE;
    }
    if (empty($template)) {
      $template = self::DEFAULT_TEMPLATE;
    }

    /** @var BlogRenderer $blogRenderer */
    $blogRenderer = $this->appContainer->get(PageRenderer\Blog::class);
    if ($blogRenderer->notificationsPending()) {
      $template = PageRenderer\BLOG::TEMPLATE;
    }
    $template = str_replace(':', Constants::PATH_SEP, $template);

    return $template;
  }
}
