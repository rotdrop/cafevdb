<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2026 Claus-Justus Heine <himself@claus-justus-heine.de>
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

use Psr\Log\LogLevel;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Output\OutputInterface;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes;

use OCP\ILogger;
use OCA\CAFEVDB\Common\ConsoleOutput;
use OCA\CAFEVDB\Common\ConsoleLogger;

/** Test the RationalNumber class. */
#[Attributes\CoversClass(ConsoleLogger::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\AppInfo\Application::class)]
class ConsoleLoggerTest extends TestCase
{
  /**
   * {@inheritdoc}
   *
   * @return void
   */
  public function setup():void
  {
    parent::setup();
  }

  /** @return void */
  public function testLogToConsole(): void
  {
    $output = [];

    $consoleOutput = $this->getMockBuilder(ConsoleOutput::class)
      ->getMock();
    $consoleOutput->method('getErrorOutput')->willReturn($consoleOutput);
    $consoleOutput->expects($this->atLeastOnce())
      ->method('writeln')
      ->willReturnCallback(function(string $argument, int $verbosity) use (&$output) {
        $output[] = [ 'message' => $argument, 'verbosity' => $verbosity ];
      });
    $consoleOutput->expects($this->atLeastOnce())
      ->method('getVerbosity')
      ->willReturn(OutputInterface::VERBOSITY_DEBUG);
    $cloudLogger = $this->getMockBuilder(LoggerInterface::class)
      ->getMock();
    $cloudLogger->expects($this->never())
      ->method('log');

    $consoleLogger = new ConsoleLogger(
      consoleOutput: $consoleOutput,
      isCLI: true,
      logger: $cloudLogger,
    );

    $levels = [
      ILogger::DEBUG => [ 'tag' => '<info>[debug]', 'verbosity' => OutputInterface::VERBOSITY_DEBUG ],
      ILogger::INFO => [ 'tag' => '<info>[info]', 'verbosity' => OutputInterface::VERBOSITY_VERY_VERBOSE ],
      ILogger::WARN => [ 'tag' => '<info>[warning]', 'verbosity' => OutputInterface::VERBOSITY_NORMAL ],
      ILogger::ERROR => [ 'tag' => '<error>[error]', 'verbosity' => OutputInterface::VERBOSITY_NORMAL ],
      ILogger::FATAL => [ 'tag' => '<error>[emergency]', 'verbosity' => OutputInterface::VERBOSITY_NORMAL ],
    ];
    ksort($levels);
    $message = 'Message';
    foreach ($levels as $cloudLogLevel => $expected) {
      $consoleLogger->log($cloudLogLevel, $message);
      $this->assertStringStartsWith($expected['tag'], $output[$cloudLogLevel]['message']);
      $this->assertEquals($expected['verbosity'], $output[$cloudLogLevel]['verbosity']);
    }
  }

  /** @return void */
  public function testLogToCloud(): void
  {
    $output = [];

    $levels = [
      ILogger::DEBUG => LogLevel::DEBUG,
      ILogger::INFO => LogLevel::INFO,
      ILogger::WARN => LogLevel::WARNING,
      ILogger::ERROR => LogLevel::ERROR,
      ILogger::FATAL => LogLevel::EMERGENCY,
      LogLevel::CRITICAL => LogLevel::CRITICAL,
    ];

    $consoleOutput = $this->getMockBuilder(ConsoleOutput::class)
      ->getMock();
    $consoleOutput->expects($this->never())->method('writeln');
    $cloudLogger = $this->getMockBuilder(LoggerInterface::class)
      ->getMock();
    $cloudLogger->expects($this->exactly(count($levels)))
      ->method('log')
      ->willReturnCallback(function($level, string $message, array $context) use (&$output) {
        $output = compact('level', 'message', 'context');
      });

    $consoleLogger = new ConsoleLogger(
      consoleOutput: $consoleOutput,
      isCLI: false,
      logger: $cloudLogger,
    );

    $message = 'Message';
    $context = [ 'context' ];
    foreach ($levels as $cloudLogLevel => $logLevel) {
      $consoleLogger->log($cloudLogLevel, $message, $context);
      $this->assertEquals($logLevel, $output['level']);
      $this->assertEquals($message, $output['message']);
      $this->assertEqualsCanonicalizing($context, $output['context']);
    }
  }
}
