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
use OCP\IL10N;

use OCA\CAFEVDB\Common\Config;

class PageController extends Controller {
    /** @var IL10N */
    private $l;

	public function __construct($AppName, IRequest $request, IL10N $l){
		parent::__construct($AppName, $request);
        $this->l = $l;
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
        Config::init();
        return new JSONResponse(['POST' => $_POST,
                                 'GET' => $_GET,
                                 'SERVER' => $_SERVER]);
    }
}

// Local Variables: ***
// c-basic-offset: 4 ***
// indent-tabs-mode: nil ***
// End: ***
