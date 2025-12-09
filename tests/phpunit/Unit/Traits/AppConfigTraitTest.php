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

use OCP\IConfig;

use OCA\CAFEVDB\Controller\EnumPersonalSettingsKey;
use OCA\CAFEVDB\Settings\ConfigConstants;
use OCA\CAFEVDB\Settings\OldSettingsKeys;
use OCA\CAFEVDB\Tests\MockProvider;
use OCA\CAFEVDB\Traits\AppConfigTrait;
use OCA\CAFEVDB\Traits\UserPreferencesTrait;

/**
 * Test the AppConfigTrait and the UserPreferencesTrait.
 */
#[Attributes\CoversTrait(AppConfigTrait::class)]
#[Attributes\CoversTrait(UserPreferencesTrait::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\AppInfo\Application::class)]
class AppConfigTraitTest extends TestCase
{
  private mixed $classWithMember;

  private mixed $classWithGetter;

  private IConfig $cloudConfig;

  public const APP_NAME = 'some_app';

  public const USER_ID = 'cloud.user';

  /** {@inheritdoc} */
  public function setup(): void
  {
    parent::setup();

    /** @var MockProvider $mockProvider */
    $mockProvider = MockProvider::create($this);
    $this->cloudConfig = $mockProvider->getCloudConfig();

    $this->classWithMember = new class($this->cloudConfig) {
      use AppConfigTrait;
      use UserPreferencesTrait;

      protected string $appName;

      protected string $userId = AppConfigTraitTest::USER_ID;

      /** {@inheritdoc} */
      public function __construct(protected IConfig $cloudConfig)
      {
        $this->appName = AppConfigTraitTest::APP_NAME;
      }

      /** {@inheritdoc} */
      public function getAppName(): string
      {
        return $this->appName;
      }
    };

    $this->classWithGetter = new class($this->cloudConfig) {
      use AppConfigTrait;
      use UserPreferencesTrait;

      protected string $appName;

      /**  {@inheritdoc} */
      public function getUserId(): string
      {
        return AppConfigTraitTest::USER_ID;
      }

      /**  {@inheritdoc} */
      public function getCloudConfig(): IConfig
      {
        return $this->myCloudConfig;
      }

      /** {@inheritdoc} */
      public function __construct(protected IConfig $myCloudConfig)
      {
        $this->appName = AppConfigTraitTest::APP_NAME;
      }

      /** {@inheritdoc} */
      public function getAppName(): string
      {
        return $this->appName;
      }
    };
  }

  /**
   * @param mixed $class
   *
   * @return void
   */
  private function appConfig(mixed $class): void
  {
    $key = ConfigConstants::EMAIL_USER;
    $value = 'someValue';

    $class->setAppValue(OldSettingsKeys::APP_KEYS[$key], $value);
    $this->assertEquals($value, $class->getAppValue(OldSettingsKeys::APP_KEYS[$key]));
    $this->assertEquals($value, $class->getAppValue($key));

    $class->setAppValue($key, $value);

    // compat should have been deleted.
    $default = 'default';
    $this->assertEquals(
      $default,
      $this->cloudConfig->getAppValue(
        self::APP_NAME,
        OldSettingsKeys::APP_KEYS[$key],
        $default,
      ),
    );

    $class->setAppValue('BLAHBLAH', 'blubber');
    $this->assertEquals(
      'blubber',
      $class->getAppValue('BLAHBLAH'),
    );

    $class->deleteAppValue('BLAHBLAH');
    $this->assertEquals(
      'default',
      $class->getAppValue('BLAHBLAH', 'default'),
    );
  }


  /** @return void */
  public function testAppConfigWithMember()
  {
    $this->appConfig($this->classWithMember);
  }

  /** @return void */
  public function testAppConfigWithGetter()
  {
    $this->appConfig($this->classWithGetter);
  }

  /**
   * @param mixed $class
   *
   * @return void
   */
  private function userPreferences(mixed $class): void
  {
    $key = 'BLAHBLAH';
    $value = 'blubber';
    $default = 'default';

    $class->setUserValue($key, $value);
    $this->assertEquals(
      $value,
      $class->getUserValue($key),
    );

    $key = EnumPersonalSettingsKey::DEBUG_MODE;
    $value = 17;
    $class->setUserValue(
      OldSettingsKeys::USER_KEYS[$key->value],
      $value,
    );
    $this->assertEquals(
      $value,
      $class->getUserValue($key),
    );

    $class->setUserValue($key, $value);
    $this->assertEquals(
      $value,
      $class->getUserValue($key),
    );

    $class->deleteUserValue($key);
    $this->assertEquals(
      $default,
      $class->getUserValue($key, $default),
    );

    // compat should have been deleted.
    $this->assertEquals(
      $default,
      $this->cloudConfig->getUserValue(
        self::USER_ID,
        self::APP_NAME,
        OldSettingsKeys::USER_KEYS[$key->value],
        $default,
      ),
    );

  }

  /** @return void */
  public function testUserPreferencesWithMember()
  {
    $this->userPreferences($this->classWithMember);
  }

  /** @return void */
  public function testUserPreferencesWithGetter()
  {
    $this->userPreferences($this->classWithGetter);
  }
}
