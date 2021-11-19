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

namespace OCA\CAFEVDB\Storage\Database;

// FIXME: those are not public, but ...
use OC\Files\Storage\Common as AbstractStorage;
use OC\Files\Storage\PolyFill\CopyDirectory;

use Icewind\Streams\CallbackWrapper;
use Icewind\Streams\CountWrapper;
use Icewind\Streams\IteratorDirectory;

use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Common\Util;

/**
 * Storage implementation for data-base storage, including access to
 * encrypted entities.
 */
class Storage extends AbstractStorage
{
  use CopyDirectory;
  use \OCA\CAFEVDB\Traits\ConfigTrait;
  use \OCA\CAFEVDB\Traits\EntityManagerTrait;

  const PATH_SEPARATOR = '/';

  /** @var \OCA\CAFEVDB\Wrapped\Doctrine\ORM\EntityRepository */
  protected $filesRepository;

  /** @var array */
  private $files = [];

  public function __construct($params)
  {
    $this->configService = \OC::$server->query(ConfigService::class);
    $this->l = $this->l10n();
    $this->entityManager = $this->di(EntityManager::class);

    if (!$this->entityManager->connected()) {
      throw new \Exception('not connected');
    }

    $this->filesRepository = $this->getDatabaseRepository(Entities\File::class);
  }

  protected function findFiles(string $dirName)
  {
    $dirName = self::normalizeDirectoryName($dirName);
    $files = empty($directory)
      ? $this->filesRepository->findAll()
      : $this->filesRepository->findLike([ 'fileName' => $dirName . self::PATH_SEPARATOR . '%' ]);
    /** @var Entities\File $file */
    foreach ($files as $file) {
      $fileName = $this->buildPath($file->getFileName());
      list('dirname' => $fileDirName, 'basename' => $baseName) = self::pathinfo($name);
      if ($fileDirName == $dirName) {
        $this->files[$dirName][$baseName] = $file;
      } else if (strpos($fileDirName, $dirName) === 0) {
        list($baseName) = explode(self::PATH_SEPARATOR, substr($fileDirName, strlen($dirName)), 1);
        $this->files[$dirName][$baseName] = $baseName;
      }
    }
    return $this->files[$dirName];
  }

  protected function getDirectoryModificationTime(string $dirName):\DateTimeInterface
  {
    $date = (new \DateTimeImmutable)->setTimestamp(0);
    /** @var Entities\File $node */
    foreach ($this->findFiles($dirName) as $node) {
      if ($node instanceof Entities\File) {
        $updated = $node->getUpdated();
      } else {
        $updated = $this->getDirectoryModificationTime($dirName . self::PATH_SEPARATOR . $node);
      }
      if ($updated > $date) {
        $date = $updated;
      }
    }
    return $date;
  }

  protected function getStorageModificationTime():int
  {
    return $this->filesRepository->fetchLatestModifiedTime()->getTimestamp();
  }

  /** {@inheritdoc} */
  public function getId()
  {
    return $this->appName() . '::' . 'database-storage'. self::PATH_SEPARATOR;
  }

  protected function buildPath($path) {
    return \OC\Files\Filesystem::normalizePath($path);
  }

  /** Attach self::PATH_SEPARATOR to the dirname if it is not the root directory. */
  protected static function normalizeDirectoryName(string $dirName)
  {
    if ($dirName == '.') {
      $dirName = '';
    }
    $dirName = trim($dirName, self::PATH_SEPARATOR);
    return empty($dirName) ? $dirName : $dirName . self::PATH_SEPARATOR;
  }

  /** Attach self::PATH_SEPARATOR to the dirname if it is not the root directory. */
  protected static function pathInfo(string $path)
  {
    $pathInfo = pathinfo($path);
    $pathInfo['dirname'] = self::normalizeDirectoryName($pathInfo['dirname']);
    return $pathInfo;
  }

  /** {@inheritdoc} */
  public static function checkDependencies() {
    return true;
  }

  private function fileIdFromFileName($name)
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
   * @param string $name
   *
   * @return null|string|Enties\File
   */
  protected function fileFromFileName(string $name)
  {
    $id = $this->fileIdFromFileName($name);
    if ($id > 0) {
      return $this->filesRepository->find($id);
    }
    return null;
  }

  public function isReadable($path) {
    // at least check whether it exists
    // subclasses might want to implement this more thoroughly
    return $this->file_exists($path);
  }

  public function isUpdatable($path) {
    // return $this->file_exists($path);
    return false; // readonly for now
  }

  public function filemtime($path)
  {
    if ($this->is_dir($path)) {
      return $this->getDirectoryModificationTime($path)->getTimestamp();
    }
    $file = $this->fileFromFileName($path);
    if (empty($file)) {
      return 0;
    }
    $updated = $file->getUpdated();
    return empty($updated) ? 0 : $updated->getTimestamp();
  }

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

  public function rmdir($path)
  {
    return false;
  }

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

  public function opendir($path)
  {
    if (!$this->is_dir($path)) {
      return false;
    }
    $fileNames = array_keys($this->findFiles($path));
    return IteratorDirectory::wrap($fileNames);
  }

  public function mkdir($path)
  {
    return false;
  }

  public function is_dir($path)
  {
    if ($path === '' || $path == self::PATH_SEPARATOR) {
      return true;
    }
    if (is_string($this->fileFromFileName($path))) {
      return true;
    }
    if (!empty($this->filesRepository->findOneLike([ 'fileName' => trim($path, self::PATH_SEPARATOR) . self::PATH_SEPARATOR . '%' ]))) {
      return true;
    }
    return false;
  }

  public function is_file($path)
  {
    return $this->filesize($path) !== false;
  }

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
        $tmpFile = \OC::$server->getTempManager()->getTemporaryFile();
      }
      $source = fopen($tmpFile, $mode);
      return CallbackWrapper::wrap($source, null, null, function () use ($tmpFile, $path) {
        $this->writeStream($path, fopen($tmpFile, 'r'));
        unlink($tmpFile);
      });
    }
    return false;
  }

  public function readStream(string $path)
  {
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

  public function touch($path, $mtime = null)
  {
    return false;
  }

  public function rename($path1, $path2)
  {
    return false;
  }
}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
