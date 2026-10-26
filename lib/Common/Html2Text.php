<?php
/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2022, 2025, 2026 Claus-Justus Heine
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

use DOMDocument;
use DOMElement;
use DOMNode;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableCell;
use Symfony\Component\Console\Helper\TableCellStyle;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Helper\TableStyle;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\ExecutableFinder;
use Html2Text\Html2Text as PhpHtml2Text;

/**
 * Html to text converter. Use the external "w3m" program if available as that
 * has the best support for HTML tables. Otherwise fall back to a pure PHP
 * implementation.
 */
class Html2Text
{
  /**
   * @var array
   *
   * List of possible html to text programs. Only w3m is supported as its
   * output has proven to be the best for tables, block quotes and
   * preformatted text, in contrast to the various PHP implementations of
   * html2text which all are loosing when it comes to tables. lynx and elinks
   * have been dropped as their output differs substantially from w3m.
   *
   * None of the external programs seems to have support for CSS ... Oops.
   */
  private const HTML_CONVERTERS = [
    'w3m' => [
      '-dump',
      '-I', 'utf-8',
      '-O', 'utf-8',
      '-T',
      'text/html',
    ],
  ];

  /** @var null|ExecutableFinder */
  private static ?ExecutableFinder $executableFinder = null;

  /**
   * Inject an executable finder, mainly for testing. Pass null to reset.
   *
   * @param null|ExecutableFinder $executableFinder
   *
   * @return void
   */
  public static function setExecutableFinder(?ExecutableFinder $executableFinder): void
  {
    self::$executableFinder = $executableFinder;
  }

  /**
   * Trim the string and remove spaces followed by a newline character.
   *
   * @param string $text
   *
   * @return string
   */
  private static function normalizeSpaces(string $text): string
  {
    $text = str_replace("\xc2\xad", '', $text); // soft hyphen
    $text = str_replace("\xc2\xa0", "\x20", $text);
    $text = trim($text);
    $text = preg_replace('/\h+([\n.,;:?!])/u', '$1', $text);

    return $text;
  }

  /**
   * @param string $html
   *
   * @return string
   */
  public static function convert(string $html):string
  {
    foreach (self::HTML_CONVERTERS as $converter => $arguments) {
      $text = self::callConverter($converter, $arguments, $html);
      if ($text !== null) {
        return self::normalizeSpaces($text);
      }
    }
    // Fallback. The PHP converter cannot handle tables, so render them
    // ourselves as preformatted text.
    $html2Text = new PhpHtml2Text(self::renderTables($html));
    return self::normalizeSpaces($html2Text->getText());
  }

  /**
   * Replace all HTML tables by preformatted blocks containing a plain-text
   * rendering of the table. Nested tables are rendered inside-out. Row spans
   * are not supported, column spans are.
   *
   * @param string $html
   *
   * @return string
   */
  private static function renderTables(string $html):string
  {
    if (stripos($html, '<table') === false) {
      return $html;
    }
    $document = new DOMDocument;
    $useErrors = libxml_use_internal_errors(true);
    $loaded = $document->loadHTML('<?xml encoding="utf-8"?>' . $html);
    libxml_clear_errors();
    libxml_use_internal_errors($useErrors);
    if (!$loaded) {
      return $html;
    }

    do {
      $table = null;
      /** @var DOMElement $candidate */
      foreach ($document->getElementsByTagName('table') as $candidate) {
        if ($candidate->getElementsByTagName('table')->length === 0) {
          $table = $candidate;
          break;
        }
      }
      if ($table === null) {
        break;
      }
      $pre = $document->createElement('pre');
      $pre->appendChild($document->createTextNode(self::renderTable($table)));
      $table->parentNode->replaceChild($pre, $table);
    } while (true);

    $result = '';
    $body = $document->getElementsByTagName('body')->item(0);
    foreach ($body?->childNodes ?? [] as $child) {
      $result .= $document->saveHTML($child);
    }
    return $result;
  }

  /**
   * @param DOMElement $table
   *
   * @return string
   */
  private static function renderTable(DOMElement $table):string
  {
    $rows = [];
    foreach ($table->getElementsByTagName('tr') as $tableRow) {
      $row = [];
      foreach ($tableRow->childNodes as $cell) {
        if (!($cell instanceof DOMElement) || !in_array(strtolower($cell->tagName), [ 'td', 'th' ])) {
          continue;
        }
        $row[] = new TableCell(
          OutputFormatter::escape(self::cellText($cell)),
          [
            'colspan' => max(1, (int)$cell->getAttribute('colspan')),
            'style' => new TableCellStyle([ 'align' => self::cellAlignment($cell) ]),
          ],
        );
      }
      if (!empty($row)) {
        $rows[] = $row;
        $rows[] = new TableSeparator;
      }
    }
    if (empty($rows)) {
      return '';
    }
    array_pop($rows);

    // Mimic the layout of w3m: no cell padding, lines between all rows.
    $style = (new TableStyle)
      ->setHorizontalBorderChars('─')
      ->setVerticalBorderChars('│')
      ->setCrossingChars('┼', '┌', '┬', '┐', '┤', '┘', '┴', '└', '├')
      ->setCellRowContentFormat('%s');

    $output = new BufferedOutput(decorated: false);
    (new Table($output))->setStyle($style)->setRows($rows)->render();

    return "\n" . rtrim($output->fetch()) . "\n";
  }

  /**
   * @param DOMNode $cell
   *
   * @return string
   */
  private static function cellText(DOMNode $cell):string
  {
    foreach (iterator_to_array($cell->getElementsByTagName('br')) as $lineBreak) {
      $lineBreak->parentNode->replaceChild($cell->ownerDocument->createTextNode("\n"), $lineBreak);
    }
    $lines = array_map(
      fn(string $line) => trim(preg_replace('/\s+/u', ' ', str_replace("\xc2\xa0", ' ', $line))),
      explode("\n", $cell->textContent),
    );
    return trim(implode("\n", $lines));
  }

  /**
   * @param DOMElement $cell
   *
   * @return string One of 'left', 'center' or 'right'.
   */
  private static function cellAlignment(DOMElement $cell):string
  {
    $style = $cell->getAttribute('style');
    if (preg_match('/text-align\s*:\s*(left|center|right)/i', $style, $matches)) {
      return strtolower($matches[1]);
    }
    $align = strtolower($cell->getAttribute('align'));
    return in_array($align, [ 'center', 'right' ]) ? $align : 'left';
  }

  /**
   * @param string $converter
   *
   * @param array $arguments
   *
   * @param string $html
   *
   * @return null|string
   */
  private static function callConverter(string $converter, array $arguments, string $html):?string
  {
    $converter = (self::$executableFinder ?? new ExecutableFinder)->find($converter);
    if (empty($converter)) {
      return null;
    }
    array_unshift($arguments, $converter);
    $htmlConvert = new Process($arguments, null, [ 'LC_ALL' => 'C.UTF-8' ]);
    $htmlConvert->setInput($html);
    $htmlConvert->run();
    $text = $htmlConvert->getOutput();
    return $text;
  }
}
