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

use ZipStream\ZipStream;
use ZipStream\Option\Archive as ArchiveOptions;

use OCP\IUser;
use OCP\IUserSession;
use OCP\IL10N;
use OCP\ILogger;
use OCP\AppFramework\IAppContainer;
use OCP\Files\IRootFolder;
use OCP\Files\Node;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\FileInfo;
use OCA\Files_Trashbin\Trash\ITrashManager;
use OCA\Files_Trashbin\Trash\ITrashItem;

use OCA\CAFEVDB\Common\Util;

/**
 * Some tweaks to for the user-folder stuff.
 */
class UserStorage
{
  use \OCA\CAFEVDB\Traits\LoggerTrait;

  const PATH_SEP = '/';
  const CACHE_DIRECTORY = self::PATH_SEP.'cache';

  /** @var IUser */
  protected $user;

  /** @var IAppContainer */
  private $appContainer;

  /** @var Folder */
  protected $rootFolder;

  /** @var Folder */
  protected $userFolder;

  public function __construct(
    IUserSession $userSession
    , IAppContainer $appContainer
    , IRootFolder $rootFolder
    , ILogger $logger
    , IL10N $l10n
  ) {
    $this->user = $userSession->getUser();
    $this->appContainer = $appContainer;
    $this->rootFolder = $rootFolder;
    $this->logger = $logger;
    $this->l = $l10n;
    if (!empty($this->user)) {
      $this->userFolder = $this->rootFolder->getUserFolder($this->user->getUID());
    }
  }

  /**
   * @return IUser The current user
   */
  public function user():IUser
  {
    return $this->user;
  }

  /**
   * @return string The current user-id
   */
  public function userId():string
  {
    return $this->user->getUID();
  }

  /** Return the path relative to user-folder */
  public function getUserPath(Node $node)
  {
    $path = $node->getPath();
    $userFolderPath = $this->userFolder->getPath();
    if (strpos($path, $userFolderPath) !== 0) {
      return null;
    }
    return substr($path, strlen($userFolderPath));
  }

  /**
   * @param string|null $path Path to lookup.
   *
   * @return \OCP\Files\Node The user-folder or the given sub-folder
   */
  public function get(?string $path, $type = null):?Node
  {
    try {
      $node = empty($path) ? $this->userFolder : $this->userFolder->get($path);
      if (!empty($type) && $node->getType() != $type) {
        return null;
      }
      return $node;
    } catch (\OCP\Files\NotFoundException $t) {
      return null;
    }
  }

  public function getFile(string $path):?File
  {
    return $this->get($path, FileInfo::TYPE_FILE);
  }

  /**
   * @param string $path
   *
   * @return null|Folder
   */
  public function getFolder(string $path):?Folder
  {
    return $this->get($path, FileInfo::TYPE_FOLDER);
  }

  /**
   * Recursively add all files in all sub-directories to the zip archive.
   */
  private function archiveFolderRecursively(Folder $folder, int $parentsToStrip, ZipStream $zipStream)
  {
    $folderContents = $folder->getDirectoryListing();
    /** @var Node $node */
    foreach ($folderContents as $node) {
      if ($node->getType() == FileInfo::TYPE_FILE) {
        /** @var File $file */
        $file = $node;

        $filePath = self::stripParents($file->getPath(), $parentsToStrip);

        $zipStream->addFile($filePath, $file->getContent());
      } else {
        $this->archiveFolderRecursively($node, $parentsToStrip, $zipStream);
      }
    }
  }

