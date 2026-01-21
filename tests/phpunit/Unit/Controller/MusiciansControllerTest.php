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

use ReflectionClass;

use PHPUnit\Framework\Attributes;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

use OCP\AppFramework\Http;
use OCP\AppFramework\OCS;
use OCP\IRequest;
use OC\AppFramework\Middleware\OCSMiddleware;

use OCA\CAFEVDB\Controller\EnumMusiciansSearchScope;
use OCA\CAFEVDB\Controller\MusiciansController;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Database\Doctrine\ORM\Repositories\EntityRepository;
use OCA\CAFEVDB\Database\Doctrine\ORM\Util\EntitySerializer;
use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Exceptions;
use OCA\CAFEVDB\Tests\DatabaseProvider;
use OCA\CAFEVDB\Tests\MockProvider;
use OCA\CAFEVDB\Tests\Unit\Database\Doctrine\ORM\Entities\EntityGeneratorTrait;

/** Test the MusiciansController. */
#[Attributes\CoversClass(MusiciansController::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\AppInfo\Application::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Common\Transliterator::class)]
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
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Listeners\DoctrineMigrationsListener::class)]
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
#[Attributes\UsesClass(\OCA\CAFEVDB\Events\MusicianEmailEvent::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Listener\MusicianEmailAddressEntityListener::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Listener\MusicianEmailPersistanceListener::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Maintenance\SanitizerRegistration::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Maintenance\Sanitizers\AbstractSanitizer::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Maintenance\Sanitizers\GoogleMailSanitizer::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\AuthorizationService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\ConfigService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\EmailAddressService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\EncryptionService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\InstrumentationService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\L10N\L10NFactory::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\Registration::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\ToolTipsService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Toolkit\DTO\AbstractDTO::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Toolkit\Service\ExecutableFinder::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Database\Doctrine\ORM\Traits\ArrayTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Database\Doctrine\ORM\Traits\AutoIncrementTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Database\Doctrine\ORM\Traits\CreatedAt::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Database\Doctrine\ORM\Traits\DateTimeTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Database\Doctrine\ORM\Traits\FactoryTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Database\Doctrine\ORM\Traits\SoftDeleteableEntity::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Database\Doctrine\ORM\Traits\UpdatedAt::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Database\Doctrine\ORM\Traits\UuidTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Toolkit\Traits\BackedEnumTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Toolkit\Traits\DateTimeTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Traits\AppConfigTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Traits\UserPreferencesTrait::class)]
class MusiciansControllerTest extends TestCase
{
  use EntityGeneratorTrait;
  use TestRoutesAreDefinedTrait;

  private const CONTROLLER_CLASS = MusiciansController::class;
  private const EXPECTED_ROUTES = ['ocs' => ['search']];

  private const OCS_OK = [
    'status' => 'ok',
    'statuscode' => Http::STATUS_OK,
    'message' => 'OK',
  ];

  private MusiciansController $musiciansController;

  private EntityRepository $musiciansRepository;

  private EntityRepository $projectsRepository;

  private IRequest $request;

  private OCSMiddleware $ocsMiddleware;

