<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2021, 2022, 2022, 2024 Claus-Justus Heine
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

use OCA\CAFEVDB\Storage\AppStorage;
use OCA\CAFEVDB\Common\PlainFileProgressStatus;

/** Cleanup left-over temporary files from the app-storage. */
class CleanupTemporaryFiles extends TimedJob
{
  use \OCA\CAFEVDB\Toolkit\Traits\LoggerTrait;

  const DIRECTORIES = [
    AppStorage::UPLOAD_FOLDER,
    PlainFileProgressStatus::DATA_DIR,
  ];

  /** @var int Age in seconds after which temporary files will be removed */
  private $oldAge;

  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct(
    string $appName,
    ITimeFactory $time,
    ICloudConfig $cloudConfig,
    protected ILogger $logger,
    private AppStorage $appStorage,
  ) {
    parent::__construct($time);
    $this->setInterval($cloudConfig->getAppValue($appName, 'backgroundjobs.cleanuptemporaryfiles.interval', 3600));
    $this->oldAge = $cloudConfig->getAppValue($appName, 'backgroundjobs.cleanuptemporaryfiles.oldage', 24*60*60);
  }
  // phpcs:enable

  /** {@inheritdoc} */
  public function run($arguments = [])
  {
    $this->logInfo('Run');
    $now = $this->time->getTime();
    foreach (self::DIRECTORIES as $directoryName) {
      /** @var \OCP\Files\SimpleFS\ISimpleFile $file */
      foreach ($this->appStorage->getFolder($directoryName)->getDirectoryListing() as $file) {
        $age = $now - $file->getMTime();
        if ($age > $this->oldAge) {
          $this->logInfo('Remove old age file ' . $file->getName());
          $file->delete();
        }
      }
    }
  }
}
