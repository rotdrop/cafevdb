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

namespace OCA\CAFEVDB\Tests\Unit\Service\Finance\SepaBulkTransactionService;

use Carbon\CarbonImmutable as DateTime;

use PHPUnit\Framework\Attributes;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

use OCA\CAFEVDB\Service\VCalendarService;
use OCA\CAFEVDB\Service\Finance\SepaBulkTransactionService\EventDataDTO as TestedDTO;

/** Consistency test for ValidatePhoneResponse DTO. */
#[Attributes\CoversClass(TestedDTO::class)]
class EventDataDTOTest extends TestCase
{
  use \OCA\CAFEVDB\Tests\Unit\Controller\DTO\TestDTOTrait;

  private const DTO_CLASS = TestedDTO::class;

  private TestedDTO $dto;

  /** {@inheritdoc} */
  public function setup(): void
  {
    $this->dto = new TestedDTO(
      summary: 'a string',
      description: 'another string',
      start: new DateTime,
    );
  }

  /** {@inheritdoc} */
  public function testDefaultArguments(): void
  {
    $this->assertEquals($this->dto->start, $this->dto->end);
    $this->assertEquals($this->dto->allDay, true);
    $this->assertEquals($this->dto->alarm, 0);
  }


  /** {@inheritdoc} */
  public function testNonDefaultArguments(): void
  {
    $start = new DateTime;
    $end = $start->addSeconds(1);
    $alarm = [ VCalendarService::VALARM_FROM_START => 42 ];
    $this->dto = new TestedDTO(
      summary: 'a string',
      description: 'another string',
      start: $start,
      end: $end,
      allDay: false,
      alarm: $alarm,
    );
    $this->assertNotEquals($this->dto->start, $this->dto->end);
    $this->assertEquals($this->dto->allDay, false);
    $this->assertEqualsCanonicalizing($this->dto->alarm, $alarm);
  }
}
