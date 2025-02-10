<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2020-2025 Claus-Justus Heine
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

use Psr\Log\LoggerInterface;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IRequest;

use OCA\CAFEVDB\Service\ProblemReportService;

/**
 * AJAX endpoints for reporting "frontend" errors, i.e. errors the user was confronted with.
 */
class ProblemReportController extends Controller
{
  use \OCA\CAFEVDB\Toolkit\Traits\ResponseTrait;
  use \OCA\CAFEVDB\Toolkit\Traits\LoggerTrait;

  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct(
    ?string $appName,
    IRequest $request,
    protected LoggerInterface $logger,
    protected IL10N $l,
    protected ProblemReportService $reportService,
  ) {
    parent::__construct($appName, $request);
  }
  // phpcs:enable

  /**
   * Submit a problem report.
   *
   * @param array $user The user reporting the error [ 'uid' => UID, 'displayName' => DISPLAY_NAME ].
   *
   * @param array $errorData The raw error data caught by the frontend
   * code. Ideally, this is data in the format of Nextcloud log entries, but this is not guaranteed.
   *
   * @param null|string $userComment Optional comment submitted alongside the user. May be markdown.
   *
   * @return Http\DataResponse
   *
   * @NoAdminRequired
   * @NoGroupMemberRequired
   */
  public function post(
    array $user,
    array $errorData,
    ?string $userComment,
  ):DataResponse {
    $status = Http::STATUS_OK;
    try {
      $result = $this->reportService->submit($user, $errorData, $userComment);
    } catch (Throwable $t) {
      $result = null;
    }
    if ($result === null) {
      $result = $this->l->t('Unfortunately your problem report could not be submitted. Please use other communication channels to submit it.');
      $status = Http::STATUS_SERVICE_UNAVAILABLE;
    }

    return self::dataResponse([ 'messages' => $result ], $status);
  }
}
