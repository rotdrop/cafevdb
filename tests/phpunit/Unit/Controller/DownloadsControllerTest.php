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

namespace OCA\CAFEVDB\Tests\Unit\Controller;

use ZipArchive;

use PHPUnit\Framework\Attributes;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataDownloadResponse;
use OCP\AppFramework\Http\DataResponse;
use OCP\ICache as FileCache;

use OCA\CAFEVDB\Controller\DownloadsController;
use OCA\RotDrop\Tests\DeprecationException;
use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Tests\MockProvider;

/** Test the EntityRepositoryController. */
#[Attributes\CoversClass(DownloadsController::class)]
#[Attributes\CoversClass(\OCA\CAFEVDB\Storage\DatabaseStorageUtil::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Common\Transliterator::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Common\Util::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Crypto\HaliteCryptoFactory::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Crypto\HaliteSymmetricStreamCryptor::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\DatabaseStorageDirEntry::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\DatabaseStorageFile::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\EncryptedFile::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\File::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\FileData::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Events\EncryptionServiceBound::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\ConfigService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\EncryptionService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\L10N\L10NFactory::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\Registration::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Toolkit\AppInfo\AbstractApplication::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Database\Doctrine\ORM\Traits\ArrayTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Database\Doctrine\ORM\Traits\AutoIncrementTrait::class)]
class DownloadsControllerTest extends TestCase
{
  use TestRoutesAreDefinedTrait;
  use \OCA\CAFEVDB\Tests\Unit\Database\MockEntityManagerTrait;

  private const CONTROLLER_CLASS = DownloadsController::class;
  private const EXPECTED_ROUTES = [ 'get', 'fetch' ];

  private DownloadsController $downloadsController;

  /** {@inheritdoc} */
  public function setup(): void
  {
    DeprecationException::throwOnDeprecations(exclude: '/OCP\\\\IConfig\\:\\:(get|set|delete)AppValue/');

    $this->getEntityManagerMock();
    $this->entityManager->expects($this->never())->method('recryptEncryptedProperties');

    $this->mockProvider = $this->mockProvider ?? MockProvider::create($this);

    $this->downloadsController = new DownloadsController(
      appName: $this->mockProvider->appName,
      configService: $this->mockProvider->getConfigService(),
      entityManager: $this->entityManager,
      request: $this->mockProvider->getRequest(),
    );
  }

  /** @return void */
  public function tearDown(): void
  {
    restore_error_handler();
  }

  /** @return void */
  public function testConstruction(): void
  {
  }

  private const CACHE_KEY = 'c06eb90c-085b-11f1-b907-9dc97e475214';
  // Keep in sync with the email-form composer!
  private const CACHE_META_DATA = [
    'data' => self::CACHE_KEY,
    'name' => 'ä-file-name.pdf.html',
    'size' => 768,
    'encoding' => '8bit',
    'mimeType' => 'text/html',
  ];
  private const CACHE_DATA = '<!DOCTYPE html>
<html lang="[de]">
  <head>
    <title>TITLE</title>
  </head>
  <body>
    <div>Content</div>
  </body>
</html>';

