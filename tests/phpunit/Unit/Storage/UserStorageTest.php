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

use Throwable;

use PHPUnit\Framework\Attributes;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

use OCP\Files\IRootFolder;
use OCP\Files\Node;

use OCA\CAFEVDB\Constants;
use OCA\CAFEVDB\Storage\UserStorage;
use OCA\CAFEVDB\Tests\MockProvider;
use OCA\RotDrop\Tests\DeprecationException;

/** Test aspects of the UserStorage class. */
#[Attributes\CoversClass(UserStorage::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Common\Util::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\L10N\L10NFactory::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\Registration::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Toolkit\AppInfo\AbstractApplication::class)]
class UserStorageTest extends TestCase
{
  use \OCA\CAFEVDB\Tests\Unit\Database\Doctrine\EnableDeprecationsTrait;
  use \OCA\CAFEVDB\Tests\Unit\Storage\RootFolderStubTrait;
  use \OCA\CAFEVDB\Toolkit\Traits\UserRootFolderTrait;

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
}
