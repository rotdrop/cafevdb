<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2020, 2021, 2022, 2022, 2023 Claus-Justus Heine
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
/**
 * @file Expose tooltips as AJAY controllers, fetching them by their key.
 */

namespace OCA\CAFEVDB\Controller;

use OCP\AppFramework\Controller;
use OCP\IRequest;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use Psr\Log\LoggerInterface as ILogger;

use OCA\CAFEVDB\Service\ToolTipsService;

use OCA\CAFEVDB\Common\Util;

/** Fetch one or multiple tooltip via AJAX. */
class ToolTipsController extends Controller
{
  use \OCA\CAFEVDB\Toolkit\Traits\ResponseTrait;
  use \OCA\CAFEVDB\Toolkit\Traits\LoggerTrait;

  /** @var IL10N */
  protected IL10N $l;

  /** @var ToolTipsService */
  private $toolTipsService;

  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct(
    ?string $appName,
    IRequest $request,
    ToolTipsService $toolTipsService,
    ILogger $logger,
  ) {
    parent::__construct($appName, $request);
    $this->toolTipsService = $toolTipsService;
    $this->logger = $logger;
  }
  // phpcs:enable

  /**
   * @param string $key
   *
   * @param null|bool $debug
   *
   * @param bool $unescaped
   *
   * @return DataResponse
   *
   * @NoAdminRequired
   * @NoGroupMemberRequired
   */
  public function get(string $key, ?bool $debug = null, bool $unescaped = false):DataResponse
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

  /**
   * @param array $keys
   *
   * @param null|bool $debug
   *
   * @param bool $unescaped
   *
   * @return DataResponse
   *
   * @NoAdminRequired
   * @NoGroupMemberRequired
   */
  public function getMultiple(array $keys, ?bool $debug = null, bool $unescaped = false)
  {
    $this->toolTipsService->debug($debug);
    $tooltips = [];
    foreach ($keys as $key) {
      $tooltip = $this->toolTipsService->fetch($key, escape: false);
      if (!$unescaped) {
        $tooltip = Util::htmlEscape($tooltip);
      }
      $tooltips[$key] = $tooltip;
    }
    return new DataResponse($tooltips, Http::STATUS_OK);
  }
}
