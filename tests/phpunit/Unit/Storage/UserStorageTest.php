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

use Exception;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

use PHPUnit\Framework\Attributes;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

use OCP\Files\File;
use OCP\Files\FileInfo;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\Node;
use OCP\Files\NotFoundException as FileNotFoundException;

use OCA\CAFEVDB\Constants;
use OCA\CAFEVDB\Storage\UserStorage;
use OCA\CAFEVDB\Tests\MockProvider;
use OCA\RotDrop\Tests\DeprecationException;

/** Test aspects of the UserStorage class. */
#[Attributes\CoversClass(UserStorage::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Common\Util::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\L10N\L10NFactory::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\Registration::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Wrapped\OCA\RotDrop\Toolkit\AppInfo\AbstractApplication::class)]
class UserStorageTest extends TestCase
{
  use \OCA\CAFEVDB\Tests\Unit\Database\Doctrine\EnableDeprecationsTrait;
  use \OCA\CAFEVDB\Tests\Unit\Storage\RootFolderStubTrait;
  use \OCA\CAFEVDB\Wrapped\OCA\RotDrop\Toolkit\Traits\UserRootFolderTrait;

   /** {@inheritdoc} */
  public function setup(): void
  {
    self::enableDeprecations();

    $this->mockProvider = $this->mockProvider ?? MockProvider::create($this);

    $this->getUserStorage();
  }

  /** @return void */
  public function testSetup(): void
  {
    $this->assertInstanceOf(UserStorage::class, $this->userStorage);

    // the constructor installes the current user, so see that this was successful.
    $cloudUser = $this->mockProvider->getUser();

    $this->assertEquals($cloudUser, $this->userStorage->getUser());
    $this->assertEquals($cloudUser->getUID(), $this->userStorage->getUserId());
    $userFolder = $this->userStorage->get('');
    $this->assertInstanceOf(IRootFolder::class, $userFolder->getParent()->getParent());
    $this->assertInstanceOf(IRootFolder::class, $this->userStorage->getRoot());
  }

  private const TEST_PATHS = [
    Constants::PATH_SEP => null,
    Constants::PATH_SEP . 'hello.pdf' => 'hello.pdf',
    Constants::PATH_SEP . 'a' . Constants::PATH_SEP . 'b' . Constants::PATH_SEP . 'c' => './////.//a//b///c////',
  ];

  /** @return void */
  public function testNormalizePath(): void
  {
    foreach (self::TEST_PATHS as $output => $input) {
      $this->assertEquals($output, UserStorage::normalizePath($input));
    }
  }

  /** @return void */
  public function testGetUserPath(): void
  {
    $userFolderPrefix = $this->getUserFolderPath($this->mockProvider->getUser()->getUID());
    $node = $this
      ->getMockBuilder(Node::class)
      ->disableOriginalConstructor()
      ->getMock();
    $userFolderPath = UserStorage::normalizePath('blahblahblah' . Constants::PATH_SEP . 'blub');
    $node
      ->expects($this->once())
      ->method('getPath')
      ->willReturn(
        UserStorage::normalizePath($userFolderPrefix . Constants::PATH_SEP . $userFolderPath),
      );
    $this->assertEquals($userFolderPath, $this->userStorage->getUserPath($node));
  }

  private const PATH_CAT_TESTS = [
    [
      'a', 'b', Constants::PATH_SEP . 'a' . Constants::PATH_SEP . 'b',
    ],
    [
      ['a', 'b'], null, Constants::PATH_SEP . 'a' . Constants::PATH_SEP . 'b',
    ],
    [
      ['a', 'b'], '', Constants::PATH_SEP . 'a' . Constants::PATH_SEP . 'b',
    ],
    [
      ['a', 'b'], 'c', Constants::PATH_SEP . 'a' . Constants::PATH_SEP . 'b' . Constants::PATH_SEP . 'c',
    ],
    [
      ['.//./a/', '/////b/./'], null, Constants::PATH_SEP . 'a' . Constants::PATH_SEP . 'b',
    ],
  ];

  /** @return void */
  public function testPathCat(): void
  {
    foreach (self::PATH_CAT_TESTS as $test) {
      $this->assertEquals($test[2], UserStorage::pathCat($test[0], $test[1]));
    }
  }

  private const STRIP_PARENTS_TEST = [
    [ 'a/b/c', 2, Constants::PATH_SEP . 'c' ],
    [ 'a/b/c', 3, Constants::PATH_SEP ],
    [ 'a/b/c', 100, Constants::PATH_SEP ],
    [ './////./a/./////b/c////./', 2, Constants::PATH_SEP . 'c' ],
  ];

  /** @return void */
  public function testStripParents(): void
  {
    foreach (self::STRIP_PARENTS_TEST as $test) {
      $this->assertEquals($test[2], UserStorage::stripParents($test[0], $test[1]));
    }
  }

  /** @return void */
  public function testSetUser(): void
  {
    $this->userStorage->setUser(null);
    $this->assertNull($this->userStorage->getUser());
    $this->assertNull($this->userStorage->getUserId());
    $this->assertNull($this->userStorage->getRoot());

    $user = $this->mockProvider->getUser();
    $this->userStorage->setUser($user);
    $this->assertEquals($user, $this->userStorage->getUser());
    $this->assertEquals($user->getUID(), $this->userStorage->getUserId());
    $this->assertInstanceOf(IRootFolder::class, $this->userStorage->getRoot());
  }

  private const DEPRECATED_METHODS = [
    'user' => [],
    'userId' => [],
  ];

