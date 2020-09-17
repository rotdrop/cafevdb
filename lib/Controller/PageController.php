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
use OCP\AppFramework\Controller;
use OCP\IGroupManager;
use OCP\Group\ISubAdmin;
use OCP\IUserSession;
use OCP\IConfig;
use OCP\IL10N;

use OCA\CAFEVDB\Common\Config;
use OCA\CAFEVDB\Common\ConfigCheck;
use OCA\CAFEVDB\Common\Util;

class PageController extends Controller {
  /** @var IL10N */
  private $l;

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

  //@@TODO inject config via constructor
  public function __construct($appName, IRequest $request, IL10N $l, IGroupManager $groupManager, IConfig $containerConfig, IUserSession $userSession, ISubAdmin $groupSubAdmin) {
    parent::__construct($appName, $request);
    $this->l = $l;
    $this->groupManager = $groupManager;
    $this->groupSubAdmin = $groupSubAdmin;
    $this->containerConfig = $containerConfig;
    $this->userSession = $userSession;

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
    //return new TemplateResponse('cafevdb', 'main');  // templates/main.php
    return $this->pageloader();
  }

  public function pageloader($template = 'blog', $projectName = '', $projectId = -1, $musicianId = -1)
  {
    // The most important ...
    $encrkey = Config::getEncryptionKey();

    // Get user and group
    $group = Config::getAppValue('usergroup', '');

    // Are we a group-admin?
    //@@TODO needed in more than one location
    $admin = !empty($group) && $this->groupSubAdmin->isSubAdminofGroup($this->user, $this->groupManager->get($group));

    $tooltips     = $this->getUserValue('tooltips', 'on');
    $usrFiltVis   = $this->getUserValue('filtervisibility', 'off');
    $directChg    = $this->getUserValue('directchange', 'off');
    $showDisabled = $this->getUserValue('showdisabled', 'off');
    $pageRows     = $this->getUserValue('pagerows', 20);

    // Filter visibility is stored here:
    $pmeSysPfx = Config::$pmeopts['cgi']['prefix']['sys'];
    Config::$pmeopts['cgi']['append'][$pmeSysPfx.'fl'] = $usrFiltVis == 'off' ? 0 : 1;

    // See if we are configured
    //$config = ConfigCheck::configured();

    if (false) {
    // following three may or may not be set
    // $projectName = Util::cgiValue('ProjectName', '');
    // $projectId   = Util::cgiValue('ProjectId', -1);
    // $musicianId  = Util::cgiValue('MusicianId', -1);
    $recordId    = Util::getCGIRecordId();

    if (!$config['summary']) {
      $tmplname = 'configcheck';
    } else {
      $tmplname = $template;
    }

    }

    return new JSONResponse(['POST' => $_POST,
                             'GET' => $_GET,
                             'SERVER' => $_SERVER]);
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
