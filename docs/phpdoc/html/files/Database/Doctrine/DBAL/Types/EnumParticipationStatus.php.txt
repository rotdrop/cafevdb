<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2011-2022, 2024, 2025 Claus-Justus Heine
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

/**
 * Participation status enum for musicians.
 *
 * @method static EnumParticipationStatus ASSOCIATED()
 * @method static EnumParticipationStatus CONDUCTOR()
 * @method static EnumParticipationStatus PASSIVE()
 * @method static EnumParticipationStatus REGULAR()
 * @method static EnumParticipationStatus SOLOIST()
 * @method static EnumParticipationStatus TEMPORARY()
 *
 * @todo This should rather be specified per project.
 */
class EnumParticipationStatus extends AbstractEnumType
{
  public const ASSOCIATED = 'associated';
  public const CONDUCTOR = 'conductor';
  public const PASSIVE = 'passive';
  public const REGULAR = 'regular';
  public const SOLOIST = 'soloist';
  public const TEMPORARY = 'temporary';

  public const L10N_TAG = parent::L10N_TAG . '_PARTICIPATION_STATUS';

  /**
   * Just here in order to inject the enum values into the l10n framework.
   *
   * @return void
   */
  protected static function translationHack():void
  {
    self::t(static::L10N_TAG . self::L10N_SEP . self::ASSOCIATED);
    self::t(static::L10N_TAG . self::L10N_SEP . self::CONDUCTOR);
    self::t(static::L10N_TAG . self::L10N_SEP . self::PASSIVE);
    self::t(static::L10N_TAG . self::L10N_SEP . self::REGULAR);
    self::t(static::L10N_TAG . self::L10N_SEP . self::SOLOIST);
    self::t(static::L10N_TAG . self::L10N_SEP . self::TEMPORARY);
  }
}
