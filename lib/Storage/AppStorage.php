<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2014, 2016, 2020, 2021, Claus-Justus Heine <himself@claus-justus-heine.de>
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

namespace OCA\CAFEVDB\Storage;

use OCP\IL10N;
use OCP\ILogger;
use OCP\Files\IAppData;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\Files\SimpleFS\ISimpleFile;
use OCP\Files\SimpleFS\ISimpleFolder;
use Spatie\TemporaryDirectory\TemporaryDirectory; // for ordinary file-system temporaries

use OCA\CAFEVDB\Common\Uuid;

class AppStorage
{
  use \OCA\CAFEVDB\Traits\LoggerTrait;

  const PATH_SEP = '/';
  public const UPLOAD_FOLDER = 'uploads';
  public const DRAFTS_FOLDER = 'drafts';

  /** @var IAppData */
  private $appData;

  /** @var IFolder */
  private $uploadFolder;

  /** @var IFolder */
  private $draftsFolder;

  public function __construct(
    IAppData $appData
    , ILogger $logger
    , IL10N $l10n
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

  public function ensureFolder($name): ISimpleFolder
  {
    try {
      $folder = $this->getFolder($name);
    } catch (NotFoundException $e) {
      $folder = $this->newFolder($name);
    }
    return $folder;
  }

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

  public function newUploadFile():ISimpleFile
  {
    $name = Uuid::create();
    return $this->uploadFolder->newFile($name);
  }

  public function newDraftsFile():ISimpleFile
  {
    $name = Uuid::create();
    return $this->draftsFolder->newFile($name);
  }

  /**
   * Remove all files in $folder older than $age according to their
   * mtime.
   */
  public function purgeFolder(ISimpleFolder $folder, $age)
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
   */
  public function moveFileSystemFile(string $srcFile, ISimpleFile $dstFile):ISimpleFile
  {
    $dstFile->putContent(file_get_contents($srcFile));
    unlink($srcFile);
    return $dstFile;
  }

  /**
   * Move the contents of a "real" file to the given app-folder.
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
   * @return ISimpleFolder
   * @throws NotFoundException
   * @throws \RuntimeException
   * @since 11.0.0
   */
  public function getFolder(string $name): ISimpleFolder
  {
    return $this->appData->getFolder($name);
  }

  /**
   * Get all the Folders
   *
   * @return ISimpleFolder[]
   * @throws NotFoundException
   * @throws \RuntimeException
   * @since 11.0.0
   */
  public function getDirectoryListing(): array
  {
    return $this->appData->getDirectoryListing();
  }

  /**
   * Create a new folder named $name
   *
   * @param string $name
   * @return ISimpleFolder
   * @throws NotPermittedException
   * @throws \RuntimeException
   * @since 11.0.0
   */
  public function newFolder(string $name): ISimpleFolder
  {
    return $this->appData->newFolder($name);
  }

  /**
   * Return a system-directory path to temporary storage.
   */
  public function getTemporaryDirectory()
  {
    return sys_get_temp_dir();
  }

}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
