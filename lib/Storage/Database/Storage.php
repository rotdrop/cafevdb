<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2011-2014, 2016, 2020, 2021, 2022, Claus-Justus Heine <himself@claus-justus-heine.de>
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

namespace OCA\CAFEVDB\Storage\Database;

use \DateTimeInterface;
use \DateTimeImmutable;

use OCP\Files\IMimeTypeDetector;
use OCP\ITempManager;

// F I X M E: those are not public, but ...
use OC\Files\Storage\Common as AbstractStorage;
use OC\Files\Storage\PolyFill\CopyDirectory;

use Icewind\Streams\CallbackWrapper;
use Icewind\Streams\CountWrapper;
use Icewind\Streams\IteratorDirectory;

use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Common\Util;
use OCA\CAFEVDB\Exceptions\Exception;

/**
 * Storage implementation for data-base storage, including access to
 * encrypted entities.
 */
class Storage extends AbstractStorage
{
  use CopyDirectory;
  use \OCA\CAFEVDB\Traits\ConfigTrait;
  use \OCA\CAFEVDB\Traits\EntityManagerTrait;
  use \OCA\CAFEVDB\Traits\DateTimeTrait;

  /**
   * @var string
   *
   * The entire storage id starts with $this->appName().self::STORAGE_ID_TAG,
   * followed by path information about the intended mount-point.
   */
  const STORAGE_ID_TAG = '::database-storage';

  const PATH_SEPARATOR = '/';

  /** @var \OCA\CAFEVDB\Wrapped\Doctrine\ORM\EntityRepository */
  protected $filesRepository;

  /** @var array */
  private $files = [];

  /** {@inheritdoc} */
  public function __construct($params)
  {
    parent::__construct($params);

    $this->configService = $params['configService'];
    $this->l = $this->l10n();
    $this->entityManager = $this->di(EntityManager::class);

    if (!$this->entityManager->connected()) {
      throw new Exception('not connected');
    }

    $this->filesRepository = $this->getDatabaseRepository(Entities\File::class);
  }

  /**
   * @param string $dirName Find all files below the given directory.
   *
   * @return array
   */
  protected function findFiles(string $dirName):array
  {
    $dirName = self::normalizeDirectoryName($dirName);
    $files = empty($dirName)
      ? $this->filesRepository->findAll()
      : $this->filesRepository->findLike([ 'fileName' => $dirName . self::PATH_SEPARATOR . '%' ]);
    /** @var Entities\File $file */
    foreach ($files as $file) {
      $fileName = $this->buildPath($file->getFileName());
      list('dirname' => $fileDirName, 'basename' => $baseName) = self::pathinfo($fileName);
      if ($fileDirName == $dirName) {
        $this->files[$dirName][$baseName] = $file;
      } elseif (strpos($fileDirName, $dirName) === 0) {
        list($baseName) = explode(self::PATH_SEPARATOR, substr($fileDirName, strlen($dirName)), 1);
        $this->files[$dirName][$baseName] = new DirectoryNode($baseName);
      }
    }
    return $this->files[$dirName];
  }

  /**
   * @param string $dirName The directory to work on.
   *
   * @return DateTimeInterface
   */
  protected function getDirectoryModificationTime(string $dirName):DateTimeInterface
  {
    $directory = $this->fileFromFileName($dirName);
    if ($directory instanceof DirectoryNode) {
      $date = $directory->minimalModificationTime ?? (new DateTimeImmutable('@1'));
    } else {
      $date = new DateTimeImmutable('@1');
    }

    // maybe we should skip the read-dir for performance reasons.
    /** @var Entities\File $node */
    foreach ($this->findFiles($dirName) as $node) {
      if ($node instanceof Entities\File) {
        $updated = $node->getUpdated();
      } elseif ($node instanceof DirectoryNode) {
        $nodeName = $node->name;
        $updated = $node->minimalModificationTime ?? (new DateTimeImmutable('@1'));
        if ($nodeName != '.') {
          $recursiveModificationTime = $this->getDirectoryModificationTime($dirName . self::PATH_SEPARATOR . $node->name);
          if ($recursiveModificationTime > $updated) {
            $updated = $recursiveModificationTime;
          }
        }
      } else {
        $this->logError('Unknown directory entry in ' .$dirName);
        $updated = new DateTimeImmutable('@1');
      }
      if ($updated > $date) {
        $date = $updated;
      }
    }
    return $date;
  }

  /**
   * Return the overall modification time of the entire storage.
   *
   * @return null|DateTimeInterface
   */
  protected function getStorageModificationDateTime():?DateTimeInterface
  {
    return self::ensureDate($this->getDatabaseRepository(Entities\LogEntry::class)->modificationTime());
  }

