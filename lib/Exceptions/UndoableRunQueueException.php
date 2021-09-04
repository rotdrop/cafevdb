<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2020, 2021 Claus-Justus Heine <himself@claus-justus-heine.de>
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

namespace OCA\CAFEVDB\Exceptions;

use OCA\CAFEVDB\Common\UndoableRunQueue;

class UndoableRunQueueException extends \RuntimeException
{
  /** @var UndoableRunQueue */
  protected $runQueue;

  public function __construct(UndoableRunQueue $queue, string $message, int $code = 0, $previous = null)
  {
    parent::__construct($message, $code, $previous);
    $this->runQueue = $queue;
  }

  public function getRunQueue():UndoableRunQueue
  {
    return $this->runQueue;
  }

  public function setRunQueue(UndoableRunQueue $queue)
  {
    $this->runQueue = $queue;
  }
}
