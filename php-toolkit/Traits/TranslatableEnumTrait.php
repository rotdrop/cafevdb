<?php
/**
 * A collection of reusable traits classes for Nextcloud apps.
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

namespace OCA\RotDrop\Toolkit\Traits;

use InvalidArgumentException;
use Throwable;
use ValueError;

use OCP\IL10N;

/**
 * Used primarily for L10N injection, a dev script can check for
 * self::getL10NValues() vie reflection and inject the enum values into the
 * translation templates.
 */

/**
 * Some convenience stuff for PHP enums.
 */
trait TranslatableEnumTrait
{
  use BackedEnumTrait;

  public const L10N_TAG = Constants::ENUM_VALUE_L10N_TAG . ': ';

  /**
   * @param IL10N $l
   *
   * @return array translated value array.
   */
  public static function getL10NValues(IL10N $l): array
  {
    $values = self::values();
    return array_combine(
      $values,
      array_map(
        function(string $value) use ($l) {
          $prefix = static::L10N_TAG;
          $l10nValue = $l->t($prefix . $value);
          return ($l10nValue === $value || $l10nValue === $prefix . $value) ? $l->t($value) : $l10nValue;
        },
        $values,
      ),
    );
  }
}
