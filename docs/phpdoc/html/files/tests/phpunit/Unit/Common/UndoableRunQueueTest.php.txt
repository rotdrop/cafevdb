<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2025, 2026 Claus-Justus Heine <himself@claus-justus-heine.de>
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

namespace OCA\CAFEVDB\Tests\Unit\Common;

use Throwable;
use Exception;
use UnexpectedValueException;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes;

use OCP\AppFramework\IAppContainer;
use OCP\IL10N;
use Psr\Log\LoggerInterface;

use OCA\CAFEVDB\Common\GenericUndoable;
use OCA\CAFEVDB\Common\UndoableRunQueue;
use OCA\CAFEVDB\Exceptions;
use OCA\CAFEVDB\Tests\MockProvider;

/** Test the LoginNameSlug\generate() function. */
#[Attributes\CoversClass(UndoableRunQueue::class)]
#[Attributes\CoversClass(\OCA\CAFEVDB\Common\GenericUndoable::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\AppInfo\Application::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Common\AbstractUndoable::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Exceptions\UndoableRunQueueException::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\L10N\L10NFactory::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\Registration::class)]
class UndoableRunQueueTest extends TestCase
{
  private const ACTION_COUNT = 6;
  private const GOOD_ACTION_COUNT = 2;
  private const BAD_ACTION_COUNT = self::ACTION_COUNT - self::GOOD_ACTION_COUNT;
  private const EXCEPTION_TEXT = 'TEST';

  private MockProvider $mockProvider;

  private UndoableRunQueue $queue;

  /** {@inheritdoc} */
  public function setup(): void
  {
    /** @var MockProvider $mockProvider */
    $this->mockProvider = MockProvider::create($this);

    $this->queue = $this->getQueue();
  }

  /** @return UndoableRunQueue */
  private function getQueue(): UndoableRunQueue
  {
    return new UndoableRunQueue(
      appContainer: $this->mockProvider->getAppContainer(),
      logger: $this->mockProvider->getLoggerInterface(),
      l: $this->mockProvider->getL10N(),
    );
  }

  /** @return void */
  public function testConstruction(): void
  {
    $this->expectNotToPerformAssertions();
  }

  /** @return void */
  public function testRegister(): void
  {
    $this->queue->register(
      fn() => true,
    );
    $this->queue->register(
      fn() => true,
      // phpcs:ignore Squiz.WhiteSpace.ScopeClosingBrace.ContentBefore
      function(bool $arg): void {},
    );
    $result = $this->queue->register(
      new GenericUndoable(
        doCallback: fn() => true,
        // phpcs:ignore Squiz.WhiteSpace.ScopeClosingBrace.ContentBefore
        undoCallback: function(bool $arg): void {},
        sortOrder: 0,
      ),
    );
    $this->assertEquals(3, $this->queue->size());
    $this->assertEquals($this->queue, $result);
  }

  /** @return void */
  public function testRunWithoutExceptions(): void
  {
    $data = 0;
    for ($i = 0; $i < self::ACTION_COUNT; ++$i) {
      // phpcs:disable Squiz.WhiteSpace.ScopeClosingBrace.ContentBefore
      // phpcs:disable Squiz.Functions.MultiLineFunctionDeclaration.ContentAfterBrace
      $this->queue->register(
        function() use (&$data) { return $data++; },
        function(int $doResult) use (&$data) { $data = $doResult; },
      );
      // phpcs:enable
    }
    $this->assertEquals(self::ACTION_COUNT, $this->queue->size());

    $this->queue->reset();
    $this->assertEquals(null, $this->queue->executionCount());

    $this->queue->executeActions();
    $this->assertEquals(self::ACTION_COUNT, $data);
    $this->assertEquals(self::ACTION_COUNT, $this->queue->executionCount());
    $this->assertEquals(true, $this->queue->active());
    $this->assertEquals([], $this->queue->getRunQueueExceptions());
    $this->assertEquals(null, $this->queue->getRunQueueException());
    $this->assertEquals([], $this->queue->getUndoExceptions());

    $this->queue->reset();
    $this->assertEquals(self::ACTION_COUNT, $data);
    $this->assertEquals(null, $this->queue->executionCount());
    $this->assertEquals(false, $this->queue->active());
    $this->assertEquals([], $this->queue->getRunQueueExceptions());
    $this->assertEquals(null, $this->queue->getRunQueueException());
    $this->assertEquals([], $this->queue->getUndoExceptions());

    $this->queue->executeActions();
    $this->assertEquals(2 * self::ACTION_COUNT, $data);
    $this->queue->executeUndo();
    $this->assertEquals(self::ACTION_COUNT, $data);
    $this->assertEquals(0, $this->queue->executionCount());
    $this->assertEquals(true, $this->queue->active());
    $this->assertEquals([], $this->queue->getRunQueueExceptions());
    $this->assertEquals(null, $this->queue->getRunQueueException());
    $this->assertEquals([], $this->queue->getUndoExceptions());

    $this->assertEquals(0, $this->queue->size());
    $data = 0;
    $this->queue->executeActions();
    $this->assertEquals(0, $data);
    $this->queue->reset();
    $this->assertEquals(self::ACTION_COUNT, $this->queue->size());
    $this->queue->executeActions();
    $this->assertEquals(self::ACTION_COUNT, $data);
  }

