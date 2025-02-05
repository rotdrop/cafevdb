<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2021, 2022, 2024, 2025 Claus-Justus Heine
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

use OCA\CAFEVDB\Service\FilesCacheService;

/** Cleanup the files cache in the app-storage. */
class CleanupFilesCache extends TimedJob
{
  use \OCA\CAFEVDB\Toolkit\Traits\LoggerTrait;

  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct(
    string $appName,
    ITimeFactory $time,
    ICloudConfig $cloudConfig,
    private FilesCacheService $filesCacheService,
  ) {
    parent::__construct($time);
    $this->setInterval($cloudConfig->getAppValue($appName, 'backgroundjobs.cleanupfilescache.interval', 24*3600));
  }
  // phpcs:enable

  /** {@inheritdoc} */
  public function run($arguments = [])
  {
    $this->filesCacheService->clean();
  }
}
