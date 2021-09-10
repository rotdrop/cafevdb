<?php
/**
 * Nextcloud - cafevdb
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright Claus-Justus Heine 2014-2021
 */

namespace OCA\CAFEVDB\Controller;

use OCP\IRequest;
use OCP\ISession;
use OCP\AppFramework\Http\ContentSecurityPolicy;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http;
use OCP\AppFramework\Controller;
use OCP\IL10N;
use OCP\IInitialStateService;
use OCP\AppFramework\IAppContainer;

use OCA\CAFEVDB\Common\Util;

use OCA\CAFEVDB\Service\HistoryService;
use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Service\RequestParameterService;
use OCA\CAFEVDB\Service\ConfigCheckService;
use OCA\CAFEVDB\Service\ToolTipsService;
use OCA\CAFEVDB\Service\OrganizationalRolesService;
use OCA\CAFEVDB\Service\MigrationsService;
use OCA\CAFEVDB\Database\Cloud\Mapper\BlogMapper;
use OCA\CAFEVDB\PageRenderer\Util\Navigation as PageNavigation;
use OCA\CAFEVDB\PageRenderer\IPageRenderer;
use OCA\CAFEVDB\Response\PreRenderedTemplateResponse;

class PageController extends Controller {
  use \OCA\CAFEVDB\Traits\InitialStateTrait;
  use \OCA\CAFEVDB\Traits\ResponseTrait;

  /** @var ISession */
  private $session;

  /** @var IL10N */
  protected $l;

  /** @var HistoryService */
  private $historyService;

  /** @var RequestParameterService */
  private $parameterService;

  /** @var ToolTipsService */
  protected $toolTipsService;

  /** @var ConfigCheckService */
  private $configCheckService;

  /** @var \OCA\CAFEVDB\Service\OrganizationalRolesService */
  private $organizationalRolesService;

  /** @var \OCP\IURLGenerator */
  private $urlGenerator;

  /** @var IAppContainer */
  private $appContainer;

  /** @var OCA\CAFEVDB\PageRenderer\Util\Navigation */
  private $pageNavigation;

  /** @var array
   *
   * Result of ConfigCheckService.
   */
  private $configCheck;

  public function __construct(
    $appName
    , IRequest $request
    , ISession $session
    , IAppContainer $appContainer
    , ConfigService $configService
    , HistoryService $historyService
    , OrganizationalRolesService $organizationalRolesService
    , RequestParameterService $parameterService
    , ToolTipsService $toolTipsService
    , PageNavigation $pageNavigation
    , IInitialStateService $initialStateService
    , ConfigCheckService $configCheckService
    , \OCP\IURLGenerator $urlGenerator
  ) {

    parent::__construct($appName, $request);

    $this->session = $session;
    $this->appContainer = $appContainer;
    $this->configService = $configService;
    $this->historyService = $historyService;
    $this->parameterService = $parameterService;
    $this->toolTipsService = $toolTipsService;
    $this->pageNavigation = $pageNavigation;
    $this->initialStateService = $initialStateService;
    $this->organizationalRolesService = $organizationalRolesService;
    $this->configCheckService = $configCheckService;
    $this->urlGenerator = $urlGenerator;
    $this->l = $this->l10N();

    // See if we are configured
    $this->configCheck = $this->configCheckService->configured();
  }

  /**
   * Load the main page of the App.
   *
   * @NoAdminRequired
   * @NoGroupMemberRequired
   * @NoCSRFRequired
   * @UseSession
   */
  public function index()
  {
    if ($this->getUserValue('restorehistory') === 'on'
        && empty($this->parameterService->getParam('template'))) {
      return $this->history(0, 'user');
    } else {
      return $this->remember('user');
    }
  }

  /**
   * @NoAdminRequired
   * @NoGroupMemberRequired
   * @NoCSRFRequired
   */
  public function post($a, $b, $c, $d, $e, $f, $g)
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
   * @NoAdminRequired
   * @UseSession
   */
  public function history($level = 0, $renderAs = 'blank')
  {
    try {
      $originalParams = $this->parameterService->getParams();
      $this->parameterService->setParams($this->historyService->fetch($level));
      $this->parameterService['originalRequestParameters'] = $originalParams;
      $this->parameterService->setParam('renderAs', $renderAs);
    } catch(\OutOfBoundsException $e) {
      return new DataResponse(['message' => $e->getMessage(),
                               'history' => ['size' => $this->historyService->size(),
                                             'position' => $this->historyService->position()] ],
                              Http::STATUS_NOT_FOUND);
    }
    return $this->loader(
      $renderAs,
      $this->parameterService['template'],
      $this->parameterService['projectName'],
      $this->parameterService['projectId'],
      $this->parameterService['musicianId']
    );
  }

  /**
   * Load a page and remembers the request parameters in the history.
   *
   * @NoAdminRequired
   * @UseSession
   */
  public function remember($renderAs = 'user')
  {
    $this->historyService->push($this->parameterService->getParams());
    return $this->loader(
      $this->parameterService->getParam('renderAs', 'user'),
      $this->parameterService['template'],
      $this->parameterService['projectName'],
      $this->parameterService['projectId'],
      $this->parameterService['musicianId']
    );
  }

