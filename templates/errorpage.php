<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2011-2014, 2020, 2021, 2023 Claus-Justus Heine
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

namespace OCA\CAFEVDB;

use OCA\CAFEVDB\Common\Util;

echo '<div class="cafevdb cfgerror error toastify">';
switch ($error) {
  case 'notamember':
    echo $l->t(
      'CamerataDB Error: You are not a member of the dedicated orchestra
group , you are `%s\'.  If this is a first-time setup, then please
define a dedicated user-group and specify that group in the
appropriate field in the `Admin\'-section of the admin-settings. You
should also assign at least one user and one or more dedicated
group-administrators to the user-group.  Afterwards one of the
group-administrators should log-in and perform the necessary
configuration steps to finish the setup. You need administrative
privileges to create an initial orchestra group and group
administrator.',
      [$userId]);
    break;
  case 'exception':
    $message = $exception;
    $trace   = is_array($trace) ? print_r($trace, true) : $trace;
    $debug   = $debug;
    echo $l->t('CamerataDB Error: got the error message "%s"!', [$message]);
    echo '<br/>';
    $adminMailto = '<a href="mailto:'.rawurlencode($admin).'?subject=cafevdb%20exception&body='.rawurlencode($message).'%20'.urlencode($trace).'">'.Util::htmlEscape($admin).'</a>';
    echo $l->t(
      'Please save the output and send it to the system administrator: "%s". Many thanks for that!',
      [$adminMailto]);
    echo '<br/>';
    echo '<br/>';
    if ($debug) {
      echo '<div class="error stacktrace"><pre>
  '.$trace.'
</pre></div>';
    }
    break;
  case 'nocalendar':
    echo $l->t('CamerataDB Error: We need the calendar app to be enabled.');
    break;
  case 'nocontacts':
    echo $l->t('CamerataDB Error: We need the contacts app to be enabled.');
    break;
  default:
    echo $l->t('Something is wrong, but what?');
    break;
}
echo '</div>';
