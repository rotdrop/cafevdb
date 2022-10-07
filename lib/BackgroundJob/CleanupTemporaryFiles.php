<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2021 Claus-Justus Heine <himself@claus-justus-heine.de>
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
use OCP\ILogger;

use OCA\CAFEVDB\Storage\AppStorage;
use OCA\CAFEVDB\Common\PlainFileProgressStatus;

class CleanupTemporaryFiles extends TimedJob
{
  use \OCA\CAFEVDB\Traits\LoggerTrait;

  const DIRECTORIES = [
    AppStorage::UPLOAD_FOLDER,
    PlainFileProgressStatus::DATA_DIR,
  ];

  /** @var AppStorage */
  private $appStorage;

  /** @var int Age in seconds after which temporary files will be removed */
  private $oldAge;

  public function __construct(
    $appName
    , ITimeFactory $time
    , ICloudConfig $cloudConfig
    , ILogger $logger
    , AppStorage $appStorage
  ) {
    parent::__construct($time);
    $this->logger = $logger;
    $this->appStorage = $appStorage;
    $this->setInterval($cloudConfig->getAppValue($appName, 'backgroundjobs.cleanuptemporaryfiles.interval', 3600));
    $this->oldAge = $cloudConfig->getAppValue($appName, 'backgroundjobs.cleanuptemporaryfiles.oldage', 24*60*60);
  }

  /**
   * @param array $arguments
   */
  public function run($arguments = []) {
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

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
