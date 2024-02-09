<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2011-2014, 2016, 2020, 2021, 2022, 2023, 2024, Claus-Justus Heine <himself@claus-justus-heine.de>
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

use OCA\Text\Service\WorkspaceService;
use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Service\ToolTipsService;
use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Common\Util;
use OCA\CAFEVDB\Exceptions;
use OCA\CAFEVDB\Exceptions\Exception;
use OCA\CAFEVDB\Constants;

// phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps

/**
 * Storage implementation for data-base storage, including access to
 * encrypted entities.
 */
class Storage extends AbstractStorage
{
  use CopyDirectory;
  use \OCA\CAFEVDB\Traits\ConfigTrait;
  use \OCA\CAFEVDB\Traits\EntityManagerTrait;
  use \OCA\CAFEVDB\Toolkit\Traits\DateTimeTrait;

  /**
   * @var string
   *
   * The entire storage id starts with $this->appName().self::STORAGE_ID_TAG,
   * followed by path information about the intended mount-point.
   */
  const STORAGE_ID_TAG = '::database-storage';

  const PATH_SEPARATOR = Constants::PATH_SEP;

  /** @var \OCA\CAFEVDB\Wrapped\Doctrine\ORM\EntityRepository */
  protected $filesRepository;

  /** @var array */
  protected $files = [];

  /** @var null|Entities\DatabaseStorage */
  protected $storageEntity = null;

  /** @var null|Entities\DatabaseStorageFolder */
  protected $rootFolder = null;

  /** @var ReadMeFactory */
  protected ReadMeFactory $readMeFactory;

  /** {@inheritdoc} */
  public function __construct($params)
  {
    parent::__construct($params);

    $this->configService = $params['configService'];
    $this->l = $this->l10n();
    $this->entityManager = $this->di(EntityManager::class);
    $this->readMeFactory = $this->di(ReadMeFactory::class);

    if (!$this->entityManager->connected()) {
      throw new Exception('not connected');
    }

    $this->filesRepository = $this->getDatabaseRepository(Entities\EncryptedFile::class);
  }

  /**
   * Ensure that the top-level root directory exists as database entity.
   *
   * @param bool $create Create the root-folder if it does not exist yet.
   *
   * @return null|Entities\DatabaseStorageFolder
   */
  protected function getRootFolder(bool $create = false):?Entities\DatabaseStorageFolder
  {
    if (!empty($this->rootFolder) && !empty($this->storageEntity)) {
      return $this->rootFolder;
    }
    /** @var Entities\DatabaseStorageFolder $root */
    $shortId = $this->getShortId();
    /** @var Entities\DatabaseStorage $rootStorage */
    $rootStorage = $this->getDatabaseRepository(Entities\DatabaseStorage::class)->findOneBy([ 'storageId' => $shortId ]);
    if (!empty($rootStorage)) {
      $this->storageEntity = $rootStorage;
      $this->rootFolder = $rootStorage->getRoot();
    }
    if (!empty($rootStorage) || !$create) {
      return $this->rootFolder;
    }
    $rootFolder = (new Entities\DatabaseStorageFolder)
      ->setName('')
      ->setParent(null);
    $rootStorage = (new Entities\DatabaseStorage)
      ->setRoot($rootFolder)
      ->setStorageId($shortId);
    $this->persist($rootFolder);
    $this->persist($rootStorage);

    $this->flush();

    $this->storageEntity = $rootStorage;
    $this->rootFolder = $rootFolder;

    return $this->rootFolder;
  }

