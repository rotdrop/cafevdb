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

use DateTime;

use PHPUnit\Framework\Attributes;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

use OCA\CAFEVDB\Common\Uuid;
use OCA\CAFEVDB\Controller\DTO\ParticipantFieldPropertyGetDefaultValue as DefaultValue;
use OCA\CAFEVDB\Controller\DTO\ParticipantFieldPropertyGetResponse as TestedDTO;
use OCA\CAFEVDB\Controller\EnumParticipantFieldPropertyGet as EnumPropertyGet;

/** Consistency test for ValidatePhoneResponse DTO. */
#[Attributes\CoversClass(DefaultValue::class)]
#[Attributes\CoversClass(TestedDTO::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Common\Uuid::class)]
class ParticipantFieldPropertyGetResponseDTOTest extends TestCase
{
  use TestResponseDTOTrait;

  private const DTO_CLASS = TestedDTO::class;

  private TestedDTO $dto;

  private const DUMMY_KEY = 'e8ab7bba-ec9c-11f0-a659-250679a0522d';

  /**
   * {@inheritdoc}
   *
   * @return void
   */
  public function setup(): void
  {
    $this->dto = new TestedDTO(
      messages: [ 'A Message' ],
      fieldId: 13,
      property: EnumPropertyGet::DEFAULT_DEPOSIT,
      value: new DefaultValue(key: Uuid::asUuid(self::DUMMY_KEY), data: 'data'),
    );
  }
}