  /** @return void */
  public function testDeprecations(): void
  {
    foreach (self::DEPRECATED_METHODS as $method => $methodArgs) {
      try {
        $this->userStorage->{$method}(...$methodArgs);
        $this->assertFalse(true, "Calling {$method}() should trigger a deprecation");
      } catch (Throwable $t) {
        $this->assertInstanceOf(DeprecationException::class, $t);
      }
    }
  }


  /** @return void */
  public function testPutGetContent(): void
  {
    // Test error handling
    $nodePath = 'blah-01';
    $this->userStorage->ensureFolder($nodePath);
    try {
      $this->userStorage->getContent($nodePath);
    } catch (Throwable $t) {
      $this->assertInstanceOf(InvalidArgumentException::class, $t);
    }
    $nodePath = 'blah-02';
    try {
      $this->userStorage->getContent($nodePath);
    } catch (Throwable $t) {
      $this->assertInstanceOf(FileNotFoundException::class, $t);
    }
    $nodePath = 'blah-03';
    $node = $this->userStorage->putContent($nodePath, 'DATA');
    $node->method('getContent')->willReturnCallback(fn() => throw new Exception('Just an ariticial exception!'));
    try {
      $this->userStorage->getContent($nodePath);
    } catch (Throwable $t) {
      $this->assertInstanceOf(RuntimeException::class, $t);
    }

    // Test consistency
    $data = 'DATA';
    $nodePath = 'blah-04';
    $node = $this->userStorage->putContent($nodePath, $data);
    $this->assertInstanceOf(File::class, $node);
    $this->assertEquals($data, $this->userStorage->getContent($nodePath));
    $this->assertEquals($data, $node->getContent());
  }

  /** @return void */
  public function testGetFileFolder(): void
  {
    $nodePath = 'prefix/blah-01';
    $this->userStorage->ensureFolder($nodePath);
    $node = $this->userStorage->getFolder($nodePath);
    $this->assertInstanceOf(Folder::class, $node);
    $this->assertEquals(UserStorage::normalizePath($nodePath), $this->userStorage->getUserPath($node));

    $node = $this->userStorage->getFile($nodePath);
    $this->assertNull($node);

    try {
      $node = $this->userStorage->get($nodePath, type: FileInfo::TYPE_FILE, throw: true);
    } catch (Throwable $t) {
      $this->assertInstanceOf(FileNotFoundException::class, $t);
    }

    $nodePath = 'prefix/blah-02';
    $this->userStorage->putContent($nodePath, 'DATA');
    $node = $this->userStorage->getFile($nodePath);
    $this->assertInstanceOf(File::class, $node);
    $this->assertEquals(UserStorage::normalizePath($nodePath), $this->userStorage->getUserPath($node));

    $node = $this->userStorage->getFolder($nodePath);
    $this->assertNull($node);

    try {
      $node = $this->userStorage->get($nodePath, type: FileInfo::TYPE_FOLDER, throw: true);
    } catch (Throwable $t) {
      $this->assertInstanceOf(FileNotFoundException::class, $t);
    }

    $nodePath = 'prefix/blah-03';
    $node = $this->userStorage->getFile($nodePath);
    $this->assertNull($node);
    $node = $this->userStorage->getFolder($nodePath);
    $this->assertNull($node);
    try {
      $this->userStorage->get($nodePath, type: FileInfo::TYPE_FILE, throw: true);
    } catch (Throwable $t) {
      $this->assertInstanceOf(FileNotFoundException::class, $t);
    }
    try {
      $this->userStorage->get($nodePath, type: FileInfo::TYPE_FOLDER, throw: true);
    } catch (Throwable $t) {
      $this->assertInstanceOf(FileNotFoundException::class, $t);
    }
  }

  /** @return void */
  public function testCreateDataUri(): void
  {
    $data = '# Heading
## Sub Heading
- one item
- another one
';
    $file = $this->userStorage->putContent('a-file.md', $data);
    $uri = $this->userStorage->createDataUri($file);
    $this->assertEquals('text/plain', mime_content_type($uri));
    $this->assertEquals('data', parse_url($uri, PHP_URL_SCHEME));
    $this->assertEquals($data, file_get_contents($uri));
  }

  /** @return void */
  public function testCreateFilesAppLink(): void
  {
    $nodePath = 'a-folder/a-child-folder/a-file.txt';
    $node = $this->userStorage->putContent($nodePath, '');
    $url = $this->userStorage->getFilesAppLink($node);
    $url2 = $this->userStorage->getFilesAppLink($nodePath);
    $this->assertEquals($url, $url2);
    $parts = parse_url($url);
    $this->assertEquals('dir=' . UserStorage::normalizePath(dirname($nodePath)), $parts['query']);

    $nodePath = 'level-1/level-2/level-3';
    $node = $this->userStorage->ensureFolder($nodePath);

    $url = $this->userStorage->getFilesAppLink($node, subDir: false);
    $url2 = $this->userStorage->getFilesAppLink($nodePath, subDir: false);
    $this->assertEquals($url, $url2);
    $parts = parse_url($url);
    $this->assertEquals('dir=' . UserStorage::normalizePath(dirname($nodePath)), $parts['query']);

    $url = $this->userStorage->getFilesAppLink($node, subDir: true);
    $url2 = $this->userStorage->getFilesAppLink($nodePath, subDir: true);
    $this->assertEquals($url, $url2);
    $parts = parse_url($url);
    $this->assertEquals('dir=' . UserStorage::normalizePath($nodePath), $parts['query']);
  }
}
