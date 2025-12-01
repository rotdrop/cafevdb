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

namespace OCA\CAFEVDB\Tests\Unit\Traits;

use PHPUnit\Framework\Attributes;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

use PhpOffice\PhpSpreadsheet;

use OCA\CAFEVDB\Service\DTO\FontFileNames;
use OCA\CAFEVDB\Service\FontService;
use OCA\CAFEVDB\Settings\Admin;
use OCA\CAFEVDB\Settings\AdminInitialState;
use OCA\CAFEVDB\Tests\MockProvider;

/**
 * Test the initial state DTO of the admin settings.
 */
#[Attributes\CoversClass(AdminInitialState::class)]
#[Attributes\CoversClass(FontFileNames::class)]
class AdminInitialStateTest extends TestCase
{
  private AdminInitialState $configData;

  /** {@inheritdoc} */
  public function setup(): void
  {
    /** @var MockProvider $mockProvider */
    $mockProvider = \OCP\Server::get(MockProvider::class);

    // generate some fonts ...
    $fonts = [];
    foreach (array_slice(PhpSpreadsheet\Shared\Font::FONT_FILE_NAMES, 0, 2) as $family => $filesArray) {
      $fonts[$family] = array_merge([ 'family' => $family], $filesArray);
    }

    $this->configData = AdminInitialState::fromArray([
      Admin::AUTHORIZATION_GROUP_SUFFIXES_KEY => Admin::AUTHORIZATION_GROUP_SUFFIXES,
      Admin::CLOUD_USER_BACKEND => 'something',
      Admin::HAVE_CLOUD_USER_BACKEND_CONFIG_KEY => true,
      Admin::IS_ADMIN => true,
      Admin::IS_SUB_ADMIN => true,
      Admin::OFFICE_FONTS => $fonts,
      Admin::PERSONAL_APP_SETTINGS_LINK => 'string',
      Admin::SHARED_FOLDER_KEY => 'something',
      Admin::USER_AND_GROUP_BACKENDS => ['one', 'two'],
      FontService::DEFAULT_OFFICE_FONT_CONFIG => 'something',
      FontService::OFFICE_FONTS_FOLDER_CONFIG => 'something',
    ]);
  }

  /** @return void */
  public function testConstructor(): void
  {
    $this->expectNotToPerformAssertions();
  }

  private const JSON_DATA = '{
    "officeFonts": {
        "Arial": {
            "family": "Arial",
            "x": "arial.ttf",
            "xb": "arialbd.ttf",
            "xi": "ariali.ttf",
            "xbi": "arialbi.ttf"
        },
        "Calibri": {
            "family": "Calibri",
            "x": "calibri.ttf",
            "xb": "calibrib.ttf",
            "xi": "calibrii.ttf",
            "xbi": "calibriz.ttf"
        }
    },
    "authorizationGroupSuffixes": {
        "127": "",
        "1": "-frontend",
        "2": "-addressbook",
        "4": "-filesystem",
        "8": "-calendar",
        "16": "-finance",
        "32": "-management",
        "64": "-email"
    },
    "cloudUserBackend": "something",
    "haveCloudUserBackendConfig": true,
    "isAdmin": true,
    "isSubAdmin": true,
    "officeFontsFolder": "something",
    "personalAppSettingsLink": "string",
    "sharedFolder": "something",
    "userAndGroupBackends": [
        "one",
        "two"
    ]
}';

  /** @return void */
  public function testSerialization(): void
  {
    $this->assertEquals(self::JSON_DATA, json_encode($this->configData, JSON_PRETTY_PRINT));
  }
}
