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

use OCA\CAFEVDB\Common\Util;

use OCA\CAFEVDB\Service\HistoryService;
use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Service\RequestParameterService;
use OCA\CAFEVDB\Service\ConfigCheckService;
use OCA\CAFEVDB\Service\ToolTipsService;

class PageController extends Controller {
  use \OCA\CAFEVDB\Traits\InitialStateTrait;

  /** @var IL10N */
  private $l;

  /** @var HistoryService */
  private $historyService;

  /** @var RequestParameterService */
  private $parameterService;

  /** @var ToolTipsService */
  private $toolTipsService;

  /** @var ConfigService */
  private $configService;

  /** @var ConfigCheckService */
  private $configCheckService;

  public function __construct(
    $appName,
    IRequest $request,
    ConfigService $configService,
    HistoryService $historyService,
    RequestParameterService $parameterService,
    ToolTipsService $toolTipsService,
    IInitialStateService $initialStateService,
    ConfigCheckService $configCheckService
  ) {

    parent::__construct($appName, $request);

    $this->configService = $configService;
    $this->historyService = $historyService;
    $this->parameterService = $parameterService;
    $this->toolTipsService = $toolTipsService;
    $this->initialStateService = $initialStateService;
    $this->configCheckService = $configCheckService;
    $this->l = $this->l10N();
  }

  /**
   * Load the main page of the App.
   *
   * @NoAdminRequired
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

    // Filter visibility is stored here:
    //$pmeSysPfx = Config::$pmeopts['cgi']['prefix']['sys'];
    //Config::$pmeopts['cgi']['append'][$pmeSysPfx.'fl'] = $usrFiltVis == 'off' ? 0 : 1;

    // @@TODO this should not go here, I think
    $recordId = Util::getCGIRecordId([$this->request, 'getParam']);

    // See if we are configured
    $config = $this->configCheckService->configured();

    if (true || ($template != 'debug' && !$config['summary'])) {
      $tmplname = 'configcheck';
    } else {
      $tmplname = $template;
    }

    $templateParameters = [
      'template' => $tmplname,

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


}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
