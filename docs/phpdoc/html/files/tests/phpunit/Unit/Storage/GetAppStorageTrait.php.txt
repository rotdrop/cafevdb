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

use OCP\Files\IAppData;
use OCP\Files\SimpleFS\ISimpleFile;
use OCP\Files\SimpleFS\ISimpleFolder;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;

use OCA\CAFEVDB\Common\TimeFactory;
use OCA\CAFEVDB\Storage\AppStorage;
use OCA\CAFEVDB\Tests\MockProvider;

/** Mock IAppData up to the point that we can fake file-access. */
trait GetAppStorageTrait
{
  private AppStorage $appStorage;

  private IAppData $appData;

  private MockProvider $mockProvider;

  private array $nodes = [];

  private array $topLevelFolders = [];

  private $folderFactory;

  private TimeFactory $timeFactory;

  /**
   * @param bool $mock If \true generate a mock, else a stub, defaults to \false.
   *
   * @return mixed The mocked or stubbed object.
   */
  private function getAppDataStub(bool $mock = false): mixed
  {
    $this->mockProvider = $this->mockProvider ?? MockProvider::create($this);
    $appContainer = $this->mockProvider->getAppContainer();
    $this->timeFactory = $appContainer->get(TimeFactory::class);

    if ($mock) {
      $this->appData = $this->getMockBuilder(IAppData::class)
        ->disableOriginalConstructor()
        ->getMock();
    } else {
      $this->appData = $this->createStub(IAppData::class);
    }
    $appDataId = spl_object_id($this->appData);
    $this->nodes[$appDataId] = [
      'name' => '/',
      'node' => $this->appData,
      'content' => [],
      'parent' => -1,
    ];

    $this->folderFactory = function(string $name, int $parentId) {
      $folder = $this->nodes[$parentId]['content'][$name] ?? null;
      if ($folder) {
        return $folder;
      }
      $folder = $this->createStub(ISimpleFolder::class);
      $this->nodes[$parentId]['content'][$name] = $folder;
      $folderId = spl_object_id($folder);
      $this->nodes[$folderId] = [
        'name' => $name,
        'node' => $folder,
        'content' => [],
        'parent' => $parentId,
      ];
      $folder->method('getName')->willReturn($name);
      $folder->method('getDirectoryListing')->willReturnCallback(fn() => $this->nodes[$folderId]['content']);
      $folder->method('delete')->willReturnCallback(
        function() use ($folderId, $name, $parentId) {
          if ($parentId) {
            unset($this->nodes[$parentId]['content'][$name]);
          }
          unset($this->nodes[$folderId]);
        });
      $folder->method('fileExists')->willReturnCallback(
        function(string $nodeName) use ($folderId) {
          return !empty($this->nodes[$folderId]['content'][$nodeName]);
        },
      );
      $folder->method('getFile')->willReturnCallback(
        function(string $nodeName) use ($folderId) {
          if (empty($this->nodes[$folderId]['content'][$nodeName])) {
            throw new NotFoundException;
          }
          return $this->nodes[$folderId]['content'][$nodeName];
        },
      );
      $folder->method('newFile')->willReturnCallback(
        function(string $nodeName, $content = null) use ($folderId) {
          $node = $this->createStub(ISimpleFile::class);
          $nodeId = spl_object_id($node);
          $this->nodes[$nodeId] = [
            'name' => $nodeName,
            'node' => $node,
            'content' => $content,
            'parent' => $folderId,
          ];
          $this->nodes[$folderId]['content'][$nodeName] = $node;
          $node->method('getName')->willReturn($nodeName);
          $node->method('delete')->willReturnCallback(
            function() use ($nodeId, $folderId, $nodeName) {
              unset($this->nodes[$folderId]['content'][$nodeName]);
              unset($this->nodes[$nodeId]);
            }
          );
          $node->method('getContent')->willReturnCallback(fn() => $this->nodes[$nodeId]['content']);
          $node->method('putContent')->willReturnCallback(
            function($data) use ($nodeId) {
              $this->nodes[$nodeId]['content'] = $data;
              $this->nodes[$nodeId]['mtime'] = $this->timeFactory->now()->getTimestamp();
            },
          );
          $node->method('getMTime')->willReturnCallback(fn() => $this->nodes[$nodeId]['mtime']);

          return $node;
        },
      );
      $folder->method('getFolder')->willReturnCallback(
        function(string $nodeName) use ($folderId) {
          if (empty($this->nodes[$folderId]['content'][$nodeName])) {
            throw new NotFoundException;
          }
          return $this->nodes[$folderId]['content'][$nodeName];
        },
      );
      $folder->method('newFolder')->willReturnCallback(
        function(string $nodeName) use ($folderId) {
          $subFolder = $this->folderFactory($nodeName, $folderId);
          $this->nodes[$folderId]['content'][$nodeName] = $subFolder;
          return $subFolder;
        }
      );

      return $folder;
    };

    $this->appData->method('getFolder')->willReturnCallback(
      fn(string $nodeName) => ($this->folderFactory)($nodeName, $appDataId),
    );
    $this->appData->method('newFolder')->willReturnCallback(
      fn(string $nodeName) => ($this->folderFactory)($nodeName, $appDataId),
    );
    $this->appData->method('getDirectoryListing')->willReturnCallback(
      fn() => $this->nodes[$appDataId]['content'],
    );

    return $this->appData;
  }

  /**
   * @param bool $appDataMock If \true the underlying IAppData will be mocked,
   * otherwise stubbed.
   *
   * @return AppStorage
  */
  private function getAppStorage(bool $appDataMock = false): AppStorage
  {
    $this->mockProvider = $this->mockProvider ?? MockProvider::create($this);

    $this->appStorage = new AppStorage(
      appData: $this->getAppDataStub(),
      logger: $this->mockProvider->getLoggerInterface(),
      l: $this->mockProvider->getL10N(),
    );

    return $this->appStorage;
  }
}
