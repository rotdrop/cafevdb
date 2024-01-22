<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2020, 2021, 2022, 2024 Claus-Justus Heine
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

namespace OCA\CAFEVDB\BackgroundJob;

use OCP\BackgroundJob\TimedJob;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IConfig as ICloudConfig;
use Psr\Log\LoggerInterface as ILogger;

use OCA\CAFEVDB\Service\GeoCodingService;

/**
 * This cannot work ATM as it needs an authenticated user.
 *
 * @todo Add a service-worker data-base account with access to non-sensitive data.
 */
class LazyUpdateGeoCoding extends TimedJob
{
  use \OCA\CAFEVDB\Toolkit\Traits\LoggerTrait;

  /** {@inheritdoc} */
  public function __construct(
    string $appName,
    ITimeFactory $time,
    ICloudConfig $cloudConfig,
    protected ILogger $logger,
    private GeoCodingService $geoCodingService,
  ) {
    parent::__construct($time);
    $this->setInterval($cloudConfig->getAppValue($appName, 'geocoding.refresh.lazy', 600));
  }

  /** {@inheritdoc} */
  public function run($arguments = [])
  {
    foreach ($this->geoCodingService->getLanguages() as $lang) {
      $this->geoCodingService->updateCountriesForLanguage($lang);
      if (!$this->geoCodingService->updatePostalCodes($lang, 1)) {
        // do not continue, might be a rate limit.
        $this->logError('Background update of postal codes failed for language ' . $lang);
        break;
      }
    }
  }
}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
