<?php
/**
 * Nextcloud - cafevdb
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright Claus-Justus Heine 2014-2020
 */

namespace OCA\CAFEVDB\Controller;

use OCP\IRequest;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http;
use OCP\AppFramework\Controller;
use OCP\ISession;
use OCP\IL10N;
use OCP\IInitialStateService;
use OCP\AppFramework\IAppContainer;

use OCA\CAFEVDB\Common\Util;

use OCA\CAFEVDB\Service\HistoryService;
use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Service\RequestParameterService;
use OCA\CAFEVDB\Service\ConfigCheckService;
use OCA\CAFEVDB\Service\ToolTipsService;
use OCA\CAFEVDB\Legacy\PME\PHPMyEdit;

class PageController extends Controller {
  use \OCA\CAFEVDB\Traits\InitialStateTrait;
  use \OCA\CAFEVDB\Traits\ResponseTrait;

  /** @var IL10N */
  private $l;

  /** @var HistoryService */
  private $historyService;

  /** @var RequestParameterService */
  private $parameterService;

  /** @var ToolTipsService */
  private $toolTipsService;

  /** @var ConfigCheckService */
  private $configCheckService;

  /** @var \OCP\IURLGenerator */
  private $urlGenerator;

  /** @var PHPMyEdit */
  private $phpMyEdit;

  /** @var IAppContainer */
  private $appContainer;

  public function __construct(
    $appName
    , IRequest $request
    , IAppContainer $appContainer
    , ConfigService $configService
    , HistoryService $historyService
    , RequestParameterService $parameterService
    , ToolTipsService $toolTipsService
    , IInitialStateService $initialStateService
    , ConfigCheckService $configCheckService
    , \OCP\IURLGenerator $urlGenerator
    , PHPMyEdit $phpMyEdit
  ) {

    parent::__construct($appName, $request);

    $this->appContainer = $appContainer;
    $this->configService = $configService;
    $this->historyService = $historyService;
    $this->parameterService = $parameterService;
    $this->toolTipsService = $toolTipsService;
    $this->initialStateService = $initialStateService;
    $this->configCheckService = $configCheckService;
    $this->urlGenerator = $urlGenerator;
    $this->phpMyEdit = $phpMyEdit;
    $this->l = $this->l10N();
  }

  /**
   * Load the main page of the App.
   *
   * @NoAdminRequired
   * @NoGroupMemberRequired
   * @NoCSRFRequired
   */
  public function index() {
    if (empty($this->request->getParam('template')) && !$this->historyService->empty()) {
      return $this->history(1);
    } else {
      return $this->history(0);
    }
  }

  /**
   * Go back in the history.
   *
   * @NoAdminRequired
   */
  public function history($level = 0)
  {
    if ($level > 0) {
      try {
        $originalParams = $this->parameterService->getParams();
        $this->parameterService->setParams($this->historyService->fetch($level-1));
        $this->parameterService['originalRequestParameters'] = $originalParams;
        $_POST = $this->parameterService->getParams(); // oh oh
      } catch(\OutOfBoundsException $e) {
        return new DataResponse(['msg' => $e->getMessage()], Http::STATUS_NOT_FOUND);
      }
    } else {
      trigger_error('try push history');
      $this->historyService->push($this->parameterService->getParams());
    }
    return $this->loader(
      $this->parameterService['renderAs'],
      $this->parameterService['template'],
      $this->parameterService['projectName'],
      $this->parameterService['projectId'],
      $this->parameterService['musicianId']
    );
  }

  /**
   * @NoAdminRequired
   * @NoCSRFRequired
   */
  public function debug() {
    return $this->loader(
      'user',
      'debug', // template
      $this->parameterService['projectName'],
      $this->parameterService['projectId'],
      $this->parameterService['musicianId']
    );
  }

