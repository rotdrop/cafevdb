<?php
/**
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @copyright 2026 Claus-Justus Heine
 * @license AGPL-3.0-or-later
 */

namespace OCA\CAFEVDB\Tests\Unit\Service;

use OCP\IL10N;
use PHPUnit\Framework\Attributes;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

use OCA\CAFEVDB\Crypto\AsymmetricKeyService;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Database\Doctrine\ORM\Repositories\MusiciansRepository;
use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Exceptions\EncryptionKeyException;
use OCA\CAFEVDB\Service\AuthorizationService;
use OCA\CAFEVDB\Service\EncryptionService;
use OCA\CAFEVDB\Service\UserEncryptionService;
use OCA\CAFEVDB\Wrapped\Doctrine\Common\Collections\ArrayCollection;

/** Recryption previews must not rotate keys or write encrypted entries. */
#[Attributes\CoversClass(UserEncryptionService::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Traits\EntityManagerTrait::class)]
class UserEncryptionServiceTest extends TestCase
{
  private EntityManager&MockObject $entityManager;
  private AsymmetricKeyService&MockObject $keyService;
  private AuthorizationService $authorization;
  private EncryptionService&MockObject $encryption;
  private MusiciansRepository $repository;
  private UserEncryptionService $service;
  private array $filterStates = [];

  /** {@inheritdoc} */
  protected function setUp(): void
  {
    $this->entityManager = $this->createMock(EntityManager::class);
    $this->keyService = $this->createMock(AsymmetricKeyService::class);
    $this->authorization = $this->createStub(AuthorizationService::class);
    $this->encryption = $this->createMock(EncryptionService::class);
    $this->repository = $this->createStub(MusiciansRepository::class);
    $this->entityManager->method('getRepository')->willReturn($this->repository);
    $l = $this->createStub(IL10N::class);
    $l->method('t')->willReturnArgument(0);
    $this->entityManager->method('setFilterEnabled')->willReturnCallback(
      function(string $filter, ?bool $state): bool {
        self::assertSame(EntityManager::SOFT_DELETEABLE_FILTER, $filter);
        $this->filterStates[] = $state;
        return true;
      },
    );
    $this->service = new UserEncryptionService(
      $this->keyService, $this->authorization, $this->encryption,
      $this->entityManager, $l, new NullLogger,
    );
  }

  /** @return array The musician and its encrypted entities. */
  private function musician(): array
  {
    $bankAccount = $this->createStub(Entities\SepaBankAccount::class);
    $fileData = $this->createStub(Entities\EncryptedFileData::class);
    $file = $this->createStub(Entities\EncryptedFile::class);
    $file->method('getFileData')->willReturn($fileData);
    $musician = $this->createStub(Entities\Musician::class);
    $musician->method('getSepaBankAccounts')->willReturn(new ArrayCollection([$bankAccount]));
    $musician->method('getEncryptedFiles')->willReturn(new ArrayCollection([$file]));
    $musician->method('getUserIdSlug')->willReturn('member');
    $this->repository->method('findByUserId')->willReturn($musician);
    return [$musician, [$bankAccount, $fileData]];
  }

  /** @return void */
  private function expectNoWrites(): void
  {
    foreach (['beginTransaction', 'persist', 'flush', 'commit', 'recryptEntityList'] as $method) {
      $this->entityManager->expects(self::never())->method($method);
    }
    $this->keyService->expects(self::never())->method('setSharedPrivateValue');
    $this->encryption->expects(self::never())->method('setUserEncryptionKey');
  }

  /** @return void */
  public function testDryRunEnumeratesBankAccountsAndFileDataWithoutWrites(): void
  {
    [, $entities] = $this->musician();
    $this->authorization->method('getUserPermissions')->willReturn(AuthorizationService::PERMISSION_NONE);
    $this->expectNoWrites();
    $visited = [];
    self::assertFalse($this->service->recrypt('member', true, function(object $entity) use (&$visited): void {
      $visited[] = $entity;
    }));
    self::assertSame($entities, $visited);
    self::assertSame([false, true], $this->filterStates);
  }

  /** @return void */
  public function testDryRunPreservesAppAccessResultWithoutMusician(): void
  {
    $this->repository->method('findByUserId')->willReturn(null);
    $this->authorization->method('getUserPermissions')->willReturn(AuthorizationService::PERMISSION_ALL);
    $this->encryption->method('encryptionKeyValid')->willReturn(true);
    $this->expectNoWrites();
    self::assertTrue($this->service->recrypt('admin', dryRun: true));
    self::assertSame([false, true], $this->filterStates);
  }

  /** @return void */
  public function testRecryptionIsForcedAndForwardsTheEntityCallback(): void
  {
    [$musician, $entities] = $this->musician();
    $this->authorization->method('getUserPermissions')->willReturn(AuthorizationService::PERMISSION_NONE);
    $this->encryption->expects(self::never())->method('setUserEncryptionKey');
    $token = $this->createMock(Entities\MusicianRowAccessToken::class);
    $musician->method('getRowAccessToken')->willReturn($token);
    $token->expects(self::once())->method('setAccessToken')->willReturnSelf();
    $token->expects(self::once())->method('setUserId')->with('member')->willReturnSelf();
    $this->keyService->expects(self::once())->method('setSharedPrivateValue')->with(
      'member',
      UserEncryptionService::ROW_ACCESS_TOKEN_KEY,
      self::callback(fn(string $value) => strlen($value) === Entities\MusicianRowAccessToken::HASH_LENGTH / 8),
    );
    $this->entityManager->expects(self::once())->method('beginTransaction');
    $this->entityManager->expects(self::once())->method('flush');
    $this->entityManager->expects(self::once())->method('commit');
    $onEntity = static function(object $entity): void {};
    $this->entityManager->expects(self::once())->method('recryptEntityList')->with($entities, null, null, $onEntity);
    self::assertFalse($this->service->recrypt('member', onEntity: $onEntity));
    self::assertSame([false, true], $this->filterStates);
  }

  /** @return void */
  public function testFilterIsRestoredWhenPreviewCallbackFails(): void
  {
    $this->musician();
    $this->authorization->method('getUserPermissions')->willReturn(AuthorizationService::PERMISSION_NONE);
    $this->expectNoWrites();
    $error = new \RuntimeException('Preview failed');
    try {
      $this->service->recrypt('member', true, static function(object $entity) use ($error): void {
        throw $error;
      });
      self::fail('The callback failure must propagate.');
    } catch (\RuntimeException $exception) {
      self::assertSame($error, $exception);
    }
    self::assertSame([false, true], $this->filterStates);
  }

  /** @return void */
  public function testInvalidAppKeyFailsBeforeAnyWrites(): void
  {
    $this->authorization->method('getUserPermissions')->willReturn(AuthorizationService::PERMISSION_ALL);
    $this->encryption->method('encryptionKeyValid')->willReturn(false);
    $this->expectNoWrites();
    $this->expectException(EncryptionKeyException::class);
    try {
      $this->service->recrypt('admin');
    } finally {
      self::assertSame([false, true], $this->filterStates);
    }
  }
}
