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
<p style="text-align: right;">und rechts ausgerichteten Text.</p>' => 'Das ist ein Beispieltext, der mit TinyMCE erzeugt wurde. Er enhält Fettschrift
und Kursivschrift und

Zeilenumbrüche und

vorformatierten
Text

    und ein Blockzitat

und rechts ausgerichteten Text.',
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
<p>&nbsp;</p>' => '┌───┬───┬───┐
│a11│a12│a13│
├───┼───┼───┤
│a21│a22│a23│
├───┼───┼───┤
│a31│a32│a33│
└───┴───┴───┘',
  ];

  /** @return void */
  public function testConversion(): void
  {
    foreach (self::HTML_TEXTS as $htmlText => $plainText) {
      $text = Html2Text::convert($htmlText);
      // echo 'A' . $text . 'B' . PHP_EOL;
      $this->assertEquals($plainText, $text);
    }
  }
}
