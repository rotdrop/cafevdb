<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2020, 2021, 2022, 2024 Claus-Justus Heine
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

namespace OCA\CAFEVDB\Exceptions;

use RuntimeException;

use OCA\CAFEVDB\Common\UndoableRunQueue;

/**
 * Base class for run-queue exceptions.
 *
 * @see OCA\CAFEVDB\Common\IUndoable
 */
class UndoableRunQueueException extends RuntimeException
{
  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct(
    protected UndoableRunQueue $queue,
    string $message,
    int $code = 0,
    $previous = null,
  ) {
    parent::__construct($message, $code, $previous);
  }
  // phpcs:enable

  /** @return UndoableRunQueue */
  public function getRunQueue():UndoableRunQueue
  {
    return $this->runQueue;
  }

  /**
   * @param UndoableRunQueue $queue
   *
   * @return void
   */
  public function setRunQueue(UndoableRunQueue $queue)
  {
    $this->runQueue = $queue;
  }
}
