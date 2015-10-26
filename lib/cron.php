<?php
/* Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2015 Claus-Justus Heine <himself@claus-justus-heine.de>
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

/**CamerataDB namespace to prevent name-collisions.
 */
namespace CAFEVDB
{

  /** Helper class for background tasks. */
  class Cron
  {
    public static function run()
    {
      $group = Config::getAppValue('usergroup', '');
      $user  = \OCP\USER::getUser();
      if (!\OC_Group::inGroup($user, $group)) {
        return;
      }

      $locale = $locale = Util::getLocale();
      \OCP\Util::writeLog(Config::APP_NAME,
                          "Running Cron Jobs, user ".$user.", locale ".$locale,
                          \OCP\Util::DEBUG);

      Config::init();

      GeoCoding::updateCountries();
      GeoCoding::updatePostalCodes(null, false, 1);
    }
  }; // class Cron

} // namespace CAFEVDB

/*
 * Local Variables: ***
 * c-basic-offset: 2 ***
 * End: ***
 */

?>
