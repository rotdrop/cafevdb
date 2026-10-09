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

namespace OCA\CAFEVDB\Tests\Unit\Controller\DTO;

use DateTimeImmutable;

use PHPUnit\Framework\Attributes;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

use OCP\Constants as CoreConstants;

use OCA\CAFEVDB\Toolkit\Common\DecimalRationalP4S4 as RateNumberType;
use OCA\CAFEVDB\Controller\DTO\InsuranceRateValidationResponse as TestedDTO;

/** Consistency test for ValidatePhoneResponse DTO. */
#[Attributes\CoversClass(TestedDTO::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Toolkit\Common\RationalNumber::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Toolkit\Common\AbstractDecimalRational::class)]
class InsuranceRateValidationResponseDTOTest extends TestCase
{
  use TestResponseDTOTrait;

  private const TEST_RATE_STRING = '0.0005';

  private const DTO_CLASS = TestedDTO::class;

  private TestedDTO $dto;

  /**
   * {@inheritdoc}
   *
   * @return void
   */
  public function setup(): void
  {
    $this->dto = new TestedDTO(
      messages: [ 'A Message' ],
      rate: RateNumberType::create(self::TEST_RATE_STRING),
      date: new DateTimeImmutable('2099-01-01'),
      policyNumber: 'just a string',
    );
  }

  /** {@inheritdoc} */
  public function testSerializedRateRepresentation(): void
  {
    $result = $this->dto->jsonSerialize();
    $this->assertEquals(self::TEST_RATE_STRING, $result['rate']);
  }
}
