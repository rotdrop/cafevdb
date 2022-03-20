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
  public static function convert($html)
  {
    $text = self::convertLynx($html);
    if ($text === null) {
      $text = self::convertPhp($html);
    }
    return $text;
  }

  public static function convertPhp($html)
  {
    $html2Text = new PhpHtml2Text;
    $html2Text->setHtml($html);
    return $html2Text->convert();
  }

  public static function convertLynx($html)
  {
    $lynx = (new ExecutableFinder)->find('lynx');
    if (empty($lynx)) {
      return null;
    }
    $htmlConvert = new Process([
      $lynx,
      '-force_html',
      '-noreferer',
      '-nomargins',
      '-dont_wrap_pre',
      '-nolist',
      '-display_charset=utf-8',
      '-width=80',
      '-dump',
      '-stdin',
    ]);
    $htmlConvert->setInput($html);
    $htmlConvert->run();
    $text = $htmlConvert->getOutput();
    return $text;
  }
}
