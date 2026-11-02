<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2026 Claus-Justus Heine
 * @license AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

namespace OCA\CAFEVDB\Tests\Unit\Settings;

use PHPUnit\Framework\Attributes;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;

use OCP\IL10N;

use OCA\CAFEVDB\Service\GeoCodingService;
use OCA\CAFEVDB\Settings\PersonalForm;

#[Attributes\CoversClass(PersonalForm::class)]
class PersonalFormTest extends TestCase
{
  private PersonalForm $form;

  protected function setUp():void
  {
    $this->form = (new ReflectionClass(PersonalForm::class))->newInstanceWithoutConstructor();
    $l10n = $this->createMock(IL10N::class);
    $l10n->method('t')->willReturnCallback(fn(string $text) => $text);
    (new ReflectionProperty(PersonalForm::class, 'l'))->setValue($this->form, $l10n);
  }

  /** @return void */
  public function testHolidayRegionsUseLocaleAndLocalizedNames():void
  {
    $geoCodingService = $this->createMock(GeoCodingService::class);
    $geoCodingService->expects($this->once())->method('getRegionNames')
      ->with('DE')->willReturn(['BW' => 'Baden-Wuerttemberg']);
    (new ReflectionProperty(PersonalForm::class, 'geoCodingService'))->setValue($this->form, $geoCodingService);

    $options = (new ReflectionMethod(PersonalForm::class, 'bankHolidayOptions'))->invoke($this->form, 'de_DE.UTF-8');
    $this->assertSame(['value' => 'DE-NATIONAL', 'name' => 'national holidays'], $options[0]);
    $this->assertContains(['value' => 'DE-BW', 'name' => 'Baden-Wuerttemberg'], $options);
    foreach ($options as $option) {
      $this->assertStringStartsWith('DE-', $option['value']);
    }
    $this->assertSame(array_values($options), $options);
  }

  /** @return void */
  public function testMissingRegionNamesRetainSelectableRegionCodes():void
  {
    $geoCodingService = $this->createMock(GeoCodingService::class);
    $geoCodingService->method('getRegionNames')->willReturn(null);
    (new ReflectionProperty(PersonalForm::class, 'geoCodingService'))->setValue($this->form, $geoCodingService);

    $options = (new ReflectionMethod(PersonalForm::class, 'bankHolidayOptions'))->invoke($this->form, 'de_DE');
    $this->assertContains(['value' => 'DE-BW', 'name' => 'DE-BW'], $options);
  }

  /** @return void */
  public function testLocaleWithoutCountryHasNoHolidayRegions():void
  {
    $options = (new ReflectionMethod(PersonalForm::class, 'bankHolidayOptions'))->invoke($this->form, 'de');
    $this->assertSame([], $options);
  }
}
