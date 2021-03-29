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
 * Enum for "extra-fields" data-type.
 *
 * @method static EnumExtraFieldDataType TEXT()
 * @method static EnumExtraFieldDataType HTML()
 * @method static EnumExtraFieldDataType BOOLEAN()
 * @method static EnumExtraFieldDataType INTEGER()
 * @method static EnumExtraFieldDataType FLOAT()
 * @method static EnumExtraFieldDataType DATE()
 * @method static EnumExtraFieldDataType DATETIME()
 * @method static EnumExtraFieldDataType SERVICE_FEE()
 * @method static EnumExtraFieldDataType DEPOSIT()
 */
class EnumExtraFieldDataType extends EnumType
{
  private const TEXT = 'text';
  private const HTML = 'html';
  private const BOOLEAN = 'boolean';
  private const INTEGER = 'integer';
  private const FLOAT = 'float';
  private const DATE = 'date';
  private const DATETIME = 'datetime';
  private const SERVICE_FEE = 'service-fee';
  private const DEPOSIT = 'deposit';
};
