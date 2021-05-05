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

use OCP\ILogger;
use OCP\IL10N;

/**
 * Implement a stack of actions with undo-actions.
 */
class UndoableRunQueue
{
  use \OCA\CAFEVDB\Traits\LoggerTrait;

  /** @var array */
  protected $actionQueue = [];

  /** @var array|null */
  protected $undoStack = null;

  public function __construct(
    ILogger $logger
    , IL10N $l10n
  ) {
    $this->logger = $logger;
    $this->l = $l10n;
  }

  /**
   * Register the given action in the run-queue.
   */
  public function register(IUndoable $action)
  {
    $this->actionQueue[] = $action; // at end
  }

  /** Clears the action queue and the undo stack. */
  public function clearActionQueue()
  {
    $this->actionQueue = [];
    $this->undoStack = null;
  }

  /** Run all registered actions. */
  public function executeActions()
  {
    try {
      $this->undoStack = [];
      while (!empty($this->actionQueue)) {
        $action = array_shift($this->actionQueue); // from front
        $action->do();
        array_unshift($this->undoStack, $action); // at front
      };
    } catch (\Throwable $t) {
      throw new \Exception(
        $this->l->t(
          'Exception during execution of run-queue; number of successful actions: %d.', count($this->undoStack)),
        $t->getCode(),
        $t);
    }
  }

  /**
   * Run all undo-actions on the undo stack. The run-queue will
   * continue even if exceptions occur during undo. At completion the
   * undo-queue is empty.
   */
  public function executeUndo()
  {
    while (!empty($this->undoStack)) {
      $action = array_shift($this->undoStack);
      try {
        if (is_callable([ $action, 'undo' ])) {
          $action->undo();
        }
      } catch (\Throwable $t) {
        $this->logException($t);
      }
    }
  }
}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
