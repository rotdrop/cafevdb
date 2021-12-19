<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2021 Claus-Justus Heine <himself@claus-justus-heine.de>
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

use OCP\BackgroundJob\IJobList;
use OCP\BackgroundJob\TimedJob;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Files\IRootFolder;
use OCP\Files\Node;
use OCP\Files\Folder;
use OCP\Files\File;
use OCP\IConfig as ICloudConfig;
use OCP\ILogger;
use OCP\Share\IManager as IShareManager;
use OCP\Share\IShare;

use OCA\CAFEVDB\Storage\AppStorage;
use OCA\CAFEVDB\Common\PlainFileProgressStatus;

/**
 * Cleanup temporary file downloads where the share-link has expired. This is
 * primarily meant for automatically created large email attachments.
 */
class CleanupExpiredDownloads extends TimedJob
{
  use \OCA\CAFEVDB\Traits\LoggerTrait;

  /** @var IRootFolder*/
  private $rootFolder;

  /** @var IShareManager */
  private $shareManager;

  /**
   * @var int Additional grace time to keep even expired downloads. This is
   * relative to the modification time to the respective file.
   */
  private $oldAge;

  /** @var bool */
  private $debug = false;

  public function __construct(
    $appName
    , ITimeFactory $time
    , IRootFolder $rootFolder
    , IShareManager $shareManager
    , ICloudConfig $cloudConfig
    , ILogger $logger
  ) {
    parent::__construct($time);
    $this->rootFolder = $rootFolder;
    $this->shareManager = $shareManager;
    $this->logger = $logger;
    $this->setInterval($cloudConfig->getAppValue($appName, 'backgroundjobs.cleanupexpireddownloads.interval', 120)); //3600));
    $this->oldAge = $cloudConfig->getAppValue($appName, 'backgroundjobs.cleanupexpireddownloads.oldage', 24*60*60);
  }

  /**
   * @param array $arguments
   * ```
   * [
   *   'uid' => USER_ID_OF_SHARE_OWNER,
   *   'paths' => [ PATH_0, PATH_1, ...  ],
   * ]
   * ```
   */
  protected function run($arguments)
  {
    $this->debug('Run');

    if (empty($arguments['uid'])) {
      $this->debug('Required argument "uid" is not set.');
    }

    if (empty($arguments['paths'])) {
      $this->debug('Required argument "paths" is not set.');
      return;
    }

    $uid = $arguments['uid'];
    $paths = (array)$arguments['paths'];

    $userFolder = $this->rootFolder->getUserFolder($uid);
    if (empty($userFolder)) {
      $this->debug('Unable to obtain user-folder handle for "' . $uid . '".');
      return;
    }

    $now = $this->time->getTime();
    foreach ($paths as $directoryName) {
      /** @var OCP\Files\Folder $directory */
      $directory = $userFolder->get($directoryName);
      if (empty($directory)) {
        $this->debug('Unable to get handle for clean-directory "' . $directoryName . '".');
        continue;
      }
      $this->debug('Try cleaning "' . $directoryName . '".');
      $count = $this->recurseDirectory($directory, function($node) use ($uid) {
        $this->debug('Examining ' . $node->getName());
        // this will never return expired shares and event delete expired shares
        $shares = $this->shareManager->getSharesBy($uid, IShare::TYPE_LINK, $node);
        $this->debug('Found ' . count($shares) . ' shares');
        // initialize with file mtime in order to implement a grace period
        $expirationStampMax = $node->stat()['mtime'];
        /** @var IShare $share */
        foreach ($shares as $share) {
          $expirationDate = $share->getExpirationDate();
          if (empty($expirationDate)) {
            $this->debug('Expiration date for share is null');
            $expirationDate = new \DateTime; // never expire?
          } else {
            $this->debug('Expiration date ' . print_r($expirationDate, true));
          }
          $expirationStampMax = max($expirationStampMax, $expirationDate->getTimestamp());
        }
        $ttlSeconds = $expirationStampMax + $this->oldAge - $this->time->getTime();
        if ($ttlSeconds <= 0) {
          $this->debug('Deleting expired download share ' . $node->getName() . ', TTL ' . $ttlSeconds);
          $node->delete();
          return 0;
        } else {
          $this->debug('Keeping active share ' . $node->getName() . ', TTL ' . $ttlSeconds);
          return 1;
        }
      });
      $this->debug('Found ' . $count . ' entries.');
      // If no entries remain we can remove ourselves. We will be re-added by the email form as necessary
      if ($count == 0) {
        /** @var IJobList $jobList */
        $jobList = \OC::$server->get(IJobList::class);
        if (!empty($jobList)) {
          $jobList->remove($this, $arguments);
          $this->debug('Removing ourselves from the job-list as the downloads folder is empty');
        }
      }
    }
  }

  private function recurseDirectory(Folder $folder, $callback, $level = 0)
  {
    $count = 0;
    /** @var Node $node */
    foreach ($folder->getDirectoryListing() as $node) {
      if ($node->getType() == Node::TYPE_FOLDER) {
        $count += $this->recurseDirectory($node, $callback, $level + 1);
      } else {
        $count += call_user_func($callback, $node);
      }
    }
    if ($count == 0 && $level > 0) {
      $this->debug('Deleting empty download folder ' . $folder->getName());
      $folder->delete();
    }
    return $count;
  }

  private function debug(string $message, array $context = [], $shift = 1) {
    if ($this->debug) {
      $this->logInfo($message, $context, $shift + 1);
    } else {
      $this->logDebug($message, $context, $shift + 1);
    }
  }
}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***