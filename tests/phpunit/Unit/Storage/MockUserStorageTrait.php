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
use OCP\Files\Node;
use OCP\Files\NotFoundException;
use OCP\IURLGenerator;
use Psr\Container\ContainerInterface;

use OCA\CAFEVDB\Constants;
use OCA\CAFEVDB\Storage\UserStorage;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Tests\MockProvider;

/**
 * Fabricate a stub or mock intercepting file system access.
 *
 * @method mixed createStub(string $class)
 */
trait MockUserStorageTrait
{
  private UserStorage $userStorage;

  private MockProvider $mockProvider;

  private ContainerInterface $appContainer;

  private IURLGenerator $urlGenerator;

  private IMimeTypeDetector $mimeTypeDetector;

  private array $fileSystemNodes = [];

  private array $nodePermissions = [];

  private int $nodeId = 1;

  /** @var array<string, Entities\DatabaseStorageDirEntry> */
  private array $databaseDirEntries = [];

  /**
   * @param string $path
   *
   * @param int $permissions
   *
   * @return void
   */
  private function setNodePermissions(string $path, int $permissions): void
  {
    if (empty($this->fileSystemNodes[$path])) {
      throw new InvalidArgumentException('Attempt to set permissions for a non-existing file "' . $path . '".');
    }
    $this->nodePermissions[$path] = $permissions;
  }

  /**
   * Generate a stub or mock for the UserStorage class.
   *
   * @param bool $mock If \true generate a mock, else a stub
   *
   * @return mixed
   */
  private function getUserStorageStub(bool $mock = false): mixed
  {
    $this->mockProvider = $this->mockProvider ?? MockProvider::create($this);
    $this->appContainer = $this->appContainer ?? $this->mockProvider->getAppContainer();
    $this->urlGenerator = $this->urlGenerator ?? $this->appContainer->get(IURLGenerator::class);
    $this->mimeTypeDetector = $this->mimeTypeDetector ?? $this->appContainer->get(IMimeTypeDetector::class);

    if ($mock) {
      $this->userStorage = $this->getMockBuilder(UserStorage::class)
        ->disableOriginalConstructor()
        ->getMock();
    } else {
      $this->userStorage = $this->createStub(UserStorage::class);
    }

    $this->userStorage->method('ensureFolderChain')->willReturn($this->createStub(Folder::class));
    $this->userStorage->method('copyTree')->willReturn($this->createStub(Folder::class));
    $this->userStorage->method('getFile')->willReturnCallback(
      function(string $path) {
        $node = $this->fileSystemNodes[$path] ?? null;
        return $node?->getType() == Node::TYPE_FILE ? $node : null;
      },
    );
    $this->userStorage->method('getFolder')->willReturnCallback(
      function(string $path) {
        $node = $this->fileSystemNodes[$path] ?? null;
        return $node?->getType() == Node::TYPE_FOLDER ? $node : null;
      },
    );
    $this->userStorage->method('get')->willReturnCallback(
      function(string $path, ?string $type = null, bool $useCache = false, bool $throw = false) {
        if ($this->fileSystemNodes[$path] ?? null) {
          return $this->fileSystemNodes[$path];
        }
        if ($path == '/' || $path == '') {
          $node = $this->createStub(IRootFolder::class);
          $this->assertInstanceOf(IRootFolder::class, $node);
        } elseif ($type == FileInfo::TYPE_FILE) {
          if ($throw) {
             throw new NotFoundException('AppStorage stub does not know about this file.');
          }
          return null;
        } else {
          // we gracefully just generate the folder.
          $node = $this->createStub(Folder::class);
        }
        $node->method('getType')->willReturn(Node::TYPE_FOLDER);
        $node->method('getPath')->willReturn($path);
        $node->method('getName')->willReturn(basename($path));
        $node->method('getPermissions')->willReturnCallback(
          fn() => array_key_exists($path, $this->nodePermissions) ? $this->nodePermissions[$path] : CloudConstants::PERMISSION_ALL,
        );
        $parent = dirname($path);
        if ($parent != $path) {
          // echo 'PARENT ' . $parent . PHP_EOL;
          $parent = $this->userStorage->get($parent);
          $node->method('getParent')->willReturn($parent);
        }
        $node->method('nodeExists')->willReturnCallback(fn(string $nodePath) => isset($this->fileSystemNodes[$path . Constants::PATH_SEP . $nodePath]));
        $this->fileSystemNodes[$path] = $node;
        $node->method('getId')->willReturn($this->nodeId++);

        return $node;
      },
    );
    $this->userStorage->method('putContent')->willReturnCallback(
      function(string $path, string $content): File {
        $node = $this->userStorage->get($path);
        $parent = $node->getParent();
        $file = $this->createStub(File::class);
        $file->method('getParent')->willReturn($parent);
        $file->method('getPath')->willReturn($path);
        $file->method('getName')->willReturn(basename($path));
        $file->method('getContent')->willReturn($content);
        $file->method('getMimeType')->willReturn($this->mimeTypeDetector->detectString($content));
        $file->method('getSize')->willReturn(strlen($content));
        $file->method('getType')->willReturn(Node::TYPE_FILE);
        $file->method('getId')->willReturn($node->getId());
        $file->method('getPermissions')->willReturnCallback(
          fn() => array_key_exists($path, $this->nodePermissions) ? $this->nodePermissions[$path] : CloudConstants::PERMISSION_ALL,
        );
        $file->method('delete')->willReturnCallback(
          function() use ($path) {
            unset($this->fileSystemNodes[$path]);
          });

        $this->fileSystemNodes[$path] = $file;

        return $file;
      }
    );
    $this->userStorage->method('delete')->willReturnCallback(
      function(string $path): void {
        $this->userStorage->get($path)?->delete();
      }
    );
    $this->userStorage->method('folderWalk')->willReturnCallback(
      function(mixed $folder) {
        $path = is_string($folder) ? $folder : $folder->getPath();
        $path .= Constants::PATH_SEP;
        $entries = array_filter(array_keys($this->fileSystemNodes), fn(string $nodePath) => str_starts_with($nodePath, $path));
        return count($entries);
      }
    );
    $this->userStorage->method('getFilesAppLink')->willReturnCallback(
      function(string|Node $pathOrNode) {
        if (is_string($pathOrNode)) {
          $nodePath = $pathOrNode;
        } else {
          $nodePath = $pathOrNode->getPath();
        }
        return $this->urlGenerator->linkToRoute('files.view.index', [ 'dir' => $nodePath ]);
      },
    );
    $this->userStorage->method('getDownloadLink')->willReturnCallback(
      function(string|Node $pathOrNode): string {
        $fileName = urlencode(is_string($pathOrNode) ? $pathOrNode : $pathOrNode->getPath());
        return 'https://host.domain.tld/somewhere/' . $fileName;
      },
    );
    $this->userStorage->method('getDatabaseFile')->willReturnCallback(
      fn(string $cloudPath) => $this->databaseDirEntries[$cloudPath] ?? null,
    );
    return $this->userStorage;
  }
}
