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

  /** @var \OCA\CAFEVDB\Wrapped\Doctrine\ORM\EntityRepository */
  protected $filesRepository;

  /** @var string */
  protected $root;

  public function __construct($params)
  {
    $this->configService = \OC::$server->query(ConfigService::class);
    $this->l = $this->l10n();
    $this->entityManager = $this->di(EntityManager::class);

    if (!$this->entityManager->connected()) {
      throw new \Exception('not connected');
    }

    $this->filesRepository = $this->getDatabaseRepository(Entities\File::class);

    $this->root = isset($params['root']) ? '/' . ltrim($params['root'], '/') : '/';
  }

  protected function findFiles()
  {
    return $this->filesRepository->findAll();
  }

  protected function getStorageModificationTime()
  {
    return $this->filesRepository->fetchLatestModifiedTime()->getTimestamp();
  }

  /** {@inheritdoc} */
  public function getId()
  {
    return $this->appName() . '::' . 'database-storage' . $this->root;
  }

  protected function buildPath($path) {
    return rtrim($this->root . '/' . $path, '/');
  }

  /** {@inheritdoc} */
  public static function checkDependencies() {
    return true;
  }

  /**
   * Generate the file name from the entity. The default is to prepend
   * the database id. Derived classes may want to override this.
   *
   * @param Entities\File $file
   *
   * @return string
   */
  protected function fileNameFromEntity(Entities\File $file):string
  {
    $nameParts = [ 'db', $file->getId() ];
    if (!empty($file->getFileName())) {
      $nameParts[] = $file->getFileName();
    }
    $name = implode('-', $nameParts);
    $ext = pathinfo($name, PATHINFO_EXTENSION);
    if (empty($ext)) {
      $ext = Util::fileExtensionFromMimeType($file->getMimeType());
      if (!empty($ext)) {
        $name .= '.' . $ext;
      }
    }
    return $name;
  }

  private function fileIdFromFileName($name)
  {
    $name = $this->buildPath($name);
    $name = pathinfo($name, PATHINFO_BASENAME);
    list(,$id) = explode('-', $name);
    return (int)$id;
  }

  /**
   * Fetch the file entity corresponding to file-name.
   *
   * @param string $name
   *
   * @return null|Enties\File
   */
  protected function fileFromFileName(string $name):?Entities\File
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
      return $this->getStorageModificationTime();
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
    if (!$this->is_dir($path)) {
      return [
        'mtime' => $this->filemtime($path),
        'size' => $this->filesize($path),
      ];
    }
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
    if ($path === '' || $path === '.' || $path === '/') {
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
    $fileNames = [];
    foreach ($this->findFiles() as $file) {
      $fileNames[] = $this->fileNameFromEntity($file);
    }
    return IteratorDirectory::wrap($fileNames);
  }

  public function mkdir($path)
  {
    return false;
  }

  public function is_dir($path)
  {
    if ($path === '' || $path == '/') {
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
