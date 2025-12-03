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

use OCA\CAFEVDB\Controller\EnumPersonalSettingsKey;
use OCA\CAFEVDB\Settings\OldSettingsKeys;

/**
 * Fetch app config value with fallback to old keys.
 */
trait UserPreferencesTrait
{
  protected string $appName;

  /**
   * @param string|EnumPersonalSettingsKey $key Config key.
   *
   * @param mixed $default Default value.
   *
   * @param null|string $userId Use the current user if null.
   *
   * @return mixed
   */
  public function getUserValue(string|EnumPersonalSettingsKey $key, mixed $default = null, ?string $userId = null)
  {
    if ($userId === null) {
      $userId = method_exists($this, 'getUserId') ? $this->getUserId() : $this->userId;
    }
    if ($key instanceof EnumPersonalSettingsKey) {
      $key = $key->value;
    }
    $cloudConfig = method_exists($this, 'getCloudConfig') ? $this->getCloudConfig() : $this->cloudConfig;
    if (!empty(OldSettingsKeys::USER_KEYS[$key]) && OldSettingsKeys::USER_KEYS[$key] != $key) {
      $default = $cloudConfig->getUserValue($userId, $this->appName, OldSettingsKeys::USER_KEYS[$key], $default);
    }
    return $cloudConfig->getUserValue($userId, $this->appName, $key, $default);
  }

  /**
   * @param string|EnumPersonalSettingsKey $key Config key.
   *
   * @param mixed $value Value to set.
   *
   * @param null|string $userId Use the current user if null.
   *
   * @return void
   */
  public function setUserValue(string|EnumPersonalSettingsKey $key, mixed $value, ?string $userId = null): void
  {
    if ($userId === null) {
      $userId = method_exists($this, 'getUserId') ? $this->getUserId() : $this->userId;
    }
    if ($key instanceof EnumPersonalSettingsKey) {
      $key = $key->value;
    }
    $cloudConfig = method_exists($this, 'getCloudConfig') ? $this->getCloudConfig() : $this->cloudConfig;
    $cloudConfig->setUserValue($userId, $this->appName, $key, $value);
    if (!empty(OldSettingsKeys::USER_KEYS[$key]) && OldSettingsKeys::USER_KEYS[$key] != $key) {
      $cloudConfig->deleteUserValue($userId, $this->appName, OldSettingsKeys::USER_KEYS[$key]);
    }
  }

  /**
   * @param string $key Config key.
   *
   * @param null|string $userId Use the current user if null.
   *
   * @return void
   */
  public function deleteUserValue(string $key, ?string $userId = null): void
  {
    if ($userId === null) {
      $userId = method_exists($this, 'getUserId') ? $this->getUserId() : $this->userId;
    }
    $cloudConfig = method_exists($this, 'getCloudConfig') ? $this->getCloudConfig() : $this->cloudConfig;
    $cloudConfig->deleteUserValue($userId, $this->appName, $key);
  }
}
