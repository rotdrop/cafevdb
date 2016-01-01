<?php

// Init owncloud

use CAFEVDB\L;

// Check if we are a user
OCP\JSON::checkLoggedIn();
OCP\JSON::checkAppEnabled('cafevdb');
OCP\JSON::callCheck();

// Get data
if( isset( $_POST['directchange'] ) ) {
  $directchange = $_POST['directchange'] == 'on' ? 'on' : 'off';
} else {
  $directchange = 'off';
}
OCP\Config::setUserValue(OCP\USER::getUser(), 'cafevdb', 'directchange', $directchange );
OCP\JSON::success(array('data' => array( 'message' => L::t('direct-change changed') )));
return true;

?>