  /**
   * Get the overall modification time of the entire storage as Unix time-stamp.
   *
   * @return int
   */
  protected function getStorageModificationTime():int
  {
    return $this->getStorageModificationDateTime()->getTimestamp();
  }

  /** {@inheritdoc} */
  public function getId()
  {
    return $this->appName() . self::STORAGE_ID_TAG . self::PATH_SEPARATOR;
  }

  /**
   * @param null|string $path The path to work on.
   *
   * @return string
   */
  protected function buildPath(?string $path):string
  {
    return \OC\Files\Filesystem::normalizePath($path);
  }

  /**
   * Attach self::PATH_SEPARATOR to the dirname if it is not the root directory.
   *
   * @param string $dirName The directory name to work on.
   *
   * @return string
   */
  protected static function normalizeDirectoryName(string $dirName):string
  {
    if ($dirName == '.') {
      $dirName = '';
    }
    $dirName = trim($dirName, self::PATH_SEPARATOR);
    return empty($dirName) ? $dirName : $dirName . self::PATH_SEPARATOR;
  }

  /**
   * Slightly modified pathinfo() function which also normalized directories
   * before computing the components.
   *
   * @param string $path The path to work on.
   *
   * @param int $flags As for the upstream pathinfo() function.
   *
   * @return string|array
   */
  protected static function pathInfo(string $path, int $flags = PATHINFO_ALL)
  {
    $pathInfo = pathinfo($path, $flags);
    if ($flags == PATHINFO_DIRNAME) {
      $pathInfo = self::normalizeDirectoryName($pathInfo);
    } elseif (is_array($pathInfo)) {
      $pathInfo['dirname'] = self::normalizeDirectoryName($pathInfo['dirname']);
    }
    return $pathInfo;
  }

  /** {@inheritdoc} */
  public static function checkDependencies()
  {
    return true;
  }

  /**
   * @param string $name The file-name to work on.
   *
   * @return int The file-id.
   */
  private function fileIdFromFileName(string $name):int
  {
    $name = $this->buildPath($name);
    $name = pathinfo($name, PATHINFO_BASENAME);
    $parts = explode('-', $name);
    if (count($parts) >= 2 && filter_var($parts[1], FILTER_VALIDATE_INT, [ 'options' => [ 'min_range' => 1 ], ]) !== false) {
      return $parts[1];
    }
    return 0;
  }

  /**
   * Fetch the file entity corresponding to file-name. If the entry is a
   * directory, return the directory name.
   *
   * @param string $name The path-name to work on.
   *
   * @return null|string|Entities\File
   */
  public function fileFromFileName(string $name)
  {
    $id = $this->fileIdFromFileName($name);
    if ($id > 0) {
      return $this->filesRepository->find($id);
    }
    return null;
  }

  /** {@inheritdoc} */
  public function isReadable($path)
  {
    // at least check whether it exists
    // subclasses might want to implement this more thoroughly
    return $this->file_exists($path);
  }

  /** {@inheritdoc} */
  public function isUpdatable($path)
  {
    // return $this->file_exists($path);
    return false; // readonly for now
  }

  /** {@inheritdoc} */
  public function isSharable($path)
  {
    // sharing cannot work in general as the database access need additional
    // credentials
    return false;
  }

  /** {@inheritdoc} */
  public function filemtime($path)
  {
    if ($this->is_dir($path)) {
      $mtime = $this->getDirectoryModificationTime($path)->getTimestamp();
      if ($path === self::PATH_SEPARATOR || empty($path)) {
        $mtime = max($mtime, $this->getStorageModificationTime());
      }
      return $mtime;
    }
    $file = $this->fileFromFileName($path);
    if (empty($file)) {
      return false;
    }
    $updated = $file->getUpdated();
    return empty($updated) ? 1 : $updated->getTimestamp();
  }

  /**
   * {@inheritdoc}
   *
   * The AbstractStorage class relies on mtime($path) > $time for triggering a
   * cache invalidation. This, however, does not cover cases where a directory
   * has been removed. Hence we also return true if mtime returns false
   * meaning that the file does not exist.
   */
  public function hasUpdated($path, $time)
  {
    $mtime = $this->filemtime($path);
    return $mtime === false || ($mtime > $time);
  }

  /** {@inheritdoc} */
  public function filesize($path)
  {
    if ($this->is_dir($path)) {
      return 0;
    }
    $file = $this->fileFromFileName($path);
    if (empty($file)) {
      return false;
    }
    return $file->getSize();
  }

  /** {@inheritdoc} */
  public function rmdir($path)
  {
    return false;
  }

  /** {@inheritdoc} */
  public function test()
  {
    try {
      $this->filesRepository->count([]);
    } catch (\Throwable $t) {
      $this->logException($t);
      return false;
    }
    return true;
  }

