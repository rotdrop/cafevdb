<?php
/**
 * Orchestra member, musician and project management application.
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
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use OCP\IL10N;

use OCA\CAFEVDB\BackgroundJob\LazyUpdateGeoCoding;
use OCA\CAFEVDB\Service\ConfigService;

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

  public function __construct(
    $appName
    , IRequest $request
    , ConfigService $configService
  ) {
    parent::__construct($appName, $request);

    $this->configService = $configService;
    $this->l = $this->l10N();
  }

  /**
   * @NoAdminRequired
   */
  public function trigger()
  {
    try {
      $now = time();
      $lastRun = $this->getConfigValue(self::BACKGROUND_JOB_LAST_RUN, 0);
      if ($now - $lastRun < self::INTERVAL_SECONDS) {
        return;
      }
      if (!$this->inGroup()) {
        return self::grumble(
          $this->l->t('User "%s" not in orchestra group "%s',
                      [ $this->userId(), $this->groupId() ]));
      }
      $this->di(LazyUpdateGeoCoding::class)->run();
      $this->setConfigValue(self::BACKGROUND_JOB_LAST_RUN, $now);
      return self::response('Ran background jobs');
    } catch (\Throwable $t) {
      $this->logException($t);
      return self::grumble(
        $this->l->t('Caught exception \`%s\' at %s:%s',
                    [$t->getMessage(), $t->getFile(), $t->getLine()])
      );
    }
  }
}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
