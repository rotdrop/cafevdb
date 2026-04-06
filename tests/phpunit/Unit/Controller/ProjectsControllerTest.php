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

use DateTimeImmutable;
use InvalidArgumentException;
use Throwable;

use PHPUnit\Framework\Attributes;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

use OCP\AppFramework\Http\JSONResponse;
use OCP\Files\Node;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\Security\ISecureRandom;

use OCA\CAFEVDB\Controller\DTO\ProjectValidationResponse;
use OCA\CAFEVDB\Controller\EnumProjectValidationTopic;
use OCA\CAFEVDB\Controller\ProjectsController;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types\EnumProjectTemporalType;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Database\Legacy\PME\PHPMyEdit;
use OCA\CAFEVDB\Exceptions;
use OCA\CAFEVDB\Service\DTO\EventMatrixRow;
use OCA\CAFEVDB\Service\EventsService;
use OCA\CAFEVDB\Service\MusicianService;
use OCA\CAFEVDB\Service\ProjectParticipantFieldsService;
use OCA\CAFEVDB\Service\ProjectService;
use OCA\CAFEVDB\Storage\UserStorage;
use OCA\CAFEVDB\Tests\MockProvider;
use OCA\CAFEVDB\Tests\Unit\Database\Legacy\PME\GetPMEStubTrait;
use OCA\CAFEVDB\Tests\Unit\Service\SetupEventsServiceTrait;
use OCA\CAFEVDB\Tests\Unit\Storage\MockUserStorageTrait;
use OCA\CAFEVDB\Toolkit;

/** Test the ProjectsController class. */
#[Attributes\CoversClass(EventsService::class)]
#[Attributes\CoversClass(ProjectsController::class)]
#[Attributes\CoversClass(\OCA\CAFEVDB\Service\DTO\EventMatrixEvent::class)]
#[Attributes\CoversClass(\OCA\CAFEVDB\Service\DTO\EventMatrixRow::class)]
#[Attributes\CoversClass(\OCA\CAFEVDB\Service\DTO\EventTimes::class)]
#[Attributes\CoversClass(\OCA\CAFEVDB\Service\DTO\HumanDateTime::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\AppInfo\Application::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Common\TimeFactory::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Common\Util::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Common\Uuid::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Controller\DTO\DownloadsShareResponse::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Controller\DTO\MessagesResponse::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Controller\DTO\ProjectValidationResponse::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Crypto\HaliteCryptoFactory::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Crypto\HaliteSymmetricStreamCryptor::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Crypto\Registration::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\Musician::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\MusicianEmailAddress::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\Project::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\ProjectEvent::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\ProjectParticipant::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\SepaBankAccount::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Repositories\ProjectsRepository::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Legacy\PME\DefaultOptions::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Events\EncryptionServiceBound::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Exceptions\EnduserNotificationException::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Legacy\Calendar\OC_Calendar_Object::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Listener\TranslationNotFoundListener::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\AuthorizationService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\CalDavService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\ConfigService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\EncryptionService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\InstrumentationService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\L10N\AppL10N::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\L10N\L10NFactory::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\ProjectService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\Registration::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\ToolTipsService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\VCalendarService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Toolkit\DTO\AbstractResponseDTO::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Database\Doctrine\ORM\Traits\ArrayTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Database\Doctrine\ORM\Traits\CreatedAt::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Database\Doctrine\ORM\Traits\DateTimeTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Database\Doctrine\ORM\Traits\FactoryTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Database\Doctrine\ORM\Traits\SoftDeleteableEntity::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Database\Doctrine\ORM\Traits\UpdatedAt::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Database\Doctrine\ORM\Traits\UuidTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Toolkit\Traits\BackedEnumTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Traits\AppConfigTrait::class)]
class ProjectsControllerTest extends TestCase
{
  use GetPMEStubTrait;
  use MockUserStorageTrait;
  use SetupEventsServiceTrait;
  use TestRoutesAreDefinedTrait;

  private const CONTROLLER_CLASS = ProjectsController::class;
  private const EXPECTED_ROUTES = [
    'validate',
    'changeinstrumentation',
    'mailinglists',
    'get',
    'post',
    'delete',
    'patch',
  ];

  private ProjectsController $projectsController;

  private IURLGenerator $urlGenerator;

  private Toolkit\Service\SimpleSharingService $simpleSharingService;

  private array $postData = [];

  private array $linkShares = [];

  private array $linkSharesByPath = [];

  private int $shareId = 1;