  /** @return void */
  public function testFetchFromFileCache(): void
  {
    /** @var FileCache $fileCache */
    $fileCache = $this->getMockBuilder(FileCache::class)
      ->disableOriginalConstructor()
      ->getMock();
    $fileCache->expects($this->exactly(2))->method('get')->willReturnMap([
      [self::CACHE_KEY, self::CACHE_DATA],
      [self::CACHE_KEY . '-meta', json_encode(self::CACHE_META_DATA)],
    ]);
    $this->mockProvider->registerClassInstance(FileCache::class, $fileCache, global: true);

    $result = $this->downloadsController->fetch(
      section: DownloadsController::SECTION_FILECACHE,
      object: self::CACHE_KEY,
    );
    $this->assertInstanceOf(DataDownloadResponse::class, $result);
    /** @var DataDownloadResponse $result */
    $this->assertEquals(Http::STATUS_OK, $result->getStatus());
    $headers = $result->getHeaders();
    $this->assertArrayHasKey('Content-Type', $headers);
    $this->assertEquals(self::CACHE_META_DATA['mimeType'], $headers['Content-Type']);
    // [Content-Disposition] => attachment; filename="a-file-name.pdf.html"; filename*=UTF-8''a-file-name.pdf.html
    $this->assertArrayHasKey('Content-Disposition', $headers);
    preg_match_all('/(?:filename="([^"]+)"|filename\\*=UTF-8\'\'([^ ]+)(?: |$))/', $headers['Content-Disposition'], $matches);
    $transliteratedFileName = $matches[1][0] ?? null;
    $encodedFileName = $matches[2][1] ?? null;
    $this->assertNotNull($transliteratedFileName);
    $this->assertNotNull($encodedFileName);
    $this->assertEquals('ae' . mb_substr(self::CACHE_META_DATA['name'], 1), $transliteratedFileName);
    $this->assertEquals(self::CACHE_META_DATA['name'], urldecode($encodedFileName));
    $data = $result->render();
    $this->assertEquals(self::CACHE_DATA, $data);
  }

  /** @return void */
  public function testFetchFromFileCacheWithMissingMetaData(): void
  {
    /** @var FileCache $fileCache */
    $fileCache = $this->getMockBuilder(FileCache::class)
      ->disableOriginalConstructor()
      ->getMock();
    $fileCache->expects($this->exactly(2))->method('get')->willReturnMap([
      [self::CACHE_KEY, self::CACHE_DATA],
      [self::CACHE_KEY . '-meta', null],
    ]);
    $this->mockProvider->registerClassInstance(FileCache::class, $fileCache, global: true);

    $result = $this->downloadsController->fetch(
      section: DownloadsController::SECTION_FILECACHE,
      object: self::CACHE_KEY,
    );
    $this->assertInstanceOf(DataDownloadResponse::class, $result);
    /** @var DataDownloadResponse $result */
    $this->assertEquals(Http::STATUS_OK, $result->getStatus());
    $headers = $result->getHeaders();
    $this->assertArrayHasKey('Content-Type', $headers);
    $this->assertEquals(self::CACHE_META_DATA['mimeType'], $headers['Content-Type']);
    // [Content-Disposition] => attachment; filename="a-file-name.pdf.html"; filename*=UTF-8''a-file-name.pdf.html
    $this->assertArrayHasKey('Content-Disposition', $headers);
    preg_match_all('/(?:filename="([^"]+)"|filename\\*=UTF-8\'\'([^ ]+)(?: |$))/', $headers['Content-Disposition'], $matches);
    $transliteratedFileName = $matches[1][0] ?? null;
    $encodedFileName = $matches[2][1] ?? null;
    $this->assertNotNull($transliteratedFileName);
    $this->assertNotNull($encodedFileName);
    $expectedFileName = implode('-', [
      $this->mockProvider->appName,
      MockProvider::EXECUTIVE_BOARD_UID,
      self::CACHE_KEY,
    ]);
    $expectedFileName .= '.html';
    $this->assertEquals($expectedFileName, $transliteratedFileName);
    $this->assertEquals($expectedFileName, $encodedFileName);
    $data = $result->render();
    $this->assertEquals(self::CACHE_DATA, $data);
  }

