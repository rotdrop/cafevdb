<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2021, 2022, 2023, 2024 Claus-Justus Heine
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

use InvalidArgumentException;
use Throwable;

use Psr\Log\LoggerInterface as ILogger;
use OCP\IL10N;
use OCP\AppFramework\IAppContainer;

use OCA\CAFEVDB\Exceptions\UndoableRunQueueException;

/**
 * Implement a stack of actions with undo-actions.
 */
class UndoableRunQueue
{
  use \OCA\CAFEVDB\Toolkit\Traits\LoggerTrait;

  /** @var array */
  protected $actionQueue = [];

  /** @var array|null */
  protected $undoStack = null;

  /** @var array<int, Throwable> */
  protected $runQueueExceptions = [];

  /** @var array<int, Throwable> */
  protected $undoExceptions = [];

  /** @var bool */
  protected $executing = false;

  /** {@inheritdoc} */
  public function __construct(
    protected IAppContainer $appContainer,
    protected ILogger $logger,
    protected IL10N $l,
  ) {
  }

  /**
   * Register the given action in the run-queue.
   *
   * @param Closure|IUndoable $action The action to be registered.
   *
   * @param null|Closure $undo The undo action if $action is a mere Closure.
   *
   * @return UndoableRunQueue
   *
   * @throw InvalidArgumentException If $action is neither a Closure nor an
   * IUndoable.
   */
  public function register(Closure|IUndoable $action, ?Closure $undo = null):UndoableRunQueue
  {
    if ($action instanceof Closure) {
      $action = new GenericUndoable($action, $undo);
    } elseif ($action instanceof IUndoable) {
      // fallthrouh
    } else {
      throw new InvalidArgumentException($this->l->t('$action must be a Closure or an instance of "%s".', IUndoable::class));
    }
    $action->initialize($this->appContainer);
    $this->actionQueue[] = $action; // at end
    return $this;
  }

  /**
   * Clears the action queue and the undo stack.
   *
   * @return void
   */
  public function clearActionQueue():void
  {
    $this->actionQueue = [];
    $this->undoStack = null;
    $this->runQueueExceptions = [];
    $this->undoExceptions = [];
  }

  /**
   * Run all registered actions.
   *
   * @param bool $gracefully Just log caught execptions, then retry with the
   * next action.
   *
   * @return bool \true if no exception has been caught, \false otherwise.
   *              Unless $gracefully was \true the function will throw an
   *              exception on error.
   *
   * @throws UndoableRunQueueException
   */
  public function executeActions(bool $gracefully = false):bool
  {
    if ($this->executing) {
      return true;
    }
    $this->executing = true;
    $this->runQueueExceptions = [];
    $this->undoExceptions = [];
    $this->undoStack = [];
    while (!empty($this->actionQueue)) {
      try {
        $action = array_shift($this->actionQueue); // from front
        $action->do();
        array_unshift($this->undoStack, $action); // at front
      } catch (Throwable $t) {
        $message = $this->l->t(
          'Exception during execution of run-queue: %1$s. Number of successful actions: %2$d.', [
            $t->getMessage(),
            count($this->undoStack),
          ]);
        $this->runQueueExceptions[] = $t;
        if ($gracefully) {
          $this->logException($t, $message);
        } else {
          $this->executing = false;
          throw new UndoableRunQueueException($this, $message, 0, $t);
        }
      }
    }
    $this->executing = false;
    return empty($this->runQueueExceptions) ? true : false;
  }

  /**
   * Run all undo-actions on the undo stack. The run-queue will
   * continue even if exceptions occur during undo. At completion the
   * undo-queue is empty.
   *
   * @return void
   */
  public function executeUndo():void
  {
    if ($this->executing) {
      return;
    }
    $this->executing = true;
    while (!empty($this->undoStack)) {
      $action = array_shift($this->undoStack);
      try {
        if (is_callable([ $action, 'undo' ])) {
          $action->undo();
        }
      } catch (Throwable $t) {
        $this->undoExceptions[] = $t;
        $this->logException($t);
      }
    }
    $this->executing = false;
  }

  /**
   * Get the list of thrown exceptions during the most recent execution of the
   * run-queue. It can contain more than one exception if
   * UndoableRunQueue::executeAction() had been executed with the $gracefully
   * parameter set to \true, otherwise it will contain at most one exception.
   *
   * @return array<int, Throwable>
   */
  public function getRunQueueExceptions():array
  {
    return $this->runQueueExceptions;
  }

  /**
   * Return only the first -- if any -- thrown exception during execution of
   * the run-queue. There will be at most one exception if
   * UndoableRunQueue::executeAction() had been executed with the $gracefully
   * parameter set to \false.
   *
   * @return null|Throwable
   */
  public function getRunQueueException():?Throwable
  {
    if (!empty($this->runQueueExceptions)) {
      return reset($this->runQueueExceptions);
    }
  }

  /**
   * Get the list of thrown exceptions during the most recent execution of the
   * undo-queue.
   *
   * @return array<int, Throwable>
   */
  public function getUndoExceptions():array
  {
    return $this->undoExceptions;
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
   *
   * @return void
   */
  public function reset():void
  {
    $this->runQueueExceptions = [];
    $this->undoExceptions = [];
    if ($this->undoStack === null) {
      return;
    }
    while (!empty($this->undoStack)) {
      $action = array_shift($this->undoStack); // from front
      $action->reset();
      array_unshift($this->actionQueue, $action); // at front
    };
    $this->undoStack = null;
  }
}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
