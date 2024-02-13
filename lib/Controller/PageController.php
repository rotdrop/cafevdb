<?php
/**
 * Nextcloud - cafevdb
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright Claus-Justus Heine 2014-2024
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

use OCP\IRequest;
use OCP\AppFramework\Http\ContentSecurityPolicy;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http;
use OCP\AppFramework\Controller;
use OCP\IL10N;
use OCP\IInitialStateService;
use OCP\AppFramework\IAppContainer;

use OCA\CAFEVDB\Common\Util;

use OCA\CAFEVDB\Http\TemplateResponse;
use OCA\CAFEVDB\Service\HistoryService;
use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Service\RequestParameterService;
use OCA\CAFEVDB\Service\ConfigCheckService;
use OCA\CAFEVDB\Service\ToolTipsService;
use OCA\CAFEVDB\Service\OrganizationalRolesService;
use OCA\CAFEVDB\Service\AuthorizationService;
use OCA\CAFEVDB\Service\MigrationsService;
use OCA\CAFEVDB\Service\AssetService;
use OCA\CAFEVDB\Service\EncryptionService;
use OCA\CAFEVDB\Database\Cloud\Mapper\BlogMapper;
use OCA\CAFEVDB\PageRenderer\Util\Navigation as PageNavigation;
use OCA\CAFEVDB\PageRenderer\IPageRenderer;

/** Main UI entry point providing the front pages. */
class PageController extends Controller
{
  use \OCA\CAFEVDB\Traits\InitialStateTrait;
  use \OCA\CAFEVDB\Toolkit\Traits\ResponseTrait;

  const DEFAULT_TEMPLATE = 'projects';
  const HOME_TEMPLATE = 'home';

  public const HISTORY_ACTION_LOAD = 'load';
  public const HISTORY_ACTION_PUSH = 'push';

  /** @var IL10N */
  protected IL10N $l;

  /** @var HistoryService */
  private $historyService;

  /** @var RequestParameterService */
  private $parameterService;

  /** @var ToolTipsService */
  protected $toolTipsService;

  /** @var ConfigCheckService */
  private $configCheckService;

  /** @var OrganizationalRolesService */
  private $organizationalRolesService;

  /** @var AuthorizationService */
  private $authorizationService;

  /** @var \OCP\IURLGenerator */
  private $urlGenerator;

  /** @var IAppContainer */
  private $appContainer;

  /** @var PageNavigation */
  private $pageNavigation;

  /** @var AssetService */
  private $assetService;

  /** @var array
   *
   * Result of ConfigCheckService.
   */
  private $configCheck;

  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct(
    ?string $appName,
    IRequest $request,
    IAppContainer $appContainer,
    AssetService $assetService,
    ConfigService $configService,
    HistoryService $historyService,
    OrganizationalRolesService $organizationalRolesService,
    AuthorizationService $authorizationService,
    RequestParameterService $parameterService,
    ToolTipsService $toolTipsService,
    PageNavigation $pageNavigation,
    IInitialStateService $initialStateService,
    ConfigCheckService $configCheckService,
    \OCP\IURLGenerator $urlGenerator,
  ) {

    parent::__construct($appName, $request);

    $this->appContainer = $appContainer;
    $this->assetService = $assetService;
    $this->configService = $configService;
    $this->historyService = $historyService;
    $this->parameterService = $parameterService;
    $this->toolTipsService = $toolTipsService;
    $this->pageNavigation = $pageNavigation;
    $this->initialStateService = $initialStateService;
    $this->organizationalRolesService = $organizationalRolesService;
    $this->authorizationService = $authorizationService;
    $this->configCheckService = $configCheckService;
    $this->urlGenerator = $urlGenerator;
    $this->l = $this->l10N();

    // See if we are configured
    $this->configCheck = $this->configCheckService->configured();
  }
  // phpcs:enable