  /** {@inheritdoc} */
  public function setup(): void
  {
    $this->generateProjectParticipant(persist: false);

    /** @var MockProvider $mockProvider */
    $mockProvider = MockProvider::create($this);

    $this->request = $mockProvider->getRequest();
    $this->request->method('getScriptName')->willReturn('/ocs/v2.php');
    $this->request->method('getFormat')->willReturn('json');

    $this->ocsMiddleware = new OCSMiddleware($this->request);

    $this->projectsRepository = $this->getMockBuilder(EntityRepository::class)
      ->disableOriginalConstructor()
      ->getMock();
    $this->projectsRepository->method('findOneBy')->willReturnCallback(
      fn(array $criteria) => $this->project->getName() == ($criteria['name'] ?? null) ? $this->project : null,
    );
    $this->projectsRepository->expects($this->never())->method('createQueryBuilder');

    $this->musiciansRepository = $this->getMockBuilder(EntityRepository::class)
      ->disableOriginalConstructor()
      ->getMock();
    $this->musiciansRepository->method('findBy')->willReturnCallback(
      function(array $criteria) {
        $participationProject = end($criteria)['projectParticipation.project'] ?? null;
        if ($participationProject !== null && $participationProject != $this->project->getId()) {
          return [];
        }
        $ids = $criteria['id'] ?? [];
        if (in_array($this->musician->getId(), $ids)) {
          return [ $this->musician ];
        }
        $pattern = $criteria['displayName'] ?? 'this will not match';
        $pattern = trim($pattern, '%');
        if (str_contains($this->musician->getPublicName(), $pattern)) {
          return [ $this->musician ];
        }
        return [];
      }
    );
    $this->musiciansRepository->method('find')->willReturnCallback(
      fn(array $identifier) => $this->musician->getId() == ($identifier['id'] ?? null) ? $this->musician : null,
    );
    $this->musiciansRepository->expects($this->never())->method('createQueryBuilder');

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
        }
        return null;
      },
    );
    $this->entityManager->expects($this->never())->method('recryptEncryptedProperties');

    /** @var DatabaseProvider $databaseProvider */
    $databaseProvider = \OCP\Server::get(DatabaseProvider::class);

    if (!$databaseProvider->getDatabaseConfig()) {
      $databaseProvider->startServer();
    }

    // Meta-data ATM does not work without database connection
    $realEntityManager = $mockProvider->getEntityManager();
    $realEntityManager->persist($this->project);
    $realEntityManager->persist($this->musician);

    $entitySerializer = new EntitySerializer(
      entityManager: $realEntityManager,
      l: $mockProvider->getL10N(),
      logger: $mockProvider->getLoggerInterface(),
    );

    $this->musiciansController = new MusiciansController(
      appName: $mockProvider->appName,
      request: $this->request,
      entitySerializer: $entitySerializer,
      configService: $mockProvider->getConfigService(),
      entityManager: $this->entityManager, // this is the mock
      l: $mockProvider->getL10N(),
    );
  }

  /** @return void */
  public function testConstruction(): void
  {
    $this->projectsRepository->expects($this->never())->method('findBy');
    $this->projectsRepository->expects($this->never())->method('find');
    $this->musiciansRepository->expects($this->never())->method('findBy');
    $this->entityManager->expects($this->never())->method('getRepository');
  }

  /**
   * @param string $pattern
   *
   * @param null|int $limit
   *
   * @param null|int $offset
   *
   * @param null|string $projectName
   *
   * @param null|int $projectId
   *
   * @param array $ids
   *
   * @param string $scope
   *
   * @param bool $throw
   *
   * @return $array
   */
  private function callSearch(
    string $pattern,
    ?int $limit = null,
    ?int $offset = null,
    ?string $projectName = null,
    ?int $projectId = null,
    array $ids = [],
    string $scope = EnumMusiciansSearchScope::MUSICIANS->value,
    bool $throw = true,
  ): array {
    $this->ocsMiddleware->beforeController($this->musiciansController, 'search');
    try {
      $response = $this->musiciansController->search(
        pattern: $pattern,
        limit: $limit,
        offset: $offset,
        projectName: $projectName,
        projectId: $projectId,
        ids: $ids,
        scope: $scope,
      );
      $response = $this->musiciansController->buildResponse($response, $this->request->getFormat());
      $response = $this->ocsMiddleware->afterController($this->musiciansController, 'search', $response);
    } catch (OCS\OCSException $e) {
      if ($throw) {
        throw $e;
      }
      $response = $this->ocsMiddleware->afterException($this->musiciansController, 'search', $e);
    }
    return json_decode($response->render(), associative: true);
  }

  /** @return void */
  public function testSimpleSearch(): void
  {
    $this->entityManager->expects($this->exactly(1))->method('getRepository');
    $this->musiciansRepository->expects($this->exactly(1))->method('findBy');
    $this->projectsRepository->expects($this->exactly(0))->method('findBy');
    $result = $this->callSearch(pattern: '%', throw: true);
    $this->assertEqualsCanonicalizing(self::OCS_OK, $result['ocs']['meta']);
    $this->assertEquals(1, count($result['ocs']['data']['entities']['Musician']));
    // print_r($result);
  }

  /** @return void */
  public function testIdSearch(): void
  {
    $this->entityManager->expects($this->exactly(1))->method('getRepository');
    $this->musiciansRepository->expects($this->exactly(2))->method('findBy');
    $this->projectsRepository->expects($this->exactly(0))->method('findBy');
    $result = $this->callSearch(
      pattern: '',
      ids: [$this->musician->getId()],
      throw: true,
    );
    // print_r($result);
    $this->assertEqualsCanonicalizing(self::OCS_OK, $result['ocs']['meta']);
    $this->assertEquals(1, count($result['ocs']['data']['entities']['Musician']));
    // print_r($result);
  }
}