  /**
   * @param string $dirName Find all files below the given directory.
   *
   * @param bool $rootIsMandatory If \true throw an exception if the root-storage is
   * missing in the data-base. Otherwise silently create an empty dummy directory entry.
   *
   * @return array
   */
  protected function findFiles(string $dirName, bool $rootIsMandatory = false):array
  {
    $dirName = self::normalizeDirectoryName($dirName);
    if (!empty($this->files[$dirName])) {
      return $this->files[$dirName];
    }

    $dirComponents = Util::explode(self::PATH_SEPARATOR, $dirName);

    $filterState = $this->disableFilter(EntityManager::SOFT_DELETEABLE_FILTER);

    /** @var Entities\DatabaseStorageFolder $folderDirEntry */
    $folderDirEntry = $this->rootFolder;
    if (!$rootIsMandatory && empty($dirName) && empty($folderDirEntry)) {
      $rootFolder = $folderDirEntry ?? new EmptyRootNode('.', new DateTimeImmutable('@1'), $this->getShortId());
      $this->files[$dirName] = [ '.' => $rootFolder ];
      if (empty($folderDirEntry)) {
        $filterState && $this->enableFilter(EntityManager::SOFT_DELETEABLE_FILTER);
        $readMeNode = $this->readMeFactory->generateReadMe($rootFolder, $dirName);
        if ($readMeNode !== null) {
          $this->files[$dirName][$readMeNode->getName()] = $readMeNode;
        }
        return $this->files[$dirName];
      }
    } elseif (empty($folderDirEntry)) {
      $filterState && $this->enableFilter(EntityManager::SOFT_DELETEABLE_FILTER);
      throw new Exceptions\DatabaseEntityNotFoundException($this->l->t(
        'Unable to find directory entry for folder "%s".', $dirName
      ));
    } else {
      foreach ($dirComponents as $component) {
        $folderDirEntry = $folderDirEntry->getFolderByName($component);
        if (empty($folderDirEntry)) {
          $filterState && $this->enableFilter(EntityManager::SOFT_DELETEABLE_FILTER);
          throw new Exceptions\DatabaseEntityNotFoundException($this->l->t(
            'Unable to find directory entry for folder "%s", component "%s", storage "%s".', [
              $dirName, $component, $this->getShortId(),
            ],
          ));
        }
      }
      $this->files[$dirName] = [ '.' => $folderDirEntry ];
    }

    $this->files[$dirName] = [ '.' => $folderDirEntry ];

    $dirEntries = $folderDirEntry->getDirectoryEntries();

    $hasReadme = false;
    $readMe = null;

    /** @var Entities\DatabaseStorageDirEntry $dirEntry */
    foreach ($dirEntries as $dirEntry) {

      $baseName = $dirEntry->getName();
      $fileName = $this->buildPath($dirName . self::PATH_SEPARATOR . $baseName);
      list('basename' => $baseName) = self::pathInfo($fileName);

      if ($dirEntry instanceof Entities\DatabaseStorageFolder) {
        /** @var Entities\DatabaseStorageFolder $dirEntry */
        // add a directory entry
        $baseName .= self::PATH_SEPARATOR;
        $this->files[$dirName][$baseName] = $dirEntry;
      } else {
        /** @var Entities\DatabaseStorageFile $dirEntry */
        $this->files[$dirName][$baseName] = $dirEntry;
        $hasReadme = $hasReadme || $this->readMeFactory->isReadMe($baseName);
        if ($this->readMeFactory->isReadMe($baseName)) {
          $readMe = $baseName;
        }
      }
    }
    if (!$hasReadme) {
      $readMeNode = $this->readMeFactory->generateReadMe($folderDirEntry, $dirName);
      if ($readMeNode !== null) {
        $this->files[$dirName][$readMeNode->getName()] = $readMeNode;
      }
    }

    $filterState && $this->enableFilter(EntityManager::SOFT_DELETEABLE_FILTER);

    return $this->files[$dirName];
  }

  /**
   * Persist an InMemoryFileNode to the database, kind of copy-on-write operation.
   *
   * @param InMemoryFileNode $memoryNode
   *
   * @param null|Entities\Musician $owner
   *
   * @return Entities\DatabaseStorageFile
   */
  protected function persistInMemoryFileNode(InMemoryFileNode $memoryNode, ?Entities\Musician $owner = null):Entities\DatabaseStorageFile
  {
    $this->entityManager->beginTransaction();
    try {
      if ($memoryNode->getParent() instanceof EmptyRootNode) {
        $memoryNode->setParent($this->getRootFolder(create: true));
      }

      $file = new Entities\EncryptedFile(
        $memoryNode->getName(),
        $memoryNode->getFileData()->getData(),
        $memoryNode->getMimeType(),
        owner: $owner,
      );
      $file->setCreated($memoryNode->getUpdated());
      $file->setUpdated($memoryNode->getUpdated());
      $this->persist($file);
      $this->flush();
      $fileNode = $memoryNode->getParent()->addDocument($file, $memoryNode->getName());

      if ($fileNode === null) {
        throw new Exceptions\DatabaseStorageException(
          $this->l->t('Unable to convert the in-memory file "%s".', $memoryNode->getParent()->getPathName()),
        );
      }

      $this->flush();

      $this->entityManager->commit();

      return $fileNode;
    } catch (Throwable $t) {
      if ($this->entityManager->isTransactionActive()) {
        $this->entityManager->rollback();
      }
      if ($t instanceof Exceptions\DatabaseStorageException) {
        throw $t;
      } else {
        throw new Exceptions\DatabaseStorageException(
          $this->l->t('Unable to convert the in-memory file "%s".', $memoryNode->getParent()->getPathName()),
          0,
          $t,
        );
      }
    }
    return null;
  }

  /**
   * Remove a "cache" entry.
   *
   * @param string $name Path-name.
   *
   * @return void
   */
  protected function unsetFileNameCache(string $name):void
  {
    $name = $this->buildPath($name);
    if ($name == self::PATH_SEPARATOR) {
      $dirName = '';
      $baseName = '.';
    } else {
      list('basename' => $baseName, 'dirname' => $dirName) = self::pathInfo($name);
    }

    if (isset($this->files[$dirName][$baseName])) {
      unset($this->files[$dirName][$baseName]);
    } elseif (isset($this->files[$dirName][$baseName . self::PATH_SEPARATOR ])) {
      unset($this->files[$dirName][$baseName . self::PATH_SEPARATOR]);
    }
  }