  /**
   * Load the main page of the App.
   *
   * @return Http\Response
   *
   * @NoAdminRequired
   * @NoGroupMemberRequired
   * @NoCSRFRequired
   */
  public function index():Http\Response
  {
    if ($this->parameterService->getParam('history', '') == 'discard') {
      $this->historyService->push([]);
      $this->historyService->store();
      return new Http\RedirectResponse($this->urlGenerator->linkTo($this->appName, ''));
    }
    if ($this->shouldLoadHistory()) {
      return $this->history(0, 'user');
    } else {
      return $this->remember('user');
    }
  }

  /**
   * @param int $level
   *
   * @return bool
   */
  private function shouldLoadHistory(int $level = 0):bool
  {
    if ($this->getUserValue('restorehistory') !== 'on') {
      return false;
    }
    if ($this->request->getMethod() !== 'GET') {
      return false;
    }
    $template = $this->parameterService->getParam('template');
    if (empty($template)) {
      return true;
    }
    $get = $this->request->get;
    $historyData = $this->historyService->fetch($level);
    foreach ($get as $key => $value) {
      if ($key == '_route') {
        continue;
      }
      if (($historyData[$key] ?? null) !== $value) {
        return false;
      }
    }
    return true;
  }

  /**
   * @param mixed $a
   *
   * @param mixed $b
   *
   * @param mixed $c
   *
   * @param mixed $d
   *
   * @param mixed $e
   *
   * @param mixed $f
   *
   * @param mixed $g
   *
   * @return Http\Response
   *
   * @NoAdminRequired
   * @NoGroupMemberRequired
   * @NoCSRFRequired
   */
  public function post(mixed $a, mixed $b, mixed $c, mixed $d, mixed $e, mixed $f, mixed $g):Http\Response
  {
    $parts = [ $a, $b, $c, $d, $e, $f, $g ];
    $request = implode('/', array_filter($parts));
    if (!empty($request)) {
      return self::grumble(
        $this->l->t('Post to end-point "%s" not implemented.', $request));
    } else {
      return self::grumble(
        $this->l->t('Post to base-url of app "%s" not allowed.', $this->appName()));
    }
  }

  /**
   * Load a page at the specified offset from the history. Returns an
   * error if the entry cannot be found in the history.
   *
   * @param int $level
   *
   * @param string $renderAs
   *
   * @return Http\Response
   *
   * @NoAdminRequired
   */
  public function history(int $level = 0, string $renderAs = 'blank'):Http\Response
  {
    try {
      $originalParams = $this->parameterService->getParams();
      $this->parameterService->setParams($this->historyService->fetch($level));
      $this->parameterService['originalRequestParameters'] = $originalParams;
      $this->parameterService->setParam('renderAs', $renderAs);
    } catch (OutOfBoundsException $e) {
      return new DataResponse(
        ['message' => $e->getMessage(),
         'history' => ['size' => $this->historyService->size(),
                       'position' => $this->historyService->position()] ],
        Http::STATUS_NOT_FOUND);
    }
    return $this->loader(
      $renderAs,
      $this->parameterService['template'],
      $this->parameterService['projectName'],
      $this->parameterService['projectId'],
      $this->parameterService['musicianId'],
      historyAction: self::HISTORY_ACTION_LOAD,
    );
  }

  /**
   * Load a page and remembers the request parameters in the history.
   *
   * @param string $renderAs
   *
   * @return Http\Response
   *
   * @NoAdminRequired
   */
  public function remember(string $renderAs = 'user'):Http\Response
  {
    $this->historyService->push($this->parameterService->getParams());
    return $this->loader(
      $this->parameterService->getParam('renderAs', 'user'),
      $this->parameterService['template'],
      $this->parameterService['projectName'],
      $this->parameterService['projectId'],
      $this->parameterService['musicianId'],
      historyAction: self::HISTORY_ACTION_PUSH,
    );
  }

