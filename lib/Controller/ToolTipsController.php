<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2020, 2021, 2022, 2022 Claus-Justus Heine <himself@claus-justus-heine.de>
 * @license GNU AGPL version 3 or any later version
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
/**
 * @file Expose tooltips as AJAY controllers, fetching them by their key.
 */

namespace OCA\CAFEVDB\Controller;

use OCP\AppFramework\Controller;
use OCP\IRequest;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\ILogger;

use OCA\CAFEVDB\Service\ToolTipsService;

use OCA\CAFEVDB\Common\Util;

class ToolTipsController extends Controller
{
  use \OCA\CAFEVDB\Traits\ResponseTrait;
  use \OCA\CAFEVDB\Traits\LoggerTrait;

  /** @var IL10N */
  protected $l;

  /** @var ToolTipsService */
  private $toolTipsService;

  public function __construct(
    $appName
    , IRequest $request
    , ToolTipsService $toolTipsService
    , ILogger $logger
  ) {
    parent::__construct($appName, $request);
    $this->toolTipsService = $toolTipsService;
    $this->logger = $logger;
  }

  /**
   * @NoAdminRequired
   * @NoGroupMemberRequired
   */
  public function get(string $key, ?bool $debug = null, bool $unescaped = false)
  {
    $this->toolTipsService->debug($debug);
    $tooltip = $this->toolTipsService->fetch($key, escape: false);
    if (!$unescaped) {
      $tooltip = Util::htmlEscape($tooltip);
    }
    if (empty($tooltip)) {
      return new DataResponse([ 'key' => $key ], Http::STATUS_NOT_FOUND);
    } else {
      return new DataResponse([ 'key' => $key, 'tooltip' => $tooltip ], Http::STATUS_OK);
    }
  }

}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
