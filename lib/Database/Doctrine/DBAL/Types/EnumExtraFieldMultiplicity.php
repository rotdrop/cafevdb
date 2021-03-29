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
 * Enum for "extra-field" multiplicity.
 *
 * @method static EnumExtraFieldMultiplicity SIMPLE()
 * @method static EnumExtraFieldMultiplicity SINGLE()
 * @method static EnumExtraFieldMultiplicity MULTIPLE()
 * @method static EnumExtraFieldMultiplicity PARALLEL()
 * @method static EnumExtraFieldMultiplicity RECURRING()
 * @method static EnumExtraFieldMultiplicity GROUPOFPEOPLE()
 * @method static EnumExtraFieldMultiplicity GROUPSOFPEOPLE()
 *
 */
class EnumExtraFieldMultiplicity extends EnumType
{
  private const SIMPLE = 'simple';
  private const SINGLE = 'single';
  private const MULTIPLE = 'multiple';
  private const PARALLEL = 'parallel';
  private const RECURRING = 'recurring';
  private const GROUPOFPEOPLE = 'groupofpeople';
  private const GROUPSOFPEOPLE = 'groupsofpeople';
}