  /**
   * Insert a "cache" entry.
   *
   * @param string $name Path-name.
   *
   * @param Entities\DatabaseStorageDirEntry $node File-system node.
   *
   * @return void
   */
  protected function setFileNameCache(string $name, Entities\DatabaseStorageDirEntry $node):void
  {
    $name = $this->buildPath($name);
    if ($name == self::PATH_SEPARATOR) {
      $dirName = '';
      $baseName = '.';
    } else {
      list('basename' => $baseName, 'dirname' => $dirName) = self::pathInfo($name);
    }

    $this->files[$dirName][$baseName] = $node;
  }

  /**
   * @param string $dirName The directory to work on.
   *
   * @return DateTimeInterface
   */
  protected function getDirectoryModificationTime(string $dirName):DateTimeInterface
  {
    $directory = $this->fileFromFileName($dirName);
    if ($directory instanceof EmptyRootNode) {
      $date = $directory->minimalModificationTime ?? (new DateTimeImmutable('@1'));
    } elseif ($directory instanceof Entities\DatabaseStorageFolder) {
      $date = $directory->getUpdated();
    } else {
      $date = new DateTimeImmutable('@1');
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
    return $this->appName() . self::STORAGE_ID_TAG . self::PATH_SEPARATOR . $this->getShortId();
  }

  /** @return string The shot storage id without the base prefix. */
  public function getShortId():string
  {
    return '';
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
   * Fetch the file entity corresponding to file-name. If the entry is a
   * directory, return the directory name.
   *
   * @param string $name The path-name to work on.
   *
   * @return null|Entities\DatabaseStorageDirEntry|EmptyRootNode
   */
  public function fileFromFileName(string $name)
  {
    $name = $this->buildPath($name);
    if ($name == self::PATH_SEPARATOR) {
      $dirName = '';
      $baseName = '.';
    } else {
      list('basename' => $baseName, 'dirname' => $dirName) = self::pathInfo($name);
    }

    if (empty($this->files[$dirName])) {
      $this->findFiles($dirName);
    }

    $dirEntry = ($this->files[$dirName][$baseName]
                 ?? ($this->files[$dirName][$baseName . self::PATH_SEPARATOR]
                     ?? null));

    return $dirEntry;
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
   * @param string $path Directory-entry to check.
   *
   * @param int $time The value of storage_mtime from the row of the
   * filescache table for $path.
   *
   * This function controls cache invalidation. We return \true if either the
   * file is still there and its mtime is larger then the supplied
   * storage_mtime of the $time argument, or if the file has vanished in which
   * case it should be removed from the filescache table.
   */
  public function hasUpdated($path, $time)
  {
    $mtime = $this->filemtime($path);
    return $mtime === false || ($mtime > $time);
  }

  /** {@inheritdoc} */
  public function filesize($path):int|float|false
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
    try {
      if ($this->is_dir($path)) {
        return true;
      }
      $file = $this->fileFromFileName($path);
      if (empty($file)) {
        return false;
      }
    } catch (Exceptions\DatabaseEntityNotFoundException $e) {
      // ignore
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
    if ($path === '' || $path == self::PATH_SEPARATOR || $path === '.') {
      return true;
    }
    $dirEntry = $this->fileFromFileName($path);

    if ($dirEntry instanceof EmptyRootNode
        || $dirEntry instanceof Entities\DatabaseStorageFolder
    ) {
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
    // $this->logInfo('WRITE STREAM ' . $path);

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
    fclose($stream);

    $this->entityManager->beginTransaction();
    try {
      if ($file instanceof InMemoryFileNode) {
        // this needs first to be replaced by a real file-node
        $file = $this->persistInMemoryFileNode($file);
      }
      $file->getFileData()->setData($fileData);
      /** @var IMimeTypeDetector $mimeTypeDetector */
      $mimeTypeDetector = $this->di(IMimeTypeDetector::class);
      $file->setMimeType($mimeTypeDetector->detectString($fileData));
      $file->setSize(strlen($fileData));

      $this->flush();

      $this->entityManager->commit();
    } catch (Throwable $t) {
      $this->logException($t, 'writeStream() failed');
      if ($this->entityManager->isOwnTransactionActive()) {
        $this->entityManager->rollback();
      }
      return -1;
    }

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
    // using data:// would also result in copying things around as something
    // would have to be prepended to the file-data ... so we can as well just
    // use fwrite(). Unfortunately there is not such a nice thing like
    // memory-backed streams as there is in C++ -- where you just can use an
    // existing buffer verbatim without the need to copy it around.
    $stream = fopen('php://memory', 'w+');
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