  /**
   * @NoAdminRequired
   * @NoCSRFRequired
   * @UseSession
   */
  public function debug() {
    return $this->loader(
      'user',
      'maintenance/debug', // template
      $this->parameterService['projectName'],
      $this->parameterService['projectId'],
      $this->parameterService['musicianId']
    );
  }

  /**
   * Load a specific page, also used to dynamically replace html content.
   *
   * @NoAdminRequired
   * @UseSession
   */
  public function loader(
    $renderAs,
    $template,
    $projectName = '',
    $projectId = null,
    $musicianId = null) {

    // Initial state injecton for JS
    $this->publishInitialStateForUser($this->userId());

    // The most important ...
    $encrkey = $this->getAppEncryptionKey();

    // Are we a group-admin?
    $isGroupAdmin = $this->isSubAdminOfGroup();

    $showToolTips = $this->getUserValue('tooltips', 'on');
    $usrFiltVis   = $this->getUserValue('filtervisibility', 'off');
    $restoreHist  = $this->getUserValue('restorehistory', 'off');
    $directChg    = $this->getUserValue('directchange', 'off');
    $showDisabled = $this->getUserValue('showdisabled', 'off');
    $expertMode   = $this->getUserValue('expertmode', false);
    $pageRows     = $this->getUserValue('pagerows', 20);

    $debugMode    = $this->getConfigValue('debugmode', 0);

    $this->toolTipsService->debug(!!($debugMode & ConfigService::DEBUG_TOOLTIPS));

    if (!$this->inGroup()) {
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

    $historySize = $this->historyService->size();
    $historyPosition = $this->historyService->position();

    $templateParameters = [
      'template' => $template,
      'renderer' => $renderer,
      'appConfig' => $this->configService,
      'pageNavigation' => $this->pageNavigation,
      'roles' => $this->organizationalRolesService,

      //'l' => $this->l,
      'appName' => $this->appName,

      'configcheck' => $this->configCheck,
      'orchestra' => $this->getConfigValue('orchestra'),
      'usergroup' => $this->groupId(),
      'shareowner' => $this->getConfigValue('shareowner'),
      'sharedfolder' => $this->getConfigValue('sharedfolder'),
      'database' => $this->getConfigValue('database'),
      'groupadmin' => $this->isSubAdminOfGroup(),
      'user' => $this->userId(),
      'expertMode' => $expertMode,
      'showToolTips' => $showToolTips,
      'toolTips' => $this->toolTipsService,
      'urlGenerator' => $this->urlGenerator,
      'debugMode' => $debugMode,
      'encryptionkey' => $encrkey,
      'configkey' => ($this->getConfigValue('encryptionkey')?: $this->getAppValue('encryptionkey')),
      'uploadMaxFilesize' => Util::maxUploadSize(),
      'uploadMaxHumanFilesize' => \OCP\Util::humanFileSize(Util::maxUploadSize()),
      'projectName' => $projectName,
      'projectId' => $projectId,
      'musicianId' => $musicianId,
      'locale' => $this->getLocale(),
      'timezone' => $this->getTimezone(),
      'historySize' => $historySize,
      'historyPosition' => $historyPosition,
      'requesttoken' => \OCP\Util::callRegister(), // @todo: check
      'csrfToken' => \OCP\Util::callRegister(), // @todo: check
      'restorehistory' => $restoreHist,
      'filtervisibility' => $usrFiltVis,
      'directchange' => $directChg,
      'showdisabled' => $showDisabled,
      'pagerows' => $pageRows,
    ];

    // renderAs = admin, user, blank
    // $renderAs = 'user';
    $response = new PreRenderedTemplateResponse($this->appName, $template, $templateParameters, $renderAs);
    $response->addHeader('X-'.$this->appName.'-history-size', $historySize);
    $response->addHeader('X-'.$this->appName.'-history-position', $historyPosition);

    // @todo: we need this only for some site like DokuWiki and CMS
    $policy = new ContentSecurityPolicy();
    $policy->addAllowedChildSrcDomain('*');
    $policy->addAllowedFrameDomain('*');
    $response->setContentSecurityPolicy($policy);

    if ($renderer->needPhpSession() && $renderAs !== 'user') {
      $response->preRender();
    }

    // ok no exception, so flush the history to the session, when we
    // got so far.
    try {
      $this->logInfo('Closing session');
      $this->historyService->store();
      if (!$renderer->needPhpSession() || $renderAs !== 'user') {
        $this->session->close();
      }
    } catch (\Throwable $t) {
      // log, but ignore otherwise
      $this->logException($t);
    }

    return $response;
  }

  private function getTemplate(?string $template, string $renderAs)
  {
    if ($template != 'maintenance/debug' && !$this->configCheck['summary']) {
      return 'maintenance/configcheck';
    }
    $template = $template ?: 'all-musicians';
    if ($renderAs === 'user') {
      $blogMapper = \OC::$server->query(BlogMapper::class);
      if ($blogMapper->notificationPending($this->userId())) {
        return 'blog/blog';
      }
    }
    return $template;
  }

  /**
   * @NoAdminRequired
   */
  public function notFound($a, $b, $c, $d, $e)
  {
    $parts = [ $a, $b, $c, $d, $e ];
    $route = '/ajax';
    foreach($parts as $part) {
      if (empty($part)) {
        break;
      }
      $route .= '/'.$part;
    }
    return self::response($this->l->t("Page `%s\' not found.", [$route]), Http::STATUS_NOT_FOUND);
  }

}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
