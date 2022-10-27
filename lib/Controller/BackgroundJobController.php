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
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use OCP\IL10N;

use OCA\CAFEVDB\BackgroundJob\LazyUpdateGeoCoding;
use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\BackgroundJob\ScanFiles;

/**
 * Run background-jobs triggered by AJAX pings from the front-end. The idea
 * here is that these jobs are running with an authenticated user.
 */
class BackgroundJobController extends Controller
{
  use \OCA\CAFEVDB\Traits\ConfigTrait;
  use \OCA\CAFEVDB\Traits\ResponseTrait;

  /**
   * @var int
   *
   * Do not run more often than this.
   */
  public const INTERVAL_SECONDS = 600;
  private const BACKGROUND_JOB_LAST_RUN = 'backgroundJobLastRun';

  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct(
    ?string $appName,
    IRequest $request,
    ConfigService $configService,
  ) {
    parent::__construct($appName, $request);

    $this->configService = $configService;
    $this->l = $this->l10N();
  }
  // phpcs:enable

  /**
   * @return DataResponse
   *
   * @NoAdminRequired
   */
  public function trigger():DataResponse
  {
    try {
      if (!$this->inGroup()) {
        return self::grumble(
          $this->l->t(
            'User "%s" not in orchestra group "%s', [
              $this->userId(), $this->groupId()
            ]),
          status: Http::STATUS_UNAUTHORIZED
        );
      }
      $now = time();
      $lastRun = (int)$this->getConfigValue(self::BACKGROUND_JOB_LAST_RUN, 0);
      if ($now - $lastRun < self::INTERVAL_SECONDS) {
        return self::dataResponse([
          'message' => 'Too early (' . ($now - $lastRun) . ' seconds)',
          'now' => $now,
          'lastRun' => $lastRun,
          'delta' => $now - $lastRun,
          'interval' => self::INTERVAL_SECONDS,
        ], Http::STATUS_TOO_MANY_REQUESTS);
      }
      $this->di(LazyUpdateGeoCoding::class)->run();

      $this->di(ScanFiles::class)->run(true);

      $this->setConfigValue(self::BACKGROUND_JOB_LAST_RUN, $now);
      return self::response('Ran background jobs');
    } catch (\Throwable $t) {
      $this->logException($t);
      return self::grumble(
        $this->l->t(
          'Caught exception \`%s\' at %s:%s', [
            $t->getMessage(), $t->getFile(), $t->getLine()
          ])
      );
    }
  }
}
