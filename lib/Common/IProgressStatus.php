<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2021, 2022 Claus-Justus Heine
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

use DateTimeInterface;

/**
 * Simplistic progress-status interface.
 */
interface IProgressStatus
{
  /**
   * Delete the underlying storage object. Afterwards any operation
   * except $this->bind() will exhibit undefined behaviour.
   *
   * @return void
   */
  public function delete();

  /**
   * Bind to the given storage object. Create a new object if $id is null.
   *
   * @param mixed $id Opaque data identifying the progress status object.
   *
   * @return void
   */
  public function bind(mixed $id);

  /**
   * Obtain a unique but opaque identifier (e.g. the id column in a
   * database up to a serialized representation).
   *
   * @return mixed
   */
  public function getId();

  /**
   * Update to the given values, where null arguments are simply
   * ignored. This function has to write the given values through to
   * the underlying storage.
   *
   * @param int $current
   *
   * @param null|int $target If null the target remains unchanged.
   *
   * @param null|array $data "user"-data stored in the progress-status
   * object. Can be retrieved later via getData(). If null then the
   * currently stored data remains unchanged.
   *
   * @return bool true on success, false if the underlying storage has
   * been deleted.
   */
  public function update(int $current, ?int $target = null, ?array $data = null):bool;

  /**
   * Add the given amount to the progress-status counter.
   *
   * @param int $delta Amount to add, defaults to 1. Negative values
   * are allowed.
   *
   * @return bool|int Current value of the counter or false if the
   * underlying storage has been deleted.
   */
  public function increment(int $delta = 1);

  /**
   * Synchronize with the underlying storage, i.e. read the data into
   * this instance.
   *
   * @return void
   */
  public function sync();

  /**
   * Return the cached value of the current state.
   *
   * @return int
   */
  public function getCurrent():int;

  /**
   * Set the current value and sync.
   *
   * @param int $current
   *
   * @return void
   */
  public function setCurrent(int $current);

  /**
   * Return the cached value of the target state.
   *
   * @return int
   */
  public function getTarget():int;

  /**
   * Set the target value and sync.
   *
   * @param int $target
   *
   * @return void
   */
  public function setTarget(int $target);

  /**
   * Return the cached value of the custom data, i.e. the data previously passed to update().
   *
   * @return null|array
   */
  public function getData():?array;

  /**
   * Set the data value and sync.
   *
   * @param array $data
   *
   * @return void
   */
  public function setData(array $data);

  /**
   * Return the cached value of the last modification time.
   *
   * @return DateTimeInterface
   */
  public function getLastModified():DateTimeinterface;
}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
