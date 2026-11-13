<?php
/**
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @copyright 2026 Claus-Justus Heine
 * @license AGPL-3.0-or-later
 */

namespace OCA\CAFEVDB\Tests\Unit\Command;

use OCP\IL10N;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IUserSession;
use PHPUnit\Framework\Attributes;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;

use OCA\CAFEVDB\Command\UserRecrypt;
use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Service\UserEncryptionService;
use OCA\CAFEVDB\Wrapped\Doctrine\Persistence\Mapping\ClassMetadata;

/** CLI selection, previews and entity-only verbose output. */
#[Attributes\CoversClass(UserRecrypt::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Command\AuthenticatedCommandTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Traits\EntityManagerTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Wrapped\OCA\RotDrop\Toolkit\Traits\AuthenticatedCommandTrait::class)]
class UserRecryptTest extends TestCase
{
  private IUserManager&MockObject $userManager;
  private UserEncryptionService&MockObject $service;
  private EntityManager&MockObject $entityManager;
  private CommandTester $tester;
  private bool $loginSuccessful = true;

  /** {@inheritdoc} */
  protected function setUp(): void
  {
    $this->userManager = $this->createMock(IUserManager::class);
    $this->service = $this->createMock(UserEncryptionService::class);
    $this->entityManager = $this->createMock(EntityManager::class);
    $container = $this->createStub(ContainerInterface::class);
    $container->method('get')->willReturnMap([
      [UserEncryptionService::class, $this->service],
      [EntityManager::class, $this->entityManager],
    ]);
    $session = $this->createStub(IUserSession::class);
    $session->method('login')->willReturnCallback(
      fn(string $userId, string $password) => $userId === 'operator' && $password === 'password' && $this->loginSuccessful,
    );
    $l = $this->createStub(IL10N::class);
    $l->method('t')->willReturnArgument(0);
    $command = new UserRecrypt(
      'cafevdb',
      $l,
      new NullLogger,
      $this->userManager,
      $session,
      $container,
    );
    (new Application)->add($command);
    $this->tester = new CommandTester($command);
    $this->tester->setInputs(['operator', 'password']);
  }

  /** @return iterable */
  public static function invalidSelections(): iterable
  {
    yield 'missing selection' => [[]];
    yield 'conflicting selection' => [['--user' => 'member', '--all' => true]];
    yield 'empty user' => [['--user' => '']];
  }

  /** @param array $options */
  #[Attributes\DataProvider('invalidSelections')]
  public function testInvalidSelectionDoesNotRecrypt(array $options): void
  {
    $this->service->expects(self::never())->method('recrypt');
    $this->userManager->expects(self::never())->method('get');
    $this->entityManager->expects(self::never())->method('getClassMetadata');
    self::assertSame(UserRecrypt::INVALID, $this->tester->execute($options));
  }

  /** @return void */
  public function testUnknownUserDoesNotRecrypt(): void
  {
    $this->userManager->expects(self::once())->method('get')->with('missing')->willReturn(null);
    $this->entityManager->expects(self::never())->method('getClassMetadata');
    $this->service->expects(self::never())->method('recrypt');
    self::assertSame(UserRecrypt::INVALID, $this->tester->execute(['--user' => 'missing']));
    self::assertStringContainsString('Unknown cloud user', $this->tester->getDisplay());
  }

  /** @return void */
  public function testVerboseDryRunListsOnlyIdentifiersIncludingAssociatedIds(): void
  {
    $user = $this->createStub(IUser::class);
    $this->userManager->method('get')->willReturn($user);
    $this->userManager->expects(self::never())->method('searchDisplayName');
    $owner = (object)['id' => 42, 'secret' => 'DO NOT PRINT'];
    $entity = (object)['id' => $owner, 'secret' => 'DO NOT PRINT'];
    $metadata = $this->createStub(ClassMetadata::class);
    $metadata->method('getName')->willReturn('EncryptedEntity');
    $metadata->method('getIdentifierValues')->willReturnCallback(fn(object $value) => ['id' => $value->id]);
    $this->entityManager->expects(self::atLeastOnce())->method('getClassMetadata')->willReturn($metadata);
    $this->service->expects(self::once())->method('recrypt')->willReturnCallback(
      static function(string $userId, bool $dryRun, ?callable $onEntity) use ($entity): bool {
        self::assertSame('member', $userId);
        self::assertTrue($dryRun);
        $onEntity($entity);
        return false;
      },
    );
    self::assertSame(UserRecrypt::SUCCESS, $this->tester->execute(
      ['--user' => 'member', '--dry-run' => true],
      ['verbosity' => OutputInterface::VERBOSITY_VERBOSE],
    ));
    $output = $this->tester->getDisplay();
    self::assertStringContainsString('Would recrypt EncryptedEntity {"id":{"id":42}}', $output);
    self::assertStringContainsString('Dry run: 1 user(s), 1 encrypted entity/entities.', $output);
    self::assertStringNotContainsString('DO NOT PRINT', $output);
  }

  /** @return void */
  public function testAllUsersAreRecryptedWithoutPendingRequestsOrVerboseOutput(): void
  {
    $users = [];
    foreach (['member', 'admin'] as $userId) {
      $user = $this->createStub(IUser::class);
      $user->method('getUID')->willReturn($userId);
      $users[] = $user;
    }
    $this->userManager->expects(self::once())->method('searchDisplayName')->with('')->willReturn($users);
    $this->entityManager->expects(self::never())->method('getClassMetadata');
    $visited = [];
    $this->service->expects(self::exactly(2))->method('recrypt')->willReturnCallback(
      static function(string $userId, bool $dryRun, ?callable $onEntity) use (&$visited): bool {
        self::assertFalse($dryRun);
        $visited[] = $userId;
        $onEntity(new \stdClass);
        return false;
      },
    );
    self::assertSame(UserRecrypt::SUCCESS, $this->tester->execute(['--all' => true]));
    self::assertSame(['member', 'admin'], $visited);
    self::assertStringContainsString('Recrypted 2 user(s), 2 encrypted entity/entities.', $this->tester->getDisplay());
    self::assertStringNotContainsString('stdClass', $this->tester->getDisplay());
  }

  /** @return void */
  public function testFailedInteractiveLoginDoesNotRecrypt(): void
  {
    $this->loginSuccessful = false;
    $this->userManager->expects(self::exactly(2))->method('get')->willReturn($this->createStub(IUser::class));
    $this->service->expects(self::never())->method('recrypt');
    $this->entityManager->expects(self::never())->method('getClassMetadata');
    self::assertSame(UserRecrypt::FAILURE, $this->tester->execute(['--user' => 'member']));
    self::assertStringContainsString('Login failed.', $this->tester->getDisplay());
  }
}
