<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2026 Claus-Justus Heine
 * @license AGPL-3.0-or-later
 */

namespace OCA\CAFEVDB\Tests\Unit\Database;

use OCP\Config\IUserConfig;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IRequest;
use PHPUnit\Framework\Attributes;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;

use OCA\CAFEVDB\Common\TimeFactory;
use OCA\CAFEVDB\Common\UndoableRunQueue;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Logging\CloudLogger;
use OCA\CAFEVDB\Database\Doctrine\DeprecationLogger;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Database\Doctrine\ORM\Listeners;
use OCA\CAFEVDB\Database\Doctrine\ORM\Repositories\RepositoryFactory;
use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Events\EntityManagerBoundEvent;
use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Service\EncryptionService;
use OCA\CAFEVDB\Service\L10N\BiDirectionalL10N;
use OCA\CAFEVDB\Settings\ConfigConstants;
use OCA\CAFEVDB\Wrapped\Carbon;
use OCA\CAFEVDB\Wrapped\Doctrine\DBAL\Platforms\MariaDBPlatform;
use OCA\CAFEVDB\Wrapped\Doctrine\DBAL\Types\Type;
use OCA\CAFEVDB\Wrapped\OCA\RotDrop\Toolkit\Doctrine\DBAL\Types\ArrayType;
use OCA\CAFEVDB\Wrapped\OCA\RotDrop\Toolkit\Doctrine\DBAL\Types\DecimalRationalMonetaryType;
use OCA\CAFEVDB\Wrapped\OCA\RotDrop\Toolkit\Doctrine\DBAL\Types\UuidType;
use OCA\RotDrop\DevScripts\PhpToTypeScript\GenerateEntityMetadata;

/** Exercise real entity metadata without an application database connection. */
#[Attributes\CoversNothing]
class OfflineEntityManagerTest extends TestCase
{
  /** @return void */
  public function testMetadataWithExplicitServerVersion(): void
  {
    require_once dirname(__DIR__, 4) . '/vendor-bin/php-to-typescript/vendor/autoload.php';

    $entityManager = $this->createEntityManager('11.7.0-MariaDB');
    $entityManager->decorateClassMetadata(false);
    $connection = $entityManager->getConnection();
    self::assertFalse($connection->isConnected());
    self::assertSame('11.7.0-MariaDB', $connection->getParams()['serverVersion']);
    $platform = $connection->getDatabasePlatform();
    self::assertInstanceOf(MariaDBPlatform::class, $platform);
    foreach ([UuidType::class, DecimalRationalMonetaryType::class, ArrayType::class] as $typeClass) {
      self::assertInstanceOf($typeClass, Type::getType($typeClass::NAME));
      self::assertSame($typeClass::NAME, $platform->getDoctrineTypeMapping($typeClass::NAME));
    }
    self::assertInstanceOf(Carbon\Doctrine\DateTimeType::class, Type::getType('datetime'));

    $generator = new GenerateEntityMetadata(
      devScriptsFolder: dirname(__DIR__, 4) . '/dev-scripts',
      phpNamespacePrefix: 'OCA\\CAFEVDB',
      outputPrefix: '',
      output: new ConsoleOutput(OutputInterface::VERBOSITY_QUIET),
      entityManagerFactory: fn() => $entityManager,
    );
    $metadata = $generator->generateSparseMetadata();

    self::assertTrue($metadata['Musician']['id']['id']);
    self::assertSame('owned', $metadata['Musician']['id']['mapping']);
    foreach (['project' => 'Project', 'musician' => 'Musician'] as $field => $target) {
      self::assertTrue($metadata['ProjectParticipant'][$field]['id']);
      self::assertSame('to-one', $metadata['ProjectParticipant'][$field]['mapping']);
      self::assertSame($target, $metadata['ProjectParticipant'][$field]['type']);
    }
    self::assertSame('to-many', $metadata['Project']['participants']['mapping']);
    self::assertSame(UuidType::NAME, $metadata['ProjectParticipantFieldDataOption']['key']['type']);
    self::assertTrue($metadata['ProjectParticipantFieldDataOption']['key']['id']);
    self::assertSame(DecimalRationalMonetaryType::NAME, $metadata['ProjectParticipantFieldDataOption']['deposit']['type']);
    self::assertSame(
      ['field', 'project', 'musician', 'optionKey'],
      $entityManager->getClassMetadata(Entities\ProjectParticipantFieldDatum::class)->getIdentifier(),
    );
    self::assertFalse($connection->isConnected());
  }

