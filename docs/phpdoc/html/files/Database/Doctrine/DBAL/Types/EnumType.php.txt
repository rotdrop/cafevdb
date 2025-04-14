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

use OCA\CAFEVDB\Wrapped\Doctrine\DBAL\Platforms\AbstractPlatform;
use OCA\CAFEVDB\Wrapped\Acelaya\Doctrine\Type\PhpEnumType;

use function call_user_func;
use function implode;
use function sprintf;

/** Base enum-type class. */
class EnumType extends PhpEnumType
{
  /** {@inheritdoc} */
  public function getSQLDeclaration(array $fieldDeclaration, AbstractPlatform $platform):string
  {
    $class = $this->enumClass;
    return "enum('" . implode("','", $class::toArray()) . "')";
  }

  /**
   * @return array
   */
  public function toArray():array
  {
    $class = $this->enumClass;
    return $class::toArray();
  }
}
