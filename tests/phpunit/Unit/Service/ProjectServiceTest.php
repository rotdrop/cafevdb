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

namespace OCA\CAFEVDB\Tests\Unit\Service;

use PHPUnit\Framework\Attributes;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

use OCA\CAFEVDB\Tests\MockProvider;
use OCA\CAFEVDB\Service\ProjectService;

/** Test aspects of the config-service .*/
#[Attributes\CoversClass(ProjectService::class)]
class ProjectServiceTest extends TestCase
{
  /** @return void */
  public function setup(): void
  {
  }

  private const TEXT_WITH_PROJECT_NAMES = 'Anmeldezeitraum für das Projekt "Test2026". Interessierte können sich während dieser Zeit über den öffentlichen Link https://dev3.home.claus-justus-heine.de/apps/cafevdbmembers/registration/Test2026 online bewerben.';

  private const NEW_NAME = 'HutzliPutzli2099';

  /** @return void */
  public function testReplaceProjectNames(): void
  {
    $text = ProjectService::replaceProjectNames(self::NEW_NAME, self::TEXT_WITH_PROJECT_NAMES);
    $this->assertStringNotContainsString('Test2026', $text);
    $this->assertStringContainsString(self::NEW_NAME, $text);
  }
}
