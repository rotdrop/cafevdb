<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2011-2014, 2016, 2020, 2021, 2022, Claus-Justus Heine
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

namespace OCA\CAFEVDB\Storage;

use RuntimeException;
use InvalidArgumentException;

use OCP\IL10N;
use Psr\Log\LoggerInterface as ILogger;
use OCP\IConfig as CloudConfig;
use OCP\Files\IAppData;
use OCP\Files\Mount\IMountManager;
use OCP\Files\IRootFolder;
use OCP\Files\FileInfo;
use OCP\Files\Folder;
use OCP\Files\NotFoundException as FileNotFoundException;

use OCA\CAFEVDB\Common\Util;
use OCA\CAFEVDB\Common\Uuid;
use OCA\CAFEVDB\Constants;

/**
 * Disclose the app-storage folder as ordinary file-system Folder instance
 * instead of only as \OCP\Files\SimpleFS\ISimpleRoot
 */
class AppStorageDisclosure
{
  use \OCA\CAFEVDB\Toolkit\Traits\LoggerTrait;

  public const PATH_SEP = Constants::PATH_SEP;

  public const UPLOAD_FOLDER = 'uploads';
  public const DRAFTS_FOLDER = 'drafts';

  private const APP_DATA_PREFIX = 'appdata_';

  /** @var string */
  private $appName;

  /** @var IAppData */
  private $appData;

  /** @var IRootFolder */
  private $rootFolder;

  /** @var IMountManager */
  private $mountManager;

  /** @var CloudConfig */
  private $cloudConfig;

  // phpcs:ignore Squiz.Commenting.FunctionComment.Missing
  public function __construct(
    string $appName,
    IAppData $appData,
    IRootFolder $rootFolder,
    IMountManager $mountManager,
    CloudConfig $cloudConfig,
    ILogger $logger,
    IL10N $l10n,
  ) {
    $this->appName = $appName;
    $this->appData = $appData;
    $this->rootFolder = $rootFolder;
    $this->mountManager = $mountManager;
    $this->cloudConfig = $cloudConfig;
    $this->logger = $logger;
    $this->l = $l10n;
  }
  // phpcs:enable

  /**
   * @return string Determine the name of the app-data folder.
   *
   * @todo This does not use internal APIs, but knowledge about internal
   * details of Nextcloud which might change in the future.
   */
  private function getAppDataFolderName(): string
  {
    $instanceId = $this->cloudConfig->getSystemValue('instanceid', null);
    if ($instanceId === null) {
      // can this be at this point?
      throw new RuntimeException($this->l->t('Cloud installation problem: instance id is missing.'));
    }
    return self::APP_DATA_PREFIX . $instanceId;
  }

  /**
   * @return Folder The app root folder.
   */
  private function getAppRootFolder():Folder
  {
    $path = $this->getAppDataFolderName();
    $mount = $this->mountManager->find($path);
    $storage = $mount->getStorage();
    $internalPath = $mount->getInternalPath($path);
    if ($storage->file_exists($internalPath)) {
      $folder = $this->rootFolder->get($path);
    } else {
      throw new RuntimeException($this->l->t('App-data root-folder does not exist.'));
    }
    return $folder;
  }

  /**
   * Obtain an app-data folder as ordinary Filesystem Folder instance instead
   * of \OCP\Files\SimpleFS\ISimpleFolder.
   *
   * @param string $path Path relative to the app-data directory for this app.
   *
   * @return Folder Filesystem folder instance pointing to $path.
   */
  public function getFilesystemFolder(string $path = ''):Folder
  {
    $rootFolder = $this->getAppRootFolder();
    /** @var Folder $appFolder */
    $appFolder = $rootFolder->get($this->appName);
    if ($path == '') {
      return $appFolder;
    }
    try {
      $folder = $appFolder->get($path);
    } catch (FileNotFoundException $e) {
      $this->logInfo('NOT FOUND EXCEPTION');
      // fallthrough
    } catch (Throwable $t) {
      $this->logInfo('OTHER ERROR ' . get_class($t));
    }
    if (empty($folder)) {
      $this->logInfo('TRY CREATE ' . $path);
      $folder = $appFolder->newFolder($path);
      if (empty($folder)) {
        throw new RuntimeException($this->l->t(
          'App-storage sub-folder "%s" oes not exist and cannot be created.', $path
        ));
      }
    }
    return $folder;
  }
}
