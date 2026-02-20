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

use OCA\CAFEVDB\Controller\DTO;

/** Test consistency of the enum with constants from ConfigConstants */
#[Attributes\CoversClass(DTO\AdminSettingsResponse::class)]
#[Attributes\CoversClass(DTO\PermanentTransientMessages::class)]
class AdminSettingsResponseTest extends TestCase
{
  /**
   * {@inheritdoc}
   *
   * @return void
   */
  public function setup(): void
  {
  }

  /** @return void */
  public function testEmptyCTOR(): void
  {
    $this->expectNotToPerformAssertions();
    new DTO\AdminSettingsResponse();
  }

  /** @return void */
  public function testFullCTOR(): void
  {
    $this->expectNotToPerformAssertions();
    new DTO\AdminSettingsResponse(
      value: ['hello'],
      messages: new DTO\PermanentTransientMessages(
        permanent: ['a', 'b'],
        transient: ['c', 'd'],
      ),
      status: 'status',
      feedback: 'feedback',
    );
  }

  /** @return void */
  public function testArrayCTOR(): void
  {
    $this->expectNotToPerformAssertions();
    DTO\AdminSettingsResponse::fromArray([
      'value' => ['hello'],
      'messages' => [
        'permanent' => ['a', 'b'],
        'transient' => ['c', 'd'],
      ],
      'status' => 'status',
      'feedback' => 'feedback',
    ]);
  }

  /** @return void */
  public function testPartialCTOR(): void
  {
    $this->expectNotToPerformAssertions();
    new DTO\AdminSettingsResponse(
      value: ['hello'],
      messages: new DTO\PermanentTransientMessages(
        permanent: 'Hello World!',
      ),
    );
  }
}
