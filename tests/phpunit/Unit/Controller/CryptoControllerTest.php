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
use OCA\CAFEVDB\Controller\DTO\UnsealedData;
use OCA\CAFEVDB\Tests\MockProvider;

/** Test aspects of the CryptoController. */
#[Attributes\CoversClass(CryptoController::class)]
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
  }
}
