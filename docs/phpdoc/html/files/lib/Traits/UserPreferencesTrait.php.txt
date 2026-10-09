<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2020-2026 Claus-Justus Heine
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

use UnexpectedValueException;

use OCP\Config\Exceptions\UnknownKeyException;
use OCP\Config\IUserConfig;
use OCP\Config\ValueType;
use OCP\IConfig;

use OCA\CAFEVDB\Controller\EnumPersonalSettingsKey;
use OCA\CAFEVDB\Settings\OldSettingsKeys;

/**
 * Fetch app config value with fallback to old keys.
 */
trait UserPreferencesTrait
{
  protected string $appName;

  protected IUserConfig $cloudUserConfig;

  /**
   * @param ?string $userId
   *
   * @return string
   */
  private function __userPreferencesTraitGetUserId(?string $userId): string
  {
    return $userId ?? method_exists($this, 'getUserId') ? $this->getUserId() : $this->userId;
  }

  /**
   * @param string $userId Use the current user if null.
   *
   * @param string $key Config key.
   *
   * @param mixed $default Default value.
   *
   * @return mixed
   */
  private function __doGetUserValue(string $userId, string $key, mixed $default = null): mixed
  {
    $this->cloudUserConfig = $this->cloudUserConfig ?? $this->getCloudUserConfig();
    try {
        $valueType = $this->cloudUserConfig->getValueType($userId, $this->appName, $key);
    } catch (UnknownKeyException) {
        $valueType = ValueType::MIXED;
    }
    switch ($valueType) {
      case ValueType::MIXED:
      case ValueType::STRING:
        return $this->cloudUserConfig->getValueString($userId, $this->appName, $key, $default ?? '');
      case ValueType::BOOL:
        return $this->cloudUserConfig->getValueBool($userId, $this->appName, $key, $default ?? false);
      case ValueType::FLOAT:
        return $this->cloudUserConfig->getValueFloat($userId, $this->appName, $key, $default ?? 0.0);
      case ValueType::INT:
        return $this->cloudUserConfig->getValueInt($userId, $this->appName, $key, $default ?? 0);
      case ValueType::ARRAY:
        return $this->cloudUserConfig->getValueArray($userId, $this->appName, $key, $default ?? []);
    }
    throw new UnexpectedValueException('Unexpected value type for key "' . $key . '": "' . $valueType->getDefinition());
  }

  /**
   * @param string|EnumPersonalSettingsKey $key Config key.
   *
   * @param mixed $default Default value.
   *
   * @param ?string $userId Use the current user if null.
   *
   * @return mixed
   */
  public function getUserValue(string|EnumPersonalSettingsKey $key, mixed $default = null, ?string $userId = null): mixed
  {
    if ($key instanceof EnumPersonalSettingsKey) {
      $key = $key->value;
    }
    $userId = $userId ??  $this->__userPreferencesTraitGetUserId($userId);
    if (!empty(OldSettingsKeys::USER_KEYS[$key]) && OldSettingsKeys::USER_KEYS[$key] != $key) {
      $default = $this->__doGetUserValue($userId, OldSettingsKeys::USER_KEYS[$key], $default);
    }
    return $this->__doGetUserValue($userId, $key, $default);
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
    if ($key instanceof EnumPersonalSettingsKey) {
      $key = $key->value;
    }
    $userId = $userId ?? $this->__userPreferencesTraitGetUserId($userId);
    $this->cloudUserConfig = $this->cloudUserConfig ?? $this->getCloudUserConfig();
    try {
      $valueType = $this->cloudUserConfig->getValueType($userId, $this->appName, $key);
    } catch (UnknownKeyException) {
      $valueType = ValueType::MIXED;
    }
    switch ($valueType) {
      case ValueType::MIXED:
        // fallthrough
      case ValueType::STRING:
        $this->cloudUserConfig->setValueString($userId, $this->appName, $key, (string)$value);
        break;
      case ValueType::BOOL:
        $this->cloudUserConfig->setValueBool($userId, $this->appName, $key, (bool)$value);
        break;
      case ValueType::FLOAT:
        $this->cloudUserConfig->getValueFloat($userId, $this->appName, $key, (float)$value);
        break;
      case ValueType::INT:
        $this->cloudUserConfig->getValueInt($userId, $this->appName, $key, (int)$value);
        break;
      case ValueType::ARRAY:
        $this->cloudUserConfig->getValueArray($userId, $this->appName, $key, (array)$value);
        break;
    }
    if (!empty(OldSettingsKeys::USER_KEYS[$key]) && OldSettingsKeys::USER_KEYS[$key] != $key) {
      $this->cloudUserConfig->deleteUserConfig($userId, $this->appName, OldSettingsKeys::USER_KEYS[$key]);
    }
  }

  /**
   * @param string|EnumPersonalSettingsKey $key Config key.
   *
   * @param null|string $userId Use the current user if null.
   *
   * @return void
   */
  public function deleteUserValue(string|EnumPersonalSettingsKey $key, ?string $userId = null): void
  {
    if ($key instanceof EnumPersonalSettingsKey) {
      $key = $key->value;
    }
    $userId = $userId ?? $this->__userPreferencesTraitGetUserId($userId);
    $this->cloudUserConfig = $this->cloudUserConfig ?? $this->getCloudUserConfig();
    $this->cloudUserConfig->deleteUserConfig($userId, $this->appName, $key);
  }
}
