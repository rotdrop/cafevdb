<?php

// Init owncloud

$l=OC_L10N::get('cafevdb');

// Check if we are a user
OCP\JSON::checkLoggedIn();
OCP\JSON::checkAppEnabled('cafevdb');
OCP\JSON::callCheck();

// Get data
if( isset( $_POST['expertmode'] ) ) {
  $expertmode=$_POST['expertmode'];
  OCP\Config::setUserValue( OCP\USER::getUser(), 'cafevdb', 'expertmode', $expertmode );
  OCP\JSON::success(array('data' => array( 'message' => $l->t('Expert-Mode changed') )));
  return true;
} else {
  OCP\JSON::error(array('data' => array( 'message' => $l->t('Invalid request') )));
  return false;
}

?>