  /** @return void */
  public function testFetchFromFileCacheWithBrokenMetaData(): void
  {
    /** @var FileCache $fileCache */
    $fileCache = $this->getMockBuilder(FileCache::class)
      ->disableOriginalConstructor()
      ->getMock();
    $fileCache->expects($this->exactly(2))->method('get')->willReturnMap([
      [self::CACHE_KEY, self::CACHE_DATA],
      [self::CACHE_KEY . '-meta', '"{a,"'],
    ]);
    $this->mockProvider->registerClassInstance(FileCache::class, $fileCache, global: true);

    $result = $this->downloadsController->fetch(
      section: DownloadsController::SECTION_FILECACHE,
      object: self::CACHE_KEY,
    );
    $this->assertInstanceOf(DataDownloadResponse::class, $result);
    /** @var DataDownloadResponse $result */
    $this->assertEquals(Http::STATUS_OK, $result->getStatus());
    $headers = $result->getHeaders();
    $this->assertArrayHasKey('Content-Type', $headers);
    $this->assertEquals(self::CACHE_META_DATA['mimeType'], $headers['Content-Type']);
    // [Content-Disposition] => attachment; filename="a-file-name.pdf.html"; filename*=UTF-8''a-file-name.pdf.html
    $this->assertArrayHasKey('Content-Disposition', $headers);
    preg_match_all('/(?:filename="([^"]+)"|filename\\*=UTF-8\'\'([^ ]+)(?: |$))/', $headers['Content-Disposition'], $matches);
    $transliteratedFileName = $matches[1][0] ?? null;
    $encodedFileName = $matches[2][1] ?? null;
    $this->assertNotNull($transliteratedFileName);
    $this->assertNotNull($encodedFileName);
    $expectedFileName = implode('-', [
      $this->mockProvider->appName,
      MockProvider::EXECUTIVE_BOARD_UID,
      self::CACHE_KEY,
    ]);
    $expectedFileName .= '.html';
    $this->assertEquals($expectedFileName, $transliteratedFileName);
    $this->assertEquals($expectedFileName, $encodedFileName);
    $data = $result->render();
    $this->assertEquals(self::CACHE_DATA, $data);
  }

  /** @return void */
  public function testFetchFromFileCacheWithMissingData(): void
  {
    /** @var FileCache $fileCache */
    $fileCache = $this->getMockBuilder(FileCache::class)
      ->disableOriginalConstructor()
      ->getMock();
    $fileCache->expects($this->exactly(1))->method('get')->willReturn(null);
    $this->mockProvider->registerClassInstance(FileCache::class, $fileCache, global: true);

    $result = $this->downloadsController->fetch(
      section: DownloadsController::SECTION_FILECACHE,
      object: self::CACHE_KEY,
    );
    $this->assertInstanceOf(DataResponse::class, $result);
    /** @var DataResponse $result */
    $this->assertEquals(Http::STATUS_BAD_REQUEST, $result->getStatus());
    $data = $result->getData();
    $this->assertTrue(is_array($data));
    foreach (['class', 'file', 'line', 'value', 'message', 'messages'] as $key) {
      $this->assertArrayHasKey($key, $data);
    }
  }

  public const DB_FILES = [
    'fileOne.pdf' => 'application/pdf',
    'fileTwo.html' => 'text/html',
    'fileThree.png' => 'image/png',
  ];
  /**
   * Generate a couple of Entities\DatabaseStorageFile instances for testing.
   *
   * @return array
   */
  private function generateDatabasFileNodes(): array
  {
    $id = 1;
    $files = [];
    foreach (self::DB_FILES as $fileName => $mimeType) {
      $file = new Entities\EncryptedFile(
        fileName: $fileName,
        data: str_repeat('X', $id * 16),
        mimeType: $mimeType,
      );
      $dbFile = new Entities\DatabaseStorageFile;
      $dbFile->setFile($file)->setName('ädb-' . $fileName);
      $this->entityManager->persist($dbFile);
      $files[] = $dbFile;
      ++$id;
    }
    $this->entityManager->flush();
    $id = 1;
    foreach ($files as $dbFile) {
      $entityId = $dbFile->getId();
      $this->assertEquals($id, $entityId);
      $this->assertEquals($dbFile, $this->entityManager->getRepository(Entities\DatabaseStorageFile::class)->find($id));
      ++$id;
    }
    return $files;
  }


