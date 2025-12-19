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

namespace OCA\CAFEVDB\Tests\Unit\Controller;

use ReflectionClass;

use PHPUnit\Framework\Attributes;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

use OCP\AppFramework\Http;
use OCP\AppFramework\OCS;
use OCP\IRequest;
use OC\AppFramework\Middleware\OCSMiddleware;

use OCA\CAFEVDB\Controller\EntityRepositoryController;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Database\Doctrine\ORM\Repositories\EntityRepository;
use OCA\CAFEVDB\Database\Doctrine\ORM\Util\EntitySerializer;
use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Exceptions;
use OCA\CAFEVDB\Tests\DatabaseProvider;
use OCA\CAFEVDB\Tests\MockProvider;
use OCA\CAFEVDB\Tests\Unit\Database\Doctrine\ORM\Entities\EntityGeneratorTrait;

/** Test the EntityRepositoryController. */
#[Attributes\CoversClass(EntityRepositoryController::class)]
#[Attributes\CoversClass(Exceptions\DatabaseEntityNotFoundException::class)]
#[Attributes\CoversMethod(EntityRepositoryController::class, 'getEntities')]
#[Attributes\UsesClass(\OCA\CAFEVDB\AppInfo\Application::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Common\UndoableRunQueue::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Common\Uuid::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Crypto\HaliteCryptoFactory::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Crypto\HaliteSymmetricStreamCryptor::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Crypto\Registration::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Connection::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\DBAL\Logging\CloudLogger::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\DBAL\Types\AbstractDecimalRationalType::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\DBAL\Types\ArrayType::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\DBAL\Types\DecimalRationalMonetaryType::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\DeprecationLogger::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\Musician::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\MusicianEmailAddress::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\Project::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\ProjectParticipant::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\SepaBankAccount::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Listeners\GedmoLoggableListener::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Listeners\GedmoSluggableListener::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Listeners\GedmoTranslatableListener::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Listeners\Sluggable\LoginNameSlugHandler::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Mapping\ClassMetadataDecorator::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Repositories\RepositoryFactory::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Util\EntityReference::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Util\EntityReferenceCollection::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Util\EntityResponse::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Util\EntitySerializer::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\EntityManager::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Events\EncryptionServiceBound::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Events\EntityManagerBoundEvent::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Exceptions\DatabaseEntityException::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Listener\TranslationNotFoundListener::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\AuthorizationService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\ConfigService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\EncryptionService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\InstrumentationService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Toolkit\DTO\AbstractResponseDTO::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Toolkit\Service\ExecutableFinder::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Database\Doctrine\ORM\Traits\ArrayTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Database\Doctrine\ORM\Traits\CreatedAt::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Database\Doctrine\ORM\Traits\DateTimeTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Database\Doctrine\ORM\Traits\FactoryTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Database\Doctrine\ORM\Traits\SoftDeleteableEntity::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Database\Doctrine\ORM\Traits\UpdatedAt::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Database\Doctrine\ORM\Traits\UuidTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Toolkit\Traits\BackedEnumTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Toolkit\Traits\DateTimeTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Traits\AppConfigTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Traits\ConfigTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Traits\EntityManagerTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Traits\UserPreferencesTrait::class)]
class EntityRepositoryControllerTest extends TestCase
{
  use EntityGeneratorTrait {
    EntityGeneratorTrait::setup as entitySetup;
  }

  private const OCS_OK = [
    'status' => 'ok',
    'statuscode' => Http::STATUS_OK,
    'message' => 'OK',
  ];

  private EntityRepositoryController $entityRepositoryController;

  private EntityRepository $projectsRepository;

  private EntityRepository $musiciansRepository;

  private EntityRepository $projectParticipantsRepository;

  private IRequest $request;

  private OCSMiddleware $ocsMiddleware;

