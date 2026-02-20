<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2020-2026 Claus-Justus Heine
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

namespace OCA\CAFEVDB\Controller;

use Spatie\TypeScriptTransformer\Attributes as TSAttributes;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute as CoreAttributes;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use Psr\Log\LoggerInterface as ILogger;

use OCA\CAFEVDB\Attributes;
use OCA\CAFEVDB\Common\Util;
use OCA\CAFEVDB\Service\ToolTipsService;

/** Fetch one or multiple tooltip via AJAX. */
#[TSAttributes\TypeScript]
class ToolTipsController extends Controller
{
  use \OCA\CAFEVDB\Toolkit\Traits\ResponseTrait;
  use \OCA\CAFEVDB\Toolkit\Traits\LoggerTrait;

  public const END_POINT = 'tooltips';

  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct(
    ?string $appName,
    IRequest $request,
    private ToolTipsService $toolTipsService,
    protected ILogger $logger,
  ) {
    parent::__construct($appName, $request);
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
   */
  #[Attributes\NoGroupMemberRequired]
  #[CoreAttributes\NoAdminRequired]
  #[CoreAttributes\FrontpageRoute(
    verb: 'GET',
    url: '/' . self::END_POINT . '/{key}',
    requirements: [ 'key' => '^.+$' ],
  )]
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
   */
  #[Attributes\NoGroupMemberRequired]
  #[CoreAttributes\FrontpageRoute(verb: 'GET', url: '/' . self::END_POINT)]
  #[CoreAttributes\NoAdminRequired]
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
