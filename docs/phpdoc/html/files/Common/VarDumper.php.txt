<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2011-2016, 2020, 2021, 2022 Claus-Justus Heine
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

namespace OCA\CAFEVDB\Common;

use Symfony\Component\VarDumper\Cloner\VarCloner;
use Symfony\Component\VarDumper\Dumper\CliDumper;

/** Recursion-safe variable dumper. */
class VarDumper
{
  /** @var VarCloner */
  private $varCloner;

  /** @var CliDumper */
  private $varDumper;

  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct()
  {
    $this->varCloner = new VarCloner;
    $this->varDumper = new CliDumper;
  }
  // phpcs:enable

  /**
   * Default dump to a string without colors.
   *
   * @param mixed $variable
   *
   * @param mixed $stream
   *
   * @return mixed
   */
  public function dump(mixed $variable, mixed $stream = true)
  {
    return $this->varDumper->dump($this->varCloner->cloneVar($variable), $stream);
  }
}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
