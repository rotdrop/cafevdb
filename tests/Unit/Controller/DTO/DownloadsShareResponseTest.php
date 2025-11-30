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

use DateTime;

use PHPUnit\Framework\Attributes;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

use OCA\CAFEVDB\Controller\DTO;

/** Test consistency of the enum with constants from ConfigConstants */
#[Attributes\CoversClass(DTO\DownloadsShareResponse::class)]
class DownloadsShareResponseTest extends TestCase
{
  private DTO\DownloadsShareResponse $dto;
  /**
   * {@inheritdoc}
   *
   * @return void
   */
  public function setup(): void
  {
    $this->dto = new DTO\DownloadsShareResponse(
      messages: ['MESSAGE'],
      share: 'SHARE',
      folder: 'FOLDER',
      expires: DateTime::createFromFormat('Y-m-d h:i:s', '2025-11-04 01:02:03'),
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
    $this->expectNotToPerformAssertions();
    $dto = DTO\DownloadsShareResponse::fromArray([
      'messages' => ['MESSAGE'],
      'expires' => DateTime::createFromFormat('Y-m-d h:i:s', '2025-11-04 01:02:03'),
      'share' => 'SHARE',
      'folder' => 'FOLDER',
    ]);
  }

  const JSON_DATA = '{
    "expires": "2025-11-04T01:02:03.000000Z",
    "messages": [
        "MESSAGE"
    ],
    "share": "SHARE",
    "folder": "FOLDER"
}';

  /** @return void */
  public function testJsonSerialization(): void
  {
    $this->assertEquals(self::JSON_DATA, json_encode($this->dto, JSON_PRETTY_PRINT));
  }
}