  /** @return void */
  public function testDefaultConstructionStillChecksConnectivity(): void
  {
    $entityManager = $this->createEntityManager();
    self::assertTrue($entityManager->bound());
    self::assertArrayNotHasKey('serverVersion', $entityManager->getConnection()->getParams());
    self::assertFalse($entityManager->getConnection()->isConnected());
  }

  /** @return void */
  public function testExplicitPlatformDoesNotBypassEncryptionBinding(): void
  {
    $entityManager = $this->createEntityManager('11.7.0-MariaDB', encryptionBound: false);
    self::assertFalse($entityManager->bound());
  }

  /**
   * @param null|string $serverVersion
   *
   * @param bool $encryptionBound
   *
   * @return EntityManager
   */
  private function createEntityManager(?string $serverVersion = null, bool $encryptionBound = true): EntityManager
  {
    $entityManager = $this->getMockBuilder(EntityManager::class)
      ->disableOriginalConstructor()
      ->onlyMethods(['connected', 'getUserValue'])
      ->getMock();
    $entityManager->expects($serverVersion === null && $encryptionBound ? $this->once() : $this->never())
      ->method('connected')
      ->willReturn(false);
    $entityManager->method('getUserValue')->willReturnCallback(fn($key, $default = null) => $default);

    $l = $this->createStub(IL10N::class);
    $l->method('getLocaleCode')->willReturn('en_US');
    $l->method('t')->willReturnArgument(0);
    $configService = $this->createStub(ConfigService::class);
    $configService->method('getL10n')->willReturn($l);

    $encryptionService = $this->createMock(EncryptionService::class);
    $encryptionService->method('bound')->willReturn($encryptionBound);
    $encryptionService->expects($this->never())->method('bind');
    $encryptionService->method('getConfigValue')->willReturnMap([
      [ConfigConstants::APP_DB_NAME, null, false, 'offline_metadata'],
      [ConfigConstants::APP_DB_USER, null, false, 'offline_metadata'],
      [ConfigConstants::APP_DB_PASSWORD, null, false, 'offline_metadata'],
      [ConfigConstants::APP_DB_SERVER, null, false, 'mysql://127.0.0.1:1/offline_metadata'],
    ]);

    $dispatcher = $this->createMock(IEventDispatcher::class);
    $dispatcher->expects($encryptionBound ? $this->once() : $this->never())
      ->method('dispatchTyped')->with($this->isInstanceOf(EntityManagerBoundEvent::class));
    $dispatcher->expects($encryptionBound ? $this->never() : $this->once())->method('addListener');

    $encryption = $this->createMock(Listeners\Transformable\Encryption::class);
    $encryption->expects($this->never())->method('transform');
    $encryption->expects($this->never())->method('reverseTransform');

    $container = $this->createStub(ContainerInterface::class);
    $container->method('get')->willReturnMap([
      [IEventDispatcher::class, $dispatcher],
      [UndoableRunQueue::class, $this->createStub(UndoableRunQueue::class)],
      [TimeFactory::class, $this->createStub(TimeFactory::class)],
      [RepositoryFactory::class, $this->createStub(RepositoryFactory::class)],
      [Listeners\DoctrineMigrationsListener::class, $this->createStub(Listeners\DoctrineMigrationsListener::class)],
      [Listeners\GedmoSluggableListener::class, new Listeners\GedmoSluggableListener($container)],
      [Listeners\Transformable\Encryption::class, $encryption],
      [Listeners\GedmoTranslatableListener::class, new Listeners\GedmoTranslatableListener(
        $configService,
        $this->createStub(BiDirectionalL10N::class),
      )],
    ]);

    $entityManager->__construct(
      deprecationLogger: $this->createStub(DeprecationLogger::class),
      sqlLogger: $this->createStub(CloudLogger::class),
      encryptionService: $encryptionService,
      l: $l,
      request: $this->createStub(IRequest::class),
      appContainer: $container,
      logger: $this->createStub(LoggerInterface::class),
      appName: 'cafevdb',
      cloudConfig: $this->createStub(IConfig::class),
      cloudUserConfig: $this->createStub(IUserConfig::class),
      databaseServerVersion: $serverVersion,
    );

    return $entityManager;
  }
}
