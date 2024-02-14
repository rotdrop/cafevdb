<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2021, 2022, 2023, 2024 Claus-Justus Heine
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

use OCA\CAFEVDB\Service\AppMTimeService;

/**
 * Cleanup temporary file downloads where the share-link has expired. This is
 * primarily meant for automatically created large email attachments.
 */
class UpdateAppMTimes extends TimedJob
{
  use \OCA\CAFEVDB\Toolkit\Traits\LoggerTrait;

  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct(
    ITimeFactory $time,
    ICloudConfig $cloudConfig,
    protected string $appName,
    protected ILogger $logger,
    protected AppMTimeService $appMTimeService,
  ) {
    parent::__construct($time);
    $this->setInterval($cloudConfig->getAppValue($appName, 'backgroundjobs.updateAppMTimes.interval', 3600));
  }
  // phpcs:enable

  /**
   * {@inheritdoc}
   */
  protected function run($arguments)
  {
    foreach (array_keys(AppMTimeService::PARTS) as $appPart) {
      $this->appMTimeService->getMTime($appPart, rescan: true);
    }
  }
}
