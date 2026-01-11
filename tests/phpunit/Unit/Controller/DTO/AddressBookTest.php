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

use OCP\Constants as CoreConstants;

use OCA\CAFEVDB\Controller\DTO;

/** Test consistency of the enum with constants from ConfigConstants */
#[Attributes\CoversClass(DTO\AddressBook::class)]
class AddressBookTest extends TestCase
{
  private DTO\AddressBook $dto;
  /**
   * {@inheritdoc}
   *
   * @return void
   */
  public function setup(): void
  {
    $this->dto = new DTO\AddressBook(
      displayName: 'Display Name',
      key: 'key',
      uri: 'http://whatever',
      isShared: false,
      isSystemAddressBook: false,
      permissions: CoreConstants::PERMISSION_READ,
    );
  }

  /** @return void */
  public function testConstructor(): void
  {
    $this->expectNotToPerformAssertions();
  }

  /** @return void */
  public function testFromArray(): void
  {
    $dto = DTO\AddressBook::fromArray(
      $this->dto->jsonSerialize(),
    );
    $this->assertEqualsCanonicalizing($this->dto->jsonSerialize(), $dto->jsonSerialize());
  }

  /** @return void */
  public function testJsonSerialization(): void
  {
    $this->assertNotEmpty(json_encode($this->dto, JSON_THROW_ON_ERROR));
  }
}
