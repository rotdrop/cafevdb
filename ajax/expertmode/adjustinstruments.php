<?php

// Init owncloud

// Check if we are a user
OCP\JSON::checkLoggedIn();
OCP\JSON::checkAppEnabled('cafevdb');

use CAFEVDB\L;

CAFEVDB\Admin::sanitizeInstrumentsTable();

?>
