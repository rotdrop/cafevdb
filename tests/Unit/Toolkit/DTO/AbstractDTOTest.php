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
 *
 * @phpcs:disable PSR1.Classes.ClassDeclaration.MultipleClasses
 */

namespace OCA\CAFEVDB\Tests\Unit\Controller;

use DateTime;
use DateTimeImmutable;
use JsonSerializable;

use PHPUnit\Framework\Attributes;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;

use OCA\CAFEVDB\Toolkit\DTO;
use OCA\CAFEVDB\Wrapped\Carbon\CarbonImmutable;
use OCA\CAFEVDB\Wrapped\Carbon\Carbon;

/** Example JSON serializable */
class Serializable implements JsonSerializable
{
  /** {@inheritdoc} */
  public function jsonSerialize(): mixed
  {
    return [ 'serialized' => true ];
  }
}

/** Example DTO. */
class ExampleDTO extends DTO\AbstractResponseDTO
{
  /** {@inheritdoc} */
  public function __construct(
    public readonly CarbonImmutable $carbonImmutable,
    public readonly Carbon $carbon,
    public readonly DateTime $dateTime,
    public readonly DateTimeImmutable $dateTimeImmutable,
    public readonly Serializable $serializable,
  ) {
  }
}

/**
 * Test abstract DTO classes, in particular date-time serialization.
 *
 * @phpcs:ignore PSR1.Classes.ClassDeclaration.MultipleClasses
 */
#[Attributes\CoversClass(DTO\AbstractDTO::class)]
#[Attributes\CoversClass(DTO\AbstractResponseDTO::class)]
class AbstractDTOTest extends TestCase
{
  private const FORMAT_ARGS = ['Y-m-d h:i:s', '2025-11-04 01:02:03'];

  private ExampleDTO $dto;

  /**
   * {@inheritdoc}
   *
   * @return void
   */
  public function setup(): void
  {
    $this->dto = new ExampleDTO(
      carbon: Carbon::createFromFormat(...self::FORMAT_ARGS),
      carbonImmutable: CarbonImmutable::createFromFormat(...self::FORMAT_ARGS),
      dateTime: DateTime::createFromFormat(...self::FORMAT_ARGS),
      dateTimeImmutable: DateTimeImmutable::createFromFormat(...self::FORMAT_ARGS),
      serializable: new Serializable,
    );
  }

  /** @return void */
  public function testConstructor(): void
  {
    $this->expectNotToPerformAssertions();
  }

  const JSON_DATA = '{
    "carbonImmutable": "2025-11-04T01:02:03.000000Z",
    "carbon": "2025-11-04T01:02:03.000000Z",
    "dateTime": "2025-11-04T01:02:03+00:00",
    "dateTimeImmutable": "2025-11-04T01:02:03+00:00",
    "serializable": {
        "serialized": true
    }
}';

  /** @return void */
  public function testJsonSerialization(): void
  {
    $this->assertEquals(self::JSON_DATA, json_encode($this->dto, JSON_PRETTY_PRINT));
  }

  /** @return void */
  public function testGetKeys(): void
  {
    $keys = ['carbon', 'carbonImmutable', 'dateTime', 'dateTimeImmutable', 'serializable'];
    $this->assertEqualsCanonicalizing($keys, $this->dto->getKeys());
  }

  const TO_ARRAY_DATA = [
    'carbonImmutable' => '2025-11-04T01:02:03.000000Z',
    'carbon' => '2025-11-04T01:02:03.000000Z',
    'dateTime' => '2025-11-04T01:02:03+00:00',
    'dateTimeImmutable' => '2025-11-04T01:02:03+00:00',
    'serializable' => [ 'serialized' => true ],
  ];

  /** @return void */
  public function testToArray(): void
  {
    $this->assertEqualsCanonicalizing(self::TO_ARRAY_DATA, $this->dto->toArray());
  }

  /** @return void */
  public function testResponse(): void
  {
    $this->assertInstanceOf(JSONResponse::class, $this->dto->response());
    $this->assertEquals(json_encode($this->dto), $this->dto->response()->render());
    $this->assertEquals(Http::STATUS_CONFLICT, $this->dto->response(Http::STATUS_CONFLICT)->getStatus());
  }
}
