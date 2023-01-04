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

use InvalidArgumentException;

use OCP\IL10N;
use Psr\Log\LoggerInterface as ILogger;
use OCP\Files\IAppData;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\Files\SimpleFS\ISimpleFile;
use OCP\Files\SimpleFS\ISimpleFolder;
use Spatie\TemporaryDirectory\TemporaryDirectory; // for ordinary file-system temporaries

use OCA\CAFEVDB\Common\Util;
use OCA\CAFEVDB\Common\Uuid;

/**
 * App-storage wrapper in order to have a common interface with the user-storage.
 *
 * @todo Maybe this class should not exists. Check whether its methods are used.
 */
class AppStorage
{
  use \OCA\RotDrop\Toolkit\Traits\LoggerTrait;

  const PATH_SEP = '/';
  public const UPLOAD_FOLDER = 'uploads';
  public const DRAFTS_FOLDER = 'drafts';

  /** @var IAppData */
  private $appData;

  /** @var IFolder */
  private $uploadFolder;

  /** @var IFolder */
  private $draftsFolder;

  // phpcs:ignore Squiz.Commenting.FunctionComment.Missing
  public function __construct(
    IAppData $appData,
    ILogger $logger,
    IL10N $l10n,
  ) {
    $this->appData = $appData;
    $this->logger = $logger;
    $this->l = $l10n;
    try {
      $this->uploadFolder = $this->ensureFolder(self::UPLOAD_FOLDER);
      $this->draftsFolder = $this->ensureFolder(self::DRAFTS_FOLDER);
    } catch (\Throwable $t) {
      $this->logException($t);
    }
  }
  // phpcs:enable

  /**
   * Ensure a folder exists withtout throwing an exception.
   *
   * @param string $name Folder name.
   *
   * @return ISimpleFolder
   */
  public function ensureFolder(string $name):ISimpleFolder
  {
    try {
      $folder = $this->getFolder($name);
    } catch (NotFoundException $e) {
      $folder = $this->newFolder($name);
    }
    return $folder;
  }

  /**
   * Generate a random file.
   *
   * @param string $folderName The name of the parent-folder of the new file.
   * in.
   *
   * @return ISimpleFile
   */
  public function newTemporaryFile(string $folderName):ISimpleFile
  {
    $name = Uuid::create();
    switch ($folderName) {
      case self::UPLOAD_FOLDER:
        $folder = $this->uploadFolder;
        break;
      case self::DRAFTS_FOLDER:
        $folder = $this->draftsFolder;
        break;
      default:
        $folder = $this->ensureFolder($folderName);
        break;
    }
    return $folder->newFile($name);
  }

  /**
   * @param string $dirName
   *
   * @param null|string $fileName
   *
   * @return ISimpleFile
   */
  public function getFile(string $dirName, ?string $fileName = null):ISimpleFile
  {
    if (empty($fileName)) {
      $components = array_filter(Util::explode(self::PATH_SEP, $dirName));
      if (count($components) != 2) {
        throw new InvalidArgumentException($this->l->t('Path "%s" must consist of exactly one directory and exactly one file component.'));
      }
      list($dirName, $fileName) = $components;
    }
    /** @var ISimpleFolder $folder */
    $folder = $this->getFolder($dirName);
    return $folder->getFile($fileName);
  }

  /**
   * @param string $dirName
   *
   * @param null|string $fileName
   *
   * @return bool
   */
  public function fileExists(string $dirName, ?string $fileName = null):bool
  {
    if (empty($fileName)) {
      $components = array_filter(Util::explode(self::PATH_SEP, $dirName));
      if (count($components) != 2) {
        throw new InvalidArgumentException($this->l->t('Path "%s" must consist of exactly one directory and exactly one file component.'));
      }
      list($dirName, $fileName) = $components;
    }
    /** @var ISimpleFolder $folder */
    $folder = $this->getFolder($dirName);
    return $folder->fileExists($fileName);
  }

