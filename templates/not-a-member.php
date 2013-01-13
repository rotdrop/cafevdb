<?php

use CAFEVDB\L;

echo '<div class="cafevdb cfgerror">';
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
default:
  echo L::t('Something is wrong, but what?');
  break;
}
echo '</div>';

?>
