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

namespace OCA\CAFEVDB\Tests\Unit\Database;

use ReflectionMethod;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes;
use PHPUnit\Framework\MockObject\MockObject;

use OCA\CAFEVDB\Database\Doctrine;
use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Service;
use OCA\CAFEVDB\Tests\DatabaseProvider;
use OCA\CAFEVDB\Tests\MockProvider;
use OCA\CAFEVDB\Wrapped\Doctrine\ORM\EntityManagerInterface;

/** Test the entity serializer with fake entities. */
#[Attributes\CoversClass(EntityManager::class)]
#[Attributes\CoversMethod(EntityManager::class, 'connectionParameters')]
#[Attributes\CoversMethod(EntityManager::class, 'connected')]
#[Attributes\CoversMethod(EntityManager::class, 'bound')]
#[Attributes\UsesClass(\OCA\CAFEVDB\AppInfo\Application::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Common\UndoableRunQueue::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Crypto\HaliteCryptoFactory::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Crypto\HaliteSymmetricStreamCryptor::class)]
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
#[Attributes\UsesClass(\OCA\CAFEVDB\Events\EncryptionServiceBound::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Events\EntityManagerBoundEvent::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\ConfigService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\EncryptionService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Toolkit\Service\ExecutableFinder::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Repositories\RepositoryFactory::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Traits\ConfigTrait::class)]
class EntityManagerTest extends TestCase
{
  private EntityManager $entityManager;

  /** {@inheritdoc} */
  public function setup(): void
  {
    /** @var MockProvider $mockProvider */
    $mockProvider = MockProvider::create($this);

    /** @var DatabaseProvider $databaseProvider */
    $databaseProvider = \OCP\Server::get(DatabaseProvider::class);

    if (!$databaseProvider->getDatabaseConfig()) {
      $databaseProvider->startServer();
    }

    $this->entityManager = $mockProvider->getEntityManager($this);
  }

  const EXPECTED_CONNECTION_PARAMETERS = '{"wrapperClass":"OCA\\\CAFEVDB\\\Database\\\Connection","dbname":"cafevdb","row_format":"compressed","host":"localhost","password":"nothing","driver":"pdo_mysql","user":"phpunit","charset":"utf8mb4","collate":"utf8mb4_uca1400_ai_ci"}';

  /** @return void */
  public function testConnectionParameters(): void
  {
    $connectionParameters = new ReflectionMethod(EntityManager::class, 'connectionParameters');
    $parameters = $connectionParameters->invoke($this->entityManager);
    $this->assertArrayHasKey('unix_socket', $parameters);
    unset($parameters['unix_socket']);
    asort($parameters);
    $this->assertEquals(json_encode($parameters), self::EXPECTED_CONNECTION_PARAMETERS);
  }

  /** @return void */
  public function testBound(): void
  {
    $this->assertEquals(true, $this->entityManager->bound());
  }

  /** @return void */
  public function testConnected(): void
  {
    $this->assertEquals(true, $this->entityManager->connected());
  }

  /** @return void */
  public function testGetWrappedObject(): void
  {
    $this->assertInstanceOf(EntityManagerInterface::class, $this->entityManager->getWrappedObject());
  }
}
