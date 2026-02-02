<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2025, 2026 Claus-Justus Heine <himself@claus-justus-heine.de>
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

namespace OCA\CAFEVDB\Tests\Unit\Database\Doctrine\ORM\Entities;

use ReflectionProperty;
use Throwable;

use PHPUnit\Framework\Attributes;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

use OCA\CAFEVDB\Controller\EnumAddDocumentConflictAction;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types\EnumDirEntryType as DirEntryType;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Exceptions;
use OCA\CAFEVDB\Tests\MockProvider;
use OCA\CAFEVDB\Toolkit;

/** Test aspects of the DatabaseStorageFolder entity. */
#[Attributes\CoversClass(Entities\DatabaseStorageDirEntry::class)]
#[Attributes\CoversClass(Entities\DatabaseStorageFile::class)]
#[Attributes\CoversClass(Entities\DatabaseStorageFolder::class)]
#[Attributes\UsesClass(Entities\EncryptedFile::class)]
#[Attributes\UsesClass(Entities\File::class)]
#[Attributes\UsesClass(Entities\FileData::class)]
#[Attributes\UsesClass(EntityManager::class)]
#[Attributes\UsesClass(Toolkit\Exceptions\DatabaseEntityException::class)]
#[Attributes\UsesClass(Exceptions\DatabaseEntityExistsException::class)]
class DatabaseStorageFolderTest extends TestCase
{
  private const FOLDER_NAME = 'FolderName';
  private const FOLDER_ID = 1;

  private const FILE_BASENAME = 'file';
  private const FILE_EXTENSION = '.ext';
  private const FILE_NAME = self::FILE_BASENAME . self::FILE_EXTENSION;
  private const FILE_MIMETYPE = 'application/octett-stream';

  private Entities\EncryptedFile $file;

  private Entities\DatabaseStorageFile $dirEntryFile;

  private Entities\DatabaseStorageFolder $folder;

  /** @return void */
  public function setup(): void
  {
    $this->file = new Entities\EncryptedFile(
      fileName: self::FILE_NAME,
      mimeType: self::FILE_MIMETYPE,
    );
    $this->folder = new Entities\DatabaseStorageFolder()
      ->setName(self::FOLDER_NAME)
      ->setId(self::FOLDER_ID);
    $this->dirEntryFile = $this->folder->addDocument($this->file);
  }

  /** @return void */
  public function testConstruction(): void
  {
    $this->assertEquals(false, $this->folder->isEmpty());
    $this->assertEquals('httpd/unix-directory', $this->folder->getMimeType());
    $this->assertEquals(self::FILE_MIMETYPE, $this->file->getMimeType());
    $this->assertEquals(0, $this->file->getSize());
  }

  /** @return void */
  public function testAddDocumentNoConflict(): void
  {
    $newFile = new Entities\EncryptedFile('other' . self::FILE_NAME, mimeType: self::FILE_MIMETYPE);

    $dirEntryFile = $this->folder->addDocument($newFile);
    $this->assertEquals(2, $this->folder->getDocuments()->count());
    $this->assertEquals($this->folder, $dirEntryFile->getParent());
    $this->assertEquals($newFile->getFileName(), $dirEntryFile->getName());

    $prefix = 'other';
    $dirEntryFile = $this->folder->addDocument($newFile, $prefix . $newFile->getFileName());
    $this->assertEquals(3, $this->folder->getDocuments()->count());
    $this->assertEquals($this->folder, $dirEntryFile->getParent());
    $this->assertEquals($prefix . $newFile->getFileName(), $dirEntryFile->getName());
  }

