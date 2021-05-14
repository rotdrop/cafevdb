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

namespace OCA\CAFEVDB\BackgroundJob;

use OCP\BackgroundJob\TimedJob;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IConfig as ICloudConfig;

use OCA\CAFEVDB\Service\GeoCodingService;

/**
 * This cannot work ATM as it needs an authenticated user.
 *
 * @todo Add a service-worker data-base account with access to non-sensitive data.
 */
class LazyUpdateGeoCoding extends TimedJob
{
  /** @var GeoCodignService */
  private $geoCodingService;

  public function __construct(
    $appName
    , ITimeFactory $time
    , ICloudConfig $cloudConfig
    , GeoCodingService $geoCodingService
  ) {
    parent::__construct($time);
    $this->geoCodingService = $geoCodingService;
    $this->setInterval($cloudConfig->getAppValue($appName, 'geocoding.refresh.lazy', 600));
  }

  /**
   * @param array $arguments
   */
  public function run($arguments = []) {
    foreach ($this->geoCodingService->languages() as $lang) {
      $this->geoCodingService->updateCountriesForLanguage($lang);
      $this->geoCodingService->updatePostalCodes($lang, 1);
    }
  }
}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
