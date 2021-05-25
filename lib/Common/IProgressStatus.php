<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2021 Claus-Justus Heine <himself@claus-justus-heine.de>
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

namespace OCA\CAFEVDB\Common;

/**
 * Simplistic progress-status interface.
 */
interface IProgressStatus
{
  /**
   * Obtain a unique but opaque identifier (e.g. the id column in a
   * database up to a serialized representation).
   */
  public function getId();

  /**
   * Update to the given values, where null arguments are simply
   * ignored. This function has to write the given values through to
   * the underlying storage.
   */
  public function update(int $current, ?int $target = null, ?array $data = null);

  /**
   * Synchronize with the underlying storage, i.e. read the data into
   * this instance.
   */
  public function sync();

  /**
   * Return the cached value of the current state.
   */
  public function getCurrent():int;

  /**
   * Return the cached value of the target state.
   */
  public function getTarget():int;

  /**
   * Return the cached value of the last modification time.
   */
  public function getLastModified():\DateTimeinterface;

  /**
   * Return the cached value of the custom data.
   */
  public function getData():?array;
}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
