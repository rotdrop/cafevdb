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
 * Simplistic do-undo interface in order to be stacked into a
 * do-undo-list.
 */
class GenericUndoable implements IUndoable
{
  /** @var Callable */
  protected $doCallback;

  /** @var Callable|null */
  protected $undoCallback = null;

  /** @var mixed */
  protected $done;

  public function __construct(Callable $do, ?Callable $undo = null)
  {
    $this->doCallback = $do;
    $this->undoCallback = $undo;
  }

  /** {@inheritdoc} */
  public function do() {
    $this->done = call_user_func($this->doCallback);
  }

  /** {@inheritdoc} */
  public function undo() {
    if (!empty($this->undoCallback)) {
      call_user_func($this->undoCallback, $this->done);
    }
    $this->done = null;
  }
}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
