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
 * Display context of ProjectParticipantFields. Some may only be relevant to
 * real participants, other fields may only be relevant for business contacts
 * / associated (legal) persons.
 *
 * @method static EnumParticipationStatus ASSOCIATES()
 * @method static EnumParticipationStatus PARTICIPANTS()
 * @method static EnumParticipationStatus UNRESTRICTED()
 *
 * @todo This should rather be specified per project.
 */
class EnumParticipationContext extends AbstractEnumType
{
  public const ASSOCIATES = 'associates';
  public const PARTICIPANTS = 'participants';
  public const UNRESTRICTED = 'unrestricted';

  public const L10N_TAG = parent::L10N_TAG . '_DISPLAY_CONTEXT';

  /**
   * Just here in order to inject the enum values into the l10n framework.
   *
   * @return void
   */
  protected static function translationHack():void
  {
    self::t(static::L10N_TAG . self::L10N_SEP . self::ASSOCIATES);
    self::t(static::L10N_TAG . self::L10N_SEP . self::PARTICIPANTS);
    self::t(static::L10N_TAG . self::L10N_SEP . self::UNRESTRICTED);
  }
}