  /** {@inheritdoc} */
  public function setup(): void
  {
    $this->generateEventsService();

    $this->mockProvider->registerClassInstance(EventsService::class, $this->eventsService);

    /** @var IRequest $request */
    $request = $this->createStub(IRequest::class);
    $request->method('getParam')->willReturnCallback(
      function(string $key, mixed $default = null) {
        return $this->postData[$key] ?? $default;
      },
    );
    $request->method('getParams')->willReturnCallback(fn() => $this->postData);
    $this->mockProvider->registerClassInstance(IRequest::class, $request);

    $this->getPHPMyEditStub();

    $this->userStorage = $this->getUserStorageStub();
    $this->mockProvider->registerClassInstance(Storage\UserStorage::class, $this->userStorage, global: true);

    $this->urlGenerator = $this->appContainer->get(IURLGenerator::class);

    /** @var Toolkit\Service\SimpleSharingService $simpleSharingService */
    $this->simpleSharingService = $this->createStub(Toolkit\Service\SimpleSharingService::class);
    $this->mockProvider->registerClassInstance(Toolkit\Service\SimpleSharingService::class, $this->simpleSharingService, global: true);
    $this->simpleSharingService->method('linkShare')->willReturnCallback(
      function(
        Node $folder,
        ?string $shareOwner = null,
        int $sharePerms = \OCP\Constants::PERMISSION_CREATE,
        mixed $expirationDate = null,
        ?string $password = null,
        bool $noCreate = false,
        ?string $newShareOwner = null,
      ) {
        $filesSharing = $this->linkSharesByPath[$folder->getPath()] ?? null;
        if ($filesSharing) {
          $share = $this->linkShares[$filesSharing]['share'];
          $dav = $this->linkShares[$filesSharing]['dav'];
        } else {
          $token = $this->appContainer->get(ISecureRandom::class)->generate(\OC\Share\Helper::DEFAULT_TOKEN_LENGTH, ISecureRandom::CHAR_HUMAN_READABLE);
          $filesSharing = $this->urlGenerator->linkToRouteAbsolute('files_sharing.sharecontroller.showShare', ['token' => $token]);
          $dav = $this->urlGenerator->getAbsoluteURL('/public.php/dav/files/' . $token);
          $share = $this->createStub(\OCP\Share\IShare::class);
          $share->method('getNode')->willReturn($folder);
          $share->method('getId')->willReturn($this->shareId++);
          $share->method('getPassword')->willReturn($password);
          $share->method('getExpirationDate')->willReturn($expirationDate);
          $this->linkSharesByPath[$folder->getPath()] = $filesSharing;
          $this->linkShares[$filesSharing] = [
            'token' => $token,
            'node' => $folder,
            'files_sharing' => $filesSharing,
            'share' => $share,
            'dav' => $dav,
            'password' => $password,
          ];
        }
        return ['files_sharing' => $filesSharing, 'share' => $share, 'dav' => $dav];
      },
    );
    $this->simpleSharingService->method('getLinkExpirationDate')->willReturn(DateTimeImmutable::createFromFormat('Y-m-d', '2099-01-01'));
    $this->simpleSharingService->method('getShareFromUrl')->willReturnCallback(function(string $url) {
      return $this->linkShares[$url]['share'] ?? null;
    });

    /** @var ProjectParticipantFieldsService $projectParticipantFieldsService */
    $projectParticipantFieldsService = $this->createStub(ProjectParticipantFieldsService::class);

    /** @var MusicianService $musiciansService */
    $musiciansService = $this->createStub(MusicianService::class);

    $projectService = new ProjectService(
      configService: $this->configService,
      entityManager: $this->entityManager,
      userStorage: $this->userStorage,
      participantFieldsService: $projectParticipantFieldsService,
      musicianService: $musiciansService,
      eventDispatcher: $this->mockProvider->getEventDispatcher(),
    );
    $this->mockProvider->registerClassInstance(ProjectService::class, $projectService);

    $this->projectsController = new ProjectsController(
      appName: $this->mockProvider->appName,
      request: $request,
      configService: $this->configService,
      entityManager: $this->entityManager,
      pme: $this->pme,
    );
  }

  /** @return void */
  public function testValidateUnknownRequest(): void
  {
    $this->entityManager->expects($this->never())->method('getRepository');
    $this->expectException(InvalidArgumentException::class);
    $this->projectsController->validate('something');
  }

  private const PROJECT_YEAR = 1984;
  private const PROJECT_NAME = 'ProjectName';

