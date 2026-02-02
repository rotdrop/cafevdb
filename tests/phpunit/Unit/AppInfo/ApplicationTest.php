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

namespace OCA\CAFEVDB\Tests\Unit\Maintenance;

use Throwable;
use UnexpectedValueException;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes;
use PHPUnit\Framework\MockObject\MockObject;

use OCA\CAFEVDB\AppInfo\Application;
use OCA\CAFEVDB\Database\Connection;
use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Database\Registration;
use OCA\CAFEVDB\Tests\MockProvider;
use OCA\CAFEVDB\Toolkit\Doctrine\ORM\AbstractEntityManager;
use OCA\CAFEVDB\Toolkit\Service\AppInfoService;
use OCA\CAFEVDB\Wrapped\Doctrine\ORM\EntityManagerInterface;
use OCA\CAFEVDB\Wrapped\Doctrine\ORM\Mapping\ClassMetadataFactory;
use OCA\CAFEVDB\Wrapped\Doctrine\ORM\UnitOfWork;
use OCA\CAFEVDB\Wrapped\Doctrine\ORM\Utility\IdentifierFlattener;

/** Test aspects of the application class, in particular service registrations. */
#[Attributes\CoversClass(Application::class)]
#[Attributes\Coverslass(\OCA\CAFEVDB\Database\Registration::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\L10N\L10NFactory::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\Registration::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Registration::class)]
class ApplicationTest extends TestCase
{
  private MockProvider $mockProvider;

  /** {@inheritcod} */
  public function setup(): void
  {
    $this->mockProvider = $this->MockProvider ?? MockProvider::create($this);
  }

  /** @return void */
  public function testEntityManagerAliases(): void
  {
    $entityManager = $this->createStub(EntityManager::class);
    $this->mockProvider->registerClassInstance(EntityManager::class, $entityManager, global: true);
    $appContainer = $this->mockProvider->getAppContainer();
    $entityManager = $appContainer->get(AbstractEntityManager::class);
    $this->assertInstanceOf(EntityManager::class, $entityManager);
    $entityManager = $appContainer->get(EntityManagerInterface::class);
    $this->assertInstanceOf(EntityManager::class, $entityManager);
  }

  /** @return void */
  public function testConnectionService(): void
  {
    $entityManager = $this->createStub(EntityManager::class);
    $this->mockProvider->registerClassInstance(EntityManager::class, $entityManager, global: true);
    $entityManager->method('getConnection')->willReturn($this->createStub(Connection::class));
    $appContainer = $this->mockProvider->getAppContainer();
    $connection = $appContainer->get(Connection::class);
    $this->assertInstanceOf(Connection::class, $connection);
  }

  /** @return void */
  public function testIdentifierFlattenerService(): void
  {
    $entityManager = $this->createStub(EntityManager::class);
    $this->mockProvider->registerClassInstance(EntityManager::class, $entityManager, global: true);
    $appContainer = $this->mockProvider->getAppContainer();
    $entityManager->method('getUnitOfWork')->willReturn($this->createStub(UnitOfWork::class));
    $entityManager->method('getMetadataFactory')->willReturn($this->createStub(ClassMetadataFactory::class));
    $identifierFlattener = $appContainer->get(IdentifierFlattener::class);
    $this->assertInstanceOf(IdentifierFlattener::class, $identifierFlattener);
  }

  private const FAKED_APPINFO = [
    'namespace' => 'Namespace',
    'scopednamespace' => 'ScopedNamespace\\SubNamespace',
  ];

  /** @return void */
  public function testScopedNamespaceService(): void
  {
    $appInfoService = $this->getMockBuilder(AppInfoService::class)
      ->disableOriginalConstructor()
      ->getMock();
    $appInfoService->expects($this->once())->method('getAppInfo')->willReturn(self::FAKED_APPINFO);
    $this->mockProvider->registerClassInstance(AppInfoService::class, $appInfoService, global: true);
    $appContainer = $this->mockProvider->getAppContainer();
    $scopedNamespace = $appContainer->get(Registration::SCOPED_NAMESPACE);
    $this->assertNotNull($scopedNamespace);
    $components = explode('\\', $scopedNamespace, 3);
    $this->assertEquals('OCA', $components[0]);
    $this->assertEquals(self::FAKED_APPINFO['namespace'], $components[1]);
    $this->assertEquals(self::FAKED_APPINFO['scopednamespace'], $components[2]);
  }

  /** @return void */
  public function testScopedNamespaceServiceFailure(): void
  {
    $appInfoService = $this->getMockBuilder(AppInfoService::class)
      ->disableOriginalConstructor()
      ->getMock();
    $appInfoService->expects($this->once())->method('getAppInfo')->willReturn([
      'namespace' => self::FAKED_APPINFO['namespace'],
    ]);
    $this->mockProvider->registerClassInstance(AppInfoService::class, $appInfoService, global: true);
    $appContainer = $this->mockProvider->getAppContainer();
    try {
      $appContainer->get(Registration::SCOPED_NAMESPACE);
    } catch (Throwable $t) {
      $this->assertInstanceOf(UnexpectedValueException::class, $t);
    }
  }
}