  /**
   * Return the given $pathOrFolder as a zip archive as binary
   * string. Empty directories will be omitted.
   *
   * @param string|Folder $pathOrFolder Folder-path or \OCP\Files\Folder instance.
   *
   * @param int $parentsToStrip The number of parent folders to strip
   * inside the archive. The default is to strip nothing.
   */
  public function getFolderArchive($pathOrFolder, $parentsToStrip = 0, string $format = 'zip'):?string
  {
    /** @var \OCP\Files\File $node */
    if (!($pathOrFolder instanceof Folder)) {
      $folder = $this->getFolder($pathOrFolder);
    } else {
      $folder = $pathOrFolder;
    }

    if (empty($folder)) {
      return null;
    }

    // don't try to strip more components than the initial path-depth
    $parentsToStrip = min($parentsToStrip, count(Util::explode(self::PATH_SEP, $folder->getPath())));

    $dataStream = fopen("php://memory", 'w');
    $zipStreamOptions = new ArchiveOptions;
    $zipStreamOptions->setOutputStream($dataStream);

    $zipStream = new ZipStream($archiveName, $zipStreamOptions);

    $this->archiveFolderRecursively($folder, $parentsToStrip, $zipStream);

    $zipStream->finish();
    rewind($dataStream);
    $data = stream_get_contents($dataStream);
    fclose($dataStream);

    return $data;
  }

  static public function pathCat($first, $second = null):string
  {
    if (is_array($first)) {
      $first = implode(self::PATH_SEP, $first);
    }
    if (is_array($second)) {
      $second = implode(self::PATH_SEP, $second);
    }
    if (empty($second)) {
      return self::PATH_SEP . (string)$first;
    } else {
      return self::PATH_SEP . (string)$first . self::PATH_SEP . $second;
    }
  }

  /**
   * Strip the given number of directories from $path. Return the
   * root-path self::PATH_SEP if $depth exceeds the number of
   * path-components present.
   */
  static public function stripParents(string $path, int $depth):string
  {
    return self::PATH_SEP . implode(self::PATH_SEP, array_slice(Util::explode(self::PATH_SEP, $path), $depth));
  }

  /**
   * @return \OCP\Files\Folder The root-folder
   */
  public function getRoot():Folder
  {
    return $this->rootFolder;
  }

  /**
   * Gracefully try to delete $path. If $path does not exist, a notice
   * is written to the log but otherwise the error is ignored.
   *
   * @param string $path
   */
  public function delete($path)
  {
    try {
      $this->userFolder->get($path)->delete();
    } catch (\OCP\Files\NotFoundException $t) {
      $this->logInfo("Not deleting non-existing path $path");
    }
  }

