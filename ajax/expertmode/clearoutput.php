<?php

// Init owncloud

$l=OC_L10N::get('cafevdb');

// Check if we are a user
OCP\JSON::checkLoggedIn();
OCP\JSON::checkAppEnabled('cafevdb');

echo '<span style="opacity:0.5">'.$l->t('empty').'</span>';

?>