  /**
   * @param ?EnumAddDocumentConflictAction $conflictAction
   *
   * @return void
   */
  public function testAddDocumentConflictDefault(
    ?EnumAddDocumentConflictAction $conflictAction = null,
  ): void {
    $newFile = new Entities\EncryptedFile(self::FILE_NAME, mimeType: self::FILE_MIMETYPE);
    try {
      if ($conflictAction) {
        $dirEntryFile = $this->folder->addDocument($newFile, conflictAction: $conflictAction);
      } else {
        $dirEntryFile = $this->folder->addDocument($newFile);
      }
    } catch (Throwable $t) {
      if (!($t instanceof Exceptions\DatabaseEntityExistsException)) {
        echo $t->getMessage() . PHP_EOL;
      }
      $this->assertInstanceOf(Exceptions\DatabaseEntityExistsException::class, $t);
      $this->assertEquals($this->dirEntryFile, $t->existingEntity);
      $this->assertEquals($newFile, $t->newEntity);
      $this->assertEquals(Entities\DatabaseStorageFile::class, $t->entityClassName);
    }

    $this->folder->removeDocument($this->file);
    $subFolder = $this->folder->addSubFolder($newFile->getFileName());
    try {
      if ($conflictAction) {
        $dirEntryFile = $this->folder->addDocument($newFile, conflictAction: $conflictAction);
      } else {
        $dirEntryFile = $this->folder->addDocument($newFile);
      }
    } catch (Throwable $t) {
      if (!($t instanceof Exceptions\DatabaseEntityExistsException)) {
        echo $t->getMessage() . PHP_EOL;
        echo $t->getTraceAsString() . PHP_EOL;
      }
      $this->assertInstanceOf(Exceptions\DatabaseEntityExistsException::class, $t);
      $this->assertEquals($subFolder, $t->existingEntity);
      $this->assertEquals($newFile, $t->newEntity);
      $this->assertEquals(Entities\DatabaseStorageFolder::class, $t->entityClassName);
    }
  }

  /** @return void */
  public function testAddDocumentConflictFail(): void
  {
    $this->testAddDocumentConflictDefault(EnumAddDocumentConflictAction::FAIL);
  }

  /** @return void */
  public function testAddDocumentConflictReplace(): void
  {
    $conflictAction = EnumAddDocumentConflictAction::REPLACE;
    $newFile = new Entities\EncryptedFile(self::FILE_NAME, mimeType: self::FILE_MIMETYPE);
    $dirEntryFile = $this->folder->addDocument($newFile, conflictAction: $conflictAction);
    $this->assertEquals($this->dirEntryFile, $dirEntryFile);

    $this->folder->removeDocument($newFile);
    $subFolder = $this->folder->addSubFolder($newFile->getFileName());
    try {
      $dirEntryFile = $this->folder->addDocument($newFile, conflictAction: $conflictAction);
    } catch (Throwable $t) {
      if (!($t instanceof Exceptions\DatabaseEntityExistsException)) {
        echo $t->getMessage() . PHP_EOL;
        echo $t->getTraceAsString() . PHP_EOL;
      }
      $this->assertInstanceOf(Exceptions\DatabaseEntityExistsException::class, $t);
      $this->assertEquals($subFolder, $t->existingEntity);
      $this->assertEquals($newFile, $t->newEntity);
      $this->assertEquals(Entities\DatabaseStorageFolder::class, $t->entityClassName);
    }
  }

  /** @return void */
  public function testAddDocumentConflictRename(): void
  {
    $dirEntryFiles = [
      $this->dirEntryFile,
    ];
    // addDocuments() calls flush().
    $entityManager = $this->getMockBuilder(EntityManager::class)
      ->disableOriginalConstructor()
      ->getMock();
    $instance = new ReflectionProperty($entityManager, 'instance');
    $instance->setValue($entityManager, $entityManager);
    $entityManager->expects($this->atLeastOnce())->method('flush')->willReturnCallback(function() {});

    $conflictAction = EnumAddDocumentConflictAction::RENAME;
    $rounds = 2;
    while ($rounds-- > 0) {
      $newFile = new Entities\EncryptedFile(self::FILE_NAME, mimeType: self::FILE_MIMETYPE);
      $dirEntryFiles[] = $dirEntryFile = $this->folder->addDocument($newFile, conflictAction: $conflictAction);
      $this->assertEquals($newFile, $dirEntryFile->getFile());
      $this->assertEquals(count($dirEntryFiles), $this->folder->getDirectoryEntries()->count());
      foreach ($this->folder->getDirectoryEntries() as $entry) {
        $dirEntryIndex = array_search($entry, $dirEntryFiles, strict: true);
        $this->assertNotFalse($dirEntryIndex);
        if ($dirEntryIndex == count($dirEntryFiles) - 1) {
          // the most recent has the correct name
          $this->assertEquals(self::FILE_NAME, $entry->getName());
        } else {
          // the other get new names, but unordered, only the conflicting node
          // is renamed.
          $base = basename($entry->getName(), self::FILE_EXTENSION);
          $backupNumber = $dirEntryIndex + 1;
          $this->assertStringEndsWith('~' . $backupNumber, $base);
        }
      }
    }
  }

  /** @return void */
  public function testToString(): void
  {
    $this->assertEquals(DirEntryType::FOLDER->value . ':' . $this->folder->getName(), (string)$this->folder);
  }
}
