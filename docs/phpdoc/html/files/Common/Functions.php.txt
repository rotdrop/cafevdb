<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2011-2016, 2020, 2021, 2022, 2023 Claus-Justus Heine
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

namespace OCA\CAFEVDB\Common\Functions;

use OCA\CAFEVDB\Common\VarDumper;

/**
 * Symphony var-dumper which does not suffer from inifinite recursions.
 *
 * @param mixed $variable
 *
 * @param mixed $stream
 *
 * @return mixed
 */
function dump(mixed $variable, mixed $stream = true)
{
  static $dumper = null;
  if (empty($dumper)) {
    $dumper = new VarDumper;
  }
  return $dumper->dump($variable, $stream);
}

/**
 * Version of strcmp which sorts empty strings last.
 *
 * @param null|string $a
 *
 * @param null|string $b
 *
 * @return int
 */
function strCmpEmptyLast(?string $a, ?string $b)
{
  if ($a == $b) {
    return 0;
  }
  if (empty($a)) {
    return 1;
  }
  if (empty($b)) {
    return -1;
  }
  return strcmp($a, $b);
}
