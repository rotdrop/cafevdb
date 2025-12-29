<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2025 Claus-Justus Heine <himself@claus-justus-heine.de>
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

namespace OCA\CAFEVDB\Tests\Unit\Service;

use PHPUnit\Framework\Attributes;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

use OCP\App\IAppManager;
use OCP\IConfig;

use OCA\CAFEVDB\Tests\MockProvider;
use OCA\CAFEVDB\Tests\DatabaseProvider;
use OCA\CAFEVDB\Service\CloudUserConnectorService;
use OCA\CAFEVDB\Service\EncryptionService;
use OCA\CAFEVDB\Settings\ConfigConstants;

/**
 * Test aspects of the CloudUserConnectorService.
 *
 * @todo Testing the DB access requires excessive mocking or a real DB. The
 * latter has not yet been fully set up but that will be the way to go in the
 * future.
 */
#[Attributes\CoversClass(CloudUserConnectorService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\AppInfo\Application::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Common\UndoableRunQueue::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Common\Util::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Crypto\HaliteCryptoFactory::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Crypto\HaliteSymmetricStreamCryptor::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Connection::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\DBAL\Logging\CloudLogger::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\DBAL\Types\AbstractDecimalRationalType::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\DBAL\Types\ArrayType::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\DBAL\Types\DecimalRationalMonetaryType::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\DeprecationLogger::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Listeners\GedmoLoggableListener::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Listeners\GedmoSluggableListener::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Listeners\GedmoTranslatableListener::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Repositories\RepositoryFactory::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\EntityManager::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Events\EncryptionServiceBound::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Events\EntityManagerBoundEvent::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\ConfigService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\EncryptionService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Toolkit\Service\ExecutableFinder::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Traits\AppConfigTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Traits\ConfigTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Traits\UserPreferencesTrait::class)]
class CloudUserConnectorServiceTest extends TestCase
{
  private string $appName;

  private IConfig $cloudConfig;

  private CloudUserConnectorService $cloudUserConnectorService;

  private EncryptionService $encryptionService;

  private IAppManager $appManager;

  /** @return void */
  public function setup(): void
  {
    /** @var MockProvider $mockProvider */
    $mockProvider = MockProvider::create($this);

    /** @var DatabaseProvider $databaseProvider */
    $databaseProvider = \OCP\Server::get(DatabaseProvider::class);

    if (!$databaseProvider->getDatabaseConfig()) {
      $databaseProvider->startServer();
    }

    // Instantiate the entity-manager, this is needed in order to use our test-database.
    $mockProvider->getEntityManager();

    $this->appManager = $this->getMockBuilder(IAppManager::class)
      ->getMock();
    $this->appManager
      ->expects($this->never())
      ->method('getAppInfo');

    $this->appName = $mockProvider->appName;

    $this->cloudConfig = $mockProvider->getCloudConfig();

    $this->encryptionService = $mockProvider->getEncryptionService();

    $this->cloudUserConnectorService = new CloudUserConnectorService(
      appName: $this->appName,
      appContainer: $mockProvider->getAppContainer(),
      logger: $mockProvider->getLoggerInterface(),
      l: $mockProvider->getL10N(),
      cloudConfig: $this->cloudConfig,
      encryptionService: $mockProvider->getEncryptionService(),
      appManager: $this->appManager,
    );
  }

  /** @return void */
  public function testConstruction(): void
  {
    $this->appManager->expects($this->never())->method('isInstalled');
    // nothing
  }

  /** @return void */
  public function testCheckRequirements(): void
  {
    $method = $this->appManager->expects($this->atLeastOnce())->method('isInstalled');

    $result = $this->cloudUserConnectorService->checkRequirements(
      dataBaseName: null,
    );
    $this->assertEquals(CloudUserConnectorService::REQUIREMENTS_MISSING, $result['status']);
    $this->assertNotEmpty($result['hints']);

    $method->with(CloudUserConnectorService::CLOUD_USER_BACKEND)->willReturn(true);
    $result = $this->cloudUserConnectorService->checkRequirements(
      dataBaseName: null,
    );
    $this->assertEquals(CloudUserConnectorService::REQUIREMENTS_OK, $result['status']);
    $this->assertEmpty($result['hints']);

    $restrictions = ['NOT EMPTY'];
    $this->appManager
      ->method('getAppRestriction')
      ->with(CloudUserConnectorService::CLOUD_USER_BACKEND)
      ->willReturnCallback(function() use (&$restrictions) {
        return $restrictions;
      });
    $result = $this->cloudUserConnectorService->checkRequirements(
      dataBaseName: null,
    );
    $this->assertEquals(CloudUserConnectorService::REQUIREMENTS_MISSING, $result['status']);
    $this->assertNotEmpty($result['hints']);

    $restrictions = [];
    $result = $this->cloudUserConnectorService->checkRequirements(
      dataBaseName: 'blub',
    );
    $this->assertNotEmpty($result['hints']);

    $result = $this->cloudUserConnectorService->checkRequirements(
      dataBaseName: $this->appName,
    );
    $this->assertEquals(CloudUserConnectorService::REQUIREMENTS_OK, $result['status']);
    $this->assertEmpty($result['hints']);
  }

  /** @return void */
  public function testWriteUserSqlConfig(): void
  {
    $this->encryptionService->setConfigValue(ConfigConstants::ORCHESTRA_NAME_KEY, 'Orchester');
    $this->cloudUserConnectorService->writeUserSqlConfig(
      dataBaseName: 'something',
      delete: false,
    );
    $this->assertEquals(
      'Orchester Personen',
      $this->cloudConfig->getAppValue(
        $this->appName,
        CloudUserConnectorService::CLOUD_USER_BACKEND . ':' . 'opt.default_group',
      ),
    );
    $this->assertTrue($this->cloudUserConnectorService->haveCloudUserBackendConfig());
    $this->cloudUserConnectorService->writeUserSqlConfig(
      dataBaseName: 'something',
      delete: true,
    );
    $this->assertEquals(
      null,
      $this->cloudConfig->getAppValue(
        $this->appName,
        CloudUserConnectorService::CLOUD_USER_BACKEND . ':' . 'opt.default_group',
      ),
    );
    $this->assertFalse($this->cloudUserConnectorService->haveCloudUserBackendConfig());
  }

  /** @return void */
  public function testProjectGroupId(): void
  {
    $number = 47;
    $groupId = $this->cloudUserConnectorService->projectGroupId($number);
    $this->assertEquals($this->appName . '_' .  $number, $groupId);
  }
}