  /**
   * Try to restore the given path from the files_trashbin app.
   *
   * @param string $path Path to restore, relative to the user's home
   * folder. It may or may not start with a slash.
   *
   * @param null|array $timeInterval If not null, restore the most recent
   * version in the given interval. Otherwise restore the most recent
   * version found in the trashbin.
   *
   * @param bool $overwriteExisting If true delete any existing
   * destination file before restoring.
   *
   * @bug This method uses internal APIs.
   */
  public function restore($path, ?array $timeInterval = null, bool $overwriteExisting = false):bool
  {
    /** @var ITrashManager $trashManager */
    $trashManager = $this->appContainer->get(ITrashManager::class);

    if (empty($trashManager)) {
      throw new \RuntimeException($this->l->t('Unable to restore "%s": TrashManager cannot be loaded', $path));
    }

    $path = trim($path, self::PATH_SEP);

    $candidate = [ 'time' => 0, 'trashItem' => null ];

    /** @var ITrashItem $trashItem */
    foreach ($trashManager->listTrashRoot($this->user()) as $trashItem) {
      $originalLocation = trim($trashItem->getOriginalLocation(), self::PATH_SEP);
      $deletedTime = $trashItem->getDeletedTime();

      if ($originalLocation == $path) {
        if (empty($timeInterval)
            || ($deletedTime >= $timeInterval[0] && $deletedTime <= $timeInterval[1])) {
          if ($candidate['time'] < $deletedTime) {
            $candidate['time'] = $deletedTime;
            $candidate['trashItem'] = $trashItem;
          }
        }
      }
    }

    if (empty($candidate['trashItem'])) {
      $this->logInfo('Unable to find trash-bin item to undelete');
      return false;
    }

    if ($overwriteExisting) {
      $this->logInfo('Try delete ' . $path);
      $this->delete($path);
    }

    $this->logInfo('Try restore ' . $candidate['trashItem']->getOriginalLocation());
    $trashManager->restoreItem($candidate['trashItem']);

    return true;
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
        $this->ensureFolder(dirname($path));
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
   * Folders seemingly are still piped through a legacy Ajax call, e.g.
   *
   * https://anaxagoras.home.claus-justus-heine.de/nextcloud-git/index.php/apps/files/ajax/download.php?dir=%2Fcamerata%2Fprojects%2F1997%2FVereinsmitglieder%2Fparticipants%2Fclaus.heine%2FVersicherungsunterlagen&files=Blah&downloadStartSecret=yur5m66p6lm
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
    // contains the /USERID/files/ prefix. We use that for the
    // moment ...
    $nodePath = substr(strchr($node->getPath(), 'files/'), strlen('files/'));

    if ($node->getType() == FileInfo::TYPE_FILE) {
      $webDAVRoot = \OCP\Util::linkToRemote('webdav/');
      return $webDAVRoot.$nodePath;
    } else if ($node->getType() == FileInfo::TYPE_FOLDER) {
      $parent = $node->getParent();
      $parentPath = substr(strchr($parent->getPath(), 'files/'), strlen('files/'));
      return \OCP\Util::linkToAbsolute('files', 'ajax/download.php', [
        'dir' => $parentPath,
        'files' => $node->getName(),
      ]);
    } else {
      throw new \InvalidArgumentException($this->l->t('Unknown file type "%s" for download file "%s".', [ $node->getType(), $node->getName() ]));
    }

  }

  /**
   * Generate a link to the files-app pointing to the parent folder if
   * $pathOrNode is a file, or the node itself if it is a folder.
   *
   * https://anaxagoras.home.claus-justus-heine.de/nextcloud-git/index.php/apps/files?dir=/camerata/projects/2020/Test2020/participants/claus-justus.heine
   *
   * @param string|\OCP\Files\Node $pathOrNode The file-system path or node.
   *
   * @param bool $subDir If the $pathOrNode refers to a folder then
   * open this folder. Otherwise open the parent.
   *
   * @return string|null URL to the files app.
   */
  public function getFilesAppLink($pathOrNode, $subDir = false):?string
  {
    if (is_string($pathOrNode)) {
      $node = $this->userFolder->get($pathOrNode);
    } else if ($pathOrNode instanceof \OCP\Files\Node) {
      $node = $pathOrNode;
    } else {
      throw new \InvalidArgumentException($this->l->t('Argument must be a valid path or already a file-system node.'));
    }
    if ($subDir === false || $node->getType() != FileInfo::TYPE_FOLDER) {
      $node = $node->getParent();
    }

    $nodePath = substr(strchr($node->getPath(), '/files/'), strlen('/files'));

    $urlGenerator = \OC::$server->getURLGenerator();
    $filesUrl = $urlGenerator->linkToRoute('files.view.index', [ 'dir' => $nodePath ]);

    return $filesUrl;
  }

  /**
   * Create a data-uri from the given file.
   *
   * @param string|\OCP\Files\File $pathOrNode.
   *
   */
  public function createDataUri($pathOrNode):?string
  {
    /** @var \OCP\Files\File $node */
    if (is_string($pathOrNode)) {
      $node = $this->userFolder->get($pathOrNode);
    } else if ($pathOrNode instanceof \OCP\Files\Node) {
      $node = $pathOrNode;
    } else {
      throw new \InvalidArgumentException($this->l->t('Argument must be a valid path or already a file-system node.'));
    }
    $dataUri = 'data:'.$node->getMimeType().';base64,' . base64_encode($node->getContent());
    return $dataUri;
  }
}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
