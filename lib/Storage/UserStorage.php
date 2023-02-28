<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2011-2014, 2016, 2020, 2021, 2022, 2023, Claus-Justus Heine <himself@claus-justus-heine.de>
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

use Throwable;
use RuntimeException;
use InvalidArgumentException;
use ZipStream\ZipStream;
use ZipStream\Option\Archive as ArchiveOptions;

use OC\Files\Storage\Wrapper\Wrapper as WrapperStorage;

use OCP\IUser;
use OCP\IUserSession;
use OCP\IL10N;
use Psr\Log\LoggerInterface as ILogger;
use OCP\IURLGenerator;
use OCP\AppFramework\IAppContainer;
use OCP\Files\IRootFolder;
use OCP\Files\Node;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\FileInfo;
use OCA\Files_Trashbin\Trash\ITrashManager;
use OCA\Files_Trashbin\Trash\ITrashItem;
use OCP\Files\NotFoundException as FileNotFoundException;

use OCA\CAFEVDB\Constants;
use OCA\CAFEVDB\Common\Util;
use OCA\CAFEVDB\Exceptions\Exception;
use OCA\CAFEVDB\Storage\Database\Storage as DatabaseStorage;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;

/**
 * Some tweaks to for the user-folder stuff.
 */
class UserStorage
{
  use \OCA\CAFEVDB\Toolkit\Traits\LoggerTrait;

  const PATH_SEP = Constants::PATH_SEP;
  const CACHE_DIRECTORY = self::PATH_SEP.'cache';

  /** @var IUser */
  protected $user;

  /** @var IAppContainer */
  private $appContainer;

  /** @var Folder */
  protected $rootFolder;

  /** @var Folder */
  protected $userFolder;

  /** @var array */
  private $nodeCache = [];

  /** {@inheritdoc} */
  public function __construct(
    IUserSession $userSession,
    IAppContainer $appContainer,
    IRootFolder $rootFolder,
    ILogger $logger,
    IL10N $l10n
  ) {
    $this->appContainer = $appContainer;
    $this->rootFolder = $rootFolder;
    $this->logger = $logger;
    $this->l = $l10n;
    $this->setUser($userSession->getUser());
  }

  /**
   * @param IUser $user The current user.
   *
   * @return UserStorage
   */
  public function setUser(?IUser $user):UserStorage
  {
    $this->user = $user;
    if (!empty($this->user)) {
      $this->userFolder = $this->rootFolder->getUserFolder($this->user->getUID());
    } else {
      $this->logInfo('NO USER, NO USER FOLDER');
    }
    return $this;
  }

  /**
   * @return null|IUser The current user
   */
  public function user():?IUser
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

  /**
   * Return the path relative to user-folder
   *
   * @param Node $node The cloud FS node.
   *
   * @return null|string
   */
  public function getUserPath(Node $node):?string
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
   * @param null|string $type Only consider nodes of this type, either
   * FileInfo::TYPE_FOLDER or FileInfo::TYPE_FILE.
   *
   * @param bool $useCache Use any previously cached value.
   *
   * @param bool $throw Throw an exception instead of returning null if the
   * file is not found.
   *
   * @return null|Node The user-folder or the given sub-folder
   */
  public function get(?string $path, ?string $type = null, bool $useCache = false, bool $throw = false):?Node
  {
    try {
      if ($useCache && !empty($this->nodeCache[$path])) {
        $node = $this->nodeCache[$path];
      } else {
        $node = empty($path) ? $this->userFolder : $this->userFolder->get($path);
        $this->nodeCache[$path] = $node;
      }
      if (!empty($type) && $node->getType() != $type) {
        if ($throw) {
          throw new FileNotFoundException($this->l->t('File-system node "%1$s" has been found but has not the required type "%2$s".', [
            $path, $type,
          ]));
        }
        return null;
      }
      if (empty($node) && $throw) {
        throw new FileNotFoundException($this->l->t('File-system node "%s" could not been found.', $path));
      }
      return $node;
    } catch (FileNotFoundException $t) {
      if ($throw) {
        throw $t;
      }
      return null;
    }
  }