  /** {@inheritdoc} */
  public function stat($path)
  {
    $file = $this->fileFromFileName($path);
    if (empty($file)) {
      return false;
    }
    return [
      'mtime' => $file->getUpdated()->getTimestamp(),
      'size' => $file->getSize(),
    ];
  }

  /** {@inheritdoc} */
  public function file_exists($path)
  {
    if ($this->is_dir($path)) {
      return true;
    }
    $file = $this->fileFromFileName($path);
    if (empty($file)) {
      return false;
    }
    return true;
  }

  /** {@inheritdoc} */
  public function unlink($path)
  {
    $file = $this->fileFromFileName($path);
    if (empty($file)) {
      return false;
    }
    return false;
    // $this->entityManager->remove($file);
    // $this->flush();
    // return true;
  }

  /** {@inheritdoc} */
  public function opendir($path)
  {
    if (!$this->is_dir($path)) {
      return false;
    }
    $fileNames = array_keys($this->findFiles($path));
    Util::unsetValue($fileNames, '.');
    Util::unsetValue($fileNames, '..');
    return IteratorDirectory::wrap(array_values($fileNames));
  }

  /** {@inheritdoc} */
  public function mkdir($path)
  {
    return false;
  }

  /** {@inheritdoc} */
  public function is_dir($path)
  {
    if ($path === '' || $path == self::PATH_SEPARATOR) {
      return true;
    }
    if ($this->fileFromFileName($path) instanceof DirectoryNode) {
      return true;
    }
    if (!empty($this->filesRepository->findOneLike([ 'fileName' => trim($path, self::PATH_SEPARATOR) . self::PATH_SEPARATOR . '%' ]))) {
      return true;
    }
    return false;
  }

  /** {@inheritdoc} */
  public function is_file($path)
  {
    return $this->filesize($path) !== false;
  }

  /** {@inheritdoc} */
  public function filetype($path)
  {
    if ($this->is_dir($path)) {
      return 'dir';
    } elseif ($this->is_file($path)) {
      return 'file';
    } else {
      return false;
    }
  }

  /** {@inheritdoc} */
  public function fopen($path, $mode)
  {
    $useExisting = true;
    switch ($mode) {
      case 'r':
      case 'rb':
        return $this->readStream($path);
      case 'w':
      case 'w+':
      case 'wb':
      case 'wb+':
        $useExisting = false;
        // no break
      case 'a':
      case 'ab':
      case 'r+':
      case 'a+':
      case 'x':
      case 'x+':
      case 'c':
      case 'c+':
        //emulate these
        if ($useExisting and $this->file_exists($path)) {
          if (!$this->isUpdatable($path)) {
            return false;
          }
          $tmpFile = $this->getCachedFile($path);
        } else {
          if (!$this->isCreatable(dirname($path))) {
            return false;
          }
          if (!$this->touch($path)) {
            return false;
          }
          $tmpFile = $this->di(ITempManager::class)->getTemporaryFile();
        }
        $source = fopen($tmpFile, $mode);

        return CallbackWrapper::wrap($source, null, null, function () use ($tmpFile, $path) {
          $this->writeStream($path, fopen($tmpFile, 'r'));
          unlink($tmpFile);
        });
    }
    return false;
  }

  /** {@inheritdoc} */
  public function writeStream(string $path, $stream, int $size = null): int
  {
    if (!$this->touch($path)) {
      return false;
    }
    /** @var Entities\EncryptedFile $file */
    $file = $this->fileFromFileName($path);
    if (empty($file)) {
      return false;
    }
    if ($size === null) {
      $stream = CountWrapper::wrap($stream, function ($writtenSize) use (&$size) {
        $size = $writtenSize;
      });
    }

    $fileData = stream_get_contents($stream);
    $file->getFileData()->setData($fileData);
    /** @var IMimeTypeDetector $mimeTypeDetector */
    $mimeTypeDetector = $this->di(IMimeTypeDetector::class);
    $file->setMimeType($mimeTypeDetector->detectString($fileData));
    $file->setSize(strlen($fileData));

    $this->flush();
    fclose($stream);

    return $size;
  }

  /** {@inheritdoc} */
  public function readStream(string $path)
  {
    /** @var Entities\EncryptedFile $file */
    $file = $this->fileFromFileName($path);
    if (empty($file)) {
      return false;
    }
    $stream = fopen('php://temp', 'w+');
    $result = fwrite($stream, $file->getFileData()->getData());
    rewind($stream);
    if ($result === false) {
      fclose($stream);
      return false;
    }
    return $stream;
  }

  /** {@inheritdoc} */
  public function touch($path, $mtime = null)
  {
    return false;
  }

  /** {@inheritdoc} */
  public function rename($path1, $path2)
  {
    return false;
  }
}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
