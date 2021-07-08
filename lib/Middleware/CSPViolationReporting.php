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

namespace OCA\CAFEVDB\Middleware;

use OCP\AppFramework\Middleware;
use OCP\AppFramework\Http\Response;

use OCA\CAFEVDB\Service\ConfigService;

/**
 * Sends CSP reports to a dedicated addresss for debugging.
 */
class CSPViolationReporting extends Middleware
{
  use \OCA\CAFEVDB\Traits\ConfigTrait;

  /** @var ConfigService */
  protected $configService;

  /**
   * @param ConfigService $configService
   */
  public function __construct(ConfigService $configService)
  {
    $this->configService = $configService;
  }

  /**
   * Add CSP reporting to CSP header.
   */
  public function afterController($controller, $methodName, Response $response)
  {
    $reportCSP = $this->inGroup();
    try {
      $reportCSP = $this->getConfigValue('debugmode', 0) & ConfigService::DEBUG_CSP;
    } catch (\Throwable $t) {
      $reportCSP = false;
    }
    if ($reportCSP) {
      $reportLocation = $this->getConfigValue('cspfailurereporting', null);
      if (empty($this->reportLocation)) {
        $cspFailureToken = $this->getAppValue('cspfailuretoken');
        $reportLocation = $this->urlGenerator()->linkToRoute($this->appName().'.csp_violation.post', ['operation' => 'report']);
        $reportLocation .= '?cspFailureToken='.urlencode($cspFailureToken);
      }
      $csp = $response->getContentSecurityPolicy();
      $csp->addReportTo($reportLocation);
    }
    return $response;
  }
}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
