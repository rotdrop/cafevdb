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
use OCA\CAFEVDB\Controller\EnumPersonalSettingsKey;

/** Test consistency of the PME intial statue DTO. */
#[Attributes\CoversClass(DTO\PMEInitialState::class)]
class PMEInitialStateTest extends TestCase
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
  public function testFromArray(): void
  {
    $this->expectNotToPerformAssertions();

    // test construction from an array in order to check consistency of the
    // used keys (constants). The actual values do not matter.
    DTO\PMEInitialState::fromArray([
      EnumPersonalSettingsKey::DIRECT_CHANGE->value => true,
      EnumPersonalSettingsKey::SHOW_DISABLED->value => true,
      EnumPersonalSettingsKey::DESELECT_INVISIBLE_MISC_RECS->value => true,
      EnumPersonalSettingsKey::INITIAL_FILTER_VISIBILITY->value => true,
      EnumPersonalSettingsKey::PAGE_ROWS_DEFAULT->value => 20,
      'selectChosen' => true,
      'filterSelectPlaceholder' => '',
      'filterSelectNoResult' => '',
      'filterSelectChosenTitle' => '',
      'inputSelectPlaceholder' => '',
      'inputSelectNoResult' => '',
      'inputSelectChosenTitle' => '',
    ]);
  }
}
