<?php
/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2022 Claus-Justus Heine <himself@claus-justus-heine.de>
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

use Symfony\Component\Process\Process;
use Symfony\Component\Process\ExecutableFinder;
use Html2Text\Html2Text as PhpHtml2Text;

/**
 * Html to text converter. Try to use "lynx" as that seems to be the only
 * html-to-text tool which has sort-of support for HTML tables.
 */
class Html2Text
{
  /**
   * @var array
   *
   * List of possible html to text programs. The programs listed below have at
   * least rudimentary support for HTML tables, in contrast to the various PHP
   * implementations of html2text which all are loosing when it comes to
   * tables.
   *
   * None of the external programs seems to have support for CSS ... Oops.
   */
  private const HTML_CONVERTERS = [
    'elinks' => [
      '-dump',
      '-force-html',
    ],
    'w3m' => [
      '-dump',
      '-I', 'utf-8',
      '-O', 'utf-8',
      '-T',
      'text/html',
    ],
    'lynx' => [
      '-force_html',
      '-noreferer',
      '-nomargins',
      '-dont_wrap_pre',
      '-nolist',
      '-display_charset=utf-8',
      '-width=80',
      '-dump',
      '-stdin',
    ],
  ];

  public static function convert($html)
  {
    foreach (self::HTML_CONVERTERS as $converter => $arguments) {
      $text = self::callConverter($converter, $arguments, $html);
      if ($text !== null) {
        return $text;
      }
    }
    // Fallback. Not support for tables.
    $html2Text = new PhpHtml2Text;
    $html2Text->setHtml($html);
    return $html2Text->convert();
  }

  private static function callConverter(string $converter, array $arguments, string $html):?string
  {
    $converter = (new ExecutableFinder)->find($converter);
    if (empty($converter)) {
      return null;
    }
    array_unshift($arguments, $converter);
    $htmlConvert = new Process($arguments, null, [ 'LC_ALL' => 'en_US.UTF-8' ]);
    $htmlConvert->setInput($html);
    $htmlConvert->run();
    $text = $htmlConvert->getOutput();
    return $text;
  }
}
