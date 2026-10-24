<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author GitHub Copilot <223556219+Copilot@users.noreply.github.com>
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

use PHPUnit\Framework\Attributes;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

use OCA\CAFEVDB\Common\CamelCaseString;
use OCA\CAFEVDB\Service\WordSplitterService;

/** Test the string shortening of CamelCaseString with a mocked word splitter. */
#[Attributes\CoversClass(CamelCaseString::class)]
#[Attributes\CoversMethod(CamelCaseString::class, 'shorten')]
class CamelCaseStringTest extends TestCase
{
  private const SPLIT_METHOD = 'german_compound_splitter';

  private WordSplitterService&MockObject $wordSplitterService;

  private CamelCaseString $camelCaseString;

  /**
   * {@inheritdoc}
   *
   * @return void
   */
  public function setUp(): void
  {
    parent::setUp();
    $this->wordSplitterService = $this->createMock(WordSplitterService::class);
    $this->camelCaseString = new CamelCaseString($this->wordSplitterService);
  }

  /**
   * Strings which already fit are returned as-is without calling the splitter.
   *
   * @return void
   */
  public function testShortStringIsUntouched(): void
  {
    $this->wordSplitterService->expects($this->never())->method('split');

    $this->assertSame('KonzertReise', $this->camelCaseString->shorten('KonzertReise', 12));
  }

  /**
   * Each letter-only camel-case component is passed to the splitter with the
   * German compound-splitter method.
   *
   * @return void
   */
  public function testComponentsAreSplitWithGermanCompoundSplitter(): void
  {
    $words = [];
    $this->wordSplitterService
      ->expects($this->exactly(3))
      ->method('split')
      ->willReturnCallback(function(string $word, string $method = 'charsplit') use (&$words): array {
        $this->assertSame(self::SPLIT_METHOD, $method);
        $words[] = $word;
        return [$word];
      });

    $result = $this->camelCaseString->shorten('KonzertReiseHerbst', 12);

    $this->assertSame(['konzert', 'reise', 'herbst'], $words);
    $this->assertSame('KonzeReiHerb', $result);
    $this->assertSame(12, strlen($result));
  }

  /**
   * The constituents returned by the splitter are shortened individually.
   *
   * @return void
   */
  public function testCompoundConstituentsAreShortened(): void
  {
    $this->wordSplitterService
      ->expects($this->once())
      ->method('split')
      ->with('sommerkonzertreise', self::SPLIT_METHOD)
      ->willReturn(['sommer', 'konzert', 'reise']);

    $result = $this->camelCaseString->shorten('Sommerkonzertreise', 12);

    $this->assertSame('SommKonzeRei', $result);
    $this->assertSame(12, strlen($result));
  }

  /**
   * Components containing non-letters, e.g. numbers, are not split.
   *
   * @return void
   */
  public function testNonWordComponentsAreNotSplit(): void
  {
    $words = [];
    $this->wordSplitterService
      ->expects($this->exactly(2))
      ->method('split')
      ->willReturnCallback(function(string $word) use (&$words): array {
        $words[] = $word;
        return [$word];
      });

    $result = $this->camelCaseString->shorten('Projekt2026Herbst', 12);

    $this->assertSame(['projekt', 'herbst'], $words);
    $this->assertSame('Proje20Herb', $result);
  }

  /**
   * Components are never shortened below the given minimum length.
   *
   * @return void
   */
  public function testMinimumComponentLengthIsRespected(): void
  {
    $this->wordSplitterService
      ->method('split')
      ->willReturnCallback(fn(string $word): array => [$word]);

    $this->assertSame('KonzertReise', $this->camelCaseString->shorten('KonzertReise', 1, minLen: 20));
    $this->assertSame('KonRei', $this->camelCaseString->shorten('KonzertReise', 1, minLen: 3));
  }
}