  /** {@inheritdoc} */
  public function setup(): void
  {
    $this->entitySetup(persist: false);

    /** @var MockProvider $mockProvider */
    $mockProvider = MockProvider::create($this);

    $this->request = $mockProvider->getRequest();
    $this->request->method('getScriptName')->willReturn('/ocs/v2.php');
    $this->request->method('getFormat')->willReturn('json');

    $this->ocsMiddleware = new OCSMiddleware($this->request);

    $this->projectsRepository = $this->getMockBuilder(EntityRepository::class)
      ->disableOriginalConstructor()
      ->getMock();
    $this->projectsRepository->method('findBy')->willReturnCallback(
      fn(array $criteria) => $this->project->getId() == ($criteria['id'] ?? null) ? [ $this->project ] : [],
    );
    $this->projectsRepository->method('find')->willReturnCallback(
      fn(array $identifier) => $this->project->getId() == ($identifier['id'] ?? null) ? $this->project : null,
    );

    $this->musiciansRepository = $this->getMockBuilder(EntityRepository::class)
      ->disableOriginalConstructor()
      ->getMock();
    $this->musiciansRepository->method('findBy')->willReturnCallback(
      fn(array $criteria) => $this->musician->getId() == ($criteria['id'] ?? null) ? [ $this->musician ] : [],
    );
    $this->musiciansRepository->method('find')->willReturnCallback(
      fn(array $identifier) => $this->musician->getId() == ($identifier['id'] ?? null) ? $this->musician : null,
    );
    $this->musiciansRepository->expects($this->never())->method('findBy');

    $this->projectParticipantsRepository = $this->getMockBuilder(EntityRepository::class)
      ->disableOriginalConstructor()
      ->getMock();
    $this->projectParticipantsRepository->method('findBy')->willReturnCallback(
      function(array $criteria) {
        if (($criteria['project'] ?? 1) === 1 && ($criteria['musician'] ?? 1) === 1) {
          return [$this->participant];
        }
        return [];
      },
    );
    $this->projectParticipantsRepository->method('find')->willReturnCallback(
      function(array $identifier) {
        if ($identifier['project'] === 1 && $identifier['musician'] === 1) {
          return $this->participant;
        }
        return null;
      },
    );
    $this->projectParticipantsRepository->expects($this->never())->method('findBy');

    $this->entityManager = $this->getMockBuilder(EntityManager::class)
      ->disableOriginalConstructor()
      ->getMock();
     $this->entityManager->method('getRepository')->willReturnCallback(
      function(string $className) {
        switch ($className) {
          case Entities\Project::class:
            return $this->projectsRepository;
          case Entities\Musician::class:
            return $this->musiciansRepository;
          case Entities\ProjectParticipant::class:
            return $this->projectParticipantsRepository;
        }
        return null;
      },
    );

    /** @var DatabaseProvider $databaseProvider */
    $databaseProvider = \OCP\Server::get(DatabaseProvider::class);

    if (!$databaseProvider->getDatabaseConfig()) {
      $databaseProvider->startServer();
    }

    // Meta-data ATM does not work without database connection
    $realEntityManager = $mockProvider->getEntityManager();
    $realEntityManager->persist($this->project);

    $entitySerializer = new EntitySerializer(
      entityManager: $realEntityManager,
      l: $mockProvider->getL10N(),
      logger: $mockProvider->getLoggerInterface(),
    );

    $this->entityRepositoryController = new EntityRepositoryController(
      appName: $mockProvider->appName,
      request: $this->request,
      entityManager: $this->entityManager, // this is the mock
      entitySerializer: $entitySerializer,
      logger: $mockProvider->getLoggerInterface(),
      l: $mockProvider->getL10N(),
    );
  }

  /** @return void */
  public function testConstruction(): void
  {
    $this->projectsRepository->expects($this->never())->method('findBy');
    $this->projectsRepository->expects($this->never())->method('find');
    $this->entityManager->expects($this->never())->method('getRepository');
    $this->assertEquals(1, $this->project->getParticipants()->count());
    // $this->expectNotToPerformAssertions();
  }

  /**
   * @param string $entityName
   *
   * @param ?string $find
   *
   * @param ?string $findBy
   *
   * @param ?int $limit
   *
   * @param int $offset
   *
   * @param int $depth
   *
   * @param bool $throw
   *
   * @return $array
   */
  private function callGetEntities(
    string $entityName,
    ?string $find,
    ?string $findBy,
    ?int $limit,
    int $offset,
    int $depth,
    bool $throw = true,
  ): array {
    $this->ocsMiddleware->beforeController($this->entityRepositoryController, 'getEntities');
    try {
      $response = $this->entityRepositoryController->getEntities(
        entityName: $entityName,
        find: $find,
        findBy: $findBy,
        limit: $limit,
        offset: $offset,
        depth: $depth,
      );
      $response = $this->entityRepositoryController->buildResponse($response, $this->request->getFormat());
      $response = $this->ocsMiddleware->afterController($this->entityRepositoryController, 'getEntities', $response);
    } catch (OCS\OCSException $e) {
      if ($throw) {
        throw $e;
      }
      $response = $this->ocsMiddleware->afterException($this->entityRepositoryController, 'getEntities', $e);
    }
    return json_decode($response->render(), associative: true);
  }

