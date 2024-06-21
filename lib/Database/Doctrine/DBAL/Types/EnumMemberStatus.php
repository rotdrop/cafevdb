<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2011-2022, 2024 Claus-Justus Heine
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

use OCP\IL10N;

/**
 * Member status enum for musicians.
 *
 * @method static EnumMemberStatus REGULAR()
 * @method static EnumMemberStatus PASSIVE()
 * @method static EnumMemberStatus SOLOIST()
 * @method static EnumMemberStatus CONDUCTOR()
 * @method static EnumMemberStatus TEMPORARY()
 *
 * @todo This should rather be specified per project.
 */
class EnumMemberStatus extends AbstractEnumType
{
  public const REGULAR = 'regular';
  public const PASSIVE = 'passive';
  public const SOLOIST = 'soloist';
  public const CONDUCTOR = 'conductor';
  public const TEMPORARY = 'temporary';

  /** {@inheritdoc} */
  public static function getL10NValues(IL10N $l): array
  {
    $values = parent::getL10NValues($l);
    $values[self::TEMPORARY] = $l->t('temporary help');
    return $values;
  }

  /**
   * Just here in order to inject the enum values into the l10n framework.
   *
   * @return void
   */
  protected static function translationHack():void
  {
    self::t(self::REGULAR);
    self::t(self::PASSIVE);
    self::t(self::SOLOIST);
    self::t(self::CONDUCTOR);
    self::t(self::TEMPORARY);
  }
}
