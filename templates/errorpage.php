<?php
/**Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2014 Claus-Justus Heine <himself@claus-justus-heine.de>
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

use CAFEVDB\Config;
use CAFEVDB\L;

Config::init();

echo '<div class="cafevdb cfgerror error">';
switch ($_['error']) {
case 'notamember':
  echo L::t('CamerataDB Error: You are not a member of the dedicated orchestra
group , you are `%s\'.  If this is a first-time setup, then please
define a dedicated user-group and specify that group in the
appropriate field in the `Admin\'-section of the admin-settings. You
should also assign at least one user and one or more dedicated
group-administrators to the user-group.  Afterwards one of the
group-administrators should log-in and perform the necessary
configuration steps to finish the setup. You need administrative
privileges to create an initial orchestra group and group
administrator.',
            array(\OCP\User::getUser()));
  break;
case 'exception':
  $message = $_['exception'];
  $trace   = $_['trace'];
  $debug   = $_['debug'];
  echo L::t("CamerataDB Error: got the error message `%s'!", array($message));
  echo '<br/>';
  echo L::t("Please save the output and send it to the system administrator: `%s'. Many thanks for that!",
            array($_['admin']));
  echo '<br/>';
  echo '<br/>';
  if ($debug) {
    echo '<div class="error stacktrace">
  '.$trace.'
</div>';
  }
  break;
case 'nocalendar':
  echo L::t('CamerataDB Error: We need the calendar app to be enabled.');
  break;
case 'nocontacts':
  echo L::t('CamerataDB Error: We need the contacts app to be enabled.');
  break;
default:
  echo L::t('Something is wrong, but what?');
  break;
}
echo '</div>';

?>
