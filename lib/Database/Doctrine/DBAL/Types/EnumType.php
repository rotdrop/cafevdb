<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2021 Claus-Justus Heine <himself@claus-justus-heine.de>
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

use OCA\CAFEVDB\Wrapped\Doctrine\DBAL\Platforms\AbstractPlatform;
use OCA\CAFEVDB\Wrapped\Acelaya\Doctrine\Type\PhpEnumType;

use function call_user_func;
use function implode;
use function sprintf;

/*abstract*/ class EnumType extends PhpEnumType
{
  public function getSQLDeclaration(array $fieldDeclaration, AbstractPlatform $platform):string
  {
    $values = call_user_func([$this->enumClass, 'toArray']);
    $values = array_map(function($val) { return "'".$val."'"; }, $values);
    return "enum(".implode(",", $values).")";
  }

  public function getValues()
  {
    return call_user_func([$this->enumClass, 'toArray']);
  }
}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