  /**
   * Load a specific page, also used to dynamically replace html content.
   *
   * @NoAdminRequired
   */
  public function loader(
    $renderAs = 'user',
    $template = 'blog',
    $projectName = '',
    $projectId = -1,
    $musicianId = -1) {

    if (empty($template)) {
      $template = 'blog';
    }

    // Initial state injecton for JS
    $this->publishInitialStateForUser($this->user());

    // The most important ...
    $encrkey = $this->getAppEncryptionKey();

    // Are we a group-admin?
    $isGroupAdmin = $this->isSubAdminOfGroup();

    $showToolTips = $this->getUserValue('tooltips', 'on');
    $usrFiltVis   = $this->getUserValue('filtervisibility', 'off');
    $directChg    = $this->getUserValue('directchange', 'off');
    $showDisabled = $this->getUserValue('showdisabled', 'off');
    $expertMode   = $this->getUserValue('expertmode', false);
    $pageRows     = $this->getUserValue('pagerows', 20);
    $debugMode    = $this->getUserValue('debug', 0);

    // @@TODO this should not go here, I think. Rather into PMETableBase.
    //
    // Filter visibility is stored here:
    //$pmeSysPfx = Config::$pmeopts['cgi']['prefix']['sys'];
    //Config::$pmeopts['cgi']['append'][$pmeSysPfx.'fl'] = $usrFiltVis == 'off' ? 0 : 1;

    // @@TODO this should not go here, I think. Rather into PMETableBase.
    //
    // Also @@TODO: perhaps find a way to inject the RequestParameters
    // without tweaking _POST
    $recordId = $this->phpMyEdit->getCGIRecordId();

    // See if we are configured
    $config = $this->configCheckService->configured();

    if (($template != 'debug' && !$config['summary'])) {
      $tmplname = 'configcheck';
      $renderer = null;
    } else {
      $template = "all-musicians";
      $tmplname = $template;
      //$renderer = \OC::$server->query(\OCA\CAFEVDB\TableView\Musicians::class);
      //\OC::$server->registerAlias($template, \OCA\CAFEVDB\TableView\Musicians::class);
      $renderer = $this->appContainer->query($template);
      if (empty($renderer)) {
        $this->logError("Template-renderer for template ".$template." is empty.");
      }
    }

    $templateParameters = [
      'template' => $tmplname,
      'renderer' => $renderer,

      'l' => $this->l,
      'appName' => $this->appName,

      'configcheck' => $config,
      'orchestra' => $this->getConfigValue('orchestra'),
      'usergroup' => $this->groupId(),
      'shareowner' => $this->getConfigValue('shareowner'),
      'sharedfolder' => $this->getConfigValue('sharedfolder'),
      'database' => $this->getConfigValue('database'),
      'groupadmin' => $this->isSubAdminOfGroup(),
      'user' => $this->userId(),
      'expertmode' => $expertMode,
      'showToolTips' => $showToolTips,
      'toolTips' => $this->toolTipsService,
      'urlGenerator' => $this->urlGenerator,
      'debugMode' => $debugMode,
      'encryptionkey' => $encrkey,
      'configkey' => $this->getConfigValue('encryptionkey'),
      'uploadMaxFilesize' => Util::maxUploadSize(),
      'uploadMaxHumanFilesize' => \OCP\Util::humanFileSize(Util::maxUploadSize()),
      'projectName' => $projectName,
      'projectId' => $projectId,
      'musicianId' => $musicianId,
      'recordId' => $recordId,
      'locale' => $this->getLocale(),
      'timezone' => $this->getTimezone(),
      'historySize' => $this->historyService->size(),
      'historyPosition' => $this->historyService->position(),
      'requesttoken' => \OCP\Util::callRegister(), // @TODO: check
      'filtervisibility' => $usrFiltVis,
      'directchange' => $directChg,
      'showdisabled' => $showDisabled,
      'pagerows' => $pageRows,
    ];

    // renderAs = admin, user, blank
    // $renderAs = 'user';
    $response = new TemplateResponse($this->appName, $tmplname, $templateParameters, $renderAs);
    if($renderAs == 'blank') {
      $response = new JSONResponse([
        'contents' => $response->render(),
        'history' => ['size' => $this->historyService->size(),
                      'position' => $this->historyService->position()]
      ]);
    }
    return $response;
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
