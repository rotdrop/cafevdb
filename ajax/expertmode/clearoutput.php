<?php

// Init owncloud

use CAFEVDB\L;

// Check if we are a user
OCP\JSON::checkLoggedIn();
OCP\JSON::checkAppEnabled('cafevdb');

echo '<span style="opacity:0.5">'.L::t('empty').'</span>';

?>
