<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2024 Claus-Justus Heine
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

/**
 * GnuCash slot types.
 *
 * @method static EnumGnuCashSlotType INT64()
 * @method static EnumGnuCashSlotType DOUBLE()
 * @method static EnumGnuCashSlotType NUMERIC()
 * @method static EnumGnuCashSlotType STRING()
 * @method static EnumGnuCashSlotType GUID()
 * @method static EnumGnuCashSlotType TIME64()
 * @method static EnumGnuCashSlotType PLACEHOLDER_DONT_USE()
 * @method static EnumGnuCashSlotType GLIST()
 * @method static EnumGnuCashSlotType FRAME()
 * @method static EnumGnuCashSlotType GDATE()
 */
class EnumGnuCashSlotType extends EnumType
{
  public const INT64 = 1;
  public const DOUBLE = 2;
  public const NUMERIC = 3;
  public const STRING = 4;
  public const GUID = 5;
  public const TIME64 = 6;
  public const PLACEHOLDER_DONT_USE = 7;
  public const GLIST = 8;
  public const FRAME = 9;
  public const GDATE = 10;
}
