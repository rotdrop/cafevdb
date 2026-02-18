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

namespace OCA\CAFEVDB\Tests\Unit\Controller;

use DOMDocument;

use PHPUnit\Framework\Attributes;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

use OCP\AppFramework\Http;

use OCA\CAFEVDB\Controller\SepaDebitMandatesController;
use OCA\CAFEVDB\Controller\DTO;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Database\Doctrine\ORM\Repositories\EntityRepository;
use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Service\Finance\FinanceService;
use OCA\CAFEVDB\Service\FuzzyInputService;
use OCA\CAFEVDB\Service\ProjectService;
use OCA\CAFEVDB\Storage\Database\Factory as StorageFactory;
use OCA\CAFEVDB\Tests\MockProvider;
use OCA\CAFEVDB\Tests\Unit\Database\Doctrine\ORM\Entities\EntityGeneratorTrait;
use OCA\CAFEVDB\Tests\Unit\Database\Doctrine\ORM\Entities\MockMusiciansRepositoryTrait;
use OCA\CAFEVDB\Tests\Unit\Database\Doctrine\ORM\Entities\MockProjectsRepositoryTrait;

/** Test aspects of the SepaDebitMandatesController */
#[Attributes\CoversClass(DTO\SepaBankAccount::class)]
#[Attributes\CoversClass(DTO\SepaDebitMandate::class)]
#[Attributes\CoversClass(SepaDebitMandatesController::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\AppInfo\Application::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Common\TimeFactory::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Common\Uuid::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Crypto\HaliteCryptoFactory::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Crypto\HaliteSymmetricStreamCryptor::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\Musician::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\MusicianEmailAddress::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\Project::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\ProjectParticipant::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\SepaBankAccount::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\SepaDebitMandate::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Events\EncryptionServiceBound::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\ConfigService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\EncryptionService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\InstrumentationService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\L10N\L10NFactory::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\Registration::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\ToolTipsDataService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\ToolTipsService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Toolkit\DTO\AbstractDTO::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Toolkit\DTO\AbstractResponseDTO::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Toolkit\Response\PreRenderedTemplateResponse::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Database\Doctrine\ORM\Traits\ArrayTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Database\Doctrine\ORM\Traits\AutoIncrementTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Database\Doctrine\ORM\Traits\CreatedAt::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Database\Doctrine\ORM\Traits\DateTimeTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Database\Doctrine\ORM\Traits\FactoryTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Database\Doctrine\ORM\Traits\SoftDeleteableEntity::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Database\Doctrine\ORM\Traits\UnusedTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Database\Doctrine\ORM\Traits\UpdatedAt::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Database\Doctrine\ORM\Traits\UuidTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Toolkit\Traits\BackedEnumTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Traits\AppConfigTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Traits\UserPreferencesTrait::class)]
class SepaDebitMandatesControllerTest extends TestCase
{
  use EntityGeneratorTrait;
  use MockMusiciansRepositoryTrait;
  use MockProjectsRepositoryTrait;
  use TestRoutesAreDefinedTrait;

  private const CONTROLLER_CLASS = SepaDebitMandatesController::class;
  private const EXPECTED_ROUTES = [
    'mandatevalidate',
    'mandateform',
    'mandatestore',
    'prefilledmandateform',
    'mandatedelete',
    'mandatedisable',
    'mandatereactivate',
    'mandatehardcopy',
    'accountdelete',
    'accountdisable',
    'accountreactivate',
  ];

  private SepaDebitMandatesController $controller;

  private MockProvider $mockProvider;

  /** {@inheritdoc} */
  public function setup(): void
  {
    $this->generateProjectParticipant(persist: false);

    $this->mockProvider = $this->mockProvider ?? MockProvider::create($this);

    $this->getEntityManagerMock();
    $this->entityManager->expects($this->never())->method('recryptEncryptedProperties');
    $this->getMusiciansRepositoryMock();
    $this->getProjectsRepositoryMock();

    $appContainer = $this->mockProvider->getAppContainer();

    $this->controller = new SepaDebitMandatesController(
      appName: $this->mockProvider->appName,
      request: $this->mockProvider->getRequest(),
      bav: $this->mockProvider->getBankAccountValidator(),
      financeService: $appContainer->get(FinanceService::class),
      fuzzyInputService: $appContainer->get(FuzzyInputService::class),
      projectService: $this->createStub(ProjectService::class),
      storageFactory: $this->createStub(StorageFactory::class),
      configService: $this->mockProvider->getConfigService(),
      entityManager: $this->entityManager,
    );
  }

  /** @return void */
  public function testSetup(): void
  {
    $this->assertInstanceOf(Entities\Musician::class, $this->musician);
    $this->assertNotNull($this->musician->getId());
  }

  /** @return void */
  public function testMandateFormBlank(): void
  {
    $result = $this->controller->mandateForm(
      musicianId: $this->musician->getId(),
      projectId: 0,
      bankAccountSequence: 0,
      mandateSequence: 0,
    );
    $this->assertTrue($result instanceof Http\JSONResponse || $result instanceof Http\DataResponse);
    $data = $result->getData();
    $this->assertInstanceOf(DTO\SepaDebitMandate::class, $data);

    $domDoc = new DOMDocument('1.0', 'UTF-8');
    $domDoc->encoding = 'UTF-8';
    $domDoc->loadHTML($data->contents, LIBXML_PEDANTIC);
    // print_r($result);
  }
}
