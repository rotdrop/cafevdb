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

/**
 * GnuCash slot types.
 */
enum EnumGnuCashSlotType: int
{
  use \OCA\CAFEVDB\Toolkit\Traits\BackedEnumTrait;

  case INT64 = 1;
  case DOUBLE = 2;
  case NUMERIC = 3;
  case STRING = 4;
  case GUID = 5;
  case TIME64 = 6;
  case PLACEHOLDER_DONT_USE = 7;
  case GLIST = 8;
  case FRAME = 9;
  case GDATE = 10;
}
