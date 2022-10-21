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

/**
 * Simplistic do-undo interface in order to be stacked into a
 * do-undo-list.
 *
 * @SuppressWarnings(PHPMD.ShortMethodName)
 */
class GenericUndoable extends AbstractUndoable
{
  /** @var Callable */
  protected $doCallback;

  /** @var Callable|null */
  protected $undoCallback = null;

  /**
   * @var mixed
   *
   * The return value of the doCallback(), which is passed to the
   * undoCallback() if necessary.
   */
  protected $doResult;

  /**
   * @param callable $doCallback
   *
   * @param null|callable $undoCallback
   */
  public function __construct(callable $doCallback, ?callable $undoCallback = null)
  {
    $this->doCallback = $doCallback;
    $this->undoCallback = $undoCallback;
  }

  /** {@inheritdoc} */
  public function do():void
  {
    $this->doResult = call_user_func($this->doCallback);
  }

  /** {@inheritdoc} */
  public function undo():void
  {
    if (!empty($this->undoCallback)) {
      call_user_func($this->undoCallback, $this->doResult);
    }
    $this->reset();
  }

  /** {@inheritdoc} */
  public function reset():void
  {
    $this->doResult = null;
  }
}
