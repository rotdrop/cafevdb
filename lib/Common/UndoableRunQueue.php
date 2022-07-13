<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2021, 2022 Claus-Justus Heine <himself@claus-justus-heine.de>
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

use OCP\ILogger;
use OCP\IL10N;
use OCP\AppFramework\IAppContainer;

use OCA\CAFEVDB\Exceptions\UndoableRunQueueException;

/**
 * Implement a stack of actions with undo-actions.
 */
class UndoableRunQueue
{
  use \OCA\CAFEVDB\Traits\LoggerTrait;

  /** @var IAppContainer */
  protected $appContainer;

  /** @var array */
  protected $actionQueue = [];

  /** @var array|null */
  protected $undoStack = null;

  public function __construct(
    IAppContainer $appContainer
    , ILogger $logger
    , IL10N $l10n
  ) {
    $this->appContainer = $appContainer;
    $this->logger = $logger;
    $this->l = $l10n;
  }

  /**
   * Register the given action in the run-queue.
   */
  public function register(IUndoable $action):UndoableRunQueue
  {
    $action->initialize($this->appContainer);
    $this->actionQueue[] = $action; // at end
    return $this;
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
      throw new UndoableRunQueueException(
        $this,
        $this->l->t(
          'Exception during execution of run-queue; number of successful actions: %d.', count($this->undoStack)),
        $t->getCode(),
        $t);
    }
  }

  /** @return int The total number of registered actions. */
  public function size():int
  {
    return count($this->actionQueue);
  }

  /** @return bool Whether the queue has been run. */
  public function active():bool
  {
    return is_array($this->undoStack);
  }

  /**
   * @return null|int The number of successfully executed actions, or
   * null if the queue has not yet been executed.
   */
  public function executionCount():?int
  {
    return $this->undoStack === null ? null : count($this->undoStack);
  }

  /**
   * Reset the queue in order to be executed again.
   */
  public function reset()
  {
    if ($this->undoStack === null) {
      return;
    }
    while (!empty($this->undoStack)) {
      $action = array_shift($this->undoStack); // from front
      $action->reset();
      array_unshift($this->actionQueue, $action); // at front
    };
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
