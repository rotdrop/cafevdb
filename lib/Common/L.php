<?php
/* Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016, 2020 Claus-Justus Heine <himself@claus-justus-heine.de>
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

/**@file*/

/**CamerataDB namespace to prevent name-collisions.
 */
namespace OCA\CAFEVDB\Common;

/**Support for internationalization.
 */
class L
{
  private static $l = false;

  /**Print the translated text.
   *
   * @param $text Text to print, is finally passed to vsprintf().
   *
   * @param $parameters Defaults to an empty array. @a $parameters
   * are passed on to vsprintf().
   *
   * @return The possibly translated message.
   */
  public static function t($text, $parameters = array())
  {
    if (self::$l === false) {
      self::$l = \OC::$server->getL10N(Config::APP_NAME);
    }
    try {
      $l10nText = self::$l->t($text, $parameters);
      if (!is_string($l10nText)) {
        return $l10nText->__toString();
      } else {
        return $l10nText;
      }
    } catch (\Exception $e) {
      throw new \Exception('Cannot translate string: "'.$text.'", arguments: '.print_r($parameters, true), -1, $e);
    }
  }
}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
