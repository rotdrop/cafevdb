<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2014, 2016, 2020 Claus-Justus Heine <himself@claus-justus-heine.de>
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
use OCP\Files\IRootFolder;
use OCP\Files\Folder;
use OCP\Files\FileInfo;

/**
 * Some tweaks to for the user-folder stuff.
 */
class UserStorage
{
  use \OCA\CAFEVDB\Traits\LoggerTrait;

  const CACHE_DIRECTORY = '/cache';

  /** @var string */
  protected $userId;

  /** @var Folder */
  protected $rootFolder;

  /** @var Folder */
  protected $userFolder;

  public function __construct(
    $userId
    , IRootFolder $rootFolder
    , ILogger $logger
    , IL10N $l10n
  ) {
    $this->userId = $userId;
    $this->rootFolder = $rootFolder;
    $this->logger = $logger;
    $this->l = $l10n;
    if (!empty($this->userId)) {
      $this->userFolder = $this->rootFolder->getUserFolder($this->userId);
    }
  }

  /**
   * @return string The current user-id
   */
  public function userId():string
  {
    return $this->userId;
  }


  /**
   * @param string|null $path Path to lookup.
   *
   * @return \OCP\Files\Folder The user-folder or the given sub-folder
   */
  public function get(?string $path):?Folder
  {
    try {
      return empty($path) ? $this->userFolder : $this->userFolder->get($path);
    } catch (\OCP\Files\NotFoundException $t) {
      $this->logInfo('File not found: '.$path);
      return null;
    }
  }

  /**
   * @return \OCP\Files\Folder The root-folder
   */
  public function getRoot():Folder
  {
    return $this->rootFolder;
  }

  public function delete($path)
  {
    try {
      $this->userFolder->get($path)->delete();
    } catch (\OCP\Files\NotFoundException $t) {
      $this->logInfo("Not deleting non-existing path $path");
    }
  }

  public function rename($oldPath, $newPath)
  {
    try {
      $newPath = $this->userFolder->getFullPath($newPath);
      $this->userFolder->get($oldPath)->move($newPath);
    } catch (\Throwable $t) {
      throw new \Exception($this->l->t('Rename of "%s" to "%s" failed.', [ $oldPath, $newPath ]), $t->getCode(), $t);
    }
  }

  /**
   * Make sure the given directory exists. If the given path existis
   * and is not a directory, it will be moved out of the way or
   * deleted.
   *
   * @param string $path The path the check and possibly create. The
   * path is relative to the user storage base directory and may
   * contain multiple components.
   *
   * @return \OCP\Files\Folder The folder object pointing to the given
   * path.
   *
   * @throws \Exception
   */
  public function ensureFolder(string $path)
  {
    try {
      $node = $this->userFolder->get($path);
      if ($node->getType() != FileInfo::TYPE_FOLDER) {
        $node->delete();
        throw new \OCP\Files\NotFoundException($this->l->t("Deleted non-directory %s", [$path]));
      }
    } catch (\OCP\Files\NotFoundException $e) {
      $node = $this->userFolder->newFolder($path);
    }
    return $node;
  }

  /**
   * @param string $subDirectory Folder name inside cache
   * subdirectory.
   *
   * @return \OCP\Files\Folder The requested cache folder
   */
  public function getCacheFolder(string $subDirectory)
  {
    return $this->ensureFolder(self::CACHE_DIRECTORY.'/'.$subDirectory);
  }

}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