  /**
   * @param string|null $path Path to lookup.
   *
   * @param bool $useCache Use any previously cached value.
   *
   * @return null|File The user-folder or the given sub-folder
   */
  public function getFile(string $path, bool $useCache = false):?File
  {
    return $this->get($path, FileInfo::TYPE_FILE, $useCache);
  }

  /**
   * @param string $path Path to lookup.
   *
   * @param bool $useCache Use any previously cached value.
   *
   * @return null|Folder
   */
  public function getFolder(string $path, bool $useCache = false):?Folder
  {
    return $this->get($path, FileInfo::TYPE_FOLDER, $useCache);
  }

  /**
   * Walk the given $pathOrFolder and apply the callable to each found node.
   *
   * @param string|Folder $pathOrFolder Folder-path or \OCP\Files\Folder instance.
   *
   * @param null|callable $callback The callback receives two arguments, the
   * current file-system node and the recursion depth.
   *
   * @param int $depth Internal recursion depth parameters. The $callback
   * receives it as second argument.
   *
   * @return int The number of plain files found during the walk.
   */
  public function folderWalk(mixed $pathOrFolder, ?callable $callback = null, int $depth = 0):int
  {
    /** @var \OCP\Files\File $node */
    if (!($pathOrFolder instanceof Folder)) {
      $folder = $this->getFolder($pathOrFolder);
    } else {
      $folder = $pathOrFolder;
    }

    if (empty($folder)) {
      return 0;
    }

    if (!empty($callback)) {
      $callback($folder, $depth);
    }
    ++$depth;

    $numberOfFiles = 0;
    $folderContents = $folder->getDirectoryListing();
    /** @var Node $node */
    foreach ($folderContents as $node) {
      if ($node->getType() == FileInfo::TYPE_FILE) {
        if (!empty($callback)) {
          $callback($node, $depth);
        }
        ++$numberOfFiles;
      } else {
        $numberOfFiles += $this->folderWalk($node, $callback, $depth);
      }
    }
    return $numberOfFiles;
  }

  /**
   * Recursively add all files in all sub-directories to the zip archive.
   *
   * @param Folder $folder The folder to archive.
   *
   * @param int $parentsToStrip How many path components to strip from the
   * path-names in order to form the archive name.
   *
   * @param ZipStream $zipStream Archive creation backend.
   *
   * @return void
   */
  private function archiveFolderRecursively(Folder $folder, int $parentsToStrip, ZipStream $zipStream):void
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
   *
   * @param string $format Defaults to 'zip' and only 'zip' is supported ATM.
   *
   * @return null|string
   */
  public function getFolderArchive($pathOrFolder, int $parentsToStrip = 0, string $format = 'zip'):?string
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

    $zipStream = new ZipStream(opt: $zipStreamOptions);

    $this->archiveFolderRecursively($folder, $parentsToStrip, $zipStream);

    $zipStream->finish();
    rewind($dataStream);
    $data = stream_get_contents($dataStream);
    fclose($dataStream);