  /** @return void */
  public function testGetEntitiesFindBy(): void
  {
    $this->projectsRepository->expects($this->never())->method('find');
    $this->projectsRepository->expects($this->once())->method('findBy');
    $this->entityManager->expects($this->once())->method('getRepository');
    $responseData = $this->callGetEntities(
      entityName: Entities\Project::class,
      find: null,
      findBy: base64_encode(json_encode([ 'id' => $this->project->getId() ])),
      limit: null,
      offset: 0,
      depth: 2,
    );
    $this->assertEquals(self::OCS_OK, $responseData['ocs']['meta']);
    $this->assertArrayHasKey('entities', $responseData['ocs']['data']);
    $this->assertArrayHasKey('repositories', $responseData['ocs']['data']);
  }

  /** @return void */
  public function testGetEntitiesFind(): void
  {
    $this->projectsRepository->expects($this->once())->method('find');
    $this->projectsRepository->expects($this->never())->method('findBy');
    $this->entityManager->expects($this->once())->method('getRepository');
    $responseData = $this->callGetEntities(
      entityName: Entities\Project::class,
      find: base64_encode(json_encode([ 'id' => $this->project->getId() ])),
      findBy: null,
      limit: null,
      offset: 0,
      depth: 2,
    );
    $this->assertEquals(self::OCS_OK, $responseData['ocs']['meta']);
    $this->assertArrayHasKey('entities', $responseData['ocs']['data']);
    $this->assertArrayHasKey('repositories', $responseData['ocs']['data']);
  }

  /** @return void */
  public function testGetEntitiesFindShortNames(): void
  {
    $this->projectsRepository->expects($this->once())->method('find');
    $this->projectsRepository->expects($this->never())->method('findBy');
    $this->entityManager->expects($this->once())->method('getRepository');
    $responseData = $this->callGetEntities(
      entityName: new ReflectionClass(Entities\Project::class)->getShortName(),
      find: base64_encode(json_encode([ 'id' => $this->project->getId() ])),
      findBy: null,
      limit: null,
      offset: 0,
      depth: 2,
    );
    $this->assertEquals(self::OCS_OK, $responseData['ocs']['meta']);
    $this->assertArrayHasKey('entities', $responseData['ocs']['data']);
    $this->assertEquals(1, count($responseData['ocs']['data']['entities']));
    $this->assertArrayHasKey('repositories', $responseData['ocs']['data']);
  }


  /** @return void */
  public function testFailNoSearchTerms(): void
  {
    $this->projectsRepository->expects($this->never())->method('find');
    $this->projectsRepository->expects($this->never())->method('findBy');
    $this->entityManager->expects($this->never())->method('getRepository');
    $this->expectException(OCS\OCSBadRequestException::class);
    $response = $this->callGetEntities(
      entityName: Entities\Project::class,
      find: null,
      findBy: null,
      limit: null,
      offset: 0,
      depth: 2,
    );
  }

  /** @return void */
  public function testFailConflictingArguments(): void
  {
    $this->projectsRepository->expects($this->never())->method('find');
    $this->projectsRepository->expects($this->never())->method('findBy');
    $this->entityManager->expects($this->never())->method('getRepository');
    $this->expectException(OCS\OCSBadRequestException::class);
    $response = $this->callGetEntities(
      entityName: Entities\Project::class,
      find: '',
      findBy: '',
      limit: null,
      offset: 0,
      depth: 2,
    );
  }

  /** @return void */
  public function testEntityNotFound(): void
  {
    $this->projectsRepository->expects($this->once())->method('find');
    $this->projectsRepository->expects($this->never())->method('findBy');
    $this->entityManager->expects($this->once())->method('getRepository');
    $identifier = [ 'id' => -1 ];
    try {
      $response = $this->callGetEntities(
        entityName: Entities\Project::class,
        find: base64_encode(json_encode($identifier)),
        findBy: null,
        limit: null,
        offset: 0,
        depth: 2,
      );
    } catch (OCS\OCSNotFoundException $ocsException) {
      $e = $ocsException->getPrevious();
      $this->assertInstanceOf(Exceptions\DatabaseEntityNotFoundException::class, $e);
      $this->assertEquals(Entities\Project::class, $e->entityClassName);
      $this->assertEquals($identifier, $e->identifier);
    }
  }
}
