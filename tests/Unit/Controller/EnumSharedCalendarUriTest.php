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

use PHPUnit\Framework\Attributes;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

use OCA\CAFEVDB\Controller\EnumSharedCalendarUri;
use OCA\CAFEVDB\Settings\ConfigConstants;
use OCA\CAFEVDB\Toolkit\Traits\BackedEnumTrait;

/** Test consistency of the enum with constants from ConfigConstants */
#[Attributes\CoversClass(EnumSharedCalendarUri::class)]
#[Attributes\CoversTrait(BackedEnumTrait::class)]
class EnumSharedCalendarUriTest extends TestCase
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
  public function testNoneMissing(): void
  {
    foreach (array_keys(ConfigConstants::CALENDARS) as $uri) {
      $enum = EnumSharedCalendarUri::get($uri);
      $this->assertEquals($uri, $enum->value);
    }
  }

  /** @return void */
  public function testNoExtranous(): void
  {
    foreach (EnumSharedCalendarUri::array() as $name => $value) {
      $this->assertArrayHasKey($value, ConfigConstants::CALENDARS);
      $this->assertEquals($value, ConfigConstants::{$name . '_CALENDAR_URI'});
    }
  }
}
