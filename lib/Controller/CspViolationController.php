<?php
/* Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2020, 2021 Claus-Justus Heine <himself@claus-justus-heine.de>
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU GENERAL PUBLIC LICENSE
 * License as published by the Free Software Foundation; either
 * version 3 of the License, or any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU AFFERO GENERAL PUBLIC LICENSE for more details.
 *
 * You should have received a copy of the GNU Lesser General Public
 * License along with this library.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace OCA\CAFEVDB\Controller;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\IRequest;
use OCP\ILogger;
use OCP\IL10N;
use OCP\IConfig;

class CspViolationController extends Controller {
  use \OCA\CAFEVDB\Traits\ResponseTrait;
  use \OCA\CAFEVDB\Traits\LoggerTrait;

  /** @var \OCP\IConfig */
  private $config;

  public function __construct(
    $appName
    , IRequest $request
    , IConfig $config
    , ILogger $logger
    , IL10N $l10n
  ) {
    parent::__construct($appName, $request);
    $this->config = $config;
    $this->logger = $logger;
    $this->l = $l10n;
  }

  /**
   * @NoCSRFRequired
   * @NoAdminRequired
   * @PublicPage
   * @NoGroupMemberRequired
   */
  public function post($operation, $cspFailureToken = null)
  {
    if ($operation != 'report') {
      return self::grumble($this->l->t('Unknown Request'));
    }
    if (empty($cspFailureToken) ||
        $this->config->getAppValue($this->appName, 'cspfailuretoken') !== $cspFailureToken) {
      return (new Http\Response)->setStatus(Http::STATUS_PRECONDITION_FAILED);
    }
    $cspReportData = file_get_contents('php://input');
    $cspReport = json_decode($cspReportData);
    $this->logError("CSP Report: ".json_encode($cspReport, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    return (new Http\Response)->setStatus(Http::STATUS_NO_CONTENT);
  }
}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
