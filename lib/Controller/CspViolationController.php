<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2020, 2021, 2022 Claus-Justus Heine
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

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\IRequest;
use Psr\Log\LoggerInterface as ILogger;
use OCP\IL10N;
use OCP\IConfig;

/** AJAX endpoints for reporting CSP violation errors. */
class CspViolationController extends Controller
{
  use \OCA\RotDrop\Toolkit\Traits\ResponseTrait;
  use \OCA\RotDrop\Toolkit\Traits\LoggerTrait;

  /** @var \OCP\IConfig */
  private $config;

  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct(
    ?string $appName,
    IRequest $request,
    IConfig $config,
    ILogger $logger,
    IL10N $l10n,
  ) {
    parent::__construct($appName, $request);
    $this->config = $config;
    $this->logger = $logger;
    $this->l = $l10n;
  }
  // phpcs:enable

  /**
   * @param string $operation
   *
   * @param null|string $cspFailureToken
   *
   * @return Http\Response
   *
   * @NoCSRFRequired
   * @NoAdminRequired
   * @PublicPage
   * @NoGroupMemberRequired
   */
  public function post(string $operation, ?string $cspFailureToken = null):Http\Response
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
