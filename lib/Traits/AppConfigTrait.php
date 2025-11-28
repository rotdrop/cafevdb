<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2020, 2021, 2022, 2023, 2024, 2025 Claus-Justus Heine
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

namespace OCA\CAFEVDB\Traits;

use OCA\CAFEVDB\Settings\OldSettingsKeys;

/**
 * Fetch app config value with fallback to old keys.
 */
trait AppConfigTrait
{
  protected string $appName;

  /**
   * A short-cut, redirecting to the stock functions for the app.
   *
   * @param string $key Config key.
   *
   * @param mixed $default Default value.
   *
   * @return mixed
   */
  public function getAppValue(string $key, mixed $default = null): mixed
  {
    $cloudConfig = method_exists($this, 'getCloudConfig') ? $this->getCloudConfig() : $this->cloudConfig;
    if (!empty(OldSettingsKeys::APP_KEYS[$key]) && OldSettingsKeys::APP_KEYS[$key] != $key) {
      $default = $cloudConfig->getAppValue($this->appName, OldSettingsKeys::APP_KEYS[$key], $default);
    }
    return $cloudConfig->getAppValue($this->appName, $key, $default);
  }

  /**
   * A short-cut, redirecting to the stock functions for the app.
   *
   * @param string $key Config key.
   *
   * @param mixed $value Value to set.
   *
   * @return mixed
   */
  public function setAppValue(string $key, mixed $value)
  {
    $cloudConfig = method_exists($this, 'getCloudConfig') ? $this->getCloudConfig() : $this->cloudConfig;
    $cloudConfig->setAppValue($this->appName, $key, $value);
    if (!empty(OldSettingsKeys::APP_KEYS[$key]) && OldSettingsKeys::APP_KEYS[$key] != $key) {
      $cloudConfig->deleteAppValue($this->appName, OldSettingsKeys::APP_KEYS[$key]);
    }
  }

  /**
   * A short-cut, redirecting to the stock functions for the app.
   *
   * @param string $key Config key.
   *
   * @return void
   */
  public function deleteAppValue(string $key):void
  {
    $cloudConfig = method_exists($this, 'getCloudConfig') ? $this->getCloudConfig() : $this->cloudConfig;
    $cloudConfig->deleteAppValue($this->appName, $key);
    if (!empty(OldSettingsKeys::APP_KEYS[$key]) && OldSettingsKeys::APP_KEYS[$key] != $key) {
      $cloudConfig->deleteAppValue($this->appName, OldSettingsKeys::APP_KEYS[$key]);
    }
  }
}