  /** @return void */
  public function testRunWithExceptions(): void
  {
    $data = 0;
    for ($i = 0; $i < self::GOOD_ACTION_COUNT; ++$i) {
      // phpcs:disable Squiz.WhiteSpace.ScopeClosingBrace.ContentBefore
      // phpcs:disable Squiz.Functions.MultiLineFunctionDeclaration.ContentAfterBrace
      $this->queue->register(
        function() use (&$data) {
          $this->queue->executeActions();
          $this->queue->executeUndo();
          return $data++;
        },
        function(int $doResult) use (&$data) { $data = $doResult; },
      );
      // phpcs:enable
    }
    for (; $i < self::ACTION_COUNT; ++$i) {
      $this->queue->register(
        function() use (&$data) {
          $data++;
          throw new Exception(self::EXCEPTION_TEXT . $data);
        },
        function(int $doResult) use (&$data) {
          $data = $doResult;
        },
      );
    }
    $this->assertEquals(self::ACTION_COUNT, $this->queue->size());

    try {
      $this->queue->executeActions();
    } catch (Exceptions\UndoableRunQueueException $e) {
      /** @var UndoableRunQueue $runQueue */
      $runQueue = $e->getRunQueue();
      $this->assertEquals($this->queue, $runQueue);
      $this->assertEquals(self::EXCEPTION_TEXT . (self::GOOD_ACTION_COUNT + 1), $runQueue->getRunQueueException()->getMessage());
      $this->assertEquals(self::GOOD_ACTION_COUNT + 1, $data); // exception thrown after action
      $this->assertEquals(self::GOOD_ACTION_COUNT, $runQueue->executionCount());

      $runQueue->executeUndo();
      $this->assertEquals(0, $data);
    }

    $this->queue->reset();
    $this->assertEquals(self::ACTION_COUNT, $this->queue->size());
    $this->queue->executeActions(gracefully: true);
    $this->assertEquals(self::EXCEPTION_TEXT . (self::GOOD_ACTION_COUNT + 1), $this->queue->getRunQueueException()->getMessage());
    $runExceptions = $this->queue->getRunQueueExceptions();
    $this->assertEquals(self::BAD_ACTION_COUNT, count($runExceptions));
    foreach ($runExceptions as $index => $exception) {
      $this->assertEquals(self::EXCEPTION_TEXT . (self::GOOD_ACTION_COUNT + $index + 1), $exception->getMessage());
    }
    $this->assertEquals(self::ACTION_COUNT, $data); // exception thrown after action
    $this->assertEquals(self::GOOD_ACTION_COUNT, $runQueue->executionCount());

    $runQueue->executeUndo();
    $this->assertEquals(0, $data);
  }

  /** @return void */
  public function testRunWithUndoExceptions(): void
  {
    $data = 0;
    for ($i = 0; $i < self::GOOD_ACTION_COUNT; ++$i) {
      // phpcs:disable Squiz.WhiteSpace.ScopeClosingBrace.ContentBefore
      // phpcs:disable Squiz.Functions.MultiLineFunctionDeclaration.ContentAfterBrace
      $this->queue->register(
        function() use (&$data) {
          return $data++;
        },
        function(int $doResult) use (&$data) {
          $data = $doResult;
        },
      );
      // phpcs:enable
    }
    for (; $i < self::ACTION_COUNT; ++$i) {
      $this->queue->register(
        function() use (&$data) {
          return $data++;
        },
        function(int $doResult) use (&$data) {
          throw new Exception(self::EXCEPTION_TEXT . $doResult);
        },
      );
    }
    $this->assertEquals(self::ACTION_COUNT, $this->queue->size());
    $this->queue->executeActions();

    $this->assertEquals(self::ACTION_COUNT, $data);
    $this->assertEquals(self::ACTION_COUNT, $this->queue->executionCount());

    $this->queue->executeUndo();
    $this->assertEquals(0, $data);
    $undoExceptions = $this->queue->getUndoExceptions();
    $this->assertEquals(self::BAD_ACTION_COUNT, count($undoExceptions));
    foreach ($undoExceptions as $index => $exception) {
      $this->assertEquals(self::EXCEPTION_TEXT . (self::ACTION_COUNT - $index - 1), $exception->getMessage());
    }
  }

  /** @return void */
  public function testUndoWithoutDo(): void
  {
    $this->expectException(UnexpectedValueException::class);
    $this->queue->executeUndo();
  }

  /** @return void */
  public function testClearActionQueue(): void
  {
    $data = 0;
    for ($i = 0; $i < self::GOOD_ACTION_COUNT; ++$i) {
      // phpcs:disable Squiz.WhiteSpace.ScopeClosingBrace.ContentBefore
      // phpcs:disable Squiz.Functions.MultiLineFunctionDeclaration.ContentAfterBrace
      $this->queue->register(
        function() use (&$data) {
          return $data++;
        },
        function(int $doResult) use (&$data) {
          $data = $doResult;
          throw new Exception(self::EXCEPTION_TEXT . $doResult);
        },
      );
      // phpcs:enable
    }
    for (; $i < self::ACTION_COUNT; ++$i) {
      $this->queue->register(
        function() use (&$data) {
          $data++;
          throw new Exception(self::EXCEPTION_TEXT . $data);
        },
        function(int $doResult) use (&$data) {
          $data = $doResult;
        },
      );
    }
    $this->queue->executeActions(gracefully: true);
    $this->queue->executeUndo();
    $this->assertEquals(self::GOOD_ACTION_COUNT, count($this->queue->getUndoExceptions()));
    $this->assertEquals(self::BAD_ACTION_COUNT, count($this->queue->getRunQueueExceptions()));
    $this->queue->clearActionQueue();
    $this->assertEquals([], $this->queue->getUndoExceptions());
    $this->assertEquals([], $this->queue->getRunQueueExceptions());
    $this->assertEquals(null, $this->queue->getRunQueueException());
    $this->assertEquals(0, $this->queue->size());
    $this->queue->reset();
    $this->assertEquals(0, $this->queue->size());
  }
}
