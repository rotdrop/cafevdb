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
use OCP\IUserManager;
use OCP\IGroupManager;
use OCP\Group\ISubAdmin;
use OCP\IUserSession;
use OCP\ISession;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IInitialStateService;

use OCA\CAFEVDB\Common\Config;
use OCA\CAFEVDB\Common\ConfigCheck;
use OCA\CAFEVDB\Common\Util;

use OCA\CAFEVDB\Service\HistoryService;
use OCA\CAFEVDB\Service\RequestParameterService;

class PageController extends Controller {
  use \OCA\CAFEVDB\Traits\InitialStateTrait;

  /** @var IL10N */
  private $l;

  /** @var IUserManager */
  private $userManager;

  /** @var IGroupManager */
  private $groupManager;

  /** @var IConfig */
  private $containerConfig;

  /** @var IUserSession */
  private $userSession;

  /** @var ISubAdmin */
  private $groupSubAdmin;

  /** @var IUser */
  private $user;

  /** @var int */
  private $userId;

  /** @var HistoryService */
  private $historyService;

  /** @var RequestParameterSerice */
  private $parameterService;

  //@@TODO inject config via constructor
  public function __construct(
    $appName,
    IRequest $request,
    IL10N $l,
    IUserManager $userManager,
    IGroupManager $groupManager,
    ISubAdmin $groupSubAdmin,
    IConfig $containerConfig,
    IUserSession $userSession,
    HistoryService $historyService,
    RequestParameterService $parameterService,
    IInitialStateService $initialStateService
  ) {

    parent::__construct($appName, $request);

    $this->l = $l;
    $this->userManager = $userManager;
    $this->groupManager = $groupManager;
    $this->groupSubAdmin = $groupSubAdmin;
    $this->containerConfig = $containerConfig;
    $this->userSession = $userSession;
    $this->historyService = $historyService;
    $this->parameterService = $parameterService;
    $this->initialStateService = $initialStateService;

    //@@TODO: make non static ?
    //Config::init($this->userSession, $this->$containerConfig, $this->groupManager);
    Config::init();

    $this->user = $this->userSession->getUser();
    $this->userId = $this->user->getUID();
  }

  /**
   * CAUTION: the @Stuff turn off security checks, for this page no admin is
   *          required and no CSRF check. If you don't know what CSRF is, read
   *          it up in the docs or you might create a security hole. This is
   *          basically the only required method to add this exemption, don't
   *          add it to any other method if you don't exactly know what it does
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
      $this->historyService->push($this->parameterService->getParams());
    }
    return $this->loader(
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
      'debug', // template
      $this->parameterService['projectName'],
      $this->parameterService['projectId'],
      $this->parameterService['musicianId']
    );
  }

  public function loader($template = 'blog', $projectName = '', $projectId = -1, $musicianId = -1) {
    if (empty($template)) {
      $template = 'blog';
    }

    // Initial state injecton for JS
    $this->publishInitialStateForUser($this->user);

    // The most important ...
    $encrkey = Config::getEncryptionKey();

    // Get user and group
    $groupId = Config::getAppValue('usergroup', '');

    // Are we a group-admin?
    //@@TODO needed in more than one location
    $isGroupAdmin = !empty($groupId) && $this->groupSubAdmin->isSubAdminofGroup($this->user, $this->groupManager->get($groupId));

    $tooltips     = $this->getUserValue('tooltips', 'on');
    $usrFiltVis   = $this->getUserValue('filtervisibility', 'off');
    $directChg    = $this->getUserValue('directchange', 'off');
    $showDisabled = $this->getUserValue('showdisabled', 'off');
    $pageRows     = $this->getUserValue('pagerows', 20);

    // Filter visibility is stored here:
    $pmeSysPfx = Config::$pmeopts['cgi']['prefix']['sys'];
    Config::$pmeopts['cgi']['append'][$pmeSysPfx.'fl'] = $usrFiltVis == 'off' ? 0 : 1;

    // @@TODO this should not go here, I think
    $recordId = Util::getCGIRecordId([$this->request, 'getParam']);

    // See if we are configured
    $config = (new ConfigCheck($this->userManager, $this->groupManager))->configured();

    if ($template != 'debug' && !$config['summary']) {
      $tmplname = 'configcheck';
    } else {
      $tmplname = $template;
    }

    $templateParameters = [
      'template' => $tmplname,

      'l' => $this->l,
      'appName' => $this->appName,

      'configcheck' => $config,
      'orchestra' => Config::getValue('orchestra'),
      'groupadmin' => $isGroupAdmin,
      'usergroup' => $groupId,
      'user' => $this->userId,
      'expertmode' => Config::$expertmode,
      'tooltips' => $tooltips,
      'encryptionkey' => $encrkey,
      'uploadMaxFilesize' => Util::maxUploadSize(),
      'uploadMaxHumanFilesize' => \OCP\Util::humanFileSize(Util::maxUploadSize()),
      'projectName' => $projectName,
      'projectId' => $projectId,
      'musicianId' => $musicianId,
      'recordId' => $recordId,
      'locale' => Util::getLocale(),
      'timezone' => Util::getTimezone(),
      'historySize' => $this->historyService->size(),
      'historyPosition' => $this->historyService->position(),
      'requesttoken' => \OCP\Util::callRegister(),
      'filtervisibility' => $usrFiltVis,
      'directchange' => $directChg,
      'showdisabled' => $showDisabled,
      'pagerows' => $pageRows,
    ];

    return new TemplateResponse($this->appName, $tmplname, $templateParameters);
  }

  private function getUserValue($key, $default = null)
  {
    return $this->containerConfig->getuserValue($this->userId, $this->appName, $key, $default);
  }
}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
