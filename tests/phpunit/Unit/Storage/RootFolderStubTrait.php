<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2026 Claus-Justus Heine <himself@claus-justus-heine.de>
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

namespace OCA\CAFEVDB\Tests\Unit\Storage;

use InvalidArgumentException;

use OCP\Constants as CloudConstants;
use OCP\Files\File;
use OCP\Files\FileInfo;
use OCP\Files\Folder;
use OCP\Files\IMimeTypeDetector;
use OCP\Files\IRootFolder;
use OCP\Files\Storage\IStorage;
use OCP\Files\Node;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\IURLGenerator;
use Psr\Container\ContainerInterface;

use OCA\CAFEVDB\Constants;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Storage\Database\Storage;
use OCA\CAFEVDB\Storage\UserStorage;
use OCA\CAFEVDB\Tests\MockProvider;

/**
 * Fabricate a stub or mock intercepting file system access.
 *
 * @method mixed createStub(string $class)
 */
trait RootFolderStubTrait
{
  use \OCA\CAFEVDB\Toolkit\Traits\UserRootFolderTrait;

  private IStorage $storage;

  private UserStorage $userStorage;

  private array $storages = [];

  private MockProvider $mockProvider;

  private ContainerInterface $appContainer;

  private IURLGenerator $urlGenerator;

  private IMimeTypeDetector $mimeTypeDetector;

  private array $fileSystemNodes = [];

  private array $nodePermissions = [];

  private array $databaseDirEntries = [];

  private int $nodeId = 1;

  /**
   * @param Node $path
   *
   * @param int $permissions
   *
   * @return void
   */
  private function setNodePermissions(Node $node, int $permissions): void
  {
    $path = $node->getPath();
    if (empty($this->fileSystemNodes[$path])) {
      throw new InvalidArgumentException('Attempt to set permissions for a non-existing file "' . $path . '".');
    }
    $this->nodePermissions[$path] = $permissions;
  }

  /** @return IStorage */
  private function getStorage(): IStorage
  {
    return $this->storage ?? $this->createStub(IStorage::class);
  }

  /**
   * @param string $parent
   *
   * @param string $child
   *
   * @return string
   */
  private function pathCat(string $parent, string $child): string
  {
    return rtrim(
      rtrim($parent, Constants::PATH_SEP) . Constants::PATH_SEP . ltrim($child, Constants::PATH_SEP),
      Constants::PATH_SEP,
    );
  }

  /**
   * @param string $path
   *
   * @return Folder
   */
  private function generateFolderStub(string $path): Folder|IRootFolder
  {
    $path = Constants::PATH_SEP . trim($path, Constants::PATH_SEP);

    $node = $this->fileSystemNodes[$path] ?? null;
    if ($node && $node->getType() == Node::TYPE_FOLDER) {
      return $node;
    }

    $node = $this->createStub($path === Constants::PATH_SEP ? IRootFolder::class : Folder::class);

    $node->method('getType')->willReturn(Node::TYPE_FOLDER);
    $node->method('getMimeType')->willReturn('httpd/unix-directory');
    $node->method('getPath')->willReturn($path);
    $node->method('getName')->willReturn(basename($path));
    $node->method('getPermissions')->willReturnCallback(
      fn() => array_key_exists($path, $this->nodePermissions) ? $this->nodePermissions[$path] : CloudConstants::PERMISSION_ALL,
    );
    $node->method('getStorage')->willReturnCallback(fn() => $this->storages[$path] ?? $this->getStorage());
    $parentPath = dirname($path);
    if ($parentPath != $path) {
      $parent = $this->generateFolderStub($parentPath);
      $node->method('getParent')->willReturn($parent);
    }
    $node->method('nodeExists')->willReturnCallback(
      fn(string $nodePath) => isset($this->fileSystemNodes[$this->pathCat($path, $childPath)]),
    );
    $node->method('getId')->willReturn($this->nodeId++);
    $node->method('get')->willReturnCallback(
      function(string $childPath) use ($path) {
        $node = $this->fileSystemNodes[$this->pathCat($path, $childPath)] ?? null;
        if ($node) {
          return $node;
        }
        throw new NotFoundException('No such filesystem node: "' . $this->pathCat($path, $childPath) . '".');
      },
    );

    $node->method('getDirectoryListing')->willReturnCallback(
      fn(?string $mimeTypeFilter) => array_values(
        array_filter(
          $this->fileSystemNodes,
          function(Node $node) use ($path) {
            $childPath = $node->getPath();
            $dirName = trim(dirname($childPath), Constants::PATH_SEP);
            return ($dirName === trim($path, Constants::PATH_SEP))
              && ($mimeTypeFilter === null || $node->getMimetype() == $mimeTypeFilter);
          },
        ),
      ),
    );
    $node->method('newFolder')->willReturnCallback(
      fn(string $childPath) => $this->generateFolderStub($this->pathCat($path, $childPath)),
    );
    $node->method('newFile')->willReturnCallback(
      fn(string $childPath, ?string $content = null) => $this->generateFileStub($node, $childPath, $content),
    );
    $node->method('getFullPath')->willReturnCallback(
      fn(string $childPath) => $this->pathCat($path, $childPath),
    );

    $this->fileSystemNodes[$path] = $node;

    return $node;
  }