  /** @return void */
  public function testFetchFromDatabaseSingle(): void
  {
    $dbFiles = $this->generateDatabasFileNodes();
    $dbFile = $dbFiles[0];
    $result = $this->downloadsController->fetch(
      section: DownloadsController::SECTION_DATABASE,
      object: $dbFile->getId(),
    );
    $this->assertInstanceOf(DataDownloadResponse::class, $result);
    /** @var DataDownloadResponse $result */
    $this->assertEquals(Http::STATUS_OK, $result->getStatus());
    $headers = $result->getHeaders();
    $this->assertArrayHasKey('Content-Type', $headers);
    $this->assertEquals($dbFile->getMimeType(), $headers['Content-Type']);
    // [Content-Disposition] => attachment; filename="a-file-name.pdf.html"; filename*=UTF-8''a-file-name.pdf.html
    $this->assertArrayHasKey('Content-Disposition', $headers);
    preg_match_all('/(?:filename="([^"]+)"|filename\\*=UTF-8\'\'([^ ]+)(?: |$))/', $headers['Content-Disposition'], $matches);
    $transliteratedFileName = $matches[1][0] ?? null;
    $encodedFileName = $matches[2][1] ?? null;
    $this->assertNotNull($transliteratedFileName);
    $this->assertNotNull($encodedFileName);
    $this->assertEquals('ae' . mb_substr($dbFile->getName(), 1), $transliteratedFileName);
    $this->assertEquals($dbFile->getName(), urldecode($encodedFileName));
    $data = $result->render();
    $this->assertEquals($dbFile->getData(), $data);
  }

  /** @return void */
  public function testFetchFromDatabaseCollectionArchive(): void
  {
    $zipFileBase = 'äfolder';
    $dbFiles = $this->generateDatabasFileNodes();
    $result = $this->downloadsController->fetch(
      section: DownloadsController::SECTION_DATABASE,
      object: DownloadsController::OBJECT_COLLECTION,
      items: array_map(fn($entity) => $entity->getId(), $dbFiles),
      fileName: $zipFileBase,
    );
    $this->assertInstanceOf(DataDownloadResponse::class, $result);
    /** @var DataDownloadResponse $result */
    $this->assertEquals(Http::STATUS_OK, $result->getStatus());
    $headers = $result->getHeaders();
    $this->assertArrayHasKey('Content-Type', $headers);
    $this->assertEquals('application/zip', $headers['Content-Type']);
    preg_match_all('/(?:filename="([^"]+)"|filename\\*=UTF-8\'\'([^ ]+)(?: |$))/', $headers['Content-Disposition'], $matches);
    $transliteratedFileName = $matches[1][0] ?? null;
    $encodedFileName = $matches[2][1] ?? null;
    $this->assertNotNull($transliteratedFileName);
    $this->assertNotNull($encodedFileName);
    $zipFileName = $zipFileBase . '.zip';
    $this->assertEquals('ae' . mb_substr($zipFileName, 1), $transliteratedFileName);
    $this->assertEquals($zipFileName, urldecode($encodedFileName));
    $data = $result->render();

    $tempManager = \OCP\Server::get(\OCP\ITempManager::class);
    $zipFile = $tempManager->getTemporaryFile('.zip');
    file_put_contents($zipFile, $data);
    $zip = new ZipArchive;
    $this->assertTrue($zip->open($zipFile));
    $fileCount = $zip->numFiles;
    $this->assertEquals(count($dbFiles), $fileCount);
    for ($i = 0; $i < $fileCount; $i++) {
      $info = $zip->statIndex($i);
      $this->assertEquals($zipFileBase . '/' . $dbFiles[$i]->getName(), $info['name']);
      $this->assertEquals($dbFiles[$i]->getSize(), $info['size']);
      $extracted = stream_get_contents($zip->getStreamIndex($i));
      $this->assertEquals($dbFiles[$i]->getData(), $extracted);
    }
  }
}
