<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2021 Claus-Justus Heine <himself@claus-justus-heine.de>
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU GENERAL PUBLIC LICENSE
 * License as published by the Free Software Foundation; either
 * version 3 of the License, or any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU AFFERO GENERAL PUBLIC LICENSE for more details.
 *
 * You should have received a copy of the GNU Lesser General Public
 * License along with this library.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace OCA\CAFEVDB\Database\Doctrine\DBAL\Types;

use MyCLabs\Enum\Enum as EnumType;

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
class EnumMemberStatus extends EnumType
{
  public const REGULAR = 'regular';
  public const PASSIVE = 'passive';
  public const SOLOIST = 'soloist';
  public const CONDUCTOR = 'conductor';
  public const TEMPORARY = 'temporary';
}