  /**
   * @param Folder $parent
   *
   * @param string $childPath
   *
   * @param ?string $content
   *
   * @return File
   */
  private function generateFileStub(Folder $parent, string $childPath, ?string $content): File
  {
    $childPath = Constants::PATH_SEP . trim($childPath, Constants::PATH_SEP);
    if (str_starts_with($childPath, $parent->getPath())) {
      throw new InvalidArgumentException($childPath . ' ' . $parent->getPath());
    }
    $fullPath = $parent->getFullPath($childPath);

    $node = $this->fileSystemNodes[$fullPath] ?? null;
    if ($node && $node->getType() == Node::TYPE_FILE) {
      return $node;
    }

    $node = $this->createStub(File::class);
    $childParentPath = dirname($childPath);
    $directParent = $childParentPath != Constants::PATH_SEP
      ? $this->generateFolderStub($parent->getFullPath($childParentPath))
      : $parent;
    $node->method('getParent')->willReturn($directParent);
    $node->method('getPath')->willReturn($fullPath);
    $node->method('getStorage')->willReturnCallback(fn() => $this->storages[$fullPath] ?? $this->getStorage());
    $node->method('getName')->willReturn(basename($childPath));
    $node->method('getContent')->willReturn($content ?? '');
    $node->method('getMimeType')->willReturn($this->mimeTypeDetector->detectString($content ?? ''));
    $node->method('getSize')->willReturn(strlen($content ?? ''));
    $node->method('getType')->willReturn(Node::TYPE_FILE);
    $node->method('getId')->willReturn($this->nodeId++);
    $node->method('getPermissions')->willReturnCallback(
      fn() => array_key_exists($fullPath, $this->nodePermissions) ? $this->nodePermissions[$fullPath] : CloudConstants::PERMISSION_ALL,
    );
    $node->method('delete')->willReturnCallback(
      function() use ($fullPath) {
        unset($this->fileSystemNodes[$fullPath]);
      });

    $this->fileSystemNodes[$fullPath] = $node;

    return $node;
  }

  /** @return IRootFolder */
  private function getRootFolderStub(): IRootFolder
  {
    if ($this->rootFolder ?? null) {
      return $this->rootFolder;
    }

    $this->mockProvider = $this->mockProvider ?? MockProvider::create($this);
    $this->appContainer = $this->appContainer ?? $this->mockProvider->getAppContainer();
    $this->urlGenerator = $this->urlGenerator ?? $this->appContainer->get(IURLGenerator::class);
    $this->mimeTypeDetector = $this->mimeTypeDetector ?? $this->appContainer->get(IMimeTypeDetector::class);

    $this->rootFolder = $this->generateFolderStub(Constants::PATH_SEP);
    $this
      ->rootFolder->method('getUserFolder')
      ->willReturnCallback(
        fn(string $userId) => $this->generateFolderStub($this->getUserFolderPath($userId)),
      );

    $this->mockProvider->registerClassInstance(IRootFolder::class, $this->rootFolder, global: true);

    return $this->rootFolder;
  }

  /** @return UserStorage */
  private function getUserStorage(): UserStorage
  {
    if ($this->userStorage ?? null) {
      return $this->userStorage;
    }
    $this->userStorage = new UserStorage(
      userSession: $this->mockProvider->getUserSession(),
      appContainer: $this->mockProvider->getAppContainer(),
      rootFolder: $this->getRootFolderStub(),
      logger: $this->mockProvider->getLoggerInterface(),
      l: $this->mockProvider->getL10N(),
    );
    return $this->userStorage;
  }

  /**
   * @param Entities\DatabaseStorageDirEntry $dirEntry
   *
   * @param ?string $mountPointPath
   *
   * @return Node
   */
  private function setDatabaseDirEntry(
    Entities\DatabaseStorageDirEntry $dirEntry,
    ?string $mountPointPath = null,
  ): Node {
    $childPath = $dirEntry->getPathName();
    if ($mountPointPath === null) {
      $mountPoint = $this->rootFolder->getUserFolder($this->mockProvider->getUserSession()->getUser()->getUID());
      $mountPointPath = $mountPoint->getPath();
    } else {
      $mountPoint = $this->generateFolderStub($mountPointPath);
    }
    $fullPath = $this->pathCat($mountPointPath, $childPath);
    $this->databaseDirEntries[$fullPath] = $dirEntry;
    if ($dirEntry instanceof Entities\DatabaseStorageFile) {
      /** @var Entities\DatabaseStorageFile $dirEntry */
      $node = $this->generateFileStub($mountPoint, $childPath, $dirEntry->getData());
    } else {
      $node = $this->generateFolderStub($this->pathCat($mountPointPath, $childPath));
    }
    $node->method('getInternalPath')->willReturn($childPath);
    $storage = $this->createStub(Storage::class);
    $storage->method('fileFromFileName')->willReturn($dirEntry);

    $this->storages[$fullPath] = $storage;

    return $node;
  }
}