    return $data;
  }

  /**
   * @param string|array $first Paths to cat. May be an array.
   *
   * @param null|string $second Optional second component.
   *
   * @return string The concatenated paths.
   */
  public static function pathCat($first, ?string $second = null):string
  {
    if (is_array($first)) {
      $first = implode(self::PATH_SEP, $first);
    }
    $first = trim($first, self::PATH_SEP);
    if (is_array($second)) {
      $second = implode(self::PATH_SEP, $second);
    }
    $second = trim($second, self::PATH_SEP);
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
   *
   * @param string $path The path to work on.
   *
   * @param int $depth How many components to strip from the start.
   *
   * @return string
   */
  public static function stripParents(string $path, int $depth):string
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
   * @param string $path The path to the file to delete.
   *
   * @return void
   */
  public function delete(string $path):void
  {
    try {
      $this->userFolder->get($path)->delete();
      unset($this->nodeCache[$path]);
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
   * @return bool
   *
   * @bug This method uses internal APIs.
   */
  public function restore(string $path, ?array $timeInterval = null, bool $overwriteExisting = false):bool
  {
    /** @var ITrashManager $trashManager */
    $trashManager = $this->appContainer->get(ITrashManager::class);

    if (empty($trashManager)) {
      throw new RuntimeException($this->l->t('Unable to restore "%s": TrashManager cannot be loaded', $path));
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
   *
   * @param string $oldPath The path to move.
   *
   * @param string $newPath The path to move to.
   *
   * @return void
   *
   * @throws Exception If renaming fails.
   */
  public function rename(string $oldPath, string $newPath):void
  {
    try {
      $newFullPath = $this->userFolder->getFullPath($newPath);
      $newNode = $this->userFolder->get($oldPath)->move($newFullPath);
      unset($this->nodeCache[$oldPath]);
      $this->nodeCache[$newPath] = $newNode;
    } catch (Throwable $t) {
      throw new Exception($this->l->t('Rename of "%s" to "%s" failed.', [ $oldPath, $newPath ]), $t->getCode(), $t);
    }
  }

  /**
   * Rename $oldPath to $newPath which are interpreted as paths
   * relative to the user's folder.
   *
   * @param string $oldPath The source path.
   *
   * @param string $newPath The destination path.
   *
   * @return void
   *
   * @throws Exception If something goes wrong.
   */
  public function copy(string $oldPath, string $newPath):void
  {
    try {
      $newPath = $this->userFolder->getFullPath($newPath);
      $newNode = $this->userFolder->get($oldPath)->copy($newPath);
      $this->nodeCache[$newPath] = $newNode;
    } catch (Throwable $t) {
      throw new Exception($this->l->t('Copy of "%s" to "%s" failed.', [ $oldPath, $newPath ]), $t->getCode(), $t);
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
   * @return Folder The folder object pointing to the given
   * path.
   *
   * @throws FileNotFoundException If something goes wrong.
   */
  public function ensureFolder(string $path)
  {
    try {
      $node = $this->userFolder->get($path);
      if ($node->getType() != FileInfo::TYPE_FOLDER) {
        $node->delete();
        unset($this->nodeCache[$path]);
        throw new FileNotFoundException($this->l->t("Deleted non-directory %s", [$path]));
      }
    } catch (FileNotFoundException $e) {
      $node = $this->userFolder->newFolder($path);
    }
    $this->nodeCache[$path] = $node;
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
   * @param array $chain Path components.
   *
   * @return Folder The folder object pointing to the
   * resulting path.
   *
   * @throws Exceptionn If something goes wrong.
   */
  public function ensureFolderChain(array $chain):Folder
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
   *
   * @param string $path The file path.
   *
   * @param string $content The file content.
   *
   * @return File
   */
  public function putContent(string $path, string $content):File
  {
    try {
      try {
        $file = $this->userFolder->get($path);
        $file->putContent($content);
        $this->nodeCache[$path] = $file;
      } catch (\OCP\Files\NotFoundException $e) {
        $this->ensureFolder(dirname($path));
        $file = $this->userFolder->newFile($path, $content);
      }
    } catch (Throwable $t) {
      throw new RuntimeException($this->l->t('Unable to set content of file "%s".', $path), $t->getCode(), $t);
    }
    return $file;
  }

  /**
   * Get file content by path.
   *
   * @param string $path The file path.
   *
   * @return The file content.
   */
  public function getContent(string $path):string
  {
    // check if file exists and read from it if possible
    try {
      $file = $this->userFolder->get($path);
      if ($file instanceof File) {
        $this->nodeCache[$path] = $file;
        return $file->getContent();
      } else {
        throw new RuntimeException($this->l->t('Cannot read from folder "%s".', $path));
      }
    } catch (Throwable $t) {
      throw new RuntimeException($this->l->t('Unable to get content of file "%s".', $path), $t->getCode(), $t);
    }
  }

  /**
   * Return the underline storage entity if the cloud-path refers to a
   * db-backed cloud-file.
   *
   * @param string $cloudPath The path in the cloud FS.
   *
   * @return null|Entities\DatabaseStorageDirEntry
   */
  public function getDatabaseFile(string $cloudPath):?Entities\DatabaseStorageDirEntry
  {
    $cloudFile = $this->get($cloudPath);
    if (empty($cloudFile)) {
      return null;
    }
    $storage = $cloudFile->getStorage();

    while ($storage instanceof WrapperStorage) {
      /** @var WrapperStorage $storage */
      $storage = $storage->getWrapperStorage();
    }

    if (!($storage instanceof DatabaseStorage)) {
      return null;
    }

    /** @var DatabaseStorage $storage */
    $dirEntry = $storage->fileFromFileName($cloudFile->getInternalPath());

    if ($dirEntry instanceof Entities\DatabaseStorageDirEntry) {
      return $dirEntry;
    }

    return null;
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
   *
   * @throws FileNotFoundException
   */
  public function getDownloadLink($pathOrNode):?string
  {
    if (is_string($pathOrNode)) {
      $node = $this->get($pathOrNode, useCache: true, throw: true);
    } elseif ($pathOrNode instanceof \OCP\Files\Node) {
      $node = $pathOrNode;
    } else {
      throw new InvalidArgumentException($this->l->t('Argument must be a valid path or already a file-system node.'));
    }
    // $this->logDebug('NODE '.$node->getPath().' '.$node->getInternalPath());

    // Internal path is mount-point specific and in particular
    // relative to the share-root if sharing a folder. getPath()
    // contains the /USERID/files/ prefix. We use that for the
    // moment ...
    $nodePath = substr(strchr($node->getPath(), 'files/'), strlen('files/'));

    if ($node->getType() == FileInfo::TYPE_FILE) {
      $webDAVRoot = \OCP\Util::linkToRemote('webdav/');
      return $webDAVRoot.$nodePath;
    } elseif ($node->getType() == FileInfo::TYPE_FOLDER) {
      $parent = $node->getParent();
      $parentPath = substr(strchr($parent->getPath(), 'files/'), strlen('files/'));
      return \OCP\Util::linkToAbsolute('files', 'ajax/download.php', [
        'dir' => $parentPath,
        'files' => $node->getName(),
      ]);
    } else {
      throw new InvalidArgumentException($this->l->t('Unknown file type "%s" for download file "%s".', [ $node->getType(), $node->getName() ]));
    }
  }

  /**
   * Generate a link to the files-app pointing to the parent folder if
   * $pathOrNode is a file, or the node itself if it is a folder.
   *
   * Example URL generated is
   *
   * https://foo.bar.com/nextcloud/index.php/apps/files?dir=/camerata/projects/2020/Test2020/participants/claus-justus.heine
   *
   * @param string|Node $pathOrNode The file-system path or node.
   *
   * @param bool $subDir If the $pathOrNode refers to a folder then
   * open this folder. Otherwise open the parent.
   *
   * @return string|null URL to the files app.
   */
  public function getFilesAppLink($pathOrNode, bool $subDir = false):?string
  {
    if (is_string($pathOrNode)) {
      try {
        $node = $this->get($pathOrNode, useCache: true, throw: true);
      } catch (Throwable $t) {
        throw new FileNotFoundException($this->l->t('Cannot find directory entry "%s".', $pathOrNode), 0, $t);
      }
    } elseif ($pathOrNode instanceof \OCP\Files\Node) {
      $node = $pathOrNode;
    } else {
      throw new InvalidArgumentException($this->l->t('Argument must be a valid path or already a file-system node.'));
    }
    $this->nodes[$pathOrNode] = $node;

    $nodePath = $node->getPath();
    if ($subDir === false || $node->getType() != FileInfo::TYPE_FOLDER) {
      // $node = $node->getParent();
      $nodePath = dirname($nodePath);
    }

    $nodePath = substr(strchr($nodePath, '/files/'), strlen('/files'));

    $urlGenerator = $this->appContainer->get(IURLGenerator::class);
    $filesUrl = $urlGenerator->linkToRoute('files.view.index', [ 'dir' => $nodePath ]);

    return $filesUrl;
  }

  /**
   * Create a data-uri from the given file.
   *
   * @param string|\OCP\Files\File $pathOrNode Either a path or a file-system node.
   *
   * @return null|string
   */
  public function createDataUri($pathOrNode):?string
  {
    /** @var \OCP\Files\File $node */
    if (is_string($pathOrNode)) {
      $node = $this->userFolder->get($pathOrNode);
    } elseif ($pathOrNode instanceof \OCP\Files\Node) {
      $node = $pathOrNode;
    } else {
      throw new InvalidArgumentException($this->l->t('Argument must be a valid path or already a file-system node.'));
    }
    $dataUri = 'data:'.$node->getMimeType().';base64,' . base64_encode($node->getContent());
    return $dataUri;
  }
}
