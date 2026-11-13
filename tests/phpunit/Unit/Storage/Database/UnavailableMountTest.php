<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2026 Claus-Justus Heine
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

namespace OCA\CAFEVDB\Tests\Unit\Storage\Database;

use PHPUnit\Framework\Attributes;
use PHPUnit\Framework\TestCase;

use OC\Files\Storage\FailedStorage;
use OCP\Files\Config\ICachedMountInfo;
use OCP\Files\StorageNotAvailableException;

use OCA\CAFEVDB\Storage\Database\MountProvider;
use OCA\CAFEVDB\Storage\Database\UnavailableCache;
use OCA\CAFEVDB\Storage\Database\UnavailableMount;

/** Test the unavailable database mount placeholder. */
#[Attributes\CoversClass(UnavailableCache::class)]
#[Attributes\CoversClass(UnavailableMount::class)]
#[Attributes\UsesClass(MountProvider::class)]
class UnavailableMountTest extends TestCase
{
  public function testPreservesCachedIdentityAndDeniesFilesystemAccess():void
  {
    $mount = $this->createMount();

    self::assertSame('/alice/files/Finance/Invoices/', $mount->getMountPoint());
    self::assertSame(17, $mount->getMountId());
    self::assertSame(MountProvider::class, $mount->getMountProvider());
    self::assertSame(MountProvider::MOUNT_TYPE, $mount->getMountType());
    self::assertSame(42, $mount->getNumericStorageId());
    self::assertSame(108, $mount->getStorageRootId());
    self::assertSame('cafevdb::database-storage::/finance/invoices/', $mount->getStorageId());

    $storage = $mount->getStorage();
    self::assertInstanceOf(FailedStorage::class, $storage);
    self::assertSame('cafevdb::database-storage::/finance/invoices/', $storage->getId());
    self::assertSame(-1, $storage->getCache()->getNumericStorageId());
    $this->expectException(StorageNotAvailableException::class);
    $storage->file_get_contents('invoice.pdf');
  }

  public function testCannotMoveMount():void
  {
    $mount = $this->createMount();

    $this->expectException(StorageNotAvailableException::class);
    $mount->setMountPoint('/alice/files/renamed/');
  }

  public function testCacheListingsFailInsteadOfAppearingEmpty():void
  {
    $mount = $this->createMount();

    $this->expectException(StorageNotAvailableException::class);
    $mount->getStorage()->getCache()->getFolderContents('');
  }

  private function createMount():UnavailableMount
  {
    $cachedMount = $this->createStub(ICachedMountInfo::class);
    $cachedMount->method('getUser')->willReturn($this->createStub(\OCP\IUser::class));
    $cachedMount->method('getStorageId')->willReturn(42);
    $cachedMount->method('getRootId')->willReturn(108);
    $cachedMount->method('getMountPoint')->willReturn('/alice/files/Finance/Invoices/');
    $cachedMount->method('getMountId')->willReturn(17);
    $cachedMount->method('getRootInternalPath')->willReturn('');
    $cachedMount->method('getMountProvider')->willReturn(MountProvider::class);

    return new UnavailableMount('cafevdb::database-storage::/finance/invoices/', $cachedMount);
  }
}
