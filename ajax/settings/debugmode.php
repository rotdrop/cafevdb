<?php

// Init owncloud

use CAFEVDB\L;

// Check if we are a user
OCP\JSON::checkLoggedIn();
OCP\JSON::checkAppEnabled('cafevdb');
OCP\JSON::callCheck();

// Get data
if( isset( $_POST['debugmode'] ) ) {
  $debugmode=$_POST['debugmode'];
} else {
  $debugmode='off';
}
OCP\Config::setUserValue( OCP\USER::getUser(), 'cafevdb', 'debugmode', $debugmode );
OCP\JSON::success(array('data' => array( 'message' => L::t('Debugmode changed') )));
return true;

?>
