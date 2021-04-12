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
use OCP\Files\IRootFolder;
use OCP\Files\Node;
use OCP\Files\Folder;
use OCP\Files\FileInfo;

/**
 * Some tweaks to for the user-folder stuff.
 */
class UserStorage
{
  use \OCA\CAFEVDB\Traits\LoggerTrait;

  const PATH_SEP = '/';
  const CACHE_DIRECTORY = self::PATH_SEP.'cache';

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
  public function get(?string $path):?Node
  {
    try {
      return empty($path) ? $this->userFolder : $this->userFolder->get($path);
    } catch (\OCP\Files\NotFoundException $t) {
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

  /**
   * Rename $oldPath to $newPath which are interpreted as paths
   * relative to the user's folder.
   */
  public function rename(string $oldPath, string $newPath)
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
   * Make sure the all components of the given array exists where each
   * following component is chained to the previous one. So the final
   * path to construct is
   * ```
   * '/'.implode('/', $chain)
   * ```
   *
   * @param array $chain Path components
   *
   * @return \OCP\Files\Folder The folder object pointing to the
   * resulting path.
   *
   * @throws \Exception
   */
  public function ensureFolderChain(array $chain)
  {
    $path = '';
    foreach ($chain as $component) {
      $path .= self::PATH_SEP.$component;
      $node = $this->ensureFolder($path);
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
    return $this->ensureFolder(self::CACHE_DIRECTORY.self::PATH_SEP.$subDirectory);
  }

  /**
   * Put file content by path. Create $path if it does not exists,
   * otherwise replace its contents by the given data.
   */
  public function putContent($path, $content)
  {
    try {
      try {
        $file = $this->userFolder->get($path);
        $file->putContent($content);
      } catch(\OCP\Files\NotFoundException $e) {
        $this->userFolder->newFile($path, $content);
      }
    } catch (\Throwable $t) {
      throw new \RuntimeException($this->l->t('Unable to set content of file "%s".', $path), $t->getCode(), $t);
    }
  }

  /**
   * Get file content by path.
   */
  public function getContent($path)
  {
    // check if file exists and read from it if possible
    try {
      $file = $userFolder->get($path);
      if($file instanceof \OCP\Files\File) {
        return $file->getContent();
      } else {
        throw new \RuntimeException($this->l->t('Cannot read from folder "%s".', $path));
      }
    } catch(\Throwable $t) {
      throw new \RuntimeException($this->l->t('Unable to get content of file "%s".', $path), $t->getCode() , $t);
    }
  }

  /**
   * Generate a DAV download-link for the given node or path-name.
   *
   * The resulting node should look like
   *
   * https://anaxagoras.home.claus-justus-heine.de/nextcloud-git/remote.php/webdav/camerata/projects/2020/Test2020/participants/claus-justus.heine/DateiUpload-ClausJustusHeine.zip?downloadStartSecret=uwq0q4j24sb
   *
   * @param string|\OCP\Files\Node $pathOrNode The file-system path or node.
   *
   * @return null|string The download URL or null.
   */
  public function getDownloadLink($pathOrNode):?string
  {
    if (is_string($pathOrNode)) {
      $node = $this->userFolder->get($pathOrNode);
    } else if ($pathOrNode instanceof \OCP\Files\Node) {
      $node = $pathOrNode;
    } else {
      throw new \InvalidArgumentException($this->l->t('Argument must be a valid path or already a file-system node.'));
    }
    $this->logDebug('NODE '.$node->getPath().' '.$node->getInternalPath());

    // Internal path is mount-point specific and in particular
    // relative to the share-root if sharing a folder. getPath()
    // containers the /USERID/files/ prefix. We use that for the
    // moment ...

    $webDAVRoot = \OCP\Util::linkToRemote('webdav/');
    $nodePath = substr(strchr($node->getPath(), 'files/'), strlen('files/'));

    return $webDAVRoot.$nodePath;
  }

  /**
   * Generate a link to the files-app pointing to the parent folder if
   * $pathOrNode is a file, or the node itself if it is a folder.
   *
   * https://anaxagoras.home.claus-justus-heine.de/nextcloud-git/index.php/apps/files?dir=/camerata/projects/2020/Test2020/participants/claus-justus.heine
   *
   * @param string|\OCP\Files\Node $pathOrNode The file-system path or node.
   *
   * @return string|null URL to the files app.
   */
  public function getFilesAppLink($pathOrNode):?string
  {
    if (is_string($pathOrNode)) {
      $node = $this->userFolder->get($pathOrNode);
    } else if ($pathOrNode instanceof \OCP\Files\Node) {
      $node = $pathOrNode;
    } else {
      throw new \InvalidArgumentException($this->l->t('Argument must be a valid path or already a file-system node.'));
    }
    if ($node->getType() != FileInfo::TYPE_FOLDER) {
      $node = $node->getParent();
    }

    $nodePath = substr(strchr($node->getPath(), '/files/'), strlen('/files'));

    $filesUrl = \OCP\Util::linkToAbsolute('files', '', [ 'dir' => $nodePath ]);

    $this->logInfo('FILESURL '.$filesUrl);

    return $filesUrl;
  }


}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
