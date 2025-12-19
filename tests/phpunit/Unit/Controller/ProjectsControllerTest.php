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

use PHPUnit\Framework\Attributes;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

use OCP\IRequest;

use OCA\CAFEVDB\Controller\ProjectsController;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Database\Legacy\PME\PHPMyEdit;
use OCA\CAFEVDB\Service\DTO\EventMatrixRow;
use OCA\CAFEVDB\Service\EventsService;
use OCA\CAFEVDB\Service\MusicianService;
use OCA\CAFEVDB\Service\ProjectParticipantFieldsService;
use OCA\CAFEVDB\Service\ProjectService;
use OCA\CAFEVDB\Storage\UserStorage;
use OCA\CAFEVDB\Tests\Unit\Service\SetupEventsServiceTrait;

/** Test the ProjectsController class. */
#[Attributes\CoversClass(ProjectsController::class)]
#[Attributes\CoversClass(\OCA\CAFEVDB\Service\DTO\EventMatrixEvent::class)]
#[Attributes\CoversClass(\OCA\CAFEVDB\Service\DTO\EventMatrixRow::class)]
#[Attributes\CoversClass(\OCA\CAFEVDB\Service\DTO\EventTimes::class)]
#[Attributes\CoversClass(\OCA\CAFEVDB\Service\DTO\HumanDateTime::class)]
#[Attributes\CoversMethod(EventsService::class, 'eventMatrix')]
#[Attributes\CoversMethod(ProjectsController::class, 'get')]
#[Attributes\UsesClass(\OCA\CAFEVDB\AppInfo\Application::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Common\Uuid::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Crypto\HaliteCryptoFactory::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Crypto\HaliteSymmetricStreamCryptor::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Crypto\Registration::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\Musician::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\MusicianEmailAddress::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\Project::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\ProjectEvent::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\ProjectParticipant::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\SepaBankAccount::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Events\EncryptionServiceBound::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Legacy\Calendar\OC_Calendar_Object::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Listener\TranslationNotFoundListener::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\AuthorizationService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\CalDavService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\ConfigService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\EncryptionService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\EventsService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\InstrumentationService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\L10N\L10NFactory::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\ProjectService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\Registration::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\VCalendarService::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Toolkit\Traits\BackedEnumTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Database\Doctrine\ORM\Traits\ArrayTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Database\Doctrine\ORM\Traits\CreatedAt::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Database\Doctrine\ORM\Traits\DateTimeTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Database\Doctrine\ORM\Traits\FactoryTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Database\Doctrine\ORM\Traits\SoftDeleteableEntity::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Database\Doctrine\ORM\Traits\UpdatedAt::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Database\Doctrine\ORM\Traits\UuidTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Traits\AppConfigTrait::class)]
class ProjectsControllerTest extends TestCase
{
  use SetupEventsServiceTrait
  {
    SetupEventsServiceTrait::setup as setupEventsService;
  }

  private ProjectsController $projectsController;

  /** {@inheritdoc} */
  public function setup(): void
  {
    $this->setupEventsService();
    $this->mockProvider->registerClassInstance(EventsService::class, $this->eventsService);

    /** @var IRequest $request */
    $request = $this->createStub(IRequest::class);
    $this->mockProvider->registerClassInstance(IRequest::class, $request);

    /** @var PHPMyEdit $pme */
    $pme = $this->createStub(PHPMyEdit::class);

    /** @var UserStorage $userStorage */
    $userStorage = $this->createStub(UserStorage::class);

    /** @var ProjectParticipantFieldsService $projectParticipantFieldsService */
    $projectParticipantFieldsService = $this->createStub(ProjectParticipantFieldsService::class);

    /** @var MusicianService $musiciansService */
    $musiciansService = $this->createStub(MusicianService::class);

    $projectService = new ProjectService(
      configService: $this->configService,
      entityManager: $this->entityManager,
      userStorage: $userStorage,
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
      pme: $pme,
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
