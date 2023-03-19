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

namespace OCA\CAFEVDB\BackgroundJob;

use OCP\BackgroundJob\TimedJob;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IConfig as ICloudConfig;

use OCA\CAFEVDB\Service\GeoCodingService;

/** Gradually sync with remote geo-coding providers. */
class BulkUpdateGeoCoding extends LazyUpdateGeoCoding
{
  /** @var GeoCodingService */
  private $geoCodingService;

  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct(
    string $appName,
    ITimeFactory $time,
    ICloudConfig $cloudConfig,
    GeoCodingService $geoCodingService,
  ) {
    parent::__construct($appName, $time, $cloudConfig, $geoCodingService);
    $this->setInterval($cloudConfig->getAppValue($appName, 'geocoding.refresh.bulk', 24*3600));
  }
  // phpcs:enable

  /** {@inheritdoc} */
  protected function run($arguments = [])
  {
    foreach ($this->geoCodingService->getLanguages() as $lang) {
      $this->geoCodingService->updateCountriesForLanguage($lang, true);
      $this->geoCodingService->updatePostalCodes($lang, 100);
    }
  }
}