  private const NAME_VALIDATION = [
    [
      'control' => 'name',
      'input' => [
        'name' => self::PROJECT_NAME . self::PROJECT_YEAR,
        'year' => self::PROJECT_YEAR,
        'type' => EnumProjectTemporalType::TEMPORARY->value,
      ],
      'result' => true,
    ],
    [
      'control' => 'submit',
      'input' => [
        'name' => self::PROJECT_NAME . self::PROJECT_YEAR,
        'year' => self::PROJECT_YEAR,
        'type' => EnumProjectTemporalType::TEMPORARY->value,
      ],
      'result' => true,
    ],
    [
      'control' => 'year',
      'input' => [
        'name' => self::PROJECT_NAME . self::PROJECT_YEAR,
        'year' => self::PROJECT_YEAR,
        'type' => EnumProjectTemporalType::TEMPORARY->value,
      ],
      'result' => true,
    ],
    [
      'control' => null,
      'input' => [
        'name' => self::PROJECT_NAME . self::PROJECT_YEAR,
        'year' => self::PROJECT_YEAR,
        'type' => EnumProjectTemporalType::TEMPORARY->value,
      ],
      'result' => true,
    ],
    [
      'control' => null,
      'input' => [
        'name' => self::PROJECT_NAME,
        'year' => self::PROJECT_YEAR,
        'type' => EnumProjectTemporalType::PERMANENT->value,
      ],
      'result' => true,
    ],
    [
      'control' => null,
      'input' => [
        'name' => self::PROJECT_NAME,
        'year' => self::PROJECT_YEAR,
        'type' => EnumProjectTemporalType::TEMPORARY->value,
      ],
      'result' => [
        'output' => [
          'name' => self::PROJECT_NAME . self::PROJECT_YEAR,
        ],
      ],
    ],
    [
      'control' => null,
      'input' => [
        'name' => 'project name ' . self::PROJECT_YEAR,
        'year' => self::PROJECT_YEAR,
        'type' => EnumProjectTemporalType::TEMPORARY->value,
      ],
      'result' => [
        'output' => [
          'name' => self::PROJECT_NAME . self::PROJECT_YEAR,
        ],
      ],
    ],
    [
      'control' => 'name',
      'input' => [
        'name' => self::PROJECT_NAME . self::PROJECT_YEAR,
        'year' => 84,
        'type' => EnumProjectTemporalType::TEMPORARY->value,
      ],
      'result' => true,
    ],
    [
      'control' => 'submit',
      'input' => [
        'name' => self::PROJECT_NAME . self::PROJECT_YEAR,
        'year' => 84,
        'type' => EnumProjectTemporalType::TEMPORARY->value,
      ],
      'result' => [
        'exception' => Exceptions\EnduserNotificationException::class,
      ],
    ],
  ];

  /** @return void */
  public function testValidateProjectName(): void
  {
    $this->entityManager->expects($this->atLeastOnce())->method('getRepository')->with(Entities\Project::class);
    foreach (self::NAME_VALIDATION as $testCase) {
      $this->postData = [];
      if (!empty($testCase['control'])) {
        $this->postData['control'] = $testCase['control'];
      }
      foreach ($testCase['input'] as $key => $value) {
        $this->postData[$this->pme->cgiDataName($key)] = $value;
      }
      if ($testCase['result'] === true) {
        $response = $this->projectsController->validate(EnumProjectValidationTopic::NAME);
        $this->assertInstanceOf(JSONResponse::class, $response);
        $data = $response->getData();
        $this->assertInstanceOf(ProjectValidationResponse::class, $data);
        $this->assertEquals([], $data->messages);
        $this->assertNull($data->hints);
      } elseif (is_array($testCase['result']['output'])) {
        $response = $this->projectsController->validate(EnumProjectValidationTopic::NAME);
        $this->assertInstanceOf(JSONResponse::class, $response);
        $data = $response->getData();
        $this->assertInstanceOf(ProjectValidationResponse::class, $data);
        foreach ($testCase['result']['output'] as $key => $value) {
          $this->assertEquals($value, $data->{$key});
        }
      } elseif (is_string($testCase['result']['exception'])) {
        try {
          $this->projectsController->validate(EnumProjectValidationTopic::NAME);
          $this->assertTrue(false, 'The case should throw "' . $testCase['result']['exception'] . '".');
        } catch (Throwable $t) {
          $this->assertInstanceOf($testCase['result']['exception'], $t);
        }
      }
    }
  }

  /** @return void */
  public function testPostFailures(): void
  {
    try {
      $this->projectsController->post(
        projectId: 12345,
        topic: 'blah',
        subTopic: 'blub',
      );
    } catch (Throwable $t) {
      $this->assertInstanceOf(Exceptions\EnduserNotificationException::class, $t);
    }
    try {
      $this->projectsController->post(
        projectId: $this->project->getId(),
        topic: 'blah',
        subTopic: 'blub',
      );
    } catch (Throwable $t) {
      $this->assertInstanceOf(Exceptions\EnduserNotificationException::class, $t);
    }
    try {
      $this->projectsController->post(
        projectId: $this->project->getId(),
        topic: ProjectsController::GET_PROJECT_SHARE,
        subTopic: 'blub',
      );
    } catch (Throwable $t) {
      $this->assertInstanceOf(Exceptions\EnduserNotificationException::class, $t);
    }
  }

  /** @return void */
  public function testPostProjectShare(): void
  {
    $this->entityManager->expects($this->never())->method('getRepository')->with(Entities\Project::class);
    $this->projectsController->post(
      projectId: $this->project->getId(),
      topic: ProjectsController::GET_PROJECT_SHARE,
      subTopic: ProjectService::FOLDER_TYPE_DOWNLOADS,
    );
  }

  /** @return void */
  public function testGetEventMatrix(): void
  {
    $this->entityManager->expects($this->atLeastOnce())->method('getRepository')->with(Entities\ProjectEvent::class);
    $this->calendarManager->expects($this->atLeastOnce())->method('getCalendars');
    $this->calDavBackend->expects($this->atLeastOnce())->method('getCalendarObject');
    $response = $this->projectsController->get(
      $this->project->getId(),
      ProjectsController::GET_EVENT_MATRIX,
    );
    $matrix = $response->render();
    $matrix = json_decode($matrix, true);
    $matrix = array_map(fn(array $row) => EventMatrixRow::fromArray($row), $matrix);
    $this->eventMatrixTest($matrix);
  }
}
