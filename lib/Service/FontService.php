<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2020, 2021, 2022 Claus-Justus Heine
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

namespace OCA\CAFEVDB\Service;

/** Truetype font-file locator, in particular for PhpOffice. */
class FontService
{
  const MS_TTF_CORE_FONTS = [
    'gentoo' => '/usr/share/fonts/corefonts/',
    'debian/ubuntu' => '/usr/share/fonts/truetype/msttcorefonts/',
  ];

  /**
   * @param string $fontName
   *
   * @return string The full path to the font file.
   */
  public static function findTrueTypeFontFile(string $fontName)
  {
    if (dirname($fontName) != '.') {
      return $fontName;
    }
    foreach (self::MS_TTF_CORE_FONTS as $fontPath)  {
      if (file_exists($fontPath . $fontName)) {
        return $fontPath . $fontName;
      }
    }
    return null;
  }
}
