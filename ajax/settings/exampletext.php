<?php

// Init owncloud

use CAFEVDB\L;
OCP\JSON::success(array('data' => array( 'message' => L::t('Example-Text changed') )));
exit;


// Check if we are a user
OCP\JSON::checkLoggedIn();
OCP\JSON::checkAppEnabled('cafevdb');
OCP\JSON::callCheck();

// Get data
if( isset( $_POST['exampletext'] ) ) {
  $exampletext=$_POST['exampletext'];
  OCP\Config::setUserValue( OCP\USER::getUser(), 'cafevdb', 'exampletext', $exampletext );
  OCP\JSON::success(array('data' => array( 'message' => L::t('Example-Text changed') )));
  return true;
} else {
  OCP\JSON::error(array('data' => array( 'message' => L::t('Invalid request') )));
  return false;
}

?>
