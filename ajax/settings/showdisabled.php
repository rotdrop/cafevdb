<?php

// Init owncloud

use CAFEVDB\L;

// Check if we are a user
OCP\JSON::checkLoggedIn();
OCP\JSON::checkAppEnabled('cafevdb');
OCP\JSON::callCheck();

// Get data
if( isset( $_POST['showdisabled'] ) ) {
  $showdisabled = $_POST['showdisabled'] == 'on' ? 'on' : 'off';
} else {
  $showdisabled = 'off';
}
OCP\Config::setUserValue(OCP\USER::getUser(), 'cafevdb', 'showdisabled', $showdisabled );
OCP\JSON::success(array('data' => array( 'message' => L::t('direct-change changed') )));
return true;

?>