  /**
   * @return Http\Response
   *
   * @NoAdminRequired
   * @NoCSRFRequired
   */
  public function debug():Http\Response
  {
    return $this->loader(
      'user',
      'maintenance/debug', // template
      $this->parameterService['projectName'],
      $this->parameterService['projectId'],
      $this->parameterService['musicianId'],
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
   */
  public function loader(
    string $renderAs,
    ?string $template,
    ?string $projectName = '',
    mixed $projectId = null,
    mixed $musicianId = null,
    string $historyAction = self::HISTORY_ACTION_PUSH,
  ) {

    // Initial state injecton for JS
    $this->publishInitialStateForUser($this->userId());

    $this->initialStateService->provideInitialState(
      $this->appName,
      'iFrameContentScript',
      $this->assetService->getJSAsset('iframe-content-script'),
    );

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

    if (!$this->authorizationService->authorized(null, AuthorizationService::PERMISSION_FRONTEND)) {
      return new TemplateResponse(
        $this->appName(),
        'errorpage',
        [
          'error' => 'notamember',
          'userId' => $this->userId(),
        ],
        'user');
    };

    $template = $this->getTemplate($template, $renderAs);
    $this->logDebug("Try load template ".$template);
    try {
      /** @var IPageRenderer $renderer */
      $renderer = $this->appContainer->query('template:'.$template);
      if (empty($renderer)) {
        return self::response(
          $this->l->t("Template-renderer for template `%s' is empty.", [$template]),
          Http::INTERNAL_SERVER_ERROR);
      }
    } catch (\Throwable $t) {
      return $this->exceptionResponse($t, $renderAs, __METHOD__);
    }

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
      'orchestra' => $this->getConfigValue('orchestra'),
      'wikinamespace' => $this->getAppValue('wikinamespace'),
      ConfigService::USER_GROUP_KEY => $this->groupId(),
      ConfigService::SHAREOWNER_KEY => $this->getConfigValue(ConfigService::SHAREOWNER_KEY),
      'sharedfolder' => $this->getConfigValue('sharedfolder'),
      'database' => $this->getConfigValue('database'),
      'groupadmin' => $this->isSubAdminOfGroup(),
      'user' => $this->userId(),
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

    // renderAs = admin, user, blank
    // $renderAs = 'user';
    $response = new TemplateResponse($this->appName, $template, $templateParameters, $renderAs);

    // @todo: we need this only for some site like DokuWiki and CMS
    $policy = new ContentSecurityPolicy();
    $policy->addAllowedChildSrcDomain('*');
    $policy->addAllowedFrameDomain('*');
    $response->setContentSecurityPolicy($policy);

    $response->addHeader('X-'.$this->appName.'-history-action', $historyAction);

    try {
      $this->historyService->store();
    } catch (Throwable $t) {
      // log, but ignore otherwise
      $this->logException($t);
    }

    return $response;
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
    if ($template != 'maintenance/debug' && !$this->configCheck['summary']) {
      return 'maintenance/configcheck';
    }
    if (empty($template) || $template == self::HOME_TEMPLATE) {
      $template = self::DEFAULT_TEMPLATE;
    }
    if ($renderAs === 'user') {
      $blogMapper = \OC::$server->query(BlogMapper::class);
      if ($blogMapper->notificationPending($this->userId())) {
        $template = 'blog/blog';
      }
    }
    return $template;
  }

  /**
   * @param mixed $a
   *
   * @param mixed $b
   *
   * @param mixed $c
   *
   * @param mixed $d
   *
   * @param mixed $e
   *
   * @param mixed $f
   *
   * @param mixed $g
   *
   * @return Http\Response
   *
   * @NoAdminRequired
   */
  public function notFound(mixed $a, mixed $b, mixed $c, mixed $d, mixed $e, mixed $f, mixed $g):Http\Response
  {
    $parts = [ $a, $b, $c, $d, $e, $f, $g ];
    $route = '/ajax';
    foreach ($parts as $part) {
      if (empty($part)) {
        break;
      }
      $route .= '/'.$part;
    }
    return self::response($this->l->t("Page `%s\' not found.", [$route]), Http::STATUS_NOT_FOUND);
  }
}
