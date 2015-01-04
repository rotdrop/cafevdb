<?php

// Init owncloud

use CAFEVDB\L;

// Check if we are a user
OCP\JSON::checkLoggedIn();
OCP\JSON::checkAppEnabled('cafevdb');
OCP\JSON::callCheck();

// Get data
if( isset( $_POST['filtervisibility'] ) ) {
  $filtervisibility = $_POST['filtervisibility'] == 'on' ? 'on' : 'off';
} else {
  $filtervisibility = 'off';
}
OCP\Config::setUserValue(OCP\USER::getUser(), 'cafevdb', 'filtervisibility', $filtervisibility );
OCP\JSON::success(array('data' => array( 'message' => L::t('filter-visibility changed') )));
return true;

?>
