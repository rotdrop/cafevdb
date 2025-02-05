<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2025 Claus-Justus Heine
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

namespace OCA\CAFEVDB\Service;

use InvalidArgumentException;

use Psr\Log\LoggerInterface;

use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\Node;
use OCP\Files\NotFoundException as FileNotFoundException;
use OCP\IL10N;
use OCP\Cache\CappedMemoryCache;
use OCP\Files\IRootFolder;

use OCA\CAFEVDB\Constants;
use OCA\CAFEVDB\Storage\AppStorageDisclosure;

/**
 * Some services for icons/images.
 */
class FilesCacheService
{
  use \OCA\CAFEVDB\Toolkit\Traits\LoggerTrait;

  private const NODE_KEY = 'node';
  private const ENTRIES_KEY = 'entries';
  private const CACHE_HASH_ALGORITHM = 'sha256';
  private const FILES_CACHE_FOLDER = 'files-cache';

  public const FILE_HASH_KEY = 'hash:' . self::CACHE_HASH_ALGORITHM;

  /** @var Folder */
  private Folder $filesCacheFolder;

  /** @var array<int, Folder> */
  private CappedMemoryCache $fileCacheFolders;

  // phpcs:ignore Squiz.Commenting.FunctionComment.Missing
  public function __construct(
    protected string $userId,
    protected AppStorageDisclosure $appStorage,
    protected IL10N $l,
    protected IRootFolder $rootFolder,
    protected LoggerInterface $logger,
    protected string $appName,
  ) {
    $this->fileCacheFolders = new CappedMemoryCache(512);
  }
  // phcs:enable

  /**
   * Return the top level cache folder, create it if it does not exist.
   *
   * @return Folder
   */
  protected function ensureCacheFolder():Folder
  {
    if (empty($this->filesCacheFolder)) {
      $this->filesCacheFolder = $this->appStorage->getFilesystemFolder(self::FILES_CACHE_FOLDER);
    }
    return $this->filesCacheFolder;
  }

  /**
   * Potentially create and return a Folder-node for storing cache-data for
   * the given $node. This folder is created as sub-directory of the app-data
   * folder and will eventually be cleaned up if the original node is removed.
   *
   * @param Node $node
   *
   * @return Folder
   */
  public function getNodeFolder(Node $node):Folder
  {
    $this->ensureCacheFolder();
    $nodeId = $node->getId();
    if (!$this->fileCacheFolders[$nodeId]) {
      $this->fileCacheFolders[$nodeId] = [
        self::ENTRIES_KEY => new CappedMemoryCache(10),
      ];
      try {
        $nodeFolder = $this->filesCacheFolder->get($nodeId);
      } catch (FileNotFoundException $e) {
        $nodeFolder = $this->filesCacheFolder->newFolder($nodeId);
        if ($node instanceof File) {
          $hash = hash(self::CACHE_HASH_ALGORITHM, $node->getContent());
          $this->set($node, self::FILE_HASH_KEY, $hash);
        }
      }
      $this->fileCacheFolders[$nodeId][self::NODE_KEY] = $nodeFolder;
    }
    return $this->fileCacheFolders[$nodeId][self::NODE_KEY];
  }

  /**
   * Store the given data in the cache directory using $key as
   * file-name. Existing data will be replaced. If $data is null then an
   * existing cache entry is deleted.
   *
   * @param Node $node
   *
   * @param string $key
   *
   * @param null|string $data
   *
   * @return null|string The value of $data.
   */
  public function set(Node $node, string $key, ?string $data):?string
  {
    $keyFile = $this->getFile($node, $key);
    if (!$keyFile && $data !== null) {
      $nodeFolder = $this->getNodeFolder($node);
      $keyFile = $nodeFolder->newFile($key);
      $this->fileCacheFolders[$node->getId()][self::ENTRIES_KEY][$key] = $keyFile;
    } elseif ($data === null) {
      $this->delete($node, $key);
      return null;
    }

    $keyFile->putContent($data);

    return $data;
  }

  /**
   * Hash the content of $file and remove existing data if it does not match
   * the stored hash. The hash is initially computed when the cache folder for
   * the file is created.
   *
   * @param File $file
   *
   * @param null|string $hash Precomputed hash key, e.g. as return value from
   * a previous call.
   *
   * @return string The current hash of the file content.
   *
   * @todo There are already checksums wildly spread across the core Files
   * code. Check if we can reuse those and avoid rehashing the file data.
   */
  public function validate(File $file, ?string $hash = null):string
  {
    $cacheFolder = $this->getNodeFolder($file);
    if ($hash === null) {
      $hash = hash(self::CACHE_HASH_ALGORITHM, $file->getContent());
    }
    $cachedHash = $this->get($file, self::FILE_HASH_KEY);
    if ($hash !== $cachedHash) {
      $entries = $cacheFolder->getDirectoryListing();
      foreach ($entries as $node) {
        $node->delete();
      }
      $this->set($file, self::FILE_HASH_KEY, $hash);
    }
    return $hash;
  }

  /**
   * Return the cached data for the given $node and $key or return null if
   * there is no data associated to $key.
   *
   * @param Node $node
   *
   * @param string $key
   *
   * @return null|string
   */
  public function get(Node $node, string $key):?string
  {
    /** @var File $keyFile */
    $keyFile = $this->getFile($node, $key);
    if (!$keyFile) {
      return null;
    }
    return $keyFile->getContent();
  }

  /**
   * Return the File holding the cached data for the given $node and $key or
   * return null if there is no data associated to $key.
   *
   * @param Node $node
   *
   * @param string $key
   *
   * @return null|sFile
   */
  public function getFile(Node $node, string $key):?File
  {
    $nodeId = $node->getId();
    $keyFile = $this->fileCacheFolders[$nodeId][self::ENTRIES_KEY][$key] ?? null;
    if (empty($keyFile)) {
      try {
        $nodeFolder = $this->getNodeFolder($node);
        $keyFile = $nodeFolder->get($key);
        $this->fileCacheFolders[$nodeId][self::ENTRIES_KEY][$key] = $keyFile;
      } catch (FileNotFoundException) {
        return null;
      }
    }
    return $keyFile;
  }

  /**
   * Delete the cached data for the given $node and $key. Do nothing if
   * there is no data associated to $key.
   *
   * @param Node $node
   *
   * @param string $key
   *
   * @return void
   */
  public function delete(Node $node, string $key):void
  {
    $keyFile = $this->getFile($node, $key);
    if ($keyFile) {
      $keyFile->delete();
      unset($this->fileCacheFolders[$node->getId()][self::ENTRIES_KEY][$key]);
    }
  }

  /**
   * Remove all entries no longer present in Nextcloud's file-cache.
   *
   * @return void
   */
  public function clean():void
  {
    if (empty($this->userId)) {
      return;
    }
    $userFolder = $this->rootFolder->getUserFolder($this->userId);
    $this->ensureCacheFolder();
    $entries = $this->filesCacheFolder->getDirectoryListing();
    foreach ($entries as $node) {
      $nodeId = $node->getName();
      if (empty($userFolder->getById($node->getName()))) {
        $node->delete();
        unset($this->fileCacheFolders[$nodeId]);
      }
    }
  }
}
