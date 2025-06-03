<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2024, 2025 Claus-Justus Heine
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

namespace OCA\CAFEVDB\Database\Doctrine\DBAL\Types;

use OCA\CAFEVDB\Wrapped\MyCLabs\Enum\Enum as EnumType;

use OCP\IL10N;

/**
 * Abstract base class for providing some common services.
 */
abstract class AbstractEnumType extends EnumType
{
  use \OCA\CAFEVDB\Toolkit\Traits\FakeTranslationTrait;

  public const L10N_TAG = 'ENUM';
  public const L10N_SEP = ': ';

  /** {@inheritdoc} */
  public static function toArray()
  {
    $class = static::class;
    if (!isset(static::$cache[$class])) {
      parent::toArray();
      $remove = [ self::L10N_SEP, static::L10N_TAG ];
      static::$cache[$class] = array_diff(static::$cache[$class], $remove);
    }
    return static::$cache[$class];
  }

  /**
   * @param IL10N $l
   *
   * @return array translated value array.
   */
  public static function getL10NValues(IL10N $l): array
  {
    $values = array_values(static::toArray());
    return array_combine(
      $values,
      array_map(
        function(string $value) use ($l) {
          $prefix = static::L10N_TAG . self::L10N_SEP;
          $l10nValue = $l->t($prefix . $value);
          return ($l10nValue === $value || $l10nValue === $prefix . $value) ? $l->t($value) : $l10nValue;
        },
        $values,
      ),
    );
  }
}
