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

use Psr\Container\ContainerInterface;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\NotFoundException;
use OCP\Files\IRootFolder;
use OCP\Files\Node;
use OCP\IURLGenerator;

use OCA\CAFEVDB\Constants;
use OCA\CAFEVDB\Storage\UserStorage;
use OCA\CAFEVDB\Tests\MockProvider;

/** Fabricate a stub or mock intercepting file system access. */
trait MockUserStorageTrait
{
  private UserStorage $userStorage;

  private MockProvider $mockProvider;

  private ContainerInterface $appContainer;

  private IURLGenerator $urlGenerator;

  private array $fileNodes = [];

  private int $nodeId = 1;

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
        $node = $this->userStorage->get($path);
        if ($node->getType() == Node::TYPE_FILE) {
          return $node;
        }
      },
    );
    $this->userStorage->method('get')->willReturnCallback(
      function(string $path, ?string $type = null, bool $useCache = false, bool $throw = false) {
        if ($this->fileNodes[$path] ?? null) {
          return $this->fileNodes[$path];
        }
        if ($path == '/' || $path == '') {
          $node = $this->createStub(IRootFolder::class);
          $this->assertInstanceOf(IRootFolder::class, $node);
        } else {
          $node = $this->createStub(Folder::class);
        }
        $node->method('getType')->willReturn(Node::TYPE_FOLDER);
        $node->method('getPath')->willReturn($path);
        $node->method('getName')->willReturn(basename($path));
        $parent = dirname($path);
        if ($parent != $path) {
          // echo 'PARENT ' . $parent . PHP_EOL;
          $parent = $this->userStorage->get($parent);
          $node->method('getParent')->willReturn($parent);
        }
        $this->fileNodes[$path] = $node;
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
        $file->method('getType')->willReturn(Node::TYPE_FILE);
        $file->method('getId')->willReturn($node->getId());
        $file->method('delete')->willReturnCallback(
          function() use ($path) {
            unset($this->fileNodes[$path]);
          });

        $this->fileNodes[$path] = $file;

        return $file;
      }
    );
    $this->userStorage->method('folderWalk')->willReturnCallback(
      function(mixed $folder) {
        $path = is_string($folder) ? $folder : $folder->getPath();
        $path .= Constants::PATH_SEP;
        $entries = array_filter(array_keys($this->fileNodes), fn(string $nodePath) => str_starts_with($nodePath, $path));
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

    return $this->userStorage;
  }
}
