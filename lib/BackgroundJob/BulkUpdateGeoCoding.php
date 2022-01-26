<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2020, 2021, 2022 Claus-Justus Heine <himself@claus-justus-heine.de>
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

class BulkUpdateGeoCoding extends LazyUpdateGeoCoding
{
  /** @var GeoCodingService */
  private $geoCodingService;

  public function __construct(
    $appName
    , ITimeFactory $time
    , ICloudConfig $cloudConfig
    , GeoCodingService $geoCodingService
  ) {
    parent::__construct($appName, $time, $cloudConfig, $geoCodingService);
    $this->setInterval($cloudConfig->getAppValue($appName, 'geocoding.refresh.bulk', 24*3600));
  }

  /**
   * @param array $arguments
   */
  protected function run($arguments = []) {
    foreach ($this->geoCodingService->getLanguages() as $lang) {
      $this->geoCodingService->updateCountriesForLanguage($lang, true);
      $this->geoCodingService->updatePostalCodes($lang, 100);
    }
  }
}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