  /**
   * @param string $dirName
   *
   * @param string $fileName
   *
   * @return bool
   */
  public function fileExistsInFolder(string $dirName, string $fileName):bool
  {
    $components = array_filter(Util::explode(self::PATH_SEP, $fileName));
    if (count($components) == 2) {
      if ($components[0] != $dirName) {
        return false;
      }
      $fileName = $components[1];
    }
    return $this->fileExists($dirName, $fileName);
  }

  /**
   * @param string $fileName
   *
   * @return bool
   */
  public function uploadExists(string $fileName):bool
  {
    return $this->fileExistsInFolder(self::UPLOAD_FOLDER, $fileName);
  }

  /**
   * @param string $fileName
   *
   * @return bool
   */
  public function draftExists(string $fileName):bool
  {
    return $this->fileExistsInFolder(self::DRAFTS_FOLDER, $fileName);
  }

  /** @return ISimpleFile */
  public function newUploadFile():ISimpleFile
  {
    $name = Uuid::create();
    return $this->uploadFolder->newFile($name);
  }

  /**
   * @param string $name
   *
   * @return ISimpleFile
   */
  public function getUploadFile(string $name):ISimpleFile
  {
    return $this->uploadFolder->getFile($name);
  }

  /** @return ISimpleFile */
  public function newDraftsFile():ISimpleFile
  {
    $name = Uuid::create();
    return $this->draftsFolder->newFile($name);
  }

  /**
   * @param string $name
   *
   * @return ISimpleFile
   */
  public function getDraftsFile(string $name):ISimpleFile
  {
    return $this->draftsFolder->getFile($name);
  }

  /**
   * Remove all files in $folder older than $age according to their
   * mtime.
   *
   * @param ISimpleFolder $folder
   *
   * @param int $age Unix time-stamp.
   *
   * @return void
   */
  public function purgeFolder(ISimpleFolder $folder, int $age):void
  {
    $now = time();
    /** @var ISimpleFile $file */
    foreach ($folder->getDirectoryListing() as $file) {
      $mtime = $file->getMTime();
      if ($now - $mtime > $age) {
        $file->delete();
      }
    }
  }

  /**
   * Move the contents of a "real" file to the given app-folder.
   *
   * @param string $srcFile
   *
   * @param ISimpleFile $dstFile
   *
   * @return ISimpleFile
   */
  public function moveFileSystemFile(string $srcFile, ISimpleFile $dstFile):ISimpleFile
  {
    $dstFile->putContent(file_get_contents($srcFile));
    unlink($srcFile);
    return $dstFile;
  }

  /**
   * Copy the contents of a "real" file to the given app-folder.
   *
   * @param string $srcFile
   *
   * @param ISimpleFile $dstFile
   *
   * @return ISimpleFile
   */
  public function copyFileSystemFile(string $srcFile, ISimpleFile $dstFile):ISimpleFile
  {
    $dstFile->putContent(file_get_contents($srcFile));
    return $dstFile;
  }

  /**
   * Get the folder with name $name
   *
   * @param string $name
   *
   * @return ISimpleFolder
   *
   * @throws NotFoundException
   * @throws \RuntimeException
   */
  public function getFolder(string $name): ISimpleFolder
  {
    return $this->appData->getFolder($name);
  }

  /**
   * Get all the Folders
   *
   * @return ISimpleFolder[]
   *
   * @throws NotFoundException
   * @throws \RuntimeException
   */
  public function getDirectoryListing():array
  {
    return $this->appData->getDirectoryListing();
  }

  /**
   * Create a new folder named $name
   *
   * @param string $name
   *
   * @return ISimpleFolder
   *
   * @throws NotPermittedException
   * @throws \RuntimeException
   */
  public function newFolder(string $name):ISimpleFolder
  {
    return $this->appData->newFolder($name);
  }

  /**
   * Return a system-directory path to temporary storage.
   *
   * @return string
   */
  public function getTemporaryDirectory():string
  {
    return sys_get_temp_dir();
  }
}
