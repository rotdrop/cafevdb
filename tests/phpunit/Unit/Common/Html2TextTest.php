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

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\ExecutableFinder;
use PHPUnit\Framework\Attributes;

use OCA\CAFEVDB\Common\Html2Text;

/** Test the Html2Text class */
#[Attributes\CoversClass(Html2Text::class)]
class Html2TextTest extends TestCase
{
  private const HTML_TEXTS = [
    '<p>Das ist ein Beispieltext, der mit TinyMCE erzeugt wurde. Er enh&auml;lt <strong>Fettschrift </strong>und <em>Kursivschrift </em>und</p>
<p>Zeilenumbr&uuml;che und</p>
<pre>vorformatierten<br>Text</pre>
<blockquote>
<p>und ein Blockzitat</p>
</blockquote>
<p style="text-align: right;">und rechts ausgerichteten Text.</p>' => [
      'w3m' => 'Das ist ein Beispieltext, der mit TinyMCE erzeugt wurde. Er enhält Fettschrift
und Kursivschrift und

Zeilenumbrüche und

vorformatierten
Text

    und ein Blockzitat

und rechts ausgerichteten Text.',
      'lynx' => 'Das ist ein Beispieltext, der mit TinyMCE erzeugt wurde. Er enhält Fettschrift und Kursivschrift und

Zeilenumbrüche und
vorformatierten
Text

und ein Blockzitat

und rechts ausgerichteten Text.',
      'elinks' => 'Das ist ein Beispieltext, der mit TinyMCE erzeugt wurde. Er enhält
   Fettschrift und Kursivschrift und

   Zeilenumbrüche und

 vorformatierten
 Text

   > und ein Blockzitat

                                              und rechts ausgerichteten Text.',
      'php' => 'Das ist ein Beispieltext, der mit TinyMCE erzeugt wurde. Er enhält
FETTSCHRIFT und _Kursivschrift _und

Zeilenumbrüche und

vorformatierten
Text

> und ein Blockzitat

und rechts ausgerichteten Text.',
    ],
    '<table style="border-collapse: collapse; width: 100%;" border="1"><colgroup><col style="width: 33.3115%;"><col style="width: 33.3115%;"><col style="width: 33.3115%;"></colgroup>
<tbody>
<tr>
<td>a11</td>
<td>a12</td>
<td>a13</td>
</tr>
<tr>
<td>a21</td>
<td style="text-align: center;">a22</td>
<td>a23</td>
</tr>
<tr>
<td>a31</td>
<td>a32</td>
<td style="text-align: right;">a33</td>
</tr>
</tbody>
</table>
<p>&nbsp;</p>' => [
      'w3m' => '┌───┬───┬───┐
│a11│a12│a13│
├───┼───┼───┤
│a21│a22│a23│
├───┼───┼───┤
│a31│a32│a33│
└───┴───┴───┘',
      'lynx' => 'a11 a12 a13
a21 a22 a23
a31 a32 a33',
      'elinks' => '┌─────┬─────┬─────┐
   │ a11 │ a12 │ a13 │
   ├─────┼─────┼─────┤
   │ a21 │ a22 │ a23 │
   ├─────┼─────┼─────┤
   │ a31 │ a32 │ a33 │
   └─────┴─────┴─────┘',
      'php' => 'a11
 		a12
 		a13

 		a21
 		a22
 		a23

 		a31
 		a32
 		a33',
    ],
  ];

  /** @return void */
  protected function tearDown(): void
  {
    Html2Text::setExecutableFinder(null);
  }

  /**
   * @param string $only
   *
   * @return ExecutableFinder
   */
  private function stubFinder(string $only): ExecutableFinder
  {
    $real = new ExecutableFinder;
    $finder = $this->createStub(ExecutableFinder::class);
    $finder->method('find')->willReturnCallback(
      fn(string $name) => $name === $only ? $real->find($name) : null
    );
    return $finder;
  }

  /**
   * @param string $converter
   *
   * @return void
   */
  #[Attributes\DataProvider('converterProvider')]
  public function testConversion(string $converter): void
  {
    $this->useConverter($converter);
    foreach (self::HTML_TEXTS as $htmlText => $plainTexts) {
      $this->assertSame($plainTexts[$converter], Html2Text::convert($htmlText));
    }
  }

  /**
   * Skip the test if the converter is not installed, otherwise hide all
   * other converters. 'php' hides all external converters.
   *
   * @param string $converter
   *
   * @return void
   */
  private function useConverter(string $converter): void
  {
    if ($converter !== 'php' && (new ExecutableFinder)->find($converter) === null) {
      $this->markTestSkipped($converter . ' is not installed.');
    }
    Html2Text::setExecutableFinder($this->stubFinder($converter));
  }

  /**
   * @param string $converter
   *
   * @return void
   */
  #[Attributes\DataProvider('converterProvider')]
  public function testSingleConverter(string $converter): void
  {
    $this->useConverter($converter);
    $text = Html2Text::convert('<p>Hello&nbsp;<strong>W&ouml;rld</strong></p><p>Second</p>');
    $this->assertStringContainsStringIgnoringCase('Hello W' . "\u{f6}" . 'rld', $text);
    $this->assertStringContainsString('Second', $text);
    $this->assertSame($text, trim($text));
  }

  /** @return array */
  public static function converterProvider(): array
  {
    return [
      'w3m' => ['w3m'],
      'lynx' => ['lynx'],
      'elinks' => ['elinks'],
      'php' => ['php'],
    ];
  }

  /** @return void */
  public function testFallbackWithoutExternalConverters(): void
  {
    $finder = $this->createStub(ExecutableFinder::class);
    $finder->method('find')->willReturn(null);
    Html2Text::setExecutableFinder($finder);
    $text = Html2Text::convert('<p>a <b>b</b></p>');
    $this->assertStringContainsString('a', $text);
    $this->assertStringContainsStringIgnoringCase('b', $text);
    $this->assertStringNotContainsString('<', $text);
  }
}
