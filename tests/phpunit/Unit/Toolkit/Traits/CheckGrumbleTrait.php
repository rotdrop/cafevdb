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

namespace OCA\CAFEVDB\Tests\Unit\Toolkit\Traits;

use PHPUnit\Framework\Attributes;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

use OCP\AppFramework\Http;

/** Check that a Http\DataResponse is the result of ResponseTrait::grumble(). */
trait CheckGrumbleTrait
{
  private const GRUMBLE_KEYS = [
    'class',
    'file',
    'line',
    'message',
    'messages',
    'value',
  ];

  /** @return void */
  public function ensureGrumbleResponse(mixed $response, mixed $expectedValue = null): void
  {
    $this->assertInstanceOf(Http\DataResponse::class, $response);
    $this->assertEquals(Http::STATUS_BAD_REQUEST, $response->getStatus());
    $data = $response->getData();
    $this->assertIsArray($data);
    foreach (self::GRUMBLE_KEYS as $key) {
      $this->assertArrayHasKey($key, $data);
    }
    foreach ($data as $key => $value) {
      switch ($key) {
        case 'file':
        case 'class':
          $this->assertIsString($value);
          break;
        case 'line':
          $this->assertIsInt($value);
          break;
        case 'message':
          $this->assertIsString($value);
          break;
        case 'messages':
          $this->assertIsArray($value);
          $this->assertTrue(array_is_list($value));
          $this->assertEquals($data['message'], $value[0]);
          break;
        case 'value':
          if ($expectedValue !== null) {
            $this->assertEquals($expectedValue, $value);
          }
          break;
      }
    }
  }
}
