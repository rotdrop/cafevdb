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

namespace OCA\CAFEVDB\Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes;
use PHPUnit\Framework\MockObject\MockObject;

use OCA\CAFEVDB\Service;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types\EnumParticipantFieldDataType as FieldDataType;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types\EnumParticipantFieldMultiplicity as FieldMultiplicity;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;

/** Test aspects of the LegacyMigrationsService class. */
#[Attributes\CoversClass(Service\ProjectParticipantFieldsService::class)]
class ProjectParticipantFieldsServiceTest extends TestCase
{
  use \OCA\CAFEVDB\Tests\Unit\Maintenance\Migrations\SetupMigrationTrait;
  use \OCA\CAFEVDB\Tests\Unit\Service\SetupCalendarBackendTrait;

  private Service\ProjectParticipantFieldsService $service;

  private static bool $migrationsApplied = false;

  /** {@inheritdoc} */
  public function setup(): void
  {
    $this->generateCalendarBackend();

    if (!self::$migrationsApplied) {
      $this->applyMigrations('latest');
      self::$migrationsApplied = true;
    }

    /** @var MockProvider $mockProvider */
    $mockProvider = $this->mockProvider = $this->mockProvider ?? MockProvider::create($this);

    /** @var ConfigService $configService */
    $configService = $mockProvider->getConfigService();

    $this->entityManager = $this->entityManager ?? $mockProvider->getEntityManager();

    $this->service = new Service\ProjectParticipantFieldsService(
      configService: $configService,
      entityManager: $this->entityManager,
    );
  }

  /** @return void */
  public function testSetup(): void
  {
  }

  /** @return void */
  #[Attributes\Depends('testSetup')]
  public function testUnapplyMigrations(): void
  {
    $this->unapplyMigrations();
    self::$migrationsApplied = false;
  }

  /** @return void */
  public function testCreateField(): void
  {
    $field = $this->service->createField(
      name: 'FieldName',
      multiplicity: FieldMultiplicity::SIMPLE,
      dataType: FieldDataType::TEXT,
      tooltip: 'Das ist nun wirklich nicht hilfreich',
    );
    $this->assertInstanceOf(Entities\ProjectParticipantField::class, $field);
  }

  /**
   * @var array
   *
   * The purpose of hardcoding the expected value is to catch changes and
   * force to think twice of it. Also, this instantiates some lines of code.
   */
  private const TYPE_MULTIPLICITY_SUPPORT_MATRIX = [
    'simple' => [
      'boolean' => false,
      'cloud-file' => true,
      'cloud-folder' => true,
      'date' => true,
      'datetime' => true,
      'db-file' => true,
      'float' => true,
      'html' => true,
      'integer' => true,
      'liabilities' => true,
      'receivables' => true,
      'text' => true,
    ],
    'single' => [
      'boolean' => true,
      'cloud-file' => false,
      'cloud-folder' => false,
      'date' => true,
      'datetime' => true,
      'db-file' => false,
      'float' => true,
      'html' => true,
      'integer' => true,
      'liabilities' => true,
      'receivables' => true,
      'text' => true,
    ],
    'multiple' => [
      'boolean' => false,
      'cloud-file' => false,
      'cloud-folder' => false,
      'date' => true,
      'datetime' => true,
      'db-file' => false,
      'float' => true,
      'html' => true,
      'integer' => true,
      'liabilities' => true,
      'receivables' => true,
      'text' => true,
    ],
    'parallel' => [
      'boolean' => false,
      'cloud-file' => true,
      'cloud-folder' => false,
      'date' => true,
      'datetime' => true,
      'db-file' => true,
      'float' => true,
      'html' => true,
      'integer' => true,
      'liabilities' => true,
      'receivables' => true,
      'text' => true,
    ],
    'recurring' => [
      'boolean' => false,
      'cloud-file' => false,
      'cloud-folder' => false,
      'date' => true,
      'datetime' => true,
      'db-file' => true,
      'float' => false,
      'html' => false,
      'integer' => false,
      'liabilities' => true,
      'receivables' => true,
      'text' => true,
    ],
    'groupofpeople' => [
      'boolean' => false,
      'cloud-file' => false,
      'cloud-folder' => false,
      'date' => true,
      'datetime' => true,
      'db-file' => false,
      'float' => true,
      'html' => true,
      'integer' => true,
      'liabilities' => true,
      'receivables' => true,
      'text' => true,
    ],
    'groupsofpeople' => [
      'boolean' => false,
      'cloud-file' => false,
      'cloud-folder' => false,
      'date' => true,
      'datetime' => true,
      'db-file' => false,
      'float' => true,
      'html' => true,
      'integer' => true,
      'liabilities' => true,
      'receivables' => true,
      'text' => true,
    ],
  ];

  /** @return void */
  public function testIsSupportedType(): void
  {
    $supported = [];
    foreach (FieldMultiplicity::cases() as $multiplicity) {
      foreach (FieldDataType::cases() as $type) {
        $supported[$multiplicity->value][$type->value] = $this->service->isSupportedType($multiplicity, $type);
      }
    }
    $this->assertEqualsCanonicalizing(self::TYPE_MULTIPLICITY_SUPPORT_MATRIX, $supported);
  }
}
