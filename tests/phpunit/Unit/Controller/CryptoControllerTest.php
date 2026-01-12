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

namespace OCA\CAFEVDB\Tests\Unit\Controller;

use PHPUnit\Framework\Attributes;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;

use OCA\CAFEVDB\Controller\CryptoController;
use OCA\CAFEVDB\Controller\DTO\IBANMetaData;
use OCA\CAFEVDB\Controller\DTO\UnsealedData;
use OCA\CAFEVDB\Service\Finance\FinanceService;
use OCA\CAFEVDB\Tests\MockProvider;

/** Test aspects of the CryptoController. */
#[Attributes\CoversClass(CryptoController::class)]
#[Attributes\CoversClass(IBANMetaData::class)]
#[Attributes\CoversClass(UnsealedData::class)]
#[Attributes\CoversMethod(FinanceService::class, 'getIbanInfo')]
#[Attributes\UsesClass(\OCA\CAFEVDB\AppInfo\Application::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Common\UndoableRunQueue::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Crypto\HaliteCryptoFactory::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Crypto\HaliteSymmetricStreamCryptor::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Crypto\SealCryptor::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Connection::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\DBAL\Logging\CloudLogger::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\DBAL\Types\AbstractDecimalRationalType::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\DBAL\Types\ArrayType::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\DBAL\Types\DecimalRationalMonetaryType::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\DeprecationLogger::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Listeners\DoctrineMigrationsListener::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Listeners\GedmoLoggableListener::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Listeners\GedmoSluggableListener::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Listeners\GedmoTranslatableListener::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Listeners\Transformable\Encryption::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Mapping\ClassMetadataDecorator::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Repositories\EntityRepository::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Repositories\RepositoryFactory::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\EntityManager::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Events\EncryptionServiceBound::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Events\EntityManagerBoundEvent::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\ConfigService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\EncryptionService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\Finance\FinanceService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\Registration::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Toolkit\DTO\AbstractDTO::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Traits\AppConfigTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Traits\ConfigTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Traits\EntityManagerTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Traits\UserPreferencesTrait::class)]
class CryptoControllerTest extends TestCase
{
  private CryptoController $cryptoController;

  private MockProvider $mockProvider;

  /** {@inheritdoc} */
  public function setup(): void
  {
    $this->mockProvider = $this->mockProvider ?? MockProvider::create($this);

    $this->cryptoController = new CryptoController(
      appName: $this->mockProvider->appName,
      request: $this->mockProvider->getRequest(),
      entityManager: $this->mockProvider->getEntityManager(),
      appContainer: $this->mockProvider->getAppContainer(),
      l: $this->mockProvider->getL10N(),
      logger: $this->mockProvider->getLoggerInterface(),
    );
  }

  /** @return void */
  public function testSetup(): void
  {
  }

  /** @return void */
  public function testBatchUnsealEncryptionDisabled(): void
  {
    $inputData = [ 'one', 'two' ];
    $result = $this->cryptoController->batchUnseal(
      sealedData: $inputData,
      metaData: null,
    );
    $this->assertInstanceOf(DataResponse::class, $result);
    $this->assertEquals(Http::STATUS_OK, $result->getStatus());
    $data = $result->getData();
    json_encode($data, JSON_THROW_ON_ERROR);
    $this->assertEquals(count($inputData), count($data));
    foreach ($data as $index => $item) {
      $this->assertInstanceOf(UnsealedData::class, $item);
      $this->assertEquals($inputData[$index], $item->data);
      $this->assertEquals(md5($inputData[$index]), $item->hash);
      $this->assertNull($item->context);
      $this->assertNull($item->metaData);
    }

    $result = $this->cryptoController->batchUnseal(
      sealedData: [ MockProvider::TEST_IBAN ],
      metaData: CryptoController::META_DATA_IBAN,
    );
    $this->assertInstanceOf(DataResponse::class, $result);
    $this->assertEquals(Http::STATUS_OK, $result->getStatus());
    $data = $result->getData();
    json_encode($data, JSON_THROW_ON_ERROR);
    $this->assertEquals(1, count($data));
    $item = $data[0];
    $this->assertInstanceOf(UnsealedData::class, $item);
    $this->assertInstanceOf(IBANMetaData::class, $item->metaData);
    $this->assertEqualsCanonicalizing(MockProvider::IBAN_INFO, $item->metaData->jsonSerialize());
  }
}
